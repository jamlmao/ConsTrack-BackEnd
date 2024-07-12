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

            return response()->json([
                'status' => true,
                'message' => 'User created successfully',
                'token' => $user->createToken("TOKEN")->plainTextToken  
            ], 200);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function loginAdmin(Request $request){
        try {
            $attrs = $request->validate([
                'email' => 'required|email',
                'password' => 'required|string',
            ]);
    
            if (Auth::attempt(['email' => $attrs['email'], 'password' => $attrs['password']])) {
                $user = Auth::user();
                $role = $user->role; // Assuming the User model has a 'role' attribute
                $token = $user->createToken('AdminToken')->plainTextToken;

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