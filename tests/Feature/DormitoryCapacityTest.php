<?php

use App\Models\Booking;
use App\Models\Dormitory;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('private');
    Storage::fake('public');
});

function dormCapFacility(string $name = 'San Fernando City District Jail'): Facility
{
    $facility = Facility::create([
        'name' => $name,
        'max_visitors_per_hour' => 15,
    ]);

    $facility->dormitories()->create([
        'name' => 'Male Dormitory',
        'max_online_bookings_per_hour' => 7,
        'max_bookings_per_hour' => 15,
        'badge_image_path' => 'dormitory-badges/male.png',
    ]);
    $facility->dormitories()->create([
        'name' => 'Female Dormitory',
        'max_online_bookings_per_hour' => 7,
        'max_bookings_per_hour' => 15,
        'badge_image_path' => 'dormitory-badges/female.png',
    ]);

    return $facility;
}

function dormCapDormitory(Facility $facility, string $name = 'Male Dormitory'): Dormitory
{
    return $facility->dormitories()->where('name', $name)->firstOrFail();
}

function dormCapBooking(Dormitory $dormitory, string $channel, string $status = 'Pending', array $attributes = []): Booking
{
    return Booking::create(array_merge([
        'reference_code' => 'BJMP-'.Str::upper(Str::random(8)),
        'facility_id' => $dormitory->facility_id,
        'dormitory_id' => $dormitory->id,
        'booking_channel' => $channel,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Visitor',
        'visitor_sex' => 'Male',
        'visitor_age' => 30,
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'relationship_to_pdl' => 'Sibling',
        'visit_date' => today()->addDays(3)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
        'status' => $status,
    ], $attributes));
}

function dormCapOnlinePayload(Dormitory $dormitory, array $overrides = []): array
{
    return array_merge([
        'facility_id' => $dormitory->facility_id,
        'dormitory_id' => $dormitory->id,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Online Visitor',
        'visitor_sex' => 'Male',
        'visitor_age' => 30,
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'visitor_id_photo' => UploadedFile::fake()->image('id.jpg'),
        'relationship_to_pdl' => 'Sibling',
        'visit_date' => today()->addDays(3)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
        'terms_accepted' => '1',
        'privacy_consent' => '1',
    ], $overrides);
}

function dormCapWalkInPayload(Dormitory $dormitory, array $overrides = []): array
{
    return array_merge([
        'facility_id' => $dormitory->facility_id,
        'dormitory_id' => $dormitory->id,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Walk-in Visitor',
        'visitor_sex' => 'Male',
        'visitor_age' => 30,
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'relationship_to_pdl' => 'Sibling',
        'visit_date' => today()->addDays(3)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
    ], $overrides);
}

test('online bookings stop at seven per dormitory hour even when combined capacity remains', function () {
    $facility = dormCapFacility();
    $male = dormCapDormitory($facility);

    for ($i = 0; $i < 7; $i++) {
        dormCapBooking($male, Booking::CHANNEL_ONLINE);
    }

    $this->post(route('bookings.store'), dormCapOnlinePayload($male))
        ->assertSessionHasErrors('time_slot');
    expect(Booking::count())->toBe(7);

    $this->postJson(route('api.check-availability'), [
        'facility_id' => $facility->id,
        'dormitory_id' => $male->id,
        'visit_date' => today()->addDays(3)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
    ])->assertOk()
        ->assertJsonPath('available', false)
        ->assertJsonPath('current_online_bookings', 7)
        ->assertJsonPath('current_combined_bookings', 7)
        ->assertJsonPath('max_online_bookings_per_hour', 7)
        ->assertJsonPath('max_bookings_per_hour', 15);

    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.bookings.store'), dormCapWalkInPayload($male))
        ->assertRedirect(route('admin.dashboard'));

    expect(Booking::where('booking_channel', Booking::CHANNEL_WALK_IN)->count())->toBe(1);
});

test('combined capacity caps online and walk-in bookings at fifteen per dormitory hour', function () {
    $facility = dormCapFacility();
    $male = dormCapDormitory($facility);

    for ($i = 0; $i < 7; $i++) {
        dormCapBooking($male, Booking::CHANNEL_ONLINE);
    }
    for ($i = 0; $i < 8; $i++) {
        dormCapBooking($male, Booking::CHANNEL_WALK_IN, 'Approved');
    }

    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.bookings.store'), dormCapWalkInPayload($male, ['visitor_full_name' => 'Overflow Walk-in']))
        ->assertSessionHasErrors('time_slot');

    $this->post(route('bookings.store'), dormCapOnlinePayload($male, ['visitor_full_name' => 'Overflow Online']))
        ->assertSessionHasErrors('time_slot');

    expect(Booking::count())->toBe(15);
});

