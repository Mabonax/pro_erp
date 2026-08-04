<?php

namespace App\Domains\Beneficiaries\Models;

use App\Domains\Members\Models\Member;
use App\Domains\Programs\Models\Program;
use App\Domains\CitizenAccess\Models\EvidenceItem;
use App\Domains\CitizenAccess\Models\SupportCase;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Models\ProjectEnrollment;
use App\Domains\Projects\Models\ProjectMilestoneAssessment;
use App\Domains\ServiceDelivery\Models\BeneficiaryPlacement;
use App\Domains\ServiceDelivery\Models\ServiceAttendance;
use App\Models\NextOfKin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Beneficiary extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $table = 'beneficiaries';

    protected $fillable = [
        'member_id',
        'beneficiary_number',
        'name',
        'surname',
        'dob',
        'age',
        'id_number',
        'email',
        'phone',
        'gender',
        'project_id',
        'program_id',
        'enrolment_date',
        'exit_date',
        'participation_status',
        'placement_status',
        'street_address',
        'address_line_2',
        'city',
        'province_id',
        'postal_code',
        'highest_qualification',
        'attendance_status',
        'next_of_kin_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'dob' => 'date:Y-m-d',
        'enrolment_date' => 'date:Y-m-d',
        'exit_date' => 'date:Y-m-d',
    ];

    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    public function nextOfKin()
    {
        return $this->belongsTo(NextOfKin::class, 'next_of_kin_id');
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function program()
    {
        return $this->belongsTo(Program::class);
    }

    public function projectEnrollments()
    {
        return $this->hasMany(ProjectEnrollment::class);
    }

    public function supportCases()
    {
        return $this->hasMany(SupportCase::class);
    }

    public function evidenceItems()
    {
        return $this->hasMany(EvidenceItem::class);
    }

    public function milestoneAssessments()
    {
        return $this->hasMany(ProjectMilestoneAssessment::class);
    }

    public function placements()
    {
        return $this->hasMany(BeneficiaryPlacement::class);
    }

    public function attendanceRecords()
    {
        return $this->hasMany(ServiceAttendance::class);
    }
}
