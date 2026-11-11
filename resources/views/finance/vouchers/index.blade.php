@extends('layouts.app')

@section('content')
<nav class="mb-3" aria-label="breadcrumb">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">General Ledger & Vouchers</li>
    </ol>
</nav>

<livewire:finance.general-ledger-manager />
@endsection
