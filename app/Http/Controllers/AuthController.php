<?php

namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\UserResource;
use App\Models\StaffProfile;
use App\Models\ClientProfile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), 
            [
                'name' => 'required|string',
                'email' => 'required|email|unique:users,email',
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'regex:/[A-Z]/', // must contain at least one uppercase letter
                    'regex:/[a-z]/', // must contain at least one lowercase letter
                    'regex:/[0-9]/', // must contain at least one number
                    'regex:/[@$!%*?&#]/' // must contain a special character
                ]
            ]);
    
            if ($validate->fails()){
                return response()->json([
                    'status'=> false,
                    'errors' => $validate->errors(),
                    'message' => 'Validation Error'
                ], 422);
            }
    
            $user = User::create([
                'name' => $request->name,
                'email' => Crypt::encryptString($request->email), // Encrypt the email
                'password' => Hash::make($request->password),
                'role' => 'client', // Default role set to client
                'status' => 'Deactivated' // not active or still not use 
            ]);
    
            return response()->json([
                'status' => true,
                'message' => 'User created successfully',
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $attrs = $request->validate([
                'username' => 'required|string', // This can be either email or username
                'password' => 'required|string',
            ]);
    
            // Log the inputted username and password (for debugging purposes only)
            Log::info('Inputted username: ' . $attrs['username']);
            Log::info('Inputted password: ' . $attrs['password']);
    
            $user = null;
            $decryptedEmail = null;
    
            // Check if the input is an email
            if (filter_var($attrs['username'], FILTER_VALIDATE_EMAIL)) {
                // Try to find the user by email
                $users = User::all();
                foreach ($users as $u) {
                    try {
                        $decryptedEmail = Crypt::decryptString($u->email);
                        Log::info('Comparing decrypted email: ' . $decryptedEmail . ' with input email: ' . $attrs['username']);
                        if ($decryptedEmail === $attrs['username']) {
                            $user = $u;
                            Log::info('Decrypted email for login attempt: ' . $decryptedEmail);
                            break;
                        }
                    } catch (\Exception $e) {
                        // Handle decryption failure (if any)
                        Log::error('Decryption failed for email: ' . $u->email . ' with error: ' . $e->getMessage());
                        continue;
                    }
                }
            } else {
                // Try to find the user by username
                $user = User::where('name', $attrs['username'])->first();
            }
    
            if ($user) {
                // Log the stored hashed password for debugging
                Log::info('Stored hashed password: ' . $user->password);
    
                // Manually verify the password
                if (Hash::check($attrs['password'], $user->password)) {
                    Log::info('Password verification successful');
    
                    // Log the user in manually
                    Auth::login($user);
    
                    $tokenResult = $user->createToken("AdminToken");
                    $token = $tokenResult->plainTextToken;
    
                    // Set the expiration time on the accessToken
                    $tokenResult->accessToken->expires_at = now()->addHours(12); // Set token to expire in 12 hours
                    $tokenResult->accessToken->save();
    
                    $role = $user->role; // Get the role of the user
                    
                    // Initialize profile ID
                    $profileId = null;
    
                    // Fetch the profile ID based on the user's role
                    if ($role === 'staff') {
                        $staffProfile = StaffProfile::where('user_id', $user->id)->first();
                        if ($staffProfile) {
                            $profileId = $staffProfile->id;
                        }
                    } elseif ($role === 'client') {
                        $clientProfile = ClientProfile::where('user_id', $user->id)->first();
                        if ($clientProfile) {
                            $profileId = $clientProfile->id;
                        }
                    }
                    
                    $user->last_logged_in_at = now();
                    $user->status = 'Active';
                    $user->save();
                    return response()->json([
                        'status' => true,
                        'message' => 'Login successful',
                        'token' => $token,
                        'role' => $role,
                        'profile_id' => $profileId,
                    ], 200);
                } else {
                    Log::info('Password verification failed');
                    return response()->json([
                        'status' => false,
                        'message' => 'Invalid login credentials'
                    ], 401);
                }
            } else {
                Log::info('User not found for username: ' . $attrs['username']);
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid login credentials'
                ], 401);
            }
        } catch (\Throwable $th) {
            Log::error('Login error: ' . $th->getMessage());
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            // Revoke the token that was used to authenticate the current request
            $request->user()->currentAccessToken()->delete();

            $user = $request->user();
            $user->status = 'Not Active';
            $user->save();

            
            return response()->json([
                'message' => 'Logout successful',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred during logout',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    
}