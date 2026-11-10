@extends('layouts.auth')

@section('title', 'Sign In')

@section('content')
<div class="row g-0 h-100">
    <div class="col-md-5 text-center bg-card-gradient">
        <div class="position-relative p-4 pt-md-5 pb-md-7 dark__bg-1000">
            <div class="bg-holder bg-auth-card-shape" style="background-image:url({{ asset('assets/img/icons/spot-illustrations/half-circle.png') }});"></div>
            <div class="z-index-1 position-relative">
                <a class="link-light mb-4 font-sans-serif fs-4 d-inline-block fw-bolder" href="#">
                    <span class="fas fa-truck-moving text-white fs-3 me-2"></span>TMS
                </a>
                <p class="opacity-75 text-white">Goods Transport Management System.<br>Enterprise Multi-Tenant SaaS Platform.</p>
            </div>
        </div>
        <div class="mt-3 mb-4 mt-md-4 mb-md-5 light">
            <div class="text-white-50 fs--1">
                Demo Accounts:<br>
                <strong>admin@tms.local</strong> (Super Admin)<br>
                <strong>owner@falconexpress.com</strong> (Tenant Owner)<br>
                <strong>booking.lhr@falconexpress.com</strong> (Booking Clerk)<br>
                Password: <code>Password123!</code>
            </div>
        </div>
    </div>
    <div class="col-md-7 d-flex flex-center">
        <div class="p-4 p-md-5 flex-grow-1">
            <div class="row flex-between-center">
                <div class="col-auto">
                    <h3>Account Login</h3>
                </div>
            </div>

            @if($errors->any())
                <div class="alert alert-danger py-2 fs--1 mt-2">
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="mt-3">
                @csrf
                <div class="mb-3">
                    <label class="form-label" for="card-email">Email address</label>
                    <input class="form-control @error('email') is-invalid @enderror" id="card-email" type="email" name="email" value="{{ old('email') }}" required autofocus placeholder="name@company.com" />
                </div>
                <div class="mb-3">
                    <label class="form-label" for="card-password">Password</label>
                    <input class="form-control @error('password') is-invalid @enderror" id="card-password" type="password" name="password" required placeholder="Enter password" />
                </div>
                <div class="row flex-between-center mb-3">
                    <div class="col-auto">
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="remember" id="card-checkbox" checked="checked" />
                            <label class="form-check-label mb-0" for="card-checkbox">Remember me</label>
                        </div>
                    </div>
                </div>
                <div class="mb-3">
                    <button class="btn btn-primary d-block w-100 mt-3" type="submit" name="submit">Sign In</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

