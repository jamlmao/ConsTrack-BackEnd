<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Models\Appointment;

class AppointmentAccepted extends Mailable
{
    use Queueable, SerializesModels;

    public $appointment;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($appointment, $companyName, $availableDate)
    {
        $this->appointment = $appointment;
        $this->companyName = $companyName;
        $this->availableDate = $availableDate;
    }
    

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('your-email@example.com',  $this->companyName)
                    ->subject('Appointment Accepted')
                    ->view('emails.appointmentAccepted')
                    ->with([
                        'appointment' => $this->appointment,
                        'companyName' => $this->companyName,
                        'availableDate' => $this->availableDate,
                    ]);
    }
}