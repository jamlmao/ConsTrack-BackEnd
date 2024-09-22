<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;
    protected $fillable = [
        'category_name',
        'c_allocated_budget'
    
    ];

    public function projectTasks()
    {
        return $this->hasMany(ProjectTask::class);
    }
}
