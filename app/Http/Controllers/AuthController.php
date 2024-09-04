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

class AuthController extends Controller
{
    public function register(Request $request){
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
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'client' ,// Default role set to client
                'status' => 'Deactivated' // not active or still not use 
            ]);

            // Create token with expiration
            $tokenResult = $user->createToken("TOKEN");
            if ($tokenResult && $tokenResult->accessToken) {
                $token = $tokenResult->plainTextToken;
                $tokenResult->accessToken->expires_at = now()->addHours(1); // Set token to expire in 1 hour
                $tokenResult->accessToken->save();

                return response()->json([
                    'status' => true,
                    'message' => 'User created successfully',
                    'token' => $token,
                    'expires_at' => $tokenResult->accessToken->expires_at
                ], 200);
            } else {
                // Log the error for debugging
                Log::error('Token creation failed for user: ' . $user->id);
                return response()->json([
                    'status' => false,
                    'message' => 'Token creation failed'
                ], 500);
            }
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function login(Request $request){
        try {
            $attrs = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);
    
            if (Auth::attempt(['email' => $attrs['email'], 'password' => $attrs['password']])) {
                $user = Auth::user();
                $tokenResult = $user->createToken("AdminToken");
                $token = $tokenResult->plainTextToken;
    
                // Set the expiration time on the accessToken
                $tokenResult->accessToken->expires_at = now()->addHours(8); // Set token to expire in 8 hours
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

                $user->status = 'Active';
                $user->save();
    
                return response()->json([
                    'message' => 'Login successful',
                    'role' => $role,
                    'token' => $token,
                    'profile_id' => $profileId,
                ], 200);
            } else {
                return response()->json([
                    'status' => false,
                    'message' => 'Invalid credentials'
                ], 401);
            }
        } catch (\Throwable $th) {
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