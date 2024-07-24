<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Task extends Model
{
    protected $table = 'project_tasks';

    protected $fillable = [
        'project_id',
        'pt_status',
        'pt_task_name',
        'pt_updated_at',
        'pt_completion_date',
        'pt_starting_date',
        'pt_photo_task',
        'pt_used_budget',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
