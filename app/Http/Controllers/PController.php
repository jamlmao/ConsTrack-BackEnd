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
                $imageName = time() . '.jpg';
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

    


    //fetchproject tasks
    public function getProjectTasks($project_id)
    {
        try {
            // Fetch all tasks related to the given project ID
            $tasks = Task::where('project_id', $project_id)->get();

            // Return the tasks in a JSON response
            return response()->json(['tasks' => $tasks], 200);
        } catch (Exception $e) {
            Log::error('Failed to fetch project tasks: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch project tasks', 'error' => $e->getMessage()], 500);
        }
    }



   //for generating SOWA
    public function downloadProjectsPdf()
    {
        return $this->generateProjectsPdf();
    }






}