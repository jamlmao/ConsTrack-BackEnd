<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CompleteTask extends Mailable
{
    use Queueable, SerializesModels;

    public $task;
    public $companyName;

    /**
     * Create a new message instance.
     *
     * @param $task
     * @param $companyName
     */
    public function __construct($task, $companyName)
    {
        $this->task = $task;
        $this->companyName = $companyName;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->from('your-email@example.com', $this->companyName)
                    ->subject('Task Completed')
                    ->view('emails.complete_task')
                    ->with([
                        'task' => $this->task,
                        'companyName' => $this->companyName,
                    ]);
    }
}