<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UsedResources extends Model
{
    use HasFactory;
    protected $fillable = [
        'resource_id',
        'used_resource_name',
        'resource_qty',
        'total_used_resources',
    ];

    public function resources()
    {
        return $this->belongsTo(Resources::class);
    }
}
