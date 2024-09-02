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

class PController extends Controller
{
    public function addproject(Request $request)
    {
        DB::beginTransaction(); // Start the transaction
        DB::enableQueryLog();
        try {
            // Validate the data, excluding staff_id
            $validatedData = $request->validate([
                'site_location' => 'required|string',
                'client_id' => 'required|integer|exists:client_profiles,id',
                'completion_date' => 'required|date',
                'starting_date' => 'required|date',
                'totalBudget' => 'required|integer',
                'pj_image' => 'nullable|string',  // Base64 encoded image
                'pj_pdf' => 'nullable|string', // Base64 encoded PDF
            ]);

            $validatedData['status'] = 'IP'; // Set status to in_progress by default

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

                $photoPath = asset('storage/photos/projects/' . $imageName); // Set the photo path
                $validatedData['pj_image'] = $photoPath; // Set the photo path
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

                $pdfPath = asset('storage/pdfs/projects/' . $pdfName); // Set the PDF path
                $validatedData['pj_pdf'] = $pdfPath; // Set the PDF path
            }

            // Retrieve the role of the authenticated user from the 'role' column
            $user = Auth::user();
            $userRole = $user->role; // Adjust this if your column name is different

            if ($userRole == 'admin') {
                // For admin, use the user's id directly
                $validatedData['staff_id'] = $user->id;
            } else if ($userRole == 'staff') {
                // For staff, use the staff profile id
                $staffProfile = $user->staffProfile;
                if (!$staffProfile) {
                    Log::error('Staff profile not found for authenticated user');
                    return response()->json(['message' => 'Staff profile not found for authenticated user'], 404);
                }
                $validatedData['staff_id'] = $staffProfile->id;
            } else {
                Log::error('Invalid role for creating a project');
                return response()->json(['message' => 'Invalid role for creating a project'], 403);
            }

            // Create a new project
            $project = Project::create($validatedData);

            ProjectLogs::create([
                'action' => 'create',
                'staff_id' => $validatedData['staff_id'], // Use the determined staff_id
                'project_id' => $project->id,
                'old_values' => null, // No old values on creation
                'new_values' => json_encode($validatedData), // Convert the new values to JSON
            ]);

            Log::info(DB::getQueryLog());
            DB::commit(); // Commit the transaction
            Log::info('Project created successfully', ['project' => $project]);
            return response()->json($project, 201); // Return the newly created project
        } catch (Exception $e) {
            DB::rollBack(); // Rollback the transaction on error
            Log::error('Failed to add project: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to create project', 'error' => $e->getMessage()], 500);
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
    
            // Get the company name from the staff profile
            $companyName = $staffProfile->company_name;
    
            // Fetch projects for all staff members under the same company
            $projects = Project::whereHas('staffProfile', function ($query) use ($companyName) {
                    $query->where('company_name', $companyName);
                })
                ->with('client:id,first_name,last_name,phone_number') // Eager load the client relationship
                ->get();
    
            $projectsWithClientDetails = $projects->map(function ($project) {
                return [
                    'id' => $project->id,
                    'site_location' => $project->site_location,
                    'client_id' => $project->client_id,
                    'staff_id' => $project->staff_id,
                    'status' => $project->status,
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
            Log::error('Failed to fetch projects: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch projects'], 500);
        }
    }

    //fetch projects counts for the company

