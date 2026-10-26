<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Dormitory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use App\Mail\BookingStatusChanged;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $visibleBookings = Booking::visibleToUser($request->user());

        $countsByStatus = (clone $visibleBookings)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $analytics = [
            'total' => $countsByStatus->sum(),
            'pending' => (int) $countsByStatus->get('Pending', 0),
            'approved' => (int) $countsByStatus->get('Approved', 0),
            'rejected' => (int) $countsByStatus->get('Rejected', 0),
            'today' => (clone $visibleBookings)->whereDate('visit_date', today())->count(),
            'upcoming' => (clone $visibleBookings)->whereDate('visit_date', '>=', today())
                ->where('status', 'Approved')
                ->count(),
        ];

        $query = Booking::visibleToUser($request->user())->with(['facility', 'dormitory']);

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        if ($request->has('date') && $request->date !== '') {
            $query->whereDate('visit_date', $request->date);
        }

        $bookings = $query->orderBy('created_at', 'desc')->paginate(20);

        return view('admin.dashboard', compact('analytics', 'bookings'));
    }

    public function approve(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->changeStatus($booking, 'Approved', $validated['reason'] ?? null);
    }

    public function reject(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'admin_remarks' => ['required', 'string', 'max:500'],
        ]);

        return $this->changeStatus($booking, 'Rejected', $validated['admin_remarks']);
    }

    public function updateStatus(Request $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['Pending', 'Approved', 'Rejected'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        if (in_array($validated['status'], ['Pending', 'Rejected'], true) && blank($validated['reason'] ?? null)) {
            throw ValidationException::withMessages([
                'reason' => 'A reason is required when changing the status to Pending or Rejected.',
            ]);
        }

        return $this->changeStatus($booking, $validated['status'], $validated['reason'] ?? null);
    }

    private function changeStatus(Booking $booking, string $status, ?string $reason): RedirectResponse
    {
        $previousStatus = $booking->status;

        DB::transaction(function () use ($booking, $status, $reason, &$previousStatus): void {
            $lockedBooking = Booking::visibleToUser(auth()->user())
                ->lockForUpdate()
                ->findOrFail($booking->id);

            if ($lockedBooking->status === $status) {
                throw ValidationException::withMessages([
                    'status' => "This booking is already {$status}.",
                ]);
            }

            if ($lockedBooking->status === 'Rejected' && $status !== 'Rejected') {
                if (! $lockedBooking->dormitory_id) {
                    throw ValidationException::withMessages([
                        'status' => 'This visit cannot be reactivated because it has no dormitory assignment.',
                    ]);
                }

                $dormitory = Dormitory::query()
                    ->lockForUpdate()
                    ->findOrFail($lockedBooking->dormitory_id);
                $usage = $dormitory->slotUsage(
                    $lockedBooking->visit_date->toDateString(),
                    $lockedBooking->time_slot,
                    $lockedBooking->id,
                );
                $channel = $lockedBooking->booking_channel ?? Booking::CHANNEL_ONLINE;

                if (! $dormitory->acceptsChannel($channel, $usage)) {
                    throw ValidationException::withMessages([
                        'status' => $dormitory->capacityErrorMessage($channel, $usage),
                    ]);
                }
            }

            $previousStatus = $lockedBooking->status;
            $lockedBooking->status = $status;
            $lockedBooking->admin_remarks = $status === 'Rejected' ? $reason : null;
            $lockedBooking->save();

            $lockedBooking->statusHistory()->create([
                'user_id' => auth()->id(),
                'from_status' => $previousStatus,
                'to_status' => $status,
                'reason' => filled($reason) ? $reason : "Status changed to {$status}.",
            ]);
        });

        // Send decision notifications after the transaction commits. Pending
        // means the request still needs review, so it does not trigger a
        // decision email. Mail failures must not roll back the status change.
        if ($booking->visitor_email && in_array($status, ['Approved', 'Rejected'], true)) {
            $booking->refresh();
            try {
                Mail::to($booking->visitor_email)->send(new BookingStatusChanged(
                    $booking,
                    $previousStatus,
                    $status === 'Rejected' ? $reason : null,
                ));
            } catch (\Throwable $e) {
                Log::error('Booking status-change email failed', [
                    'reference_code' => $booking->reference_code,
                    'mailer' => config('mail.default'),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return redirect()->route('admin.dashboard')->with('success', "Booking status changed to {$status}.");
    }

    public function dailySummary(Request $request): View
    {
        $date = $request->query('date', today()->toDateString());

        $bookings = Booking::visibleToUser($request->user())
            ->whereDate('visit_date', $date)
            ->where('status', 'Approved')
            ->with('accompanyingVisitors')
            ->get();

        $summary = [
            'date' => $date,
            'total_visitors' => 0,
            'by_sex' => ['Male' => 0, 'Female' => 0],
            'by_age' => ['child' => 0, 'adult' => 0, 'senior' => 0],
            'by_sex_and_age' => [
                'Male' => ['child' => 0, 'adult' => 0, 'senior' => 0],
                'Female' => ['child' => 0, 'adult' => 0, 'senior' => 0],
            ],
        ];

        foreach ($bookings as $booking) {
            $visitors = collect([
                [
                    'age' => $booking->visitor_age,
                    'sex' => $booking->visitor_sex,
                ],
            ]);

            foreach ($booking->accompanyingVisitors as $accompanying) {
                $visitors->push([
                    'age' => $accompanying->age,
                    'sex' => $accompanying->sex,
                ]);
            }

            foreach ($visitors as $visitor) {
                $summary['total_visitors']++;
                $summary['by_sex'][$visitor['sex']]++;

                if ($visitor['age'] < 18) {
                    $ageCategory = 'child';
                } elseif ($visitor['age'] >= 60) {
                    $ageCategory = 'senior';
                } else {
                    $ageCategory = 'adult';
                }

                $summary['by_age'][$ageCategory]++;
                $summary['by_sex_and_age'][$visitor['sex']][$ageCategory]++;
            }
        }

        return view('admin.daily-summary', compact('summary', 'bookings'));
    }
}
