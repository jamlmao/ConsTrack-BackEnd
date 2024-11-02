<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class StaffCreated extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $staffProfile;
    public $password;
    public $company;

    /**
     * Create a new message instance.
     */
    public function __construct($user, $staffProfile, $password, $company)
    {
        $this->user = $user;
        $this->staffProfile = $staffProfile;
        $this->password = $password;
        $this->company = $company;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome! Your account has been activated',
        );
    }

    public function build()
    {
        return $this->from('your-email@example.com', $this->company->company_name)
                    ->subject('Your Account Has Been Created')
                    ->view('emails.staff_created')
                    ->with([
                        'user' => $this->user,
                        'staffProfile' => $this->staffProfile,
                        'password' => $this->password,
                        'company' => $this->company->company_name,
                    ]);
    }
}