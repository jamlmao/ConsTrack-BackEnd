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
    public function getGeneralTasks($projectId)
    {
        try {
            
            $category = 'GENERAL REQUIREMENTS';

           
            $tasks = DB::table('project_tasks')
                ->where('pt_task_desc', $category)
                ->where('project_id', $projectId)
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
                    unset($task->pt_task_desc); 
                    return $task;
                });
              

            return response()->json([
                'category' => $category,
                'tasks' => $tasks
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching tasks for category ' . $category . ': ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while fetching tasks for the category'], 500);
        }
    }

    public function getSiteWorksTasks($projectId)
    {
        try {
            
            $category = 'SITE WORKS';

           
            $tasks = DB::table('project_tasks')
                ->where('pt_task_desc', $category)
                ->where('project_id', $projectId)
                ->get()
                ->map(function ($task) {
                    $task->resources = DB::table('resources')
                    ->where('task_id', $task->id) 
                    ->get();
                    if (!empty($task->pt_starting_date || $task->pt_completion_date || $task->pt_updated_at )) {
                        $task->pt_starting_date = Carbon::parse($task->pt_starting_date)->format('Y-m-d');
                        $task->pt_completion_date = Carbon::parse($task->pt_completion_date)->format('Y-m-d');
                        $task->pt_updated_at = Carbon::parse($task->pt_updated_at)->format('Y-m-d');
                    }
                    unset($task->pt_task_desc); 
                    return $task;
                });

            return response()->json([
                'category' => $category,
                'tasks' => $tasks
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching tasks for category ' . $category . ' and project ID ' . $projectId . ': ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while fetching tasks for the category'], 500);
        }
    }

    public function getConcreteWorksTasks($projectId)
    {
        try {
            
            $category = 'CONCRETE & MASONRY WORKS';

           
            $tasks = DB::table('project_tasks')
                ->where('pt_task_desc', $category)
                ->where('project_id', $projectId)
                ->get()
                ->map(function ($task) {
                    $task->resources = DB::table('resources')
                    ->where('task_id', $task->id) 
                    ->get();
                    if (!empty($task->pt_starting_date || $task->pt_completion_date || $task->pt_updated_at )) {
                        $task->pt_starting_date = Carbon::parse($task->pt_starting_date)->format('Y-m-d');
                        $task->pt_completion_date = Carbon::parse($task->pt_completion_date)->format('Y-m-d');
                        $task->pt_updated_at = Carbon::parse($task->pt_updated_at)->format('Y-m-d');
                    }
                    unset($task->pt_task_desc); 
                    return $task;
                });

            return response()->json([
                'category' => $category,
                'tasks' => $tasks
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching tasks for category ' . $category . ': ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while fetching tasks for the category'], 500);
        }
    }

    public function getMetalWorksTasks($projectId)
    {
        try {
            
            $category = 'METAL REINFORCEMENT WORK';

           
            $tasks = DB::table('project_tasks')
                ->where('pt_task_desc', $category)
                ->where('project_id', $projectId)
                ->get()
                ->map(function ($task) {
                    $task->resources = DB::table('resources')
                    ->where('task_id', $task->id) 
                    ->get();
                    if (!empty($task->pt_starting_date || $task->pt_completion_date || $task->pt_updated_at )) {
                        $task->pt_starting_date = Carbon::parse($task->pt_starting_date)->format('Y-m-d');
                        $task->pt_completion_date = Carbon::parse($task->pt_completion_date)->format('Y-m-d');
                        $task->pt_updated_at = Carbon::parse($task->pt_updated_at)->format('Y-m-d');
                    }
                    unset($task->pt_task_desc); 
                    return $task;
                });

            return response()->json([
                'category' => $category,
                'tasks' => $tasks
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching tasks for category ' . $category . ': ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while fetching tasks for the category'], 500);
        }
    }


    public function getFormsAndScaffoldingWorksTasks($projectId)
    {
        try {
            
            $category = 'FORMS & SCAFFOLDINGS';

           
            $tasks = DB::table('project_tasks')
                ->where('pt_task_desc', $category)
                ->where('project_id', $projectId)
                ->get()
                ->map(function ($task) {
                    $task->resources = DB::table('resources')
                    ->where('task_id', $task->id) 
                    ->get();
                    if (!empty($task->pt_starting_date || $task->pt_completion_date || $task->pt_updated_at )) {
                        $task->pt_starting_date = Carbon::parse($task->pt_starting_date)->format('Y-m-d');
                        $task->pt_completion_date = Carbon::parse($task->pt_completion_date)->format('Y-m-d');
                        $task->pt_updated_at = Carbon::parse($task->pt_updated_at)->format('Y-m-d');
                    }
                    unset($task->pt_task_desc); 
                    return $task;
                });

            return response()->json([
                'category' => $category,
                'tasks' => $tasks
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching tasks for category ' . $category . ': ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while fetching tasks for the category'], 500);
        }
    }



    public function getSteelWorksTasks($projectId)
    {
        try {
            
            $category = 'STEEL FRAMING WORK';

           
            $tasks = DB::table('project_tasks')
                ->where('pt_task_desc', $category)
                ->where('project_id', $projectId)
                ->get()
                ->map(function ($task) {
                    $task->resources = DB::table('resources')
                    ->where('task_id', $task->id) 
                    ->get();
                    if (!empty($task->pt_starting_date || $task->pt_completion_date || $task->pt_updated_at )) {
                        $task->pt_starting_date = Carbon::parse($task->pt_starting_date)->format('Y-m-d');
                        $task->pt_completion_date = Carbon::parse($task->pt_completion_date)->format('Y-m-d');
                        $task->pt_updated_at = Carbon::parse($task->pt_updated_at)->format('Y-m-d');
                    }
                    unset($task->pt_task_desc);
                    return $task;
                });

            return response()->json([
                'category' => $category,
                'tasks' => $tasks
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching tasks for category ' . $category . ': ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while fetching tasks for the category'], 500);
        }
    }



    public function getTinsWorksTasks($projectId)
    {
        try {
            
            $category = 'TINSMITHRY WORKS';

           
            $tasks = DB::table('project_tasks')
                ->where('pt_task_desc', $category)
                ->where('project_id', $projectId)
                ->get()
                ->map(function ($task) {
                    $task->resources = DB::table('resources')
                    ->where('task_id', $task->id) 
                    ->get();
                    if (!empty($task->pt_starting_date || $task->pt_completion_date || $task->pt_updated_at )) {
                        $task->pt_starting_date = Carbon::parse($task->pt_starting_date)->format('Y-m-d');
                        $task->pt_completion_date = Carbon::parse($task->pt_completion_date)->format('Y-m-d');
                        $task->pt_updated_at = Carbon::parse($task->pt_updated_at)->format('Y-m-d');
                    }
                    unset($task->pt_task_desc); 
                    return $task;
                });

            return response()->json([
                'category' => $category,
                'tasks' => $tasks
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching tasks for category ' . $category . ': ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while fetching tasks for the category'], 500);
        }
    }



    public function getPlasterWorksTasks($projectId)
    {
        try {
            
            $category = 'PLASTERING WORKS';

           
            $tasks = DB::table('project_tasks')
                ->where('pt_task_desc', $category)
                ->where('project_id', $projectId)
                ->get()
                ->map(function ($task) {
                    $task->resources = DB::table('resources')
                    ->where('task_id', $task->id) 
                    ->get();
                    if (!empty($task->pt_starting_date || $task->pt_completion_date || $task->pt_updated_at )) {
                        $task->pt_starting_date = Carbon::parse($task->pt_starting_date)->format('Y-m-d');
                        $task->pt_completion_date = Carbon::parse($task->pt_completion_date)->format('Y-m-d');
                        $task->pt_updated_at = Carbon::parse($task->pt_updated_at)->format('Y-m-d');
                    }
                    unset($task->pt_task_desc);
                    return $task;
                });

            return response()->json([
                'category' => $category,
                'tasks' => $tasks
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching tasks for category ' . $category . ': ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while fetching tasks for the category'], 500);
        }
    }



    public function getPaintsWorksTasks($projectId)
    {
        try {
            
            $category = 'PAINTS WORKS';

           
            $tasks = DB::table('project_tasks')
                ->where('pt_task_desc', $category)
                ->where('project_id', $projectId)
                ->get()
                ->map(function ($task) {
                    $task->resources = DB::table('resources')
                    ->where('task_id', $task->id) 
                    ->get();
                    if (!empty($task->pt_starting_date || $task->pt_completion_date || $task->pt_updated_at )) {
                        $task->pt_starting_date = Carbon::parse($task->pt_starting_date)->format('Y-m-d');
                        $task->pt_completion_date = Carbon::parse($task->pt_completion_date)->format('Y-m-d');
                        $task->pt_updated_at = Carbon::parse($task->pt_updated_at)->format('Y-m-d');
                    }
                    unset($task->pt_task_desc); 
                    return $task;
                });

            return response()->json([
                'category' => $category,
                'tasks' => $tasks
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching tasks for category ' . $category . ': ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while fetching tasks for the category'], 500);
        }
    }



    public function getPlumbingWorksTasks($projectId)
    {
        try {
            
            $category = 'PLUMBING WORKS';

           
            $tasks = DB::table('project_tasks')
                ->where('pt_task_desc', $category)
                ->where('project_id', $projectId)
                ->get()
                ->map(function ($task) {
                    $task->resources = DB::table('resources')
                    ->where('task_id', $task->id) 
                    ->get();
                    if (!empty($task->pt_starting_date || $task->pt_completion_date || $task->pt_updated_at )) {
                        $task->pt_starting_date = Carbon::parse($task->pt_starting_date)->format('Y-m-d');
                        $task->pt_completion_date = Carbon::parse($task->pt_completion_date)->format('Y-m-d');
                        $task->pt_updated_at = Carbon::parse($task->pt_updated_at)->format('Y-m-d');
                    }
                    unset($task->pt_task_desc); 
                    return $task;
                });

            return response()->json([
                'category' => $category,
                'tasks' => $tasks
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching tasks for category ' . $category . ': ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while fetching tasks for the category'], 500);
        }
    }



    public function getElectricalWorksTasks($projectId)
    {
        try {
            
            $category = 'ELECTRICAL WORKS';

           
            $tasks = DB::table('project_tasks')
                ->where('pt_task_desc', $category)
                ->where('project_id', $projectId)
                ->get()
                ->map(function ($task) {
                    $task->resources = DB::table('resources')
                    ->where('task_id', $task->id) 
                    ->get();
                    if (!empty($task->pt_starting_date || $task->pt_completion_date || $task->pt_updated_at )) {
                        $task->pt_starting_date = Carbon::parse($task->pt_starting_date)->format('Y-m-d');
                        $task->pt_completion_date = Carbon::parse($task->pt_completion_date)->format('Y-m-d');
                        $task->pt_updated_at = Carbon::parse($task->pt_updated_at)->format('Y-m-d');
                    }
                    unset($task->pt_task_desc); 
                    return $task;
                });

            return response()->json([
                'category' => $category,
                'tasks' => $tasks
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching tasks for category ' . $category . ': ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while fetching tasks for the category'], 500);
        }
    }



    public function getCeilingWorksTasks($projectId)
    {
        try {
            
            $category = 'CEILING WORKS';

           
            $tasks = DB::table('project_tasks')
                ->where('pt_task_desc', $category)
                ->where('project_id', $projectId)
                ->get()
                ->map(function ($task) {
                    $task->resources = DB::table('resources')
                    ->where('task_id', $task->id) 
                    ->get();
                    if (!empty($task->pt_starting_date || $task->pt_completion_date || $task->pt_updated_at )) {
                        $task->pt_starting_date = Carbon::parse($task->pt_starting_date)->format('Y-m-d');
                        $task->pt_completion_date = Carbon::parse($task->pt_completion_date)->format('Y-m-d');
                        $task->pt_updated_at = Carbon::parse($task->pt_updated_at)->format('Y-m-d');
                    }
                    unset($task->pt_task_desc);
                    return $task;
                });

            return response()->json([
                'category' => $category,
                'tasks' => $tasks
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching tasks for category ' . $category . ': ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while fetching tasks for the category'], 500);
        }
    }

    public function getArchiWorksTasks($projectId)
    {
        try {
            
            $category = 'ARCHITECTURAL';

           
            $tasks = DB::table('project_tasks')
                ->where('pt_task_desc', $category)
                ->where('project_id', $projectId)
                ->get()
                ->map(function ($task) {
                    $task->resources = DB::table('resources')
                    ->where('task_id', $task->id) 
                    ->get();
                    if (!empty($task->pt_starting_date || $task->pt_completion_date || $task->pt_updated_at )) {
                        $task->pt_starting_date = Carbon::parse($task->pt_starting_date)->format('Y-m-d');
                        $task->pt_completion_date = Carbon::parse($task->pt_completion_date)->format('Y-m-d');
                        $task->pt_updated_at = Carbon::parse($task->pt_updated_at)->format('Y-m-d');
                    }
                    unset($task->pt_task_desc);
                    return $task;
                });

            return response()->json([
                'category' => $category,
                'tasks' => $tasks
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching tasks for category ' . $category . ': ' . $e->getMessage());
            return response()->json(['error' => 'An error occurred while fetching tasks for the category'], 500);
        }
    }

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




}
