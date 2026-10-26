<?php

use App\Models\Booking;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->withoutVite();
});

function createAdminManagementBooking(Facility $facility, array $attributes = []): Booking
{
    return Booking::create(array_merge([
        'reference_code' => 'BJMP-'.Str::upper(Str::random(8)),
        'facility_id' => $facility->id,
        'dormitory_id' => $facility->dormitories()->firstOrFail()->id,
        'pdl_name' => 'Juan Dela Cruz',
        'visitor_full_name' => 'Sample Visitor',
        'visitor_sex' => 'Male',
        'visitor_age' => 30,
        'visitor_email' => 'visitor@example.com',
        'visitor_contact' => '09123456789',
        'visitor_id_type' => 'Passport',
        'visitor_id_number' => 'P1234567',
        'relationship_to_pdl' => 'Spouse',
        'visit_date' => now()->addDays(5)->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
        'status' => 'Pending',
    ], $attributes));
}

function createAdminManagementFacility(int $maxOnlineBookings = 5, int $maxCombinedBookings = 15): Facility
{
    $facility = Facility::create([
        'name' => 'Test City Jail',
        'max_visitors_per_hour' => $maxCombinedBookings,
    ]);

    $facility->dormitories()->create([
        'name' => 'Male Dormitory',
        'max_online_bookings_per_hour' => $maxOnlineBookings,
        'max_bookings_per_hour' => $maxCombinedBookings,
    ]);

    return $facility;
}

test('admin dashboard summarizes booking activity', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();

    createAdminManagementBooking($facility, [
        'visit_date' => today()->toDateString(),
        'status' => 'Pending',
    ]);
    createAdminManagementBooking($facility, ['status' => 'Approved']);
    createAdminManagementBooking($facility, ['status' => 'Rejected']);

    $response = $this->actingAs($admin)->get(route('admin.dashboard'));

    $response->assertOk()
        ->assertSeeText('Total bookings')
        ->assertSeeText('Visits today')
        ->assertSeeText('Change status')
        ->assertSee('name="reason"', false)
        ->assertViewHas('analytics', fn (array $analytics): bool => $analytics['total'] === 3
            && $analytics['pending'] === 1
            && $analytics['approved'] === 1
            && $analytics['rejected'] === 1
            && $analytics['today'] === 1);
});

test('admin management routes redirect guests to login', function () {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

test('admins can create edit and delete admin accounts', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();

    $this->actingAs($admin)
        ->get(route('admin.users.index'))
        ->assertOk()
        ->assertSeeText($admin->email);

    $this->get(route('admin.users.create'))
        ->assertOk()
        ->assertSeeText('Create account');

    $this->actingAs($admin)
        ->post(route('admin.users.store'), [
            'name' => 'Second Admin',
            'email' => 'second-admin@example.com',
            'password' => 'secure-password',
            'password_confirmation' => 'secure-password',
            'role' => 'facility_admin',
            'facility_id' => $facility->id,
            'dormitory_id' => $facility->dormitories()->firstOrFail()->id,
        ])
        ->assertRedirect(route('admin.users.index'));

    $managedUser = User::where('email', 'second-admin@example.com')->firstOrFail();
    expect($managedUser->role)->toBe(User::ROLE_FACILITY_ADMIN)
        ->and($managedUser->facility_id)->toBe($facility->id);

    $this->get(route('admin.users.edit', $managedUser))
        ->assertOk()
        ->assertSeeText('Edit admin account');

    $this->put(route('admin.users.update', $managedUser), [
        'name' => 'Updated Admin',
        'email' => 'updated-admin@example.com',
        'role' => 'super_admin',
        'password' => '',
        'password_confirmation' => '',
    ])->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseHas('users', [
        'id' => $managedUser->id,
        'name' => 'Updated Admin',
        'email' => 'updated-admin@example.com',
    ]);

    $this->delete(route('admin.users.destroy', $managedUser))
        ->assertRedirect(route('admin.users.index'));

    $this->assertDatabaseMissing('users', ['id' => $managedUser->id]);
});

test('admins cannot delete their own account', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.users.destroy', $admin))
        ->assertRedirect(route('admin.users.index'))
        ->assertSessionHas('error');

    $this->assertDatabaseHas('users', ['id' => $admin->id]);
});

