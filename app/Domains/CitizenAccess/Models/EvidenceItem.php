<?php

namespace App\Domains\CitizenAccess\Models;

use Illuminate\Database\Eloquent\Model;

class EvidenceItem extends Model
{
    protected $table = 'citizen_access_evidence_items';

    protected $fillable = ['beneficiary_id', 'document_file_id', 'evidence_type', 'issuer', 'issue_date', 'expiry_date', 'upload_source', 'uploaded_by_user_id', 'verification_status', 'verified_by_user_id', 'verified_at', 'rejection_reason', 'sensitivity_classification', 'retention_category', 'archive_status'];

    protected $casts = ['issue_date' => 'date:Y-m-d', 'expiry_date' => 'date:Y-m-d', 'verified_at' => 'datetime'];
}
