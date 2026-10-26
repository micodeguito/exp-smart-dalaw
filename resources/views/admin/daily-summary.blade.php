@extends('layouts.admin')

@section('title', 'Daily Visitation Summary | SmartDalaw')

@section('content')
    <div class="max-w-5xl">
        <a href="{{ route('admin.dashboard') }}" class="text-sm font-medium text-[#0A2472] hover:underline">&larr; Dashboard</a>
        <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase text-[#0A2472]">Reports</p>
                <h1 class="mt-1 text-2xl font-bold text-[#102044]">Daily Visitation Summary</h1>
                <p class="mt-2 text-sm text-slate-600">View visitor statistics grouped by sex and age category.</p>
            </div>
            <form action="{{ route('admin.daily-summary') }}" method="GET" class="flex items-center gap-2">
                <input type="date" name="date" value="{{ $summary['date'] }}" class="min-h-10 rounded-md border border-slate-300 bg-white px-3 py-2 text-sm text-slate-800 focus:border-[#0A2472] focus:outline-none focus:ring-2 focus:ring-blue-100">
                <button type="submit" class="min-h-10 rounded-md bg-[#0A2472] px-4 py-2 text-sm font-semibold text-white hover:bg-[#071D54]">View Date</button>
            </form>
        </div>

        <section class="mt-6" aria-label="Overall statistics">
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <article class="rounded-md border border-slate-200 bg-white p-4">
                    <p class="text-xs font-medium text-slate-500">Total Visitors</p>
                    <p class="mt-2 text-2xl font-bold text-[#102044]">{{ $summary['total_visitors'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">All approved visitors</p>
                </article>
                <article class="rounded-md border border-blue-200 bg-white p-4">
                    <p class="text-xs font-medium text-blue-800">Male Visitors</p>
                    <p class="mt-2 text-2xl font-bold text-blue-900">{{ $summary['by_sex']['Male'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ number_format(($summary['by_sex']['Male'] / max($summary['total_visitors'], 1)) * 100, 1) }}% of total</p>
                </article>
                <article class="rounded-md border border-pink-200 bg-white p-4">
                    <p class="text-xs font-medium text-pink-800">Female Visitors</p>
                    <p class="mt-2 text-2xl font-bold text-pink-900">{{ $summary['by_sex']['Female'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ number_format(($summary['by_sex']['Female'] / max($summary['total_visitors'], 1)) * 100, 1) }}% of total</p>
                </article>
                <article class="rounded-md border border-slate-200 bg-white p-4">
                    <p class="text-xs font-medium text-slate-500">Approved Bookings</p>
                    <p class="mt-2 text-2xl font-bold text-[#102044]">{{ $bookings->count() }}</p>
                    <p class="mt-1 text-xs text-slate-500">Including accompanying visitors</p>
                </article>
            </div>
        </section>

        <section class="mt-6" aria-label="Age distribution">
            <h2 class="text-lg font-bold text-[#102044]">Age Distribution</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <article class="rounded-md border border-emerald-200 bg-white p-4">
                    <p class="text-xs font-medium text-emerald-800">Children (Under 18)</p>
                    <p class="mt-2 text-2xl font-bold text-emerald-900">{{ $summary['by_age']['child'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ number_format(($summary['by_age']['child'] / max($summary['total_visitors'], 1)) * 100, 1) }}% of total</p>
                </article>
                <article class="rounded-md border border-slate-200 bg-white p-4">
                    <p class="text-xs font-medium text-slate-500">Adults (18-59)</p>
                    <p class="mt-2 text-2xl font-bold text-[#102044]">{{ $summary['by_age']['adult'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ number_format(($summary['by_age']['adult'] / max($summary['total_visitors'], 1)) * 100, 1) }}% of total</p>
                </article>
                <article class="rounded-md border border-amber-200 bg-white p-4">
                    <p class="text-xs font-medium text-amber-800">Senior Citizens (60+)</p>
                    <p class="mt-2 text-2xl font-bold text-amber-900">{{ $summary['by_age']['senior'] }}</p>
                    <p class="mt-1 text-xs text-slate-500">{{ number_format(($summary['by_age']['senior'] / max($summary['total_visitors'], 1)) * 100, 1) }}% of total</p>
                </article>
            </div>
        </section>

        <section class="mt-6" aria-label="Sex and age breakdown">
            <h2 class="text-lg font-bold text-[#102044]">Breakdown by Sex and Age</h2>
            <div class="mt-4 overflow-x-auto border-y border-slate-200 bg-white">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50">
                        <tr>
                            <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Sex</th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-semibold uppercase text-slate-500">Children<br><span class="font-normal">&lt;18</span></th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-semibold uppercase text-slate-500">Adults<br><span class="font-normal">18-59</span></th>
                            <th scope="col" class="px-4 py-3 text-center text-xs font-semibold uppercase text-slate-500">Seniors<br><span class="font-normal">60+</span></th>
                            <th scope="col" class="px-4 py-3 text-right text-xs font-semibold uppercase text-slate-500">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <tr>
                            <td class="px-4 py-4 font-medium text-slate-900">Male</td>
                            <td class="px-4 py-4 text-center text-slate-700">{{ $summary['by_sex_and_age']['Male']['child'] }}</td>
                            <td class="px-4 py-4 text-center text-slate-700">{{ $summary['by_sex_and_age']['Male']['adult'] }}</td>
                            <td class="px-4 py-4 text-center text-slate-700">{{ $summary['by_sex_and_age']['Male']['senior'] }}</td>
                            <td class="px-4 py-4 text-right font-semibold text-slate-900">{{ $summary['by_sex']['Male'] }}</td>
                        </tr>
                        <tr>
                            <td class="px-4 py-4 font-medium text-slate-900">Female</td>
                            <td class="px-4 py-4 text-center text-slate-700">{{ $summary['by_sex_and_age']['Female']['child'] }}</td>
                            <td class="px-4 py-4 text-center text-slate-700">{{ $summary['by_sex_and_age']['Female']['adult'] }}</td>
                            <td class="px-4 py-4 text-center text-slate-700">{{ $summary['by_sex_and_age']['Female']['senior'] }}</td>
                            <td class="px-4 py-4 text-right font-semibold text-slate-900">{{ $summary['by_sex']['Female'] }}</td>
                        </tr>
                        <tr class="bg-slate-50">
                            <td class="px-4 py-4 font-semibold text-slate-900">Total</td>
                            <td class="px-4 py-4 text-center font-semibold text-slate-900">{{ $summary['by_age']['child'] }}</td>
                            <td class="px-4 py-4 text-center font-semibold text-slate-900">{{ $summary['by_age']['adult'] }}</td>
                            <td class="px-4 py-4 text-center font-semibold text-slate-900">{{ $summary['by_age']['senior'] }}</td>
                            <td class="px-4 py-4 text-right font-bold text-[#0A2472]">{{ $summary['total_visitors'] }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        @if($bookings->count() > 0)
            <section class="mt-6" aria-label="Booking details">
                <h2 class="text-lg font-bold text-[#102044]">Approved Bookings for {{ \Carbon\Carbon::parse($summary['date'])->format('F d, Y') }}</h2>
                <div class="mt-4 overflow-x-auto border-y border-slate-200 bg-white">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Reference</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Visitor</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">PDL</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">Time Slot</th>
                                <th scope="col" class="px-4 py-3 text-center text-xs font-semibold uppercase text-slate-500">Accompanying</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach($bookings as $booking)
                                <tr>
                                    <td class="whitespace-nowrap px-4 py-4 font-mono text-xs font-semibold text-[#0A2472]">{{ $booking->reference_code }}</td>
                                    <td class="whitespace-nowrap px-4 py-4">
                                        <p class="text-sm font-medium text-slate-900">{{ $booking->visitor_full_name }}</p>
                                        <p class="mt-1 text-xs text-slate-500">{{ $booking->visitor_age }} yrs · {{ $booking->visitor_sex }}</p>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-700">{{ $booking->pdl_name }}</td>
                                    <td class="whitespace-nowrap px-4 py-4 text-sm text-slate-700">{{ $booking->time_slot }}</td>
                                    <td class="whitespace-nowrap px-4 py-4 text-center text-sm text-slate-700">{{ $booking->accompanyingVisitors->count() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @else
            <div class="mt-6 rounded-lg border border-slate-200 bg-white p-8 text-center">
                <p class="text-sm text-slate-500">No approved bookings found for this date.</p>
            </div>
        @endif
    </div>
@endsection
