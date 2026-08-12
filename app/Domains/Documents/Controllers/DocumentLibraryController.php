<?php

namespace App\Domains\Documents\Controllers;

use App\Domains\Documents\Models\DocumentFile;
use App\Domains\Documents\Models\DocumentFolder;
use App\Domains\Documents\Policies\DocumentFolderPolicy;
use App\Domains\Documents\Requests\MoveDocumentFileRequest;
use App\Domains\Documents\Requests\MoveDocumentFolderRequest;
use App\Domains\Documents\Requests\PublishDocumentFileToVaultRequest;
use App\Domains\Documents\Requests\RenameDocumentFileRequest;
use App\Domains\Documents\Requests\RenameDocumentFolderRequest;
use App\Domains\Documents\Requests\StoreDocumentRootFolderRequest;
use App\Domains\Documents\Requests\StoreDocumentFolderRequest;
use App\Domains\Documents\Requests\UploadDocumentFileRequest;
use App\Domains\Documents\Resources\DocumentFileResource;
use App\Domains\Documents\Resources\DocumentFolderResource;
use App\Domains\Organization\Models\OrganizationProfile;
use App\Domains\Documents\Services\DocumentFileService;
use App\Domains\Documents\Services\DocumentFolderService;
use App\Domains\Organization\Enums\OrganizationDocumentSlot;
use App\Domains\Organization\Enums\OrganizationDocumentType;
use App\Domains\Organization\Models\OrganizationDocument;
use App\Domains\Organization\Services\OrganizationDocumentVaultService;
use App\Domains\Programs\Models\Program;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectLocation;
use App\Domains\Staff\Models\StaffDepartment;
use App\Domains\Beneficiaries\Models\Beneficiary;
use App\Domains\Stakeholders\Models\Stakeholder;
use App\Domains\Organization\Services\OrganizationProfileService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class DocumentLibraryController extends Controller
{
    public function __construct(
        protected DocumentFolderService $folderService,
        protected DocumentFileService $fileService,
        protected OrganizationDocumentVaultService $vaultService,
        protected OrganizationProfileService $profileService,
    ) {}

    public function index(): Response
    {
        $this->authorize('viewAny', DocumentFolder::class);
        $user = request()->user();

        $workspace = $this->folderService->workspace(
            $user,
            request()->integer('folder') ?: null
        );

        return Inertia::render('Organization/DocumentLibrary/Index', [
            'tree' => $workspace['tree'],
            'selectedFolder' => $workspace['selected_folder'] ? new DocumentFolderResource($workspace['selected_folder']) : null,
            'folders' => DocumentFolderResource::collection($workspace['content_folders']),
            'files' => DocumentFileResource::collection($workspace['content_files']),
            'moveTargets' => DocumentFolderResource::collection($workspace['move_targets']),
            'departments' => StaffDepartment::query()->orderBy('name')->get(['id', 'name']),
            'users' => User::query()->orderBy('name')->get(['id', 'name']),
            'vaultDocumentTypes' => OrganizationDocumentType::options(),
            'vaultSlotOptions' => OrganizationDocumentSlot::options(),
            'canPublishToVault' => $user?->can('create', OrganizationDocument::class) ?? false,
            'ownerOptions' => $this->ownerOptions($user),
        ]);
    }

    public function storeRootFolder(StoreDocumentRootFolderRequest $request)
    {
        $folder = $this->folderService->createOwnedRootFolder($request->validated(), $request->user());

        return redirect()->route('organization.document-library.index', ['folder' => $folder->id])
            ->with('success', 'Workspace created.');
    }

    public function storeFolder(StoreDocumentFolderRequest $request)
    {
        $parent = DocumentFolder::query()->findOrFail((int) $request->validated('parent_id'));
        $this->authorize('update', $parent);

        $folder = $this->folderService->createFolder($parent, $request->validated(), $request->user());

        return redirect()->route('organization.document-library.index', ['folder' => $folder->parent_id])
            ->with('success', 'Folder created.');
    }

    public function renameFolder(RenameDocumentFolderRequest $request, DocumentFolder $folder)
    {
        $this->authorize('update', $folder);

        $this->folderService->renameFolder($folder, (string) $request->validated('name'), $request->user());

        return redirect()->back()->with('success', 'Folder renamed.');
    }

    public function moveFolder(MoveDocumentFolderRequest $request, DocumentFolder $folder)
    {
        $this->authorize('update', $folder);

        $targetParent = DocumentFolder::query()->findOrFail((int) $request->validated('parent_id'));
        $this->authorize('update', $targetParent);

        $this->folderService->moveFolder($folder, $targetParent, $request->user());

        return redirect()->back()->with('success', 'Folder moved.');
    }

    public function destroyFolder(DocumentFolder $folder)
    {
        $this->authorize('delete', $folder);

        $parentId = $folder->parent_id;
        $this->folderService->deleteFolder($folder, request()->user());

        return redirect()->route('organization.document-library.index', ['folder' => $parentId])
            ->with('success', 'Folder deleted.');
    }

    public function storeFile(UploadDocumentFileRequest $request)
    {
        $folder = DocumentFolder::query()->findOrFail((int) $request->validated('folder_id'));
        $this->authorize('view', $folder);

        $this->fileService->uploadFile($folder, $request->validated(), $request->user());

        return redirect()->route('organization.document-library.index', ['folder' => $folder->id])
            ->with('success', 'File uploaded.');
    }

    public function renameFile(RenameDocumentFileRequest $request, DocumentFile $file)
    {
        $this->authorize('update', $file);

        $this->fileService->renameFile($file, $request->validated(), $request->user());

        return redirect()->back()->with('success', 'File updated.');
    }

    public function moveFile(MoveDocumentFileRequest $request, DocumentFile $file)
    {
        $this->authorize('update', $file);

        $targetFolder = DocumentFolder::query()->findOrFail((int) $request->validated('folder_id'));
        $this->authorize('update', $targetFolder);

        $this->fileService->moveFile($file, $targetFolder, $request->user());

        return redirect()->back()->with('success', 'File moved.');
    }

    public function destroyFile(DocumentFile $file)
    {
        $this->authorize('delete', $file);

        $folderId = $file->folder_id;
        $this->fileService->deleteFile($file, request()->user());

        return redirect()->route('organization.document-library.index', ['folder' => $folderId])
            ->with('success', 'File deleted.');
    }

    public function downloadFile(DocumentFile $file): HttpResponse
    {
        $this->authorize('view', $file);

        return $this->fileService->downloadFile($file, request()->user());
    }

    public function previewFile(DocumentFile $file): HttpResponse
    {
        $this->authorize('view', $file);

        return $this->fileService->previewFile($file, request()->user());
    }

    public function publishToVault(PublishDocumentFileToVaultRequest $request, DocumentFile $file)
    {
        $this->authorize('view', $file);
        $this->authorize('create', OrganizationDocument::class);

        $this->vaultService->publishFromDocumentFile($file, $request->validated(), $request->user());

        return redirect()->back()->with('success', 'File published to the organization vault.');
    }

    protected function ownerOptions(User $user): array
    {
        $profile = $this->profileService->getProfile();

        return collect([
            [
                'label' => 'Organization',
                'owner_type' => OrganizationProfile::class,
                'items' => [[
                    'id' => $profile->id,
                    'name' => $profile->name ?: 'Organization',
                ]],
            ],
            [
                'label' => 'Programs',
                'owner_type' => Program::class,
                'items' => Program::query()->orderBy('title')->get(['id', 'title'])->map(fn (Program $program) => [
                    'id' => $program->id,
                    'name' => $program->title,
                ])->values()->all(),
            ],
            [
                'label' => 'Projects',
                'owner_type' => Project::class,
                'items' => Project::query()->orderBy('name')->get(['id', 'name'])->map(fn (Project $project) => [
                    'id' => $project->id,
                    'name' => $project->name,
                ])->values()->all(),
            ],
            [
                'label' => 'Project Locations',
                'owner_type' => ProjectLocation::class,
                'items' => ProjectLocation::query()->with('project:id,name')->orderBy('id')->get(['id', 'project_id', 'training_venue_address'])->map(
                    fn (ProjectLocation $location) => [
                        'id' => $location->id,
                        'name' => trim(($location->project?->name ? $location->project->name.' - ' : '').($location->training_venue_address ?: 'Location #'.$location->id)),
                    ]
                )->values()->all(),
            ],
            [
                'label' => 'Beneficiaries',
                'owner_type' => Beneficiary::class,
                'items' => Beneficiary::query()->orderBy('name')->orderBy('surname')->get(['id', 'name', 'surname'])->map(fn (Beneficiary $beneficiary) => [
                    'id' => $beneficiary->id,
                    'name' => trim($beneficiary->name.' '.$beneficiary->surname),
                ])->values()->all(),
            ],
            [
                'label' => 'Stakeholders',
                'owner_type' => Stakeholder::class,
                'items' => Stakeholder::query()->orderBy('organization_name')->orderBy('name')->get(['id', 'organization_name', 'name'])->map(
                    fn (Stakeholder $stakeholder) => [
                        'id' => $stakeholder->id,
                        'name' => trim(($stakeholder->organization_name ? $stakeholder->organization_name.' - ' : '').$stakeholder->name),
                    ]
                )->values()->all(),
            ],
            [
                'label' => 'HR Departments',
                'owner_type' => StaffDepartment::class,
                'items' => StaffDepartment::query()->orderBy('name')->get(['id', 'name'])->map(fn (StaffDepartment $department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                ])->values()->all(),
            ],
        ])->filter(fn (array $group) => app(\App\Domains\Documents\Services\DocumentAccessService::class)->canManageOwner($user, $group['owner_type']))
            ->values()
            ->all();
    }
}
