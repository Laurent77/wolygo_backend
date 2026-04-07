<?php


use App\Events\CustomerPaymentConfirmedEvent;
use App\Events\CustomerTripPaymentSuccessfulEvent;
use Modules\TripManagement\Entities\TripRequest;
use Modules\TransactionManagement\Traits\TransactionTrait;
use Modules\UserManagement\Lib\LevelHistoryManagerTrait;

if (!function_exists('tripRequestUpdate'))
{
    function tripRequestUpdate($data)
    {
        $trip = TripRequest::query()
            ->with(['driver', 'customer'])
            ->find($data->attribute_id);

        // Detect upfront payment : ACCEPTED ou ONGOING (l'ancien matchOtp passe ONGOING avant paiement)
        $isUpfrontPayment = in_array($trip->current_status, [ACCEPTED, ONGOING]);

        $trip->paid_fare = ($trip->paid_fare + $trip->tips);
        $trip->payment_status = PAID;
        if ($isUpfrontPayment && $trip->current_status === ACCEPTED) {
            // Seulement si encore ACCEPTED (l'ancien matchOtp l'a peut-être déjà mis à ONGOING)
            $trip->current_status = ONGOING;
            $trip->trip_status = now();
        }
        $trip->save();
        $push = getNotification('payment_successful');
        sendDeviceNotification(
            fcm_token: $trip->driver->fcm_token,
            title: translate($push['title']),
            description: translate(textVariableDataFormat(value: $push['description'],paidAmount: $trip->paid_fare,methodName: $trip->payment_method)),
            status: $push['status'],
            ride_request_id: $trip->id,
            type: $trip->type,
            action: 'payment_successful',
            user_id: $trip->driver->id
        );
        if ($trip->tips > 0)
        {
            $pushTips = getNotification('tips_from_customer');
            sendDeviceNotification(
                fcm_token: $trip->driver->fcm_token,
                title: translate($pushTips['title']),
                description: translate(textVariableDataFormat(value: $pushTips['description'],tipsAmount: $trip->tips)),
                status: $push['status'],
                ride_request_id: $trip->id,
                type: $trip->type,
                action: 'tips_from_customer',
                user_id: $trip->driver->id
            );
        }
        if (!empty($trip)) {
            try {
                if ($isUpfrontPayment) {
                    // Paiement avant course : notifier le driver pour passer à ONGOING
                    broadcast(new CustomerPaymentConfirmedEvent($trip))->toOthers();
                } else {
                    // Paiement après course : notifier le driver que le client a payé
                    event(checkPusherConnection(CustomerTripPaymentSuccessfulEvent::broadcast($trip)));
                }
            }catch(Exception $exception){

            }
        }

        (new class {
            use TransactionTrait;
        })->digitalPaymentTransaction($trip);

        return $trip;
    }
}
