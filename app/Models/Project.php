<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;


    protected $table = 'projects';

    // Specify the fillable fields
    protected $fillable = [
        'site_location',
        'client_id',
        'status',
        'updated_at',
        'completion_date',
        'staff_id',
        'starting_date',
        'totalBudget',
        'pj_image',
        'pj_pdf'
    ];

      // Define the relationship with the Client model
      public function client()
      {
          return $this->belongsTo(ClientProfile::class);
      }
}