test('admins must explain status changes and each reason is kept in the booking history', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();
    $booking = createAdminManagementBooking($facility);

    $this->actingAs($admin)
        ->post(route('admin.bookings.status.update', $booking), [
            'status' => 'Approved',
            'reason' => 'Visitor identity verified.',
        ])
        ->assertRedirect(route('admin.dashboard'));

    $this->post(route('admin.bookings.status.update', $booking), [
        'status' => 'Rejected',
        'reason' => 'Facility inspection requires a reschedule.',
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertDatabaseHas('booking_status_histories', [
        'booking_id' => $booking->id,
        'user_id' => $admin->id,
        'from_status' => 'Pending',
        'to_status' => 'Approved',
        'reason' => 'Visitor identity verified.',
    ]);

    $this->assertDatabaseHas('booking_status_histories', [
        'booking_id' => $booking->id,
        'user_id' => $admin->id,
        'from_status' => 'Approved',
        'to_status' => 'Rejected',
        'reason' => 'Facility inspection requires a reschedule.',
    ]);

    $this->post(route('bookings.track.search'), ['reference_code' => $booking->reference_code])
        ->assertOk()
        ->assertSeeText('Status history')
        ->assertSeeText('Visitor identity verified.')
        ->assertSeeText('Facility inspection requires a reschedule.');
});

test('approved status changes do not require a reason', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();

    $pendingBooking = createAdminManagementBooking($facility, ['status' => 'Pending']);

    $this->actingAs($admin)
        ->post(route('admin.bookings.status.update', $pendingBooking), ['status' => 'Approved'])
        ->assertRedirect(route('admin.dashboard'));

    expect($pendingBooking->fresh()->status)->toBe('Approved')
        ->and($pendingBooking->fresh()->admin_remarks)->toBeNull();

    $this->assertDatabaseHas('booking_status_histories', [
        'booking_id' => $pendingBooking->id,
        'from_status' => 'Pending',
        'to_status' => 'Approved',
        'reason' => 'Status changed to Approved.',
    ]);

    $rejectedBooking = createAdminManagementBooking($facility, [
        'status' => 'Rejected',
        'admin_remarks' => 'Previous rejection reason.',
    ]);
    $rejectedBooking->statusHistory()->create([
        'user_id' => $admin->id,
        'from_status' => 'Pending',
        'to_status' => 'Rejected',
        'reason' => 'Previous rejection reason.',
    ]);

    $this->post(route('admin.bookings.status.update', $rejectedBooking), ['status' => 'Approved'])
        ->assertRedirect(route('admin.dashboard'));

    expect($rejectedBooking->fresh()->status)->toBe('Approved')
        ->and($rejectedBooking->fresh()->admin_remarks)->toBeNull();

    $this->assertDatabaseHas('booking_status_histories', [
        'booking_id' => $rejectedBooking->id,
        'from_status' => 'Rejected',
        'to_status' => 'Approved',
        'reason' => 'Status changed to Approved.',
    ]);
    $this->assertDatabaseHas('booking_status_histories', [
        'booking_id' => $rejectedBooking->id,
        'from_status' => 'Pending',
        'to_status' => 'Rejected',
        'reason' => 'Previous rejection reason.',
    ]);
});

test('pending and rejected status changes require a reason', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();

    $approvedBooking = createAdminManagementBooking($facility, ['status' => 'Approved']);
    $pendingBooking = createAdminManagementBooking($facility, ['status' => 'Pending']);

    $this->actingAs($admin)
        ->post(route('admin.bookings.status.update', $approvedBooking), ['status' => 'Pending'])
        ->assertSessionHasErrors('reason');

    $this->post(route('admin.bookings.status.update', $approvedBooking), ['status' => 'Rejected'])
        ->assertSessionHasErrors('reason');

    $this->post(route('admin.bookings.status.update', $pendingBooking), ['status' => 'Rejected'])
        ->assertSessionHasErrors('reason');

    $this->post(route('admin.bookings.status.update', $approvedBooking), [
        'status' => 'Rejected',
        'reason' => '   ',
    ])->assertSessionHasErrors('reason');

    expect($approvedBooking->fresh()->status)->toBe('Approved')
        ->and($pendingBooking->fresh()->status)->toBe('Pending')
        ->and($approvedBooking->statusHistory()->count())->toBe(0)
        ->and($pendingBooking->statusHistory()->count())->toBe(0);
});

