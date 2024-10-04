<?php
namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PasswordChange extends Mailable
{
    use Queueable, SerializesModels;

    public $password;
    public $companyName;

    public function __construct($password, $companyName)
    {
        $this->password = $password;
        $this->companyName = $companyName;
    }

    public function build()
    {
        return $this->from('your-email@example.com', $this->companyName)
                    ->subject('Password Change')
                    ->view('emails.password_change')
                    ->with([
                        'password' => $this->password,
                        'companyName' => $this->companyName,
                    ]);
    }
}