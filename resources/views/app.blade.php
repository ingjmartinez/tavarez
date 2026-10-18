<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>Tavarez - Business Intelligence</title>
    <meta name="description"
        content="Tavarez - Business Intelligence platform.">
    <meta name="keywords"
        content="Tavarez, Business Intelligence, dashboard">
    <meta name="author" content="Tavarez">

    <!-- Social Media Meta Tags -->
    <meta property="og:title" content="Tavarez - Business Intelligence">
    <meta property="og:description"
        content="Tavarez - Business Intelligence platform.">
    <meta property="og:image" content="URL to the template's logo or featured image">
    <meta property="og:url" content="URL to the template's webpage">
    <meta name="twitter:card" content="summary_large_image">

    <!-- App favicon -->
    <link rel="shortcut icon" href="{{ URL::asset('image/favicon.ico') }}">

    <!-- Scripts -->
    @routes
    @vite(['resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead
</head>

<body>
    @inertia
</body>

</html>
