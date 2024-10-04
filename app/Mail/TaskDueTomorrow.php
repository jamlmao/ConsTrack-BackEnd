<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TaskDueTomorrow extends Mailable
{
    use Queueable, SerializesModels;

    public $task;
    public $staffCompanyName;
    /**
     * Create a new message instance.
     *
     * @param $task
     */
    public function __construct($task,$staffCompanyName)
    {
        $this->task = $task;
        $this->staffCompanyName = $staffCompanyName;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('your-email@example.com',  $this->staffCompanyName)
                    ->subject('Task Due Tomorrow')
                    ->view('emails.task_due_tomorrow')
                    ->with(['task' => $this->task]);
    }
}