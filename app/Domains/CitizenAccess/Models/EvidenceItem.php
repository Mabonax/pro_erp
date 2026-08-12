<?php

namespace App\Domains\CitizenAccess\Models;

use App\Domains\Beneficiaries\Models\Beneficiary;
use App\Domains\Documents\Models\DocumentFile;
use App\Domains\Enterprises\Models\Enterprise;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EvidenceItem extends Model
{
    protected $table = 'citizen_access_evidence_items';

    protected $fillable = ['beneficiary_id', 'enterprise_id', 'document_file_id', 'evidence_type', 'issuer', 'issue_date', 'expiry_date', 'upload_source', 'uploaded_by_user_id', 'verification_status', 'verified_by_user_id', 'verified_at', 'rejection_reason', 'sensitivity_classification', 'retention_category', 'archive_status'];

    protected $casts = ['issue_date' => 'date:Y-m-d', 'expiry_date' => 'date:Y-m-d', 'verified_at' => 'datetime'];

    public function documentFile(): BelongsTo
    {
        return $this->belongsTo(DocumentFile::class, 'document_file_id');
    }

    public function beneficiary(): BelongsTo
    {
        return $this->belongsTo(Beneficiary::class);
    }

    public function enterprise(): BelongsTo
    {
        return $this->belongsTo(Enterprise::class);
    }
}
