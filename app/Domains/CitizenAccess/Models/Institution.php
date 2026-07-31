<?php

namespace App\Domains\CitizenAccess\Models;

use Illuminate\Database\Eloquent\Model;

class Institution extends Model
{
    protected $table = 'citizen_access_institutions';

    protected $fillable = ['name', 'institution_type', 'province', 'official_website', 'notes', 'is_active'];

    protected $casts = ['is_active' => 'boolean'];
}
