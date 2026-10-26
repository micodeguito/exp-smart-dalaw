<?php

use App\Models\Booking;
use App\Models\Facility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('private');
});

test('hourly dormitory capacity is checked per selected slot and prevents overbooking', function () {
    $facility = Facility::create([
        'name' => 'Hourly Capacity Jail',
        'max_visitors_per_hour' => 1,
    ]);
    $dormitory = $facility->dormitories()->create([
        'name' => 'Male Dormitory',
        'max_online_bookings_per_hour' => 1,
        'max_bookings_per_hour' => 15,
    ]);

    Booking::create([
        'reference_code' => 'BJMP-CAPACITY',
        'facility_id' => $facility->id,
        'dormitory_id' => $dormitory->id,
        'booking_channel' => Booking::CHANNEL_ONLINE,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'First Visitor',
        'visitor_sex' => 'Male',
        'visitor_age' => 30,
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'relationship_to_pdl' => 'Spouse',
        'visit_date' => today()->addDays(3)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
        'status' => 'Pending',
    ]);

    $this->postJson(route('api.check-availability'), [
        'facility_id' => $facility->id,
        'dormitory_id' => $dormitory->id,
        'visit_date' => today()->addDays(3)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
    ])->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('max_online_bookings_per_hour', 1)
        ->assertJsonPath('max_bookings_per_hour', 15);

    $this->postJson(route('api.check-availability'), [
        'facility_id' => $facility->id,
        'dormitory_id' => $dormitory->id,
        'visit_date' => today()->addDays(3)->toDateString(),
        'time_slot' => '9:00 AM - 10:00 AM',
    ])->assertOk()
        ->assertJsonPath('available', true);

    $this->post(route('bookings.store'), [
        'facility_id' => $facility->id,
        'dormitory_id' => $dormitory->id,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Second Visitor',
        'visitor_sex' => 'Female',
        'visitor_age' => 25,
        'visitor_contact' => '09987654321',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P7654321',
        'visitor_id_photo' => UploadedFile::fake()->image('id.jpg'),
        'relationship_to_pdl' => 'Sibling',
        'visit_date' => today()->addDays(3)->toDateString(),
        'time_slot' => '9:00 AM - 10:00 AM',
        'terms_accepted' => '1',
        'privacy_consent' => '1',
    ])->assertRedirect();

    $secondBooking = Booking::where('visitor_full_name', 'Second Visitor')->firstOrFail();
    $this->assertDatabaseHas('booking_status_histories', [
        'booking_id' => $secondBooking->id,
        'from_status' => null,
        'to_status' => 'Pending',
        'reason' => 'Booking request submitted.',
    ]);

    $this->post(route('bookings.store'), [
        'facility_id' => $facility->id,
        'dormitory_id' => $dormitory->id,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Third Visitor',
        'visitor_sex' => 'Male',
        'visitor_age' => 35,
        'visitor_contact' => '09876543210',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P9999999',
        'visitor_id_photo' => UploadedFile::fake()->image('id.jpg'),
        'relationship_to_pdl' => 'Sibling',
        'visit_date' => today()->addDays(3)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
        'terms_accepted' => '1',
        'privacy_consent' => '1',
    ])->assertSessionHasErrors('time_slot');

    expect(Booking::count())->toBe(2);
});
