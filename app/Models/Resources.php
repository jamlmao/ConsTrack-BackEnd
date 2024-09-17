<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Resources extends Model
{
    use HasFactory;

   protected $fillable = [
        'task_id',
        'resource_name',
        'qty',
        'unit_cost',
        'total_cost',
        'total_used_resources',
    ];

    public function projectTask()
    {
        return $this->belongsTo(ProjectTask::class, 'task_id');
    }

}
