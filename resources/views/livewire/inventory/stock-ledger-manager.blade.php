<div>
    <div class="space-y-6">
        <!-- Header -->
        <div class="sm:flex sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Stock Ledger & Movement Audit Trail</h1>
                <p class="mt-1 text-sm text-gray-500">Immutable ledger of all inventory transactions, issues, receipts, transfers, and physical count reconciliations.</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 grid grid-cols-1 md:grid-cols-5 gap-4 items-center">
            <div>
                <label class="block text-xs font-medium text-gray-500">Warehouse</label>
                <select wire:model.live="warehouseFilter" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-xs">
                    <option value="">All Warehouses</option>
                    @foreach ($warehouses as $wh)
                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500">Spare Part / Item</label>
                <select wire:model.live="itemFilter" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-xs">
                    <option value="">All Items</option>
                    @foreach ($items as $it)
                        <option value="{{ $it->id }}">{{ $it->name }} ({{ $it->item_code }})</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500">Transaction Type</label>
                <select wire:model.live="typeFilter" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-xs">
                    <option value="">All Types</option>
                    <option value="grn">Goods Receipt (GRN)</option>
                    <option value="issue_maintenance">Issue to Job Card (Maintenance)</option>
                    <option value="transfer_in">Transfer In</option>
                    <option value="transfer_out">Transfer Out</option>
                    <option value="purchase_return">Purchase Return</option>
                    <option value="damage">Damage Write-off</option>
                    <option value="wastage">Wastage</option>
                    <option value="physical_count">Physical Count Adjustment</option>
                    <option value="opening_stock">Opening Stock</option>
                </select>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500">From Date</label>
                <input type="date" wire:model.live="startDate" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-xs">
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-500">To Date</label>
                <input type="date" wire:model.live="endDate" class="mt-1 w-full rounded-md border-gray-300 shadow-sm text-xs">
            </div>
        </div>

        <!-- Ledger Table -->
        <div class="bg-white shadow overflow-hidden rounded-lg border border-gray-200">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Ref</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Warehouse</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Transaction Type</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">In Qty</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Out Qty</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Cost</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Value</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance Qty</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance Value</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200 font-mono text-xs">
                    @forelse ($entries as $e)
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap font-sans">
                                <div class="font-bold text-gray-900">{{ $e->transaction_date->format('d M Y') }}</div>
                                <div class="text-xs font-mono text-indigo-600">{{ $e->reference_number ?? '-' }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap font-sans text-gray-900">
                                {{ $e->warehouse?->name }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap font-sans">
                                <div class="font-medium text-gray-900">{{ $e->item?->name }}</div>
                                <div class="text-xs font-mono text-gray-500">{{ $e->item?->item_code }}</div>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap font-sans">
                                <span class="px-2 py-0.5 rounded-full text-xs font-semibold
                                    @if(str_contains($e->transaction_type, 'issue') || str_contains($e->transaction_type, 'damage') || str_contains($e->transaction_type, 'loss'))
                                        bg-red-100 text-red-800
                                    @elseif(str_contains($e->transaction_type, 'grn') || str_contains($e->transaction_type, 'gain') || str_contains($e->transaction_type, 'opening'))
                                        bg-green-100 text-green-800
                                    @else
                                        bg-blue-100 text-blue-800
                                    @endif
                                ">
                                    {{ ucwords(str_replace('_', ' ', $e->transaction_type)) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right font-bold text-green-700">
                                {{ $e->quantity_in > 0 ? number_format($e->quantity_in, 2) : '-' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right font-bold text-red-700">
                                {{ $e->quantity_out > 0 ? number_format($e->quantity_out, 2) : '-' }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right text-gray-800">
                                {{ number_format($e->unit_cost, 2) }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right font-bold text-gray-900">
                                {{ number_format($e->total_cost, 2) }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right font-bold text-indigo-900">
                                {{ number_format($e->balance_quantity, 2) }}
                            </td>
                            <td class="px-4 py-3 whitespace-nowrap text-right font-bold text-indigo-900">
                                {{ number_format($e->balance_valuation, 2) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-8 text-center text-sm font-sans text-gray-500">No stock ledger entries found.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
            <div class="px-6 py-3 border-t">
                {{ $entries->links() }}
            </div>
        </div>
    </div>
</div>

