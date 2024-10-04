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


    public function editCategory(Request $request, $categoryId)
    {
        $user = Auth::user();

        if (!in_array($user->role, ['admin', 'staff'])) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized'
            ], 403);
        }
        DB::beginTransaction();
        $validatedData = $request->validate([
            'category_name' => 'required|string|max:255',
            'c_allocated_budget' => 'nullable|numeric|min:0',
        ]);

        try {
            $category = Category::findOrFail($categoryId);

            $category->update([
                'category_name' => $validatedData['category_name'],
                'c_allocated_budget' => $validatedData['c_allocated_budget'],
            ]);
            DB::commit();
            return response()->json([
                'message' => 'Category updated successfully',
                'category' => $category
            ], 200);
        } catch (Exception $e) {
            // Log the error and return a 500 response
            DB::rollBack();
            Log::error('Failed to update category: ' . $e->getMessage());
            return response()->json(['message' => 'Failed to update category', 'error' => $e->getMessage()], 500);
        }
    }

    public function removeCategory(Request $request, $categoryId)
    {
        try {
            // Start a database transaction
            DB::beginTransaction();

            // Get the project ID from the request
            $projectId = $request->input('project_id');
            if (!$projectId) {
                return response()->json([
                    'message' => 'Project ID is required',
                ], 400);
            }

            // Find the category by its ID
            $category = Category::findOrFail($categoryId);

            // Verify that the category belongs to the specified project
            if ($category->project_id != $projectId) {
                return response()->json([
                    'message' => 'Category does not belong to the specified project',
                ], 403);
            }

            // Update the isRemoved column to 1
            $category->isRemoved = 1;
            $category->save();

            // Commit the transaction
            DB::commit();

            // Return a success response
            return response()->json([
                'message' => 'Category removed successfully',
                'category' => $category
            ], 200);
        } catch (Exception $e) {
            // Roll back the transaction and log the error
            DB::rollBack();
            Log::error('Failed to remove category: ' . $e->getMessage());

            // Return a failure response
            return response()->json([
                'message' => 'Failed to remove category',
                'error' => $e->getMessage()
            ], 500);
        }
    }


}
