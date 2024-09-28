<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AvailableDate extends Model
{
    use HasFactory;
    protected $fillable = ['staff_id', 'available_date'];

    public function staff()
    {
        return $this->belongsTo(StaffProfile::class);
    }
}
