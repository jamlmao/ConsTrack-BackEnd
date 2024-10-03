<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ClientCreated extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public $user;
    public $clientProfile;
    public $password;
    public $company;

    /**
     * Create a new message instance.
     */
    public function __construct($user, $clientProfile, $password, $company)
    {
        $this->user = $user;
        $this->clientProfile = $clientProfile;
        $this->password = $password;
        $this->company = $company;
    }


    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome! your account has been activated',
        );
    }

    public function build()
    {
        return $this->from('your-email@example.com', 'Constrack')
                    ->subject('Your Account Has Been Created')
                    ->view('emails.client_created')
                    ->with([
                        'user' => $this->user,
                        'clientProfile' => $this->clientProfile,
                        'password' => $this->password,
                        'company' => $this->company,
                    ]);
    }
}
