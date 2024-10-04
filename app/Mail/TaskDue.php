<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TaskDue extends Mailable
{
    use Queueable, SerializesModels;

    public $task;
    public $clientCompanyName;
    /**
     * Create a new message instance.
     *
     * @param $task
     */
    public function __construct($task,$clientCompanyName)
    {
        $this->task = $task;
        $this->clientCompanyName = $clientCompanyName;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('your-email@example.com', $this->clientCompanyName)
                    ->subject('Due Task')
                    ->view('emails.task_due')
                    ->with(['task' => $this->task]);
    }
}