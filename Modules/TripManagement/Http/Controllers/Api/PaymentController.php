<?php

namespace Modules\TripManagement\Http\Controllers\Api;

use App\Events\CustomerPaymentConfirmedEvent;
use App\Events\CustomerTripPaymentSuccessfulEvent;
use App\Events\DriverPaymentReceivedEvent;
use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Modules\BusinessManagement\Entities\BusinessSetting;
use Modules\Gateways\Library\Payer;
use Modules\Gateways\Library\Payment as PaymentInfo;
use Modules\Gateways\Library\Receiver;
use Modules\Gateways\Traits\Payment;
use Modules\TransactionManagement\Traits\TransactionTrait;
use Modules\TripManagement\Interfaces\TripRequestInterfaces;
use Modules\UserManagement\Lib\LevelHistoryManagerTrait;
use Modules\UserManagement\Lib\LevelUpdateCheckerTrait;

class PaymentController extends Controller
{
    use TransactionTrait, Payment, LevelHistoryManagerTrait, LevelUpdateCheckerTrait;

    public function __construct(
        private TripRequestInterfaces $trip
    )
    {
    }

    /**
     * @param Request $request
     * @return Application|JsonResponse|RedirectResponse|Redirector
     */
    public function digitalPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'payment_method' => 'required|in:ssl_commerz,stripe,paypal,razor_pay,paystack,senang_pay,paymob_accept,flutterwave,paytm,paytabs,liqpay,mercadopago,bkash,fatoorah,xendit,amazon_pay,iyzi_pay,hyper_pay,foloosi,ccavenue,pvit,moncash,thawani,tap,viva_wallet,hubtel,maxicash,esewa,swish,momo,payfast,worldpay,sixcash,ssl_commerz,stripe,paypal,razor_pay,paystack,senang_pay,paymob_accept,flutterwave,paytm,paytabs,liqpay,mercadopago,bkash,fatoorah,xendit,amazon_pay,iyzi_pay,hyper_pay,foloosi,ccavenue,pvit,moncash,thawani,tap,viva_wallet,hubtel,maxicash,esewa,swish,momo,payfast,worldpay,sixcash'
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $trip = $this->trip->getBy(column: 'id', value: $request->trip_request_id, attributes: ['relations' => ['customer.userAccount', 'fee', 'time', 'driver']]);
        if (!$trip) {
            return response()->json(responseFormatter(TRIP_REQUEST_404), 403);
        }
        if ($trip->payment_status == PAID) {

            return response()->json(responseFormatter(DEFAULT_PAID_200));
        }

        $attributes = [
            'column' => 'id',
            'payment_method' => $request->payment_method,
        ];
        $tips = $request->tips;
        $feeAttributes['tips'] = $tips;
        $attributes['tips'] = $tips;
        $trip->fee()->update($feeAttributes);
        $trip = $this->trip->update($attributes, $request->trip_request_id);

        // Paiement anticipé si trip accepté et paid_fare encore à 0
        $upfrontFare = ($trip->current_status === ACCEPTED && $trip->paid_fare == 0)
            ? round($trip->estimated_fare * 1.12, 2)
            : $trip->paid_fare;
        // Persister paid_fare pour que le webhook (TripRequestUpdate) ait la bonne valeur
        if ($trip->paid_fare == 0 && $trip->current_status === ACCEPTED) {
            $this->trip->update(['column' => 'id', 'paid_fare' => $upfrontFare], $request->trip_request_id);
        }
        $paymentAmount = $upfrontFare + $tips;
        $customer = $trip->customer;
        $payer = new Payer(
            name: $customer?->first_name,
            email: $customer->email,
            phone: $customer->phone,
            address: '');
        $additionalData = [
            'business_name' => BusinessSetting::where(['key_name' => 'business_name'])->first()->value,
            'business_logo' => asset('storage/app/public/business') . '/' . BusinessSetting::where(['key_name' => 'header_logo'])->first()->value,
        ];
//hook is look for a autoloaded function to perform action after payment
        $paymentInfo = new PaymentInfo(
            hook: 'tripRequestUpdate',
            currencyCode: businessConfig('currency_code')?->value ?? 'USD',
            paymentMethod: $request->payment_method,
            paymentPlatform: 'mono',
            payerId: $customer->id,
            receiverId: '100',
            additionalData: $additionalData,
            paymentAmount: $paymentAmount,
            externalRedirectLink: null,
            attribute: 'order',
            attributeId: $request->trip_request_id
        );
        $receiverInfo = new Receiver('receiver_name', 'example.png');
        $redirectLink = $this->generate_link($payer, $paymentInfo, $receiverInfo);

