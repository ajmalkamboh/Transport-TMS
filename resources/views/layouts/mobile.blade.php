<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full bg-gray-900 text-gray-100">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#4f46e5">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="manifest" href="/manifest.json">
    <title>{{ $title ?? 'TMS Driver Portal' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body { -webkit-tap-highlight-color: transparent; }
    </style>
</head>
<body class="h-full flex flex-col font-sans antialiased selection:bg-indigo-500 selection:text-white">
    <!-- Top Mobile App Bar -->
    <header class="bg-gray-800 border-b border-gray-700 px-4 py-3 flex items-center justify-between sticky top-0 z-30">
        <div class="flex items-center space-x-2">
            <span class="p-1.5 bg-indigo-600 rounded-lg text-white font-black text-sm">TMS</span>
            <span class="font-bold text-sm tracking-wide">Driver Portal</span>
        </div>
        <div class="flex items-center space-x-3 text-xs">
            <span class="inline-block w-2.5 h-2.5 rounded-full bg-green-500 animate-pulse"></span>
            <span class="text-gray-300">{{ auth()->user()?->name ?? 'Driver' }}</span>
            <form method="POST" action="{{ route('logout') }}" class="inline">
                @csrf
                <button type="submit" class="text-red-400 hover:text-red-300 font-semibold ml-2">Exit</button>
            </form>
        </div>
    </header>

    <!-- Main Content Container -->
    <main class="flex-1 overflow-y-auto p-4 pb-20">
        @if(session('success'))
            <div class="mb-4 bg-green-900 border border-green-700 text-green-200 px-3 py-2 rounded text-xs flex items-center justify-between">
                <span>{{ session('success') }}</span>
            </div>
        @endif
        @if(session('error'))
            <div class="mb-4 bg-red-900 border border-red-700 text-red-200 px-3 py-2 rounded text-xs flex items-center justify-between">
                <span>{{ session('error') }}</span>
            </div>
        @endif

        {{ $slot ?? '' }}
        @yield('content')
    </main>

    <!-- Bottom Navigation Bar -->
    <nav class="fixed bottom-0 inset-x-0 bg-gray-800 border-t border-gray-700 py-2 px-6 flex justify-around items-center z-30">
        <a href="{{ route('mobile.driver.dashboard') }}" class="flex flex-col items-center text-xs font-medium {{ request()->routeIs('mobile.driver.dashboard') ? 'text-indigo-400' : 'text-gray-400 hover:text-gray-200' }}">
            <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
            </svg>
            Dashboard
        </a>
        <button onclick="captureCurrentLocation()" class="flex flex-col items-center text-xs font-medium text-gray-400 hover:text-indigo-400">
            <svg class="w-5 h-5 mb-0.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            Send GPS
        </button>
    </nav>

    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/sw.js').catch(err => console.log('SW registration failed', err));
        }

        function captureCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(position => {
                    fetch('{{ route("mobile.driver.location") }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            latitude: position.coords.latitude,
                            longitude: position.coords.longitude,
                            speed_kmh: position.coords.speed ? (position.coords.speed * 3.6) : 0,
                            heading: position.coords.heading || 0
                        })
                    }).then(r => r.json()).then(data => {
                        alert('Location captured and uploaded successfully!');
                    }).catch(e => alert('Failed to sync location.'));
                }, error => {
                    alert('Geolocation access denied or unavailable: ' + error.message);
                });
            } else {
                alert('Geolocation is not supported by your mobile browser.');
            }
        }
    </script>
</body>
</html>

