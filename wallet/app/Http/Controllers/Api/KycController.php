<?php

namespace App\Http\Controllers\Api;

use App\Enums\KycStatus;
use App\Http\Controllers\Controller;
use App\Models\KycRecord;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class KycController extends Controller
{
    public function __construct(
        protected NotificationService $notifications,
    ) {}

    /**
     * Submit KYC documents.
     */
    public function submit(Request $request): JsonResponse
    {
        $user = $request->user();

        // Only allow resubmission if not already verified or under review
        $existing = $user->kycRecord;
        if ($existing && in_array($existing->status, [KycStatus::Verified, KycStatus::Pending])) {
            return response()->json([
                'message' => 'KYC already '.$existing->status->value.'. No resubmission needed.',
                'status' => $existing->status->value,
            ], 422);
        }

        $request->validate([
            'id_type' => ['required', 'string', 'in:national_id,passport,drivers_license'],
            'id_number' => ['required', 'string', 'max:50'],
            'id_document' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
            'selfie' => ['required', 'file', 'mimes:jpg,jpeg,png', 'max:5120'],
        ]);

        $idPath = $request->file('id_document')->store('kyc/documents', 'local');
        $selfiePath = $request->file('selfie')->store('kyc/selfies', 'local');

        $kyc = KycRecord::updateOrCreate(
            ['user_id' => $user->id],
            [
                'full_name' => $user->name,
                'id_type' => $request->id_type,
                'id_number' => $request->id_number,
                'id_document_path' => $idPath,
                'selfie_path' => $selfiePath,
                'status' => KycStatus::Pending,
                'review_notes' => null,
                'reviewed_by' => null,
                'reviewed_at' => null,
            ]
        );

        $this->notifications->notifyUser(
            $user,
            'kyc_submitted',
            'KYC submitted',
            'We received your documents and will review within 24 hours.',
            ['kyc_id' => $kyc->id],
            sendEmail: false,
            sendPush: true,
        );

        $this->notifications->notifyAdmins(
            'admin_kyc_submitted',
            'New KYC submission',
            sprintf('User #%d submitted KYC (record #%d).', (int) $user->id, (int) $kyc->id),
            ['user_id' => $user->id, 'kyc_id' => $kyc->id],
            sendEmail: true,
        );

        return response()->json([
            'message' => 'KYC submitted successfully. We will review within 24 hours.',
            'kyc' => $this->formatKyc($kyc),
        ], 201);
    }

    /**
     * Get the authenticated user's KYC status.
     */
    public function status(Request $request): JsonResponse
    {
        $kyc = $request->user()->kycRecord;

        if (! $kyc) {
            return response()->json([
                'status' => KycStatus::NotSubmitted->value,
                'message' => 'No KYC record found. Please submit your documents.',
                'kyc' => null,
            ]);
        }

        return response()->json([
            'status' => $kyc->status->value,
            'kyc' => $this->formatKyc($kyc),
        ]);
    }

    /**
     * Admin: list pending KYC records.
     */
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', KycRecord::class);

        $records = KycRecord::with('user:id,name,email')
            ->where('status', KycStatus::Pending)
            ->latest()
            ->paginate(20);

        return response()->json($records);
    }

    /**
     * Admin: approve or reject a KYC record.
     */
    public function review(Request $request, KycRecord $kyc): JsonResponse
    {
        Gate::authorize('review', $kyc);

        $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'review_notes' => ['nullable', 'string', 'max:500'],
        ]);

        $kyc->update([
            'status' => $request->decision === 'approved' ? KycStatus::Verified : KycStatus::Rejected,
            'review_notes' => $request->review_notes,
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        $decision = $request->decision === 'approved' ? 'approved' : 'rejected';
        $user = $kyc->user()->first();
        if ($user) {
            $this->notifications->notifyUser(
                $user,
                $decision === 'approved' ? 'kyc_approved' : 'kyc_rejected',
                $decision === 'approved' ? 'KYC approved' : 'KYC rejected',
                $decision === 'approved'
                    ? 'Your KYC has been approved. You can now access all features.'
                    : 'Your KYC was rejected. Please review the notes and resubmit.',
                ['kyc_id' => $kyc->id],
                sendEmail: true,
                sendPush: true,
            );
        }

        return response()->json([
            'message' => 'KYC record '.$request->decision.'.',
            'kyc' => $this->formatKyc($kyc->fresh()),
        ]);
    }

    private function formatKyc(KycRecord $kyc): array
    {
        return [
            'id' => $kyc->id,
            'id_type' => $kyc->id_type,
            'id_number' => $kyc->id_number,
            'status' => $kyc->status->value,
            'review_notes' => $kyc->review_notes,
            'submitted_at' => $kyc->created_at?->toISOString(),
            'reviewed_at' => $kyc->reviewed_at?->toISOString(),
        ];
    }
}
