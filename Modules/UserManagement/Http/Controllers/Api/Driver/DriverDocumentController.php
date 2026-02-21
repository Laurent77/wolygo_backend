<?php

namespace Modules\UserManagement\Http\Controllers\Api\Driver;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\UserManagement\Entities\DriverDocument;
use Ramsey\Uuid\Uuid;

class DriverDocumentController extends Controller
{
    /**
     * List all documents for the authenticated driver.
     */
    public function index(): JsonResponse
    {
        $documents = DriverDocument::where('driver_id', auth('api')->id())
            ->get()
            ->map(fn($d) => $this->transform($d));

        return response()->json(responseFormatter(DEFAULT_200, $documents));
    }

    /**
     * Upload or re-submit a document.
     */
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document_type'   => 'required|in:national_id,passport,driving_license,work_permit,vehicle_registration,vehicle_insurance,technical_inspection,criminal_record',
            'document_number' => 'nullable|string|max:100',
            'issued_at'       => 'nullable|date',
            'expires_at'      => 'nullable|date|after:today',
            'front_image'     => 'nullable|image|mimes:jpeg,jpg,png|max:5000',
            'back_image'      => 'nullable|image|mimes:jpeg,jpg,png|max:5000',
            'pdf_file'        => 'nullable|file|mimes:pdf|max:10000',
        ]);

        if ($validator->fails()) {
            return response()->json(responseFormatter(DEFAULT_400, errors: errorProcessor($validator)), 400);
        }

        $driverId = auth('api')->id();

        $doc = DriverDocument::firstOrNew([
            'driver_id'     => $driverId,
            'document_type' => $request->document_type,
        ]);

        if (!$doc->id) {
            $doc->id = Uuid::uuid4()->toString();
        }

        $doc->document_number = $request->document_number;
        $doc->issued_at       = $request->issued_at;
        $doc->expires_at      = $request->expires_at;
        $doc->status          = 'pending'; // reset to pending on re-submission
        $doc->rejection_reason = null;

        if ($request->hasFile('front_image')) {
            $doc->front_image_path = $request->file('front_image')
                ->store("driver-documents/$driverId/front", 'public');
        }
        if ($request->hasFile('back_image')) {
            $doc->back_image_path = $request->file('back_image')
                ->store("driver-documents/$driverId/back", 'public');
        }
        if ($request->hasFile('pdf_file')) {
            $doc->pdf_path = $request->file('pdf_file')
                ->store("driver-documents/$driverId/pdf", 'public');
        }

        $doc->save();

        return response()->json(responseFormatter(DEFAULT_STORE_200, $this->transform($doc)));
    }

    /**
     * Check if the driver account is fully approved (all mandatory docs approved).
     * Used by the app on login to decide whether to show the blocking screen.
     */
    public function approvalStatus(): JsonResponse
    {
        $driverId  = auth('api')->id();
        $documents = DriverDocument::where('driver_id', $driverId)->get();

        $mandatoryTypes = ['driving_license'];  // extend as needed from admin config
        $allApproved    = true;
        $pendingDocs    = [];

        foreach ($documents as $doc) {
            if ($doc->is_mandatory && $doc->status !== 'approved') {
                $allApproved = false;
                $pendingDocs[] = $this->transform($doc);
            }
        }

        return response()->json(responseFormatter(DEFAULT_200, [
            'is_approved'  => $allApproved,
            'documents'    => $documents->map(fn($d) => $this->transform($d)),
            'pending_docs' => $pendingDocs,
        ]));
    }

    private function transform(DriverDocument $doc): array
    {
        return [
            'id'              => $doc->id,
            'document_type'   => $doc->document_type,
            'document_number' => $doc->document_number,
            'issued_at'       => $doc->issued_at?->format('Y-m-d'),
            'expires_at'      => $doc->expires_at?->format('Y-m-d'),
            'front_image_url' => $doc->front_image_path ? asset('storage/' . $doc->front_image_path) : null,
            'back_image_url'  => $doc->back_image_path  ? asset('storage/' . $doc->back_image_path)  : null,
            'pdf_url'         => $doc->pdf_path          ? asset('storage/' . $doc->pdf_path)          : null,
            'status'          => $doc->status,
            'rejection_reason'=> $doc->rejection_reason,
            'days_until_expiry' => $doc->daysUntilExpiry(),
        ];
    }
}
