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
                'pj_image' => 'required|string',  
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
    
            // Decode the base64 encoded image
            if (!empty($validatedData['pj_image'])) {
                $decodedImage = base64_decode($validatedData['pj_image'], true);
                if ($decodedImage === false) {
                    Log::error('Invalid base64 image');
                    return response()->json(['message' => 'Invalid base64 image'], 400);
                }
    
                $imageName = time() . '.png';
                $isSaved = Storage::disk('public')->put('photos/projects/' . $imageName, $decodedImage);
    
                if (!$isSaved) {
                    Log::error('Failed to save image');
                    return response()->json(['message' => 'Failed to save image'], 500);
                }
    
                $photoPath = asset('storage/photos/projects/' . $imageName);
                $validatedData['pj_image'] = $photoPath; 
            }
      // Decode the base64 encoded image1
            if (!empty($validatedData['pj_image1'])) {
                $decodedImage = base64_decode($validatedData['pj_image1'], true);
                if ($decodedImage === false) {
                    Log::error('Invalid base64 image');
                    return response()->json(['message' => 'Invalid base64 image'], 400);
                }
    
                $imageName = time() . '.png';
                $isSaved = Storage::disk('public')->put('photos/projects/' . $imageName, $decodedImage);
    
                if (!$isSaved) {
                    Log::error('Failed to save image');
                    return response()->json(['message' => 'Failed to save image'], 500);
                }
    
                $photoPath = asset('storage/photos/projects/' . $imageName);
                $validatedData['pj_image1'] = $photoPath; 
            }
      // Decode the base64 encoded image2
            if (!empty($validatedData['pj_image2'])) {
                $decodedImage = base64_decode($validatedData['pj_image2'], true);
                if ($decodedImage === false) {
                    Log::error('Invalid base64 image');
                    return response()->json(['message' => 'Invalid base64 image'], 400);
                }
    
                $imageName = time() . '.png';
                $isSaved = Storage::disk('public')->put('photos/projects/' . $imageName, $decodedImage);
    
                if (!$isSaved) {
                    Log::error('Failed to save image');
                    return response()->json(['message' => 'Failed to save image'], 500);
                }
    
                $photoPath = asset('storage/photos/projects/' . $imageName);
                $validatedData['pj_image2'] = $photoPath; 
            }
    
            // Decode the base64 encoded PDF
            if (!empty($validatedData['pj_pdf'])) {
                $decodedPdf = base64_decode($validatedData['pj_pdf'], true);
                if ($decodedPdf === false) {
                    Log::error('Invalid base64 PDF');
                    return response()->json(['message' => 'Invalid base64 PDF'], 400);
                }
    
                $pdfName = time() . '.pdf';
                $isSaved = Storage::disk('public')->put('pdfs/projects/' . $pdfName, $decodedPdf);
    
                if (!$isSaved) {
                    Log::error('Failed to save PDF');
                    return response()->json(['message' => 'Failed to save PDF'], 500);
                }
    
                $pdfPath = asset('storage/pdfs/projects/' . $pdfName); 
                $validatedData['pj_pdf'] = $pdfPath; 
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
            public function addTask(Request $request, $project_id)
            {
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
                      
                    ]);
                    
                    // Add the project_id to the validated data
                    $validatedData['project_id'] = $project_id;
            
                    // Fetch the project's total budget
                    $project = Project::findOrFail($project_id);
                    $totalBudget = $project->totalBudget;


                    $staffProfile = StaffProfile::findOrFail($project->staff_id);
                    $clientProfile = ClientProfile::findOrFail($project->client_id);
            
                    // Get the user IDs from the profiles
                    $staffUserId = $staffProfile->user_id;
                    $clientUserId = $clientProfile->user_id;


                    // Calculate the total allocated budget of all existing tasks
                    $totalAllocatedBudget = Task::where('project_id', $project_id)->sum('pt_allocated_budget');
            
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
                    $task->pt_task_desc = $validatedData['pt_task_desc'];
            
                    // Add resources to the database
                    foreach ($resources as $resource) {
                        $resource['task_id'] = $task->id;
                    $resource['total_cost'] = $resource['qty'] * $resource['unit_cost'];
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
        
                return response()->json(['message' => 'Task created successfully', 'task' => $task, 'resources'=>$resource], 201);
            } catch (Exception $e) {
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

                return response()->json(['message' => 'Task created successfully', 'task' => $task, 'resources' => $resources], 201);
            } catch (Exception $e) {
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
                ->with('resources:id,task_id,resource_name,qty,unit_cost,total_cost', 'category:id,category_name')
                ->get(['id', 'project_id', 'pt_status', 'pt_task_name', 'pt_updated_at', 'pt_completion_date', 'pt_starting_date', 'pt_photo_task', 'pt_allocated_budget','update_img', 'week1_img', 'week2_img', 'week3_img', 'week4_img','week5_img','update_file', 'created_at', 'updated_at', 'pt_file_task','category_id']);
        
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


        public function getSortedProjectTasks($project_id)
        {
            try {
                // Define the custom order for pt_task_desc
                $customOrder = [
                    'GENERAL REQUIREMENTS',
                    'SITE WORKS',
                    'CONCRETE & MASONRY WORKS',
                    'METAL REINFORCEMENT WORKS',
                    'FORMS & SCAFFOLDINGS',
                    'STEEL FRAMING WORK',
                    'TINSMITHRY WORKS',
                    'PLASTERING WORKS',
                    'PAINTS WORKS',
                    'PLUMBING WORKS',
                    'ELECTRICAL WORKS',
                    'CEILING WORKS',
                    'ARCHITECTURAL'
                ];
        
                // Fetch all tasks related to the given project ID
                $tasks = Task::where('project_id', $project_id)->get();
        
                // Sort the tasks based on the custom order
                $sortedTasks = $tasks->sort(function ($a, $b) use ($customOrder) {
                    $posA = array_search($a->pt_task_desc, $customOrder);
                    $posB = array_search($b->pt_task_desc, $customOrder);
        
                    return $posA - $posB;
                });
        
                // Calculate the total allocated budget per category for tasks with status 'C'
                $totalAllocatedBudgetPerCategory = [];
                $totalBudget = $tasks->where('pt_status', 'C')->sum('pt_allocated_budget');
        
                foreach ($customOrder as $category) {
                    $categoryTasks = $tasks->where('pt_task_desc', $category);
                    $categoryBudget = $categoryTasks->where('pt_status', 'C')->sum('pt_allocated_budget');
                    $totalAllocatedBudgetPerCategory[$category] = [
                        'tasks' => $categoryTasks->values()->all(),
                        'totalAllocatedBudget' => $categoryBudget,
                        'percentage' => $totalBudget > 0 ? ($categoryBudget / $totalBudget) * 100 : 0
                    ];
                }
        
                // Return the sorted tasks and total allocated budget per category in a JSON response
                return response()->json([
                    'tasks' => $sortedTasks->values()->all(),
                    'totalAllocatedBudgetPerCategory' => $totalAllocatedBudgetPerCategory
                ], 200);
            } catch (Exception $e) {
                Log::error('Failed to fetch and sort project tasks: ' . $e->getMessage());
                return response()->json(['message' => 'Failed to fetch and sort project tasks', 'error' => $e->getMessage()], 500);
            }
        }


 
        public function getSortedProjectTasks2($project_id)
        {
            try {
                // Fetch all categories related to the project
                $categories = Category::where('project_id', $project_id)->get()->keyBy('id');
        
                // Fetch all tasks related to the given project ID
                $tasks = Task::where('project_id', $project_id)->get();
        
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
        
                // Define the current period (e.g., today)
                $todayStart = now()->startOfDay();
                $todayEnd = now()->endOfDay();
        
                foreach ($customOrder as $categoryName) {
                    $category = $categories->firstWhere('category_name', $categoryName);
                    $categoryId = $category->id;
                    $categoryTasks = $tasks->where('category_id', $categoryId);
                    $categoryBudget = $categoryTasks->sum('pt_allocated_budget');
        
                    // Fetch the c_allocated_budget from the Category model
                    $categoryAllocatedBudget = $category->c_allocated_budget;
        
                    // Calculate the percentage value of the category based on c_allocated_budget
                    $categoryPercentage = $categoryAllocatedBudget > 0 ? ($categoryBudget / $categoryAllocatedBudget) * 100 : 0;
        
                    // Initialize budget trackers
                    $previousBudget = 0;
                    $thisPeriodBudget = 0;
                    $toDateBudget = 0;
        
                    // Calculate the percentage for each task based on the status and budget
                    $categoryTasksWithPercentage = $categoryTasks->map(function ($task) use ($categoryAllocatedBudget, $totalBudget) {
                        // Fetch resources for the task from the used_resources table and join with resources table
                        $taskResources = DB::table('used_resources')
                            ->join('resources', 'used_resources.resource_id', '=', 'resources.id')
                            ->where('resources.task_id', $task->id)
                            ->get(['resources.id as resource_id', 'resources.resource_name as resource_name', 'resources.total_used_resources', 'used_resources.resource_qty', 'used_resources.created_at']);
        
                        $task->resources = $taskResources->map(function ($resource) {
                            return [
                                'id' => $resource->resource_id,
                                'name' => $resource->resource_name,
                                'total_used_resources' => $resource->total_used_resources,
                                'qty' => $resource->resource_qty,
                                'used_date' => $resource->created_at
                            ];
                        });
        
                        // Calculate the total used resources for the task
                        $totalUsedResources = $task->resources->sum('total_used_resources');
        
                        // Calculate the percentage based on total used resources and update_img
                        if (!empty($task->update_img)) {
                            $task->percentage = 100;
                        } else {
                            $task->percentage = $totalUsedResources > 0 ? ($totalUsedResources / $task->pt_allocated_budget) * 100 : 0;
                        }
        
                        return $task;
                    });
        
                    $totalUsedResources = $categoryTasksWithPercentage->reduce(function ($carry, $task) {
                        return $carry + $task->resources->sum('total_used_resources');
                    }, 0);
        
                    // Check if there are any completed tasks
                    $completedTasks = $categoryTasksWithPercentage->filter(function ($task) {
                        return $task->pt_status === 'C';
                    });
        
                    // Calculate budgets based on task completion dates
                    foreach ($completedTasks as $task) {
                        $completionDate = $task->updated_at;
                        if ($completionDate < $todayStart) {
                            $previousBudget += $task->pt_allocated_budget;
                        } elseif ($completionDate >= $todayStart && $completionDate <= $todayEnd) {
                            $thisPeriodBudget += $task->pt_allocated_budget;
                        }
                        $toDateBudget += $task->pt_allocated_budget;
                    }
        
                    $categoryPercentage = $completedTasks->isEmpty() ? 0 : $completedTasks->sum('pt_allocated_budget') / $categoryAllocatedBudget * 100;
        
                    $totalAllocatedBudgetPerCategory[$categoryName] = [
                        'category_id' => $categoryId,
                        'c_allocated_budget' => $categoryAllocatedBudget,
                        'tasks' => $categoryTasksWithPercentage->values()->all(),
                        'totalAllocatedBudget' => $categoryBudget,
                        'progress' => $categoryPercentage,
                        'totalUsedResources' => $totalUsedResources,
                        'todate' => $toDateBudget,
                        'previous' => $previousBudget,
                        'thisperiod' => $thisPeriodBudget
                    ];
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
        
                // Fetch all tasks related to the given project ID
                $tasks = Task::where('project_id', $project_id)->get(['pt_status', 'pt_allocated_budget', 'category_id', 'pt_completion_date']);
        
                // Initialize the result array
                $totalAllocatedBudgetPerCategory = [];
        
                foreach ($categories as $category) {
                    $categoryTasks = $tasks->where('category_id', $category->id);
                    $categoryBudget = $categoryTasks->where('pt_status', 'C')->sum('pt_allocated_budget');
        
                    // Calculate previous cost, this period cost, and to date cost
                    $previousCost = $categoryTasks->where('pt_completion_date', '<', Carbon::today())->sum('pt_allocated_budget');
                    $thisPeriodCost = $categoryTasks->where('pt_completion_date', '=', Carbon::today())->sum('pt_allocated_budget');
                    $toDateCost = $categoryTasks->where('pt_status', 'C')->sum('pt_allocated_budget');
        
                    $totalAllocatedBudgetPerCategory[$category->category_name] = [
                        'tasks' => $categoryTasks->map(function ($task) {
                            return [
                                'pt_status' => $task->pt_status,
                                'pt_allocated_budget' => $task->pt_allocated_budget
                            ];
                        })->values()->all(),
                        'totalAllocatedBudget' => $categoryBudget,
                        'percentage' => $projectTotalBudget > 0 ? ($categoryBudget / $projectTotalBudget) * 100 : 0,
                        'previousCost' => $previousCost,
                        'thisPeriodCost' => $thisPeriodCost,
                        'toDateCost' => $toDateCost
                    ];
                }
        
                // Return the tasks grouped by category and total allocated budget per category in a JSON response
                return response()->json([
                    'totalAllocatedBudgetPerCategory' => $totalAllocatedBudgetPerCategory
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

  
    




    public function updateTask(Request $request, $taskId)
    {
        $request->validate([
            'placeholder_image' => 'nullable|string',
            'update_img' => 'nullable|string',
            'resources' => 'required|array',
            'resources.*.resource_id' => 'required|integer|exists:resources,id',
            'resources.*.used_qty' => 'required|integer|min:1',
        ]);

        try {
            DB::beginTransaction(); // Start the transaction

            // Find the task by ID
            $task = Task::findOrFail($taskId);
            Log::info('Task found: ' . $taskId);

            // Find the project associated with the task
            $project = Project::findOrFail($task->project_id);
            Log::info('Project found: ' . $project->id);

            // Determine the current week based on the task start date
            $taskStartDate = Carbon::parse($task->pt_starting_date);
            $taskCompletionDate = Carbon::parse($task->pt_completion_date);
            $currentDate = Carbon::now();
            $weekNumber = $currentDate->diffInWeeks($taskStartDate) + 1;
            $taskDuration = $taskCompletionDate->diffInWeeks($taskStartDate) + 1;
            Log::info('Task start date: ' . $taskStartDate);
            Log::info('Task completion date: ' . $taskCompletionDate);
            Log::info('Current date: ' . $currentDate);
            Log::info('Current week number: ' . $weekNumber);
            Log::info('Task duration: ' . $taskDuration);

            // Determine if the task is in its last week
            $isLastWeek = ($weekNumber == $taskDuration);
            Log::info('Is last week: ' . ($isLastWeek ? 'Yes' : 'No'));

            // Function to save image and update the corresponding column
            $saveImage = function($imageData, $column) use ($task) {
                $decodedImage = base64_decode($imageData, true);
                if ($decodedImage === false) {
                    Log::error('Invalid base64 image for ' . $column);
                    return response()->json(['message' => 'Invalid base64 image'], 400);
                }
                $uniqueId = uniqid();

                $imageName = Carbon::now()->format('Ymd_His') . '_' . $uniqueId . '_' . $column . '.webp';
                $isSaved = Storage::disk('public')->put('photos/projects/' . $imageName, $decodedImage);

                if (!$isSaved) {
                    Log::error('Failed to save image for ' . $column);
                    return response()->json(['message' => 'Failed to save image'], 500);
                }

                $photoPath = asset('storage/photos/projects/' . $imageName);
                $task->$column = $photoPath;
                Log::info('Image saved successfully: ' . $photoPath);

                // Return the image name and upload date
                return [
                    'image' => $imageName,
                    'uploaded_at' => Carbon::now()->format('Y-m-d')
                ];
            };

            // Initialize response data
            $responseData = [
                'message' => 'Task updated successfully',
                'used_budget' => $project->total_used_budget,
                'update_img' => null,
                'week1_img' => $task->week1_img,
                'week2_img' => $task->week2_img,
                'week3_img' => $task->week3_img,
                'week4_img' => $task->week4_img,
                'week5_img' => $task->week5_img
            ];

            // Handle the update_img field
            if (!empty($request->update_img)) {
                $imageData = $saveImage($request->update_img, 'update_img');
                $responseData['update_img'] = $imageData;

                // Update task status to 'C' as it's the final image
                $task->pt_status = 'C';

                // Update the used budget only if the task is complete
                if ($project->total_used_budget !== null) {
                    $project->total_used_budget += $task->pt_allocated_budget;
                } else {
                    $project->total_used_budget = $task->pt_allocated_budget;
                }
                Log::info('Updated project used budget: ' . $project->total_used_budget);
            } else if (!empty($request->placeholder_image)) {
                if ($isLastWeek) {
                    $imageData = $saveImage($request->placeholder_image, 'update_img');
                    $responseData['update_img'] = $imageData;

                    // Update task status to 'C' as it's the final image
                    $task->pt_status = 'C';

                    // Update the used budget only if the task is complete
                    if ($project->total_used_budget !== null) {
                        $project->total_used_budget += $task->pt_allocated_budget;
                    } else {
                        $project->total_used_budget = $task->pt_allocated_budget;
                    }
                    Log::info('Updated project used budget: ' . $project->total_used_budget);
                } else {
                    $imgKey = 'week' . $weekNumber . '_img';
                    $imageData = $saveImage($request->placeholder_image, $imgKey);
                    $responseData[$imgKey] = $imageData;
                }
            }

            // Validate the request data for resources
            $validatedData = $request->validate([
                'resources' => 'required|array',
                'resources.*.resource_id' => 'required|integer|exists:resources,id',
                'resources.*.used_qty' => 'required|integer|min:1',
            ]);

            // Fetch resources related to the task
            $resources = Resources::where('task_id', $taskId)->get();

            // Check if resources are found
            if ($resources->isEmpty()) {
                return response()->json(['message' => 'No resources found for this task'], 404);
            }

            // Get the staff_id from the logged-in user
            $user = auth()->user();
            $userId = $user->id;
            Log::info('Logged-in user user_id: ' . $userId);

            // Retrieve the staff_id from the StaffProfile model using the user_id
            $staffProfile = StaffProfile::where('user_id', $userId)->first();
            if (!$staffProfile) {
                return response()->json(['message' => 'Staff profile not found for the logged-in user'], 404);
            }

            $staffId = $staffProfile->id;
            Log::info('Logged-in user staff_id: ' . $staffId);

            // Iterate over the resources and check if the available quantity is sufficient
            foreach ($validatedData['resources'] as $resourceData) {
                $resource = $resources->where('id', $resourceData['resource_id'])->first();
                if ($resource) {
                    // Check if the total used resources plus the new used quantity exceed the available quantity
                    if ($resource->total_used_resources + $resourceData['used_qty'] > $resource->qty) {
                        return response()->json(['message' => 'Insufficient quantity for: ' . $resource->resource_name], 400);
                    }
                } else {
                    return response()->json(['message' => 'Resource ID: ' . ($resourceData['resource_id'] ?? 'Unknown') . ' not found'], 404);
                }
            }

            // Iterate over the resources and update the used quantities
            foreach ($validatedData['resources'] as $resourceData) {
                $resource = $resources->where('id', $resourceData['resource_id'])->first();
                if ($resource) {
                    // Update the total used resources
                    $resource->total_used_resources += $resourceData['used_qty'];
                    $resource->save();

                    // Insert into used_resources table
                    UsedResources::create([
                        'resource_id' => $resource->id,
                        'used_resource_name' => $resource->resource_name,
                        'resource_qty' => $resourceData['used_qty'],
                        'staff_id' => $staffId, // Include staff_id from logged-in user
                    ]);
                }
            }

            $task->save();
            $project->save();

            DB::commit(); // Commit the transaction

            // Notify the client only if the task is successfully updated
            if ($task->pt_status == 'C') {
                $clientProfile = ClientProfile::findOrFail($project->client_id);
                $clientUser = User::findOrFail($clientProfile->user_id);
                $clientEmail = $clientUser->email;
                Log::info('Client email: ' . $clientEmail);

                Mail::to($clientEmail)->send(new CompleteTask($task));
                Log::info('Task status updated to completed for task: ' . $taskId);
            }

            Log::info('Task and project saved successfully');

            return response()->json($responseData, 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback the transaction in case of error
            Log::error('Error updating task ' . $taskId . ': ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while updating the task'], 500);
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



  
    public function refreshTables()
    {
        try {
            // Start the transaction
            DB::beginTransaction();

            // Disable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            // Refresh used_resources table
            DB::table('used_resources')->truncate();
            // Optionally, you can seed the table or perform other operations
            // DB::table('used_resources')->insert([...]);

            // Refresh resources table
            DB::table('resources')->truncate();
            // Optionally, you can seed the table or perform other operations
            // DB::table('resources')->insert([...]);

            // Refresh project_task table
            DB::table('project_tasks')->truncate();
            // Optionally, you can seed the table or perform other operations
            // DB::table('project_task')->insert([...]);

            // Re-enable foreign key checks
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            // Commit the transaction
            DB::commit();

            return response()->json(['message' => 'Tables refreshed successfully'], 200);
        } catch (\Exception $e) {
            // Rollback the transaction if any operation fails
            DB::rollBack();

            // Re-enable foreign key checks in case of an error
            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            // Log the error
            Log::error('Failed to refresh tables: ' . $e->getMessage());

            return response()->json(['message' => 'Failed to refresh tables', 'error' => $e->getMessage()], 500);
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
            'pt_photo_task' => 'nullable|string', // base64 encoded image
        ]);
    
        // Check if all submitted fields are null
        if (empty($validatedData['pt_task_name']) && empty($validatedData['pt_completion_date']) && empty($validatedData['pt_starting_date']) && empty($validatedData['pt_photo_task'])) {
            return response()->json([
                'status' => false,
                'message' => 'No fields to update'
            ], 400);
        }
    
        DB::beginTransaction();
    
        try {
            $task = Task::findOrFail($taskId);
    
            $oldValues = $task->only(['pt_task_name', 'pt_completion_date', 'pt_starting_date', 'pt_photo_task']);
    
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
    
            // Decode the base64 image and save it
            if (!empty($validatedData['pt_photo_task'])) {
                $decodedImage = base64_decode($validatedData['pt_photo_task'], true);
                if ($decodedImage === false) {
                    Log::error('Invalid base64 image');
                    throw new \Exception('Invalid base64 image');
                }
    
                // Ensure the directory exists
                $imageName = time() . '.webp';
                $imagePath = storage_path('app/public/photos/tasks/' . $imageName);
                if (!file_exists(dirname($imagePath))) {
                    mkdir(dirname($imagePath), 0755, true);
                }
    
                // Save the decoded image to a file or storage
                file_put_contents($imagePath, $decodedImage);
    
                $photoPath = asset('storage/photos/tasks/' . $imageName); // Set the photo path
                $validatedData['pt_photo_task'] = $photoPath;
            }
    
            // Prepare the update array
            $updateData = array_filter([
                'pt_task_name' => $validatedData['pt_task_name'],
                'pt_completion_date' => $validatedData['pt_completion_date'],
                'pt_starting_date' => $validatedData['pt_starting_date'],
                'pt_photo_task' => $validatedData['pt_photo_task'] ?? $task->pt_photo_task,
                'pt_status' => $validatedData['pt_status'] ?? $task->pt_status,
                'updated_by' => $user->id,
            ], function ($value) {
                return !is_null($value);
            });
    
            $task->update($updateData);
    
            $newValues = $task->only(['pt_task_name', 'pt_completion_date', 'pt_starting_date', 'pt_photo_task', 'pt_status']);
    
            AuditLogT::create([
                'task_id' => $task->id,
                'editor_id' => $user->id,
                'action' => 'edit',
                'old_values' => json_encode($oldValues),
                'new_values' => json_encode($newValues),
            ]);
    
            DB::commit();
    
            return response()->json([
                'message' => 'Task updated successfully',
                'task' => $task
            ], 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
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
}