test('walk-in bookings fill remaining capacity without consuming the online limit', function () {
    $facility = dormCapFacility();
    $male = dormCapDormitory($facility);
    $admin = User::factory()->create();

    for ($i = 0; $i < 8; $i++) {
        $this->actingAs($admin)
            ->post(route('admin.bookings.store'), dormCapWalkInPayload($male, ['visitor_full_name' => "Walk-in {$i}"]))
            ->assertRedirect(route('admin.dashboard'));
    }

    $this->postJson(route('api.check-availability'), [
        'facility_id' => $facility->id,
        'dormitory_id' => $male->id,
        'visit_date' => today()->addDays(3)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
    ])->assertOk()
        ->assertJsonPath('available', true)
        ->assertJsonPath('current_online_bookings', 0)
        ->assertJsonPath('current_combined_bookings', 8);

    $this->post(route('bookings.store'), dormCapOnlinePayload($male))
        ->assertRedirect();

    expect(Booking::where('booking_channel', Booking::CHANNEL_ONLINE)->count())->toBe(1);
});

test('male and female dormitory capacity is counted independently', function () {
    $facility = dormCapFacility();
    $male = dormCapDormitory($facility, 'Male Dormitory');
    $female = dormCapDormitory($facility, 'Female Dormitory');

    for ($i = 0; $i < 7; $i++) {
        dormCapBooking($male, Booking::CHANNEL_ONLINE);
    }

    $this->postJson(route('api.check-availability'), [
        'facility_id' => $facility->id,
        'dormitory_id' => $female->id,
        'visit_date' => today()->addDays(3)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
    ])->assertOk()
        ->assertJsonPath('available', true)
        ->assertJsonPath('current_online_bookings', 0);

    $this->post(route('bookings.store'), dormCapOnlinePayload($female))
        ->assertRedirect();

    expect(Booking::where('dormitory_id', $female->id)->count())->toBe(1)
        ->and(Booking::where('dormitory_id', $male->id)->count())->toBe(7);
});

test('rejected bookings do not consume dormitory capacity', function () {
    $facility = dormCapFacility();
    $male = dormCapDormitory($facility);

    for ($i = 0; $i < 7; $i++) {
        dormCapBooking($male, Booking::CHANNEL_ONLINE, $i === 0 ? 'Rejected' : 'Pending');
    }

    $this->postJson(route('api.check-availability'), [
        'facility_id' => $facility->id,
        'dormitory_id' => $male->id,
        'visit_date' => today()->addDays(3)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
    ])->assertOk()
        ->assertJsonPath('available', true)
        ->assertJsonPath('current_online_bookings', 6);

    $this->post(route('bookings.store'), dormCapOnlinePayload($male))
        ->assertRedirect();

    expect(Booking::count())->toBe(8);
});

test('editing a booking cannot move it into a full dormitory slot', function () {
    $facility = dormCapFacility();
    $male = dormCapDormitory($facility);
    $male->update([
        'max_online_bookings_per_hour' => 1,
        'max_bookings_per_hour' => 1,
    ]);

    dormCapBooking($male, Booking::CHANNEL_ONLINE, 'Approved', [
        'reference_code' => 'BJMP-SLOTFULL',
        'visit_date' => today()->addDays(4)->toDateString(),
        'time_slot' => '9:00 AM - 10:00 AM',
    ]);
    $bookingToEdit = dormCapBooking($male, Booking::CHANNEL_ONLINE, 'Approved', [
        'reference_code' => 'BJMP-TOMOVE',
        'visit_date' => today()->addDays(5)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
    ]);

    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.bookings.update', $bookingToEdit), [
            'facility_id' => $facility->id,
            'dormitory_id' => $male->id,
            'pdl_name' => 'Juan Dela Cruz',
            'visitor_full_name' => $bookingToEdit->visitor_full_name,
            'visitor_sex' => 'Male',
            'visitor_age' => 30,
            'visitor_contact' => '09123456789',
            'visitor_id_type' => 'Passport',
            'visitor_id_number' => 'P1234567',
            'relationship_to_pdl' => 'Sibling',
            'visit_date' => today()->addDays(4)->toDateString(),
            'time_slot' => '9:00 AM - 10:00 AM',
        ])
        ->assertSessionHasErrors('time_slot');

    expect($bookingToEdit->fresh()->time_slot)->toBe('8:00 AM - 9:00 AM');
});

