<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dormitory;
use App\Models\Facility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\File;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FacilitySettingsController extends Controller
{
    public function index(): View
    {
        $facilities = Facility::visibleToUser(auth()->user())
            ->with(['dormitories' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        return view('admin.facilities.settings', compact('facilities'));
    }

    public function updateDormitory(Request $request, Facility $facility, Dormitory $dormitory): RedirectResponse
    {
        $facility = Facility::visibleToUser($request->user())->whereKey($facility->id)->firstOrFail();

        abort_unless((int) $dormitory->facility_id === (int) $facility->id, 404);

        $validated = $request->validate([
            'max_online_bookings_per_hour' => ['required', 'integer', 'min:1', 'max:10000', 'lte:max_bookings_per_hour'],
            'max_bookings_per_hour' => ['required', 'integer', 'min:1', 'max:10000', 'gte:max_online_bookings_per_hour'],
            'badge_image' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max('2mb')],
        ], [
            'max_online_bookings_per_hour.lte' => 'The online booking limit cannot be greater than the combined hourly limit.',
            'max_bookings_per_hour.gte' => 'The combined hourly limit cannot be less than the online booking limit.',
        ]);

        $badge = $validated['badge_image'] ?? null;
        unset($validated['badge_image']);

        $previousBadgePath = $dormitory->badge_image_path;
        if ($badge instanceof UploadedFile) {
            $badgePath = $badge->store("dormitory-badges/{$facility->id}", 'public');

            if (! $badgePath) {
                throw ValidationException::withMessages([
                    'badge_image' => 'The dormitory badge could not be stored. Please try again.',
                ]);
            }

            $validated['badge_image_path'] = $badgePath;
        }

        $dormitory->update($validated);

        if ($badge instanceof UploadedFile && $previousBadgePath) {
            Storage::disk('public')->delete($previousBadgePath);
        }

        return redirect()->route('admin.facilities.settings')->with('success', 'Dormitory booking limits updated.');
    }

    public function updateBranding(Request $request, Facility $facility): RedirectResponse
    {
        $facility = Facility::visibleToUser($request->user())->whereKey($facility->id)->firstOrFail();
        $validated = $request->validate([
            'regional_office' => ['nullable', 'string', 'max:150'],
            'jail_unit_name' => ['nullable', 'string', 'max:150'],
            'badge_image' => ['nullable', File::image()->types(['jpg', 'jpeg', 'png', 'webp'])->max('2mb')],
        ]);

        $badge = $validated['badge_image'] ?? null;
        unset($validated['badge_image']);

        $previousBadgePath = $facility->badge_image_path;
        if ($badge instanceof UploadedFile) {
            $badgePath = $badge->store("facility-badges/{$facility->id}", 'public');

            if (! $badgePath) {
                throw ValidationException::withMessages([
                    'badge_image' => 'The facility badge could not be stored. Please try again.',
                ]);
            }

            $validated['badge_image_path'] = $badgePath;
        }

        $facility->update($validated);

        if ($badge instanceof UploadedFile && $previousBadgePath) {
            Storage::disk('public')->delete($previousBadgePath);
        }

        return redirect()->route('admin.facilities.settings')->with('success', 'Facility identity details updated.');
    }
}