test('pending and rejected status changes succeed when a reason is provided', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();
    $booking = createAdminManagementBooking($facility, ['status' => 'Approved']);

    $this->actingAs($admin)
        ->post(route('admin.bookings.status.update', $booking), [
            'status' => 'Pending',
            'reason' => 'Returning the request for corrected visitor details.',
        ])
        ->assertRedirect(route('admin.dashboard'));

    $this->post(route('admin.bookings.status.update', $booking), [
        'status' => 'Rejected',
        'reason' => 'The submitted ID could not be verified.',
    ])->assertRedirect(route('admin.dashboard'));

    expect($booking->fresh()->status)->toBe('Rejected')
        ->and($booking->fresh()->admin_remarks)->toBe('The submitted ID could not be verified.');

    $this->assertDatabaseHas('booking_status_histories', [
        'booking_id' => $booking->id,
        'from_status' => 'Approved',
        'to_status' => 'Pending',
        'reason' => 'Returning the request for corrected visitor details.',
    ]);
    $this->assertDatabaseHas('booking_status_histories', [
        'booking_id' => $booking->id,
        'from_status' => 'Pending',
        'to_status' => 'Rejected',
        'reason' => 'The submitted ID could not be verified.',
    ]);
});

test('status reason rules are enforced without frontend validation', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();
    $booking = createAdminManagementBooking($facility, ['status' => 'Pending']);

    $this->actingAs($admin)
        ->postJson(route('admin.bookings.status.update', $booking), ['status' => 'Rejected'])
        ->assertStatus(422)
        ->assertJsonValidationErrors('reason');

    expect($booking->fresh()->status)->toBe('Pending')
        ->and($booking->statusHistory()->count())->toBe(0);

    $this->postJson(route('admin.bookings.status.update', $booking), ['status' => 'Approved'])
        ->assertRedirect(route('admin.dashboard'));

    expect($booking->fresh()->status)->toBe('Approved');
});

test('rejected bookings cannot be reactivated into a full hourly slot', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility(1, 1);
    $rejectedBooking = createAdminManagementBooking($facility, [
        'status' => 'Rejected',
        'visit_date' => today()->addDays(5)->toDateString(),
    ]);
    createAdminManagementBooking($facility, [
        'status' => 'Pending',
        'visit_date' => today()->addDays(5)->toDateString(),
    ]);

    $this->actingAs($admin)
        ->post(route('admin.bookings.status.update', $rejectedBooking), [
            'status' => 'Approved',
            'reason' => 'The visitor provided corrected documents.',
        ])
        ->assertSessionHasErrors('status');

    expect($rejectedBooking->fresh()->status)->toBe('Rejected');
});

test('admins can set the online and combined limits per dormitory', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();
    $dormitory = $facility->dormitories()->firstOrFail();

    $this->actingAs($admin)
        ->get(route('admin.facilities.settings'))
        ->assertOk()
        ->assertSeeText('Online bookings per hour')
        ->assertSeeText('Total bookings per hour');

    $this->put(route('admin.facilities.dormitories.update', [$facility, $dormitory]), [
        'max_online_bookings_per_hour' => 7,
        'max_bookings_per_hour' => 15,
    ])->assertRedirect(route('admin.facilities.settings'));

    $this->assertDatabaseHas('dormitories', [
        'id' => $dormitory->id,
        'max_online_bookings_per_hour' => 7,
        'max_bookings_per_hour' => 15,
    ]);
});

