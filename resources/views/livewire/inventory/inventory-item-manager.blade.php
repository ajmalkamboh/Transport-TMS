<div>
    <div class="space-y-6">
        <!-- Header -->
        <div class="sm:flex sm:items-center sm:justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Inventory Items & Masters</h1>
                <p class="mt-1 text-sm text-gray-500">Fleet spare parts, tyres, lubricants, workshop consumables, and unit conversions.</p>
            </div>
            <div class="mt-4 sm:mt-0 flex flex-wrap gap-2">
                <button wire:click="openCreateItemModal" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700">
                    <svg class="-ml-1 mr-2 h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                    </svg>
                    New Spare Part / Item
                </button>
                <button wire:click="$set('showCategoryModal', true)" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    + Category
                </button>
                <button wire:click="$set('showUomModal', true)" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    + Unit (UOM)
                </button>
                <button wire:click="$set('showConversionModal', true)" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    + Conversion
                </button>
            </div>
        </div>

        @if (session()->has('message'))
            <div class="rounded-md bg-green-50 p-4 border border-green-200">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-green-400" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm font-medium text-green-800">{{ session('message') }}</p>
                    </div>
                </div>
            </div>
        @endif

        <!-- Navigation Tabs -->
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-8" aria-label="Tabs">
                <button wire:click="$set('activeTab', 'items')" class="{{ $activeTab === 'items' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Inventory Items ({{ $items->total() }})
                </button>
                <button wire:click="$set('activeTab', 'categories')" class="{{ $activeTab === 'categories' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Categories ({{ $categories->count() }})
                </button>
                <button wire:click="$set('activeTab', 'uom')" class="{{ $activeTab === 'uom' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    Units of Measure ({{ $uoms->count() }})
                </button>
                <button wire:click="$set('activeTab', 'conversions')" class="{{ $activeTab === 'conversions' ? 'border-indigo-500 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} whitespace-nowrap py-4 px-1 border-b-2 font-medium text-sm">
                    UOM Conversions ({{ $conversions->count() }})
                </button>
            </nav>
        </div>

        @if ($activeTab === 'items')
            <!-- Filters -->
            <div class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 flex flex-wrap gap-4 items-center justify-between">
                <div class="flex-1 min-w-[240px]">
                    <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search item code, name, SKU barcode..." class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                </div>
                <div class="w-48">
                    <select wire:model.live="categoryFilter" class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                        <option value="">All Categories</option>
                        @foreach ($categories as $cat)
                            <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <!-- Items Table -->
            <div class="bg-white shadow overflow-hidden rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Item Code & Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Primary UOM</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Total On Hand</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Reorder Level</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Avg Cost (PKR)</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($items as $item)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-semibold text-gray-900">{{ $item->name }}</div>
                                    <div class="text-xs font-mono text-indigo-600">{{ $item->item_code }} @if($item->sku) &bull; SKU: {{ $item->sku }} @endif</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    {{ $item->category?->name ?? 'General' }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                    {{ $item->primaryUom?->name }} ({{ $item->primaryUom?->code }})
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-bold text-gray-900">
                                    {{ number_format($item->total_stock_on_hand, 2) }}
                                    @if($item->isLowStock())
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 ml-1">Low</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right text-gray-600">
                                    {{ number_format($item->reorder_level, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-mono font-medium text-gray-900">
                                    {{ number_format($item->average_cost, 2) }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    @if ($item->is_active)
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                    @else
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-gray-100 text-gray-800">Inactive</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <button wire:click="editItem({{ $item->id }})" class="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-8 text-center text-sm text-gray-500">No inventory items found. Click "New Spare Part / Item" to add one.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
                <div class="px-6 py-3 border-t border-gray-200">
                    {{ $items->links() }}
                </div>
            </div>
        @elseif ($activeTab === 'categories')
            <div class="bg-white shadow overflow-hidden rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($categories as $cat)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap font-mono font-semibold text-sm text-indigo-600">{{ $cat->code }}</td>
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-sm text-gray-900">{{ $cat->name }}</td>
                                <td class="px-6 py-4 text-sm text-gray-500">{{ $cat->description ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">No inventory categories created.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @elseif ($activeTab === 'uom')
            <div class="bg-white shadow overflow-hidden rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">UOM Code</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Unit Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Symbol</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($uoms as $u)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap font-mono font-semibold text-sm text-indigo-600">{{ $u->code }}</td>
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-sm text-gray-900">{{ $u->name }}</td>
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-sm text-gray-500">{{ $u->symbol ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Active</span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">No units of measurement created.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @elseif ($activeTab === 'conversions')
            <div class="bg-white shadow overflow-hidden rounded-lg border border-gray-200">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">From Unit</th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Equals</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">To Unit</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Conversion Factor</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        @forelse ($conversions as $conv)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-sm text-gray-900">1 {{ $conv->fromUom?->name }} ({{ $conv->fromUom?->code }})</td>
                                <td class="px-6 py-4 whitespace-nowrap text-center font-bold text-gray-400">=</td>
                                <td class="px-6 py-4 whitespace-nowrap font-medium text-sm text-gray-900">{{ $conv->toUom?->name }} ({{ $conv->toUom?->code }})</td>
                                <td class="px-6 py-4 whitespace-nowrap text-right font-mono font-bold text-indigo-600 text-sm">{{ number_format($conv->conversion_factor, 4) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-8 text-center text-sm text-gray-500">No UOM conversions configured.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif

        <!-- Item Modal -->
        @if ($showItemModal)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-500 bg-opacity-75 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg max-w-2xl w-full p-6 space-y-4">
                    <div class="flex justify-between items-center border-b pb-3">
                        <h3 class="text-lg font-bold text-gray-900">{{ $isEditingItem ? 'Edit Spare Part / Item' : 'New Spare Part / Item' }}</h3>
                        <button wire:click="$set('showItemModal', false)" class="text-gray-400 hover:text-gray-500">&times;</button>
                    </div>

                    <form wire:submit.prevent="saveItem" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Item Code *</label>
                                <input type="text" wire:model="item_code" placeholder="e.g. FLT-001" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('item_code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">SKU / Barcode</label>
                                <input type="text" wire:model="sku" placeholder="e.g. 8964000123" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('sku') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Item / Part Name *</label>
                            <input type="text" wire:model="name" placeholder="e.g. Fuel Filter Heavy Duty Donaldson" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                            @error('name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Category</label>
                                <select wire:model="category_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select Category</option>
                                    @foreach ($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }} ({{ $cat->code }})</option>
                                    @endforeach
                                </select>
                                @error('category_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Primary Unit of Measure (UOM) *</label>
                                <select wire:model="primary_uom_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                    <option value="">Select UOM</option>
                                    @foreach ($uoms as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->code }})</option>
                                    @endforeach
                                </select>
                                @error('primary_uom_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Reorder Level *</label>
                                <input type="number" step="0.01" wire:model="reorder_level" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('reorder_level') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Safety Stock *</label>
                                <input type="number" step="0.01" wire:model="safety_stock" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('safety_stock') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Standard Cost (PKR)</label>
                                <input type="number" step="0.01" wire:model="average_cost" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                @error('average_cost') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Preferred Supplier</label>
                            <select wire:model="preferred_supplier_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm">
                                <option value="">Select Supplier</option>
                                @foreach ($suppliers as $sup)
                                    <option value="{{ $sup->id }}">{{ $sup->name }} ({{ $sup->code }})</option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Description / Specifications</label>
                            <textarea wire:model="description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm"></textarea>
                        </div>

                        <div class="flex items-center">
                            <input type="checkbox" wire:model="is_active" id="item_active" class="h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <label for="item_active" class="ml-2 block text-sm text-gray-900">Active for workshop and purchase operations</label>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4 border-t">
                            <button type="button" wire:click="$set('showItemModal', false)" class="px-4 py-2 border rounded-md text-gray-700 bg-white hover:bg-gray-50">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md hover:bg-indigo-700">Save Item</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Category Modal -->
        @if ($showCategoryModal)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-500 bg-opacity-75 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg max-w-md w-full p-6 space-y-4">
                    <div class="flex justify-between items-center border-b pb-3">
                        <h3 class="text-lg font-bold text-gray-900">Add Inventory Category</h3>
                        <button wire:click="$set('showCategoryModal', false)" class="text-gray-400 hover:text-gray-500">&times;</button>
                    </div>

                    <form wire:submit.prevent="saveCategory" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Category Name *</label>
                            <input type="text" wire:model="category_name" placeholder="e.g. Lubricants & Oils" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            @error('category_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Category Code *</label>
                            <input type="text" wire:model="category_code" placeholder="e.g. LUB" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            @error('category_code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <textarea wire:model="category_description" rows="2" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm"></textarea>
                        </div>

                        <div class="flex justify-end space-x-3 pt-4 border-t">
                            <button type="button" wire:click="$set('showCategoryModal', false)" class="px-4 py-2 border rounded-md text-gray-700 bg-white">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md">Save Category</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- UOM Modal -->
        @if ($showUomModal)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-500 bg-opacity-75 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg max-w-md w-full p-6 space-y-4">
                    <div class="flex justify-between items-center border-b pb-3">
                        <h3 class="text-lg font-bold text-gray-900">Add Unit of Measurement (UOM)</h3>
                        <button wire:click="$set('showUomModal', false)" class="text-gray-400 hover:text-gray-500">&times;</button>
                    </div>

                    <form wire:submit.prevent="saveUom" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700">Unit Name *</label>
                            <input type="text" wire:model="uom_name" placeholder="e.g. Liters" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            @error('uom_name') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Code *</label>
                            <input type="text" wire:model="uom_code" placeholder="e.g. LTR" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            @error('uom_code') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Symbol</label>
                            <input type="text" wire:model="uom_symbol" placeholder="e.g. L" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                        </div>

                        <div class="flex justify-end space-x-3 pt-4 border-t">
                            <button type="button" wire:click="$set('showUomModal', false)" class="px-4 py-2 border rounded-md text-gray-700 bg-white">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md">Save UOM</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        <!-- Conversion Modal -->
        @if ($showConversionModal)
            <div class="fixed inset-0 z-50 overflow-y-auto bg-gray-500 bg-opacity-75 flex items-center justify-center p-4">
                <div class="bg-white rounded-lg max-w-md w-full p-6 space-y-4">
                    <div class="flex justify-between items-center border-b pb-3">
                        <h3 class="text-lg font-bold text-gray-900">Define Unit Conversion</h3>
                        <button wire:click="$set('showConversionModal', false)" class="text-gray-400 hover:text-gray-500">&times;</button>
                    </div>

                    <form wire:submit.prevent="saveConversion" class="space-y-4">
                        <p class="text-xs text-gray-500">Define rule: 1 [From Unit] = [Factor] &times; [To Unit] (e.g. 1 Drum = 208 Liters)</p>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">From Unit *</label>
                            <select wire:model="from_uom_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                <option value="">Select From UOM</option>
                                @foreach ($uoms as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->code }})</option>
                                @endforeach
                            </select>
                            @error('from_uom_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">Conversion Factor *</label>
                            <input type="number" step="0.0001" wire:model="conversion_factor" placeholder="e.g. 208" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                            @error('conversion_factor') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700">To Unit *</label>
                            <select wire:model="to_uom_id" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm sm:text-sm">
                                <option value="">Select To UOM</option>
                                @foreach ($uoms as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->code }})</option>
                                @endforeach
                            </select>
                            @error('to_uom_id') <span class="text-red-500 text-xs">{{ $message }}</span> @enderror
                        </div>

                        <div class="flex justify-end space-x-3 pt-4 border-t">
                            <button type="button" wire:click="$set('showConversionModal', false)" class="px-4 py-2 border rounded-md text-gray-700 bg-white">Cancel</button>
                            <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-md">Save Conversion</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>

