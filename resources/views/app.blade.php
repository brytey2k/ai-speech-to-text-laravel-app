<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" data-kt-theme="true" data-kt-theme-mode="light" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield("title")</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body style="background-image: url('{{ asset('images/background.png') }}');" class="bg-no-repeat bg-cover bg-center h-screen overflow-hidden">
    <!-- Top Navigation Bar -->
    <nav class="bg-white shadow w-full">
        <div class="w-full px-6 md:px-12 py-2 flex justify-between items-center">
            <div class="text-lg font-semibold">
            </div>

            <div class="flex items-center">
                @if(auth()->check())
                    <div class="relative">
                        <img
                            src="{{ auth()->user()->avatar ?? 'https://ui-avatars.com/api/?name=' . urlencode(auth()->user()->name) . '&color=7F9CF5&background=EBF4FF' }}"
                            alt="User Avatar"
                            class="h-10 w-10 rounded-full object-cover border-2 border-gray-200"
                        >
                    </div>
                @else
                    <div class="relative">
                        <img
                            src="https://ui-avatars.com/api/?name=Guest&color=7F9CF5&background=EBF4FF"
                            alt="Guest Avatar"
                            class="h-10 w-10 rounded-full object-cover border-2 border-gray-200"
                        >
                    </div>
                @endif
            </div>
        </div>
    </nav>

    <div class="w-full h-full px-6 md:px-12 py-0">
        @yield('content')
    </div>

@stack('page-scripts')
</body>
</html>