test('admins can save regional unit naming and an approved facility badge', function () {
    Storage::fake('public');
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();

    $this->actingAs($admin)
        ->patch(route('admin.facilities.branding.update', $facility), [
            'regional_office' => 'Region III',
            'jail_unit_name' => 'Mabalacat City Jail',
            'badge_image' => UploadedFile::fake()->image('facility-badge.png', 240, 240),
        ])
        ->assertRedirect(route('admin.facilities.settings'));

    $facility->refresh();
    expect($facility->regional_office)->toBe('Region III')
        ->and($facility->jail_unit_name)->toBe('Mabalacat City Jail')
        ->and($facility->badge_image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($facility->badge_image_path);
});

test('admins can edit a booking without changing its approval status', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();
    $booking = createAdminManagementBooking($facility, ['status' => 'Approved']);

    $this->actingAs($admin)
        ->get(route('admin.bookings.edit', $booking))
        ->assertOk()
        ->assertSeeText($booking->reference_code);

    $this->actingAs($admin)
        ->put(route('admin.bookings.update', $booking), [
            'facility_id' => $facility->id,
            'dormitory_id' => $facility->dormitories()->firstOrFail()->id,
            'pdl_name' => 'Juan Dela Cruz',
            'visitor_full_name' => 'Updated Visitor',
            'visitor_sex' => 'Male',
            'visitor_age' => 30,
            'visitor_email' => 'updated@example.com',
            'visitor_contact' => '09987654321',
            'visitor_id_type' => 'Driver\'s License',
            'visitor_id_number' => 'DL-123456',
            'relationship_to_pdl' => 'Sibling',
            'visit_date' => today()->addDays(8)->toDateString(),
            'time_slot' => '9:00 AM - 10:00 AM',
        ])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertDatabaseHas('bookings', [
        'id' => $booking->id,
        'visitor_full_name' => 'Updated Visitor',
        'status' => 'Approved',
        'time_slot' => '9:00 AM - 10:00 AM',
    ]);
});

test('booking edits cannot exceed facility slot capacity', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility(1, 1);
    $bookingToEdit = createAdminManagementBooking($facility, [
        'visit_date' => today()->addDays(5)->toDateString(),
    ]);
    createAdminManagementBooking($facility, [
        'visit_date' => today()->addDays(6)->toDateString(),
        'time_slot' => '9:00 AM - 10:00 AM',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.bookings.update', $bookingToEdit), [
            'facility_id' => $facility->id,
            'dormitory_id' => $facility->dormitories()->firstOrFail()->id,
            'pdl_name' => 'Juan Dela Cruz',
            'visitor_full_name' => 'Sample Visitor',
            'visitor_sex' => 'Male',
            'visitor_age' => 30,
            'visitor_email' => 'visitor@example.com',
            'visitor_contact' => '09123456789',
            'visitor_id_type' => 'Passport',
            'visitor_id_number' => 'P1234567',
            'relationship_to_pdl' => 'Spouse',
            'visit_date' => today()->addDays(6)->toDateString(),
            'time_slot' => '9:00 AM - 10:00 AM',
        ])
        ->assertSessionHasErrors('time_slot');

    expect($bookingToEdit->fresh()->visit_date->toDateString())
        ->toBe(today()->addDays(5)->toDateString());
});

test('visit calendar shows all bookings in the selected month', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();
    createAdminManagementBooking($facility, [
        'reference_code' => 'BJMP-OCT001',
        'visit_date' => '2026-10-08',
    ]);
    createAdminManagementBooking($facility, [
        'reference_code' => 'BJMP-OCT002',
        'visit_date' => '2026-10-08',
        'time_slot' => '9:00 AM - 10:00 AM',
    ]);
    createAdminManagementBooking($facility, [
        'reference_code' => 'BJMP-NOV001',
        'visit_date' => '2026-11-04',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.calendar', ['month' => '2026-10']))
        ->assertOk()
        ->assertSee('BJMP-OCT001')
        ->assertSee('BJMP-OCT002')
        ->assertDontSee('BJMP-NOV001');
});

test('admin can view walk-in booking creation form', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();

    $this->actingAs($admin)
        ->get(route('admin.bookings.create'))
        ->assertOk()
        ->assertSeeText('Add Walk-in Visitor')
        ->assertSeeText('Register a visitor who arrived at the facility without a prior booking')
        ->assertSee($facility->name)
        ->assertSee('<select name="accompanying_visitors[${index}][id_type]"', false)
        ->assertSee('<option value="PhilSys National ID">PhilSys National ID</option>', false)
        ->assertSee('<select name="accompanying_visitors[${index}][relationship_to_pdl]"', false)
        ->assertSee('<option value="Legal Counsel">Legal Counsel</option>', false)
        ->assertDontSee('<input type="text" name="accompanying_visitors[${index}][id_type]"', false)
        ->assertDontSee('<input type="text" name="accompanying_visitors[${index}][relationship_to_pdl]"', false);
});