        return redirect($redirectLink);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function payment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'trip_request_id' => 'required',
            'payment_method' => 'required|in:wallet,cash'
        ]);
        if ($validator->fails()) {

            return response()->json(responseFormatter(constant: DEFAULT_400, errors: errorProcessor($validator)), 400);
        }
        $trip = $this->trip->getBy(column: 'id', value: $request->trip_request_id, attributes: [
            'relations' => ['customer.userAccount', 'driver', 'fee']]);
        if (!$trip) {
            return response()->json(responseFormatter(TRIP_REQUEST_404), 403);
        }
        if ($trip->payment_status == PAID) {

            return response()->json(responseFormatter(DEFAULT_PAID_200));
        }

        $tips = 0;
        DB::beginTransaction();
        if (!is_null($request->tips) && $request->payment_method == 'wallet') {
            $tips = $request->tips;
        }

        // Paiement anticipé si trip accepté et paid_fare encore à 0 (OTP déjà validé)
        $wasAccepted = ($trip->current_status === ACCEPTED && $trip->paid_fare == 0);
        $upfrontFare = $wasAccepted
            ? round($trip->estimated_fare * 1.12, 2)
            : $trip->paid_fare;

        $attributes = [
            'column' => 'id',
            'tips' => $tips,
            'payment_method' => $request->payment_method,
            'paid_fare' => $upfrontFare + $tips,
            'payment_status' => PAID
        ];
        $feeAttributes['tips'] = $tips;
        $trip->fee()->update($feeAttributes);
        $trip = $this->trip->update($attributes, $request->trip_request_id);
        $trip->tips = 0;
        $trip->save();
        if ($request->payment_method == 'wallet') {
            if ($trip->customer->userAccount->wallet_balance < ($upfrontFare + $tips)) {

                return response()->json(responseFormatter(INSUFFICIENT_FUND_403), 403);
            }
            $method = '_with_wallet_balance';
            $this->walletTransaction($trip);
        } // driver only make cash payment
        elseif ($request->payment_method == 'cash') {
            $method = '_by_cash';
            $this->cashTransaction($trip);
        }

        // Démarrer la course maintenant que le paiement est confirmé
        if ($wasAccepted) {
            $this->trip->update(['column' => 'id', 'current_status' => ONGOING, 'trip_status' => now()], $request->trip_request_id);
        }

        $this->customerLevelUpdateChecker($trip->customer);
        DB::commit();

        $push = getNotification('payment_successful');
        sendDeviceNotification(
            fcm_token: auth('api')->user()->user_type == 'customer' ? $trip->driver->fcm_token : $trip->customer->fcm_token,
            title: translate($push['title']),
            description: translate(textVariableDataFormat(value: $push['description'],paidAmount: $trip->paid_fare,methodName: $request->payment_method)),
            status: $push['status'],
            ride_request_id: $trip->id,
            type: $trip->type,
            action: 'payment_successful',
            user_id: $trip->driver->id
        );

        if ($wasAccepted) {
            // Paiement avant course : notifier le driver pour passer à ONGOING
            try {
                broadcast(new CustomerPaymentConfirmedEvent($trip))->toOthers();
            } catch(Exception $exception) {}
        } else {
            // Paiement après course : comportement original
            try {
                checkPusherConnection(DriverPaymentReceivedEvent::broadcast($trip));
            }catch(Exception $exception){}
            try {
                checkPusherConnection(CustomerTripPaymentSuccessfulEvent::broadcast($trip));
            }catch(Exception $exception){}
        }

        return response()->json(responseFormatter(DEFAULT_UPDATE_200));
    }

}
