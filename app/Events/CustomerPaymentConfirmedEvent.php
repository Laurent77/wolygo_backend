<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Modules\TripManagement\Entities\TripRequest;

class CustomerPaymentConfirmedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $tripRequest;

    public function __construct(TripRequest $tripRequest)
    {
        $this->tripRequest = $tripRequest;
    }

    /**
     * Driver-side private channel: only the assigned driver can receive this.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("customer-payment-confirmed.{$this->tripRequest->id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return "customer-payment-confirmed.{$this->tripRequest->id}";
    }

    public function broadcastWith(): array
    {
        return [
            'id'   => $this->tripRequest->id,
            'type' => $this->tripRequest->type,
        ];
    }
}
