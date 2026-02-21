<?php

namespace App\Broadcasting;

use Modules\TripManagement\Entities\TripRequest;
use Modules\UserManagement\Entities\User;

class CustomerPaymentConfirmedChannel
{
    public function __construct() {}

    /**
     * Only the assigned driver of this trip can listen on this channel.
     */
    public function join(User $user, $id): array|bool
    {
        $trip = TripRequest::find($id);
        return $trip && $user->id == $trip->driver_id;
    }
}
