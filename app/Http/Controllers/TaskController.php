<?php

namespace App\Http\Controllers;
use App\Models\Project;
use App\Models\Task;
use App\Models\ClientProfile;
use App\Models\StaffProfile;
use App\Models\UsedResources;
use App\Models\ProjectLogs;
use App\Models\EstimatedCost;
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
use App\Models\ProjectTasks;

class TaskController extends Controller
{
   


    public function getAllTasksWithResources()
    {
        try {
            // Fetch all tasks
            $tasks = DB::table('project_tasks')
                ->get()
                ->map(function ($task) {
                    // Fetch resources for each task
                    $task->resources = DB::table('resources')
                        ->where('task_id', $task->id)
                        ->get();

                   
                    if (!empty($task->pt_starting_date || $task->pt_completion_date || $task->pt_updated_at )) {
                        $task->pt_starting_date = Carbon::parse($task->pt_starting_date)->format('Y-m-d');
                        $task->pt_completion_date = Carbon::parse($task->pt_completion_date)->format('Y-m-d');
                        $task->pt_updated_at = Carbon::parse($task->pt_updated_at)->format('Y-m-d');
                    }

                    return $task;
                });

            return response()->json([
                'alltasks' => $tasks
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching tasks and resources: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while fetching tasks and resources'], 500);
        }
    }








    public function getTasksByCategoryId($category_id)
    {
        try {
            // Fetch all tasks associated with the given category ID
            $tasks = Task::where('category_id', $category_id)->get();

            // Check if tasks are found
            if ($tasks->isEmpty()) {
                return response()->json(['message' => 'No tasks found for this category'], 404);
            }

            // Format the dates and return the tasks
            $tasks = $tasks->map(function ($task) {
                $task->pt_completion_date = Carbon::parse($task->pt_completion_date)->format('Y-m-d');
                $task->pt_updated_at = Carbon::parse($task->pt_updated_at)->format('Y-m-d');
                return $task;
            });

            return response()->json([
                'tasks' => $tasks
            ], 200);
        } catch (Exception $e) {
            Log::error('Error fetching tasks: ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while fetching tasks'], 500);
        }
    }

   
 

    public function updateTask(Request $request, $taskId)
    {
        $request->validate([
            'placeholder_images' => 'nullable|array',
            'placeholder_images.*' => 'nullable|string',
            'resources' => 'nullable|array',
            'resources.*.resource_id' => 'nullable|integer|exists:resources,id',
            'resources.*.used_qty' => 'nullable|integer|min:1',
        ]);
    
        try {
            DB::beginTransaction(); // Start the transaction    
    
            // Find the task by ID
            $task = Task::findOrFail($taskId);
            Log::info('Task found: ' . $taskId);
    
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
    
            // Function to save image and return the image name, upload date, and path
            $saveImage = function($imageData) {
                $decodedImage = base64_decode($imageData, true);
                if ($decodedImage === false) {
                    Log::error('Invalid base64 image');
                    throw new \Exception('Invalid base64 image');
                }
                $uniqueId = uniqid();
    
                $imageName = Carbon::now()->format('Ymd_His') . '_' . $uniqueId . '.webp';
                $isSaved = Storage::disk('public')->put('photos/projects/' . $imageName, $decodedImage);
    
                if (!$isSaved) {
                    Log::error('Failed to save image');
                    throw new \Exception('Failed to save image');
                }
    
                $photoPath = asset('storage/photos/projects/' . $imageName);
                Log::info('Image saved successfully: ' . $photoPath);
    
                // Return the image name, upload date, and full path
                return [
                    'image' => $imageName,
                    'uploaded_at' => Carbon::now()->format('Y-m-d'),
                    'path' => $photoPath
                ];
            };
    
            // Initialize response data
            $responseData = [
                'message' => 'Task updated successfully',
            ];
    
            // Handle the placeholder_images field
            if (!empty($request->placeholder_images)) {
                $imagesData = [];
                foreach ($request->placeholder_images as $image) {
                    $imageData = $saveImage($image);
                    $imagesData[] = $imageData;
    
                    // Insert into task_update_pictures table
                    $inserted = DB::table('task_update_pictures')->insert([
                        'task_id' => $taskId,
                        'staff_id' => $staffId,
                        'tup_photo' => $imageData['path'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
    
                    if ($inserted) {
                        Log::info('Image data inserted into task_update_pictures table: ' . $imageData['path']);
                    } else {
                        Log::error('Failed to insert image data into task_update_pictures table: ' . $imageData['path']);
                    }
                }
                $responseData['placeholder_images'] = $imagesData;
            }
    
            // Handle the resources field
            if (!empty($request->resources)) {
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
            }
    
            $task->save();
    
            DB::commit(); // Commit the transaction
    
            Log::info('Task saved successfully');
    
            return response()->json($responseData, 200);
        } catch (\Exception $e) {
            DB::rollBack(); // Rollback the transaction in case of error
            Log::error('Error updating task ' . $taskId . ': ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while updating the task'], 500);
        }
    }



    

        public function getTaskImages($taskId)
        {
            try {
                // Find the task by ID
                $task = Task::findOrFail($taskId);
                Log::info('Task found: ' . $taskId);

                // Fetch images and additional details from the task_update_pictures table
                $taskUpdatePictures = DB::table('task_update_pictures')
                    ->leftJoin('resources', 'task_update_pictures.task_id', '=', 'resources.task_id')
                    ->leftJoin('used_resources', 'resources.id', '=', 'used_resources.resource_id')
                    ->leftJoin('staff_profiles', 'task_update_pictures.staff_id', '=', 'staff_profiles.id')
                    ->leftJoin('task_estimated_values', 'task_update_pictures.task_id', '=', 'task_estimated_values.task_id')
                    ->where('task_update_pictures.task_id', $taskId)
                    ->get([
                        'task_update_pictures.tup_photo', 
                        'task_update_pictures.created_at', 
                        'task_estimated_values.description', 
                        'task_estimated_values.estimated_resource_value', // Include estimated_resource_value
                        'used_resources.resource_qty', 
                        'used_resources.used_resource_name as used_resource_name', 
                        'staff_profiles.first_name', 
                        'staff_profiles.last_name'
                    ]);

                // Initialize an array to store images and resources grouped by their upload dates
                $imagesByDate = [];

                // Use a set to track unique images
                $uniqueImages = [];

                // Get the task start date
                $taskStartDate = \Carbon\Carbon::parse($task->created_at);

                // Iterate over each record and group images by their upload date
                foreach ($taskUpdatePictures as $picture) {
                    $uploadDate = \Carbon\Carbon::parse($picture->created_at);
                    $dayCount = $uploadDate->diffInDays($taskStartDate) + 1; // Adding 1 to make it 1-based index
                    $formattedDate = $uploadDate->format('Y-m-d');

                    if (!isset($imagesByDate[$formattedDate])) {
                        $imagesByDate[$formattedDate] = [
                            'day' => 'Day ' . $dayCount,
                            'uploaded_at' => $formattedDate,
                            'images' => [],
                            'resources' => []
                        ];
                    }

                    // Add image to the set to ensure uniqueness
                    if (!in_array($picture->tup_photo, $uniqueImages)) {
                        $uniqueImages[] = $picture->tup_photo;
                        $imagesByDate[$formattedDate]['images'][] = $picture->tup_photo;
                    }
                }

                // Fetch resources that don't have a matching image upload date
                $resources = DB::table('used_resources')
                    ->leftJoin('resources', 'used_resources.resource_id', '=', 'resources.id')
                    ->where('resources.task_id', $taskId)
                    ->get([
                        'used_resources.resource_qty', 
                        'used_resources.used_resource_name as used_resource_name', 
                        'used_resources.created_at'
                    ]);

                // Iterate over each resource and group them by their upload date
                foreach ($resources as $resource) {
                    $resourceDate = \Carbon\Carbon::parse($resource->created_at);
                    $dayCount = $resourceDate->diffInDays($taskStartDate) + 1; // Adding 1 to make it 1-based index
                    $formattedDate = $resourceDate->format('Y-m-d');

                    if (!isset($imagesByDate[$formattedDate])) {
                        $imagesByDate[$formattedDate] = [
                            'day' => 'Day ' . $dayCount,
                            'uploaded_at' => $formattedDate,
                            'images' => [],
                            'resources' => []
                        ];
                    }

                    $resourceKey = $resource->used_resource_name . '-' . $resource->resource_qty;
                    if (!array_key_exists($resourceKey, $imagesByDate[$formattedDate]['resources'])) {
                        $imagesByDate[$formattedDate]['resources'][$resourceKey] = [
                            'name' => $resource->used_resource_name,
                            'qty' => $resource->resource_qty
                        ];
                    }
                }

                // Convert the associative array to a numeric array
                $images = array_values($imagesByDate);

                // Prepare the response data
                $responseData = [
                    'images' => $images,
                    'description' => $taskUpdatePictures->first()->description,
                    'used_budget' => $taskUpdatePictures->first()->estimated_resource_value, // Include estimated_resource_value
                    'staff_name' => $taskUpdatePictures->first()->first_name . ' ' . $taskUpdatePictures->first()->last_name
                ];

                // Return the response with the images grouped by their upload dates
                return response()->json(['data' => $responseData], 200);
            } catch (\Exception $e) {
                Log::error('Error fetching images for task ' . $taskId . ': ' . $e->getMessage());
                return response()->json(['error' => 'An error occurred while fetching the images'], 500);
            }
        }



        

   
        public function CompleteTask(Request $request)
        {
            $taskId = $request->input('task_id');
        
            DB::beginTransaction();
        
            try {
                // Fetch the task to be completed
                $task = Task::find($taskId);
        
                // Check if the task exists
                if (!$task) {
                    return response()->json(['message' => 'Task not found.'], 404);
                }
        
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
        
                // Calculate the task percentage
                $taskPercentage = $totalUsedResources >= $task->pt_allocated_budget ? 100 : ($totalUsedResources / $task->pt_allocated_budget) * 100;
        
                // Validate if the total task percentage is above 95%
                if ($taskPercentage < 95) {
                    return response()->json(['message' => 'Task percentage must be above 95% to complete the task.'], 400);
                }
        
                // Remove the resources attribute before saving the task
                unset($task->resources);
        
                // Update the task status to completed
                $task->pt_status = 'C';
                $task->save();
        
                // Update the total_used_budget in the project table
                $project = Project::find($task->project_id);
                if ($project) {
                    $project->total_used_budget += $task->pt_allocated_budget;
                    $project->save();
                }
        
                DB::commit();
        
                return response()->json(['message' => 'Task completed successfully.'], 200);
            } catch (Exception $e) {
                DB::rollBack();
                Log::error('Failed to complete task: ' . $e->getMessage());
                return response()->json(['message' => 'Failed to complete task', 'error' => $e->getMessage()], 500);
            }
        }


        public function updateTaskv2(Request $request, $taskId)
        {
            $request->validate([
                'description' => 'nullable|string',
                'placeholder_images' => 'nullable|array',
                'placeholder_images.*' => 'nullable|string',
                'resources' => 'nullable|array',
                'resources.*.resource_id' => 'nullable|integer|exists:resources,id',
                'resources.*.used_qty' => 'nullable|integer|min:1',
                'estimated_resource_value' => 'nullable|numeric|min:0', // Add validation for estimated_resource_value as a single input
            ]);

            try {
                DB::beginTransaction(); // Start the transaction    

                // Find the task by ID
                $task = Task::findOrFail($taskId);
                Log::info('Task found: ' . $taskId);

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

                // Function to save image and return the image name, upload date, and path
                $saveImage = function($imageData) {
                    $decodedImage = base64_decode($imageData, true);
                    if ($decodedImage === false) {
                        Log::error('Invalid base64 image');
                        throw new \Exception('Invalid base64 image');
                    }
                    $uniqueId = uniqid();

                    $imageName = Carbon::now()->format('Ymd_His') . '_' . $uniqueId . '.webp';
                    $isSaved = Storage::disk('public')->put('photos/projects/' . $imageName, $decodedImage);

                    if (!$isSaved) {
                        Log::error('Failed to save image');
                        throw new \Exception('Failed to save image');
                    }

                    $photoPath = asset('storage/photos/projects/' . $imageName);
                    Log::info('Image saved successfully: ' . $photoPath);

                    // Return the image name, upload date, and full path
                    return [
                        'image' => $imageName,
                        'uploaded_at' => Carbon::now()->format('Y-m-d'),
                        'path' => $photoPath
                    ];
                };

                // Initialize response data
                $responseData = [
                    'message' => 'Task updated successfully',
                ];

                // Handle the placeholder_images field
                if (!empty($request->placeholder_images)) {
                    $imagesData = [];
                    foreach ($request->placeholder_images as $image) {
                        $imageData = $saveImage($image);
                        $imagesData[] = $imageData;

                        // Insert into task_update_pictures table
                        $inserted = DB::table('task_update_pictures')->insert([
                            'task_id' => $taskId,
                            'staff_id' => $staffId,
                            'tup_photo' => $imageData['path'],
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        if ($inserted) {
                            Log::info('Image data inserted into task_update_pictures table: ' . $imageData['path']);
                        } else {
                            Log::error('Failed to insert image data into task_update_pictures table: ' . $imageData['path']);
                        }
                    }
                    $responseData['placeholder_images'] = $imagesData;
                }

                // Handle the resources field
                if (!empty($request->resources)) {
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
                }

                // Store the estimated_resource_value in the new table
                if ($request->has('estimated_resource_value')) {
                    EstimatedCost::create([
                        'task_id' => $taskId,
                        'estimated_resource_value' => $request->estimated_resource_value,
                        'description' => $request->description,
                    ]);
                    $responseData['estimated_resource_value'] = $request->estimated_resource_value;
                }

                $task->save();

                DB::commit(); // Commit the transaction

                Log::info('Task saved successfully');

                return response()->json($responseData, 200);
            } catch (\Exception $e) {
                DB::rollBack(); // Rollback the transaction in case of error
                Log::error('Error updating task ' . $taskId . ': ' . $e->getMessage());
                return response()->json(['error' => 'An error occurred while updating the task'], 500);
            }
        }


        public function getAllProjectTasks($projectId)
        {
            try {
                // Fetch the project starting date
                $project = Project::findOrFail($projectId);
                $projectStartDate = \Carbon\Carbon::parse($project->starting_date);
        
                // Fetch tasks with only the necessary columns
                $tasks = Task::where('project_id', $projectId)
                    ->select('id', 'pt_task_name', 'pt_starting_date', 'pt_completion_date', 'pt_status', 'category_id')
                    ->with(['taskUpdatePictures' => function ($query) {
                        $query->select('id', 'tup_photo', 'task_id', 'created_at')
                              ->orderBy('created_at', 'desc');
                    }, 'category' => function ($query) {
                        $query->select('id', 'category_name');
                    }])
                    ->get();
        
                // Initialize the response array
                $response = [];
        
                // Process each task
                $tasks->each(function ($task) use (&$response, $projectStartDate) {
                    // Initialize the task entry
                    $taskEntry = [
                        'id' => $task->id,
                        'pt_task_name' => $task->pt_task_name,
                        'pt_starting_date' => $task->pt_starting_date,
                        'pt_completion_date' => $task->pt_completion_date,
                        'pt_status' => $task->pt_status,
                        'category_name' => $task->category->category_name,
                        'images' => []
                    ];
        
                    // Group photos by the day based on the project starting date
                    $task->taskUpdatePictures->each(function ($picture) use (&$taskEntry, $projectStartDate) {
                        $day = $projectStartDate->diffInDays(\Carbon\Carbon::parse($picture->created_at)) + 1;
                        $dayKey = 'Day ' . $day;
                        if (!isset($taskEntry['images'][$dayKey])) {
                            $taskEntry['images'][$dayKey] = [
                                'day' => $dayKey,
                                'uploaded_at' => $picture->created_at->format('Y-m-d'),
                                'images' => []
                            ];
                        }
                        $taskEntry['images'][$dayKey]['images'][] = $picture->tup_photo;
                    });
        
                    // Convert images array to a list of values
                    $taskEntry['images'] = array_values($taskEntry['images']);
        
                    // Add the task entry to the response
                    $response[] = $taskEntry;
                });
        
                return response()->json($response, 200);
            } catch (\Exception $e) {
                Log::error('Error fetching tasks for project ' . $projectId . ': ' . $e->getMessage());
                return response()->json(['error' => 'An error occurred while fetching the tasks'], 500);
            }
        }


}
