<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\ClientProfile;
use App\Models\AuditLog;
use App\Models\StaffProfile;
use App\Mail\ClientCreated;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log; 
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use App\Mail\PasswordChange;
use Carbon\Carbon;
use App\Models\Project;
use App\Models\Company;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use DateTime;

class AController extends Controller
{
    


   
    public function createStaff(Request $request)
    {
        try {

            $user = Auth::user();

            if ($user->role !== 'admin' && !$user->staffProfile) {
                return response()->json([
                    'status' => false,
                    'message' => 'User does not have an associated staff profile'
                ], 400);
            }


            $validate = Validator::make($request->all(), 
            [
                'name' => 'required|string|max:255|unique:users',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'regex:/[A-Z]/', // must contain at least one uppercase letter
                    'regex:/[a-z]/', // must contain at least one lowercase letter
                    'regex:/[0-9]/', // must contain at least one digit
                    'regex:/[@$!%*?&#]/' // must contain a special character
                ],
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'sex' => 'required|in:M,F',
                'address' => 'required|string|max:255',
                'city' => 'required|string|max:255',
                'country' => 'required|string|max:255',
                'zipcode' => 'required|string|max:10',
                'extension_name' => 'nullable|string|max:255', 
                'license_number' => 'nullable|unique:staff_profiles|string|max:255', 
                'phone_number' => 'required|string|max:15|unique:staff_profiles',
                'company_name' => 'required|string|max:255',
                'company_logo' => 'nullable|string', // Add validation for base64 company_logo
            ]);

            if ($validate->fails()){
                return response()->json([
                    'status'=> false,
                    'errors' => $validate->errors(),
                    'message' => 'Validation Error'
                ], 422);
            }

            DB::beginTransaction(); // Start the transaction

            // Handle company logo upload
            $companyLogoPath = null;
            if (!empty($request->company_logo)) {
                $decodedImage = base64_decode($request->company_logo, true);
                if ($decodedImage === false) {
                    return response()->json(['message' => 'Invalid base64 image'], 400);
                }
                $uniqueId = uniqid();
                $imageName = Carbon::now()->format('Ymd_His') . '_' . $uniqueId . '.png';
                $isSaved = Storage::disk('public')->put('company_logos/' . $imageName, $decodedImage);

                if (!$isSaved) {
                    return response()->json(['message' => 'Failed to save company logo'], 500);
                }

                $companyLogoPath = 'storage/company_logos/' . $imageName;
            }

            $company = Company::firstOrCreate(
                ['company_name' => $request->company_name]
            );


            if ($companyLogoPath) {
                $company->company_logo = $companyLogoPath;
                $company->save();
            }

            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'staff', // Default role set to staff
                'status' => 'Not Active' // not active or still not use 
            ]);

            // Create staff profile
            $staffProfile = StaffProfile::create([
                'user_id' => $user->id,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'sex' => $request->sex,
                'address' => $request->address,
                'city' => $request->city,
                'country' => $request->country,
                'zipcode' => $request->zipcode,
                'extension_name' => $request->extension_name ?? '', 
                'license_number' => $request->license_number ?? '',
                'phone_number' => $request->phone_number,
                'company_id' => $company->id, 
            ]);

            // Log the creation of the staff profile
            AuditLog::create([
                'user_id' => $user->id,
                'editor_id' => auth()->user()->id, // Assuming the editor is the authenticated user
                'action' => 'create',
                'old_values' => null,
                'new_values' => json_encode([
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'sex' => $request->sex,
                    'address' => $request->address,
                    'city' => $request->city,
                    'country' => $request->country,
                    'zipcode' => $request->zipcode,
                    'company_name' => $request->company_name,
                    'phone_number' => $request->phone_number,
                    'extension_name' => $request->extension_name ?? '',
                    'license_number' => $request->license_number ?? '',
                    'company_logo' => $companyLogoPath, // Add company logo path to the log
                ]),
            ]);

            DB::commit(); // Commit the transaction

            return response()->json([
                'status' => true,
                'message' => 'Staff registered successfully',
                'user' => $user,
                'staff_profile' => $staffProfile
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack(); // Rollback the transaction in case of error
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    

    public function createStaffByAuthorizedStaff(Request $request)
    {
        try {
            $user = Auth::user();
    
            // Check if the authenticated user is a staff member with an extension_name and license_number
            if ($user->role !== 'staff' || !$user->staffProfile || !$user->staffProfile->extension_name || !$user->staffProfile->license_number) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized: Staff member does not have the required extension_name and license_number'
                ], 403);
            }
    
            // Validate the request data
            $validate = Validator::make($request->all(), [
                'name' => 'required|string|max:255|unique:users',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => [
                    'required',
                    'string',
                    'min:8',
                    'regex:/[A-Z]/', // must contain at least one uppercase letter
                    'regex:/[a-z]/', // must contain at least one lowercase letter
                    'regex:/[0-9]/', // must contain at least one digit
                    'regex:/[@$!%*?&#]/' // must contain a special character
                ],
                'first_name' => 'required|string|max:255',
                'last_name' => 'required|string|max:255',
                'sex' => 'required|in:M,F',
                'address' => 'required|string|max:255',
                'city' => 'required|string|max:255',
                'country' => 'required|string|max:255',
                'zipcode' => 'required|string|max:10',
                'phone_number' => 'required|string|max:15|unique:staff_profiles',
            ]);
    
            if ($validate->fails()) {
                return response()->json([
                    'status' => false,
                    'errors' => $validate->errors(),
                    'message' => 'Validation Error'
                ], 422);
            }
    
            DB::beginTransaction(); // Start the transaction
    
            // Fetch the company information from the authenticated user's profile
            $company = $user->staffProfile->company;
    
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'staff', // Default role set to staff
                'status' => 'Not Active' // not active or still not use 
            ]);
    
            // Create staff profile
            $staffProfile = StaffProfile::create([
                'user_id' => $user->id,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'sex' => $request->sex,
                'address' => $request->address,
                'city' => $request->city,
                'country' => $request->country,
                'zipcode' => $request->zipcode,
                'phone_number' => $request->phone_number,
                'company_id' => $company->id,
                'extension_name' => '',
                'license_number' => '',
            ]);
    
            // Log the creation of the staff profile
            AuditLog::create([
                'user_id' => $user->id,
                'editor_id' => auth()->user()->id, // Assuming the editor is the authenticated user
                'action' => 'create',
                'old_values' => null,
                'new_values' => json_encode([
                    'first_name' => $request->first_name,
                    'last_name' => $request->last_name,
                    'sex' => $request->sex,
                    'address' => $request->address,
                    'city' => $request->city,
                    'country' => $request->country,
                    'zipcode' => $request->zipcode,
                    'company_name' => $company->company_name,
                    'phone_number' => $request->phone_number,
                    'company_logo' => $company->company_logo, // Add company logo path to the log
                ]),
            ]);
    
            DB::commit(); // Commit the transaction
    
            return response()->json([
                'status' => true,
                'message' => 'Staff registered successfully',
                'user' => $user,
                'staff_profile' => $staffProfile
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack(); // Rollback the transaction in case of error
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
    
        if ($user->role !== 'admin' && !$user->staffProfile) {
            return response()->json([
                'status' => false,
                'message' => 'User does not have an associated staff profile'
            ], 400);
        }
    
        $validatedData = $request->validate([
            'name' => 'required|string|max:20',
            'email' => 'required|string|email|max:100|unique:users',
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
            'company_name' => 'required_if:user.role,admin|string|max:60', // Add company_name validation for admin
        ]);
    
        DB::beginTransaction();
    
        try {
            // Create or find the company
            if ($user->role === 'admin') {
                $company = Company::firstOrCreate(['company_name' => $validatedData['company_name']]);
            } else {
                $company = Company::find($user->staffProfile->company_id);
                if (!$company) {
                    throw new \Exception('Company not found for the staff user');
                }
            }
    
            // Create the user and client profile in a single transaction
            $client = User::create([
                'name' => $validatedData['name'],
                'email' => $validatedData['email'],
                'password' => Hash::make($validatedData['password']),
                'role' => 'client',
                'created_by' => $user->id,
                'updated_by' => $user->id,
                'status' => 'Not Active'
            ]);
    
            $clientProfile = $client->clientProfile()->create([
                'first_name' => $validatedData['first_name'],
                'last_name' => $validatedData['last_name'],
                'sex' => $validatedData['sex'],
                'address' => $validatedData['address'],
                'city' => $validatedData['city'],
                'country' => $validatedData['country'],
                'zipcode' => $validatedData['zipcode'],
                'company_id' => $company->id,
                'phone_number' => $validatedData['phone_number'],
            ]);
    
            // Log the audit
            AuditLog::create([
                'user_id' => $client->id,
                'editor_id' => $user->id,
                'action' => 'create',
                'old_values' => null,
                'new_values' => json_encode($validatedData),
            ]);
    
            DB::commit();
    
            // Send email to the client
            Mail::to($client->email)->send(new ClientCreated($client, $clientProfile, $validatedData['password'], $company));
    
            return response()->json([
                'message' => 'Client created successfully',
                'client' => [
                    'id' => $client->id,
                    'name' => $client->name,
                    'email' => $client->email,
                    'first_name' => $clientProfile->first_name,
                    'last_name' => $clientProfile->last_name,
                ],
                'company' => [
                                'id' => $company->id,
                                'company_name' => $company->company_name
                            ]
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack();
    
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

        public function getClientsUnderSameCompany()
        {
            // Get the logged-in staff profile
            $staffProfile = StaffProfile::where('user_id', Auth::id())->first();

            if (!$staffProfile) {
                return response()->json(['error' => 'Staff profile not found'], 404);
            }

            // Get the company ID from the staff profile
            $companyId = $staffProfile->company_id;

            // Get clients under the same company and their project statuses
            $clients = DB::table('client_profiles')
                ->leftJoin('projects', 'client_profiles.id', '=', 'projects.client_id')
                ->leftJoin('users', 'client_profiles.user_id', '=', 'users.id') // Join with users table
                ->where('client_profiles.company_id', $companyId)
                ->whereNotNull('client_profiles.company_id') // Ensure company_id is not null
                ->whereIn('users.status', ['Active', 'Not Active']) // Filter by user status
                ->select('client_profiles.*', 'projects.status as project_status', 'users.status as user_status')
                ->get();

            // Map project statuses to descriptive terms
            $statusMapping = [
                'OG' => 'Ongoing',
                'C' => 'Complete',
                'D' => 'Due'
            ];

            $clients = $clients->map(function ($client) use ($statusMapping) {
                if (isset($client->project_status) && array_key_exists($client->project_status, $statusMapping)) {
                    $client->project_status = $statusMapping[$client->project_status];
                }
                return $client;
            });

            // Count the number of clients
            $clientCount = $clients->count();

            return response()->json(['clients' => $clients, 'client_count' => $clientCount], 200);
        }

        public function getClientsUnderSameCompany2()
        {
            // Get the logged-in staff profile
            $staffProfile = StaffProfile::where('user_id', Auth::id())->first();

            if (!$staffProfile) {
                return response()->json(['error' => 'Staff profile not found'], 404);
            }

            // Get the company ID from the staff profile
            $companyId = $staffProfile->company_id;

            // Get clients under the same company and their project statuses
            $clients = DB::table('client_profiles')
                ->leftJoin('projects', 'client_profiles.id', '=', 'projects.client_id')
                ->leftJoin('users', 'client_profiles.user_id', '=', 'users.id') // Join with users table
                ->where('client_profiles.company_id', $companyId)
                ->whereNotNull('client_profiles.company_id') // Ensure company_id is not null
                ->whereIn('users.status', ['Active', 'Not Active']) // Filter by user status
                ->select('client_profiles.id') // Only select the client IDs
                ->get();

            // Count the number of clients
            $clientCount = $clients->count();

            return response()->json(['client_count' => $clientCount], 200);
        }



       
        public function getStaffWithExtensionAndLicense()
        {
            $user = Auth::user();

            if (!in_array($user->role, ['admin', 'staff'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            try {
                // Get the company ID based on the user's role
                $companyId = $user->staffProfile->company_id;

                if (!$companyId) {
                    throw new \Exception('Company ID not found for the user');
                }

                // Fetch staff members with extension names, license numbers, and phone numbers under the same company
                $staffWithExtension = StaffProfile::where('company_id', $companyId)
                    ->whereNotNull('extension_name')
                    ->where('extension_name', '!=', '')
                    ->whereNotNull('license_number')
                    ->where('license_number', '!=', '')
                    ->where('id', '!=', $user->staffProfile->id) // Exclude the logged-in user by ID
                    ->get(['id', 'first_name', 'last_name', 'extension_name', 'license_number', 'phone_number']);

                return response()->json([
                    'staff' => $staffWithExtension
                ], 200);
            } catch (\Throwable $th) {
                return response()->json([
                    'status' => false,
                    'message' => $th->getMessage()
                ], 500);
            }
        }

        public function getAllStaffFromSameCompany()
        {
            try {
                // Get the logged-in user
                $user = Auth::user();
        
                // Fetch the client profile associated with the user
                $clientProfile = DB::table('client_profiles')
                    ->where('user_id', $user->id)
                    ->first();
        
                // Check if the client profile exists
                if (!$clientProfile) {
                    return response()->json([
                        'status' => false,
                        'message' => 'User does not have an associated client profile.'
                    ], 400);
                }
        
                // Get the company ID from the client profile
                $companyId = $clientProfile->company_id;
        
                // Fetch all staff members from the same company
                $staffMembers = DB::table('staff_profiles')
                    ->where('company_id', $companyId)
                    ->get(['id', 'first_name', 'last_name', 'extension_name', 'license_number', 'phone_number']);
        
                return response()->json([
                    'staff' => $staffMembers
                ], 200);
            } catch (\Throwable $th) {
                return response()->json([
                    'status' => false,
                    'message' => $th->getMessage()
                ], 500);
            }
        }

       
        public function getAllClientsForAdmin()
        {
            // Get the logged-in user
            $user = Auth::user();

            // Check if the user is an admin
            if ($user->role !== 'admin') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Get all clients and their project statuses along with company information
            $clients = DB::table('client_profiles')
                ->leftJoin('projects', 'client_profiles.id', '=', 'projects.client_id')
                ->leftJoin('users', 'client_profiles.user_id', '=', 'users.id') // Join with users table
                ->leftJoin('companies', 'client_profiles.company_id', '=', 'companies.id') // Join with companies table
                ->whereIn('users.status', ['Active', 'Not Active']) // Filter by user status
                ->select(
                    'client_profiles.*',
                    'projects.status as project_status',
                    'users.status as user_status',
                    'companies.company_name as company_name',
                    'companies.id as company_id'
                )
                ->distinct() // Ensure unique records
                ->get();

            // Group clients by company and count the number of clients under each company
            $companies = $clients->groupBy('company_id')->map(function ($companyClients) {
                return [
                    'company_id' => $companyClients->first()->company_id,
                    'company_name' => $companyClients->first()->company_name,
                    'client_count' => $companyClients->count(),
                    'clients' => $companyClients->values()
                ];
            })->values();

            return response()->json(['companies' => $companies], 200);
        }





        public function getClientsCountByMonth()
        {
            try {
                // Get the logged-in user
                $user = Auth::user();
        
                // Initialize company ID
                $companyId = null;
        
                // Check the user's role and fetch the company ID accordingly
                if ($user->role === 'staff') {
                    $staffProfile = StaffProfile::where('user_id', $user->id)->first();
                    if (!$staffProfile) {
                        return response()->json(['error' => 'Staff profile not found'], 404);
                    }
                    $companyId = $staffProfile->company_id;
                } elseif ($user->role === 'admin') {
                    $companyId = $user->company_id; 
                } else {
                    return response()->json(['error' => 'Unauthorized'], 403);
                }
        
                // Fetch clients under the same company and group them by month
                $clients = DB::table('client_profiles')
                    ->where('company_id', $companyId)
                    ->whereNotNull('company_id') 
                    ->select(
                        DB::raw('YEAR(created_at) as year'),
                        DB::raw('MONTH(created_at) as month'),
                        DB::raw('COUNT(id) as client_count')
                    )
                    ->groupBy('year', 'month')
                    ->orderBy('year', 'asc')
                    ->orderBy('month', 'asc')
                    ->get();
        
                // Map month numbers to month names
                $monthNames = [
                    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                ];
        
                $clients = $clients->map(function ($client) use ($monthNames) {
                    $client->month = $monthNames[$client->month];
                    return $client;
                });
        
                return response()->json(['clients_per_month' => $clients], 200);
            } catch (Exception $e) {
                Log::error('Failed to fetch clients count by month: ' . $e->getMessage());
                return response()->json(['error' => 'Failed to fetch clients count by month', 'message' => $e->getMessage()], 500);
            }
        }

      
        public function getClientCountByMonthA()
        {
            try {
                // Fetch clients and group them by month
                $clients = DB::table('client_profiles')
                    ->select(
                        DB::raw('YEAR(created_at) as year'),
                        DB::raw('MONTH(created_at) as month'),
                        DB::raw('COUNT(id) as client_count')
                    )
                    ->groupBy('year', 'month')
                    ->orderBy('year', 'asc')
                    ->orderBy('month', 'asc')
                    ->get();
    
                // Format the results
                $formattedClients = $clients->map(function ($client) {
                    $dateObj = DateTime::createFromFormat('!m', $client->month);
                    $monthName = strtolower($dateObj->format('M')); // Use 'M' for short month name
                    return [
                        'year' => $client->year,
                        'count' => $client->client_count,
                        'month' => $monthName
                    ];
                });
    
                // Return the clients count by month as a JSON response
                return response()->json($formattedClients);
            } catch (Exception $e) {
                Log::error('Failed to fetch clients count by month: ' . $e->getMessage());
                return response()->json(['error' => 'Failed to fetch clients count by month', 'message' => $e->getMessage()], 500);
            }
        }
        


 
        public function getStaffCountByMonth()
        {
            try {
                // Get the logged-in user
                $user = Auth::user();

                // Check if the user is an admin
                if ($user->role !== 'admin') {
                    return response()->json(['error' => 'Unauthorized'], 403);
                }

                // Get the company ID from the admin user
             

                // Fetch staff under the same company and group them by month
                $staffs = DB::table('staff_profiles')
                    ->select(
                        DB::raw('YEAR(created_at) as year'),
                        DB::raw('MONTH(created_at) as month'),
                        DB::raw('COUNT(id) as staff_count')
                    )
                    ->groupBy('year', 'month')
                    ->orderBy('year', 'asc')
                    ->orderBy('month', 'asc')
                    ->get();

                // Map month numbers to month names
                $monthNames = [
                    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
                ];

                $staffCountByMonth = [];
                foreach ($staffs as $staff) {
                    $monthYear = $monthNames[$staff->month] . '_' . $staff->year;
                    $staffCountByMonth[$monthYear] = $staff->staff_count;
                }

                return response()->json(['StaffPerMonth' => $staffCountByMonth], 200);
            } catch (Exception $e) {
                Log::error('Failed to fetch staff count by month: ' . $e->getMessage());
                return response()->json(['error' => 'Failed to fetch staff count by month', 'message' => $e->getMessage()], 500);
            }
        }




        public function getStaffCountByMonthA()
        {
            try {
                // Fetch staff and group them by month
                $staff = DB::table('staff_profiles')
                    ->select(
                        DB::raw('YEAR(created_at) as year'),
                        DB::raw('MONTH(created_at) as month'),
                        DB::raw('COUNT(id) as staff_count')
                    )
                    ->groupBy('year', 'month')
                    ->orderBy('year', 'asc')
                    ->orderBy('month', 'asc')
                    ->get();
    
                // Format the results
                $formattedStaff = $staff->map(function ($staff) {
                    $dateObj = DateTime::createFromFormat('!m', $staff->month);
                    $monthName = strtolower($dateObj->format('M')); // Use 'M' for short month name
                    return [
                        'year' => $staff->year,
                        'count' => $staff->staff_count,
                        'month' => $monthName
                    ];
                });
    
                // Return the staff count by month as a JSON response
                return response()->json(['staff_per_month' => $formattedStaff], 200);
            } catch (Exception $e) {
                Log::error('Failed to fetch staff count by month: ' . $e->getMessage());
                return response()->json(['error' => 'Failed to fetch staff count by month', 'message' => $e->getMessage()], 500);
            }
        }












        private function getExtensionName($extensionName)
        {
            switch (strtolower($extensionName)) {
                case 'civil engineer':
                    return 'CE';
                case 'architect':
                    return 'UAP';
                default:
                    return $extensionName; // Return the original value if it doesn't match
            }
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
                    $company = Company::find($staffProfile->company_id);
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
                        'company_id' => $staffProfile->company_id,
                        'company_name' => $company ? $company->company_name : null, 
                        'company_logo' => $company ? $company->company_logo : null,
                        'extension_name' =>$this->getExtensionName($staffProfile->extension_name),
                        'license_number' => $staffProfile->license_number,
                    ];
                }
            } elseif ($user->role === 'client') {
                $clientProfile = ClientProfile::where('user_id', $user->id)->first();
                if ($clientProfile) {
                    $company = Company::find($clientProfile->company_id);
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
                        'company_id' => $clientProfile->company_id,
                        'company_name' => $company ? $company->company_name : null, 
                        
                        'company_logo' => $company ? $company->company_logo : null,
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
        // Authenticate the user
        $authenticatedUser = Auth::user();
    
        // Validate the request data
        $validatedData = $request->validate([
            'client_id' => 'required|exists:client_profiles,id',
            'password' => [
                'nullable',
                'string',
                'min:8',
                'regex:/[A-Z]/', // must contain at least one uppercase letter
                'regex:/[a-z]/', // must contain at least one lowercase letter
                'regex:/[0-9]/', // must contain at least one digit
                'regex:/[@$!%*?&#]/' // must contain a special character
            ],
            'name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'zipcode' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
        ]);
    
        // Begin a database transaction
        DB::beginTransaction();
    
        try {
            // Find the ClientProfile by client_id
            $clientProfile = ClientProfile::findOrFail($validatedData['client_id']);
    
            // Retrieve the associated user_id from the ClientProfile
            $user_id = $clientProfile->user_id;
    
            // Check if the authenticated user is an admin or the user themselves (staff)
            if ($authenticatedUser->role !== 'admin' && $authenticatedUser->role !== 'staff' && $authenticatedUser->id !== $user_id) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }
    
            // Find the user by user_id
            $user = User::findOrFail($user_id);
    
            // Flag to check if the password was updated
            $passwordUpdated = false;
    
            // Update the user's data if provided
            if (isset($validatedData['password'])) {
                $user->password = bcrypt($validatedData['password']);
                $passwordUpdated = true;
            }
            if (isset($validatedData['name'])) {
                $user->name = $validatedData['name'];
            }
            if (isset($validatedData['email'])) {
                $user->email = $validatedData['email'];
            }
            $user->save();
    
            // Update the client's profile data if provided
            if (isset($validatedData['first_name'])) {
                $clientProfile->first_name = $validatedData['first_name'];
            }
            if (isset($validatedData['last_name'])) {
                $clientProfile->last_name = $validatedData['last_name'];
            }
            if (isset($validatedData['phone_number'])) {
                $clientProfile->phone_number = $validatedData['phone_number'];
            }
            if (isset($validatedData['zipcode'])) {
                $clientProfile->zipcode = $validatedData['zipcode'];
            }
            if (isset($validatedData['country'])) {
                $clientProfile->country = $validatedData['country'];
            }
            if (isset($validatedData['address'])) {
                $clientProfile->address = $validatedData['address'];
            }
            if (isset($validatedData['city'])) {
                $clientProfile->city = $validatedData['city'];
            }
            $clientProfile->save();
    
            // Fetch the company name using the company_id from the ClientProfile
            $company = Company::findOrFail($clientProfile->company_id);
            $companyName = $company->company_name;
            Log::info('email Sent '. $user->email);
            // Send the new password to the client via email if the password was updated
            if ($passwordUpdated) {
                Mail::to($user->email)->send(new PasswordChange($validatedData['password'], $companyName));
            }
           
            // Commit the transaction
            DB::commit();
    
            // Return a success response
            return response()->json([
                'status' => true,
                'message' => 'Password and user details updated successfully'
            ], 200);
        } catch (\Exception $e) {
            // Rollback the transaction in case of an error
            DB::rollBack();
    
            // Return an error response
            return response()->json([
                'status' => false,
                'message' => 'Failed to update password and user details',
                'error' => $e->getMessage()
            ], 500);
        }
    }





    
    public function getUserCounts() //user accounts and projects
    {
        try {
            // Fetch the count of staff
            $staffCount = StaffProfile::count();
    
            // Fetch the count of clients
            $clientCount = ClientProfile::count();
    
            // Fetch unique company names from client_profiles
            $clientCompanies = ClientProfile::distinct()->pluck('company_id')->toArray();
    
            // Fetch unique company names from staff_profiles
            $staffCompanies = StaffProfile::distinct()->pluck('company_id')->toArray();
            
            $totalCount = $clientCount + $staffCount;

            // Merge and count unique company names
            $allCompanies = array_unique(array_merge($clientCompanies, $staffCompanies));
            $companyCount = count($allCompanies);
    
            // Fetch the count of projects with status "IP" (In Progress)
            $OnGoingProjectCount = Project::where('status', 'OG')->count();
    
            // Fetch the count of projects with status "C" (Completed)
            $completedProjectCount = Project::where('status', 'C')->count();
    
            // Fetch the count of projects with status "D" (Delayed)
            $delayedProjectCount = Project::where('status', 'D')->count();


            $projectCount = Project::count();
    
            // Return the counts in a JSON response
            return response()->json([
                'staffcount' => $staffCount,
                'clientcount' => $clientCount,
                'companycount' => $companyCount,
                'OnGoingProjectCount' => $OnGoingProjectCount,
                'completedProjectCount' => $completedProjectCount,
                'delayedProjectCount' => $delayedProjectCount,
                'totalUserCount' => $totalCount,
                'totalProjectCount' => $projectCount
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch counts: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch counts', 'error' => $e->getMessage()], 500);
        }
    }


    
    public function getMonthlyCounts()
    {
        try {
            $currentYear = Carbon::now()->year;
            $monthlyCounts = [];
            $months = [
                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
            ];
    
            // Loop through each month of the current year
            for ($month = 1; $month <= 12; $month++) {
                // Fetch the count of clients created in the current month
                $clientCount = ClientProfile::whereYear('created_at', $currentYear)
                                            ->whereMonth('created_at', $month)
                                            ->count();
    
                // Fetch the count of staff created in the current month
                $staffCount = StaffProfile::whereYear('created_at', $currentYear)
                                          ->whereMonth('created_at', $month)
                                          ->count();
    
                // Store the counts along with the month name
                $monthlyCounts[] = [
                    'month' => $months[$month],
                    'clientCount' => $clientCount,
                    'staffCount' => $staffCount
                ];
            }


            
    
            // Return the counts in a JSON response
            return response()->json(['monthlyUserCounts' => $monthlyCounts], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch monthly counts: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch monthly counts', 'error' => $e->getMessage()], 500);
        }
    }
    

    public function getAllStaff(){ // for admin to get all staff
        try {
            // Fetch all staff
            $staff = StaffProfile::all();

            // Return the staff in a JSON response
            return response()->json(['staff' => $staff], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch all staff: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch all staff', 'error' => $e->getMessage()], 500);
        }
    }



    public function fetchAllCompanies()
    {
        try{
            $companies =Company::all();

            return response()->json(['companies'=>$companies],200);
        }catch(Exception $e){
            Log::error('Failed to fetch all companies: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch all companies', 'error' => $e->getMessage()], 500);
        }
        


    }


    public function getAllUsers()
    {
        $user = Auth::user();
    
        // Check if the user is an admin
        if ($user->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
    
        try {
            // Fetch all users except those with the role 'admin'
            $users = User::where('role', '!=', 'admin')
                ->with([
                    'clientProfile' => function ($query) {
                        $query->select('id', 'first_name', 'last_name', 'phone_number', 'user_id', 'address', 'city', 'country', 'company_id');
                    },
                    'staffProfile' => function ($query) {
                        $query->select('id', 'first_name', 'last_name', 'phone_number', 'user_id', 'address', 'city', 'country', 'company_id');
                    },
                    'clientProfile.company:id,company_name',
                    'staffProfile.company:id,company_name'
                ])
                ->get(['id', 'role', 'status']);
    
            // Map the users to include profile information
            $users = $users->map(function ($user) {
                $profile = $user->clientProfile ?? $user->staffProfile;
                return [
                    'id' => $profile ? $profile->id : null,
                    'first_name' => $profile ? $profile->first_name : null,
                    'last_name' => $profile ? $profile->last_name : null,
                    'phone_number' => $profile ? $profile->phone_number : null,
                    'role' => $user->role,
                    'address' => $profile ? $profile->address : null,
                    'city' => $profile ? $profile->city : null,
                    'country' => $profile ? $profile->country : null,
                    'company_name' => $profile && $profile->company ? $profile->company->company_name : null,
                    'status' => $user->status
                ];
            });
    
            return response()->json(['users' => $users], 200);
        } catch (\Exception $e) {
            Log::error('Failed to fetch all users: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch all users', 'error' => $e->getMessage()], 500);
        }
    }






}
