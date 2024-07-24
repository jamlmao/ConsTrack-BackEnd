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



class AController extends Controller
{
    public function createStaff(Request $request)
    {
        $user = Auth::user();
        // Ensure the authenticated user is an admin
        if (Auth::user()->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
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
            'company_name' => 'required|string|max:60',
            'phone_number' => 'required|string|regex:/^\d{11,15}$/',
        ]);

        try {
            DB::transaction(function () use ($request) {
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'role' => 'staff',
                ]);

                $user->staffProfile()->create([
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'sex' => $request->sex,
                    'address' => $request->address,
                    'city' => $request->city,
                    'country' => $request->country,
                    'zipcode' => $request->zipcode,
                    'company_name' => $request->company_name,
                    'phone_number' => $request->phone_number,
                ]);
            });


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
                'action' => 'create',
                'old_values' => null,
                'new_values' => json_encode($newValues),
            ]);
     

            return response()->json(['message' => 'Staff created successfully'], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
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
            'company_name' => 'required|string|max:60',
            'phone_number' => 'required|string|regex:/^\d{11,15}$/',
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
                    'company_name' => $request->company_name,
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
                    'company_name' => $request->company_name,
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
}
