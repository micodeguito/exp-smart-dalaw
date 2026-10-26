<?php

use App\Models\Booking;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('private');
});

function createConsentFacility(string $name = 'Consent Test Facility'): array
{
    $facility = Facility::create([
        'name' => $name,
        'max_visitors_per_hour' => 20,
    ]);

    $dormitory = $facility->dormitories()->create([
        'name' => $name.' Dormitory',
        'max_online_bookings_per_hour' => 7,
        'max_bookings_per_hour' => 15,
    ]);

    return [$facility, $dormitory];
}

test('booking cannot be submitted unless both agreements are accepted', function () {
    [$facility, $dormitory] = createConsentFacility();

    $this->post(route('bookings.store'), [
        'facility_id' => $facility->id,
        'dormitory_id' => $dormitory->id,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Visitor Name',
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'relationship_to_pdl' => 'Sibling',
        'visit_date' => today()->addDays(4)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
    ])->assertSessionHasErrors(['terms_accepted', 'privacy_consent']);

    expect(Booking::count())->toBe(0);
});

test('booking stores consent status timestamps and privacy notice version', function () {
    [$facility, $dormitory] = createConsentFacility();

    $this->post(route('bookings.store'), [
        'facility_id' => $facility->id,
        'dormitory_id' => $dormitory->id,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Visitor Name',
        'visitor_sex' => 'Male',
        'visitor_age' => 30,
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'visitor_id_photo' => UploadedFile::fake()->image('id.jpg'),
        'relationship_to_pdl' => 'Sibling',
        'visit_date' => today()->addDays(4)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
        'terms_accepted' => '1',
        'privacy_consent' => '1',
    ])->assertRedirect();

    $booking = Booking::where('visitor_full_name', 'Visitor Name')->firstOrFail();

    expect($booking->terms_accepted)->toBeTrue()
        ->and($booking->privacy_consent)->toBeTrue()
        ->and($booking->terms_accepted_at)->not->toBeNull()
        ->and($booking->privacy_consent_at)->not->toBeNull()
        ->and($booking->privacy_notice_version)->toBe('2026-09-28-v1');
});

test('booking form provides mandatory agreement controls and privacy notice', function () {
    [$facility] = createConsentFacility();

    $this->get(route('bookings.create'))
        ->assertOk()
        ->assertSee('name="terms_accepted"', false)
        ->assertSee('name="privacy_consent"', false)
        ->assertSeeText('Data Privacy Agreement')
        ->assertSeeText('Privacy Notice');
});

test('booking rejects a PDL assigned to a different facility', function () {
    [$selectedFacility, $selectedDormitory] = createConsentFacility('Selected Consent Facility');
    createConsentFacility('Other Consent Facility');

    $this->post(route('bookings.store'), [
        'facility_id' => $selectedFacility->id,
        'dormitory_id' => $selectedDormitory->id,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Visitor Name',
        'visitor_sex' => 'Male',
        'visitor_age' => 30,
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'visitor_id_photo' => UploadedFile::fake()->image('id.jpg'),
        'relationship_to_pdl' => 'Sibling',
        'visit_date' => today()->addDays(4)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
        'terms_accepted' => '1',
        'privacy_consent' => '1',
    ])->assertRedirect();

    expect(Booking::count())->toBe(1);
});

test('facility admins browse and book any facility through the public portal', function () {
    [$assignedFacility] = createConsentFacility('Assigned Consent Facility');
    [$otherFacility, $otherDormitory] = createConsentFacility('Other Consent Facility');
    $facilityAdmin = User::factory()->create([
        'role' => User::ROLE_FACILITY_ADMIN,
        'facility_id' => $assignedFacility->id,
    ]);

    $this->actingAs($facilityAdmin)
        ->get(route('bookings.create'))
        ->assertOk()
        ->assertSee('value="'.$assignedFacility->id.'" data-badge-url', false)
        ->assertSee('value="'.$otherFacility->id.'" data-badge-url', false);

    $this->postJson(route('api.check-availability'), [
        'facility_id' => $otherFacility->id,
        'dormitory_id' => $otherDormitory->id,
        'visit_date' => today()->addDays(4)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
    ])->assertOk()
        ->assertJsonPath('available', true);

    $this->post(route('bookings.store'), [
        'facility_id' => $otherFacility->id,
        'dormitory_id' => $otherDormitory->id,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Visitor Name',
        'visitor_sex' => 'Male',
        'visitor_age' => 30,
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'visitor_id_photo' => UploadedFile::fake()->image('id.jpg'),
        'relationship_to_pdl' => 'Sibling',
        'visit_date' => today()->addDays(4)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
        'terms_accepted' => '1',
        'privacy_consent' => '1',
    ])->assertRedirect();

    expect(Booking::count())->toBe(1)
        ->and(Booking::first()->facility_id)->toBe($otherFacility->id);

    $referenceCode = Booking::first()->reference_code;

    $this->get(route('booking.success', $referenceCode))
        ->assertOk()
        ->assertSeeText($referenceCode);

    $this->post(route('bookings.track.search'), ['reference_code' => $referenceCode])
        ->assertOk()
        ->assertSeeText($referenceCode);

    $this->get(route('qr.redirect', $referenceCode))
        ->assertRedirect(route('booking.success', $referenceCode));
});
