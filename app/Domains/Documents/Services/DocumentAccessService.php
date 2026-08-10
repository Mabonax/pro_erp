<?php

namespace App\Domains\Documents\Services;

use App\Domains\Beneficiaries\Models\Beneficiary;
use App\Domains\Documents\Models\DocumentFile;
use App\Domains\Documents\Models\DocumentFolder;
use App\Domains\Enterprises\Models\Enterprise;
use App\Domains\Organization\Models\OrganizationProfile;
use App\Domains\Programs\Models\Program;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectLocation;
use App\Domains\Staff\Models\StaffDepartment;
use App\Domains\Stakeholders\Models\Stakeholder;
use App\Models\User;
use App\Policies\Concerns\InteractsWithDomainPermissions;

class DocumentAccessService
{
    use InteractsWithDomainPermissions;

    public function canViewAny(User $user): bool
    {
        foreach (['organization', 'programs', 'projects', 'beneficiaries', 'citizen-access', 'stakeholders', 'human-resources'] as $domain) {
            if ($this->canViewDomain($user, $domain)) {
                return true;
            }
        }

        return false;
    }

    public function canViewFolder(User $user, DocumentFolder $folder): bool
    {
        if ($folder->isLibraryGroup()) {
            return $this->canViewAny($user);
        }

        return $this->canViewOwner($user, $folder->owner_type);
    }

    public function canManageFolder(User $user, DocumentFolder $folder): bool
    {
        if ($folder->isLibraryGroup()) {
            return false;
        }

        return $this->canManageOwner($user, $folder->owner_type);
    }

    public function canViewFile(User $user, DocumentFile $file): bool
    {
        return $this->canViewFolder($user, $file->folder);
    }

    public function canManageFile(User $user, DocumentFile $file): bool
    {
        return $this->canManageFolder($user, $file->folder);
    }

    public function canViewOwner(User $user, ?string $ownerType): bool
    {
        return match ($ownerType) {
            OrganizationProfile::class => $this->canViewDomain($user, 'organization'),
            Program::class => $this->canViewDomain($user, 'programs'),
            Project::class, ProjectLocation::class => $this->canViewDomain($user, 'projects'),
            Beneficiary::class => $this->canViewDomain($user, 'beneficiaries'),
            Enterprise::class => $this->canViewDomain($user, 'citizen-access'),
            Stakeholder::class => $this->canViewDomain($user, 'stakeholders'),
            StaffDepartment::class => $this->canViewDomain($user, 'human-resources'),
            null => false,
            default => false,
        };
    }

    public function canManageOwner(User $user, ?string $ownerType): bool
    {
        return match ($ownerType) {
            OrganizationProfile::class => $this->canManageDomain($user, 'organization'),
            Program::class => $this->canManageDomain($user, 'programs'),
            Project::class, ProjectLocation::class => $this->canManageDomain($user, 'projects'),
            Beneficiary::class => $this->canManageDomain($user, 'beneficiaries'),
            Enterprise::class => $this->canManageDomain($user, 'citizen-access'),
            Stakeholder::class => $this->canManageDomain($user, 'stakeholders'),
            StaffDepartment::class => $this->canManageDomain($user, 'human-resources'),
            null => false,
            default => false,
        };
    }
}
