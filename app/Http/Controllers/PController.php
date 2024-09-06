<?php
namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\ClientProfile;
use App\Models\StaffProfile;
use App\Models\ProjectLogs;
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
                'client_id' => 'required|integer|exists:client_profiles,id',
                'completion_date' => 'required|date',
                'starting_date' => 'required|date',
                'totalBudget' => 'required|integer',
                'pj_image' => 'required|string',  
                'pj_pdf' => 'required|string', 
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
                    'starting_date' => $project->starting_date,
                    'totalBudget' => $project->totalBudget,
                    'created_at' => $project->created_at,
                    'updated_at' => $project->updated_at,
                    'client' => [
                        'first_name' => $project->client->first_name,
                        'last_name' => $project->client->last_name,
                        'phone_number' => $project->client->phone_number,
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
    
            // Count projects under the same company
            $projectCount = Project::where('company_id', $companyId)->count();
    
            return response()->json(['project_count' => $projectCount], 200);
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

            // Initialize counters
            $doneCount = 0;
            $ongoingCount = 0;

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
                'ongoing_count' => $ongoingCount
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
                        'pt_task_desc' => 'required|string', // Should be dropdown
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
        
                return response()->json(['message' => 'Task created successfully', 'task' => $task, 'resources'=>$resource], 201);
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
                ->with('resources:id,task_id,resource_name,qty,unit_cost,total_cost')
                ->get(['id', 'project_id', 'pt_status', 'pt_task_name', 'pt_updated_at', 'pt_completion_date', 'pt_starting_date', 'pt_photo_task', 'pt_allocated_budget', 'pt_task_desc', 'update_img', 'update_file', 'created_at', 'updated_at', 'pt_file_task']);
        
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

                // Fetch the project to get the total budget
                $project = Project::findOrFail($project_id);
                $projectTotalBudget = $project->totalBudget;

                // Fetch all tasks related to the given project ID
                $tasks = Task::where('project_id', $project_id)->get(['pt_status', 'pt_allocated_budget', 'pt_task_desc', 'pt_completion_date']);

                // Initialize the result array
                $totalAllocatedBudgetPerCategory = [];

                foreach ($customOrder as $category) {
                    $categoryTasks = $tasks->where('pt_task_desc', $category);
                    $categoryBudget = $categoryTasks->where('pt_status', 'C')->sum('pt_allocated_budget');

                    // Calculate previous cost, this period cost, and to date cost
                    $previousCost = $categoryTasks->where('pt_completion_date', '<', Carbon::today())->sum('pt_allocated_budget');
                    $thisPeriodCost = $categoryTasks->where('pt_completion_date', '=', Carbon::today())->sum('pt_allocated_budget');
                    $toDateCost = $categoryTasks->where('pt_status', 'C')->sum('pt_allocated_budget');

                    $totalAllocatedBudgetPerCategory[$category] = [
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




   //for generating SOWA // need to make the generateProjectsPdf method in the same controller
    public function downloadProjectsPdf()
    {
        return $this->generateProjectsPdf();
    }



    public function getProjectDetails($project_id)
    {
        try {
            // Fetch project details
          $project = DB::table('projects')
            ->join('staff_profiles', 'projects.staff_id', '=', 'staff_profiles.id')
            ->where('projects.id', $project_id)
            ->select('projects.*', 'staff_profiles.first_name as staff_first_name', 'staff_profiles.last_name as staff_last_name', 'staff_profiles.extension_name', 'staff_profiles.license_number')
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


   


    
}