test('admin can create a walk-in booking', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();

    $this->actingAs($admin)
        ->post(route('admin.bookings.store'), [
            'facility_id' => $facility->id,
            'dormitory_id' => $facility->dormitories()->firstOrFail()->id,
            'pdl_name' => 'Juan Dela Cruz',
            'visitor_full_name' => 'Walk-in Visitor',
            'visitor_sex' => 'Male',
            'visitor_age' => 30,
            'visitor_email' => 'walkin@example.com',
            'visitor_contact' => '09123456789',
            'visitor_id_type' => 'Passport',
            'visitor_id_number' => 'P1234567',
            'relationship_to_pdl' => 'Spouse',
            'visit_date' => today()->addDay()->toDateString(),
            'time_slot' => '8:00 AM - 9:00 AM',
        ])
        ->assertRedirect(route('admin.dashboard'))
        ->assertSessionHas('success', 'Walk-in visitor registered successfully.');

    $this->assertDatabaseHas('bookings', [
        'visitor_full_name' => 'Walk-in Visitor',
        'status' => 'Approved',
    ]);

    $booking = Booking::where('visitor_full_name', 'Walk-in Visitor')->first();
    expect($booking->status)->toBe('Approved')
        ->and($booking->terms_accepted)->toBeTrue()
        ->and($booking->privacy_consent)->toBeTrue();

    $this->assertDatabaseHas('booking_status_histories', [
        'booking_id' => $booking->id,
        'user_id' => $admin->id,
        'from_status' => null,
        'to_status' => 'Approved',
        'reason' => 'Walk-in visitor registered on-site.',
    ]);
});

test('walk-in bookings can be for today', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();

    $this->actingAs($admin)
        ->post(route('admin.bookings.store'), [
            'facility_id' => $facility->id,
            'dormitory_id' => $facility->dormitories()->firstOrFail()->id,
            'pdl_name' => 'Juan Dela Cruz',
            'visitor_full_name' => 'Today Walk-in',
            'visitor_sex' => 'Male',
            'visitor_age' => 30,
            'visitor_contact' => '09123456789',
            'visitor_id_type' => 'Passport',
            'visitor_id_number' => 'P1234567',
            'relationship_to_pdl' => 'Spouse',
            'visit_date' => today()->toDateString(),
            'time_slot' => '8:00 AM - 9:00 AM',
        ])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertDatabaseHas('bookings', [
        'visitor_full_name' => 'Today Walk-in',
        'status' => 'Approved',
    ]);
});

test('walk-in bookings cannot exceed facility slot capacity', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility(1, 1);
    createAdminManagementBooking($facility, [
        'visit_date' => today()->addDay()->toDateString(),
        'time_slot' => '8:00 AM - 9:00 AM',
        'status' => 'Approved',
    ]);

    $this->actingAs($admin)
        ->post(route('admin.bookings.store'), [
            'facility_id' => $facility->id,
            'dormitory_id' => $facility->dormitories()->firstOrFail()->id,
            'pdl_name' => 'Juan Dela Cruz',
            'visitor_full_name' => 'Walk-in Visitor',
            'visitor_sex' => 'Male',
            'visitor_age' => 30,
            'visitor_contact' => '09123456789',
            'visitor_id_type' => 'Passport',
            'visitor_id_number' => 'P1234567',
            'relationship_to_pdl' => 'Spouse',
            'visit_date' => today()->addDay()->toDateString(),
            'time_slot' => '8:00 AM - 9:00 AM',
        ])
        ->assertSessionHasErrors('time_slot');

    $this->assertDatabaseMissing('bookings', [
        'visitor_full_name' => 'Walk-in Visitor',
    ]);
});

