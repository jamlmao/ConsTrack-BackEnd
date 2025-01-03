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
        'site_address',
        'site_city',
        'site_province',
        'project_name',
        'client_id',
        'staff_id',
        'status',
        'updated_at',
        'completion_date',
        'company_id',
        'starting_date',
        'totalBudget',
        'pj_image',
        'pj_image1',
        'pj_image2',
        'pj_pdf',
        'total_used_budget',
        'project_type'
    ];

      // Define the relationship with the Client model
      public function client()
      {
          return $this->belongsTo(ClientProfile::class, 'client_id');
      }
  
      public function staff()
      {
          return $this->belongsTo(StaffProfile::class, 'staff_id');
      }
  
      public function company()
      {
          return $this->belongsTo(Company::class, 'company_id');
      }

      public function staffUser()
      {
          return $this->belongsTo(User::class, 'staff_id');
      }
  
      public function clientUser()
      {
          return $this->belongsTo(User::class, 'client_id');
      }

}


