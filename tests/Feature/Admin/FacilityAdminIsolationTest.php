<?php

use App\Models\Booking;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function createIsolatedFacility(string $name): Facility
{
    $facility = Facility::create([
        'name' => $name,
        'max_visitors_per_hour' => 20,
    ]);

    $facility->dormitories()->create([
        'name' => 'Male Dormitory',
        'max_online_bookings_per_hour' => 7,
        'max_bookings_per_hour' => 15,
    ]);

    return $facility;
}

function createIsolatedBooking(Facility $facility, string $referenceCode): Booking
{
    return Booking::create([
        'reference_code' => $referenceCode,
        'facility_id' => $facility->id,
        'dormitory_id' => $facility->dormitories()->firstOrFail()->id,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Sample Visitor',
        'visitor_sex' => 'Male',
        'visitor_age' => 30,
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'relationship_to_pdl' => 'Sibling',
        'visit_date' => today()->addDays(5)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
        'status' => 'Pending',
    ]);
}

test('facility admins only see their facility analytics bookings calendar and settings', function () {
    $assignedFacility = createIsolatedFacility('Assigned Facility');
    $otherFacility = createIsolatedFacility('Other Facility');
    $facilityAdmin = User::factory()->create([
        'role' => 'facility_admin',
        'facility_id' => $assignedFacility->id,
    ]);
    $assignedBooking = createIsolatedBooking($assignedFacility, 'BJMP-LOCAL001');
    createIsolatedBooking($otherFacility, 'BJMP-FOREIGN1');

    $this->actingAs($facilityAdmin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSeeText('BJMP-LOCAL001')
        ->assertDontSee('BJMP-FOREIGN1')
        ->assertViewHas('analytics', fn (array $analytics): bool => $analytics['total'] === 1)
        ->assertViewHas('bookings', fn ($bookings): bool => $bookings->total() === 1);

    $this->get(route('admin.calendar', ['month' => $assignedBooking->visit_date->format('Y-m')]))
        ->assertOk()
        ->assertSee('BJMP-LOCAL001')
        ->assertDontSee('BJMP-FOREIGN1');

    $this->get(route('admin.facilities.settings'))
        ->assertOk()
        ->assertSeeText('Assigned Facility')
        ->assertDontSee('Other Facility');
});

test('facility admins cannot access another facility booking or global admin accounts', function () {
    $assignedFacility = createIsolatedFacility('Assigned Facility');
    $otherFacility = createIsolatedFacility('Other Facility');
    $facilityAdmin = User::factory()->create([
        'role' => 'facility_admin',
        'facility_id' => $assignedFacility->id,
    ]);
    $otherBooking = createIsolatedBooking($otherFacility, 'BJMP-FOREIGN2');

    $this->actingAs($facilityAdmin)
        ->get(route('admin.bookings.edit', $otherBooking))
        ->assertNotFound();

    $this->post(route('admin.bookings.status.update', $otherBooking), [
        'status' => 'Approved',
        'reason' => 'Attempted cross-facility status update.',
    ])->assertNotFound();

    $this->put(route('admin.bookings.update', $otherBooking), [
        'facility_id' => $assignedFacility->id,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Tampered Visitor',
        'visitor_sex' => 'Male',
        'visitor_age' => 30,
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'relationship_to_pdl' => 'Sibling',
        'visit_date' => today()->addDays(8)->toDateString(),
        'time_slot' => '9:00 AM - 10:00 AM',
    ])->assertNotFound();

    $this->get(route('admin.users.index'))->assertForbidden();
    $this->put(route('admin.facilities.dormitories.update', [$otherFacility, $otherFacility->dormitories()->firstOrFail()]), [
        'max_online_bookings_per_hour' => 2,
        'max_bookings_per_hour' => 2,
    ])->assertNotFound();

    $this->patch(route('admin.facilities.branding.update', $otherFacility), [
        'regional_office' => 'Attempted unauthorized update',
    ])->assertNotFound();
});

test('super admins can view booking data across all facilities', function () {
    $firstFacility = createIsolatedFacility('First Facility');
    $secondFacility = createIsolatedFacility('Second Facility');
    $superAdmin = User::factory()->create(['role' => 'super_admin']);
    createIsolatedBooking($firstFacility, 'BJMP-GLOBAL01');
    createIsolatedBooking($secondFacility, 'BJMP-GLOBAL02');

    $this->actingAs($superAdmin)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('BJMP-GLOBAL01')
        ->assertSee('BJMP-GLOBAL02')
        ->assertViewHas('analytics', fn (array $analytics): bool => $analytics['total'] === 2);
});