    public function getProjectsCounts($staffId) 
    {
        try {
            // Fetch the staff's company name
            $staff = StaffProfile::find($staffId);
            if (!$staff) {
                return response()->json(['message' => 'Staff not found'], 404);
            }
            $companyName = $staff->company_name;

            // Get all staff IDs under the same company
            $staffIds = StaffProfile::where('company_name', $companyName)->pluck('id');

            // Count projects created by any staff under the same company
            $projectCount = Project::whereIn('staff_id', $staffIds)->count();

            return response()->json(['project_count' => $projectCount], 200);
        } catch (Exception $e) {
            Log::error('Failed to count projects: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to count projects'], 500);
        }
    }

 
    //get project and client details
    public function getProjectAndClientDetails($projectId)
    {
        // Get the logged-in user's ID
        $userId = Auth::id();
    
        // Fetch the company_name of the logged-in user
        $companyName = DB::table('staff_profiles')
            ->where('user_id', $userId)
            ->value('company_name');
    
        // Fetch the project and client details for the specified project ID and company name
        $project = DB::table('projects')
            ->join('client_profiles', 'projects.client_id', '=', 'client_profiles.id')
            ->join('staff_profiles', 'projects.staff_id', '=', 'staff_profiles.id')
            ->where('staff_profiles.company_name', $companyName) // Ensure the project belongs to the same company
            ->where('projects.id', $projectId)
            ->select(
                'projects.id as project_id',
                'projects.site_location',
                'projects.starting_date as project_starting_date',
                'projects.completion_date as project_completion_date',
                'client_profiles.first_name as first_name',
                'client_profiles.last_name as last_name',
                'client_profiles.address as address',
                'client_profiles.city as city'
            )
            ->first();
    
        if ($project) {
            // Return the project and client details as JSON
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
            // Validate the incoming request
            $validatedData = $request->validate([
                'pt_task_name' => 'required|string',
                'pt_completion_date' => 'required|date',
                'pt_starting_date' => 'required|date',
                'pt_photo_task' => 'nullable|string', // Base64 encoded image
                'pt_file_task' => 'nullable|string',
                'pt_allocated_budget' => 'required|integer',
                'pt_task_desc' => 'required|string', // Should be dropdown
            ]);
    
            // Add the project_id to the validated data
            $validatedData['project_id'] = $project_id;
    
            // Check if the completion date has passed
            $completionDate = Carbon::parse($validatedData['pt_completion_date']);
            $currentDate = Carbon::now();
    
            if ($completionDate->isPast()) {
                $validatedData['pt_status'] = 'D'; // Set status to 'D' if the date has passed
            } else {
                $validatedData['pt_status'] = 'IP'; // Set status to 'IP' otherwise
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
                $validatedData['pt_photo_task'] = $photoPath; // Set the photo path
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
                $validatedData['pt_file_task'] = $pdfPath; // Set the PDF path
            }

    
            // Create a new task with the validated data
            $task = Task::create($validatedData);
            $task->photo_path = $validatedData['pt_photo_task'] ?? null;
            $task->pdf_path = $validatedData['pt_file_task'] ?? null;
            $task->pt_task_desc = $validatedData['pt_task_desc'];
        
            return response()->json(['message' => 'Task created successfully', 'task' => $task], 201);
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
            $tasks = Task::where('project_id', $project_id)->get(['id', 'project_id', 'pt_status', 'pt_task_name', 'pt_updated_at', 'pt_completion_date', 'pt_starting_date', 'pt_photo_task', 'pt_allocated_budget', 'pt_task_desc', 'update_img', 'update_file', 'created_at', 'updated_at', 'pt_file_task']);

            // Return the tasks in a JSON response
            return response()->json(['tasks' => $tasks], 200);
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

            // Return the sorted tasks in a JSON response
            return response()->json(['tasks' => $sortedTasks->values()->all()], 200);
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

    public function getProjectsPerMonth() // fetch projects per month
    {
        try {
            // Get the logged-in staff profile
            $staffProfile = StaffProfile::where('user_id', Auth::id())->first();
    
            if (!$staffProfile) {
                Log::error('Staff profile not found for user_id: ' . Auth::id());
                return response()->json(['error' => 'Staff profile not found'], 404);
            }
    
            // Get the company name from the staff profile
            $companyName = $staffProfile->company_name;
            Log::info('Fetching projects for company: ' . $companyName);
    
            // Fetch projects under the same company and group them by month
            $projects = DB::table('projects')
                ->join('client_profiles', 'projects.client_id', '=', 'client_profiles.id')
                ->where('client_profiles.company_name', $companyName)
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
    
            Log::info('Projects fetched successfully for company: ' . $companyName);
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






}