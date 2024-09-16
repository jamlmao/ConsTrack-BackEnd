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
    /**
     * Create a new message instance.
     *
     * @return void
     */


    public function __construct(Appointment $appointment, $availableDates)
    {
        $this->appointment = $appointment;
        $this->availableDates = $availableDates;
    }

    public function build()
    {
        return $this->from('your-email@example.com', 'Constrack')
                    ->view('emails.appointmentRejected')
                    ->with([
                        'appointment' => $this->appointment,
                        'availableDates' => $this->availableDates
                    ]);
    }
}