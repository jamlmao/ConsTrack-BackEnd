<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaskUpdatePictures extends Model
{
    use HasFactory;
    protected $fillable = [
        'task_id',
        'tup_photo',
    ];

    public function projectTask()
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }

    
}
