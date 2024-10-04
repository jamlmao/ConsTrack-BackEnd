<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Appointment;

class AppointmentRejected extends Mailable
{
    use Queueable, SerializesModels;

    public $appointment;
    public $availableDates;
    public $company;
    /**
     * Create a new message instance.
     *
     * @return void
     */


     public function __construct($appointment, $availableDates, $companyName, $availableDate)
     {
         $this->appointment = $appointment;
         $this->availableDates = $availableDates;
         $this->companyName = $companyName;
         $this->availableDate = $availableDate;
     }

    public function build()
    {
        return $this->from('your-email@example.com', $this->companyName)
                    ->view('emails.appointmentRejected')
                    ->with([
                        'appointment' => $this->appointment,
                        'availableDates' => $this->availableDates,
                        'companyName' => $this->companyName,
                        'availableDate' => $this->availableDate,
                    ]);
    }
}