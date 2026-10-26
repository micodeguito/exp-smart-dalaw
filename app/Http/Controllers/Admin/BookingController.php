<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWalkInBookingRequest;
use App\Http\Requests\UpdateBookingRequest;
use App\Models\Booking;
use App\Models\Dormitory;
use App\Models\Facility;
use App\Support\VisitorIdPhoto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function create(): View
    {
        $facilities = Facility::visibleToUser(auth()->user())
            ->with(['dormitories' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        return view('admin.bookings.create', compact('facilities'));
    }

    public function store(StoreWalkInBookingRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $referenceCode = 'BJMP-'.strtoupper(Str::random(6));

        $booking = DB::transaction(function () use ($validated, $referenceCode): Booking {
            $facility = Facility::visibleToUser(auth()->user())
                ->lockForUpdate()
                ->findOrFail($validated['facility_id']);
            $dormitory = $facility->dormitories()
                ->lockForUpdate()
                ->findOrFail($validated['dormitory_id']);

            $usage = $dormitory->slotUsage($validated['visit_date'], $validated['time_slot']);

            if (! $dormitory->acceptsChannel(Booking::CHANNEL_WALK_IN, $usage)) {
                throw ValidationException::withMessages([
                    'time_slot' => $dormitory->capacityErrorMessage(Booking::CHANNEL_WALK_IN, $usage),
                ]);
            }

            $accompanyingVisitors = $validated['accompanying_visitors'] ?? [];
            unset($validated['accompanying_visitors']);

            $booking = Booking::create([
                ...$validated,
                'reference_code' => $referenceCode,
                'booking_channel' => Booking::CHANNEL_WALK_IN,
                'status' => 'Approved',
                'terms_accepted' => true,
                'terms_accepted_at' => now(),
                'privacy_consent' => true,
                'privacy_consent_at' => now(),
                'privacy_notice_version' => '2026-09-28-v1',
            ]);

            foreach ($accompanyingVisitors as $visitorData) {
                $booking->accompanyingVisitors()->create($visitorData);
            }

            $booking->statusHistory()->create([
                'user_id' => auth()->id(),
                'from_status' => null,
                'to_status' => 'Approved',
                'reason' => 'Walk-in visitor registered on-site.',
            ]);

            return $booking;
        });

        return redirect()->route('admin.dashboard')->with('success', 'Walk-in visitor registered successfully.');
    }

    public function show(Booking $booking): View
    {
        $booking = Booking::visibleToUser(auth()->user())
            ->whereKey($booking->id)
            ->with(['facility', 'dormitory', 'accompanyingVisitors', 'statusHistory.changedBy'])
            ->firstOrFail();

        return view('admin.bookings.show', compact('booking'));
    }

    public function edit(Booking $booking): View
    {
        $booking = Booking::visibleToUser(auth()->user())
            ->whereKey($booking->id)
            ->firstOrFail();
        $booking->load(['facility', 'dormitory', 'accompanyingVisitors', 'statusHistory.changedBy']);
        $facilities = Facility::visibleToUser(auth()->user())
            ->with(['dormitories' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        return view('admin.bookings.edit', compact('booking', 'facilities'));
    }

    public function update(UpdateBookingRequest $request, Booking $booking): RedirectResponse
    {
        $validated = $request->validated();
        $accompanyingVisitors = $validated['accompanying_visitors'] ?? [];
        unset($validated['accompanying_visitors'], $validated['visitor_id_photo']);

        $newPhotoPaths = [];
        $oldPhotoPaths = [];

        try {
            DB::transaction(function () use ($request, $booking, $validated, $accompanyingVisitors, &$newPhotoPaths, &$oldPhotoPaths): void {
                $lockedBooking = Booking::visibleToUser(auth()->user())
                    ->lockForUpdate()
                    ->findOrFail($booking->id);
                $facility = Facility::visibleToUser(auth()->user())
                    ->lockForUpdate()
                    ->findOrFail($validated['facility_id']);
                $dormitory = $facility->dormitories()
                    ->lockForUpdate()
                    ->findOrFail($validated['dormitory_id']);

                if ($lockedBooking->status !== 'Rejected') {
                    $usage = $dormitory->slotUsage(
                        $validated['visit_date'],
                        $validated['time_slot'],
                        $lockedBooking->id,
                    );
                    $channel = $lockedBooking->booking_channel ?? Booking::CHANNEL_ONLINE;

                    if (! $dormitory->acceptsChannel($channel, $usage)) {
                        throw ValidationException::withMessages([
                            'time_slot' => $dormitory->capacityErrorMessage($channel, $usage),
                        ]);
                    }
                }

                $lockedBooking->update($validated);

                if ($request->hasFile('visitor_id_photo')) {
                    $path = $request->file('visitor_id_photo')->store(VisitorIdPhoto::DIRECTORY, VisitorIdPhoto::DISK);
                    $newPhotoPaths[] = $path;

                    if ($lockedBooking->visitor_id_photo_path) {
                        $oldPhotoPaths[] = $lockedBooking->visitor_id_photo_path;
                    }

                    $lockedBooking->visitor_id_photo_path = $path;
                    $lockedBooking->save();
                }

                $existingVisitors = $lockedBooking->accompanyingVisitors()->get()->keyBy('id');
                $keptVisitorIds = [];

                foreach ($accompanyingVisitors as $index => $visitorData) {
                    $visitorId = $visitorData['id'] ?? null;
                    $newPhoto = $visitorData['id_photo'] ?? null;
                    unset($visitorData['id'], $visitorData['id_photo']);

                    if ($visitorId !== null && $visitorId !== '') {
                        $existingVisitor = $existingVisitors->get((int) $visitorId);

                        if (! $existingVisitor) {
                            throw ValidationException::withMessages([
                                "accompanying_visitors.{$index}.id" => 'The selected accompanying visitor does not belong to this booking.',
                            ]);
                        }

                        if ($newPhoto) {
                            $path = $newPhoto->store(VisitorIdPhoto::DIRECTORY, VisitorIdPhoto::DISK);
                            $newPhotoPaths[] = $path;

                            if ($existingVisitor->id_photo_path) {
                                $oldPhotoPaths[] = $existingVisitor->id_photo_path;
                            }

                            $visitorData['id_photo_path'] = $path;
                        }

                        $existingVisitor->update($visitorData);
                        $keptVisitorIds[] = $existingVisitor->id;

                        continue;
                    }

                    if ($newPhoto) {
                        $path = $newPhoto->store(VisitorIdPhoto::DIRECTORY, VisitorIdPhoto::DISK);
                        $newPhotoPaths[] = $path;
                        $visitorData['id_photo_path'] = $path;
                    }

                    $created = $lockedBooking->accompanyingVisitors()->create($visitorData);
                    $keptVisitorIds[] = $created->id;
                }

                foreach ($existingVisitors as $visitorId => $existingVisitor) {
                    if (in_array((int) $visitorId, $keptVisitorIds, true)) {
                        continue;
                    }

                    if ($existingVisitor->id_photo_path) {
                        $oldPhotoPaths[] = $existingVisitor->id_photo_path;
                    }

                    $existingVisitor->delete();
                }
            });
        } catch (\Throwable $e) {
            foreach ($newPhotoPaths as $path) {
                Storage::disk(VisitorIdPhoto::DISK)->delete($path);
            }

            throw $e;
        }

        foreach (array_unique($oldPhotoPaths) as $path) {
            if (! in_array($path, $newPhotoPaths, true)) {
                Storage::disk(VisitorIdPhoto::DISK)->delete($path);
            }
        }

        return redirect()->route('admin.dashboard')->with('success', 'Booking details updated.');
    }

    /**
     * Serve a visitor's privately stored ID photo to an authorized administrator.
     *
     * The EnsureAdminIsAuthenticated middleware already enforces authentication
     * and facility ownership (a facility admin cannot access another facility's
     * booking, and the {booking} route parameter is scope-checked).
     */
    public function viewIdPhoto(Booking $booking, string $visitor)
    {
        return VisitorIdPhoto::response($booking->idPhotoPathFor($visitor));
    }
}
