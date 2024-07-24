<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectLogs;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\Task;

class PController extends Controller
{
    public function store(Request $request)
    {
        DB::beginTransaction(); // Start the transaction
        try {
            // Validate the data, excluding staff_id
            $validatedData = $request->validate([
                'site_location' => 'required|string',
                'client_id' => 'required|integer|exists:client_profiles,id',
                'completion_date' => 'required|date',
                'starting_date' => 'required|date',
                'totalBudget' => 'required|integer',
            ]);
    
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
                    return response()->json(['message' => 'Staff profile not found for authenticated user'], 404);
                }
                $validatedData['staff_id'] = $staffProfile->id;
            } else {
                return response()->json(['message' => 'Invalid role for creating a project'], 403);
            }
    
            $validatedData['status'] = 'in_progress'; // Set status to in_progress by default
    
            // Create a new project
            $project = Project::create($validatedData);
    
            ProjectLogs::create([
                'action' => 'create',
                'staff_id' => $validatedData['staff_id'], // Use the determined staff_id
                'project_id' => $project->id,
                'old_values' => null, // No old values on creation
                'new_values' => json_encode($validatedData), // Convert the new values to JSON
            ]);
    
            DB::commit(); // Commit the transaction
            return response()->json($project, 201); // Return the newly created project
        } catch (Exception $e) {
            DB::rollBack(); // Rollback the transaction on error
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
            'pt_photo_task' => 'required|string', // Assuming base64 encoded string
            'pt_used_budget' => 'required|integer',
        ]);

        // Decode the base64 encoded photo, if present
        $decodedImage = base64_decode($request->pt_photo_task, true);
        if ($decodedImage === false) {
            return response()->json(['message' => 'Invalid base64 image'], 400);
        }

        $imageName = time().'.png';
        $isSaved = \Storage::disk('public')->put('photos/tasks/'.$imageName, $decodedImage);

        if (!$isSaved) {
            return response()->json(['message' => 'Failed to save image'], 500);
        }

        $photoPath = asset('storage/photos/tasks/'.$imageName);
        
        $request['pt_status'] = 'in_progress'; // Set status to in_progress by default

        // Create and save the new task
        $task = new Task([
            'project_id' => $request->project_id,
            'pt_status' => $request->pt_status,
            'pt_task_name' => $request->pt_task_name,
            'pt_completion_date' => $request->pt_completion_date,
            'pt_starting_date' => $request->pt_starting_date,
            'pt_photo_task' => $photoPath,
            'pt_used_budget' => $request->pt_used_budget,
            'pt_updated_at' => now(), 
        ]);

        $task->save();

        return response()->json(['message' => 'Task added successfully', 'task' => $task], 201);
        }



}
