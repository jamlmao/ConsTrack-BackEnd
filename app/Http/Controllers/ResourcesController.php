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
    
            // Calculate the left resources for each resource
            $resourcesWithLeftQty = $resources->map(function ($resource) {
                $resource->left_qty = $resource->qty - $resource->total_used_resources;
                return $resource;
            });
    
            // Fetch the sum of estimated_resource_value from task_estimated_values table
            $estimatedResourceValueSum = DB::table('task_estimated_values')
                ->where('task_id', $task_id)
                ->sum('estimated_resource_value');
            Log::info('Estimated resource value sum: ' . $estimatedResourceValueSum);
            $estimatedResourceValueSum = intval($estimatedResourceValueSum);
            // Calculate the percentage based on the estimated resource value sum
            $task->percentage = $estimatedResourceValueSum >= $task->pt_allocated_budget ? 100 : ($estimatedResourceValueSum / $task->pt_allocated_budget) * 100;
            Log::info('Task percentage: ' . $task->percentage);
    
            // Return the resources along with the task, category name, and estimated resource value sum
            return response()->json([
                'resources' => $resourcesWithLeftQty,
                'task' => $task,
                'category_name' => $category_name,
                'estimated_resource_value_sum' => $estimatedResourceValueSum,
            ], 200);
        } catch (Exception $e) {
            // Log the error and return a 500 response
            Log::error('Failed to fetch resources: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch resources', 'error' => $e->getMessage()], 500);
        }
    }






    public function getUsedResourcesForTask($task_id)
    {
        try {
            // Validate the task_id
            if (!is_numeric($task_id)) {
                return response()->json(['message' => 'Invalid task ID'], 400);
            }
    
            // Fetch the used resources for the given task
            $usedResources = DB::table('used_resources')
                ->join('resources', 'used_resources.resource_id', '=', 'resources.id')
                ->join('staff_profiles', 'used_resources.staff_id', '=', 'staff_profiles.id')
                ->where('resources.task_id', $task_id)
                ->select(
                    'used_resources.id',
                    'resources.resource_name as used_resource_name',
                    'used_resources.resource_qty',
                    'used_resources.created_at',
                    'used_resources.staff_id',
                    'staff_profiles.first_name',
                    'staff_profiles.last_name'
                )
                ->get();
    
            // Check if resources are found
            if ($usedResources->isEmpty()) {
                return response()->json(['message' => 'No resources found for this task'], 404);
            }
    
            // Return the used resources
            return response()->json(['used_resources' => $usedResources], 200);
        } catch (Exception $e) {
            // Log the error and return a 500 response
            Log::error('Failed to fetch used resources: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch used resources', 'error' => $e->getMessage()], 500);
        }
    }
}
    

