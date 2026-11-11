@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="sm:flex sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center space-x-3">
                <h1 class="text-2xl font-bold font-mono text-gray-900">{{ $consignment->consignment_number }}</h1>
                <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold
                    {{ in_array($consignment->delivery_status, ['delivered', 'delivered_full']) ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800' }}">
                    {{ strtoupper($consignment->delivery_status ?? 'BOOKED') }}
                </span>
            </div>
            <p class="mt-1 text-sm text-gray-500">{{ $consignment->origin_city }} &rarr; {{ $consignment->destination_city }}</p>
        </div>
        <a href="{{ route('portal.customer.consignments') }}" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
            &larr; Back to Consignments
        </a>
    </div>

    <!-- Overview Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
        <div class="bg-white p-4 rounded-lg border border-gray-200">
            <span class="text-gray-400 font-semibold uppercase">Cargo Details</span>
            <div class="mt-2 text-gray-800 space-y-1">
                <div><span class="text-gray-500">Packages:</span> <strong>{{ $consignment->package_count }}</strong></div>
                <div><span class="text-gray-500">Weight:</span> <strong>{{ number_format($consignment->weight_kg) }} kg</strong></div>
                <div><span class="text-gray-500">Description:</span> {{ $consignment->goods_description ?? 'General Cargo' }}</div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-lg border border-gray-200">
            <span class="text-gray-400 font-semibold uppercase">Delivery Status</span>
            <div class="mt-2 text-gray-800 space-y-1">
                <div><span class="text-gray-500">Receiver:</span> <strong>{{ $consignment->consignee_name ?? 'Pending' }}</strong></div>
                <div><span class="text-gray-500">Delivery Address:</span> {{ $consignment->consignee_address ?? 'Customer Hub' }}</div>
                <div><span class="text-gray-500">Delivered At:</span> {{ $consignment->delivered_at ? \Carbon\Carbon::parse($consignment->delivered_at)->format('M d, Y h:i A') : 'In-Transit' }}</div>
            </div>
        </div>

        <div class="bg-white p-4 rounded-lg border border-gray-200">
            <span class="text-gray-400 font-semibold uppercase">Live Telemetry & GPS</span>
            <div class="mt-2 text-gray-800 space-y-1">
                @if($latestLocation)
                    <div><span class="text-gray-500">Lat/Long:</span> {{ $latestLocation->latitude }}, {{ $latestLocation->longitude }}</div>
                    <div><span class="text-gray-500">Speed:</span> {{ $latestLocation->speed_kmh }} km/h</div>
                    <div><span class="text-gray-500">Last Ping:</span> {{ \Carbon\Carbon::parse($latestLocation->recorded_at)->diffForHumans() }}</div>
                @else
                    <div class="text-gray-400 italic">No GPS device or active signal attached to this trip.</div>
                @endif
            </div>
        </div>
    </div>

    <!-- En-Route Milestones & Proof of Delivery -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Milestones Timeline -->
        <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
            <h3 class="text-sm font-bold text-gray-900 mb-4">Tracking Timeline & Checkpoints</h3>
            <div class="space-y-4">
                <div class="flex items-start space-x-3 text-xs">
                    <div class="w-3 h-3 rounded-full bg-green-500 mt-0.5"></div>
                    <div>
                        <div class="font-bold text-gray-900">Consignment Booked</div>
                        <div class="text-gray-500">{{ \Carbon\Carbon::parse($consignment->created_at)->format('M d, Y h:i A') }}</div>
                    </div>
                </div>

                @if($activeTrip)
                    <div class="flex items-start space-x-3 text-xs">
                        <div class="w-3 h-3 rounded-full bg-indigo-500 mt-0.5"></div>
                        <div>
                            <div class="font-bold text-gray-900">Assigned to Trip {{ $activeTrip->trip_number }}</div>
                            <div class="text-gray-500">Vehicle: {{ $activeTrip->primaryVehicle()?->registration_number ?? 'Assigned' }}</div>
                        </div>
                    </div>

                    @foreach($activeTrip->checkpoints as $cp)
                        <div class="flex items-start space-x-3 text-xs">
                            <div class="w-3 h-3 rounded-full bg-blue-500 mt-0.5"></div>
                            <div>
                                <div class="font-bold text-gray-900">{{ $cp->location_name }}</div>
                                <div class="text-gray-500">{{ \Carbon\Carbon::parse($cp->arrival_time)->format('M d, Y h:i A') }} &bull; {{ $cp->notes }}</div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>
        </div>

        <!-- Electronic POD Section -->
        <div class="bg-white p-5 rounded-lg border border-gray-200 shadow-sm">
            <h3 class="text-sm font-bold text-gray-900 mb-4">Proof of Delivery (POD)</h3>
            @forelse($pods as $pod)
                <div class="p-4 bg-gray-50 rounded-lg border border-gray-200 text-xs space-y-2">
                    <div class="flex justify-between items-center">
                        <span class="font-bold text-gray-800">Verified Receipt</span>
                        <span class="px-2 py-0.5 bg-green-100 text-green-800 rounded font-semibold text-[10px]">VERIFIED</span>
                    </div>
                    <div>Receiver: <strong>{{ $pod->receiver_name }}</strong> ({{ $pod->receiver_phone ?? 'N/A' }})</div>
                    <div>Signed at: {{ \Carbon\Carbon::parse($pod->verified_at ?? $pod->created_at)->format('M d, Y h:i A') }}</div>
                    
                    @if($pod->file_path)
                        <div class="pt-2">
                            <a href="{{ route('portal.customer.pod.download', $pod->id) }}" class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white rounded font-medium hover:bg-indigo-500 transition">
                                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                Download Signed POD Slip
                            </a>
                        </div>
                    @endif
                </div>
            @empty
                <div class="p-6 text-center text-gray-400 text-xs">
                    POD will be uploaded upon shipment arrival and receiver acknowledgment.
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

