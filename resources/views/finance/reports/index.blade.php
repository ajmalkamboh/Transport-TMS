@extends('layouts.app')

@section('content')
<nav class="mb-3" aria-label="breadcrumb">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Financial Statements & Reports</li>
    </ol>
</nav>

<livewire:finance.financial-report-manager />
@endsection
