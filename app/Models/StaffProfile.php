<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffProfile extends Model
{
    use HasFactory;

    protected $table = 'staff_profiles';

    // Specify the fillable fields
    protected $fillable = [
        'user_id',
        'first_name',
        'last_name',
        'sex',
        'address',
        'city',
        'country',
        'zipcode',
        'phone_number',
        'company_id',
    ];

    // Define the relationship with the User model
    public function user()
    {
        return $this->belongsTo(User::class);
    }

      // Define the relationship with Company
      public function company()
      {
          return $this->belongsTo(Company::class);
      }

}