test('non-admins cannot access walk-in booking creation', function () {
    $this->get(route('admin.bookings.create'))
        ->assertRedirect(route('login'));

    $this->post(route('admin.bookings.store'), [])
        ->assertRedirect(route('login'));
});

test('admin can add accompanying visitors when editing a booking', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();
    $booking = createAdminManagementBooking($facility, ['status' => 'Approved']);

    $this->actingAs($admin)
        ->put(route('admin.bookings.update', $booking), [
            'facility_id' => $facility->id,
            'dormitory_id' => $facility->dormitories()->firstOrFail()->id,
            'pdl_name' => 'Juan Dela Cruz',
            'visitor_full_name' => 'Sample Visitor',
            'visitor_sex' => 'Male',
            'visitor_age' => 30,
            'visitor_email' => 'visitor@example.com',
            'visitor_contact' => '09123456789',
            'visitor_id_type' => 'Passport',
            'visitor_id_number' => 'P1234567',
            'relationship_to_pdl' => 'Spouse',
            'visit_date' => today()->addDays(8)->toDateString(),
            'time_slot' => '9:00 AM - 10:00 AM',
            'accompanying_visitors' => [
                [
                    'full_name' => 'Accompanying Visitor 1',
                    'age' => 25,
                    'sex' => 'Female',
                    'id_type' => 'Driver\'s License',
                    'id_number' => 'DL-987654',
                    'relationship_to_pdl' => 'Sibling',
                ],
            ],
        ])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertDatabaseHas('accompanying_visitors', [
        'booking_id' => $booking->id,
        'full_name' => 'Accompanying Visitor 1',
    ]);
});

test('admin can remove accompanying visitors when editing a booking', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();
    $booking = createAdminManagementBooking($facility, ['status' => 'Approved']);
    $booking->accompanyingVisitors()->create([
        'full_name' => 'Existing Visitor',
        'age' => 30,
        'sex' => 'Male',
        'id_type' => 'Passport',
        'id_number' => 'P1234567',
        'relationship_to_pdl' => 'Friend',
    ]);

    $this->actingAs($admin)
        ->put(route('admin.bookings.update', $booking), [
            'facility_id' => $facility->id,
            'dormitory_id' => $facility->dormitories()->firstOrFail()->id,
            'pdl_name' => 'Juan Dela Cruz',
            'visitor_full_name' => 'Sample Visitor',
            'visitor_sex' => 'Male',
            'visitor_age' => 30,
            'visitor_email' => 'visitor@example.com',
            'visitor_contact' => '09123456789',
            'visitor_id_type' => 'Passport',
            'visitor_id_number' => 'P1234567',
            'relationship_to_pdl' => 'Spouse',
            'visit_date' => today()->addDays(8)->toDateString(),
            'time_slot' => '9:00 AM - 10:00 AM',
            'accompanying_visitors' => [],
        ])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertDatabaseMissing('accompanying_visitors', [
        'booking_id' => $booking->id,
    ]);
});

test('walk-in bookings can have accompanying visitors', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();

    $this->actingAs($admin)
        ->post(route('admin.bookings.store'), [
            'facility_id' => $facility->id,
            'dormitory_id' => $facility->dormitories()->firstOrFail()->id,
            'pdl_name' => 'Juan Dela Cruz',
            'visitor_full_name' => 'Walk-in Visitor',
            'visitor_sex' => 'Male',
            'visitor_age' => 30,
            'visitor_contact' => '09123456789',
            'visitor_id_type' => 'Passport',
            'visitor_id_number' => 'P1234567',
            'relationship_to_pdl' => 'Spouse',
            'visit_date' => today()->addDay()->toDateString(),
            'time_slot' => '8:00 AM - 9:00 AM',
            'accompanying_visitors' => [
                [
                    'full_name' => 'Accompanying Person',
                    'age' => 28,
                    'sex' => 'Female',
                    'id_type' => 'Driver\'s License',
                    'id_number' => 'DL-555555',
                    'relationship_to_pdl' => 'Spouse',
                ],
            ],
        ])
        ->assertRedirect(route('admin.dashboard'));

    $booking = Booking::where('visitor_full_name', 'Walk-in Visitor')->first();
    $this->assertDatabaseHas('accompanying_visitors', [
        'booking_id' => $booking->id,
        'full_name' => 'Accompanying Person',
    ]);
});

