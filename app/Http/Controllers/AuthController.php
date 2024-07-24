<?php

namespace App\Http\Controllers;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use App\Http\Resources\UserResource;

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
                'role' => 'client' // Default role set to client
            ]);
                  // Create token with expiration
            $tokenResult = $user->createToken("TOKEN");
            $token = $tokenResult->plainTextToken;
            $tokenResult->token->expires_at = now()->addHours(1); // Set token to expire in 1 hour
            $tokenResult->token->save();

            return response()->json([
                'status' => true,
                'message' => 'User created successfully',
                'token' => $token,
                'expires_at' => $tokenResult->token->expires_at

            ], 200);

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
                $tokenResult->accessToken->expires_at = now()->addHours(6); // Set token to expire in 6 hours
                $tokenResult->accessToken->save();
    
                $role = $user->role; // Get the role of the user
    
                return response()->json([
                    'message' => 'Login successful',
                    'role' => $role,
                    'token' => $token,
                ], 200);
            } else {
                return response()->json([
                    'message' => 'Invalid credentials',
                ], 401);
            }
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'An error occurred during login',
                'error' => $e->getMessage(),
            ], 500);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }
    
}