<?php

namespace Modules\AdminModule\Http\Controllers\Web\New\Admin;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\UserManagement\Entities\DriverDocument;
use Modules\UserManagement\Entities\User;

class DriverDocumentAdminController extends Controller
{
    /**
     * List all drivers with their document status.
     */
    public function index(Request $request)
    {
        $drivers = User::where('user_type', DRIVER)
            ->with('driverDocuments')
            ->when($request->status, function ($q) use ($request) {
                $q->whereHas('driverDocuments', fn($d) => $d->where('status', $request->status));
            })
            ->when($request->search, function ($q) use ($request) {
                $q->where(fn($q2) => $q2
                    ->where('first_name', 'like', '%' . $request->search . '%')
                    ->orWhere('last_name', 'like', '%' . $request->search . '%')
                    ->orWhere('phone', 'like', '%' . $request->search . '%')
                );
            })
            ->paginate(15);

        return view('adminmodule::driver-documents.index', compact('drivers'));
    }

    /**
     * Show all documents for a specific driver.
     */
    public function show(string $driverId)
    {
        $driver    = User::where('user_type', DRIVER)->findOrFail($driverId);
        $documents = DriverDocument::where('driver_id', $driverId)->get();

        return view('adminmodule::driver-documents.show', compact('driver', 'documents'));
    }

    /**
     * Approve a document.
     */
    public function approve(string $docId)
    {
        $doc = DriverDocument::findOrFail($docId);
        $doc->update([
            'status'      => 'approved',
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);

        // Notify driver
        if ($doc->driver?->fcm_token) {
            $label = str_replace('_', ' ', $doc->document_type);
            sendDeviceNotification(
                fcm_token:       $doc->driver->fcm_token,
                title:           'Document Approved',
                description:     "Your $label has been approved. You can now drive with WolyGo.",
                status:          1,
                ride_request_id: null,
                action:          'document_approved',
                user_id:         $doc->driver_id,
            );
        }

        return back()->with('success', translate('Document approved successfully.'));
    }

    /**
     * Reject a document with a reason.
     */
    public function reject(Request $request, string $docId)
    {
        $request->validate(['reason' => 'required|string|max:500']);

        $doc = DriverDocument::findOrFail($docId);
        $doc->update([
            'status'           => 'rejected',
            'rejection_reason' => $request->reason,
            'reviewed_by'      => auth()->id(),
            'reviewed_at'      => now(),
        ]);

        // Notify driver
        if ($doc->driver?->fcm_token) {
            $label = str_replace('_', ' ', $doc->document_type);
            sendDeviceNotification(
                fcm_token:       $doc->driver->fcm_token,
                title:           'Document Rejected',
                description:     "Your $label was rejected: {$request->reason}. Please re-submit.",
                status:          1,
                ride_request_id: null,
                action:          'document_rejected',
                user_id:         $doc->driver_id,
            );
        }

        return back()->with('success', translate('Document rejected.'));
    }
}
