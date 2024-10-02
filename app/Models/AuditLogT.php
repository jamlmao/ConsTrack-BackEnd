<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AuditLogT extends Model
{
    use HasFactory;
    protected $table = 'audit_log_task';
    protected $fillable = [
        'task_id',
        'editor_id',
        'action',
        'old_values', 
        'new_values',
    ];

    public function task()
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'editor_id');
    }
}