test('admin can view daily visitation summary', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();

    $booking1 = createAdminManagementBooking($facility, [
        'visitor_age' => 10,
        'visitor_sex' => 'Male',
        'visit_date' => today()->toDateString(),
        'status' => 'Approved',
    ]);
    $booking1->accompanyingVisitors()->create([
        'full_name' => 'Child Visitor',
        'age' => 8,
        'sex' => 'Female',
        'id_type' => 'Passport',
        'id_number' => 'P111111',
        'relationship_to_pdl' => 'Sibling',
    ]);

    $booking2 = createAdminManagementBooking($facility, [
        'visitor_age' => 65,
        'visitor_sex' => 'Female',
        'visit_date' => today()->toDateString(),
        'status' => 'Approved',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.daily-summary', ['date' => today()->toDateString()]));

    $response->assertOk()
        ->assertSeeText('Daily Visitation Summary')
        ->assertSeeText('Total Visitors')
        ->assertSee('4') // 2 main visitors + 2 accompanying
        ->assertSeeText('Children (Under 18)')
        ->assertSee('2')
        ->assertSeeText('Senior Citizens (60+)')
        ->assertSee('1');
});

test('daily summary correctly categorizes visitors by age', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();

    createAdminManagementBooking($facility, [
        'visitor_age' => 17,
        'visitor_sex' => 'Male',
        'visit_date' => today()->toDateString(),
        'status' => 'Approved',
    ]);

    createAdminManagementBooking($facility, [
        'visitor_age' => 18,
        'visitor_sex' => 'Female',
        'visit_date' => today()->toDateString(),
        'status' => 'Approved',
    ]);

    createAdminManagementBooking($facility, [
        'visitor_age' => 19,
        'visitor_sex' => 'Male',
        'visit_date' => today()->toDateString(),
        'status' => 'Approved',
    ]);

    createAdminManagementBooking($facility, [
        'visitor_age' => 59,
        'visitor_sex' => 'Female',
        'visit_date' => today()->toDateString(),
        'status' => 'Approved',
    ]);

    createAdminManagementBooking($facility, [
        'visitor_age' => 60,
        'visitor_sex' => 'Male',
        'visit_date' => today()->toDateString(),
        'status' => 'Approved',
    ]);

    createAdminManagementBooking($facility, [
        'visitor_age' => 61,
        'visitor_sex' => 'Female',
        'visit_date' => today()->toDateString(),
        'status' => 'Approved',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.daily-summary', ['date' => today()->toDateString()]));

    $response->assertOk();
    $summary = $response->viewData('summary');

    expect($summary['by_age']['child'])->toBe(1) // 17 only
        ->and($summary['by_age']['adult'])->toBe(3) // 18, 19, and 59
        ->and($summary['by_age']['senior'])->toBe(2); // 60 and 61
});

test('daily summary only counts approved bookings', function () {
    $admin = User::factory()->create();
    $facility = createAdminManagementFacility();

    createAdminManagementBooking($facility, [
        'visit_date' => today()->toDateString(),
        'status' => 'Approved',
    ]);

    createAdminManagementBooking($facility, [
        'visit_date' => today()->toDateString(),
        'status' => 'Pending',
    ]);

    createAdminManagementBooking($facility, [
        'visit_date' => today()->toDateString(),
        'status' => 'Rejected',
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.daily-summary', ['date' => today()->toDateString()]));

    $response->assertOk();
    $summary = $response->viewData('summary');

    expect($summary['total_visitors'])->toBe(1);
});
