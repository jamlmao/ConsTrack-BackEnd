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
        'category_id',
        'isRemoved'

    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function resources()
    {
        return $this->hasMany(Resources::class, 'task_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function taskUpdatePictures()
    {
        return $this->hasMany(TaskUpdatePictures::class);
    }




}






