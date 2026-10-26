@extends('layouts.portal')

@section('title', 'Booking Submitted | SmartDalaw')

@section('content')
    <section class="mx-auto max-w-5xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
        <div class="mx-auto mb-8 max-w-2xl text-center">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-100 text-lg font-bold text-emerald-800" aria-hidden="true">OK</span>
            <p class="mt-5 text-xs font-semibold uppercase text-[#0A2472]">Request received</p>
            <h1 class="mt-2 text-3xl font-bold text-[#102044]">Booking submitted</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">Your visit request has been sent to the facility for review. Save your reference number to check its status.</p>
        </div>

        <div class="grid items-start gap-5 md:grid-cols-[1.2fr_0.8fr]">
            <article class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="flex flex-col gap-2 border-b border-slate-100 pb-5 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <p class="text-xs font-medium text-slate-500">Reference number</p>
                        <p class="mt-1 break-all text-xl font-bold text-[#0A2472]">{{ $booking->reference_code }}</p>
                    </div>
                    <span class="w-fit rounded-full bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800">{{ $booking->status }}</span>
                </div>
                <div class="flex items-center gap-3 border-b border-slate-100 py-4">
                    @if($booking->facility->badge_image_path)
                        <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($booking->facility->badge_image_path) }}" alt="Official badge for {{ $booking->facility->name }}" class="h-14 w-14 shrink-0 rounded-full border border-base-300 bg-base-100 object-contain p-1">
                    @else
                        <img src="{{ asset('images/bjmp-logo.png') }}" alt="BJMP Official Seal" class="h-14 w-14 shrink-0 rounded-full border border-base-300 bg-base-100 object-contain p-1">
                    @endif
                    <div class="min-w-0">
                        <p class="font-semibold text-slate-900">{{ $booking->facility->name }}</p>
                        @if($booking->facility->regional_office)
                            <p class="mt-1 text-xs text-slate-500">{{ $booking->facility->regional_office }}</p>
                        @endif
                        @if($booking->facility->jail_unit_name)
                            <p class="text-xs text-slate-500">{{ $booking->facility->jail_unit_name }}</p>
                        @endif
                    </div>
                </div>
                @if($booking->dormitory)
                    <div class="flex items-center gap-3 border-b border-slate-100 py-4">
                        @if($booking->dormitory->badge_image_path)
                            <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($booking->dormitory->badge_image_path) }}" alt="Badge for {{ $booking->dormitory->name }}" class="h-14 w-14 shrink-0 rounded-full border border-base-300 bg-base-100 object-contain p-1">
                        @endif
                        <div class="min-w-0">
                            <p class="text-xs text-slate-500">Dormitory</p>
                            <p class="font-semibold text-slate-900">{{ $booking->dormitory->name }}</p>
                        </div>
                    </div>
                @endif
                <dl class="grid gap-x-8 sm:grid-cols-2">
                    <div class="border-b border-slate-100 py-4"><dt class="text-xs text-slate-500">Person you are visiting</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $booking->pdl_name }}</dd></div>
                    <div class="border-b border-slate-100 py-4"><dt class="text-xs text-slate-500">Visitor</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $booking->visitor_full_name }}</dd></div>
                    <div class="border-b border-slate-100 py-4"><dt class="text-xs text-slate-500">Date &amp; time</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $booking->visit_date->format('M d, Y') }} · {{ $booking->time_slot }}</dd></div>
                </dl>

                @if($booking->accompanyingVisitors->count() > 0)
                    <div class="mt-4 border-t border-slate-100 pt-4">
                        <dt class="text-xs text-slate-500">Accompanying Visitors ({{ $booking->accompanyingVisitors->count() }})</dt>
                        <dd class="mt-2 space-y-2">
                            @foreach($booking->accompanyingVisitors as $visitor)
                                <div class="rounded-md bg-slate-50 p-3 text-sm">
                                    <p class="font-medium text-slate-900">{{ $visitor->full_name }}</p>
                                    <p class="mt-1 text-xs text-slate-600">{{ $visitor->age }} years old · {{ $visitor->sex }} · {{ $visitor->relationship_to_pdl }}</p>
                                </div>
                            @endforeach
                        </dd>
                    </div>
                @endif

                @include('bookings.partials.id-photos', ['booking' => $booking])
            </article>

            <aside class="rounded-lg border border-slate-200 bg-white p-5 text-center sm:p-7">
                <p class="text-xs font-semibold uppercase text-[#0A2472]">Your booking QR</p>
                <div class="mx-auto mt-4 flex aspect-square w-full max-w-56 items-center justify-center rounded-lg border border-slate-200 bg-white p-3">
                    {!! QrCode::size(180)->generate(route('qr.redirect', ['code' => $booking->reference_code])) !!}
                </div>
                <p class="mt-4 text-sm font-semibold text-slate-900">Keep this code for check-in</p>
                <p class="mt-1 text-xs leading-5 text-slate-500">Present it at the facility entrance after your visit is approved.</p>
            </aside>
        </div>

        <div class="mt-5 rounded-lg border border-blue-200 bg-blue-50 p-4 text-sm leading-6 text-blue-950">
            <strong>Next step:</strong> Track your request with reference <span class="font-semibold">{{ $booking->reference_code }}</span>. The facility administrator must approve the visit before check-in.
        </div>

        <div class="mt-7 flex flex-col gap-3 sm:flex-row sm:justify-center">
            <button type="button" onclick="window.print()" class="inline-flex min-h-11 items-center justify-center rounded-lg bg-[#0A2472] px-5 py-3 text-sm font-semibold text-white hover:bg-[#071D54] print:hidden">Print confirmation</button>
            <a href="{{ route('bookings.track') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-[#0A2472] hover:bg-blue-50 print:hidden">Track booking</a>
            <a href="{{ route('home') }}" class="inline-flex min-h-11 items-center justify-center rounded-lg border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-[#0A2472] hover:bg-blue-50 print:hidden">Return to home</a>
        </div>
    </section>
@endsection

@push('head')
    <style>
        @media print {
            body {
                background: white;
            }
        }
    </style>
@endpush