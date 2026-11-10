@extends('layouts.app')

@section('title', 'Initial Setup Wizard')

@section('content')
<div class="row justify-content-center">
    <div class="col-xl-10">
        @livewire('onboarding.setup-wizard')
    </div>
</div>
@endsection

