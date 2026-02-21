<?php

namespace Modules\AdminModule\Http\Controllers\Web\New\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\UserManagement\Entities\AdminBroadcastNotification;
use Modules\UserManagement\Entities\User;

class BroadcastNotificationController extends Controller
{
    public function index()
    {
        $notifications = AdminBroadcastNotification::with('sentBy', 'targetUser')
            ->latest()
            ->paginate(15);

        return view('adminmodule::broadcast-notification.index', compact('notifications'));
    }

    public function create()
    {
        return view('adminmodule::broadcast-notification.create');
    }

    public function send(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'          => 'required|max:200',
            'message'        => 'required',
            'target_type'    => 'required|in:all_customers,all_drivers,all_users,specific_user',
            'target_user_id' => 'required_if:target_type,specific_user|nullable|uuid',
            'image'          => 'nullable|image|mimes:jpeg,jpg,png|max:5000',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        // Store image in push-notification folder (matches sendDeviceNotification helper path)
        $imageFilename = null;
        $imagePath     = null;
        if ($request->hasFile('image')) {
            $stored        = $request->file('image')->store('push-notification', 'public');
            $imageFilename = basename($stored); // just the filename for the helper
            $imagePath     = $stored;           // relative path for DB
        }

        // Resolve recipients
        $query = User::whereNull('deleted_at');
        if ($request->target_type === 'all_customers') {
            $query->where('user_type', CUSTOMER);
        } elseif ($request->target_type === 'all_drivers') {
            $query->where('user_type', DRIVER);
        } elseif ($request->target_type === 'specific_user') {
            $query->where('id', $request->target_user_id);
        }
        // all_users = no extra filter

        $users        = $query->whereNotNull('fcm_token')->get();
        $total        = $users->count();
        $successCount = 0;
        $failCount    = 0;

        foreach ($users as $user) {
            try {
                sendDeviceNotification(
                    fcm_token:       $user->fcm_token,
                    title:           $request->title,
                    description:     $request->message,
                    status:          1,
                    image:           $imageFilename,
                    ride_request_id: null,
                    action:          'admin_broadcast',
                    user_id:         $user->id,
                );
                $successCount++;
            } catch (\Exception $e) {
                $failCount++;
            }
        }

        AdminBroadcastNotification::create([
            'title'            => $request->title,
            'message'          => $request->message,
            'image_path'       => $imagePath,
            'target_type'      => $request->target_type,
            'target_user_id'   => $request->target_user_id ?? null,
            'channels'         => ['push'],
            'status'           => 'sent',
            'sent_at'          => now(),
            'sent_by'          => auth()->id(),
            'total_recipients' => $total,
            'success_count'    => $successCount,
            'failure_count'    => $failCount,
        ]);

        return redirect()->route('admin.broadcast-notification.index')
            ->with('success', translate('Notification sent to') . ' ' . $successCount . ' ' . translate('recipients'));
    }

    public function userSearch(Request $request)
    {
        $users = User::where('user_type', '!=', 'super-admin')
            ->where(function ($q) use ($request) {
                $q->where('first_name', 'like', '%' . $request->q . '%')
                  ->orWhere('last_name',  'like', '%' . $request->q . '%')
                  ->orWhere('phone',      'like', '%' . $request->q . '%');
            })
            ->select('id', 'first_name', 'last_name', 'phone', 'user_type')
            ->limit(10)
            ->get();

        return response()->json($users);
    }
}
