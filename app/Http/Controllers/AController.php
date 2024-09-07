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
use App\Models\Project;
use App\Models\Company;
use Illuminate\Support\Facades\Validator;

class AController extends Controller
{
    
    public function createStaff(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), 
            [
                'name' => 'required|string|max:255',
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
                'phone_number' => 'required|string|max:15',
                'company_name' => 'required|string|max:255', 
            ]);
    
            if ($validate->fails()){
                return response()->json([
                    'status'=> false,
                    'errors' => $validate->errors(),
                    'message' => 'Validation Error'
                ], 422);
            }

            $company = Company::firstOrCreate(['company_name' => $request->company_name]);
    
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make($request->password),
                'role' => 'staff', // Default role set to staff
                'status' => 'Deactivated' // not active or still not use 
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
                'extension_name' => $request->extension_name??'', 
                'license_number' => $request->license_number??'',
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
                    'extension_name' => $request->extension_name??'',
                    'license_number' => $request->license_number??'',
                    'company_name' => $company->company_name,
                ]),
            ]);
    
            return response()->json([
                'status' => true,
                'message' => 'Staff registered successfully',
                'user' => $user,
                'staff_profile' => $staffProfile
            ], 201);
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
        
            if ($user->role !== 'admin' && !$user->staffProfile) {
                return response()->json([
                    'status' => false,
                    'message' => 'User does not have an associated staff profile'
                ], 400);
            }
        
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
                'phone_number' => 'required|string|regex:/^\d{10,15}$/',
                'company_name' => 'required_if:user.role,admin|string|max:60', // Add company_name validation for admin
            ]);
        
            $client = null; // Initialize the $client variable
            $company = null; // Initialize the $company variable
        
            try {
                DB::transaction(function () use ($request, $user, $validatedData, &$client, &$company) {
                    $client = User::create([
                        'name' => $validatedData['name'],
                        'email' => $validatedData['email'],
                        'password' => Hash::make($validatedData['password']),
                        'role' => 'client',
                        'created_by' => $user->id, // Track who created the client
                        'updated_by' => $user->id, // Track who last updated the client
                        'status' => 'Not Active'
                    ]);
        
                    if ($user->role === 'admin') {
                        $companyName = $validatedData['company_name'];
        
                        // Check if the company already exists
                        $company = Company::where('company_name', $companyName)->first();
        
                        if ($company) {
                            // Use the existing company's ID
                            $companyId = $company->id;
                        } else {
                            // Create a new company and get its ID
                            $company = Company::create([
                                'company_name' => $companyName,
                                // Add other necessary fields for the company
                            ]);
                            $companyId = $company->id;
                        }
                    } else {
                        // For staff, get the company from the staff profile
                        $companyId = $user->staffProfile->company_id;
                        $company = Company::find($companyId);
        
                        if (!$company) {
                            throw new \Exception('Company not found for the staff user');
                        }
                    }
        
                    $client->ClientProfile()->create([
                        'first_name' => $validatedData['first_name'],
                        'last_name' => $validatedData['last_name'],
                        'sex' => $validatedData['sex'],
                        'address' => $validatedData['address'],
                        'city' => $validatedData['city'],
                        'country' => $validatedData['country'],
                        'zipcode' => $validatedData['zipcode'],
                        'company_id' => $companyId, // Set the company ID
                        'phone_number' => $validatedData['phone_number'],
                    ]);
        
                    $newValues = [
                        'name' => $client->name,
                        'email' => $client->email,
                        'password' => $client->password,
                        'first_name' => $validatedData['first_name'],
                        'last_name' => $validatedData['last_name'],
                        'sex' => $validatedData['sex'],
                        'address' => $validatedData['address'],
                        'city' => $validatedData['city'],
                        'country' => $validatedData['country'],
                        'zipcode' => $validatedData['zipcode'],
                        'company_id' => $companyId, // Set the company ID
                        'phone_number' => $validatedData['phone_number'],
                    ];
        
                    AuditLog::create([
                        'user_id' => $client->id,
                        'editor_id' => auth()->user()->id, // Assuming the editor is the authenticated user
                        'action' => 'create',
                        'old_values' => null,
                        'new_values' => json_encode($newValues),
                    ]);
                });
        
                return response()->json([
                    'message' => 'Client created successfully',
                    'client' => $client,
                    'company' => $company
                ], 201);
            } catch (\Throwable $th) {
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
                $companyId = $user->company_id;

                // Fetch staff under the same company and group them by month
                $staffs = DB::table('staff_profiles')
                    ->where('company_id', $companyId)
                    ->whereNotNull('company_id')  
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

                $staffs = $staffs->map(function ($staff) use ($monthNames) {
                    $staff->month = $monthNames[$staff->month];
                    return $staff;
                });

                return response()->json(['staffs_per_month' => $staffs], 200);
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
                        'company_name' => $company ? $company->company_name : null, // Get company_name from company_id
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
            // Fetch all clients and staff
            $clients = ClientProfile::with('user:id,role','company:id,company_name')->get(['id', 'first_name', 'last_name',  'phone_number', 'user_id','address','city','country','company_id']);
            $staff = StaffProfile::with('user:id,role,status','company:id,company_name')->get(['id', 'first_name', 'last_name', 'phone_number', 'user_id','address','city','country','company_id']);
      
            
            $users = $clients->merge($staff)->map(function ($profile) {
                return [
                    'id' => $profile->id,
                    'first_name' => $profile->first_name,
                    'last_name' => $profile->last_name,
                    'phone_number' => $profile->phone_number,
                    'role' => $profile->user ? $profile->user->role : null,
                    'address' => $profile->address,
                    'city' => $profile->city,
                    'country' => $profile->country,
                    'company_name' => $profile->company ? $profile->company->company_name : null,
                    'status' => $profile->user ? $profile->user->status : null
                ];
            });

            
            return response()->json(['users' => $users], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch all users: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch all users', 'error' => $e->getMessage()], 500);
        }
    }

}
