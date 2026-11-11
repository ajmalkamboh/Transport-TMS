<div>
    <div class="space-y-6">
        <!-- Header -->
        <div class="sm:flex sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Warehouse Stocks & Multi-Location Inventory</h1>
                <p class="mt-1 text-sm text-gray-500">Track real-time stock balances, warehouse storage, inter-warehouse transfers, and stock adjustments.</p>
            </div>
            <div class="mt-4 sm:mt-0 flex flex-wrap gap-2">
                <button wire:click="openCreateWarehouseModal" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                    + New Warehouse
                </button>
                <button wire:click="$set('showTransferModal', true)" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    &harr; Transfer Stock
                </button>
                <button wire:click="$set('showAdjustmentModal', true)" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    &plusmn; Stock Adjustment / Count
                </button>
            </div>
        </div>

        @if (session()->has('message'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm font-medium text-green-800">{{ session('message') }}</p>
            </div>
        @endif

        @if (session()->has('error'))
            <div class="rounded-md bg-red-50 p-4 border border-red-200">
                <p class="text-sm font-medium text-red-800">{{ session('error') }}</p>
            </div>
        @endif

        <!-- Navigation Tabs -->
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8">
                <button wire:click="$set('activeTab', 'stock')" class="{{ $activeTab === 'stock' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }} py-4 px-1 border-b-2 font-medium text-sm">
                    Stock Balances ({{ $stocks->total() }})
                </button>
                <button wire:click="$set('activeTab', 'warehouses')" class="{{ $activeTab === 'warehouses' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }} py-4 px-1 border-b-2 font-medium text-sm">
                    Warehouses ({{ $warehouses->count() }})
                </button>
                <button wire:click="$set('activeTab', 'transfers')" class="{{ $activeTab === 'transfers' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }} py-4 px-1 border-b-2 font-medium text-sm">
                    Recent Transfers ({{ count($transfers) }})
                </button>
                <button wire:click="$set('activeTab', 'adjustments')" class="{{ $activeTab === 'adjustments' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }} py-4 px-1 border-b-2 font-medium text-sm">
                    Recent Adjustments ({{ count($adjustments) }})
                </button>
            </nav>
        </div>

        @if ($activeTab === 'stock')
            <!-- Filters -->
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 flex flex-wrap gap-4 items-center justify-between">
                <div class="flex-1 min-w-[240px]">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search item name or code..." class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div class="w-64">
                    <select wire:model.live="selectedWarehouseId" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">All Warehouses</option>
                        @foreach ($warehouses as $wh)
                            <option value="{{ $wh->id }}">{{ $wh->name }} ({{ $wh->code }})</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Stocks Table -->
            <div class="bg-white shadow overflow-hidden rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Warehouse</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item / Spare Part</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">On Hand</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Allocated</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Available</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Unit Cost</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Valuation</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($stocks as $stk)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900">
                                    {{ $stk->warehouse?->name }}
                                    <span class="block text-xs text-gray-500 font-mono">{{ $stk->warehouse?->code }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">{{ $stk->item?->name }}</div>
                                    <div class="text-xs font-mono text-indigo-600">{{ $stk->item?->item_code }} &bull; {{ $stk->item?->primaryUom?->name }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-gray-900">
                                    {{ number_format($stk->quantity_on_hand, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-500">
                                    {{ number_format($stk->quantity_allocated, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-green-700">
                                    {{ number_format($stk->available_quantity, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-mono text-gray-800">
                                    PKR {{ number_format($stk->average_unit_cost, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-mono font-bold text-indigo-700">
                                    PKR {{ number_format($stk->total_valuation, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">No warehouse stock balances recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-6 py-3 border-t border-gray-200">
                    {{ $stocks->links() }}
                </div>
            </div>
        @elseif ($activeTab === 'warehouses')
            <div class="bg-white shadow overflow-hidden rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Warehouse Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Warehouse Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Branch</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Person</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($warehouses as $wh)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap font-mono font-semibold text-sm text-indigo-600">{{ $wh->code }}</td>
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-sm text-gray-900">
                                    {{ $wh->name }}
                                    @if ($wh->is_default)
                                        <span class="ml-2 inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-blue-100 text-blue-800">Default</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $wh->branch?->name ?? 'Company-Wide' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $wh->contact_person ?? '-' }} ({{ $wh->phone ?? '-' }})</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button wire:click="editWarehouse({{ $wh->id }})" class="text-indigo-600 hover:text-indigo-900">Edit</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">No warehouses configured. Click "+ New Warehouse" to create one.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @elseif ($activeTab === 'transfers')
            <div class="bg-white shadow overflow-hidden rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transfer #</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From &rarr; To</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Value</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($transfers as $tr)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap font-mono font-bold text-sm text-indigo-600">{{ $tr->transfer_number }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $tr->transfer_date->format('d M Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                                    {{ $tr->fromWarehouse?->name }} &rarr; {{ $tr->toWarehouse?->name }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-mono font-bold text-gray-900">
                                    PKR {{ number_format($tr->total_cost, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">{{ ucfirst($tr->status) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">No stock transfers recorded yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @elseif ($activeTab === 'adjustments')
            <div class="bg-white shadow overflow-hidden rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Adjustment #</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Warehouse</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type & Reason</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Net Cost</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($adjustments as $adj)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap font-mono font-bold text-sm text-indigo-600">{{ $adj->adjustment_number }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $adj->adjustment_date->format('d M Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $adj->warehouse?->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <span class="font-semibold uppercase text-xs">{{ $adj->adjustment_type }}:</span> {{ $adj->reason }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-mono font-bold text-gray-900">
                                    PKR {{ number_format($adj->total_cost, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">{{ ucfirst($adj->status) }}</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">No stock adjustments recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        <!-- Warehouse Modal -->
        @if ($showWarehouseModal)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-500 bg-opacity-75 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg max-w-lg w-full p-6 space-y-4">
                    <div class="flex justify-between items-center border-b pb-3">
                        <h3 class="text-lg font-bold text-gray-900">{{ $isEditingWarehouse ? 'Edit Warehouse' : 'New Warehouse' }}</h3>
                        <button wire:click="$set('showWarehouseModal', false)" class="text-gray-400 hover:text-gray-500">&times;</button>
                    </div>

                    <form wire:submit.prevent="saveWarehouse" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Code *</label>
                                <input type="text" wire:model="warehouse_code" placeholder="e.g. WH-KHI-01" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                @error('warehouse_code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Branch</label>
                                <select wire:model="warehouse_branch_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                    <option value="">Company-Wide / Central</option>
                                    @foreach ($branches as $br)
                                        <option value="{{ $br->id }}">{{ $br->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Warehouse Name *</label>
                            <input type="text" wire:model="warehouse_name" placeholder="e.g. Karachi Central Workshop Depot" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            @error('warehouse_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Contact Person</label>
                                <input type="text" wire:model="warehouse_contact_person" placeholder="Store In-Charge" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Phone</label>
                                <input type="text" wire:model="warehouse_phone" placeholder="0300-1234567" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Address / Location</label>
                            <textarea wire:model="warehouse_address" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"></textarea>
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" wire:model="warehouse_is_default" id="wh_default" class="h-4 w-4 rounded border-gray-300 text-indigo-600">
                            <label for="wh_default" class="ml-2 block text-sm text-gray-900">Set as default warehouse</label>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4 border-t">
                            <button type="button" wire:click="$set('showWarehouseModal', false)" class="px-4 py-2 border rounded-md text-gray-700 bg-white">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md">Save Warehouse</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Transfer Modal -->
        @if ($showTransferModal)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-500 bg-opacity-75 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg max-w-lg w-full p-6 space-y-4">
                    <div class="flex justify-between items-center border-b pb-3">
                        <h3 class="text-lg font-bold text-gray-900">Inter-Warehouse Stock Transfer</h3>
                        <button wire:click="$set('showTransferModal', false)" class="text-gray-400 hover:text-gray-500">&times;</button>
                    </div>

                    <form wire:submit.prevent="executeTransfer" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Source Warehouse *</label>
                                <select wire:model="transfer_from_warehouse_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                    <option value="">Select Origin</option>
                                    @foreach ($warehouses as $wh)
                                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                    @endforeach
                                </select>
                                @error('transfer_from_warehouse_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Destination Warehouse *</label>
                                <select wire:model="transfer_to_warehouse_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                    <option value="">Select Destination</option>
                                    @foreach ($warehouses as $wh)
                                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                    @endforeach
                                </select>
                                @error('transfer_to_warehouse_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Inventory Item *</label>
                            <select wire:model="transfer_item_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                <option value="">Select Item to Transfer</option>
                                @foreach ($items as $it)
                                    <option value="{{ $it->id }}">{{ $it->name }} ({{ $it->item_code }})</option>
                                @endforeach
                            </select>
                            @error('transfer_item_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Transfer Quantity *</label>
                            <input type="number" step="0.01" wire:model="transfer_quantity" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            @error('transfer_quantity') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Transfer Dispatch Notes</label>
                            <textarea wire:model="transfer_notes" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"></textarea>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4 border-t">
                            <button type="button" wire:click="$set('showTransferModal', false)" class="px-4 py-2 border rounded-md text-gray-700 bg-white">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md">Dispatch Transfer</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Adjustment Modal -->
        @if ($showAdjustmentModal)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-500 bg-opacity-75 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg max-w-lg w-full p-6 space-y-4">
                    <div class="flex justify-between items-center border-b pb-3">
                        <h3 class="text-lg font-bold text-gray-900">Stock Adjustment & Count Reconciliation</h3>
                        <button wire:click="$set('showAdjustmentModal', false)" class="text-gray-400 hover:text-gray-500">&times;</button>
                    </div>

                    <form wire:submit.prevent="executeAdjustment" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Warehouse *</label>
                                <select wire:model="adjustment_warehouse_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                    <option value="">Select Warehouse</option>
                                    @foreach ($warehouses as $wh)
                                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                    @endforeach
                                </select>
                                @error('adjustment_warehouse_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Adjustment Type *</label>
                                <select wire:model="adjustment_type" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                    <option value="physical_count">Physical Stock Count</option>
                                    <option value="damage">Transit / Workshop Damage</option>
                                    <option value="wastage">Spillage / Wastage</option>
                                    <option value="gain">Surplus Found (Gain)</option>
                                    <option value="loss">Stock Loss / Shortage</option>
                                </select>
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Inventory Item *</label>
                            <select wire:model="adjustment_item_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                <option value="">Select Item</option>
                                @foreach ($items as $it)
                                    <option value="{{ $it->id }}">{{ $it->name }} ({{ $it->item_code }})</option>
                                @endforeach
                            </select>
                            @error('adjustment_item_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Actual Counted / New Quantity *</label>
                            <input type="number" step="0.01" wire:model="adjustment_counted_quantity" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            @error('adjustment_counted_quantity') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Reason / Justification *</label>
                            <input type="text" wire:model="adjustment_reason" placeholder="e.g. Annual physical count audit adjustment" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            @error('adjustment_reason') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex justify-end space-x-3 pt-4 border-t">
                            <button type="button" wire:click="$set('showAdjustmentModal', false)" class="px-4 py-2 border rounded-md text-gray-700 bg-white">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md">Post Adjustment</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>

