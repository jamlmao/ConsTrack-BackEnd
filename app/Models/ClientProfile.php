<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClientProfile extends Model
{
    use HasFactory;

    
    protected $table = 'client_profiles';

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
        return $this->belongsTo(User::class, "user_id");
    }

    // Define the relationship with the StaffProfile model for the same company name 
    public function staffProfiles()
    {
        return $this->hasMany(StaffProfile::class, 'company_name', 'company_name', 'staff');
    }
   
    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}