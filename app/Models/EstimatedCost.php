<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EstimatedCost extends Model
{
    use HasFactory;
    protected $table = 'task_estimated_values';

    protected $fillable = [
        'task_id',
        'estimated_resource_value',
    ];

    // Define the relationship with the Task model
    public function task()
    {
        return $this->belongsTo(Task::class);
    }
}
