<?php
namespace App\Http\Controllers;
use App\Models\User;
use App\Mail\CompleteTask;
use App\Mail\TaskDue;
use App\Mail\TaskDueTomorrow;
use App\Models\Project;
use App\Models\Task;
use App\Models\ClientProfile;
use App\Models\StaffProfile;
use App\Models\ProjectLogs;
use App\Models\UsedResources;
use App\Models\AuditLogT;
use App\Models\EstimatedCost;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Dompdf\Dompdf;
use Dompdf\Options;
use Exception;
use Carbon\Carbon;
use Intervention\Image\Facades\Image;
use App\Models\Company;
use App\Models\Resources;
use App\Models\Category;
use DateTime;

class PController extends Controller
{
   
  

    public function addproject(Request $request)
    {
        $user = Auth::user();
    
        if (!in_array($user->role, ['admin', 'staff'])) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
    
        DB::beginTransaction(); 
        DB::enableQueryLog();
        try {
            // Validate the data, excluding company_id for staff
            $validatedData = $request->validate([
                'site_address'=>'required|string',
                'site_city' => 'required|string',
                'site_province' => 'required|string',
                'project_name'=>'required|string',
                'project_type' => 'required|string',
                'client_id' => 'required|integer|exists:client_profiles,id',
                'completion_date' => 'required|date',
                'starting_date' => 'required|date',
                'totalBudget' => 'required|integer',
                'pj_image' => 'nullable|string',  
                'pj_image1' => 'nullable|string', 
                'pj_image2' => 'nullable|string', 
                'pj_pdf' => 'nullable|string', 
                'company_id' => 'required_if:user.role,admin|integer|exists:companies,id', 
                'selected_staff_id' => 'nullable|integer|exists:staff_profiles,id', // Validate selected staff ID
            ]);
    
            // Fetch company_id based on user role
            if ($user->role === 'admin') {
                $companyId = $validatedData['company_id'];
            } else {
                $companyId = $user->staffProfile->company_id;
                if (!$companyId) {
                    throw new \Exception('Company ID not found for the staff user');
                }
            }
    
            $validatedData['company_id'] = $companyId;
    
            // Check if the completion date is more than one day in the past
            $completionDate = Carbon::parse($validatedData['completion_date']);
            if (Carbon::now()->diffInDays($completionDate, false) < -1) {
                $validatedData['status'] = 'D'; // Set status to "D" if completion date is more than one day in the past
            } else {
                $validatedData['status'] = 'OG'; // Set status to on progress by default
            }
    
            // Decode and save the base64 encoded images and PDF
            $fieldsToProcess = ['pj_image', 'pj_image1', 'pj_image2', 'pj_pdf'];
            foreach ($fieldsToProcess as $field) {
                if (isset($validatedData[$field]) && !empty($validatedData[$field])) {
                    $decodedFile = base64_decode($validatedData[$field], true);
                    if ($decodedFile === false) {
                        Log::error("Invalid base64 $field");
                        return response()->json(['message' => "Invalid base64 $field"], 400);
                    }

                    $extension = $field === 'pj_pdf' ? 'pdf' : 'png';
                    $fileName = uniqid() . "_" . time() . ".$extension";
                    $directory = $field === 'pj_pdf' ? 'pdfs/projects/' : 'photos/projects/';
                    $isSaved = Storage::disk('public')->put($directory . $fileName, $decodedFile);

                    if (!$isSaved) {
                        Log::error("Failed to save $field");
                        return response()->json(['message' => "Failed to save $field"], 500);
                    }

                    $filePath = asset("storage/$directory" . $fileName);
                    $validatedData[$field] = $filePath; 
                } else {
                    unset($validatedData[$field]); // Ensure the field is not in the validatedData array if not provided
                }
            }

            // Explicitly remove pj_pdf if it is not provided
            if (!isset($validatedData['pj_pdf']) || empty($validatedData['pj_pdf'])) {
                unset($validatedData['pj_pdf']);
            }
            // Ensure the staff_id exists in the staff_profiles table
            $staffProfile = $user->staffProfile;
            if (!$staffProfile) {
                throw new \Exception('Staff profile not found for the user');
            }
    
            // Check if the staff member has extension name and license number
            if (empty($staffProfile->extension_name) || empty($staffProfile->license_number)) {
                if (empty($validatedData['selected_staff_id'])) {
                    // Fetch staff members with extension names
                    $staffWithExtension = StaffProfile::whereNotNull('extension_name')->get(['id', 'first_name', 'last_name']);
                    return response()->json([
                        'status' => false,
                        'message' => 'Please select a staff member with an extension name',
                        'staff_with_extension' => $staffWithExtension
                    ], 400);
                } else {
                    $staffId = $validatedData['selected_staff_id'];
                }
            } else {
                $staffId = $staffProfile->id;
            }
            
            $validatedData['staff_id'] = $staffId;
            
            $validatedData['total_used_budget'] = 0;
            // Create the project
            $project = Project::create($validatedData);
    
            // Create project log
            ProjectLogs::create([
                'project_id' => $project->id,
                'user_id' => $user->id,
                'staff_id' => $staffId,
                'action' => 'create',
                'description' => 'Project created by ' . $user->name,
                'timestamp' => Carbon::now(),
            ]);
    
            DB::commit(); // Commit the transaction
    
            return response()->json([
                'message' => 'Project created successfully',
                'project' => $project
            ], 201);
        } catch (\Throwable $th) {
            DB::rollBack(); // Rollback the transaction
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }




    /// Fetch all projects for the staff

    public function getProjectsForStaff()
    {
        try {
            $user = Auth::user();
            $staffProfile = $user->staffProfile;
    
            if (!$staffProfile) {
                Log::error('Staff profile not found for authenticated user');
                return response()->json(['message' => 'Staff profile not found for authenticated user'], 404);
            }
    
            // Get the company ID from the staff profile
            $companyId = $staffProfile->company_id;
    
            // Fetch projects for the same company
            $projects = Project::where('company_id', $companyId)
                ->with('client:id,first_name,last_name,phone_number') // Eager load the client relationship
                ->get();
    
            // Map project statuses to descriptive terms
            $statusMapping = [
                'OG' => 'Ongoing',
                'C' => 'Complete',
                'D' => 'Due'
            ];
    
            $projectsWithClientDetails = $projects->map(function ($project) use ($statusMapping) {
                return [
                    'id' => $project->id,
                    'site_address' => $project->site_address,
                    'site_city' => $project->site_city,
                    'project_name' => $project->project_name,
                    'client_id' => $project->client_id,
                    'company_id' => $project->company_id,
                    'status' => $statusMapping[$project->status] ?? $project->status, // Map status
                    'completion_date' => $project->completion_date,
                    'pj_image' => $project->pj_image,
                    'pj_pdf' => $project->pj_pdf,
                    'project_type' => $project->project_type,
                    'starting_date' => $project->starting_date,
                    'totalBudget' => $project->totalBudget,
                    'created_at' => $project->created_at,
                    'updated_at' => $project->updated_at,
                    'client' => [
                        'first_name' => $project->client->first_name,
                        'last_name' => $project->client->last_name,
                        'phone_number' => $project->client->phone_number,
                    ],
                    'staff_in_charge' => [
                    'staff_first_name' => $project->staff->first_name,
                    'staff_last_name' => $project->staff->last_name,
                    'extension_name' => $project->staff->extension_name,
        ],
                ];
            });
    
            return response()->json($projectsWithClientDetails, 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch projects: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Failed to fetch projects'], 500);
        }
    }

    //fetch projects counts for the company

    public function getProjectsCounts($staffId) 
    {
        try {
            // Fetch the staff's company ID
            $staff = StaffProfile::find($staffId);
            if (!$staff) {
                return response()->json(['message' => 'Staff not found'], 404);
            }
            $companyId = $staff->company_id;
    
            // Count all projects under the same company
            $projectCount = Project::where('company_id', $companyId)->count();
    
            // Count done projects under the same company
            $doneProjectCount = Project::where('company_id', $companyId)
                ->where('status', 'C') // Assuming 'done' is the status for completed projects
                ->count();
    
            // Count ongoing projects under the same company
            $ongoingProjectCount = Project::where('company_id', $companyId)
                ->where('status', 'OG') // Assuming 'ongoing' is the status for ongoing projects
                ->count();


            $dueProjectCount = Project::where('company_id', $companyId)
                ->where('status', 'D') // Assuming 'ongoing' is the status for ongoing projects
                ->count();
    
            return response()->json([
                'project_count' => $projectCount,
                'due' => $dueProjectCount,
                'done' => $doneProjectCount,
                'ongoing' => $ongoingProjectCount
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to count projects: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to count projects'], 500);
        }
    }




    public function getAllProjectCounts()
    {
        try {
            $user = Auth::user();

            // Check if the user has the role of 'admin'
            if ($user->role === 'admin') {
                // Fetch all projects
                $projects = Project::all();
            } else {
                $staffProfile = $user->staffProfile;

                if (!$staffProfile) {
                    Log::error('Staff profile not found for user ID: ' . $user->id);
                    return response()->json(['message' => 'Staff profile not found for user ID: ' . $user->id], 404);
                }

                // Get the company ID from the staff profile
                $companyId = $staffProfile->company_id;

                // Fetch all projects for the company
                $projects = Project::where('company_id', $companyId)->get();
            }

        
            $doneCount = 0;
            $ongoingCount = 0;
            $projectCount = $projects->count();
            
            // Iterate through projects and count statuses
            foreach ($projects as $project) {
                if ($project->status === 'C') {
                    $doneCount++;
                } elseif ($project->status === 'OG') {
                    $ongoingCount++;
                }
            }

            return response()->json([
                'done_count' => $doneCount,
                'ongoing_count' => $ongoingCount,
                'project_count' => $projectCount
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to count projects: ' . $e->getMessage(), ['exception' => $e]);
            return response()->json(['message' => 'Failed to count projects'], 500);
        }
    }

 
    //get project and client details
    public function getProjectAndClientDetails($projectId)
    {
        // Get the logged-in user's ID
        $userId = Auth::id();

        // Fetch the company_id of the logged-in user
        $companyId = DB::table('staff_profiles')
            ->where('user_id', $userId)
            ->value('company_id');

        // Fetch the project and client details for the specified project ID and company ID
        $project = DB::table('projects')
            ->join('client_profiles', 'projects.client_id', '=', 'client_profiles.id')
            ->join('staff_profiles', 'projects.company_id', '=', 'staff_profiles.company_id')
            ->where('staff_profiles.company_id', $companyId) // Ensure the project belongs to the same company
            ->where('projects.id', $projectId)
            ->select(
                'projects.id as project_id',
                'projects.site_address',
                'projects.site_city',
                'projects.pj_image',
                'projects.pj_pdf',
                'projects.status',
                'projects.project_name',
                'projects.starting_date as project_starting_date',
                'projects.completion_date as project_completion_date',
                'client_profiles.first_name as first_name',
                'client_profiles.last_name as last_name',
                'client_profiles.address as address',
                'client_profiles.city as city'
            )
            ->first();

            $statusMapping = [
                'OG' => 'Ongoing',
                'C' => 'Complete',
                'D' => 'Due'
            ];




        if ($project) {
            // Return the project and client details as JSON
            $project->status = $statusMapping[$project->status] ?? $project->status;
            return response()->json($project);
        } else {
            // Return a 404 response if the project is not found
            return response()->json(['message' => 'Project not found'], 404);
        }
    }


     // Add a new task to a project
     //new add task for experimentation
     public function addTask(Request $request, $project_id)
     {
         DB::beginTransaction();
         try {
             // Validate the incoming request for task
             $validatedData = $request->validate([
                 'pt_task_name' => 'required|string',
                 'pt_completion_date' => 'required|date',
                 'pt_starting_date' => 'required|date',
                 'resources' => 'nullable|array', // Make resources nullable
                 'resources.*.resource_name' => 'required_with:resources|string', // Validate resource_name only if resources are provided
                 'resources.*.qty' => 'required_with:resources|integer', // Validate qty only if resources are provided
                 'category_id' => 'required|exists:categories,id', // Validate category_id
                 'pt_total_budget' => 'required|numeric', // Total budget for the task
             ]);
     
             $resources = $validatedData['resources'] ?? []; // Default to an empty array if resources are not provided
     
             $validatedData['project_id'] = $project_id;
     
             $project = Project::findOrFail($project_id);
             $totalBudget = $project->totalBudget;
             $cAllocatedBudget = $project->c_allocated_budget;
     
             $staffProfile = StaffProfile::findOrFail($project->staff_id);
             $clientProfile = ClientProfile::findOrFail($project->client_id);
     
             $staffUserId = $staffProfile->user_id;
             $clientUserId = $clientProfile->user_id;
     
             // Calculate the total allocated budget of all existing tasks
             $totalAllocatedBudget = Task::where('project_id', $project_id)->sum('pt_allocated_budget');
     
             // Check if the total budget and total allocated budget are equal
             if ($totalBudget == $totalAllocatedBudget) {
                 return response()->json(['message' => 'Cannot add task: total budget and total allocated budget are equal'], 400);
             }
     
             // Check if adding the new task's budget exceeds the total budget
             if ($totalAllocatedBudget + $validatedData['pt_total_budget'] > $totalBudget) {
                 return response()->json(['message' => 'Cannot add task: allocated budget exceeds the total project budget'], 400);
             }
     
             $category = Category::findOrFail($validatedData['category_id']);
             $cAllocatedBudget = $category->c_allocated_budget;
     
             $totalCategoryAllocatedBudget = Task::where('category_id', $validatedData['category_id'])->sum('pt_allocated_budget');
     
             Log::info('Category Allocated Budget: ' . $cAllocatedBudget);
             Log::info('Total Category Allocated Budget: ' . $totalCategoryAllocatedBudget);
     
             if ($totalCategoryAllocatedBudget + $validatedData['pt_total_budget'] > $cAllocatedBudget) {
                 return response()->json(['message' => 'Cannot add task: allocated budget exceeds the category allocated budget'], 400);
             }
     
             // Check if the completion date has passed with a one-day clearance
             $completionDate = Carbon::parse($validatedData['pt_completion_date']);
             $currentDate = Carbon::now();
     
             Log::info('Completion Date: ' . $completionDate);
             Log::info('Current Date: ' . $currentDate);
             Log::info('Difference in Days: ' . $completionDate->diffInDays($currentDate, false));
     
             if ($completionDate->diffInDays($currentDate, false) >= 1) {
                 $validatedData['pt_status'] = 'D'; // Set status to 'D' if the date is more than one day in the past
                 Log::info('Status set to D');
             } else {
                 $validatedData['pt_status'] = 'OG'; // Set status to 'OG' otherwise
                 Log::info('Status set to OG');
             }
     
             // Create a new task with the validated data and calculated allocated budget
             $taskData = array_merge($validatedData, [
                 'pt_allocated_budget' => $validatedData['pt_total_budget'],
                 'isRemoved' => '0' // Set isRemoved to 0 by default
             ]);
             $task = Task::create($taskData);
     
             // Add resources to the database if provided
             foreach ($resources as $resource) {
                 $resource['task_id'] = $task->id;
                 $resource['total_cost'] = $validatedData['pt_total_budget']; // Use pt_total_budget for total_cost
                 $resource['unit_cost'] = 0; // Set unit_cost to 0
                 $resource['total_used_resources'] = 0;
                 Resources::create($resource);
             }
     
             // Fetch the staff and client emails from the users table
             $staffUser = User::findOrFail($staffUserId);
             $clientUser = User::findOrFail($clientUserId);
             $staffProfile = StaffProfile::where('user_id', $staffUserId)->firstOrFail();
             $clientProfile = ClientProfile::where('user_id', $clientUserId)->firstOrFail();
     
             $staffEmail = $staffUser->email;
             $clientEmail = $clientUser->email;
             $staffCompanyId = $staffProfile->company_id;
             $clientCompanyId = $clientProfile->company_id;
     
             // Fetch the company_name for the staff user
             $staffCompany = Company::find($staffCompanyId);
             if ($staffCompany) {
                 $staffCompanyName = $staffCompany->company_name;
                 Log::info('Staff company name: ' . $staffCompanyName);
             } else {
                 Log::warning('Staff company not found for company_id: ' . $staffUser->company_id);
             }
     
             // Fetch the company_name for the client user
             $clientCompany = Company::find($clientCompanyId);
             if ($clientCompany) {
                 $clientCompanyName = $clientCompany->company_name;
                 Log::info('Client company name: ' . $clientCompanyName);
             } else {
                 Log::warning('Client company not found for company_id: ' . $clientUser->company_id);
             }
     
             // Check if the task is due tomorrow
             if ($completionDate->isTomorrow() && isset($staffCompanyName)) {
                 Mail::to($staffEmail)->send(new TaskDueTomorrow($task, $staffCompanyName));
             }
     
             // Check if the task is past the completion date with a one-day clearance
             if ($completionDate->diffInDays($currentDate, false) >= 1 && isset($clientCompanyName)) {
                 $validatedData['pt_status'] = 'D'; // Mark as due
                 Log::info('Status set to D before sending email');
                 Mail::to($clientEmail)->send(new TaskDue($task, $clientCompanyName));
             }
     
             DB::commit();
             return response()->json(['message' => 'Task created successfully', 'task' => $task, 'resources' => $resources], 201);
         } catch (Exception $e) {
             DB::rollBack();
             Log::error('Failed to add task: ' . $e->getMessage());
             return response()->json(['message' => 'Failed to add task', 'error' => $e->getMessage()], 500);
         }
     }


        public function addCategory(Request $request, $project_id)
        {
            try {
                // Validate the incoming request for category
                $validatedData = $request->validate([
                    'category_name' => 'required|string',
                    'category_allocated_budget' => 'required|integer',
                ]);
        
                // Find the project by ID
                $project = Project::find($project_id);
        
                if (!$project) {
             
                    return response()->json(['message' => 'Project not found'], 404);
                }
        
                // Add the new category to the project
                $category = new Category();
                $category->category_name = $validatedData['category_name'];
                $category->c_allocated_budget = $validatedData['category_allocated_budget'];
                $category->project_id = $project->id; // Set the project_id
        
                // Save the category
                $category->save();
        
                // Return a success response
                return response()->json(['message' => 'Category added successfully', 'category' => $category], 201);
            } catch (\Exception $e) {
              
                return response()->json(['message' => 'Failed to add category', 'error' => $e->getMessage()], 500);
            }
        }



      
        public function addTaskv2(Request $request, $project_id)
        {
            DB::beginTransaction();
            try {
                // Validate the incoming request for task
                $validatedData = $request->validate([
                    'pt_task_name' => 'required|string',
                    'pt_completion_date' => 'required|date',
                    'pt_starting_date' => 'required|date',
                    'pt_photo_task' => 'nullable|string', // Base64 encoded image
                    'pt_file_task' => 'nullable|string',
                    'resources' => 'required|array',
                    'resources.*.resource_name' => 'required|string',
                    'resources.*.qty' => 'required|integer',
                    'resources.*.unit_cost' => 'required|numeric',
                    'category_id' => 'required|exists:categories,id', // Validate category_id
                ]);
            
                // Add the project_id to the validated data
                $validatedData['project_id'] = $project_id;

                // Fetch the project's total budget and c_allocated_budget
                $project = Project::findOrFail($project_id);
                $totalBudget = $project->totalBudget;
                $cAllocatedBudget = $project->c_allocated_budget;

                $staffProfile = StaffProfile::findOrFail($project->staff_id);
                $clientProfile = ClientProfile::findOrFail($project->client_id);

                // Get the user IDs from the profiles
                $staffUserId = $staffProfile->user_id;
                $clientUserId = $clientProfile->user_id;

                // Calculate the total allocated budget of all existing tasks
                $totalAllocatedBudget = Task::where('project_id', $project_id)->sum('pt_allocated_budget');

                // Check if the total budget and total allocated budget are equal
                if ($totalBudget == $totalAllocatedBudget) {
                    return response()->json(['message' => 'Cannot add task: total budget and total allocated budget are equal'], 400);
                }

                // Calculate the total resource cost
                $resources = $validatedData['resources'];
                $totalResourceCost = 0;

                foreach ($resources as $resource) {
                    $totalResourceCost += $resource['qty'] * $resource['unit_cost'];
                }

                // Check if adding the new task's budget exceeds the total budget
                if ($totalAllocatedBudget + $totalResourceCost > $totalBudget) {
                    return response()->json(['message' => 'Cannot add task: allocated budget exceeds the total project budget'], 400);
                }

                $category = Category::findOrFail($validatedData['category_id']);
                $cAllocatedBudget = $category->c_allocated_budget;



                $totalCategoryAllocatedBudget = Task::where('category_id', $validatedData['category_id'])->sum('pt_allocated_budget');


                Log::info('Category Allocated Budget: ' . $cAllocatedBudget);
                Log::info('Total Category Allocated Budget: ' . $totalCategoryAllocatedBudget);
                
                if ($totalCategoryAllocatedBudget + $totalResourceCost > $cAllocatedBudget) {
                    return response()->json(['message' => 'Cannot add task: allocated budget exceeds the category allocated budget'], 400);
                }
        

                // Check if the completion date has passed
                $completionDate = Carbon::parse($validatedData['pt_completion_date']);
                $currentDate = Carbon::now();

                if ($completionDate->isPast()) {
                    $validatedData['pt_status'] = 'D'; // Set status to 'D' if the date has passed
                } else {
                    $validatedData['pt_status'] = 'OG'; // Set status to 'OG' otherwise
                }

                // Decode the base64 encoded photo, if present
                if (!empty($validatedData['pt_photo_task'])) {
                    $decodedImage = base64_decode($validatedData['pt_photo_task'], true);
                    if ($decodedImage === false) {
                        Log::error('Invalid base64 image');
                        return response()->json(['message' => 'Invalid base64 image'], 400);
                    }
                    // Save the decoded image to a file or storage
                    $imageName = time() . '.webp';
                    $isSaved = Storage::disk('public')->put('photos/tasks/' . $imageName, $decodedImage);

                    if (!$isSaved) {
                        Log::error('Failed to save image');
                        return response()->json(['message' => 'Failed to save image'], 500);
                    }

                    $photoPath = asset('storage/photos/tasks/' . $imageName); // Set the photo path
                    $validatedData['pt_photo_task'] = $photoPath;
                }

                // Decode the base64 encoded PDF, if present
                if (!empty($validatedData['pt_file_task'])) {
                    $decodedPdf = base64_decode($validatedData['pt_file_task'], true);
                    if ($decodedPdf === false) {
                        Log::error('Invalid base64 PDF');
                        return response()->json(['message' => 'Invalid base64 PDF'], 400);
                    }

                    $pdfName = time() . '.pdf';
                    $isSaved = Storage::disk('public')->put('pdfs/tasks/' . $pdfName, $decodedPdf);

                    if (!$isSaved) {
                        Log::error('Failed to save PDF');
                        return response()->json(['message' => 'Failed to save PDF'], 500);
                    }

                    $pdfPath = asset('storage/pdfs/tasks/' . $pdfName); // Set the PDF path
                    $validatedData['pt_file_task'] = $pdfPath;
                }

                // Create a new task with the validated data and calculated allocated budget
                $taskData = array_merge($validatedData, ['pt_allocated_budget' => $totalResourceCost]);
                $task = Task::create($taskData);
                $task->photo_path = $validatedData['pt_photo_task'] ?? null;
                $task->pdf_path = $validatedData['pt_file_task'] ?? null;

                // Add resources to the database
                foreach ($resources as $resource) {
                    $resource['task_id'] = $task->id;
                    $resource['total_cost'] = $resource['qty'] * $resource['unit_cost'];
                    $resource['total_used_resources'] = 0;
                    Resources::create($resource);
                }

                // Fetch the staff and client emails from the users table
                $staffUser = User::findOrFail($staffUserId);
                $clientUser = User::findOrFail($clientUserId);
                $staffEmail = $staffUser->email;
                $clientEmail = $clientUser->email;
                Log::info('Client email: ' . $clientEmail); 
                Log::info('Staff email: ' . $staffEmail);

                if ($validatedData['pt_status'] == 'D') {
                    Mail::to($clientEmail)->send(new TaskDue($task));
                }

                // Check if the task is due tomorrow
                if ($completionDate->isTomorrow()) {
                    Mail::to($staffEmail)->send(new TaskDueTomorrow($task));
                }

                DB::commit();
                return response()->json(['message' => 'Task created successfully', 'task' => $task, 'resources' => $resources], 201);
            } catch (Exception $e) {
                DB::rollBack();
                Log::error('Failed to add task: ' . $e->getMessage());
                return response()->json(['message' => 'Failed to add task', 'error' => $e->getMessage()], 500);
            }
        }

    public function getProjectTaskImages($project_id)
        {
            try {
                // Fetch tasks related to the given project ID, selecting only the update_img and pt_photo_task columns
                $tasks = Task::where('project_id', $project_id)->get(['update_img', 'pt_photo_task']);

                // Process images to lower their quality
                foreach ($tasks as $task) {
                    if (!empty($task->pt_photo_task)) {
                        $task->pt_photo_task = $this->processImage($task->pt_photo_task);
                    }
                    if (!empty($task->update_img)) {
                        $task->update_img = $this->processImage($task->update_img);
                    }
                }

                // Return the tasks in a JSON response
                return response()->json(['tasks' => $tasks], 200);
            } catch (Exception $e) {
                Log::error('Failed to fetch project tasks with images: ' . $e->getMessage());
                return response()->json(['message' => 'Failed to fetch project tasks with images', 'error' => $e->getMessage()], 500);
            }
    }

    private function processImage($imagePath)
    {
        try {
            // Load the image
            $image = Image::make(public_path($imagePath));

            // Resize the image to a width of 800 and constrain aspect ratio (auto height)
            $image->resize(800, null, function ($constraint) {
                $constraint->aspectRatio();
            });

            // Save the image with lower quality (50%)
            $imageName = pathinfo($imagePath, PATHINFO_FILENAME) . '_low_quality.webp';
            $image->save(public_path('storage/photos/tasks/' . $imageName), 50, 'webp');

            // Return the new image path
            return asset('storage/photos/tasks/' . $imageName);
        } catch (Exception $e) {
            Log::error('Failed to process image: ' . $e->getMessage());
            return $imagePath; // Return original path if processing fails
        }
    }

    //fetchproject tasks
        public function getProjectTasks($project_id)
        {
            try {
                // Fetch all tasks related to the given project ID
                $tasks = Task::where('project_id', $project_id)
                ->where('isRemoved', '0')
                ->with('resources:id,task_id,resource_name,qty,unit_cost,total_cost', 'category:id,category_name')
                ->get(['id', 'project_id', 'pt_status', 'pt_task_name', 'pt_updated_at', 'pt_completion_date', 'pt_starting_date', 'pt_photo_task', 'pt_allocated_budget', 'created_at', 'updated_at', 'pt_file_task','category_id']);
        
                // Calculate the total number of tasks
                $totalTasks = $tasks->count();
        
                // Calculate the total allocated budget
                $totalAllocatedBudget = $tasks->sum('pt_allocated_budget');

             
        
                // Return the tasks, total number of tasks, and total allocated budget in a JSON response
                return response()->json([
                    'tasks' => $tasks,
                    'totalTasks' => $totalTasks,
                    'totalAllocatedBudget' => $totalAllocatedBudget
                
                ], 200);
            } catch (Exception $e) {
                Log::error('Failed to fetch project tasks: ' . $e->getMessage());
                return response()->json(['message' => 'Failed to fetch project tasks', 'error' => $e->getMessage()], 500);
            }
        }


      
        public function getSortedProjectTasks2($project_id)
        {
            try {
                // Fetch all categories related to the project
                $categories = Category::where('project_id', $project_id)->get()->keyBy('id');
        
                // Fetch all tasks related to the given project ID
                $tasks = Task::where('project_id', $project_id)->get();
        
                // Fetch all used resources
                $usedResources = UsedResources::with('resource')->get();
                Log::info('UsedResources Count after initialization: ' . $usedResources->count());
        
                $usedResources->each(function ($usedResource) {
                    Log::info('UsedResource - created_at: ' . $usedResource->created_at . ', resource_id: ' . $usedResource->resource_id . ', total_used_resources: ' . $usedResource->resource->total_used_resources);
                });
        
                $resources = Resources::all()->keyBy('id');
        
                // Get unique category names from the categories
                $customOrder = $categories->pluck('category_name')->toArray();
        
                // Sort the tasks based on the custom order
                $sortedTasks = $tasks->sort(function ($a, $b) use ($categories) {
                    $posA = array_search($categories[$a->category_id]->category_name, $categories->pluck('category_name')->toArray());
                    $posB = array_search($categories[$b->category_id]->category_name, $categories->pluck('category_name')->toArray());
        
                    return $posA - $posB;
                });
        
                // Calculate the total allocated budget per category
                $totalAllocatedBudgetPerCategory = [];
                $totalBudget = $tasks->sum('pt_allocated_budget');
        
                foreach ($customOrder as $categoryName) {
                    $category = $categories->firstWhere('category_name', $categoryName);
                    $categoryId = $category->id;
                    $categoryTasks = $tasks->where('category_id', $categoryId);
                    $categoryBudget = $categoryTasks->sum('pt_allocated_budget');
        
                    // Fetch the c_allocated_budget from the Category model
                    $categoryAllocatedBudget = $category->c_allocated_budget;
        
                    // Calculate the percentage value of the category based on c_allocated_budget
                    $categoryPercentage = $categoryAllocatedBudget > 0 ? ($categoryBudget / $categoryAllocatedBudget) * 100 : 0;
        
                    // Calculate the percentage for each task based on the status and budget
                    $categoryTasksWithPercentage = $categoryTasks->map(function ($task) use ($categoryAllocatedBudget, $totalBudget, $resources, $usedResources) {
                        // Fetch resources for the task from the used_resources table and join with resources table
                        $taskResources = DB::table('used_resources')
                            ->join('resources', 'used_resources.resource_id', '=', 'resources.id')
                            ->where('resources.task_id', $task->id)
                            ->get(['resources.id as resource_id', 'resources.resource_name as resource_name', 'resources.total_used_resources', 'used_resources.resource_qty', 'resources.unit_cost', 'used_resources.created_at']);
        
                        $task->resources = $taskResources->map(function ($resource) {
                            return [
                                'id' => $resource->resource_id,
                                'name' => $resource->resource_name,
                                'total_used_resources' => $resource->total_used_resources,
                                'qty' => $resource->resource_qty,
                                'unit_cost' => $resource->unit_cost,
                                'used_date' => $resource->created_at
                            ];
                        });
        
                        // Calculate the total used resources for the task
                        $totalUsedResources = $task->resources->sum(function ($resource) {
                            return $resource['unit_cost'] * $resource['total_used_resources'];
                        });
                        Log::info('Total used resources: ' . $totalUsedResources);
        
                        // Calculate the percentage based on total used resources
                        $task->percentage = $totalUsedResources >= $task->pt_allocated_budget ? 100 : ($totalUsedResources / $task->pt_allocated_budget) * 100;
                        Log::info('Task percentage: ' . $task->percentage);
        
                        // Filter used resources for this task created before today
                        $filteredResourcesTask = $usedResources->filter(function ($usedResource) use ($task, $resources) {
                            $resource = $resources->get($usedResource->resource_id);
                            $isTaskMatch = $resource && $resource->task_id == $task->id;
                            $isBeforeToday = $usedResource->created_at < Carbon::today();
        
                            return $isTaskMatch && $isBeforeToday;
                        });
        
                        // Calculate previous cost for this task
                        $previousCostTask = $filteredResourcesTask->sum(function ($usedResource) use ($resources) {
                            if (isset($resources[$usedResource->resource_id])) {
                                return $usedResource->resource_qty * $resources[$usedResource->resource_id]->unit_cost;
                            } else {
                                return 0;
                            }
                        });
        
                        // Calculate this period cost (resources used today) for this task
                        $thisPeriodResourcesTask = $usedResources->filter(function ($usedResource) use ($task, $resources) {
                            $resource = $resources->get($usedResource->resource_id);
                            $isTaskMatch = $resource && $resource->task_id == $task->id;
                            $isTodayOrAfter = $usedResource->created_at >= Carbon::today();
        
                            return $isTaskMatch && $isTodayOrAfter;
                        });
        
                        $thisPeriodCostTask = $thisPeriodResourcesTask->sum(function ($usedResource) use ($resources) {
                            if (isset($resources[$usedResource->resource_id])) {
                                return $usedResource->resource->total_used_resources * $resources[$usedResource->resource_id]->unit_cost;
                            } else {
                                return 0;
                            }
                        });
        
                        // Calculate to date cost for this task
                        $toDateCostTask = $task->pt_allocated_budget - $previousCostTask + $thisPeriodCostTask;
        
                        // Add the calculated costs to the task
                        $task->previousCostTask = $previousCostTask;
                        $task->thisPeriodCostTask = $thisPeriodCostTask;
                        $task->toDateCostTask = $toDateCostTask;
        
                        return $task;
                    });
        
                    $totalUsedResources = $categoryTasksWithPercentage->reduce(function ($carry, $task) {
                        return $carry + $task->resources->sum('total_used_resources');
                    }, 0);
        
                    Log::info('Total usedResources Count: ' . $usedResources->count());
        
                    // Filter used resources for this category created before today
                    $filteredResources = $usedResources->filter(function ($usedResource) use ($categoryId, $resources) {
                        $resource = $resources->get($usedResource->resource_id);
                        $task = $resource ? Task::find($resource->task_id) : null;
                        
                        // Log the resource and task details
                        Log::info('Filtering Resource:', [
                            'resource_id' => $usedResource->resource_id,
                            'resource_exists' => $resource ? 'yes' : 'no',
                            'task_id' => $task ? $task->id : 'null',
                            'task_category_id' => $task ? $task->category_id : 'null',
                            'category_match' => $task && $task->category_id == $categoryId ? 'yes' : 'no',
                            'used_date' => $usedResource->created_at,
                            'is_before_today' => $usedResource->created_at < Carbon::today() ? 'yes' : 'no'
                        ]);
                    
                        return $task && $task->category_id == $categoryId && $usedResource->created_at < Carbon::today();
                    });
                    
                    // Calculate previous cost for this category
                    $previousCost = $filteredResources->sum(function ($usedResource) use ($resources) {
                        if (isset($resources[$usedResource->resource_id])) {
                            $cost = $usedResource->resource_qty * $resources[$usedResource->resource_id]->unit_cost;
                            
                            return $cost;
                        } else {
                         
                          
                            return 0;
                        }
                    });
                    
           
                    Log::info('Previous Cost:', ['previousCost' => $previousCost]);
        
                    // Filter used resources for this category created today or after
                    $thisPeriodResources = $usedResources->filter(function ($usedResource) use ($categoryId, $resources) {
                        $resource = $resources->get($usedResource->resource_id);
                        $task = $resource ? Task::find($resource->task_id) : null;
                    
                        return $task && $task->category_id == $categoryId && $usedResource->created_at >= Carbon::today();
                    });
                    
                    // Calculate this period cost for this category
                    $thisPeriodCost = $thisPeriodResources->sum(function ($usedResource) use ($resources) {
                        if (isset($resources[$usedResource->resource_id])) {
                            $cost = $usedResource->resource_qty * $resources[$usedResource->resource_id]->unit_cost;
                    
                    
                            return $cost;
                        } else {
                          
                          
                            return 0;
                        }
                    });
                    
                    // Log the this period cost for debugging purposes
                    Log::info('This Period Cost:', ['thisPeriodCost' => $thisPeriodCost]);
        
                    // Calculate to date cost for this category
                    $toDateCost = $categoryAllocatedBudget - ($previousCost + $thisPeriodCost);
                    Log::info('This Date Cost: ' . $toDateCost);
        
                    $categoryData = [
                        'category_id' => $categoryId,
                        'c_allocated_budget' => $categoryAllocatedBudget,
                        'tasks' => $categoryTasksWithPercentage->values()->all(),
                        'totalAllocatedBudget' => $categoryBudget,
                        'totalUsedResources' => $totalUsedResources,
                        'previousCost' => $previousCost,
                        'thisPeriodCost' => $thisPeriodCost,
                        'toDateCost' => $toDateCost
                    ];
        
                    // Only add 'progress' if the task status is 'C'
                    $completedTasks = $categoryTasksWithPercentage->where('pt_status', 'C');
                    if ($completedTasks->isNotEmpty()) {
                        $completedBudget = $completedTasks->sum('pt_allocated_budget');
                        $categoryData['progress'] = $categoryAllocatedBudget > 0 ? ($completedBudget / $categoryAllocatedBudget) * 100 : 0;
                    } else {
                        $categoryData['progress'] = 0;
                    }
        
                    $totalAllocatedBudgetPerCategory[$categoryName] = $categoryData;
                }
        
                // Return the sorted tasks and total allocated budget per category in a JSON response
                return response()->json([
                    'Category' => $totalAllocatedBudgetPerCategory
                ], 200);
            } catch (Exception $e) {
                Log::error('Failed to fetch and sort project tasks: ' . $e->getMessage());
                return response()->json(['message' => 'Failed to fetch and sort project tasks', 'error' => $e->getMessage()], 500);
            }
        }


        public function getSortedProjectTasks3($project_id)
        {
            try {
                // Fetch all categories related to the project where isRemoved is 0
                $categories = Category::where('project_id', $project_id)
                                      ->where('isRemoved', '0')
                                      ->get()
                                      ->keyBy('id');
        
                // Fetch all tasks related to the given project ID
                $tasks = Task::where('project_id', $project_id)
                             ->where('isRemoved', '0')
                             ->get();
        
                $resources = Resources::all()->keyBy('id');
        
                // Get unique category names from the categories
                $customOrder = $categories->pluck('category_name')->toArray();
        
                // Sort the tasks based on the custom order
                $sortedTasks = $tasks->sort(function ($a, $b) use ($categories) {
                    // Check if category_id exists in the categories collection
                    if (!isset($categories[$a->category_id]) || !isset($categories[$b->category_id])) {
                        return 0; // If either category_id is not found, consider them equal
                    }
        
                    $posA = array_search($categories[$a->category_id]->category_name, $categories->pluck('category_name')->toArray());
                    $posB = array_search($categories[$b->category_id]->category_name, $categories->pluck('category_name')->toArray());
        
                    return $posA - $posB;
                });
        
                // Calculate the total allocated budget per category
                $totalAllocatedBudgetPerCategory = [];
        
                foreach ($customOrder as $categoryName) {
                    $category = $categories->firstWhere('category_name', $categoryName);
                    $categoryId = $category->id;
                    $categoryTasks = $tasks->where('category_id', $categoryId);
                    $categoryBudget = $categoryTasks->sum('pt_allocated_budget');
        
                    // Fetch the c_allocated_budget from the Category model
                    $categoryAllocatedBudget = $category->c_allocated_budget;
        
                    // Calculate the percentage value of the category based on c_allocated_budget
                    $categoryPercentage = $categoryAllocatedBudget > 0 ? ($categoryBudget / $categoryAllocatedBudget) * 100 : 0;
        
                    // Calculate the percentage for each task based on the status and budget
                    $categoryTasksWithPercentage = $categoryTasks->map(function ($task) use ($resources) {
                        if ($task->isRemoved != 0) {
                            return $task;
                        }
        
                        // Fetch the estimated resource values for the task
                        $estimatedCosts = EstimatedCost::where('task_id', $task->id)->get();
                        $totalEstimatedResourceValue = $estimatedCosts->sum('estimated_resource_value');
        
                        $task->percentage = $totalEstimatedResourceValue >= $task->pt_allocated_budget ? 100 : ($totalEstimatedResourceValue / $task->pt_allocated_budget) * 100;
                        Log::info('Task percentage: ' . $task->percentage);
        
                        $currentWeekStartTask = Carbon::now()->startOfWeek(Carbon::SUNDAY);
        
                        $previousCostTask = EstimatedCost::where('task_id', $task->id)
                            ->where('created_at', '<', $currentWeekStartTask)
                            ->sum('estimated_resource_value');
        
                        $thisPeriodCostTask = EstimatedCost::where('task_id', $task->id)
                            ->where('created_at', '>=', Carbon::today())
                            ->sum('estimated_resource_value');
        
                        // Calculate to date cost for this task
                        $toDateCostTask = $previousCostTask + $thisPeriodCostTask;
        
                        // Add the calculated costs to the task
                        $task->previousCostTask = $previousCostTask;
                        $task->thisPeriodCostTask = $thisPeriodCostTask;
                        $task->toDateCostTask = $toDateCostTask;
        
                        unset($task->pt_updated_at, $task->pt_completion_date, $task->pt_starting_date, $task->pt_photo_task, $task->pt_file_task, $task->created_at, $task->updated_at);
        
                        return $task;
                    });
        
                    $currentWeekStart = Carbon::now()->startOfWeek(Carbon::SUNDAY);
        
                    // Calculate the previous cost for all values before the start of the current week
                    $previousCost = EstimatedCost::whereIn('task_id', function ($query) use ($categoryId) {
                        $query->select('id')
                            ->from('project_tasks')
                            ->where('isRemoved', '0')
                            ->where('category_id', $categoryId);
                    })->where('created_at', '<', $currentWeekStart)->sum('estimated_resource_value');
        
                    Log::info('Previous Cost:', ['previousCost' => $previousCost]);
        
                    // Calculate this period cost for this category (today or after)
                    $thisPeriodCost = EstimatedCost::whereIn('task_id', function ($query) use ($categoryId) {
                        $query->select('id')
                            ->from('project_tasks')
                            ->where('isRemoved', '0')
                            ->where('category_id', $categoryId);
                    })->whereBetween('created_at', [
                        $currentWeekStart,
                        Carbon::now()
                    ])->sum('estimated_resource_value');
        
                    Log::info('This Period Cost:', ['thisPeriodCost' => $thisPeriodCost]);
        
                    // Calculate to date cost for this category
                    $toDateCost = $previousCost + $thisPeriodCost;
                    Log::info('This Date Cost: ' . $toDateCost);
        
                    $categoryData = [
                        'category_id' => $categoryId,
                        'c_allocated_budget' => $categoryAllocatedBudget,
                        'tasks' => $categoryTasksWithPercentage->values()->all(),
                        'totalAllocatedBudget' => $categoryBudget,
                        'previousCost' => $previousCost,
                        'thisPeriodCost' => $thisPeriodCost,
                        'toDateCost' => $toDateCost
                    ];
        
                    // Only add 'progress' if the task status is 'C'
                    $completedTasks = $categoryTasksWithPercentage->filter(function ($task) {
                        return $task->pt_status === 'C' && $task->isRemoved == 0;
                    });
                    Log::info('Completed Tasks Count: ' . $completedTasks->count()); // Additional logging
                    Log::info('Completed Tasks: ' . $completedTasks->pluck('id')->toJson()); // Log task IDs
        
                    if ($completedTasks->isNotEmpty()) {
                        $completedBudget = $completedTasks->sum('pt_allocated_budget');
                        $categoryData['progress'] = $categoryAllocatedBudget > 0 ? ($completedBudget / $categoryAllocatedBudget) * 100 : 0;
                    } else {
                        $categoryData['progress'] = 0;
                    }
        
                    $totalAllocatedBudgetPerCategory[$categoryName] = $categoryData;
                }
        
                // Return the sorted tasks and total allocated budget per category in a JSON response
                return response()->json([
                    'Category' => $totalAllocatedBudgetPerCategory
                ], 200);
            } catch (Exception $e) {
                Log::error('Failed to fetch and sort project tasks: ' . $e->getMessage());
                return response()->json(['message' => 'Failed to fetch and sort project tasks', 'error' => $e->getMessage()], 500);
            }
        }

        
        public function getProjectTasksGroupedByMonth($project_id)
        {
            try {
                // Fetch all tasks related to the given project ID and group them by month and year
                $tasks = DB::table('project_tasks')
                    ->where('project_id', $project_id)
                    ->select(
                        DB::raw('YEAR(pt_starting_date) as year'),
                        DB::raw('MONTH(pt_starting_date) as month'),
                        DB::raw('COUNT(id) as task_count')
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

                $tasks = $tasks->map(function ($task) use ($monthNames) {
                    $task->month = $monthNames[$task->month];
                    return $task;
                });

                // Return the tasks in a JSON response
                return response()->json(['tasks_per_month' => $tasks], 200);
            } catch (Exception $e) {
                Log::error('Failed to fetch project tasks grouped by month: ' . $e->getMessage());
                return response()->json(['message' => 'Failed to fetch project tasks grouped by month', 'error' => $e->getMessage()], 500);
            }
        }



       public function getTasksByCategory($project_id)
        {
            try {
                // Fetch the project to get the total budget
                $project = Project::findOrFail($project_id);
                $projectTotalBudget = $project->totalBudget;

                // Fetch all categories related to the given project ID
                $categories = Category::where('project_id', $project_id)->get();

                // Calculate the total allocated budget for all categories
                $totalAllocatedBudget = $categories->sum('c_allocated_budget');

                // Fetch all tasks related to the given project ID
                $tasks = Task::where('project_id', $project_id)->get(['pt_status', 'category_id', 'pt_completion_date']);

                // Initialize the result array
                $totalAllocatedBudgetPerCategory = [];

                foreach ($categories as $category) {
                    $categoryTasks = $tasks->where('category_id', $category->id);
                    $categoryBudget = $category->c_allocated_budget;

                    $totalAllocatedBudgetPerCategory[$category->category_name] = [
                        'tasks' => $categoryTasks->map(function ($task) {
                            return [
                                'pt_status' => $task->pt_status,
                                'pt_allocated_budget' => $task->pt_allocated_budget
                            ];
                        })->values()->all(),
                        'totalAllocatedBudget' => $categoryBudget,
                        'percentage' => $projectTotalBudget > 0 ? ($categoryBudget / $projectTotalBudget) * 100 : 0,
                    ];
                }

                // Return the tasks grouped by category and total allocated budget per category in a JSON response
                return response()->json([
                    'totalAllocatedBudgetPerCategory' => $totalAllocatedBudgetPerCategory,
                    'totalAllocatedBudget' => $totalAllocatedBudget
                ], 200);
            } catch (Exception $e) {
                Log::error('Failed to fetch tasks by category: ' . $e->getMessage());
                return response()->json(['message' => 'Failed to fetch tasks by category', 'error' => $e->getMessage()], 500);
            }
        }
                

    public function getProjectsPerMonth() // fetch projects per month
    {
        try {
            // Get the logged-in user
            $user = Auth::user();

            // Check if the user is an admin
            if ($user->role === 'admin') {
                // Admins can access all projects
                $projects = DB::table('projects')
                    ->join('client_profiles', 'projects.client_id', '=', 'client_profiles.id')
                    ->whereNotNull('projects.starting_date') // Ensure starting_date is not null
                    ->select(
                        DB::raw('YEAR(projects.starting_date) as year'),
                        DB::raw('MONTH(projects.starting_date) as month'),
                        DB::raw('COUNT(projects.id) as project_count')
                    )
                    ->groupBy('year', 'month')
                    ->orderBy('year', 'asc')
                    ->orderBy('month', 'asc')
                    ->get();
            } else {
                // Get the logged-in staff profile
                $staffProfile = StaffProfile::where('user_id', $user->id)->first();

                if (!$staffProfile) {
                    Log::error('Staff profile not found for user_id: ' . $user->id);
                    return response()->json(['error' => 'Staff profile not found'], 404);
                }

                // Non-admin users can only access projects from their own company
                $companyId = $staffProfile->company_id;
                Log::info('Fetching projects for company ID: ' . $companyId);

                $projects = DB::table('projects')
                    ->join('client_profiles', 'projects.client_id', '=', 'client_profiles.id')
                    ->where('projects.company_id', $companyId)
                    ->whereNotNull('projects.starting_date') // Ensure starting_date is not null
                    ->select(
                        DB::raw('YEAR(projects.starting_date) as year'),
                        DB::raw('MONTH(projects.starting_date) as month'),
                        DB::raw('COUNT(projects.id) as project_count')
                    )
                    ->groupBy('year', 'month')
                    ->orderBy('year', 'asc')
                    ->orderBy('month', 'asc')
                    ->get();
            }

            // Map month numbers to month names
            $monthNames = [
                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
            ];

            $projects = $projects->map(function ($project) use ($monthNames) {
                $project->month = $monthNames[$project->month];
                return $project;
            });

            Log::info('Projects fetched successfully');
            return response()->json(['projects_per_month' => $projects], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch projects per month: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch projects per month', 'message' => $e->getMessage()], 500);
        }
    }





    public function getProjectDetails($project_id)
    {
        try {
            // Fetch project details
          $project = DB::table('projects')
            ->join('staff_profiles', 'projects.staff_id', '=', 'staff_profiles.id')
            ->join('client_profiles', 'projects.client_id', '=', 'client_profiles.id')
            ->where('projects.id', $project_id)
            ->select('projects.*', 'staff_profiles.first_name as staff_first_name', 'staff_profiles.last_name as staff_last_name', 'staff_profiles.extension_name', 'staff_profiles.license_number', 'client_profiles.first_name as client_first_name', 'client_profiles.last_name as client_last_name')
            ->first();

            if (!$project) {
                return response()->json(['error' => 'Project not found'], 404);
            }

            // Fetch tasks grouped by month
           

            // Combine project details and tasks
            $response = [
                'project' => $project, 
            ];

            return response()->json($response, 200);
        } catch (\Exception $e) {
            return response()->json(['error' => 'An error occurred while fetching project details'], 500);
        }
    }

    public function getProjectPerYear(){
        try {
            // Get the logged-in user
            $user = Auth::user();
    
            // Check if the user is an admin
            if ($user->role === 'admin') {
                // Admins can access all projects
                $projects = DB::table('projects')
                    ->join('client_profiles', 'projects.client_id', '=', 'client_profiles.id')
                    ->whereNotNull('projects.starting_date') // Ensure starting_date is not null
                    ->select(
                        DB::raw('YEAR(projects.starting_date) as year'),
                        DB::raw('MONTH(projects.starting_date) as month'),
                        DB::raw('SUM(CASE WHEN projects.status = "D" THEN 1 ELSE 0 END) as due'),
                        DB::raw('SUM(CASE WHEN projects.status = "C" THEN 1 ELSE 0 END) as complete'),
                        DB::raw('SUM(CASE WHEN projects.status = "OG" THEN 1 ELSE 0 END) as ongoing')
                    )
                    ->groupBy('year', 'month')
                    ->orderBy('year', 'asc')
                    ->orderBy('month', 'asc')
                    ->get();
            } else {
                // Get the logged-in staff profile
                $staffProfile = StaffProfile::where('user_id', $user->id)->first();
    
                if (!$staffProfile) {
                    Log::error('Staff profile not found for user_id: ' . $user->id);
                    return response()->json(['error' => 'Staff profile not found'], 404);
                }
    
                // Non-admin users can only access projects from their own company
                $companyId = $staffProfile->company_id;
                Log::info('Fetching projects for company ID: ' . $companyId);
    
                $projects = DB::table('projects')
                    ->join('client_profiles', 'projects.client_id', '=', 'client_profiles.id')
                    ->where('projects.company_id', $companyId)
                    ->whereNotNull('projects.starting_date') // Ensure starting_date is not null
                    ->select(
                        DB::raw('YEAR(projects.starting_date) as year'),
                        DB::raw('MONTH(projects.starting_date) as month'),
                        DB::raw('SUM(CASE WHEN projects.status = "D" THEN 1 ELSE 0 END) as due'),
                        DB::raw('SUM(CASE WHEN projects.status = "C" THEN 1 ELSE 0 END) as complete'),
                        DB::raw('SUM(CASE WHEN projects.status = "OG" THEN 1 ELSE 0 END) as ongoing')
                    )
                    ->groupBy('year', 'month')
                    ->orderBy('year', 'asc')
                    ->orderBy('month', 'asc')
                    ->get();
            }
    
            // Map month numbers to month names
            $monthNames = [
                1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
                5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
                9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
            ];
    
            $projects = $projects->map(function ($project) use ($monthNames) {
                $project->month = $monthNames[$project->month];
                return $project;
            });
    
            Log::info('Projects status fetched successfully');
            return response()->json(['projects_status_per_month' => $projects], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch projects status per month: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch projects status per month', 'message' => $e->getMessage()], 500);
        }
    }


    public function getAllProjectsFilteredByCompanies()
    {
        try {
            // Get the logged-in user
            $user = Auth::user();

            // Check if the user is an admin
            if ($user->role === 'admin') {
                // Admins can access all projects
                $projects = DB::table('projects')
                    ->join('companies', 'projects.company_id', '=', 'companies.id')
                    ->select(
                        'companies.company_name as company_name',
                        DB::raw('COUNT(projects.id) as project_count')
                    )
                    ->groupBy('companies.company_name')
                    ->get();
            } else {
                // Get the logged-in staff profile
                $staffProfile = StaffProfile::where('user_id', $user->id)->first();

                if (!$staffProfile) {
                    Log::error('Staff profile not found for user_id: ' . $user->id);
                    return response()->json(['error' => 'Staff profile not found'], 404);
                }

                // Non-admin users can only access projects from their own company
                $companyId = $staffProfile->company_id;

                $projects = DB::table('projects')
                    ->join('companies', 'projects.company_id', '=', 'companies.id')
                    ->where('projects.company_id', $companyId)
                    ->select(
                        'companies.company_name as company_name',
                        DB::raw('COUNT(projects.id) as project_count')
                    )
                    ->groupBy('companies.company_name')
                    ->get();
            }

            // Return the projects and their counts in a JSON response
            return response()->json(['projects' => $projects], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch all projects: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch all projects', 'error' => $e->getMessage()], 500);
        }
    }

    
  



    public function getAllProjectsForAdmin()
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
            // Fetch all projects, including those from other companies
            $projects = Project::with('client:id,first_name,last_name,phone_number','company:id,company_name','staff:id,first_name,last_name') // Eager load the client
                ->get();
    
            return response()->json([
                'projects' => $projects
            ], 200);
        } catch (\Throwable $th) {
            Log::error('Failed to fetch projects for admin: ' . $th->getMessage());
            return response()->json([
                'message' => 'Failed to fetch projects'
            ], 500);
        }
    }


    public function getClientProjects($clientId)
    {
        try {
            // Fetch the client
            $client = ClientProfile::find($clientId);

            if (!$client) {
                return response()->json(['message' => 'Client not found'], 404);
            }


            // Fetch the projects associated with the client
            $projects = Project::where('client_id', $clientId)
                ->with('staff:id,first_name,last_name,extension_name', 'company:id,company_name') 
                ->get();

               $projects =$projects -> map(function ($project){
                    return [
                        "id" => $project->id,
                        "site_address" => $project->site_address,
                        "site_city" => $project->site_city,
                        "site_province"=> $project->site_province,
                        "project_name" => $project->project_name,
                        "status" => $project->status,
                        "completion_date" => $project->completion_date,
                        "pj_image" => $project->pj_image,
                        "totalBudget" => $project->totalBudget,
                        "starting_date" => $project->starting_date,
                        "status" => $project->status,
                        "first_name" => $project->staff->first_name,
                        "last_name" => $project->staff->last_name,
                        "company_name" => $project->company->company_name,
                        "extension_name" => $project->staff->extension_name,
                    ];
               });

            return response()->json(['projects' => $projects], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch client projects: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch client projects', 'error' => $e->getMessage()], 500);
        }
    }

    public function updateProjectStatus($projectId)
    {
        try {
            $user = Auth::user();

            // Check if the user is a staff member
            if ($user->role !== 'staff') {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Fetch the project
            $project = Project::find($projectId);

            if (!$project) {
                return response()->json(['message' => 'Project not found'], 404);
            }

            // Check if the staff member is assigned to the project
            if ($project->staff_id !== $user->staffProfile->id) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }

            // Fetch all tasks associated with the project
            $tasks = Task::where('project_id', $projectId)->get();

            // Check if all tasks have a status of 'C'
            $allTasksCompleted = $tasks->every(function ($task) {
         
                return $task->pt_status === 'C';
            });

            // Calculate the total budget used
            $totalBudgetUsed = $project->total_used_budget;
            $budgetUsedPercentage = ($totalBudgetUsed / $project->totalBudget) * 100;
            
            Log::info('Total Budget Used: ' . $totalBudgetUsed);
            Log::info('Total Budget: ' . $project->totalBudget);
            Log::info('Budget Used Percentage: ' . $budgetUsedPercentage);
            Log::info('All Tasks Completed: ' . ($allTasksCompleted ? 'Yes' : 'No'));
            // Check if 95% of the total budget is used
            if ($allTasksCompleted && $budgetUsedPercentage > 95) {
                // Update the project status
                $project->status = 'C';
                $project->save();

                return response()->json(['message' => 'Project status updated successfully'], 200);
            } else {
                return response()->json(['message' => 'Conditions not met for updating project status'], 400);
            }
        } catch (Exception $e) {
            Log::error('Failed to update project status: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update project status', 'error' => $e->getMessage()], 500);
        }
    }

    public function getCompletedAndOngoingProjects()
    {
        // Fetch all projects with status 'completed' or 'ongoing'
        $projects = Project::whereIn('status', ['C', 'OG'])->get();

        // Return the projects as a JSON response
        return response()->json($projects);
    }

    public function getProjectCountByMonth()
    {
        try {
            // Fetch projects and group them by month and company
            $projects = DB::table('projects')
                ->join('companies', 'projects.company_id', '=', 'companies.id')
                ->select(
                    DB::raw('YEAR(projects.created_at) as year'),
                    DB::raw('MONTH(projects.created_at) as month'),
                    DB::raw('COUNT(projects.id) as project_count'),
                    DB::raw('COUNT(DISTINCT companies.id) as company_count')
                )
                ->groupBy('year', 'month')
                ->orderBy('year', 'asc')
                ->orderBy('month', 'asc')
                ->get();
    
            // Format the results
            $formattedProjects = $projects->map(function ($project) {
                $dateObj = DateTime::createFromFormat('!m', $project->month);
                $monthName = strtolower($dateObj->format('M')); // Use 'M' for short month name
                return [
                    'year' => $project->year,
                    'count' => $project->project_count,
                    'month' => $monthName,
                    'company_count' => $project->company_count
                ];
            });
    
            // Return the projects count by month as a JSON response
            return response()->json(['projects_per_month' => $formattedProjects], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch projects count by month: ' . $e->getMessage());
            return response()->json(['error' => 'Failed to fetch projects count by month', 'message' => $e->getMessage()], 500);
        }
    }

  
    public function editTask(Request $request, $taskId)
    {
            $user = Auth::user();

            if (!in_array($user->role, ['admin', 'staff'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'Unauthorized'
                ], 403);
            }

            $validatedData = $request->validate([
                'pt_task_name' => 'nullable|string|max:255',
                'pt_completion_date' => 'nullable|date',
                'pt_starting_date' => 'nullable|date',
                'pt_allocated_budget' => 'nullable|numeric', // allocated budget
            ]);

            // Check if all submitted fields are null
            if (empty($validatedData['pt_task_name']) && empty($validatedData['pt_completion_date']) && empty($validatedData['pt_starting_date']) && empty($validatedData['pt_allocated_budget'])) {
                return response()->json([
                    'status' => false,
                    'message' => 'No fields to update'
                ], 400);
            }

            DB::beginTransaction();

            try {
                $task = Task::findOrFail($taskId);

                $oldValues = $task->only(['pt_task_name', 'pt_completion_date', 'pt_starting_date', 'pt_allocated_budget']);

                // Handle task status based on completion date
                if (!empty($validatedData['pt_completion_date'])) {
                    $completionDate = Carbon::parse($validatedData['pt_completion_date']);
                    $currentDate = Carbon::now();

                    if ($completionDate->isPast()) {
                        $validatedData['pt_status'] = 'D'; // Set status to 'D' if the date has passed
                    } else {
                        $validatedData['pt_status'] = 'OG'; // Set status to 'OG' otherwise
                    }
                }

                // Prepare the update array
                $updateData = array_filter([
                    'pt_task_name' => $validatedData['pt_task_name'],
                    'pt_completion_date' => $validatedData['pt_completion_date'],
                    'pt_starting_date' => $validatedData['pt_starting_date'],
                    'pt_allocated_budget' => $validatedData['pt_allocated_budget'] ?? $task->pt_allocated_budget,
                    'pt_status' => $validatedData['pt_status'] ?? $task->pt_status,
                    'updated_by' => $user->id,
                ]);

                $task->update($updateData);

                // Ensure task_id exists in audit_log_task table before inserting
                if (!DB::table('project_tasks')->where('id', $taskId)->exists()) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Task ID does not exist'
                    ], 400);
                }
                Log::info('Task ID exists in project_tasks table', ['task_id' => $taskId]);
                DB::table('audit_log_task')->insert([
                    'task_id' => $taskId,
                    'editor_id' => Auth::id(),
                    'action' => 'edit',
                    'old_values' => json_encode($oldValues),
                    'new_values' => json_encode($updateData),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::commit();

                return response()->json([
                    'status' => true,
                    'message' => 'Task updated successfully'
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => $e->getMessage()
                ], 500);
            }
        }

    public function editTaskResources(Request $request, $taskId, $resourceId)
    {
        $user = Auth::user();
    
        if (!in_array($user->role, ['admin', 'staff'])) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
    
        $validatedData = $request->validate([
            'resource_name' => 'required|string|max:255',
            'qty' => 'nullable|numeric|min:0',
            'unit_cost' => 'nullable|numeric|min:0',
        ]);
    
        try {
            $task = null; // Declare the variable outside the closure
    
            DB::transaction(function () use ($request, $user, $validatedData, $taskId, $resourceId, &$task) {
                $task = Task::findOrFail($taskId);
    
                $oldResources = $task->resources->map(function ($resource) {
                    return $resource->only(['resource_name', 'qty', 'unit_cost', 'total_cost']);
                });
    
                $resource = $task->resources()->where('id', $resourceId)->firstOrFail();
    
                // Prepare the update array
                $updateData = array_filter([
                    'resource_name' => $validatedData['resource_name'],
                    'qty' => $validatedData['qty'],
                    'unit_cost' => $validatedData['unit_cost'],
                    'total_cost' => isset($validatedData['qty']) && isset($validatedData['unit_cost']) ? $validatedData['qty'] * $validatedData['unit_cost'] : null,
                ], function ($value) {
                    return !is_null($value);
                });
    
                $resource->update($updateData);
    
                // Calculate the sum of total_cost for all resources of the same task
                $totalAllocatedBudget = $task->resources()->sum('total_cost');
    
                // Update pt_allocated_budget in the task table
                $task->update([
                    'pt_allocated_budget' => $totalAllocatedBudget,
                ]);
    
                $newResources = $task->resources->map(function ($resource) {
                    return $resource->only(['resource_name', 'qty', 'unit_cost', 'total_cost']);
                });
    
                AuditLogT::create([
                    'task_id' => $task->id,
                    'editor_id' => $user->id,
                    'action' => 'edit_resources',
                    'old_values' => json_encode(['resources' => $oldResources]),
                    'new_values' => json_encode(['resources' => $newResources]),
                ]);
            });
    
            return response()->json([
                'message' => 'Task resources updated successfully',
                'task' => $task->load('resources')
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }







    public function getProjectHistory($projectId)
    {
        try {
            // Find the project by ID
            $project = Project::findOrFail($projectId);
            Log::info('Project found: ' . $projectId);
    
            // Fetch tasks related to the project where isRemoved is 0
            $tasks = Task::where('project_id', $projectId)
                         ->where('isRemoved', '0')
                         ->pluck('id');
    
            // Fetch images and additional details from the task_update_pictures table
            $projectHistory = DB::table('task_update_pictures')
                ->leftJoin('resources', 'task_update_pictures.task_id', '=', 'resources.task_id')
                ->leftJoin('used_resources', 'resources.id', '=', 'used_resources.resource_id')
                ->leftJoin('staff_profiles', 'task_update_pictures.staff_id', '=', 'staff_profiles.id')
                ->leftJoin('task_estimated_values', function($join) {
                    $join->on('task_update_pictures.task_id', '=', 'task_estimated_values.task_id')
                        ->on('task_update_pictures.created_at', '=', 'task_estimated_values.created_at');
                })
                ->leftJoin('project_tasks', function($join) {
                    $join->on('task_update_pictures.task_id', '=', 'project_tasks.id')
                         ->where('project_tasks.isRemoved', '0');
                })
                ->leftJoin('categories', 'project_tasks.category_id', '=', 'categories.id')
                ->whereIn('task_update_pictures.task_id', $tasks)
                ->get([
                    'task_update_pictures.tup_photo', 
                    'task_update_pictures.created_at', 
                    'task_estimated_values.description', 
                    'task_estimated_values.estimated_resource_value', 
                    'used_resources.resource_qty', 
                    'used_resources.used_resource_name as used_resource_name', 
                    'staff_profiles.first_name', 
                    'staff_profiles.last_name',
                    'project_tasks.pt_task_name as task_name',
                    'categories.category_name as category_name',
                    'project_tasks.pt_starting_date', 
                    'project_tasks.pt_completion_date',
                    'project_tasks.pt_status'
                ]);
    
            // Initialize an array to store images and resources grouped by their upload dates and categories
            $historyByDateAndCategory = [];
    
            // Use a set to track unique images
            $uniqueImages = [];
    
            // Get the project start date
            $projectStartDate = \Carbon\Carbon::parse($project->starting_date);
    
            // Iterate over each record and group images by their upload date and category
            foreach ($projectHistory as $record) {
                $uploadDate = \Carbon\Carbon::parse($record->created_at);
                $dayCount = $uploadDate->diffInDays($projectStartDate) + 1; // Adding 1 to make it 1-based index
                $formattedDate = $uploadDate->format('Y-m-d');
                $categoryName = $record->category_name;
    
                Log::info('Processing record for date: ' . $formattedDate . ' and category: ' . $categoryName);
                Log::info('Description: ' . $record->description);
                Log::info('Used Budget: ' . $record->estimated_resource_value);
                Log::info('Staff Name: ' . $record->first_name . ' ' . $record->last_name);
                Log::info('Task Name: ' . $record->task_name);
    
                if (!isset($historyByDateAndCategory[$formattedDate])) {
                    $historyByDateAndCategory[$formattedDate] = [];
                }
    
                if (!isset($historyByDateAndCategory[$formattedDate][$categoryName])) {
                    $historyByDateAndCategory[$formattedDate][$categoryName] = [
                        'day' => 'Day ' . $dayCount,
                        'uploaded_at' => $formattedDate,
                        'category' => $categoryName,
                        'images' => [],
                        'resources' => [],
                        'description' => $record->description,
                        'used_budget' => $record->estimated_resource_value,
                        'staff_name' => $record->first_name . ' ' . $record->last_name,
                        'task_name' => $record->task_name,
                        'pt_starting_date' => $record->pt_starting_date, 
                        'pt_completion_date' => $record->pt_completion_date,
                        'pt_status' => $record->pt_status
                    ];
                }
    
                // Add image to the set to ensure uniqueness
                if (!in_array($record->tup_photo, $uniqueImages)) {
                    $uniqueImages[] = $record->tup_photo;
                    $historyByDateAndCategory[$formattedDate][$categoryName]['images'][] = $record->tup_photo;
                }
            }
    
            // Fetch resources along with their task categories where project_tasks.isRemoved is 0
            $resources = DB::table('used_resources')
                ->leftJoin('resources', 'used_resources.resource_id', '=', 'resources.id')
                ->leftJoin('project_tasks', function($join) {
                    $join->on('resources.task_id', '=', 'project_tasks.id')
                         ->where('project_tasks.isRemoved', '0');
                })
                ->leftJoin('categories', 'project_tasks.category_id', '=', 'categories.id')
                ->whereIn('resources.task_id', $tasks)
                ->get([
                    'used_resources.resource_qty', 
                    'used_resources.used_resource_name as used_resource_name', 
                    'used_resources.created_at',
                    'categories.category_name as category_name',
                    'project_tasks.pt_starting_date', 
                    'project_tasks.pt_completion_date',
                    'project_tasks.pt_status'
                ]);
    
            // Iterate over each resource and group them by their upload date and category
            foreach ($resources as $resource) {
                $resourceDate = \Carbon\Carbon::parse($resource->created_at);
                $dayCount = $resourceDate->diffInDays($projectStartDate) + 1; // Adding 1 to make it 1-based index
                $formattedDate = $resourceDate->format('Y-m-d');
                $categoryName = $resource->category_name ?: 'Uncategorized'; // Default category for resources without a specific category
    
                if (!isset($historyByDateAndCategory[$formattedDate])) {
                    $historyByDateAndCategory[$formattedDate] = [];
                }
    
                if (!isset($historyByDateAndCategory[$formattedDate][$categoryName])) {
                    $historyByDateAndCategory[$formattedDate][$categoryName] = [
                        'day' => 'Day ' . $dayCount,
                        'uploaded_at' => $formattedDate,
                        'category' => $categoryName,
                        'images' => [],
                        'resources' => [],
                        'description' => null,
                        'used_budget' => null,
                        'staff_name' => null,
                        'task_name' => null,
                        'pt_starting_date' => $resource->pt_starting_date, 
                        'pt_completion_date' => $resource->pt_completion_date, 
                        'pt_status' => $resource->pt_status
                    ];
                }
    
                $resourceKey = $resource->used_resource_name . '-' . $resource->resource_qty;
                if (!array_key_exists($resourceKey, $historyByDateAndCategory[$formattedDate][$categoryName]['resources'])) {
                    $historyByDateAndCategory[$formattedDate][$categoryName]['resources'][$resourceKey] = [
                        'name' => $resource->used_resource_name,
                        'qty' => $resource->resource_qty
                    ];
                }
            }
    
            // Convert the associative array to a numeric array
            $history = [];
            foreach ($historyByDateAndCategory as $date => $categories) {
                foreach ($categories as $category => $data) {
                    $history[] = $data;
                }
            }
    
            // Sort the history array by uploaded_at in descending order
            usort($history, function($a, $b) {
                return strtotime($b['uploaded_at']) - strtotime($a['uploaded_at']);
            });
    
            // Log the final structure of historyByDateAndCategory
            Log::info('Final historyByDateAndCategory structure: ' . json_encode($historyByDateAndCategory));
    
            // Prepare the response data
            $responseData = [
                'history' => $history
            ];
    
            // Return the response with the history grouped by their upload dates and categories
            return response()->json(['data' => $responseData], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching history for project ' . $projectId . ': ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while fetching the history'], 500);
        }
    }
}