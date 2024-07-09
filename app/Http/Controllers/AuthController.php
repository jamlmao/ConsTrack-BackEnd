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
                    'regex:/[A-Z]/', 
                    'regex:/[a-z]/', 
                    'regex:/[0-9]/', 
                    'regex:/[@$!%*?&#]/' 
                ],
                'role' => 'required|string|in:admin,staff,user'

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
                'role' => $request->role

            ]);

            return response()-> json([
                'status' => true,
                'message' => 'User created successfully',
                'token' => $user->createToken("TOKEN")->plainTextToken  
               
            ], 200);

        }catch(\Throwable $th){
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function login(Request $request){
        try{
                $attrs = $request-> validate([
                    'email' => 'required|email',
                    'password' => 'required|string'
                ]);

                if (!Auth::attempt($attrs)){
                    return response([
                        'message' => 'Invalid credentials',
                    ], 403);
                }

                return response()->json([
                    'status' => true,
                    'message' => 'Login successful',
                    'token' => Auth::user()->createToken('secret')->plainTextToken
                ],200);

            } catch(\Throwable $th){
                return response()->json([
                    'status' => false,
                    'message' => $th->getMessage()
                ], 500);
            }
         }
}