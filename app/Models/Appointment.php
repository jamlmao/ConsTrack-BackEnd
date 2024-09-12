<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Appointment extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_id',
        'client_id',
        'description',
        'appointment_datetime',
        'status',
    ];

    public function staff()
    {
        return $this->belongsTo(StaffProfile::class, 'staff_id');
    }

    public function client()
    {
        return $this->belongsTo(ClientProfile::class, 'client_id');
    }
}
