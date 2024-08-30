<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ClientProfile;
use App\Models\AuditLog;
use App\Models\StaffProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; 
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;


class AController extends Controller
{
    public function createStaff(Request $request)
    {
        // Validate the request data
        $validatedData = $request->validate([
            'name' => 'required|string|max:20',
            'email' => 'required|string|email|max:20|unique:users',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/', // must contain at least one uppercase letter
                'regex:/[a-z]/', // must contain at least one lowercase letter
                'regex:/[0-9]/', // must contain at least one digit
                'regex:/[@$!%*?&#]/' // must contain a special character
            ],
            'first_name' => 'required|string|max:20',
            'last_name' => 'required|string|max:20',
            'sex' => 'required|in:M,F',
            'address' => 'required|string|max:60',
            'city' => 'required|string|max:60',
            'country' => 'required|string|max:60',
            'zipcode' => 'required|string|regex:/^\d{0,9}$/',
            'company_name' => 'required|string|max:60',
            'phone_number' => 'required|string|regex:/^\d{10,15}$/',
        ]);
    
        try {
            DB::transaction(function () use ($request, $validatedData) {
                // Create the user
                $user = User::create([
                    'name' => $validatedData['name'],
                    'email' => $validatedData['email'],
                    'password' => bcrypt($validatedData['password']),
                    'role' => 'staff',
                ]);
    
                // Create the staff profile
                $staffProfile = StaffProfile::create([
                    'user_id' => $user->id,
                    'first_name' => $validatedData['first_name'],
                    'last_name' => $validatedData['last_name'],
                    'sex' => $validatedData['sex'],
                    'address' => $validatedData['address'],
                    'city' => $validatedData['city'],
                    'country' => $validatedData['country'],
                    'zipcode' => $validatedData['zipcode'],
                    'phone_number' => $validatedData['phone_number'],
                    'company_name' => $validatedData['company_name'],
                ]);
    
                // Log the creation
                Log::info('Staff account created successfully', ['user_id' => $user->id, 'staff_profile_id' => $staffProfile->id]);
            });
    
            return response()->json(['status' => true, 'message' => 'Staff account created successfully'], 201);
        } catch (Exception $e) {
            // Log the error
            Log::error('Failed to create staff account: ' . $e->getMessage());
    
            return response()->json(['status' => false, 'message' => 'Failed to create staff account'], 500);
        }
    }
  
    public function createClient(Request $request)
    {
        $user = Auth::user();
    
        if (!in_array($user->role, ['admin', 'staff'])) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
    
        // Ensure the user has an associated staff profile
        if (!$user->staffProfile) {
            return response()->json([
                'status' => false,
                'message' => 'User does not have an associated staff profile'
            ], 400);
        }
    
        $request->validate([
            'name' => 'required|string|max:20',
            'email' => 'required|string|email|max:20|unique:users',
            'password' => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/', // must contain at least one uppercase letter
                'regex:/[a-z]/', // must contain at least one lowercase letter
                'regex:/[0-9]/', // must contain at least one digit
                'regex:/[@$!%*?&#]/' // must contain a special character
            ],
            'first_name' => 'required|string|max:20',
            'last_name' => 'required|string|max:20',
            'sex' => 'required|in:M,F',
            'address' => 'required|string|max:60',
            'city' => 'required|string|max:60',
            'country' => 'required|string|max:60',
            'zipcode' => 'required|string|regex:/^\d{0,9}$/',
           'phone_number' => 'required|string|regex:/^\d{10,15}$/',
        ]);
    
        try {
            DB::transaction(function () use ($request, $user) {
                $client = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'role' => 'client',
                    'created_by' => $user->id, // Track who created the client
                    'updated_by' => $user->id, // Track who last updated the client
                ]);
    
                $client->ClientProfile()->create([
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'sex' => $request->sex,
                    'address' => $request->address,
                    'city' => $request->city,
                    'country' => $request->country,
                    'zipcode' => $request->zipcode,
                    'company_name' => $user->staffProfile->company_name, // Automatically set the company name from staff profile
                    'phone_number' => $request->phone_number,
                ]);
    
                $newValues = [
                    'name' => $client->name,
                    'email' => $client->email,
                    'password' => $client->password,
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'sex' => $request->sex,
                    'address' => $request->address,
                    'city' => $request->city,
                    'country' => $request->country,
                    'zipcode' => $request->zipcode,
                    'company_name' => $user->staffProfile->company_name, // Automatically set the company name from staff profile
                    'phone_number' => $request->phone_number,
                ];
    
                AuditLog::create([
                    'user_id' => $client->id,
                    'editor_id' => auth()->user()->id, // Assuming the editor is the authenticated user
                    'action' => 'create',
                    'old_values' => null,
                    'new_values' => json_encode($newValues),
                ]);
            });
    
            return response()->json(['message' => 'Client created successfully'], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'nullable|string|min:8',
            'first_name' => 'required|string|max:20',
            'last_name' => 'required|string|max:20',
            'sex' => 'required|in:M,F',
            'address' => 'required|string|max:60',
            'city' => 'required|string|max:60',
            'country' => 'required|string|max:60',
            'zipcode' => 'required|string|regex:/^\d{0,9}$/',
            'company_name' => 'required|string|max:60',
            'phone_number' => 'required|string|regex:/^\d{11,15}$/',
        ]);

        $user = User::find($id);

        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }

        $oldValues = [ 
            'name' => $user->name,
            'email' => $user->email,
            'password' => $user->password,
            'first_name' => $user->StaffProfile->first_name,
            'last_name' => $user->StaffProfile->last_name,
            'sex' => $user->StaffProfile->sex,
            'address' => $user->StaffProfile->address,
            'city' => $user->StaffProfile->city,
            'country' => $user->StaffProfile->country,
            'zipcode' => $user->StaffProfile->zipcode,
            'company_name' => $user->StaffProfile->company_name,
            'phone_number' => $user->StaffProfile->phone_number,
        ];

        try {
            DB::transaction(function () use ($request, $user, $oldValues) {
                $user->update([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => $request->password ? Hash::make($request->password) : $user->password,
                ]);

                $user->StaffProfile()->updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'first_name' => $request->first_name,
                        'last_name' => $request->last_name,
                        'sex' => $request->sex,
                        'address' => $request->address,
                        'city' => $request->city,
                        'country' => $request->country,
                        'zipcode' => $request->zipcode,
                        'company_name' => $request->company_name,
                        'phone_number' => $request->phone_number,
                    ]
                );

                $newValues = [
                    'name' => $user->name,
                    'email' => $user->email,
                    'password' => $user->password,
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'sex' => $request->sex,
                    'address' => $request->address,
                    'city' => $request->city,
                    'country' => $request->country,
                    'zipcode' => $request->zipcode,
                    'company_name' => $request->company_name,
                    'phone_number' => $request->phone_number,
                ];

                AuditLog::create([
                    'user_id' => $user->id,
                    'editor_id' => auth()->user()->id, // Assuming the editor is the authenticated user
                    'action' => 'update',
                    'old_values' => json_encode($oldValues),
                    'new_values' => json_encode($newValues),
                ]);
            });

            return response()->json(['message' => 'Staff profile updated successfully'], 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update staff profile'], 500);
        }
    }
    public function getClientsUnderSameCompany()
    {
         // Get the logged-in staff profile
         $staffProfile = StaffProfile::where('user_id', Auth::id())->first();

         if (!$staffProfile) {
             return response()->json(['error' => 'Staff profile not found'], 404);
         }
 
         // Get the clients under the same company
         $clients = ClientProfile::where('company_name', $staffProfile->company_name)->get();
 
         return response()->json($clients);

    }

    public function getLoggedInUserNameAndId()
    {
        $user = Auth::user();
        $response = [
            'name' => $user->name,
            'id' => $user->id,
            'role' => $user->role,
            'email' => $user->email,
        ];
    
        if ($user->role === 'staff') {
            $staffProfile = StaffProfile::where('user_id', $user->id)->first();
            if ($staffProfile) {
                $response['staff'] = [
                    'id' => $staffProfile->id,
                    'first_name' => $staffProfile->first_name,
                    'last_name' => $staffProfile->last_name,
                    'sex' => $staffProfile->sex,
                    'address' => $staffProfile->address,
                    'city' => $staffProfile->city,
                    'country' => $staffProfile->country,
                    'zipcode' => $staffProfile->zipcode,
                    'phone_number' => $staffProfile->phone_number,
                    'company_name' => $staffProfile->company_name,
                ];
            }
        } elseif ($user->role === 'client') {
            $clientProfile = ClientProfile::where('user_id', $user->id)->first();
            if ($clientProfile) {
                $response['client_details'] = [
                    'id' => $clientProfile->id,
                    'first_name' => $clientProfile->first_name,
                    'last_name' => $clientProfile->last_name,
                    'sex' => $clientProfile->sex,
                    'address' => $clientProfile->address,
                    'city' => $clientProfile->city,
                    'country' => $clientProfile->country,
                    'zipcode' => $clientProfile->zipcode,
                    'phone_number' => $clientProfile->phone_number,
                    'company_name' => $clientProfile->company_name,
                ];
            }
        }
    
        return response()->json($response);
    }
    
   
    public function sendOtp(Request $request)
    {
        $user = Auth::user();

        // Check if the user can request a new OTP
        if ($user->otp_requested_at && Carbon::parse($user->otp_requested_at)->addMinutes(2)->isFuture()) {
            return response()->json(['message' => 'You can request a new OTP after 2 minutes'], 429);
        }

        // Generate OTP
        $otp = rand(100000, 999999);

        // Save OTP and request time to the user
        $user->otp = $otp;
        $user->otp_expires_at = now()->addMinutes(10); // OTP expires in 10 minutes
        $user->otp_requested_at = now();
        $user->save();

        // Send OTP email
        Mail::raw("Your OTP is: $otp", function ($message) use ($user) {
            $message->to($user->email)
                    ->subject('Your OTP for Password Change');
        });

        return response()->json(['message' => 'OTP sent to your email'], 200);
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::user();
    
        // Validate the request data
        $validatedData = $request->validate([
            'otp' => 'required|string',
            'new_password' => [
                'required',
                'string',
                'min:8',
                'regex:/[A-Z]/', // must contain at least one uppercase letter
                'regex:/[a-z]/', // must contain at least one lowercase letter
                'regex:/[0-9]/', // must contain at least one digit
                'regex:/[@$!%*?&#]/' // must contain a special character
            ],
        ]);
    
        // Check if the OTP is correct and not expired
        if ($user->otp !== $validatedData['otp'] || now()->greaterThan($user->otp_expires_at)) {
            return response()->json(['message' => 'Invalid or expired OTP'], 400);
        }
    
        // Update the user's password
        $user->password = bcrypt($validatedData['new_password']);
        $user->otp = null; // Clear the OTP
        $user->otp_expires_at = null; // Clear the OTP expiration time
        $user->save();
    
        return response()->json(['message' => 'Password updated successfully'], 200);
    }
}
