<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Facility;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class BookingIdPhotoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Storage::fake('private');
        Mail::fake();
    }

    public static function createIdPhotoFacility(array $attributes = []): Facility
    {
        $facility = Facility::create(array_merge([
            'name' => 'ID Photo Test Facility',
            'max_visitors_per_hour' => 10,
            'regional_office' => 'San Fernando City, Pampanga',
        ], $attributes));

        $facility->dormitories()->create([
            'name' => 'Male Dormitory',
            'max_online_bookings_per_hour' => 7,
            'max_bookings_per_hour' => 15,
        ]);

        return $facility;
    }

    public static function baseBookingData(Facility $facility, array $overrides = []): array
    {
        return array_merge([
            'facility_id' => $facility->id,
            'dormitory_id' => $facility->dormitories()->firstOrFail()->id,
            'pdl_name' => 'Juan Dela Cruz',
            'visitor_full_name' => 'Visitor Name',
            'visitor_email' => 'visitor@example.com',
            'visitor_contact' => '09123456789',
            'visitor_sex' => 'Male',
            'visitor_age' => 30,
            'visitor_id_type' => 'Passport',
            'visitor_id_number' => 'P1234567',
            'visitor_id_photo' => UploadedFile::fake()->image('id.jpg'),
            'relationship_to_pdl' => 'Sibling',
            'visit_date' => today()->addDays(4)->toDateString(),
            'time_slot' => '8:00 AM - 9:00 AM',
            'terms_accepted' => '1',
            'privacy_consent' => '1',
            'accompanying_visitors' => [],
        ], $overrides);
    }

    public function testTheBookingFormEncodesIdPhotoUploadsAsMultipartFormData(): void
    {
        $this->get(route('bookings.create'))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="visitor_id_photo"', false);
    }

    public function testABookingWithoutThePrimaryVisitorIdPhotoIsRejected(): void
    {
        $facility = self::createIdPhotoFacility();

        $this->post(route('bookings.store'), array_merge(
            self::baseBookingData($facility, ['visitor_id_photo' => null]),
            ['accompanying_visitors' => []]
        ))->assertSessionHasErrors('visitor_id_photo');

        expect(Booking::count())->toBe(0);
        expect(Storage::disk('private')->files('visitor-id-photos'))->toBe([]);
    }

    public function testABookingWithoutAnIdPhotoForAnyAccompanyingVisitorIsRejected(): void
    {
        $facility = self::createIdPhotoFacility();

        $this->post(route('bookings.store'), array_merge(
            self::baseBookingData($facility),
            [
                'accompanying_visitors' => [
                    [
                        'full_name' => 'Accompanying Visitor',
                        'age' => 8,
                        'sex' => 'Female',
                        'id_type' => 'Passport',
                        'id_number' => 'P8888888',
                        'relationship_to_pdl' => 'Child',
                    ],
                ],
            ]
        ))->assertSessionHasErrors('accompanying_visitors.0.id_photo');

        expect(Booking::count())->toBe(0);
    }

    public function testValidJpgJpegPngAndWebpPhotosAreAcceptedForEveryVisitor(): void
    {
        $facility = self::createIdPhotoFacility();

        foreach (['jpg', 'jpeg', 'png', 'webp'] as $extension) {
            $data = self::baseBookingData($facility, [
                'visitor_id_photo' => UploadedFile::fake()->create("primary.{$extension}", 100),
                'accompanying_visitors' => [
                    [
                        'full_name' => 'Accompanying Visitor',
                        'age' => 8,
                        'sex' => 'Female',
                        'id_type' => 'Passport',
                        'id_number' => 'P8888888',
                        'id_photo' => UploadedFile::fake()->create("accompanying.{$extension}", 100),
                        'relationship_to_pdl' => 'Child',
                    ],
                ],
            ]);

            $this->post(route('bookings.store'), $data)->assertRedirect();
        }

        expect(Booking::count())->toBe(4);
    }

    public function testPhotosLargerThan2MbAreRejected(): void
    {
        $facility = self::createIdPhotoFacility();

        $this->post(route('bookings.store'), array_merge(
            self::baseBookingData($facility, [
                'visitor_id_photo' => UploadedFile::fake()->create('large.jpg', 3000), // 3 MB
            ]),
            ['accompanying_visitors' => []]
        ))->assertSessionHasErrors('visitor_id_photo');

        expect(Booking::count())->toBe(0);
    }

    public function testEveryVisitorPhotoIsAssociatedWithTheCorrectVisitorRecord(): void
    {
        $facility = self::createIdPhotoFacility();

        $this->post(route('bookings.store'), array_merge(
            self::baseBookingData($facility),
            [
                'accompanying_visitors' => [
                    [
                        'full_name' => 'Accompanying One',
                        'age' => 8,
                        'sex' => 'Female',
                        'id_type' => 'Passport',
                        'id_number' => 'P0000001',
                        'id_photo' => UploadedFile::fake()->create('accompanying-one.jpg', 100),
                        'relationship_to_pdl' => 'Child',
                    ],
                    [
                        'full_name' => 'Accompanying Two',
                        'age' => 12,
                        'sex' => 'Male',
                        'id_type' => 'Passport',
                        'id_number' => 'P0000002',
                        'id_photo' => UploadedFile::fake()->create('accompanying-two.jpg', 100),
                        'relationship_to_pdl' => 'Sibling',
                    ],
                ],
            ]
        ))->assertRedirect();

        $booking = Booking::first();
        expect($booking->visitor_id_photo_path)->not->toBeNull();

        $accompanying = $booking->accompanyingVisitors()->orderBy('full_name')->get();
        expect($accompanying)->toHaveCount(2);
        expect($accompanying->first()->id_photo_path)->not->toBeNull();
        expect($accompanying->last()->id_photo_path)->not->toBeNull();
        expect($accompanying->first()->id_photo_path)->not->toBe($accompanying->last()->id_photo_path);
        expect($accompanying->first()->id_photo_path)->not->toBe($booking->visitor_id_photo_path);
    }


    public function testMultipleAccompanyingVisitorsCanEachSubmitTheirOwnPhoto(): void
    {
        $facility = self::createIdPhotoFacility();

        $accompanying = [];
        for ($i = 1; $i <= 3; $i++) {
            $accompanying[] = [
                'full_name' => "Accompanying Visitor {$i}",
                'age' => 10 + $i,
                'sex' => 'Female',
                'id_type' => 'Passport',
                'id_number' => "P000000{$i}",
                'id_photo' => UploadedFile::fake()->create("accompanying-{$i}.jpg", 100),
                'relationship_to_pdl' => 'Relative',
            ];
        }

        $this->post(route('bookings.store'), array_merge(
            self::baseBookingData($facility),
            ['accompanying_visitors' => $accompanying]
        ))->assertRedirect();

        $booking = Booking::first();
        expect($booking->accompanyingVisitors)->toHaveCount(3);
        $booking->accompanyingVisitors->each(fn ($visitor) => expect($visitor->id_photo_path)->not->toBeNull());
    }

    public function testAFailedBookingDoesNotLeaveOrphanedUploadedFiles(): void
    {
        $facility = self::createIdPhotoFacility(['max_visitors_per_hour' => 1]);
        $facility->dormitories()->update([
            'max_online_bookings_per_hour' => 1,
            'max_bookings_per_hour' => 1,
        ]);
        $dormitory = $facility->dormitories()->firstOrFail();

        Booking::create([
            'reference_code' => 'BJMP-EXISTING',
            'facility_id' => $facility->id,
            'dormitory_id' => $dormitory->id,
            'pdl_name' => 'Juan Dela Cruz',
            'visitor_full_name' => 'Existing Visitor',
            'visitor_sex' => 'Male',
            'visitor_age' => 30,
            'visitor_contact' => '09123456789',
            'visitor_id_type' => 'Passport',
            'visitor_id_number' => 'PEXISTING',
            'visitor_id_photo_path' => 'visitor-id-photos/existing.jpg',
            'relationship_to_pdl' => 'Sibling',
            'visit_date' => today()->addDays(4)->toDateString(),
            'time_slot' => '8:00 AM - 9:00 AM',
            'status' => 'Pending',
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
            'privacy_consent' => true,
            'privacy_consent_at' => now(),
            'privacy_notice_version' => '2026-09-28-v1',
        ]);

        $before = Storage::disk('private')->files('visitor-id-photos');

        $this->post(route('bookings.store'), array_merge(
            self::baseBookingData($facility),
            ['accompanying_visitors' => []]
        ))->assertSessionHasErrors('time_slot');

        expect(Booking::count())->toBe(1);
        expect(Storage::disk('private')->files('visitor-id-photos'))->toBe($before);
    }

    public function testPhotosAreStoredPrivatelyAndCannotBeRetrievedThroughPublicUrls(): void
    {
        $facility = self::createIdPhotoFacility();

        $this->post(route('bookings.store'), array_merge(
            self::baseBookingData($facility, ['visitor_email' => null]),
            ['accompanying_visitors' => []]
        ))->assertRedirect();

        $booking = Booking::first();

        $path = $booking->visitor_id_photo_path;
        expect($path)->not->toBeNull();

        expect(Storage::disk('private')->exists($path))->toBeTrue();
        expect(Storage::disk('public')->exists($path))->toBeFalse();

        $this->get(route('booking.success', $booking->reference_code))
            ->assertOk()
            ->assertDontSee($path);
    }

    public function testUnauthenticatedUsersCannotViewPhotos(): void
    {
        $facility = self::createIdPhotoFacility();

        $this->post(route('bookings.store'), array_merge(
            self::baseBookingData($facility, ['visitor_email' => null]),
            ['accompanying_visitors' => []]
        ))->assertRedirect();

        $booking = Booking::first();

        $this->get(route('admin.bookings.id-photo', ['booking' => $booking->id, 'visitor' => 'primary']))
            ->assertRedirect(route('login'));
    }


    public function testFacilityAdminsCannotViewPhotosOutsideTheirAuthorizedFacility(): void
    {
        $facilityA = self::createIdPhotoFacility(['name' => 'Facility A']);
        $facilityB = self::createIdPhotoFacility(['name' => 'Facility B']);

        $adminB = User::factory()->create([
            'role' => User::ROLE_FACILITY_ADMIN,
            'facility_id' => $facilityB->id,
        ]);

        $booking = Booking::create([
            'reference_code' => 'BJMP-CROSSFAC',
            'facility_id' => $facilityA->id,
            'pdl_name' => 'Juan Dela Cruz',
            'visitor_full_name' => 'Visitor Name',
            'visitor_sex' => 'Male',
            'visitor_age' => 30,
            'visitor_contact' => '09123456789',
            'visitor_id_type' => 'Passport',
            'visitor_id_number' => 'PX123456',
            'visitor_id_photo_path' => 'visitor-id-photos/cross.jpg',
            'relationship_to_pdl' => 'Sibling',
            'visit_date' => today()->addDays(4)->toDateString(),
            'time_slot' => '8:00 AM - 9:00 AM',
            'status' => 'Pending',
            'terms_accepted' => true,
            'terms_accepted_at' => now(),
            'privacy_consent' => true,
            'privacy_consent_at' => now(),
            'privacy_notice_version' => '2026-09-28-v1',
        ]);

        $this->actingAs($adminB)
            ->get(route('admin.bookings.id-photo', ['booking' => $booking->id, 'visitor' => 'primary']))
            ->assertStatus(404);
    }

    public function testAuthorizedAdministratorsCanViewTheAppropriatePhotoForEachVisitor(): void
    {
        $facility = self::createIdPhotoFacility();

        $admin = User::factory()->create([
            'role' => User::ROLE_FACILITY_ADMIN,
            'facility_id' => $facility->id,
        ]);

        $this->post(route('bookings.store'), array_merge(
            self::baseBookingData($facility),
            [
                'accompanying_visitors' => [
                    [
                        'full_name' => 'Accompanying Visitor',
                        'age' => 8,
                        'sex' => 'Female',
                        'id_type' => 'Passport',
                        'id_number' => 'P8888888',
                        'id_photo' => UploadedFile::fake()->create('accompanying.jpg', 100),
                        'relationship_to_pdl' => 'Child',
                    ],
                ],
            ]
        ))->assertRedirect();

        $booking = Booking::first();

        $this->actingAs($admin)
            ->get(route('admin.bookings.id-photo', ['booking' => $booking->id, 'visitor' => 'primary']))
            ->assertOk();

        $accompanying = $booking->accompanyingVisitors()->first();
        $this->actingAs($admin)
            ->get(route('admin.bookings.id-photo', ['booking' => $booking->id, 'visitor' => $accompanying->id]))
            ->assertOk();
    }

    public function testPublicConfirmationTrackingAndQrPagesDoNotRevealPrivatePhotosOrPaths(): void
    {
        $facility = self::createIdPhotoFacility();

        $this->post(route('bookings.store'), self::baseBookingData($facility))->assertRedirect();

        $booking = Booking::first();
        $path = $booking->visitor_id_photo_path;

        $this->get(route('booking.success', $booking->reference_code))
            ->assertOk()
            ->assertDontSee($path);

        $this->post(route('bookings.track.search'), ['reference_code' => $booking->reference_code])
            ->assertOk()
            ->assertDontSee($path);

        $this->get(route('qr.redirect', $booking->reference_code))
            ->assertRedirect(route('booking.success', $booking->reference_code));
    }

    public function testUnsupportedFormatsAreRejectedForEveryVisitor(): void
    {
        $facility = self::createIdPhotoFacility();

        $this->post(route('bookings.store'), array_merge(
            self::baseBookingData($facility, [
                'visitor_id_photo' => UploadedFile::fake()->create('document.pdf', 100),
            ]),
            ['accompanying_visitors' => []]
        ))->assertSessionHasErrors('visitor_id_photo');

        expect(Booking::count())->toBe(0);
    }

    public function testAdminDetailsPageShowsEachVisitorsPhotoWithTheirName(): void
    {
        $facility = self::createIdPhotoFacility();
        $admin = User::factory()->create();

        $this->post(route('bookings.store'), array_merge(
            self::baseBookingData($facility),
            [
                'accompanying_visitors' => [
                    [
                        'full_name' => 'Accompanying Visitor',
                        'age' => 8,
                        'sex' => 'Female',
                        'id_type' => 'Passport',
                        'id_number' => 'P8888888',
                        'id_photo' => UploadedFile::fake()->image('accompanying.jpg'),
                        'relationship_to_pdl' => 'Child',
                    ],
                ],
            ]
        ))->assertRedirect();

        $booking = Booking::firstOrFail();
        $accompanying = $booking->accompanyingVisitors()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.bookings.show', $booking))
            ->assertOk()
            ->assertSee('Submitted valid ID photos')
            ->assertSee('(primary visitor)')
            ->assertSee('(accompanying visitor)')
            ->assertSee('Accompanying Visitor')
            ->assertSee(route('admin.bookings.id-photo', ['booking' => $booking, 'visitor' => 'primary']), false)
            ->assertSee(route('admin.bookings.id-photo', ['booking' => $booking, 'visitor' => $accompanying->id]), false)
            ->assertSee('Open full size');
    }

    public function testAdminEditPageShowsExistingPhotosWithoutRequiringReplacement(): void
    {
        $facility = self::createIdPhotoFacility();
        $admin = User::factory()->create();

        $this->post(route('bookings.store'), array_merge(
            self::baseBookingData($facility),
            [
                'accompanying_visitors' => [
                    [
                        'full_name' => 'Accompanying Visitor',
                        'age' => 8,
                        'sex' => 'Female',
                        'id_type' => 'Passport',
                        'id_number' => 'P8888888',
                        'id_photo' => UploadedFile::fake()->image('accompanying.jpg'),
                        'relationship_to_pdl' => 'Child',
                    ],
                ],
            ]
        ))->assertRedirect();

        $booking = Booking::firstOrFail();
        $accompanying = $booking->accompanyingVisitors()->firstOrFail();

        $this->actingAs($admin)
            ->get(route('admin.bookings.edit', $booking))
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee(route('admin.bookings.id-photo', ['booking' => $booking, 'visitor' => 'primary']), false)
            ->assertSee(route('admin.bookings.id-photo', ['booking' => $booking, 'visitor' => $accompanying->id]), false)
            ->assertSee('Leave empty to keep the current photo.');
    }

    public function testEditingWithoutReplacementKeepsExistingPhotoPathsAndFiles(): void
    {
        $facility = self::createIdPhotoFacility();
        $admin = User::factory()->create();

        $this->post(route('bookings.store'), array_merge(
            self::baseBookingData($facility),
            [
                'accompanying_visitors' => [
                    [
                        'full_name' => 'Accompanying Visitor',
                        'age' => 8,
                        'sex' => 'Female',
                        'id_type' => 'Passport',
                        'id_number' => 'P8888888',
                        'id_photo' => UploadedFile::fake()->image('accompanying.jpg'),
                        'relationship_to_pdl' => 'Child',
                    ],
                ],
            ]
        ))->assertRedirect();

        $booking = Booking::firstOrFail();
        $accompanying = $booking->accompanyingVisitors()->firstOrFail();
        $oldPrimaryPath = $booking->visitor_id_photo_path;
        $oldAccompanyingPath = $accompanying->id_photo_path;
        $before = Storage::disk('private')->files('visitor-id-photos');

        $this->actingAs($admin)
            ->put(route('admin.bookings.update', $booking), array_merge(
                self::baseBookingData($facility),
                [
                    'visitor_id_photo' => null,
                    'accompanying_visitors' => [
                        [
                            'id' => $accompanying->id,
                            'full_name' => 'Updated Accompanying Visitor',
                            'age' => 9,
                            'sex' => 'Female',
                            'id_type' => 'Passport',
                            'id_number' => 'P8888888',
                            'relationship_to_pdl' => 'Child',
                        ],
                    ],
                ]
            ))
            ->assertRedirect();

        $booking->refresh();
        $accompanying->refresh();

        expect($booking->visitor_id_photo_path)->toBe($oldPrimaryPath)
            ->and($accompanying->full_name)->toBe('Updated Accompanying Visitor')
            ->and($accompanying->id_photo_path)->toBe($oldAccompanyingPath);

        expect(Storage::disk('private')->files('visitor-id-photos'))->toBe($before);
    }

    public function testReplacingAPhotoUsesTheNewImageAfterSaving(): void
    {
        $facility = self::createIdPhotoFacility();
        $admin = User::factory()->create();

        $this->post(route('bookings.store'), self::baseBookingData($facility))->assertRedirect();

        $booking = Booking::firstOrFail();
        $oldPath = $booking->visitor_id_photo_path;

        $this->actingAs($admin)
            ->put(route('admin.bookings.update', $booking), array_merge(
                self::baseBookingData($facility),
                [
                    'visitor_id_photo' => UploadedFile::fake()->image('replacement.jpg'),
                    'accompanying_visitors' => [],
                ]
            ))
            ->assertRedirect();

        $booking->refresh();

        expect($booking->visitor_id_photo_path)->not->toBe($oldPath);
        expect(Storage::disk('private')->exists($booking->visitor_id_photo_path))->toBeTrue();
        expect(Storage::disk('private')->exists($oldPath))->toBeFalse();

        $photoResponse = $this->actingAs($admin)
            ->get(route('admin.bookings.id-photo', ['booking' => $booking, 'visitor' => 'primary']))
            ->assertOk();

        expect($photoResponse->headers->get('Cache-Control'))->toContain('no-store');
    }

    public function testReplacingAnAccompanyingVisitorsPhotoKeepsTheCorrectVisitorRecord(): void
    {
        $facility = self::createIdPhotoFacility();
        $admin = User::factory()->create();

        $this->post(route('bookings.store'), array_merge(
            self::baseBookingData($facility),
            [
                'accompanying_visitors' => [
                    [
                        'full_name' => 'Accompanying Visitor',
                        'age' => 8,
                        'sex' => 'Female',
                        'id_type' => 'Passport',
                        'id_number' => 'P8888888',
                        'id_photo' => UploadedFile::fake()->image('accompanying.jpg'),
                        'relationship_to_pdl' => 'Child',
                    ],
                ],
            ]
        ))->assertRedirect();

        $booking = Booking::firstOrFail();
        $accompanying = $booking->accompanyingVisitors()->firstOrFail();
        $oldPath = $accompanying->id_photo_path;

        $this->actingAs($admin)
            ->put(route('admin.bookings.update', $booking), array_merge(
                self::baseBookingData($facility),
                [
                    'visitor_id_photo' => null,
                    'accompanying_visitors' => [
                        [
                            'id' => $accompanying->id,
                            'full_name' => 'Accompanying Visitor',
                            'age' => 8,
                            'sex' => 'Female',
                            'id_type' => 'Passport',
                            'id_number' => 'P8888888',
                            'id_photo' => UploadedFile::fake()->image('replacement.jpg'),
                            'relationship_to_pdl' => 'Child',
                        ],
                    ],
                ]
            ))
            ->assertRedirect();

        $accompanying->refresh();

        expect($accompanying->id_photo_path)->not->toBe($oldPath);
        expect(Storage::disk('private')->exists($accompanying->id_photo_path))->toBeTrue();
        expect(Storage::disk('private')->exists($oldPath))->toBeFalse();

        $this->actingAs($admin)
            ->get(route('admin.bookings.id-photo', ['booking' => $booking, 'visitor' => $accompanying->id]))
            ->assertOk();
    }

    public function testEditingCannotAttachAnotherBookingsAccompanyingVisitor(): void
    {
        $facility = self::createIdPhotoFacility();
        $admin = User::factory()->create();

        $this->post(route('bookings.store'), self::baseBookingData($facility))->assertRedirect();
        $bookingA = Booking::firstOrFail();

        $this->post(route('bookings.store'), array_merge(
            self::baseBookingData($facility, ['visitor_full_name' => 'Second Visitor']),
            [
                'accompanying_visitors' => [
                    [
                        'full_name' => 'Other Booking Visitor',
                        'age' => 8,
                        'sex' => 'Female',
                        'id_type' => 'Passport',
                        'id_number' => 'P7777777',
                        'id_photo' => UploadedFile::fake()->image('other.jpg'),
                        'relationship_to_pdl' => 'Child',
                    ],
                ],
            ]
        ))->assertRedirect();

        $bookingB = Booking::latest('id')->firstOrFail();
        $otherVisitor = $bookingB->accompanyingVisitors()->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.bookings.update', $bookingA), array_merge(
                self::baseBookingData($facility),
                [
                    'visitor_id_photo' => null,
                    'accompanying_visitors' => [
                        [
                            'id' => $otherVisitor->id,
                            'full_name' => 'Other Booking Visitor',
                            'age' => 8,
                            'sex' => 'Female',
                            'id_type' => 'Passport',
                            'id_number' => 'P7777777',
                            'relationship_to_pdl' => 'Child',
                        ],
                    ],
                ]
            ))
            ->assertSessionHasErrors('accompanying_visitors.0.id');

        expect($otherVisitor->fresh()->booking_id)->toBe($bookingB->id);
        expect($bookingA->fresh()->accompanyingVisitors)->toHaveCount(0);
    }

    public function testVisitorsCanViewPhotosOnlyForTheirOwnBookingReference(): void
    {
        $facility = self::createIdPhotoFacility();

        $this->post(route('bookings.store'), self::baseBookingData($facility))->assertRedirect();
        $bookingA = Booking::firstOrFail();

        $this->post(route('bookings.store'), array_merge(
            self::baseBookingData($facility, ['visitor_full_name' => 'Second Visitor']),
            [
                'accompanying_visitors' => [
                    [
                        'full_name' => 'Second Booking Accompanying Visitor',
                        'age' => 8,
                        'sex' => 'Female',
                        'id_type' => 'Passport',
                        'id_number' => 'P6666666',
                        'id_photo' => UploadedFile::fake()->image('other.jpg'),
                        'relationship_to_pdl' => 'Child',
                    ],
                ],
            ]
        ))->assertRedirect();

        $bookingB = Booking::latest('id')->firstOrFail();
        $otherVisitor = $bookingB->accompanyingVisitors()->firstOrFail();

        $this->get(route('booking.id-photo', ['code' => $bookingA->reference_code, 'visitor' => 'primary']))
            ->assertOk();

        $this->get(route('booking.id-photo', ['code' => $bookingA->reference_code, 'visitor' => $otherVisitor->id]))
            ->assertNotFound();

        $this->get(route('booking.id-photo', ['code' => 'BJMP-NOTREAL', 'visitor' => 'primary']))
            ->assertNotFound();
    }

    public function testVisitorStatusPagesShowOnlyTheirOwnPhotoLinks(): void
    {
        $facility = self::createIdPhotoFacility();

        $this->post(route('bookings.store'), array_merge(
            self::baseBookingData($facility),
            [
                'accompanying_visitors' => [
                    [
                        'full_name' => 'Accompanying Visitor',
                        'age' => 8,
                        'sex' => 'Female',
                        'id_type' => 'Passport',
                        'id_number' => 'P8888888',
                        'id_photo' => UploadedFile::fake()->image('accompanying.jpg'),
                        'relationship_to_pdl' => 'Child',
                    ],
                ],
            ]
        ))->assertRedirect();

        $booking = Booking::firstOrFail();
        $accompanying = $booking->accompanyingVisitors()->firstOrFail();

        $primaryUrl = route('booking.id-photo', ['code' => $booking->reference_code, 'visitor' => 'primary']);
        $accompanyingUrl = route('booking.id-photo', ['code' => $booking->reference_code, 'visitor' => $accompanying->id]);

        $this->get(route('booking.success', $booking->reference_code))
            ->assertOk()
            ->assertSee('Submitted valid ID photos')
            ->assertSee($primaryUrl, false)
            ->assertSee($accompanyingUrl, false);

        $this->post(route('bookings.track.search'), ['reference_code' => $booking->reference_code])
            ->assertOk()
            ->assertSee($primaryUrl, false)
            ->assertSee($accompanyingUrl, false);
    }

    public function testMissingInvalidAndUnsafeStoredPhotoPathsReturnNotFound(): void
    {
        $facility = self::createIdPhotoFacility();
        $admin = User::factory()->create();

        $this->post(route('bookings.store'), self::baseBookingData($facility))->assertRedirect();
        $booking = Booking::firstOrFail();

        foreach ([
            'visitor-id-photos/missing.jpg',
            '../.env',
            'visitor-id-photos/../private.jpg',
            'visitor-id-photos/evil.php',
            'public/visitor-id-photos/photo.jpg',
            '/visitor-id-photos/photo.jpg',
        ] as $unsafePath) {
            $booking->update(['visitor_id_photo_path' => $unsafePath]);

            $this->actingAs($admin)
                ->get(route('admin.bookings.id-photo', ['booking' => $booking, 'visitor' => 'primary']))
                ->assertNotFound();
        }
    }

    public function testGuestsAndOtherFacilityAdminsCannotOpenAdminBookingDetails(): void
    {
        $facilityA = self::createIdPhotoFacility(['name' => 'Facility A']);
        $facilityB = self::createIdPhotoFacility(['name' => 'Facility B']);

        $adminA = User::factory()->create([
            'role' => User::ROLE_FACILITY_ADMIN,
            'facility_id' => $facilityA->id,
        ]);

        $this->post(route('bookings.store'), self::baseBookingData($facilityB))->assertRedirect();
        $booking = Booking::firstOrFail();

        $this->get(route('admin.bookings.show', $booking))->assertRedirect(route('login'));

        $this->actingAs($adminA)
            ->get(route('admin.bookings.show', $booking))
            ->assertNotFound();
    }
}

