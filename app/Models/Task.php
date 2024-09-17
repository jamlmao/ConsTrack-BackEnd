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
        'pt_file_task',
        'pt_allocated_budget',
        'update_img',
        'update_file',
        'week1_img',
        'week2_img',
        'week3_img',
        'week4_img',
        'week5_img',
        'category_id',

    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function resources()
    {
        return $this->hasMany(Resources::class, 'task_id');
    }
}
