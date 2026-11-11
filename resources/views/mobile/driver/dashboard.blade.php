@extends('layouts.mobile')

@section('content')
<div class="space-y-4">
    <!-- Header Welcome Card -->
    <div class="bg-gray-800 rounded-xl p-4 border border-gray-700 shadow-sm">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-base font-bold text-white">Hello, {{ $user->name }}</h1>
                <p class="text-xs text-gray-400">Driver Portal &bull; {{ $user->company?->name ?? 'Fleet Operations' }}</p>
            </div>
            <div class="text-right">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-indigo-900 text-indigo-300 border border-indigo-700">
                    {{ $activeTrips->count() }} Active
                </span>
            </div>
        </div>
    </div>

    <!-- Active / En-Route Trips Section -->
    <div class="space-y-3">
        <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-400 px-1">Assigned / In-Transit Trips</h2>

        @forelse($activeTrips as $trip)
            <div class="bg-gray-800 rounded-xl p-4 border border-gray-700 hover:border-indigo-500 transition-colors shadow">
                <div class="flex justify-between items-start mb-2">
                    <div>
                        <span class="font-mono text-sm font-bold text-indigo-400">{{ $trip->trip_number }}</span>
                        <div class="text-xs text-gray-400 mt-0.5">{{ \Carbon\Carbon::parse($trip->trip_date)->format('M d, Y') }}</div>
                    </div>
                    <span class="px-2 py-0.5 rounded text-[11px] font-semibold 
                        {{ $trip->trip_status === 'in_transit' ? 'bg-amber-900/60 text-amber-300 border border-amber-700' : 'bg-blue-900/60 text-blue-300 border border-blue-700' }}">
                        {{ strtoupper(str_replace('_', ' ', $trip->trip_status)) }}
                    </span>
                </div>

                <div class="my-3 py-2 px-3 bg-gray-900/70 rounded-lg text-xs space-y-1 border border-gray-800">
                    <div class="flex items-center text-gray-300">
                        <span class="w-16 text-gray-500">Route:</span>
                        <span class="font-medium text-white">{{ $trip->origin_city ?? 'Origin' }} &rarr; {{ $trip->destination_city ?? 'Destination' }}</span>
                    </div>
                    <div class="flex items-center text-gray-300">
                        <span class="w-16 text-gray-500">Vehicle:</span>
                        <span class="font-mono">{{ $trip->primaryVehicle()?->registration_number ?? 'Assigned' }}</span>
                    </div>
                    <div class="flex items-center text-gray-300">
                        <span class="w-16 text-gray-500">Cargo:</span>
                        <span>{{ $trip->consignments->count() }} Consignment(s)</span>
                    </div>
                </div>

                <a href="{{ route('mobile.driver.trip.detail', $trip->id) }}" 
                   class="w-full mt-2 inline-flex justify-center items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-white font-medium text-xs rounded-lg shadow-sm transition">
                    Open Trip Actions &rarr;
                </a>
            </div>
        @empty
            <div class="bg-gray-800/60 rounded-xl p-6 text-center border border-gray-700">
                <p class="text-sm text-gray-400">No active trips assigned right now.</p>
                <p class="text-xs text-gray-500 mt-1">Check back later or contact your fleet dispatcher.</p>
            </div>
        @endforelse
    </div>

    <!-- Recent Completed Trips -->
    @if($completedTrips->isNotEmpty())
        <div class="space-y-3 pt-2">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-gray-400 px-1">Recent Completed Trips</h2>
            <div class="space-y-2">
                @foreach($completedTrips as $trip)
                    <div class="bg-gray-800/70 rounded-lg p-3 border border-gray-700 flex justify-between items-center text-xs">
                        <div>
                            <div class="font-mono font-medium text-gray-200">{{ $trip->trip_number }}</div>
                            <div class="text-[11px] text-gray-500">{{ $trip->origin_city }} &rarr; {{ $trip->destination_city }}</div>
                        </div>
                        <div class="text-right">
                            <span class="text-green-400 font-medium">Completed</span>
                            <div class="text-[10px] text-gray-500">{{ \Carbon\Carbon::parse($trip->actual_arrival)->format('M d') }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection

