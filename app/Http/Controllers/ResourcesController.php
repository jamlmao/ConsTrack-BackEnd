<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Mail\CompleteTask;
use App\Mail\TaskDue;
use App\Mail\TaskDueTomorrow;
use App\Models\Project;
use App\Models\Task;
use App\Models\ClientProfile;
use App\Models\StaffProfile;
use App\Models\ProjectLogs;
use Illuminate\Support\Facades\Mail;
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
use App\Models\UsedResources;
use DateTime;

class ResourcesController extends Controller
{
    public function getResourcesByTaskId($task_id)
    {
        try {

            $task = Task::find($task_id);

            if (!$task) {
                return response()->json(['message' => 'Task not found'], 404);
            }

            $category = Category::find($task->category_id);
            $category_name = $category->category_name;

            // Fetch all resources associated with the given task ID
            $resources = Resources::where('task_id', $task_id)->get();

            // Check if resources are found
            if ($resources->isEmpty()) {
                return response()->json(['message' => 'No resources found for this task'], 404);
            }

            // Return the resources
            return response()->json(['resources' => $resources, 'tasks'=> $task, 'category_name' => $category_name,], 200);
        } catch (Exception $e) {
            // Log the error and return a 500 response
            Log::error('Failed to fetch resources: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch resources', 'error' => $e->getMessage()], 500);
        }
    }

            public function useResourcesForWeek(Request $request, $task_id)
        {
            try {
                // Validate the incoming request
                $validatedData = $request->validate([
                    'resources' => 'required|array',
                    'resources.*.resource_id' => 'required|exists:resources,id',
                    'resources.*.used_qty' => 'required|integer|min:1',
                ]);
        
                // Fetch the resources associated with the given task ID
                $resources = Resources::where('task_id', $task_id)->get();
        
                // Check if resources are found
                if ($resources->isEmpty()) {
                    return response()->json(['message' => 'No resources found for this task'], 404);
                }
        
                // Iterate over the resources and check if the available quantity is sufficient
                foreach ($validatedData['resources'] as $resourceData) {
                    $resource = $resources->where('id', $resourceData['resource_id'])->first();
                    if ($resource) {
                        // Check if the total used resources plus the new used quantity exceed the available quantity
                        if ($resource->total_used_resources + $resourceData['used_qty'] > $resource->qty) {
                            return response()->json(['message' => 'Insufficient quantity for: ' . $resource->resource_name], 400);
                        }
                    } else {
                        return response()->json(['message' => 'Resource ID: ' . $resourceData['resource_name'] . ' not found'], 404);
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
                            'used_at' => now(),
                        ]);
                    }
                }
        
                // Return a success response
                return response()->json(['message' => 'Resources updated successfully'], 200);
            } catch (Exception $e) {
                // Log the error and return a 500 response
                Log::error('Failed to update resources: ' . $e->getMessage());
                return response()->json(['message' => 'Failed to update resources', 'error' => $e->getMessage()], 500);
            }
    }


}
    

