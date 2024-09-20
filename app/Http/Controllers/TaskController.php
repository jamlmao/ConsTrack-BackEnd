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

    
    public function getImageUploadDate($fileName)
    {
        try {
            // Extract the Unix timestamp part from the file name (first part before the underscore)
            $timestamp = strtok($fileName, '_');
            Log::info('Extracted timestamp from file name: ' . $timestamp);

            // Convert the Unix timestamp to 'Y-m-d' format
            $uploadDate = Carbon::createFromTimestamp($timestamp)->format('Y-m-d');
            Log::info('Converted upload date: ' . $uploadDate);

            return $uploadDate;
        } catch (\Exception $e) {
            Log::error('Error extracting upload date from file name: ' . $fileName . ' - ' . $e->getMessage());
            return null;
        }
    }

    public function getTaskImages($taskId)
    {
        try {
            // Find the task by ID
            $task = Task::findOrFail($taskId);
            Log::info('Task found: ' . $taskId);

            // Initialize an array to store images and their upload dates
            $images = [];

            // List of image columns
            $imageColumns = [
                'update_img',
                'week1_img',
                'week2_img',
                'week3_img',
                'week4_img',
                'week5_img'
            ];

            // Iterate over each image column
            foreach ($imageColumns as $column) {
                if (!empty($task->$column)) {
                    // Extract the file name from the URL
                    $fileName = basename($task->$column);

                    // Extract the upload date from the file name
                    $uploadDate = $this->getImageUploadDate($fileName);

                    // Add the image and its upload date to the array
                    $images[] = [
                        'image' => $task->$column,
                        'uploaded_at' => $uploadDate
                    ];
                }
            }

            // Return the response with the images and their upload dates
            return response()->json([
                'message' => 'Images fetched successfully',
                'images' => $images
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching images for task ' . $taskId . ': ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while fetching the images'], 500);
        }
    }

}
