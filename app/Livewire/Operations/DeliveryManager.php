<?php

namespace App\Livewire\Operations;

use App\Models\ProofOfDelivery;
use App\Models\Trip;
use App\Models\TripConsignment;
use App\Services\DeliveryManagementService;
use App\Services\TenantAuthorizationService;
use App\Services\TenantContextService;
use Livewire\Component;
use Livewire\WithPagination;

class DeliveryManager extends Component
{
    use WithPagination;

    public string $search = '';
    public string $deliveryStatusFilter = '';
    public ?int $tripFilter = null;

    // Record Delivery Modal
    public bool $showDeliveryModal = false;
    public ?TripConsignment $selectedAllocation = null;
    public int $delivered_quantity = 0;
    public int $damaged_quantity = 0;
    public int $short_quantity = 0;
    public string $receiver_name = '';
    public string $receiver_phone = '';
    public string $receiver_cnic = '';
    public string $delivery_datetime = '';
    public ?float $gps_latitude = 31.5204;
    public ?float $gps_longitude = 74.3587;
    public string $exception_notes = '';

    // POD Detail Modal
    public bool $showPodDetailModal = false;
    public ?ProofOfDelivery $selectedPod = null;

    public function mount(): void
    {
        $this->delivery_datetime = now()->format('Y-m-d\TH:i');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function updatingDeliveryStatusFilter(): void
    {
        $this->resetPage();
    }

    public function openDeliveryModal(int $allocationId, TenantAuthorizationService $auth): void
    {
        $this->resetErrorBag();
        $this->selectedAllocation = TripConsignment::with(['trip', 'consignment.customer'])->findOrFail($allocationId);
        $auth->validateTenantOwnership($this->selectedAllocation);

        $this->delivered_quantity = $this->selectedAllocation->assigned_quantity;
        $this->damaged_quantity = 0;
        $this->short_quantity = 0;
        $this->receiver_name = $this->selectedAllocation->consignment?->consignee_name ?? '';
        $this->receiver_phone = $this->selectedAllocation->consignment?->consignee_phone ?? '';
        $this->receiver_cnic = '';
        $this->delivery_datetime = now()->format('Y-m-d\TH:i');
        $this->gps_latitude = 31.5204;
        $this->gps_longitude = 74.3587;
        $this->exception_notes = '';

        $this->showDeliveryModal = true;
    }

    public function submitDelivery(DeliveryManagementService $deliveryService): void
    {
        $this->validate([
            'delivered_quantity' => 'required|integer|min:0',
            'damaged_quantity' => 'required|integer|min:0',
            'short_quantity' => 'required|integer|min:0',
            'receiver_name' => 'required|string|max:100',
            'receiver_phone' => 'nullable|string|max:50',
            'receiver_cnic' => 'nullable|string|max:50',
            'delivery_datetime' => 'required|date',
            'exception_notes' => 'nullable|string',
        ]);

        try {
            $pod = $deliveryService->recordDelivery(
                $this->selectedAllocation,
                $this->delivered_quantity,
                $this->damaged_quantity,
                $this->short_quantity,
                [
                    'receiver_name' => $this->receiver_name,
                    'receiver_phone' => $this->receiver_phone ?: null,
                    'receiver_cnic' => $this->receiver_cnic ?: null,
                    'delivery_datetime' => $this->delivery_datetime,
                    'gps_latitude' => $this->gps_latitude,
                    'gps_longitude' => $this->gps_longitude,
                    'exception_notes' => $this->exception_notes ?: null,
                    'status' => ProofOfDelivery::STATUS_VERIFIED,
                ],
                auth()->id()
            );

            $this->showDeliveryModal = false;
            session()->flash('success', "Delivery recorded successfully! POD reference: POD-" . str_pad($pod->id, 6, '0', STR_PAD_LEFT));
        } catch (\InvalidArgumentException $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function viewPod(int $podId, TenantAuthorizationService $auth): void
    {
        $this->selectedPod = ProofOfDelivery::with(['trip', 'consignment.customer', 'verifier'])->findOrFail($podId);
        $auth->validateTenantOwnership($this->selectedPod);
        $this->showPodDetailModal = true;
    }

    public function render()
    {
        $query = TripConsignment::with(['trip', 'consignment.customer', 'proofOfDeliveries'])
            ->latest();

        if ($this->search) {
            $query->whereHas('consignment', function ($q) {
                $q->where('consignment_number', 'like', "%{$this->search}%")
                    ->orWhere('consignee_name', 'like', "%{$this->search}%")
                    ->orWhere('consignee_city', 'like', "%{$this->search}%");
            })->orWhereHas('trip', function ($q) {
                $q->where('trip_number', 'like', "%{$this->search}%");
            });
        }

        if ($this->deliveryStatusFilter) {
            $query->where('delivery_status', $this->deliveryStatusFilter);
        }

        if ($this->tripFilter) {
            $query->where('trip_id', $this->tripFilter);
        }

        $allocations = $query->paginate(15);
        $trips = Trip::latest()->take(20)->get();

        return view('livewire.operations.delivery-manager', [
            'allocations' => $allocations,
            'trips' => $trips,
        ]);
    }
}
