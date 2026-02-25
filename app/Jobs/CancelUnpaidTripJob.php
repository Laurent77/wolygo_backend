<?php

namespace App\Jobs;

use App\Events\CustomerTripCancelledEvent;
use App\Events\DriverTripCancelledEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\TripManagement\Entities\TripRequest;

class CancelUnpaidTripJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected string $tripId)
    {
    }

    public function handle(): void
    {
        $trip = TripRequest::with(['customer', 'driver'])->find($this->tripId);

        // Abort if trip no longer exists, is already paid, or left the accepted state
        if (!$trip || $trip->payment_status === PAID || $trip->current_status !== ACCEPTED) {
            return;
        }

        // Cancel the trip
        $trip->current_status = CANCELLED;
        $trip->cancelled_by   = 'payment_timeout';
        $trip->save();

        // Notify rider (DriverTripCancelledEvent → private-driver-trip-cancelled.{tripId})
        try {
            broadcast(new DriverTripCancelledEvent($trip))->toOthers();
        } catch (\Exception $e) {}

        // Notify driver (CustomerTripCancelledEvent → private-customer-trip-cancelled.{tripId}.{driverId})
        if ($trip->driver) {
            try {
                broadcast(new CustomerTripCancelledEvent($trip->driver, $trip))->toOthers();
            } catch (\Exception $e) {}

            // FCM to driver
            if ($trip->driver->fcm_token) {
                sendDeviceNotification(
                    fcm_token:       $trip->driver->fcm_token,
                    title:           'Payment timeout',
                    description:     'Customer did not pay in time. Trip has been cancelled.',
                    status:          'trip_cancelled',
                    ride_request_id: $trip->id,
                    type:            $trip->type,
                    action:          'trip_cancelled',
                    user_id:         $trip->driver->id
                );
            }
        }

        // FCM to customer
        if ($trip->customer?->fcm_token) {
            sendDeviceNotification(
                fcm_token:       $trip->customer->fcm_token,
                title:           'Payment deadline expired',
                description:     'Your trip was cancelled because payment was not completed in time.',
                status:          'trip_cancelled',
                ride_request_id: $trip->id,
                type:            $trip->type,
                action:          'payment_timeout',
                user_id:         $trip->customer->id
            );
        }
    }
}
