<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AppointmentRequest extends Mailable
{
    use Queueable, SerializesModels;

    public $clientProfile;
    public $appointment;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($clientProfile, $appointment)
    {
        $this->clientProfile = $clientProfile;
        $this->appointment = $appointment;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('your-email@example.com', 'Constrack')
                    ->subject('New Appointment Request')
                    ->view('emails.appointment_request')
                    ->with([
                        'clientProfile' => $this->clientProfile,
                        'appointment' => $this->appointment,
                    ]);
    }
}