<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full" data-kt-theme="true" data-kt-theme-mode="light" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta content="width=device-width, initial-scale=1, shrink-to-fit=no" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield("title")</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body>
    <div class="container py-4">
        @yield('content')
    </div>
</body>
</html>