test('dormitory limits must be positive and the online limit cannot exceed the combined limit', function () {
    $facility = dormCapFacility();
    $male = dormCapDormitory($facility);
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->put(route('admin.facilities.dormitories.update', [$facility, $male]), [
            'max_online_bookings_per_hour' => 16,
            'max_bookings_per_hour' => 15,
        ])
        ->assertSessionHasErrors('max_online_bookings_per_hour');

    $this->put(route('admin.facilities.dormitories.update', [$facility, $male]), [
        'max_online_bookings_per_hour' => 0,
        'max_bookings_per_hour' => 15,
    ])->assertSessionHasErrors('max_online_bookings_per_hour');

    $this->put(route('admin.facilities.dormitories.update', [$facility, $male]), [
        'max_online_bookings_per_hour' => 7,
        'max_bookings_per_hour' => 0,
    ])->assertSessionHasErrors('max_bookings_per_hour');

    $this->put(route('admin.facilities.dormitories.update', [$facility, $male]), [
        'max_online_bookings_per_hour' => 7,
        'max_bookings_per_hour' => 15,
    ])->assertRedirect(route('admin.facilities.settings'));

    expect($male->fresh()->max_online_bookings_per_hour)->toBe(7)
        ->and($male->fresh()->max_bookings_per_hour)->toBe(15);
});

test('duplicate facility and dormitory records are prevented', function () {
    $facility = dormCapFacility();

    expect(fn () => Facility::create(['name' => 'San Fernando City District Jail', 'max_visitors_per_hour' => 15]))
        ->toThrow(QueryException::class);

    expect(fn () => $facility->dormitories()->create([
        'name' => 'Male Dormitory',
        'max_online_bookings_per_hour' => 7,
        'max_bookings_per_hour' => 15,
    ]))->toThrow(QueryException::class);

    $otherFacility = Facility::create(['name' => 'Other City Jail', 'max_visitors_per_hour' => 15]);

    $otherDormitory = $otherFacility->dormitories()->create([
        'name' => 'Male Dormitory',
        'max_online_bookings_per_hour' => 7,
        'max_bookings_per_hour' => 15,
    ]);

    expect($otherDormitory->exists)->toBeTrue();
});

test('the public booking form lists each dormitory with its badge', function () {
    $facility = dormCapFacility();

    $this->get(route('bookings.create'))
        ->assertOk()
        ->assertSeeText('Male Dormitory')
        ->assertSeeText('Female Dormitory')
        ->assertSee('name="dormitory_id"', false)
        ->assertSee('male.png', false)
        ->assertSee('female.png', false);

    expect($facility->dormitories()->count())->toBe(2);
});

test('the public booking form scopes dormitory options to their facility and resyncs restored values', function () {
    $facility = dormCapFacility();
    $otherFacility = dormCapFacility('Other City Jail');
    $male = dormCapDormitory($facility, 'Male Dormitory');
    $female = dormCapDormitory($facility, 'Female Dormitory');
    $otherDormitory = dormCapDormitory($otherFacility, 'Female Dormitory');

    $html = $this->get(route('bookings.create'))->assertOk()->getContent();

    expect($html)
        ->toContain('id="facility_id"')
        ->toContain('name="dormitory_id"')
        ->toContain('syncDormitoryOptions')
        ->toContain("window.addEventListener('pageshow'")
        ->toContain("dormitorySelect.addEventListener('focus', syncDormitoryOptions)")
        ->toContain("facilitySelect.addEventListener('focus', resetFacilityFilter)");

    preg_match('/const DORMITORIES_BY_FACILITY = (.*?);/s', $html, $matches);

    expect($matches)->not->toBeEmpty();

    $map = json_decode($matches[1], true);
    $scoped = $map[$facility->id] ?? $map[(string) $facility->id] ?? [];
    $otherScoped = $map[$otherFacility->id] ?? $map[(string) $otherFacility->id] ?? [];

    expect(array_column($scoped, 'id'))->toEqualCanonicalizing([$male->id, $female->id])
        ->and(array_column($scoped, 'name'))->toEqualCanonicalizing(['Male Dormitory', 'Female Dormitory'])
        ->and(array_column($otherScoped, 'id'))->toContain($otherDormitory->id)
        ->and(array_column($otherScoped, 'id'))->not->toContain($male->id)
        ->and(array_column($otherScoped, 'id'))->not->toContain($female->id);
});

test('bookings and availability reject a dormitory from another facility', function () {
    $facility = dormCapFacility('Facility A');
    $otherFacility = dormCapFacility('Facility B');
    $otherDormitory = dormCapDormitory($otherFacility);

    $this->post(route('bookings.store'), dormCapOnlinePayload($otherDormitory, [
        'facility_id' => $facility->id,
    ]))->assertSessionHasErrors('dormitory_id');

    $this->postJson(route('api.check-availability'), [
        'facility_id' => $facility->id,
        'dormitory_id' => $otherDormitory->id,
        'visit_date' => today()->addDays(3)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('dormitory_id');

    expect(Booking::count())->toBe(0);
});
