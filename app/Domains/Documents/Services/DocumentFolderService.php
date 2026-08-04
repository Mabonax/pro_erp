<?php

namespace App\Domains\Documents\Services;

use App\Domains\Documents\Models\DocumentFile;
use App\Domains\Documents\Models\DocumentFolder;
use App\Domains\Documents\Repositories\DocumentFolderRepositoryInterface;
use App\Domains\Organization\Models\OrganizationDocument;
use App\Domains\Organization\Models\OrganizationProfile;
use App\Domains\Programs\Models\Program;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectLocation;
use App\Domains\Staff\Models\StaffDepartment;
use App\Domains\Stakeholders\Models\Stakeholder;
use App\Domains\Beneficiaries\Models\Beneficiary;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class DocumentFolderService
{
    public function __construct(
        protected DocumentFolderRepositoryInterface $repository,
        protected DocumentAccessService $accessService,
    ) {}

    public function workspace(User $user, ?int $selectedFolderId = null): array
    {
        $folders = $this->repository->all();
        $visibleIds = $this->visibleFolderIds($folders, $user);
        $visibleFolders = $folders->whereIn('id', $visibleIds)->values();
        $selectedFolder = $selectedFolderId
            ? $visibleFolders->firstWhere('id', $selectedFolderId)
            : null;

        if ($selectedFolderId && ! $selectedFolder) {
            abort(403);
        }

        $contentFolders = $visibleFolders
            ->where('parent_id', $selectedFolder?->id)
            ->values();

        $contentFiles = $selectedFolder
            ? $selectedFolder->files->filter(fn (DocumentFile $file) => $this->accessService->canViewFile($user, $file))->values()
            : collect();

        return [
            'tree' => $this->buildTree($visibleFolders, null),
            'selected_folder' => $selectedFolder,
            'content_folders' => $contentFolders,
            'content_files' => $contentFiles,
            'move_targets' => $visibleFolders
                ->filter(fn (DocumentFolder $folder) => $this->accessService->canManageFolder($user, $folder))
                ->values(),
        ];
    }

    public function createFolder(DocumentFolder $parent, array $data, User $actor): DocumentFolder
    {
        if (! $this->accessService->canManageFolder($actor, $parent)) {
            abort(403);
        }

        return DB::transaction(function () use ($parent, $data, $actor) {
            return $this->repository->create([
                'name' => trim((string) $data['name']),
                'parent_id' => $parent->id,
                'owner_type' => $parent->owner_type,
                'owner_id' => $parent->owner_id,
                'folder_type' => DocumentFolder::TYPE_STANDARD,
                'created_by' => $actor->id,
            ]);
        });
    }

    public function createOwnedRootFolder(array $data, User $actor): DocumentFolder
    {
        $ownerType = (string) $data['owner_type'];
        $ownerId = (int) $data['owner_id'];

        if (! $this->accessService->canManageOwner($actor, $ownerType)) {
            abort(403);
        }

        $owner = $this->resolveOwnerModel($ownerType, $ownerId);

        if (DocumentFolder::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->exists()) {
            throw ValidationException::withMessages([
                'owner_id' => ['A workspace for this owner already exists.'],
            ]);
        }

        return DB::transaction(function () use ($data, $actor, $ownerType, $ownerId) {
            $group = $this->ensureLibraryGroup($this->groupNameForOwnerType($ownerType), $actor);

            return $this->repository->create([
                'name' => trim((string) $data['name']),
                'parent_id' => $group->id,
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'folder_type' => DocumentFolder::TYPE_STANDARD,
                'created_by' => $actor->id,
            ]);
        });
    }

    public function firstOrCreateOwnedRootFolder(string $ownerType, int $ownerId, string $name, User $actor): DocumentFolder
    {
        if (! $this->accessService->canManageOwner($actor, $ownerType)) {
            abort(403);
        }

        $this->resolveOwnerModel($ownerType, $ownerId);

        $existing = DocumentFolder::query()
            ->where('owner_type', $ownerType)
            ->where('owner_id', $ownerId)
            ->where('folder_type', DocumentFolder::TYPE_STANDARD)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($actor, $ownerType, $ownerId, $name) {
            $group = $this->ensureLibraryGroup($this->groupNameForOwnerType($ownerType), $actor);

            return $this->repository->create([
                'name' => $name,
                'parent_id' => $group->id,
                'owner_type' => $ownerType,
                'owner_id' => $ownerId,
                'folder_type' => DocumentFolder::TYPE_STANDARD,
                'created_by' => $actor->id,
            ]);
        });
    }

    public function renameFolder(DocumentFolder $folder, string $name, User $actor): DocumentFolder
    {
        if (! $this->accessService->canManageFolder($actor, $folder) || $folder->isLibraryGroup()) {
            abort(403);
        }

        return $this->repository->update($folder, [
            'name' => trim($name),
        ]);
    }

    public function moveFolder(DocumentFolder $folder, DocumentFolder $targetParent, User $actor): DocumentFolder
    {
        if (! $this->accessService->canManageFolder($actor, $folder) || ! $this->accessService->canManageFolder($actor, $targetParent)) {
            abort(403);
        }

        if ($folder->isLibraryGroup()) {
            throw ValidationException::withMessages([
                'folder' => ['System library groups cannot be moved.'],
            ]);
        }

        if ($folder->id === $targetParent->id || $this->descendantIds($folder)->contains($targetParent->id)) {
            throw ValidationException::withMessages([
                'parent_id' => ['Choose a destination outside the current folder tree.'],
            ]);
        }

        if ($folder->owner_type !== $targetParent->owner_type || (int) $folder->owner_id !== (int) $targetParent->owner_id) {
            throw ValidationException::withMessages([
                'parent_id' => ['Folders can only be moved within the same ownership scope.'],
            ]);
        }

        return $this->repository->update($folder, [
            'parent_id' => $targetParent->id,
        ]);
    }

    public function deleteFolder(DocumentFolder $folder, User $actor): void
    {
        if (! $this->accessService->canManageFolder($actor, $folder) || $folder->isLibraryGroup()) {
            abort(403);
        }

        DB::transaction(function () use ($folder) {
            $allFolders = $this->repository->all();
            $folderIds = collect([$folder->id])->merge($this->descendantIds($folder, $allFolders))->unique()->values();
            $publishedIds = DocumentFile::query()
                ->whereIn('folder_id', $folderIds)
                ->pluck('id');

            if ($publishedIds->isNotEmpty() && OrganizationDocument::query()
                ->where('source_type', DocumentFile::class)
                ->whereIn('source_id', $publishedIds)
                ->exists()) {
                throw ValidationException::withMessages([
                    'folder' => ['Folders with files published to the organization vault cannot be deleted.'],
                ]);
            }

            DocumentFile::query()
                ->whereIn('folder_id', $folderIds)
                ->get()
                ->each(function (DocumentFile $file) {
                    Storage::disk($file->disk)->delete($file->file_path);
                    $file->delete();
                });

            DocumentFolder::query()
                ->whereIn('id', $folderIds)
                ->orderByDesc('parent_id')
                ->get()
                ->each(fn (DocumentFolder $item) => $item->delete());
        });
    }

    public function createDefaultProgramFolders(Program $program, ?User $actor = null): void
    {
        DB::transaction(function () use ($program, $actor) {
            $programsRoot = $this->ensureLibraryGroup('Programs', $actor);
            $root = $this->repository->create([
                'name' => $program->title,
                'parent_id' => $programsRoot->id,
                'owner_type' => Program::class,
                'owner_id' => $program->id,
                'folder_type' => DocumentFolder::TYPE_PROGRAM_ROOT,
                'created_by' => $actor?->id,
            ]);

            foreach (['Reports', 'Marketing', 'Deliverables'] as $name) {
                $this->repository->create([
                    'name' => $name,
                    'parent_id' => $root->id,
                    'owner_type' => Program::class,
                    'owner_id' => $program->id,
                    'folder_type' => DocumentFolder::TYPE_STANDARD,
                    'created_by' => $actor?->id,
                ]);
            }
        });
    }

    public function createDefaultProjectFolders(Project $project, ?User $actor = null): void
    {
        DB::transaction(function () use ($project, $actor) {
            $projectsRoot = $this->ensureLibraryGroup('Projects', $actor);
            $root = $this->repository->create([
                'name' => $project->name,
                'parent_id' => $projectsRoot->id,
                'owner_type' => Project::class,
                'owner_id' => $project->id,
                'folder_type' => DocumentFolder::TYPE_PROJECT_ROOT,
                'created_by' => $actor?->id,
            ]);

            foreach (['Sponsors', 'Attendance', 'Reports'] as $name) {
                $this->repository->create([
                    'name' => $name,
                    'parent_id' => $root->id,
                    'owner_type' => Project::class,
                    'owner_id' => $project->id,
                    'folder_type' => DocumentFolder::TYPE_STANDARD,
                    'created_by' => $actor?->id,
                ]);
            }
        });
    }

    protected function ensureLibraryGroup(string $name, ?User $actor = null): DocumentFolder
    {
        return DocumentFolder::query()->firstOrCreate(
            [
                'parent_id' => null,
                'folder_type' => DocumentFolder::TYPE_LIBRARY_GROUP,
                'name' => $name,
            ],
            [
                'owner_type' => null,
                'owner_id' => null,
                'created_by' => $actor?->id,
            ]
        );
    }

    protected function resolveOwnerModel(string $ownerType, int $ownerId): object
    {
        $modelClass = match ($ownerType) {
            OrganizationProfile::class,
            Program::class,
            Project::class,
            ProjectLocation::class,
            Beneficiary::class,
            Stakeholder::class,
            StaffDepartment::class => $ownerType,
            default => throw ValidationException::withMessages([
                'owner_type' => ['Choose a valid workspace owner type.'],
            ]),
        };

        return $modelClass::query()->findOrFail($ownerId);
    }

    protected function groupNameForOwnerType(string $ownerType): string
    {
        return match ($ownerType) {
            OrganizationProfile::class => 'Organization',
            Program::class => 'Programs',
            Project::class => 'Projects',
            ProjectLocation::class => 'Project Locations',
            Beneficiary::class => 'Beneficiaries',
            Stakeholder::class => 'Stakeholders',
            StaffDepartment::class => 'HR',
            default => 'Documents',
        };
    }

    protected function visibleFolderIds(Collection $folders, User $user): Collection
    {
        $baseVisible = $folders
            ->filter(fn (DocumentFolder $folder) => ! $folder->isLibraryGroup() && $this->accessService->canViewFolder($user, $folder))
            ->pluck('id')
            ->values();

        $visible = $baseVisible->flip();

        foreach ($baseVisible as $folderId) {
            $current = $folders->firstWhere('id', $folderId);

            while ($current?->parent_id) {
                $visible[(int) $current->parent_id] = true;
                $current = $folders->firstWhere('id', $current->parent_id);
            }
        }

        return collect(array_keys($visible->all()))->map(fn ($id) => (int) $id)->values();
    }

    protected function buildTree(Collection $folders, ?int $parentId): array
    {
        return $folders
            ->where('parent_id', $parentId)
            ->sortBy('name')
            ->map(fn (DocumentFolder $folder) => [
                'id' => $folder->id,
                'name' => $folder->name,
                'folder_type' => $folder->folder_type,
                'owner_type' => $folder->owner_type,
                'owner_id' => $folder->owner_id,
                'children' => $this->buildTree($folders, $folder->id),
            ])
            ->values()
            ->all();
    }

    protected function descendantIds(DocumentFolder $folder, ?Collection $folders = null): Collection
    {
        $folders ??= $this->repository->all();

        $descendants = collect();
        $stack = [$folder->id];

        while ($stack !== []) {
            $currentId = array_pop($stack);
            $children = $folders->where('parent_id', $currentId);

            foreach ($children as $child) {
                $descendants->push($child->id);
                $stack[] = $child->id;
            }
        }

        return $descendants->unique()->values();
    }
}
