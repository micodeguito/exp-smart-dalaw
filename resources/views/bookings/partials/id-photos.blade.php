@php
    $idPhotoVisitors = collect([
        [
            'visitor' => 'primary',
            'name' => $booking->visitor_full_name,
            'label' => 'Primary visitor',
            'id_type' => $booking->visitor_id_type,
            'id_number' => $booking->visitor_id_number,
            'path' => $booking->visitor_id_photo_path,
        ],
    ]);

    foreach ($booking->accompanyingVisitors as $accompanyingVisitor) {
        $idPhotoVisitors->push([
            'visitor' => (string) $accompanyingVisitor->id,
            'name' => $accompanyingVisitor->full_name,
            'label' => 'Accompanying visitor',
            'id_type' => $accompanyingVisitor->id_type,
            'id_number' => $accompanyingVisitor->id_number,
            'path' => $accompanyingVisitor->id_photo_path,
        ]);
    }
@endphp

<div class="mt-4 border-t border-slate-100 pt-4">
    <h2 class="text-xs font-semibold uppercase text-slate-500">Submitted valid ID photos</h2>
    <p class="mt-1 text-xs text-slate-500">These photos are available only through this booking's reference number.</p>

    <div class="mt-3 grid gap-4 sm:grid-cols-2">
        @foreach($idPhotoVisitors as $idPhotoVisitor)
            <figure class="rounded-md border border-slate-200 bg-slate-50 p-3">
                <figcaption class="mb-2">
                    <p class="text-sm font-semibold text-slate-900">{{ $idPhotoVisitor['name'] }} <span class="font-normal text-slate-500">({{ $idPhotoVisitor['label'] }})</span></p>
                    <p class="mt-0.5 text-xs text-slate-600">{{ $idPhotoVisitor['id_type'] }} · {{ $idPhotoVisitor['id_number'] }}</p>
                </figcaption>

                @if($idPhotoVisitor['path'])
                    <img src="{{ route('booking.id-photo', ['code' => $booking->reference_code, 'visitor' => $idPhotoVisitor['visitor']]) }}" alt="Submitted valid ID photo for {{ $idPhotoVisitor['name'] }}" class="h-56 w-full rounded-md border border-slate-200 bg-white object-contain">
                    <a href="{{ route('booking.id-photo', ['code' => $booking->reference_code, 'visitor' => $idPhotoVisitor['visitor']]) }}" target="_blank" rel="noopener" class="mt-2 inline-block text-xs font-semibold text-[#0A2472] hover:underline">Open full size &rarr;</a>
                @else
                    <p class="rounded-md bg-amber-50 p-3 text-xs text-amber-900">No ID photo is on file for this visitor.</p>
                @endif
            </figure>
        @endforeach
    </div>
</div>
