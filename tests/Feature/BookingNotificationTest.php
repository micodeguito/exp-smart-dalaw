<?php

use App\Mail\BookingConfirmation;
use App\Mail\BookingStatusChanged;
use App\Models\Booking;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('private');
});

function notificationFacility(
    int $maxOnlineBookings = 7,
    int $maxCombinedBookings = 15,
    string $name = 'San Fernando City District Jail',
): Facility {
    $facility = Facility::create([
        'name' => $name,
        'max_visitors_per_hour' => $maxCombinedBookings,
        'regional_office' => 'Region III',
    ]);

    $facility->dormitories()->create([
        'name' => 'Male Dormitory',
        'max_online_bookings_per_hour' => $maxOnlineBookings,
        'max_bookings_per_hour' => $maxCombinedBookings,
    ]);

    return $facility;
}

function notificationBooking(Facility $facility, array $attributes = []): Booking
{
    return Booking::create(array_merge([
        'reference_code' => 'BJMP-'.Str::upper(Str::random(8)),
        'facility_id' => $facility->id,
        'dormitory_id' => $facility->dormitories()->firstOrFail()->id,
        'booking_channel' => Booking::CHANNEL_ONLINE,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Maria Santos',
        'visitor_email' => 'maria@example.com',
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'visitor_id_photo_path' => 'visitor-id-photos/private-primary.jpg',
        'relationship_to_pdl' => 'Spouse',
        'visit_date' => today()->addDays(3)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
        'status' => 'Pending',
    ], $attributes));
}

function notificationBookingData(Facility $facility, array $overrides = []): array
{
    return array_merge([
        'facility_id' => $facility->id,
        'dormitory_id' => $facility->dormitories()->firstOrFail()->id,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Maria Santos',
        'visitor_email' => 'visitor@example.com',
        'visitor_contact' => '09123456789',
        'visitor_sex' => 'Female',
        'visitor_age' => 30,
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'visitor_id_photo' => UploadedFile::fake()->image('id.jpg'),
        'relationship_to_pdl' => 'Spouse',
        'visit_date' => today()->addDays(4)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
        'terms_accepted' => '1',
        'privacy_consent' => '1',
        'accompanying_visitors' => [],
    ], $overrides);
}

test('a valid visitor email is saved and receives a booking confirmation', function () {
    Mail::fake();
    $facility = notificationFacility();

    $this->post(route('bookings.store'), notificationBookingData($facility))
        ->assertRedirect();

    $booking = Booking::query()->sole();

    expect($booking->visitor_email)->toBe('visitor@example.com');

    Mail::assertSent(BookingConfirmation::class, function (BookingConfirmation $mail) use ($booking, $facility) {
        $html = $mail->render();

        return $mail->hasTo('visitor@example.com')
            && str_contains($html, $booking->reference_code)
            && str_contains($html, $facility->name)
            && str_contains($html, 'Male Dormitory')
            && str_contains($html, $booking->visit_date->format('M d, Y'))
            && str_contains($html, '8:00 AM - 9:00 AM')
            && str_contains($html, 'Pending')
            && str_contains($html, route('bookings.track'))
            && str_contains($html, 'Bureau of Jail Management and Penology')
            && str_contains($html, 'images/bjmp-logo.png')
            && ! str_contains($html, 'visitor-id-photos')
            && ! str_contains($html, (string) $booking->visitor_id_photo_path);
    });
});

test('an invalid visitor email is rejected before a booking is created', function () {
    Mail::fake();
    $facility = notificationFacility();

    $this->post(route('bookings.store'), notificationBookingData($facility, [
        'visitor_email' => 'not-a-valid-email',
    ]))->assertSessionHasErrors('visitor_email');

    expect(Booking::count())->toBe(0);
    Mail::assertNothingSent();
});

test('approving a pending or rejected booking sends an approval notification', function (string $fromStatus) {
    Mail::fake();
    $admin = User::factory()->create();
    $facility = notificationFacility();
    $booking = notificationBooking($facility, ['status' => $fromStatus]);

    $this->actingAs($admin)
        ->post(route('admin.bookings.status.update', $booking), ['status' => 'Approved'])
        ->assertRedirect(route('admin.dashboard'));

    expect($booking->fresh()->status)->toBe('Approved');

    Mail::assertSent(BookingStatusChanged::class, function (BookingStatusChanged $mail) use ($booking, $fromStatus) {
        $html = $mail->render();

        return $mail->hasTo('maria@example.com')
            && $mail->previousStatus === $fromStatus
            && str_contains($html, 'Your Booking Has Been Approved')
            && str_contains($html, $booking->reference_code)
            && str_contains($html, 'San Fernando City District Jail')
            && str_contains($html, 'Male Dormitory')
            && str_contains($html, $booking->visit_date->format('M d, Y'))
            && str_contains($html, '8:00 AM - 9:00 AM')
            && str_contains($html, 'Approved')
            && str_contains($html, 'Before your visit')
            && str_contains($html, route('bookings.track'))
            && ! str_contains($html, 'visitor-id-photos');
    });
})->with(['Pending', 'Rejected']);

