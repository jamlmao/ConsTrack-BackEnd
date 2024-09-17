<?php

namespace App\Http\Controllers;

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
use App\Models\Category;
use DateTime;

class CategoryController extends Controller
{
    public function getCategoriesByProjectId($project_id)
    {
        try {
            // Fetch all categories associated with the given project ID
            $categories = Category::where('project_id', $project_id)->get();

            // Check if categories are found
            if ($categories->isEmpty()) {
                return response()->json(['message' => 'No categories found for this project'], 404);
            }

            // Return the categories
            return response()->json(['categories' => $categories], 200);
        } catch (Exception $e) {
            // Log the error and return a 500 response
            Log::error('Failed to fetch categories: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to fetch categories', 'error' => $e->getMessage()], 500);
        }
    }
}
