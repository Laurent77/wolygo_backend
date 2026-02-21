<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\UserManagement\Entities\DriverDocument;

class CheckDocumentExpiration extends Command
{
    protected $signature   = 'driver-documents:check-expiration';
    protected $description = 'Check driver document expiration dates and send reminder notifications';

    public function handle(): int
    {
        $today = Carbon::today();

        // 1. Documents that expire today → mark as expired
        DriverDocument::where('status', 'approved')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<=', $today)
            ->update(['status' => 'expired']);

        // 2. Documents expiring within 60 days → mark as expiring_soon
        DriverDocument::where('status', 'approved')
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '>', $today)
            ->whereDate('expires_at', '<=', $today->copy()->addDays(60))
            ->update(['status' => 'expiring_soon']);

        // 3. Send notifications per driver
        $affected = DriverDocument::with('driver')
            ->whereIn('status', ['expiring_soon', 'expired'])
            ->get()
            ->groupBy('driver_id');

        foreach ($affected as $driverId => $docs) {
            $driver = $docs->first()->driver;
            if (!$driver || !$driver->fcm_token) continue;

            foreach ($docs as $doc) {
                $days   = $doc->daysUntilExpiry();
                $label  = str_replace('_', ' ', $doc->document_type);

                if ($doc->status === 'expired') {
                    $title   = translate('Document Expired');
                    $message = translate("Your $label has expired. Please renew it to continue driving.");
                } else {
                    $title   = translate('Document Expiring Soon');
                    $message = $days > 0
                        ? translate("Your $label expires in $days days. Please renew it.")
                        : translate("Your $label expires today!");
                }

                sendDeviceNotification(
                    fcm_token:       $driver->fcm_token,
                    title:           $title,
                    description:     $message,
                    status:          1,
                    ride_request_id: null,
                    action:          'document_expiry_reminder',
                    user_id:         $driver->id,
                );
            }
        }

        $this->info('Document expiration check complete.');
        return 0;
    }
}