test('rejecting a booking sends the officer recorded reason', function () {
    Mail::fake();
    $admin = User::factory()->create();
    $facility = notificationFacility();
    $booking = notificationBooking($facility, ['status' => 'Approved']);
    $reason = 'The submitted ID could not be verified.';

    $this->actingAs($admin)
        ->post(route('admin.bookings.status.update', $booking), [
            'status' => 'Rejected',
            'reason' => $reason,
        ])
        ->assertRedirect(route('admin.dashboard'));

    expect($booking->fresh()->status)->toBe('Rejected');

    Mail::assertSent(BookingStatusChanged::class, function (BookingStatusChanged $mail) use ($booking, $reason) {
        $html = $mail->render();

        return $mail->hasTo('maria@example.com')
            && $mail->rejectionReason === $reason
            && str_contains($html, 'Your Booking Has Been Rejected')
            && str_contains($html, 'Reason for rejection')
            && str_contains($html, $reason)
            && str_contains($html, $booking->reference_code)
            && str_contains($html, $booking->visit_date->format('M d, Y'))
            && str_contains($html, '8:00 AM - 9:00 AM')
            && str_contains($html, 'Rejected')
            && str_contains($html, route('bookings.track'));
    });
});

test('changing a booking to pending does not send a decision notification', function () {
    Mail::fake();
    $admin = User::factory()->create();
    $facility = notificationFacility();
    $booking = notificationBooking($facility, ['status' => 'Approved']);

    $this->actingAs($admin)
        ->post(route('admin.bookings.status.update', $booking), [
            'status' => 'Pending',
            'reason' => 'Returning the request for corrected details.',
        ])
        ->assertRedirect(route('admin.dashboard'));

    expect($booking->fresh()->status)->toBe('Pending');
    Mail::assertNothingSent();
});

test('saving the same status again does not send a duplicate notification', function () {
    Mail::fake();
    $admin = User::factory()->create();
    $facility = notificationFacility();
    $booking = notificationBooking($facility, ['status' => 'Approved']);

    $this->actingAs($admin)
        ->post(route('admin.bookings.status.update', $booking), ['status' => 'Approved'])
        ->assertSessionHasErrors('status');

    expect($booking->fresh()->status)->toBe('Approved')
        ->and($booking->statusHistory()->count())->toBe(0);

    Mail::assertNothingSent();
});

test('failed validation and rolled back status changes do not send notifications', function () {
    Mail::fake();
    $admin = User::factory()->create();

    $facility = notificationFacility();
    $approved = notificationBooking($facility, ['status' => 'Approved']);

    $this->actingAs($admin)
        ->post(route('admin.bookings.status.update', $approved), ['status' => 'Rejected'])
        ->assertSessionHasErrors('reason');

    expect($approved->fresh()->status)->toBe('Approved');
    Mail::assertNothingSent();

    $fullFacility = notificationFacility(1, 1, 'Full Capacity Jail');
    $rejected = notificationBooking($fullFacility, [
        'status' => 'Rejected',
        'visit_date' => today()->addDays(5)->toDateString(),
    ]);
    notificationBooking($fullFacility, [
        'status' => 'Pending',
        'visit_date' => today()->addDays(5)->toDateString(),
    ]);

    $this->post(route('admin.bookings.status.update', $rejected), [
        'status' => 'Approved',
        'reason' => 'Documents were corrected.',
    ])->assertSessionHasErrors('status');

    expect($rejected->fresh()->status)->toBe('Rejected');
    Mail::assertNothingSent();
});

test('mail failures are logged without changing a saved status or exposing personal data', function () {
    $admin = User::factory()->create();
    $facility = notificationFacility();
    $booking = notificationBooking($facility, ['status' => 'Pending']);

    Log::spy();
    Mail::shouldReceive('to')
        ->once()
        ->andThrow(new RuntimeException('SMTP host is not configured.'));

    $this->actingAs($admin)
        ->post(route('admin.bookings.status.update', $booking), ['status' => 'Approved'])
        ->assertRedirect(route('admin.dashboard'));

    expect($booking->fresh()->status)->toBe('Approved');

    Log::shouldHaveReceived('error')
        ->withArgs(function (string $message, array $context = []) use ($booking) {
            return $message === 'Booking status-change email failed'
                && ($context['reference_code'] ?? null) === $booking->reference_code
                && ($context['mailer'] ?? null) === config('mail.default')
                && ($context['error'] ?? null) === 'SMTP host is not configured.'
                && ! array_key_exists('visitor_email', $context)
                && ! array_key_exists('visitor_id_photo_path', $context);
        })
        ->once();
});
