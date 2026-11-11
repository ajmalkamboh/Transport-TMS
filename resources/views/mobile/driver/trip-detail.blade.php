@extends('layouts.mobile')

@section('content')
<div class="space-y-4">
    <!-- Back Button & Trip Summary Header -->
    <div class="flex items-center space-x-2">
        <a href="{{ route('mobile.driver.dashboard') }}" class="p-2 bg-gray-800 rounded-lg text-gray-300 hover:text-white">
            &larr; Back
        </a>
        <div class="flex-1">
            <h1 class="text-base font-bold text-white font-mono">{{ $trip->trip_number }}</h1>
            <p class="text-xs text-gray-400">{{ $trip->origin_city }} &rarr; {{ $trip->destination_city }}</p>
        </div>
        <span class="px-2 py-0.5 rounded text-xs font-semibold
            {{ $trip->trip_status === 'in_transit' ? 'bg-amber-900/60 text-amber-300 border border-amber-700' : 
               ($trip->trip_status === 'operationally_completed' ? 'bg-green-900/60 text-green-300 border border-green-700' : 'bg-blue-900/60 text-blue-300 border border-blue-700') }}">
            {{ strtoupper(str_replace('_', ' ', $trip->trip_status)) }}
        </span>
    </div>

    <!-- Quick Metrics Card -->
    <div class="grid grid-cols-2 gap-2 text-xs">
        <div class="bg-gray-800 p-3 rounded-lg border border-gray-700">
            <span class="text-gray-400 block text-[11px]">Primary Vehicle</span>
            <span class="font-mono font-bold text-white text-sm">{{ $trip->primaryVehicle()?->registration_number ?? 'N/A' }}</span>
        </div>
        <div class="bg-gray-800 p-3 rounded-lg border border-gray-700">
            <span class="text-gray-400 block text-[11px]">Dep. Odometer</span>
            <span class="font-mono font-bold text-white text-sm">{{ $trip->departure_odometer_km ? number_format($trip->departure_odometer_km) . ' km' : 'Pending' }}</span>
        </div>
    </div>

    <!-- ACTION 1: START TRIP -->
    @if(in_array($trip->trip_status, ['planned', 'loading', 'dispatched']))
        <div class="bg-gray-800 rounded-xl p-4 border border-indigo-500/50 shadow-md">
            <h3 class="text-sm font-semibold text-white mb-2 flex items-center">
                <span class="w-2 h-2 bg-indigo-500 rounded-full mr-2"></span> Start Trip Execution
            </h3>
            <form method="POST" action="{{ route('mobile.driver.trip.start', $trip->id) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-300 mb-1">Departure Odometer (KM)</label>
                    <input type="number" name="departure_odometer_km" required min="0" placeholder="e.g. 124500"
                           class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500">
                </div>
                <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-500 text-white font-semibold py-2.5 rounded-lg text-xs shadow transition">
                    Start Trip & Go In-Transit
                </button>
            </form>
        </div>
    @endif

    <!-- ACTION 2: EN-ROUTE CHECKPOINT -->
    @if($trip->trip_status === 'in_transit')
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
            <h3 class="text-sm font-semibold text-white mb-2 flex items-center">
                <span class="w-2 h-2 bg-blue-500 rounded-full mr-2"></span> Record Checkpoint / Stop
            </h3>
            <form method="POST" action="{{ route('mobile.driver.trip.checkpoint', $trip->id) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-300 mb-1">Location / Rest Stop / Toll</label>
                    <input type="text" name="location_name" required placeholder="e.g. Hyderabad Toll Plaza"
                           class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-300 mb-1">Notes / Delay Reason</label>
                    <input type="text" name="notes" placeholder="e.g. 15 min tea break"
                           class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white placeholder-gray-500 focus:outline-none focus:border-indigo-500">
                </div>
                <input type="hidden" name="latitude" id="checkpoint_lat">
                <input type="hidden" name="longitude" id="checkpoint_lng">
                <button type="submit" onclick="populateCheckpointCoords()" class="w-full bg-blue-600 hover:bg-blue-500 text-white font-medium py-2 rounded-lg text-xs transition">
                    Record Checkpoint
                </button>
            </form>
        </div>
    @endif

    <!-- ACTION 3: DELIVERY CONFIRMATION & POD -->
    @if(in_array($trip->trip_status, ['in_transit', 'arrived']))
        <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
            <h3 class="text-sm font-semibold text-white mb-2 flex items-center">
                <span class="w-2 h-2 bg-emerald-500 rounded-full mr-2"></span> Confirm Delivery & POD
            </h3>
            <form method="POST" action="{{ route('mobile.driver.trip.deliver', $trip->id) }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-300 mb-1">Select Consignment</label>
                    <select name="trip_consignment_id" required class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white focus:outline-none focus:border-indigo-500">
                        @foreach($trip->tripConsignments as $alloc)
                            <option value="{{ $alloc->id }}">
                                {{ $alloc->consignment?->consignment_number ?? 'Allocation #'.$alloc->id }} ({{ $alloc->assigned_quantity }} qty) - {{ $alloc->delivery_status ?? 'Pending' }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-3 gap-2">
                    <div>
                        <label class="block text-[11px] text-gray-400 mb-1">Delivered Qty</label>
                        <input type="number" name="delivered_quantity" required min="0" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-2 py-1.5 text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] text-gray-400 mb-1">Damaged Qty</label>
                        <input type="number" name="damaged_quantity" value="0" min="0" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-2 py-1.5 text-xs text-white">
                    </div>
                    <div>
                        <label class="block text-[11px] text-gray-400 mb-1">Short Qty</label>
                        <input type="number" name="short_quantity" value="0" min="0" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-2 py-1.5 text-xs text-white">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-300 mb-1">Receiver Name</label>
                    <input type="text" name="receiver_name" required placeholder="Person who received goods" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-300 mb-1">Receiver Phone</label>
                    <input type="text" name="receiver_phone" placeholder="Phone number" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-xs text-white">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-300 mb-1">Upload Signed POD / Photo</label>
                    <input type="file" name="pod_document" accept="image/*,application/pdf" capture="environment" class="w-full text-xs text-gray-400 file:mr-2 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-indigo-600 file:text-white hover:file:bg-indigo-500">
                </div>
                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white font-medium py-2 rounded-lg text-xs transition">
                    Submit Delivery Confirmation
                </button>
            </form>
        </div>
    @endif

    <!-- ACTION 4: EXPENSE SUBMISSION -->
    <div class="bg-gray-800 rounded-xl p-4 border border-gray-700">
        <h3 class="text-sm font-semibold text-white mb-2 flex items-center">
            <span class="w-2 h-2 bg-yellow-500 rounded-full mr-2"></span> Submit En-Route Expense
        </h3>
        <form method="POST" action="{{ route('mobile.driver.trip.expense', $trip->id) }}" enctype="multipart/form-data" class="space-y-3">
            @csrf
            <div class="grid grid-cols-2 gap-2">
                <div>
                    <label class="block text-xs font-medium text-gray-300 mb-1">Category</label>
                    <select name="expense_category" required class="w-full bg-gray-900 border border-gray-700 rounded-lg px-2 py-1.5 text-xs text-white">
                        <option value="toll_tax">Toll Tax</option>
                        <option value="fuel">Emergency Fuel</option>
                        <option value="food">Food / Meal</option>
                        <option value="accommodation">Accommodation</option>
                        <option value="parking">Parking</option>
                        <option value="repair">Emergency Repair</option>
                        <option value="other">Other Route Expense</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-300 mb-1">Amount (Rs.)</label>
                    <input type="number" step="0.01" name="amount" required min="1" placeholder="0.00" class="w-full bg-gray-900 border border-gray-700 rounded-lg px-2 py-1.5 text-xs text-white">
                </div>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-300 mb-1">Payment Method</label>
                <select name="payment_method" required class="w-full bg-gray-900 border border-gray-700 rounded-lg px-2 py-1.5 text-xs text-white">
                    <option value="cash">Paid in Cash</option>
                    <option value="fuel_card">Fuel Card</option>
                    <option value="company_online">Company Online Transfer</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-300 mb-1">Receipt / Slip Photo</label>
                <input type="file" name="receipt" accept="image/*,application/pdf" capture="environment" class="w-full text-xs text-gray-400 file:mr-2 file:py-1.5 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-gray-700 file:text-white hover:file:bg-gray-600">
            </div>
            <button type="submit" class="w-full bg-yellow-600 hover:bg-yellow-500 text-white font-medium py-2 rounded-lg text-xs transition">
                Submit Expense Slip
            </button>
        </form>
    </div>

    <!-- ACTION 5: COMPLETE TRIP -->
    @if(in_array($trip->trip_status, ['in_transit', 'arrived']))
        <div class="bg-gray-800 rounded-xl p-4 border border-red-500/50 shadow-md">
            <h3 class="text-sm font-semibold text-white mb-2 flex items-center">
                <span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span> Complete Trip
            </h3>
            <form method="POST" action="{{ route('mobile.driver.trip.complete', $trip->id) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-gray-300 mb-1">Arrival Odometer (KM)</label>
                    <input type="number" name="arrival_odometer_km" required min="{{ (int)$trip->departure_odometer_km }}" placeholder="Must be &ge; {{ (int)$trip->departure_odometer_km }}"
                           class="w-full bg-gray-900 border border-gray-700 rounded-lg px-3 py-2 text-sm text-white placeholder-gray-500 focus:outline-none focus:border-red-500">
                </div>
                <button type="submit" class="w-full bg-red-600 hover:bg-red-500 text-white font-semibold py-2.5 rounded-lg text-xs shadow transition">
                    Mark Trip Operationally Completed
                </button>
            </form>
        </div>
    @endif

    <!-- RECENT TRIP LOGS -->
    <div class="bg-gray-800/80 rounded-xl p-4 border border-gray-700 space-y-3">
        <h3 class="text-xs font-semibold uppercase text-gray-400">En-Route History</h3>
        
        <div class="space-y-2 text-xs">
            @forelse($trip->checkpoints as $cp)
                <div class="flex justify-between items-center py-1 border-b border-gray-700/60">
                    <span class="text-gray-200 font-medium">{{ $cp->location_name }}</span>
                    <span class="text-[11px] text-gray-400">{{ \Carbon\Carbon::parse($cp->arrival_time)->format('h:i A') }}</span>
                </div>
            @empty
                <p class="text-gray-500 text-[11px]">No checkpoints recorded yet.</p>
            @endforelse
        </div>
    </div>
</div>

<script>
    function populateCheckpointCoords() {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(pos => {
                document.getElementById('checkpoint_lat').value = pos.coords.latitude;
                document.getElementById('checkpoint_lng').value = pos.coords.longitude;
            });
        }
    }
</script>
@endsection

