<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;           // ✅ PUBLIC Channel import
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JambJobSubmitted implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $job;

    public function __construct($job)
    {
        $this->job = $job;
    }

    public function broadcastOn()
    {
        return new Channel('jobs');  // ✅ PUBLIC CHANNEL - NO 401 ERROR!
    }

    public function broadcastAs()
    {
        return 'jamb-job-submitted';
    }

    public function broadcastWith()
    {
        return ['job' => $this->job];
    }
}
