<?php
namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectLogs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Exception;

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

            $validatedData['status'] = 'in_progress'; // Set status to in_progress by default

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

    public function addTask(Request $request)
    {
        // Validate the incoming request
        $request->validate([
            'project_id' => 'required|integer',
            'pt_task_name' => 'required|string',
            'pt_completion_date' => 'required|date',
            'pt_starting_date' => 'required|date',
            'pt_photo_task' => 'required|string', // Base64 encoded image
            'pt_used_budget' => 'required|integer',
        ]);

        // Decode the base64 encoded photo, if present
        $decodedImage = base64_decode($request->pt_photo_task, true);
        if ($decodedImage === false) {
            Log::error('Invalid base64 image');
            return response()->json(['message' => 'Invalid base64 image'], 400);
        }

        $imageName = time() . '.png';
        $isSaved = Storage::disk('public')->put('photos/tasks/' . $imageName, $decodedImage);

        if (!$isSaved) {
            Log::error('Failed to save image');
            return response()->json(['message' => 'Failed to save image'], 500);
        }

        $photoPath = asset('storage/photos/tasks/' . $imageName); // Set the photo path

        // Create a new task
        $task = Task::create([
            'project_id' => $request->project_id,
            'pt_task_name' => $request->pt_task_name,
            'pt_completion_date' => $request->pt_completion_date,
            'pt_starting_date' => $request->pt_starting_date,
            'pt_photo_task' => $photoPath, // Set the photo path
            'pt_used_budget' => $request->pt_used_budget,
        ]);

        Log::info('Task created successfully', ['task' => $task]);
        return response()->json($task, 201); // Return the newly created task
    }
}