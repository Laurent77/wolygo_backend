<?php

namespace Modules\UserManagement\Http\Controllers\Api\Driver;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Validator;
use Modules\UserManagement\Entities\DriverDocument;

class DriverDocumentController extends Controller
{
    /**
     * List all documents for the authenticated driver, grouped by type.
     */
    public function index(): JsonResponse
    {
        $documents = DriverDocument::where('driver_id', auth('api')->id())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($d) => $this->transform($d));

        return response()->json(responseFormatter(DEFAULT_200, $documents));
    }

    /**
     * Upload a new document (always creates a new record — multiple per type allowed).
     */
    public function upload(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document_type'   => 'required|in:work_permit,vehicle_registration,vehicle_insurance,technical_inspection,criminal_record,formation_taxi,numero_tvq,tps,photo_odometre,certificat_assurance,certificat_immatriculation',
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

        if (!$request->hasFile('front_image') && !$request->hasFile('pdf_file')) {
            return response()->json(responseFormatter(DEFAULT_400, errors: [
                ['message' => 'Veuillez fournir au moins le recto ou un PDF.']
            ]), 400);
        }

        $driverId = auth('api')->id();

        $data = [
            'driver_id'       => $driverId,
            'document_type'   => $request->document_type,
            'document_number' => $request->document_number,
            'issued_at'       => $request->issued_at,
            'expires_at'      => $request->expires_at,
            'status'          => 'pending',
            'is_mandatory'    => true,
        ];

        if ($request->hasFile('front_image')) {
            $data['front_image_path'] = $request->file('front_image')
                ->store("driver-documents/$driverId/front", 'public');
        }
        if ($request->hasFile('back_image')) {
            $data['back_image_path'] = $request->file('back_image')
                ->store("driver-documents/$driverId/back", 'public');
        }
        if ($request->hasFile('pdf_file')) {
            $data['pdf_path'] = $request->file('pdf_file')
                ->store("driver-documents/$driverId/pdf", 'public');
        }

        $doc = DriverDocument::create($data);

        return response()->json(responseFormatter(DEFAULT_STORE_200, $this->transform($doc)));
    }

    /**
     * Approval status:
     * Approved = at least one document submitted AND for each mandatory type submitted,
     * at least one document of that type has status 'approved'.
     */
    public function approvalStatus(): JsonResponse
    {
        $driverId  = auth('api')->id();
        $documents = DriverDocument::where('driver_id', $driverId)->get();

        // Aucun document soumis → pas approuvé
        if ($documents->isEmpty()) {
            return response()->json(responseFormatter(DEFAULT_200, [
                'is_approved'  => false,
                'documents'    => [],
                'pending_docs' => [],
            ]));
        }

        // Pour chaque type soumis : au moins 1 doc doit être approuvé
        $submittedTypes = $documents->pluck('document_type')->unique();
        $allApproved    = true;
        $pendingDocs    = [];

        foreach ($submittedTypes as $type) {
            $typeDocs    = $documents->where('document_type', $type);
            $hasApproved = $typeDocs->where('status', 'approved')->isNotEmpty();

            if (!$hasApproved) {
                $allApproved = false;
                foreach ($typeDocs as $doc) {
                    $pendingDocs[] = $this->transform($doc);
                }
            }
        }

        return response()->json(responseFormatter(DEFAULT_200, [
            'is_approved'  => $allApproved,
            'documents'    => $documents->map(fn($d) => $this->transform($d))->values(),
            'pending_docs' => $pendingDocs,
        ]));
    }

    private function transform(DriverDocument $doc): array
    {
        return [
            'id'               => $doc->id,
            'document_type'    => $doc->document_type,
            'document_number'  => $doc->document_number,
            'issued_at'        => $doc->issued_at?->format('Y-m-d'),
            'expires_at'       => $doc->expires_at?->format('Y-m-d'),
            'front_image_url'  => $doc->front_image_path
                ? asset('storage/' . $doc->front_image_path) : null,
            'back_image_url'   => $doc->back_image_path
                ? asset('storage/' . $doc->back_image_path)  : null,
            'pdf_url'          => $doc->pdf_path
                ? asset('storage/' . $doc->pdf_path)          : null,
            'status'           => $doc->status,
            'rejection_reason' => $doc->rejection_reason,
            'days_until_expiry'=> $doc->daysUntilExpiry(),
        ];
    }
}
