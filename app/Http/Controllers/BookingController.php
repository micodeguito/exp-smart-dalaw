<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBookingRequest;
use App\Mail\BookingConfirmation;
use App\Models\Booking;
use App\Models\Dormitory;
use App\Models\Facility;
use App\Support\VisitorIdPhoto;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function index()
    {
        return view('home');
    }

    public function create()
    {
        $facilities = Facility::query()
            ->with(['dormitories' => fn ($query) => $query->orderBy('name')])
            ->orderBy('regional_office')
            ->orderBy('name')
            ->get();

        return view('bookings.create', compact('facilities'));
    }

    public function checkSlotAvailability(Request $request)
    {
        $validated = $request->validate([
            'facility_id' => ['required', 'integer', 'exists:facilities,id'],
            'dormitory_id' => [
                'required',
                'integer',
                Rule::exists('dormitories', 'id')
                    ->where(fn ($query) => $query->where('facility_id', $request->input('facility_id'))),
            ],
            'visit_date' => ['required', 'date'],
            'time_slot' => ['required', 'string', 'max:50'],
        ]);

        $dormitory = Dormitory::findOrFail($validated['dormitory_id']);
        $usage = $dormitory->slotUsage($validated['visit_date'], $validated['time_slot']);

        return response()->json([
            'available' => $dormitory->acceptsChannel(Booking::CHANNEL_ONLINE, $usage),
            'current_online_bookings' => $usage['online'],
            'current_combined_bookings' => $usage['combined'],
            'max_online_bookings_per_hour' => $dormitory->max_online_bookings_per_hour,
            'max_bookings_per_hour' => $dormitory->max_bookings_per_hour,
        ]);
    }

    /**
     * Return the CSRF token for the caller's active session so a form that was
     * left open past the session lifetime can submit without a 419 response.
     */
    public function csrfToken(Request $request)
    {
        return response()
            ->json(['token' => $request->session()->token()])
            ->header('Cache-Control', 'no-store, private');
    }

    public function store(StoreBookingRequest $request)
    {
        $validated = $request->validated();
        $referenceCode = 'BJMP-'.strtoupper(Str::random(6));
        $consentedAt = now();

        $storedPhotoPaths = [];

        try {
            $booking = DB::transaction(function () use ($validated, $request, $referenceCode, $consentedAt, &$storedPhotoPaths): Booking {
                $dormitory = Dormitory::query()
                    ->lockForUpdate()
                    ->findOrFail($validated['dormitory_id']);

                if ((int) $dormitory->facility_id !== (int) $validated['facility_id']) {
                    throw ValidationException::withMessages([
                        'dormitory_id' => 'The selected dormitory does not belong to this facility.',
                    ]);
                }

                $usage = $dormitory->slotUsage($validated['visit_date'], $validated['time_slot']);

                if (! $dormitory->acceptsChannel(Booking::CHANNEL_ONLINE, $usage)) {
                    throw ValidationException::withMessages([
                        'time_slot' => $dormitory->capacityErrorMessage(Booking::CHANNEL_ONLINE, $usage),
                    ]);
                }

                $accompanyingVisitors = $validated['accompanying_visitors'] ?? [];
                unset($validated['accompanying_visitors']);

                // Store the primary visitor's ID photo privately (not publicly accessible).
                $primaryPhotoPath = $request->file('visitor_id_photo')
                    ->store('visitor-id-photos', 'private');
                $storedPhotoPaths[] = $primaryPhotoPath;

                $booking = Booking::create([
                    ...$validated,
                    'reference_code' => $referenceCode,
                    'booking_channel' => Booking::CHANNEL_ONLINE,
                    'status' => 'Pending',
                    'visitor_id_photo_path' => $primaryPhotoPath,
                    'terms_accepted' => true,
                    'terms_accepted_at' => $consentedAt,
                    'privacy_consent' => true,
                    'privacy_consent_at' => $consentedAt,
                    'privacy_notice_version' => StoreBookingRequest::PRIVACY_NOTICE_VERSION,
                ]);

                foreach ($accompanyingVisitors as $index => $visitorData) {
                    $accompanyingPhotoPath = $request->file("accompanying_visitors.{$index}.id_photo")
                        ->store('visitor-id-photos', 'private');
                    $storedPhotoPaths[] = $accompanyingPhotoPath;

                    $visitorData['id_photo_path'] = $accompanyingPhotoPath;
                    $booking->accompanyingVisitors()->create($visitorData);
                }

                $booking->statusHistory()->create([
                    'from_status' => null,
                    'to_status' => 'Pending',
                    'reason' => 'Booking request submitted.',
                ]);

                return $booking;
            });
        } catch (\Throwable $e) {
            // The DB transaction rolled back; remove any orphaned uploaded photos.
            foreach ($storedPhotoPaths as $path) {
                Storage::disk('private')->delete($path);
            }
            throw $e;
        }

        // Send the confirmation email after the booking is persisted. A mail
        // failure must never roll back or fail the booking itself.
        if ($booking->visitor_email) {
            try {
                Mail::to($booking->visitor_email)->send(new BookingConfirmation($booking));
            } catch (\Throwable $e) {
                Log::error('Booking confirmation email failed', [
                    'reference_code' => $booking->reference_code,
                    'mailer' => config('mail.default'),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('booking.success', $referenceCode);
    }

    public function success($code)
    {
        $booking = Booking::query()->with(['facility', 'dormitory', 'statusHistory.changedBy', 'accompanyingVisitors'])
            ->where('reference_code', $code)
            ->firstOrFail();

        return view('bookings.success', compact('booking'));
    }

    /**
     * Serve a visitor's ID photo only through the booking reference that the
     * visitor already uses to view that booking's status.
     */
    public function viewIdPhoto($code, $visitor)
    {
        $booking = Booking::query()
            ->where('reference_code', strtoupper((string) $code))
            ->firstOrFail();

        return VisitorIdPhoto::response($booking->idPhotoPathFor((string) $visitor));
    }

    public function track()
    {
        return view('bookings.track');
    }

    public function trackSearch(Request $request)
    {
        $request->validate([
            'reference_code' => 'required|string',
        ]);

        $booking = Booking::query()->with(['facility', 'dormitory', 'statusHistory.changedBy', 'accompanyingVisitors'])
            ->where('reference_code', strtoupper($request->reference_code))
            ->first();

        if (! $booking) {
            return back()->with('error', 'No booking found with this reference code.');
        }

        return view('bookings.track-result', compact('booking'));
    }

    public function qrRedirect($code)
    {
        $booking = Booking::query()->with(['facility', 'dormitory', 'accompanyingVisitors'])
            ->where('reference_code', strtoupper($code))
            ->first();

        if (! $booking) {
            return redirect()->route('bookings.track')->with('error', 'Invalid QR code or booking not found.');
        }

        return redirect()->route('booking.success', $booking->reference_code);
    }
}
