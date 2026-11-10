@extends('layouts.app')

@section('title', 'SaaS Billing & Invoicing')

@section('content')
<div class="row g-3 mb-3">
    <div class="col-md-12">
        <div class="card bg-100 shadow-none border">
            <div class="card-body py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 text-primary fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i> Platform SaaS Billing & Payment Verification</h5>
                        <div class="fs--1 text-600">Audit subscription invoices, record manual payments, verify bank receipts, and track platform revenue.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<livewire:saas.billing-manager />
@endsection

