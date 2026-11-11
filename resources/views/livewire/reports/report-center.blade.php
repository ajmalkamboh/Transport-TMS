<div>
    <div class="space-y-6">
        <!-- Header & Action Controls -->
        <div class="sm:flex sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">TMS Reports & Business Intelligence</h1>
                <p class="mt-1 text-sm text-gray-500">Comprehensive analytics across fleet operations, drivers, commercial clients, trips, finance, and inventory.</p>
            </div>
            <div class="mt-4 sm:mt-0 flex flex-wrap gap-2">
                <a href="{{ route('reports.export', ['tab' => $activeTab, 'branch_id' => $branchFilter, 'from_date' => $fromDate, 'to_date' => $toDate]) }}" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="-ml-1 mr-2 h-4 w-4 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    Export CSV / Excel
                </a>
                <a href="{{ route('reports.print', ['tab' => $activeTab, 'branch_id' => $branchFilter, 'from_date' => $fromDate, 'to_date' => $toDate]) }}" target="_blank" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    <svg class="-ml-1 mr-2 h-4 w-4 text-gray-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                    </svg>
                    Printable Report / PDF
                </a>
            </div>
        </div>

        <!-- Global Filter Panel -->
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b pb-2">
                <span class="text-xs font-bold text-gray-700 text-uppercase tracking-wider">Quick Date Presets:</span>
                <div class="flex flex-wrap gap-1">
                    <button wire:click="setPreset('today')" class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200 text-gray-700">Today</button>
                    <button wire:click="setPreset('this_month')" class="px-2 py-1 text-xs rounded bg-indigo-50 hover:bg-indigo-100 text-indigo-700 font-bold">This Month</button>
                    <button wire:click="setPreset('this_quarter')" class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200 text-gray-700">This Quarter</button>
                    <button wire:click="setPreset('this_year')" class="px-2 py-1 text-xs rounded bg-gray-100 hover:bg-gray-200 text-gray-700">This Year</button>
                    <button wire:click="resetFilters" class="px-2 py-1 text-xs rounded bg-red-50 hover:bg-red-100 text-red-600 ml-2">Reset Filters</button>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-6 gap-3">
                <div>
                    <label class="block text-xs font-medium text-gray-600">From Date</label>
                    <input type="date" wire:model.live="fromDate" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">To Date</label>
                    <input type="date" wire:model.live="toDate" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                </div>
                <div>
                    <label class="block text-xs font-medium text-gray-600">Branch / Terminal</label>
                    <select wire:model.live="branchFilter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                        <option value="">All Branches</option>
                        @foreach ($branches as $br)
                            <option value="{{ $br->id }}">{{ $br->name }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($activeTab === 'fleet')
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Vehicle</label>
                        <select wire:model.live="vehicleFilter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                            <option value="">All Vehicles</option>
                            @foreach ($vehicles as $veh)
                                <option value="{{ $veh->id }}">{{ $veh->registration_number }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif ($activeTab === 'customer')
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Customer</label>
                        <select wire:model.live="customerFilter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                            <option value="">All Customers</option>
                            @foreach ($customers as $c)
                                <option value="{{ $c->id }}">{{ $c->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif ($activeTab === 'driver')
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Driver</label>
                        <select wire:model.live="driverFilter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                            <option value="">All Drivers</option>
                            @foreach ($drivers as $d)
                                <option value="{{ $d->id }}">{{ $d->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif ($activeTab === 'owner')
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Vehicle Owner</label>
                        <select wire:model.live="ownerFilter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                            <option value="">All Owners</option>
                            @foreach ($owners as $ow)
                                <option value="{{ $ow->id }}">{{ $ow->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @elseif ($activeTab === 'inventory')
                    <div>
                        <label class="block text-xs font-medium text-gray-600">Warehouse</label>
                        <select wire:model.live="warehouseFilter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                            <option value="">All Warehouses</option>
                            @foreach ($warehouses as $wh)
                                <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label class="block text-xs font-medium text-gray-600">Status</label>
                    <select wire:model.live="statusFilter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm text-xs">
                        <option value="">All Statuses</option>
                        <option value="completed">Completed</option>
                        <option value="in_transit">In Transit</option>
                        <option value="available">Available</option>
                        <option value="maintenance">Maintenance</option>
                    </select>
                </div>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-6 overflow-x-auto" aria-label="Tabs">
                <button wire:click="$set('activeTab', 'executive')" class="{{ $activeTab === 'executive' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }} py-3 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                    1. Executive Dashboard
                </button>
                <button wire:click="$set('activeTab', 'fleet')" class="{{ $activeTab === 'fleet' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }} py-3 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                    2. Fleet & Utilization
                </button>
                <button wire:click="$set('activeTab', 'driver')" class="{{ $activeTab === 'driver' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }} py-3 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                    3. Driver Operations
                </button>
                <button wire:click="$set('activeTab', 'customer')" class="{{ $activeTab === 'customer' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }} py-3 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                    4. Customer Aging (0-90+)
                </button>
                <button wire:click="$set('activeTab', 'trip')" class="{{ $activeTab === 'trip' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }} py-3 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                    5. Trips & Routes
                </button>
                <button wire:click="$set('activeTab', 'owner')" class="{{ $activeTab === 'owner' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }} py-3 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                    6. Owner Settlements
                </button>
                <button wire:click="$set('activeTab', 'financial')" class="{{ $activeTab === 'financial' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }} py-3 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                    7. Financial & Cash Flow
                </button>
                <button wire:click="$set('activeTab', 'inventory')" class="{{ $activeTab === 'inventory' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }} py-3 px-1 border-b-2 font-medium text-sm whitespace-nowrap">
                    8. Inventory & Spares
                </button>
            </nav>
        </div>

        <!-- TAB 1: EXECUTIVE DASHBOARD REPORT -->
        @if ($activeTab === 'executive')
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-4 gap-4">
                <div class="bg-white p-4 rounded-lg shadow-sm border border-l-4 border-l-indigo-600">
                    <span class="text-xs text-gray-500 uppercase font-semibold">Total Fleet Vehicles</span>
                    <h3 class="text-2xl font-bold text-gray-900 mt-1">{{ $reportData['total_fleet'] ?? 0 }}</h3>
                    <div class="text-xs text-indigo-600 mt-1">Available: {{ $reportData['available_vehicles'] ?? 0 }} &bull; Trips: {{ $reportData['vehicles_on_trips'] ?? 0 }}</div>
                </div>

                <div class="bg-white p-4 rounded-lg shadow-sm border border-l-4 border-l-blue-600">
                    <span class="text-xs text-gray-500 uppercase font-semibold">Trips Completed in Period</span>
                    <h3 class="text-2xl font-bold text-blue-900 mt-1">{{ $reportData['completed_trips'] ?? 0 }}</h3>
                    <div class="text-xs text-blue-600 mt-1">In Transit: {{ $reportData['trips_in_progress'] ?? 0 }} trips</div>
                </div>

                <div class="bg-white p-4 rounded-lg shadow-sm border border-l-4 border-l-green-600">
                    <span class="text-xs text-gray-500 uppercase font-semibold">Freight Revenue</span>
                    <h3 class="text-2xl font-bold text-green-900 mt-1">PKR {{ number_format($reportData['monthly_freight_revenue'] ?? 0, 0) }}</h3>
                    <div class="text-xs text-green-600 mt-1">Net Op Margin: PKR {{ number_format($reportData['estimated_profit'] ?? 0, 0) }}</div>
                </div>

                <div class="bg-white p-4 rounded-lg shadow-sm border border-l-4 border-l-red-600">
                    <span class="text-xs text-gray-500 uppercase font-semibold">Outstanding Receivables</span>
                    <h3 class="text-2xl font-bold text-red-900 mt-1">PKR {{ number_format($reportData['outstanding_receivables'] ?? 0, 0) }}</h3>
                    <div class="text-xs text-red-600 mt-1">Pending Owner Payables: PKR {{ number_format($reportData['outstanding_owner_payables'] ?? 0, 0) }}</div>
                </div>
            </div>

            <div class="bg-white shadow overflow-hidden rounded-lg border border-gray-200 p-4">
                <h3 class="font-bold text-gray-900 mb-3">Operational Fleet Breakdown</h3>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                    <div class="p-3 bg-gray-50 rounded">
                        <div class="text-xs text-gray-500">Active Drivers</div>
                        <div class="text-xl font-bold text-gray-900">{{ $reportData['active_drivers'] ?? 0 }}</div>
                    </div>
                    <div class="p-3 bg-gray-50 rounded">
                        <div class="text-xs text-gray-500">Under Maintenance</div>
                        <div class="text-xl font-bold text-yellow-600">{{ $reportData['vehicles_under_maintenance'] ?? 0 }}</div>
                    </div>
                    <div class="p-3 bg-gray-50 rounded">
                        <div class="text-xs text-gray-500">Pending Deliveries (POD)</div>
                        <div class="text-xl font-bold text-red-600">{{ $reportData['pending_deliveries'] ?? 0 }}</div>
                    </div>
                    <div class="p-3 bg-gray-50 rounded">
                        <div class="text-xs text-gray-500">Finalized Net Profit (GL)</div>
                        <div class="text-xl font-bold text-green-600">PKR {{ number_format($reportData['finalized_profit'] ?? 0, 0) }}</div>
                    </div>
                </div>
            </div>

        <!-- TAB 2: FLEET REPORTS -->
        @elseif ($activeTab === 'fleet')
            <div class="bg-white shadow overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-50 font-medium text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-3 text-left">Reg # / Fleet #</th>
                            <th class="px-4 py-3 text-left">Type / Owner</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Trips</th>
                            <th class="px-4 py-3 text-right">Distance (KM)</th>
                            <th class="px-4 py-3 text-right">Revenue (PKR)</th>
                            <th class="px-4 py-3 text-right">Fuel Cost</th>
                            <th class="px-4 py-3 text-right">Maint. Cost</th>
                            <th class="px-4 py-3 text-right">Total Running Cost</th>
                            <th class="px-4 py-3 text-right">Net Profit</th>
                            <th class="px-4 py-3 text-right">KM/L</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($reportData as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-bold text-gray-900">{{ $row['registration_number'] }} <span class="text-gray-500 font-normal">({{ $row['fleet_number'] }})</span></td>
                                <td class="px-4 py-2 text-gray-600 capitalize">{{ $row['ownership_type'] }} &bull; {{ $row['owner_name'] }}</td>
                                <td class="px-4 py-2 text-center"><span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">{{ $row['status'] }}</span></td>
                                <td class="px-4 py-2 text-right font-bold">{{ $row['trip_count'] }}</td>
                                <td class="px-4 py-2 text-right font-mono">{{ number_format($row['distance_km'], 0) }}</td>
                                <td class="px-4 py-2 text-right font-mono font-bold text-green-700">{{ number_format($row['freight_revenue'], 0) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-gray-700">{{ number_format($row['fuel_cost'], 0) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-gray-700">{{ number_format($row['maintenance_cost'], 0) }}</td>
                                <td class="px-4 py-2 text-right font-mono font-bold text-red-700">{{ number_format($row['total_running_cost'], 0) }}</td>
                                <td class="px-4 py-2 text-right font-mono font-bold {{ $row['net_profit'] >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ number_format($row['net_profit'], 0) }}</td>
                                <td class="px-4 py-2 text-right font-mono font-bold text-indigo-700">{{ $row['fuel_efficiency_kml'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="11" class="text-center py-6 text-gray-500">No vehicle records found for selected filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        <!-- TAB 3: DRIVER REPORTS -->
        @elseif ($activeTab === 'driver')
            <div class="bg-white shadow overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-50 font-medium text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-3 text-left">Code & Name</th>
                            <th class="px-4 py-3 text-left">License & Phone</th>
                            <th class="px-4 py-3 text-left">Branch</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Total Trips</th>
                            <th class="px-4 py-3 text-right">Completed Trips</th>
                            <th class="px-4 py-3 text-right">Advances Received</th>
                            <th class="px-4 py-3 text-right">Approved Expenses</th>
                            <th class="px-4 py-3 text-right">Allowances Paid</th>
                            <th class="px-4 py-3 text-right">Net Settlement Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($reportData as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-bold text-gray-900">{{ $row['name'] }} <span class="text-xs text-gray-500 font-normal">({{ $row['employee_code'] }})</span></td>
                                <td class="px-4 py-2 text-gray-600">{{ $row['license_number'] }} &bull; {{ $row['phone'] }}</td>
                                <td class="px-4 py-2 text-gray-600">{{ $row['branch_name'] }}</td>
                                <td class="px-4 py-2 text-center"><span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">{{ $row['status'] }}</span></td>
                                <td class="px-4 py-2 text-right font-bold">{{ $row['total_trips'] }}</td>
                                <td class="px-4 py-2 text-right font-bold text-green-700">{{ $row['completed_trips'] }}</td>
                                <td class="px-4 py-2 text-right font-mono">{{ number_format($row['total_advances'], 0) }}</td>
                                <td class="px-4 py-2 text-right font-mono">{{ number_format($row['approved_expenses'], 0) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-gray-700">{{ number_format($row['allowances_paid'], 0) }}</td>
                                <td class="px-4 py-2 text-right font-mono font-bold text-indigo-700">{{ number_format($row['net_settlement_payable'], 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center py-6 text-gray-500">No driver records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        <!-- TAB 4: CUSTOMER REVENUE & AGING (0-90+) -->
        @elseif ($activeTab === 'customer')
            <div class="bg-white shadow overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-50 font-medium text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-3 text-left">Customer Code & Name</th>
                            <th class="px-4 py-3 text-right">Credit Limit</th>
                            <th class="px-4 py-3 text-right">Total Billed</th>
                            <th class="px-4 py-3 text-right">Total Collected</th>
                            <th class="px-4 py-3 text-right font-bold text-red-700">Total Outstanding</th>
                            <th class="px-4 py-3 text-right bg-green-50 text-green-800">Current (0-30 Days)</th>
                            <th class="px-4 py-3 text-right bg-yellow-50 text-yellow-800">31 - 60 Days</th>
                            <th class="px-4 py-3 text-right bg-orange-50 text-orange-800">61 - 90 Days</th>
                            <th class="px-4 py-3 text-right bg-red-50 text-red-800">90+ Days Overdue</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($reportData as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-bold text-gray-900">{{ $row['name'] }} <span class="text-xs text-gray-500 font-mono">({{ $row['code'] }})</span></td>
                                <td class="px-4 py-2 text-right font-mono text-gray-600">{{ number_format($row['credit_limit'], 0) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-gray-900">{{ number_format($row['total_billed'], 0) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-green-700">{{ number_format($row['total_collected'], 0) }}</td>
                                <td class="px-4 py-2 text-right font-mono font-bold text-red-700">{{ number_format($row['total_outstanding'], 0) }}</td>
                                <td class="px-4 py-2 text-right font-mono bg-green-50 text-green-800">{{ number_format($row['current_0_to_30'], 0) }}</td>
                                <td class="px-4 py-2 text-right font-mono bg-yellow-50 text-yellow-800">{{ number_format($row['days_31_to_60'], 0) }}</td>
                                <td class="px-4 py-2 text-right font-mono bg-orange-50 text-orange-800">{{ number_format($row['days_61_to_90'], 0) }}</td>
                                <td class="px-4 py-2 text-right font-mono font-bold bg-red-50 text-red-800">{{ number_format($row['days_90_plus'], 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center py-6 text-gray-500">No customer balance records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        <!-- TAB 5: TRIPS & ROUTE PERFORMANCE -->
        @elseif ($activeTab === 'trip')
            <div class="space-y-6">
                <!-- Route Performance Aggregation -->
                <div class="bg-white shadow overflow-hidden rounded-lg border border-gray-200 p-4">
                    <h3 class="font-bold text-gray-900 text-sm mb-3">Route-Wise Operational Performance</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-xs">
                            <thead class="bg-gray-50 uppercase text-gray-500 font-medium">
                                <tr>
                                    <th class="px-4 py-2 text-left">Route (Origin &rarr; Destination)</th>
                                    <th class="px-4 py-2 text-right">Total Trips</th>
                                    <th class="px-4 py-2 text-right">Total Freight Revenue</th>
                                    <th class="px-4 py-2 text-right">Average Freight / Trip</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                @forelse ($reportData['routes'] ?? [] as $rt)
                                    <tr>
                                        <td class="px-4 py-2 font-bold text-gray-900">{{ $rt['route'] }}</td>
                                        <td class="px-4 py-2 text-right font-bold">{{ $rt['total_trips'] }}</td>
                                        <td class="px-4 py-2 text-right font-mono text-green-700 font-bold">PKR {{ number_format($rt['total_freight'], 0) }}</td>
                                        <td class="px-4 py-2 text-right font-mono">PKR {{ number_format($rt['avg_freight_per_trip'], 0) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center py-3 text-gray-500">No route statistics in selected period.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Individual Trips Register -->
                <div class="bg-white shadow overflow-x-auto rounded-lg border border-gray-200">
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50 font-medium text-gray-500 uppercase">
                            <tr>
                                <th class="px-4 py-3 text-left">Trip #</th>
                                <th class="px-4 py-3 text-left">Date</th>
                                <th class="px-4 py-3 text-left">Route</th>
                                <th class="px-4 py-3 text-left">Vehicle & Driver</th>
                                <th class="px-4 py-3 text-center">Status</th>
                                <th class="px-4 py-3 text-right">Freight (PKR)</th>
                                <th class="px-4 py-3 text-right">Fuel Cost</th>
                                <th class="px-4 py-3 text-right">Expenses</th>
                                <th class="px-4 py-3 text-right">Net Margin</th>
                                <th class="px-4 py-3 text-center">POD Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($reportData['trips'] ?? [] as $t)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 font-bold font-mono text-indigo-600">{{ $t['trip_number'] }}</td>
                                    <td class="px-4 py-2 text-gray-600">{{ $t['trip_date'] }}</td>
                                    <td class="px-4 py-2 font-medium text-gray-900">{{ $t['route'] }}</td>
                                    <td class="px-4 py-2 text-gray-600">{{ $t['vehicle'] }} &bull; {{ $t['driver'] }}</td>
                                    <td class="px-4 py-2 text-center"><span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800">{{ $t['status'] }}</span></td>
                                    <td class="px-4 py-2 text-right font-mono font-bold text-green-700">{{ number_format($t['freight_amount'], 0) }}</td>
                                    <td class="px-4 py-2 text-right font-mono text-gray-700">{{ number_format($t['fuel_cost'], 0) }}</td>
                                    <td class="px-4 py-2 text-right font-mono text-gray-700">{{ number_format($t['trip_expenses'], 0) }}</td>
                                    <td class="px-4 py-2 text-right font-mono font-bold {{ $t['net_margin'] >= 0 ? 'text-green-700' : 'text-red-700' }}">{{ number_format($t['net_margin'], 0) }}</td>
                                    <td class="px-4 py-2 text-center"><span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-800">{{ $t['pod_status'] }}</span></td>
                                </tr>
                            @empty
                                <tr><td colspan="10" class="text-center py-6 text-gray-500">No trips recorded for selected period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

        <!-- TAB 6: OWNER SETTLEMENTS -->
        @elseif ($activeTab === 'owner')
            <div class="bg-white shadow overflow-x-auto rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200 text-xs">
                    <thead class="bg-gray-50 font-medium text-gray-500 uppercase">
                        <tr>
                            <th class="px-4 py-3 text-left">Owner Code & Name</th>
                            <th class="px-4 py-3 text-left">Phone</th>
                            <th class="px-4 py-3 text-center">Attached Vehicles</th>
                            <th class="px-4 py-3 text-right">Settlements</th>
                            <th class="px-4 py-3 text-right">Agreed Freight</th>
                            <th class="px-4 py-3 text-right">Deductions</th>
                            <th class="px-4 py-3 text-right">Advances</th>
                            <th class="px-4 py-3 text-right">Net Payable</th>
                            <th class="px-4 py-3 text-right">Paid Amount</th>
                            <th class="px-4 py-3 text-right font-bold text-red-700">Pending Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        @forelse ($reportData as $row)
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-2 font-bold text-gray-900">{{ $row['name'] }} <span class="text-xs text-gray-500 font-normal">({{ $row['code'] }})</span></td>
                                <td class="px-4 py-2 text-gray-600">{{ $row['phone'] }}</td>
                                <td class="px-4 py-2 text-center font-bold">{{ $row['attached_vehicles'] }}</td>
                                <td class="px-4 py-2 text-right font-bold">{{ $row['settlements_count'] }}</td>
                                <td class="px-4 py-2 text-right font-mono text-gray-900">{{ number_format($row['agreed_freight'], 0) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-red-600">{{ number_format($row['deductions'], 0) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-gray-600">{{ number_format($row['advances'], 0) }}</td>
                                <td class="px-4 py-2 text-right font-mono font-bold text-gray-900">{{ number_format($row['net_payable'], 0) }}</td>
                                <td class="px-4 py-2 text-right font-mono text-green-700">{{ number_format($row['paid_amount'], 0) }}</td>
                                <td class="px-4 py-2 text-right font-mono font-bold text-red-700">{{ number_format($row['pending_balance'], 0) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="10" class="text-center py-6 text-gray-500">No owner settlement records found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

        <!-- TAB 7: FINANCIAL REPORTS & CASH FLOW -->
        @elseif ($activeTab === 'financial')
            <div class="space-y-4">
                <div class="flex space-x-2 border-b pb-2">
                    <button wire:click="$set('financialSubReport', 'pnl')" class="px-3 py-1.5 text-xs font-semibold rounded {{ $financialSubReport === 'pnl' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700' }}">Profit & Loss</button>
                    <button wire:click="$set('financialSubReport', 'trial_balance')" class="px-3 py-1.5 text-xs font-semibold rounded {{ $financialSubReport === 'trial_balance' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700' }}">Trial Balance</button>
                    <button wire:click="$set('financialSubReport', 'balance_sheet')" class="px-3 py-1.5 text-xs font-semibold rounded {{ $financialSubReport === 'balance_sheet' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700' }}">Balance Sheet</button>
                    <button wire:click="$set('financialSubReport', 'cash_flow')" class="px-3 py-1.5 text-xs font-semibold rounded {{ $financialSubReport === 'cash_flow' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700' }}">Cash Flow Statement</button>
                </div>

                @if ($financialSubReport === 'cash_flow')
                    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 max-w-2xl mx-auto space-y-4 text-sm">
                        <h3 class="font-bold text-gray-900 border-b pb-2 text-base">Statement of Cash Flows</h3>

                        <div class="space-y-2">
                            <div class="font-bold text-gray-800 uppercase text-xs">Operating Cash Inflows:</div>
                            <div class="flex justify-between pl-4">
                                <span>Collections from Commercial Customers</span>
                                <span class="font-mono font-bold text-green-700">PKR {{ number_format($reportData['inflows']['customer_collections'] ?? 0, 2) }}</span>
                            </div>
                            <div class="flex justify-between font-bold border-t pt-1">
                                <span>Total Operating Cash Inflows</span>
                                <span class="font-mono text-green-700">PKR {{ number_format($reportData['inflows']['total_inflows'] ?? 0, 2) }}</span>
                            </div>
                        </div>

                        <div class="space-y-2 pt-2">
                            <div class="font-bold text-gray-800 uppercase text-xs">Operating Cash Outflows:</div>
                            <div class="flex justify-between pl-4">
                                <span>Diesel & Fuel Refuel Payments</span>
                                <span class="font-mono text-red-600">(PKR {{ number_format($reportData['outflows']['diesel_fuel_payments'] ?? 0, 2) }})</span>
                            </div>
                            <div class="flex justify-between pl-4">
                                <span>En-Route Trip Expenses & Toll Taxes</span>
                                <span class="font-mono text-red-600">(PKR {{ number_format($reportData['outflows']['trip_en_route_expenses'] ?? 0, 2) }})</span>
                            </div>
                            <div class="flex justify-between pl-4">
                                <span>Third-Party Vehicle Owner Settlements</span>
                                <span class="font-mono text-red-600">(PKR {{ number_format($reportData['outflows']['vehicle_owner_settlements'] ?? 0, 2) }})</span>
                            </div>
                            <div class="flex justify-between pl-4">
                                <span>Spare Parts & Workshop Supplies</span>
                                <span class="font-mono text-red-600">(PKR {{ number_format($reportData['outflows']['parts_supplier_disbursements'] ?? 0, 2) }})</span>
                            </div>
                            <div class="flex justify-between font-bold border-t pt-1">
                                <span>Total Operating Cash Outflows</span>
                                <span class="font-mono text-red-700">(PKR {{ number_format($reportData['outflows']['total_outflows'] ?? 0, 2) }})</span>
                            </div>
                        </div>

                        <div class="flex justify-between font-bold text-base border-t-2 border-b-2 py-2">
                            <span>Net Cash Generated / (Used)</span>
                            <span class="font-mono {{ ($reportData['net_cash_flow'] ?? 0) >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                PKR {{ number_format($reportData['net_cash_flow'] ?? 0, 2) }}
                            </span>
                        </div>
                    </div>
                @elseif ($financialSubReport === 'trial_balance')
                    <div class="bg-white shadow overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200 text-xs">
                            <thead class="bg-gray-50 uppercase text-gray-500 font-medium">
                                <tr>
                                    <th class="px-4 py-2 text-left">Code</th>
                                    <th class="px-4 py-2 text-left">Account Title</th>
                                    <th class="px-4 py-2 text-left">Type</th>
                                    <th class="px-4 py-2 text-right">Debit (PKR)</th>
                                    <th class="px-4 py-2 text-right">Credit (PKR)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 font-mono">
                                @foreach ($reportData['accounts'] ?? [] as $acc)
                                    <tr>
                                        <td class="px-4 py-1.5 font-bold">{{ $acc['code'] }}</td>
                                        <td class="px-4 py-1.5 font-sans">{{ $acc['name'] }}</td>
                                        <td class="px-4 py-1.5 font-sans capitalize text-gray-500">{{ $acc['type'] }}</td>
                                        <td class="px-4 py-1.5 text-right font-bold">{{ $acc['debit'] > 0 ? number_format($acc['debit'], 2) : '-' }}</td>
                                        <td class="px-4 py-1.5 text-right font-bold">{{ $acc['credit'] > 0 ? number_format($acc['credit'], 2) : '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="border-t-2 font-bold bg-gray-50 font-mono">
                                <tr>
                                    <td colspan="3" class="px-4 py-2 text-right uppercase font-sans">Total Balanced:</td>
                                    <td class="px-4 py-2 text-right text-indigo-700">PKR {{ number_format($reportData['total_debit'] ?? 0, 2) }}</td>
                                    <td class="px-4 py-2 text-right text-indigo-700">PKR {{ number_format($reportData['total_credit'] ?? 0, 2) }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200 max-w-2xl mx-auto space-y-3 text-sm">
                        <div class="flex justify-between border-b pb-2">
                            <span class="font-bold text-gray-800">Total Operating Revenues:</span>
                            <span class="font-mono font-bold text-green-700">PKR {{ number_format($reportData['total_revenue'] ?? 0, 2) }}</span>
                        </div>
                        <div class="flex justify-between border-b pb-2">
                            <span class="font-bold text-gray-800">Direct Operating Costs:</span>
                            <span class="font-mono font-bold text-red-600">(PKR {{ number_format($reportData['total_direct_costs'] ?? 0, 2) }})</span>
                        </div>
                        <div class="flex justify-between border-b pb-2">
                            <span class="font-bold text-gray-800">Administrative Overheads:</span>
                            <span class="font-mono font-bold text-red-600">(PKR {{ number_format($reportData['total_operating_expenses'] ?? 0, 2) }})</span>
                        </div>
                        <div class="flex justify-between font-bold text-base border-t-2 pt-2">
                            <span>Net Accounting Profit / (Loss):</span>
                            <span class="font-mono {{ ($reportData['net_income'] ?? 0) >= 0 ? 'text-green-700' : 'text-red-700' }}">
                                PKR {{ number_format($reportData['net_income'] ?? 0, 2) }}
                            </span>
                        </div>
                    </div>
                @endif
            </div>

        <!-- TAB 8: INVENTORY & SPARES REPORTS -->
        @elseif ($activeTab === 'inventory')
            <div class="space-y-6">
                <!-- Stock Summary Table -->
                <div class="bg-white shadow overflow-x-auto rounded-lg border border-gray-200">
                    <div class="p-3 bg-gray-50 border-b flex justify-between items-center">
                        <h4 class="font-bold text-gray-800 text-xs uppercase">Warehouse Stock Summary & Valuation</h4>
                        <span class="text-xs font-bold text-indigo-700 font-mono">Total Valuation: PKR {{ number_format($reportData['total_stock_valuation'] ?? 0, 2) }}</span>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50 font-medium text-gray-500 uppercase">
                            <tr>
                                <th class="px-4 py-2 text-left">Warehouse</th>
                                <th class="px-4 py-2 text-left">Item Code & Name</th>
                                <th class="px-4 py-2 text-left">Category</th>
                                <th class="px-4 py-2 text-right">On Hand</th>
                                <th class="px-4 py-2 text-right">Available</th>
                                <th class="px-4 py-2 text-right">Avg Cost</th>
                                <th class="px-4 py-2 text-right">Total Valuation</th>
                                <th class="px-4 py-2 text-center">Alert</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($reportData['summary'] ?? [] as $row)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 font-bold text-gray-900">{{ $row['warehouse_name'] }}</td>
                                    <td class="px-4 py-2">{{ $row['item_name'] }} <span class="font-mono text-gray-500">({{ $row['item_code'] }})</span></td>
                                    <td class="px-4 py-2 text-gray-600">{{ $row['category'] }}</td>
                                    <td class="px-4 py-2 text-right font-bold">{{ number_format($row['quantity_on_hand'], 2) }} {{ $row['uom'] }}</td>
                                    <td class="px-4 py-2 text-right font-bold text-green-700">{{ number_format($row['available_quantity'], 2) }}</td>
                                    <td class="px-4 py-2 text-right font-mono">{{ number_format($row['average_unit_cost'], 2) }}</td>
                                    <td class="px-4 py-2 text-right font-mono font-bold text-indigo-700">{{ number_format($row['total_valuation'], 2) }}</td>
                                    <td class="px-4 py-2 text-center">
                                        @if ($row['is_low_stock'])
                                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-red-100 text-red-800">Low Stock</span>
                                        @else
                                            <span class="px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-800">Normal</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="8" class="text-center py-6 text-gray-500">No warehouse stock records found.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Spare Parts Maintenance Consumption -->
                <div class="bg-white shadow overflow-x-auto rounded-lg border border-gray-200">
                    <div class="p-3 bg-gray-50 border-b">
                        <h4 class="font-bold text-gray-800 text-xs uppercase">Spare Parts Issued & Consumed in Maintenance (Job Cards)</h4>
                    </div>
                    <table class="min-w-full divide-y divide-gray-200 text-xs">
                        <thead class="bg-gray-50 font-medium text-gray-500 uppercase">
                            <tr>
                                <th class="px-4 py-2 text-left">Part Name & Code</th>
                                <th class="px-4 py-2 text-left">Vehicle Reg #</th>
                                <th class="px-4 py-2 text-left">Job Card #</th>
                                <th class="px-4 py-2 text-left">Date</th>
                                <th class="px-4 py-2 text-right">Qty</th>
                                <th class="px-4 py-2 text-right">Unit Cost</th>
                                <th class="px-4 py-2 text-right">Total Cost</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            @forelse ($reportData['parts_consumption'] ?? [] as $pc)
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 font-bold">{{ $pc['part_name'] }} <span class="font-mono text-gray-500">({{ $pc['part_number'] }})</span></td>
                                    <td class="px-4 py-2 text-indigo-600 font-bold">{{ $pc['vehicle_registration'] ?? 'Fleet' }}</td>
                                    <td class="px-4 py-2 font-mono">{{ $pc['job_card_number'] }}</td>
                                    <td class="px-4 py-2">{{ $pc['scheduled_date'] }}</td>
                                    <td class="px-4 py-2 text-right font-bold">{{ number_format($pc['quantity'], 2) }}</td>
                                    <td class="px-4 py-2 text-right font-mono">{{ number_format($pc['unit_cost'], 2) }}</td>
                                    <td class="px-4 py-2 text-right font-mono font-bold text-red-700">PKR {{ number_format($pc['total_cost'], 2) }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="7" class="text-center py-6 text-gray-500">No spare parts consumed in maintenance during this period.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    </div>
</div>

