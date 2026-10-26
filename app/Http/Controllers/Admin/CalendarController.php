<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CalendarController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'month' => ['nullable', 'date_format:Y-m'],
        ]);

        $month = CarbonImmutable::parse(($validated['month'] ?? today()->format('Y-m')).'-01');
        $calendarStart = $month->startOfWeek(Carbon::SUNDAY);
        $calendarEnd = $month->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $visits = Booking::visibleToUser($request->user())
            ->with(['facility'])
            ->whereDate('visit_date', '>=', $calendarStart->toDateString())
            ->whereDate('visit_date', '<=', $calendarEnd->toDateString())
            ->orderBy('visit_date')
            ->orderBy('time_slot')
            ->get();

        $visitsByDate = $visits->groupBy(fn (Booking $booking): string => $booking->visit_date->toDateString());
        $calendarDays = collect();

        for ($day = $calendarStart; $day->lte($calendarEnd); $day = $day->addDay()) {
            $calendarDays->push([
                'date' => $day,
                'inMonth' => $day->month === $month->month,
                'visits' => $visitsByDate->get($day->toDateString(), collect()),
            ]);
        }

        $monthLabel = $month->format('F Y');
        $previousMonth = $month->subMonth()->format('Y-m');
        $nextMonth = $month->addMonth()->format('Y-m');

        return view('admin.calendar', compact('calendarDays', 'monthLabel', 'nextMonth', 'previousMonth', 'visits'));
    }
}
