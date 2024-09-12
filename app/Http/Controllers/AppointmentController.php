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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    public function setAppointment(Request $request)
    {
        try {
            // Validate the request
            $validatedData = $request->validate([
                'staff_id' => 'required|exists:staff_profiles,id',
                'description' => 'required|string|max:255',
                'appointment_datetime' => 'required',
            ]);

            $validatedData['status'] = 'P';

            // Get the logged-in client's ID
            $userId = Auth::id();

            // Debugging: Log the validated data and client ID
            Log::info('Validated Data: ', $validatedData);
          

            $clientProfile = ClientProfile::where('user_id', $userId)->first();
         
            $clientId = $clientProfile->id;

         
            Log::info('Client ID: ', ['client_id' => $clientId]);


            // Create the appointment
            $appointment = Appointment::create([
                'staff_id' => $validatedData['staff_id'],
                'client_id' => $clientId,
                'description' => $validatedData['description'],
                'appointment_datetime' => $validatedData['appointment_datetime'],
                'status' => $validatedData['status'],
            ]);

            // Debugging: Log the created appointment
            Log::info('Created Appointment: ', $appointment->toArray());

            // Send email to staff
            $staffProfile = StaffProfile::find($validatedData['staff_id']);
            $staffEmail = $staffProfile->user->email;
            Mail::to($staffEmail)->send(new AppointmentRequest($clientProfile, $appointment));

            return response()->json(['message' => 'Appointment request sent successfully.'], 200);
        } catch (\Exception $e) {
            // Log the exception
            Log::error('Error creating appointment: ', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Failed to create appointment.'], 500);
        }
    }
}
