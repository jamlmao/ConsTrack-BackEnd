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

    private function getImageUploadDate($fileName)
    {
        // Assuming the file name contains the date in a specific format, e.g., 'image_20231001.jpg'
        Log::info('Processing file name: ' . $fileName);
        // Extract the date part from the file name
        if (preg_match('/(\d{4})(\d{2})(\d{2})/', $fileName, $matches)) {
            // Validate the extracted date parts
            Log::info('Extracted date parts: ' . json_encode($matches));

            $year = $matches[1];
            $month = $matches[2];
            $day = $matches[3];

            // Check if the extracted parts form a valid date
            if (checkdate($month, $day, $year)) {
                // Format the date as YYYY-MM-DD
                return $year . '-' . $month . '-' . $day;
            }
        }

        // If the date is not found in the file name or is invalid, return null
        return null;
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
    
                    // Add the image and its upload date to the response
                    $images[$column] = $task->$column;
                    $images[$column . '_uploaded_at'] = $uploadDate;
                } else {
                    // Add null for the image and upload date if the column is empty
                    $images[$column] = null;
                    $images[$column . '_uploaded_at'] = null;
                }
            }
    
            // Return the response with the images and their upload dates
            return response()->json([
                'images' => $images
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching images for task ' . $taskId . ': ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while fetching the images'], 500);
        }
    }


    
}
