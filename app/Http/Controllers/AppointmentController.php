<?php

namespace App\Http\Controllers;
use App\Mail\AppointmentRequest;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use App\Models\Appointment;
use App\Models\StaffProfile;
use App\Models\ClientProfile;
use App\Models\User;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon; 
use App\Mail\AppointmentAccepted;
use App\Mail\AppointmentRejected;
use App\Models\AvailableDate;

class AppointmentController extends Controller
{
    public function setAppointment(Request $request)
    {
        // Log the incoming request data
        Log::info('Incoming Request Data: ', $request->all());
    
        DB::beginTransaction();
    
        try {
            // Validate the request
            $validatedData = $request->validate([
                'staff_id' => 'required|exists:staff_profiles,id',
                'description' => 'required|string|max:255',
                'appointment_datetime' => 'required|date_format:Y-m-d H:i:s|nullable',
            ]);
    
            $validatedData['status'] = 'P';
    
            // Get the logged-in client's ID
            $userId = Auth::id();
    
            // Debugging: Log the validated data and client ID
            Log::info('Validated Data: ', $validatedData);
    
            $clientProfile = ClientProfile::where('user_id', $userId)->first();
    
            if (!$clientProfile) {
                return response()->json(['message' => 'Client profile not found.'], 404);
            }
    
            $clientId = $clientProfile->id;
            $companyId = $clientProfile->company_id;
    
            Log::info('Client ID: ', ['client_id' => $clientId]);
    
            // Fetch the company name
            $company = Company::find($companyId);
            if (!$company) {
                return response()->json(['message' => 'Company not found.'], 404);
            }
            $companyName = $company->company_name;
    
            // Extract the date from the appointment_datetime
            $appointmentDate = Carbon::parse($validatedData['appointment_datetime'])->format('Y-m-d');
    
            // Check if the selected date is available for the staff member
            $isAvailable = AvailableDate::where('staff_id', $validatedData['staff_id'])
                ->whereDate('available_date', $appointmentDate)
                ->exists();
    
            if (!$isAvailable) {
                return response()->json(['message' => 'The selected date is not available for the staff member.'], 400);
            }
    
            // Check for appointment conflicts within the same company and date
            $conflict = Appointment::where('staff_id', $validatedData['staff_id'])
                ->whereDate('appointment_datetime', $appointmentDate)
                ->whereHas('client', function ($query) use ($companyId) {
                    $query->where('company_id', $companyId);
                })
                ->where('status', '!=', 'R')
                ->exists();
    
            if ($conflict) {
                return response()->json(['message' => 'The appointment date conflicts with another appointment in the same company.'], 400);
            }
    
            // Handle nullable time
            $appointmentDatetime = Carbon::parse($validatedData['appointment_datetime']);
            if (!$appointmentDatetime->hour && !$appointmentDatetime->minute && !$appointmentDatetime->second) {
                $appointmentDatetime->setTime(0, 0, 0); // Default to 00:00:00 if time is not provided
            }
    
            // Create the appointment
            $appointment = Appointment::create([
                'staff_id' => $validatedData['staff_id'],
                'client_id' => $clientId,
                'description' => $validatedData['description'],
                'appointment_datetime' => $appointmentDatetime,
                'status' => $validatedData['status'],
            ]);
    
            // Debugging: Log the created appointment
            Log::info('Created Appointment: ', $appointment->toArray());
    
            // Send email to staff
            $staffProfile = StaffProfile::find($validatedData['staff_id']);
            $staffEmail = $staffProfile->user->email;
            Mail::to($staffEmail)->send(new AppointmentRequest($clientProfile, $appointment, $companyName));
    
            DB::commit();
    
            return response()->json(['message' => 'Appointment request sent successfully.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error creating appointment: ', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to create appointment.'], 500);
        }
    }



    
    public function getStaffAppointments()
    {
        try {
            // Get the logged-in user
            $user = Auth::user();

            // Check if the user has a staff profile
            if (!$user->staffProfile) {
                return response()->json([
                    'status' => false,
                    'message' => 'User does not have an associated staff profile.'
                ], 400);
            }

            // Get the staff ID from the staff profile
            $staffId = $user->staffProfile->id;

            // Fetch the appointments for the logged-in staff member
            $appointments = Appointment::where('staff_id', $staffId)
                ->with(['client.project' => function($query) {
                    $query->select('id', 'client_id', 'site_city', 'site_address', 'site_province');
                }, 'client' => function($query) {
                    $query->select('id', 'first_name', 'last_name', 'company_id','phone_number');
                }])
                ->get(['id', 'client_id', 'description', 'appointment_datetime', 'status']);


                Log::info('Fetched appointments: ', ['appointments' => $appointments]);
            // Map the appointments to include project location
            $appointments = $appointments->map(function ($appointment) {
                $project = optional($appointment->client->project);
                Log::info('Appointment project details: ', [
                    'appointment_id' => $appointment->id,
                    'client_id' => $appointment->client_id,
                    'project' => $project
                ]);
    
                return [
                    'id' => $appointment->id,
                    'client_id' => $appointment->client_id,
                    'client_first_name' => $appointment->client->first_name,
                    'client_last_name' => $appointment->client->last_name,
                    'client_phone_number' => $appointment->client->phone_number,
                    'company_id' => $appointment->client->company_id,
                    'site_city' => $project->site_city,
                    'site_address' => $project->site_address,
                    'site_province' => $project->site_province,
                    'description' => $appointment->description,
                    'appointment_datetime' => $appointment->appointment_datetime,
                    'status' => $appointment->status,
                ];
            });

            return response()->json([
                'appointments' => $appointments
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }





    public function updateStatus(Request $request, $id)
    {
        Log::info('updateStatus called with ID: ' . $id);
    
        try {
            // Start the transaction
            DB::beginTransaction();
    
            // Validate the request
            $request->validate([
                'status' => 'required|string|in:A,R'
            ]);
    
            Log::info('Request validated');
    
            // Find the appointment by ID
            $appointment = Appointment::findOrFail($id);
    
            Log::info('Appointment found: ' . $appointment->id);
    
            // Update the status
            $appointment->status = $request->input('status');
            $appointment->save();
    
            Log::info('Appointment status updated to: ' . $appointment->status);
    
            // Fetch the client and user email
            $client = $appointment->client;
            if (!$client) {
                Log::error('Client not found for appointment ID: ' . $appointment->id);
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Client not found.'
                ], 500);
            }
    
            $user = $client->user;
            if (!$user || !$user->email) {
                Log::error('User email not found for client ID: ' . $client->id);
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'User email not found.'
                ], 500);
            }
    
            Log::info('User email: ' . $user->email);
    
            // Fetch the company name from the companies table using the company_id
            $company = Company::find($client->company_id);
            if (!$company) {
                Log::error('Company not found for company ID: ' . $client->company_id);
                DB::rollBack();
                return response()->json([
                    'status' => false,
                    'message' => 'Company not found.'
                ], 500);
            }
            $companyName = $company->company_name; // Assuming the company name column is 'company_name'
    
            // Log the company name
            Log::info('Company name: ' . $companyName);
    
            // Get the available date using your existing function
            $availableDate = $this->getAvailableDates();
    
            // Send email based on status
            if ($appointment->status == 'A') {
                Mail::to($user->email)->send(new AppointmentAccepted($appointment, $companyName, $availableDate));
                Log::info('AppointmentAccepted email sent to: ' . $user->email);
            } elseif ($appointment->status == 'R') {
                // Get available dates excluding Saturdays and Sundays
                $availableDates = $this->getAvailableDates();
    
                // Ensure $availableDates is an array
                if (!is_array($availableDates)) {
                    $availableDates = [$availableDates];
                }
    
                // Send rejection email with available dates
                Mail::to($user->email)->send(new AppointmentRejected($appointment, $availableDates, $companyName, $availableDate));
                Log::info('AppointmentRejected email sent to: ' . $user->email);
            }
    
            // Commit the transaction
            DB::commit();
    
            return response()->json([
                'status' => true,
                'message' => 'Appointment status updated successfully.',
                'appointment' => $appointment
            ], 200);
        } catch (\Throwable $th) {
            // Rollback the transaction in case of error
            DB::rollBack();
            Log::error('Error updating appointment status: ' . $th->getMessage());
            return response()->json([
                'status' => false,
                'message' => 'Error updating appointment status.'
            ], 500);
        }
    }
    
    public function getAvailableDates()
    {
        $user = auth()->user();
        $companyId = $user->staffProfile->company_id;
    
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
    
        // Fetch only the available_date values
        $availableDates = AvailableDate::whereHas('staff', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })
        ->whereMonth('available_date', $currentMonth)
        ->whereYear('available_date', $currentYear)
        ->pluck('available_date'); // Use pluck to get only the available_date values
    
        return $availableDates->toArray(); // Convert to array
    }



    
    private function isDateTaken($date)
    {
        return Appointment::whereDate('appointment_datetime', $date)->exists();
    }



