<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProjectLogs extends Model
{
    use HasFactory;

    protected $fillable = [
        'action',
        'staff_id',
        'project_id',
        'old_values',
        'new_values',
    ];

    /**
     * Get the staff associated with the project log.
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * Get the project associated with the project log.
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
