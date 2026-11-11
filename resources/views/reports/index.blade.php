@extends('layouts.app')

@section('title', 'Reports & Business Intelligence')

@section('content')
<nav class="mb-3" aria-label="breadcrumb">
    <ol class="breadcrumb mb-0">
        <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li class="breadcrumb-item active" aria-current="page">Reports & Business Intelligence</li>
    </ol>
</nav>

<livewire:reports.report-center />
@endsection