    public function getNotifications(Request $request)
    {
        try {
            // Define the query to fetch appointments with status 'P' and made today
            $appointments = Appointment::where('appointments.status', 'P')
                ->whereDate('appointments.created_at', Carbon::today())
                ->join('client_profiles', 'appointments.client_id', '=', 'client_profiles.id')
                ->select('appointments.*', 'client_profiles.first_name', 'client_profiles.last_name')
                ->get();
    
           
            $clientCount = Appointment::where('appointments.status', 'P')
                ->whereDate('appointments.created_at', Carbon::today())
                ->distinct('client_id')
                ->count('client_id');
    
          
            $responseData = [
                'appointments' => $appointments,
                'client_count' => $clientCount
            ];
    
            return response()->json($responseData, 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }



    public function insertAvailableDates(Request $request)
    {
        $user = auth()->user();
        $staffId = $user->staffProfile->id;
        $dates = $request->input('dates'); 
    
        $currentDay = Carbon::now()->day;
        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;
    
        foreach ($dates as $day) {
            // Check if the day is in the past
            if ($day < $currentDay) {
                $availableDate = Carbon::create($currentYear, $currentMonth + 1, $day);
            } else {
                $availableDate = Carbon::create($currentYear, $currentMonth, $day);
            }
    
            AvailableDate::create([
                'staff_id' => $staffId,
                'available_date' => $availableDate,
            ]);
        }
    
        return response()->json(['message' => 'Available dates inserted successfully']);
    }




    public function getAvailableDates2()
    {
        $user = auth()->user();
        $companyId = $user->staffProfile->company_id;

        $currentMonth = Carbon::now()->month;
        $currentYear = Carbon::now()->year;

        $availableDates = AvailableDate::whereHas('staff', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })
        ->whereMonth('available_date', $currentMonth)
        ->whereYear('available_date', $currentYear)
        ->get()
        ->map(function ($availableDate) {
            $appointments = Appointment::select('appointments.id', 'appointments.staff_id', 'appointments.appointment_datetime', 'appointments.status', 'client_profiles.first_name', 'client_profiles.last_name', 'client_profiles.phone_number')
                ->join('client_profiles', 'appointments.client_id', '=', 'client_profiles.id')
                ->whereDate('appointment_datetime', $availableDate->available_date)
                ->where('appointments.staff_id', $availableDate->staff_id)
                ->get();

            $availableDate->appointments = $appointments;
            return $availableDate;
        });

        return response()->json(['available_dates' => $availableDates]);
    }
     


    public function getAvailableDates3() /// for gettin all available dates even other months 
    {
        $user = auth()->user();
        $companyId = $user->staffProfile->company_id;
    
        $availableDates = AvailableDate::whereHas('staff', function ($query) use ($companyId) {
            $query->where('company_id', $companyId);
        })
        ->get()
        ->map(function ($availableDate) {
            $appointments = Appointment::select('appointments.id', 'appointments.staff_id', 'appointments.appointment_datetime', 'appointments.status', 'client_profiles.first_name', 'client_profiles.last_name', 'client_profiles.phone_number')
                ->join('client_profiles', 'appointments.client_id', '=', 'client_profiles.id')
                ->whereDate('appointment_datetime', $availableDate->available_date)
                ->where('appointments.staff_id', $availableDate->staff_id)
                ->get();
    
            $availableDate->appointments = $appointments;
            return $availableDate;
        });
    
        return response()->json(['available_dates' => $availableDates]);
    }


}


