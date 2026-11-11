<div>
    <div class="space-y-6">
        <!-- Header -->
        <div class="sm:flex sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Procurement & Trade Payables</h1>
                <p class="mt-1 text-sm text-gray-500">Manage suppliers, purchase orders, goods receipt notes (GRN), supplier bills, and AP disbursements.</p>
            </div>
            <div class="mt-4 sm:mt-0 flex flex-wrap gap-2">
                <button wire:click="$set('showPoModal', true)" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                    + New Purchase Order
                </button>
                <button wire:click="openCreateSupplierModal" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    + New Supplier
                </button>
            </div>
        </div>

        @if (session()->has('message'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <p class="text-sm font-medium text-green-800">{{ session('message') }}</p>
            </div>
        @endif

        <!-- Navigation Tabs -->
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8">
                <button wire:click="$set('activeTab', 'orders')" class="{{ $activeTab === 'orders' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }} py-4 px-1 border-b-2 font-medium text-sm">
                    Purchase Orders
                </button>
                <button wire:click="$set('activeTab', 'grn')" class="{{ $activeTab === 'grn' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }} py-4 px-1 border-b-2 font-medium text-sm">
                    Goods Receipts (GRN)
                </button>
                <button wire:click="$set('activeTab', 'bills')" class="{{ $activeTab === 'bills' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }} py-4 px-1 border-b-2 font-medium text-sm">
                    Supplier Bills
                </button>
                <button wire:click="$set('activeTab', 'payments')" class="{{ $activeTab === 'payments' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }} py-4 px-1 border-b-2 font-medium text-sm">
                    Supplier Payments
                </button>
                <button wire:click="$set('activeTab', 'suppliers')" class="{{ $activeTab === 'suppliers' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700' }} py-4 px-1 border-b-2 font-medium text-sm">
                    Trade Suppliers ({{ $suppliers->count() }})
                </button>
            </nav>
        </div>

        @if ($activeTab === 'orders')
            <div class="bg-white shadow overflow-hidden rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO Number</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destination Warehouse</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($purchaseOrders as $po)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap font-mono font-bold text-sm text-indigo-600">{{ $po->po_number }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $po->order_date->format('d M Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $po->supplier?->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $po->warehouse?->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-mono font-bold text-gray-900">
                                    PKR {{ number_format($po->total_amount, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if ($po->status === 'approved')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">Approved</span>
                                    @elseif ($po->status === 'received')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Received</span>
                                    @elseif ($po->status === 'partially_received')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Partial</span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Draft</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                    @if ($po->status === 'draft')
                                        <button wire:click="approveOrder({{ $po->id }})" class="text-green-600 hover:text-green-900">Approve</button>
                                    @elseif (in_array($po->status, ['approved', 'partially_received']))
                                        <button wire:click="openReceivePoModal({{ $po->id }})" class="text-indigo-600 hover:text-indigo-900 font-bold">Receive GRN</button>
                                    @endif
                                    <a href="{{ route('print.purchase-order', $po->id) }}" target="_blank" class="text-gray-500 hover:text-gray-700">Print</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">No purchase orders created yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-6 py-3 border-t">
                    {{ $purchaseOrders->links() }}
                </div>
            </div>
        @elseif ($activeTab === 'grn')
            <div class="bg-white shadow overflow-hidden rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">GRN #</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Warehouse</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PO Reference</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Received Value</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($grns as $grn)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap font-mono font-bold text-sm text-indigo-600">{{ $grn->grn_number }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $grn->received_date->format('d M Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $grn->supplier?->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $grn->warehouse?->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-500">{{ $grn->purchaseOrder?->po_number ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-mono font-bold text-gray-900">
                                    PKR {{ number_format($grn->total_received_amount, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium space-x-2">
                                    <button wire:click="openBillModalForGrn({{ $grn->id }})" class="text-indigo-600 hover:text-indigo-900 font-bold">Generate Bill</button>
                                    <a href="{{ route('print.goods-receipt', $grn->id) }}" target="_blank" class="text-gray-500 hover:text-gray-700">Print</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-6 py-8 text-center text-sm text-gray-500">No goods receipts registered.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-6 py-3 border-t">
                    {{ $grns->links() }}
                </div>
            </div>
        @elseif ($activeTab === 'bills')
            <div class="bg-white shadow overflow-hidden rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bill #</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date / Due</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total Amount</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Paid Amount</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Balance Due</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($bills as $b)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap font-mono font-bold text-sm text-indigo-600">{{ $b->bill_number }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    {{ $b->bill_date->format('d M Y') }}
                                    <span class="block text-xs text-red-500">Due: {{ $b->due_date->format('d M Y') }}</span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $b->supplier?->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-mono font-bold text-gray-900">
                                    PKR {{ number_format($b->total_amount, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-mono text-green-700">
                                    PKR {{ number_format($b->paid_amount, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-mono font-bold text-red-700">
                                    PKR {{ number_format($b->balance_due, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if ($b->status === 'paid')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Paid</span>
                                    @elseif ($b->status === 'partially_paid')
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Partial</span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Unpaid</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    @if ($b->balance_due > 0)
                                        <button wire:click="openPaymentModal({{ $b->id }})" class="text-indigo-600 hover:text-indigo-900 font-bold">Pay Bill</button>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500">No supplier bills generated yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-6 py-3 border-t">
                    {{ $bills->links() }}
                </div>
            </div>
        @elseif ($activeTab === 'payments')
            <div class="bg-white shadow overflow-hidden rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment #</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bill Reference</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Method</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Amount Paid</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($payments as $p)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap font-mono font-bold text-sm text-indigo-600">{{ $p->payment_number }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $p->payment_date->format('d M Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{{ $p->supplier?->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-500">{{ $p->bill?->bill_number ?? 'Direct Supplier Credit' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 capitalize">{{ str_replace('_', ' ', $p->payment_method) }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-mono font-bold text-green-700">
                                    PKR {{ number_format($p->amount, 2) }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">No supplier payments recorded.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-6 py-3 border-t">
                    {{ $payments->links() }}
                </div>
            </div>
        @elseif ($activeTab === 'suppliers')
            <div class="bg-white shadow overflow-hidden rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Supplier Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name & Contact</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone & Email</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Terms</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Current AP Balance</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($suppliers as $s)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap font-mono font-bold text-sm text-indigo-600">{{ $s->code }}</td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-gray-900">{{ $s->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $s->contact_name ?? 'Vendor Representative' }}</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $s->phone ?? '-' }} &bull; {{ $s->email ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{{ $s->payment_terms_days }} Days Net</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-mono font-bold text-red-700">
                                    PKR {{ number_format($s->current_balance, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">No trade suppliers registered yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        <!-- Supplier Modal -->
        @if ($showSupplierModal)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-500 bg-opacity-75 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg max-w-lg w-full p-6 space-y-4">
                    <div class="flex justify-between items-center border-b pb-3">
                        <h3 class="text-lg font-bold text-gray-900">Register Trade Supplier</h3>
                        <button wire:click="$set('showSupplierModal', false)" class="text-gray-400 hover:text-gray-500">&times;</button>
                    </div>

                    <form wire:submit.prevent="saveSupplier" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Supplier Code *</label>
                                <input type="text" wire:model="supplier_code" placeholder="e.g. SUP-001" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                @error('supplier_code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Credit Terms (Days) *</label>
                                <input type="number" wire:model="supplier_payment_terms_days" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Supplier / Company Name *</label>
                            <input type="text" wire:model="supplier_name" placeholder="e.g. General Tyre & Rubber Co." class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            @error('supplier_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Contact Person</label>
                                <input type="text" wire:model="supplier_contact_name" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Phone</label>
                                <input type="text" wire:model="supplier_phone" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Email</label>
                                <input type="email" wire:model="supplier_email" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Tax / NTN Number</label>
                                <input type="text" wire:model="supplier_tax_number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Address</label>
                            <textarea wire:model="supplier_address" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"></textarea>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4 border-t">
                            <button type="button" wire:click="$set('showSupplierModal', false)" class="px-4 py-2 border rounded-md text-gray-700 bg-white">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md">Save Supplier</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Purchase Order Modal -->
        @if ($showPoModal)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-500 bg-opacity-75 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg max-w-3xl w-full p-6 space-y-4">
                    <div class="flex justify-between items-center border-b pb-3">
                        <h3 class="text-lg font-bold text-gray-900">Create Purchase Order (PO)</h3>
                        <button wire:click="$set('showPoModal', false)" class="text-gray-400 hover:text-gray-500">&times;</button>
                    </div>

                    <form wire:submit.prevent="savePurchaseOrder" class="space-y-4">
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Supplier *</label>
                                <select wire:model="po_supplier_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                    <option value="">Select Supplier</option>
                                    @foreach ($suppliers as $s)
                                        <option value="{{ $s->id }}">{{ $s->name }}</option>
                                    @endforeach
                                </select>
                                @error('po_supplier_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Destination Warehouse *</label>
                                <select wire:model="po_warehouse_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                    <option value="">Select Warehouse</option>
                                    @foreach ($warehouses as $wh)
                                        <option value="{{ $wh->id }}">{{ $wh->name }}</option>
                                    @endforeach
                                </select>
                                @error('po_warehouse_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Order Date *</label>
                                <input type="date" wire:model="po_order_date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            </div>
                        </div>

                        <!-- Items Rows -->
                        <div class="border rounded-md p-3 space-y-3">
                            <div class="flex justify-between items-center">
                                <h4 class="text-sm font-bold text-gray-800">Order Items</h4>
                                <button type="button" wire:click="addPoItemRow" class="text-xs bg-indigo-50 text-indigo-600 font-bold px-2 py-1 rounded">+ Add Item</button>
                            </div>

                            @foreach ($po_items as $index => $item)
                                <div class="grid grid-cols-12 gap-2 items-center">
                                    <div class="col-span-5">
                                        <select wire:model="po_items.{{ $index }}.inventory_item_id" class="w-full rounded-md border-gray-300 shadow-sm text-xs">
                                            <option value="">Select Item</option>
                                            @foreach ($items as $it)
                                                <option value="{{ $it->id }}">{{ $it->name }} ({{ $it->item_code }})</option>
                                            @endforeach
                                        </select>
                                    </div>
                                    <div class="col-span-2">
                                        <input type="number" step="0.01" wire:model="po_items.{{ $index }}.quantity_ordered" placeholder="Qty" class="w-full rounded-md border-gray-300 shadow-sm text-xs">
                                    </div>
                                    <div class="col-span-3">
                                        <input type="number" step="0.01" wire:model="po_items.{{ $index }}.unit_rate" placeholder="Rate (PKR)" class="w-full rounded-md border-gray-300 shadow-sm text-xs">
                                    </div>
                                    <div class="col-span-2 text-right">
                                        @if(count($po_items) > 1)
                                            <button type="button" wire:click="removePoItemRow({{ $index }})" class="text-red-500 hover:text-red-700 text-xs">&times; Remove</button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Notes / Remarks</label>
                            <input type="text" wire:model="po_notes" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                        </div>

                        <div class="flex justify-end space-x-3 pt-4 border-t">
                            <button type="button" wire:click="$set('showPoModal', false)" class="px-4 py-2 border rounded-md text-gray-700 bg-white">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md">Save Purchase Order</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- GRN Modal -->
        @if ($showGrnModal)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-500 bg-opacity-75 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg max-w-2xl w-full p-6 space-y-4">
                    <div class="flex justify-between items-center border-b pb-3">
                        <h3 class="text-lg font-bold text-gray-900">Receive Goods (GRN)</h3>
                        <button wire:click="$set('showGrnModal', false)" class="text-gray-400 hover:text-gray-500">&times;</button>
                    </div>

                    <form wire:submit.prevent="saveGoodsReceipt" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Supplier Delivery Challan #</label>
                            <input type="text" wire:model="grn_challan_number" placeholder="e.g. DC-7896" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                        </div>

                        <div class="border rounded-md p-3 space-y-2">
                            <h4 class="text-sm font-bold text-gray-800">Items Being Received</h4>
                            @foreach ($grn_items as $idx => $gi)
                                <div class="grid grid-cols-12 gap-2 items-center text-xs">
                                    <div class="col-span-6 font-medium text-gray-900">{{ $gi['item_name'] ?? 'Item' }}</div>
                                    <div class="col-span-3">
                                        <label class="block text-gray-500">Qty Received</label>
                                        <input type="number" step="0.01" wire:model="grn_items.{{ $idx }}.quantity_received" class="w-full rounded border-gray-300 text-xs">
                                    </div>
                                    <div class="col-span-3">
                                        <label class="block text-gray-500">Unit Rate (PKR)</label>
                                        <input type="number" step="0.01" wire:model="grn_items.{{ $idx }}.unit_rate" class="w-full rounded border-gray-300 text-xs">
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <div class="flex justify-end space-x-3 pt-4 border-t">
                            <button type="button" wire:click="$set('showGrnModal', false)" class="px-4 py-2 border rounded-md text-gray-700 bg-white">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md">Confirm Goods Receipt</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Bill Modal -->
        @if ($showBillModal)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-500 bg-opacity-75 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg max-w-2xl w-full p-6 space-y-4">
                    <div class="flex justify-between items-center border-b pb-3">
                        <h3 class="text-lg font-bold text-gray-900">Generate Supplier Bill</h3>
                        <button wire:click="$set('showBillModal', false)" class="text-gray-400 hover:text-gray-500">&times;</button>
                    </div>

                    <form wire:submit.prevent="saveSupplierBill" class="space-y-4">
                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Supplier Invoice / Bill # *</label>
                                <input type="text" wire:model="bill_number" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Bill Date *</label>
                                <input type="date" wire:model="bill_date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Due Date *</label>
                                <input type="date" wire:model="bill_due_date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            </div>
                        </div>

                        <div class="border rounded-md p-3 space-y-2">
                            <h4 class="text-sm font-bold text-gray-800">Bill Lines</h4>
                            @foreach ($bill_items as $bi)
                                <div class="flex justify-between text-xs py-1 border-b">
                                    <span>{{ $bi['description'] }} (&times; {{ $bi['quantity'] }})</span>
                                    <span class="font-mono font-bold">PKR {{ number_format($bi['quantity'] * $bi['unit_rate'], 2) }}</span>
                                </div>
                            @endforeach
                        </div>

                        <p class="text-xs text-indigo-700 bg-indigo-50 p-2 rounded">
                            Notice: Generating this bill will post to General Ledger:
                            <strong>Dr. 1300-SPARES-INVENTORY (Asset)</strong> and <strong>Cr. 2110-ACCOUNTS-PAYABLE-SUPPLIERS (Liability)</strong>.
                        </p>

                        <div class="flex justify-end space-x-3 pt-4 border-t">
                            <button type="button" wire:click="$set('showBillModal', false)" class="px-4 py-2 border rounded-md text-gray-700 bg-white">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md">Post Bill</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Payment Modal -->
        @if ($showPaymentModal)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-500 bg-opacity-75 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg max-w-md w-full p-6 space-y-4">
                    <div class="flex justify-between items-center border-b pb-3">
                        <h3 class="text-lg font-bold text-gray-900">Record Supplier Payment</h3>
                        <button wire:click="$set('showPaymentModal', false)" class="text-gray-400 hover:text-gray-500">&times;</button>
                    </div>

                    <form wire:submit.prevent="saveSupplierPayment" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Amount (PKR) *</label>
                            <input type="number" step="0.01" wire:model="payment_amount" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Payment Method *</label>
                                <select wire:model="payment_method" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                    <option value="bank_transfer">Bank Transfer</option>
                                    <option value="cheque">Cheque</option>
                                    <option value="cash">Cash</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Payment Date *</label>
                                <input type="date" wire:model="payment_date" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Cheque / Reference #</label>
                            <input type="text" wire:model="payment_reference" placeholder="e.g. CHQ-990812" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                        </div>

                        <div class="flex justify-end space-x-3 pt-4 border-t">
                            <button type="button" wire:click="$set('showPaymentModal', false)" class="px-4 py-2 border rounded-md text-gray-700 bg-white">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md">Post Payment</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
