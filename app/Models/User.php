<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role'
    ];
    protected static function boot()
    {
        parent::boot();

        static::created(function ($user) {
            if ($user->role === 'staff') {
                StaffProfile::create([
                    'user_id' => $user->id,
                    'first_name' => '', // Default value, adjust as needed
                    'last_name' => '', // Default value, adjust as needed
                    'sex' => 'M', // Default value, adjust as needed
                    'address' => '', // Default value, adjust as needed
                    'city' => '', // Default value, adjust as needed
                    'country' => '', // Default value, adjust as needed
                    'zipcode' => '', // Default value, adjust as needed
                    'company_name' => '', // Default value, adjust as needed
                    'phone_number' => '', // Default value, adjust as needed
                ]);
            }
        });
    }

    public function staffProfile()
    {
        return $this->hasOne(StaffProfile::class);
    }

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
}
