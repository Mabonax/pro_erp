<?php

namespace App\Domains\Documents\Services;

use App\Domains\Documents\Models\DocumentFile;
use App\Domains\Documents\Models\DocumentFolder;
use App\Domains\Documents\Repositories\DocumentFileRepositoryInterface;
use App\Domains\Organization\Models\OrganizationDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentFileService
{
    protected const DISK = 'document_library';

    public function __construct(
        protected DocumentFileRepositoryInterface $repository,
        protected DocumentAccessService $accessService,
    ) {}

    public function uploadFile(DocumentFolder $folder, array $data, User $actor): DocumentFile
    {
        if ($folder->isLibraryGroup()) {
            throw ValidationException::withMessages([
                'folder_id' => ['Choose a workspace folder before uploading files.'],
            ]);
        }

        if (! $this->accessService->canViewFolder($actor, $folder)) {
            abort(403);
        }

        $file = $data['file'] ?? null;

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages([
                'file' => ['Upload a valid file.'],
            ]);
        }

        return DB::transaction(function () use ($folder, $data, $actor, $file) {
            $title = trim((string) ($data['title'] ?? ''));
            $title = $title !== '' ? $title : (string) Str::of($file->getClientOriginalName())->beforeLast('.');
            $path = $file->storeAs(
                'document-library/'.$folder->id,
                Str::uuid()->toString().($file->getClientOriginalExtension() ? '.'.$file->getClientOriginalExtension() : ''),
                self::DISK
            );

            return $this->repository->create([
                'folder_id' => $folder->id,
                'title' => $title,
                'description' => $data['description'] ?? null,
                'disk' => self::DISK,
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'size_bytes' => $file->getSize(),
                'version' => $this->repository->nextVersion($folder, $title),
                'uploaded_by' => $actor->id,
            ]);
        });
    }

    public function moveFile(DocumentFile $file, DocumentFolder $targetFolder, User $actor): DocumentFile
    {
        if (! $this->accessService->canManageFile($actor, $file) || ! $this->accessService->canManageFolder($actor, $targetFolder)) {
            abort(403);
        }

        if ($file->folder->owner_type !== $targetFolder->owner_type || (int) $file->folder->owner_id !== (int) $targetFolder->owner_id) {
            throw ValidationException::withMessages([
                'folder_id' => ['Files can only be moved within the same ownership scope.'],
            ]);
        }

        return $this->repository->update($file, [
            'folder_id' => $targetFolder->id,
        ]);
    }

    public function renameFile(DocumentFile $file, array $data, User $actor): DocumentFile
    {
        if (! $this->accessService->canManageFile($actor, $file)) {
            abort(403);
        }

        return $this->repository->update($file, [
            'title' => trim((string) $data['title']),
            'description' => $data['description'] ?? $file->description,
        ]);
    }

    public function deleteFile(DocumentFile $file, User $actor): void
    {
        if (! $this->accessService->canManageFile($actor, $file)) {
            abort(403);
        }

        if (OrganizationDocument::query()
            ->where('source_type', DocumentFile::class)
            ->where('source_id', $file->id)
            ->exists()) {
            throw ValidationException::withMessages([
                'file' => ['Files published to the organization vault cannot be deleted.'],
            ]);
        }

        DB::transaction(function () use ($file) {
            Storage::disk($file->disk)->delete($file->file_path);
            $this->repository->delete($file);
        });
    }

    public function downloadFile(DocumentFile $file, User $actor)
    {
        if (! $this->accessService->canViewFile($actor, $file)) {
            abort(403);
        }

        return Storage::disk($file->disk)->download($file->file_path, $file->original_name);
    }

    public function previewFile(DocumentFile $file, User $actor)
    {
        if (! $this->accessService->canViewFile($actor, $file)) {
            abort(403);
        }

        return response()->file(Storage::disk($file->disk)->path($file->file_path), [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.$file->original_name.'"',
        ]);
    }
}
