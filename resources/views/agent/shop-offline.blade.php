<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <title>Shop Offline — Freedom Data</title>
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen bg-paper text-ink flex items-center justify-center">
    <div class="text-center px-4">
        <span class="signal-bars" style="justify-content:center"><span class="bar"></span><span class="bar"></span><span class="bar"></span><span class="bar"></span></span>
        <h1 class="font-display mt-4 text-xl font-semibold">This shop is currently offline</h1>
        <p class="mt-2 text-sm text-ink-muted">The vendor's subscription has expired. Please contact {{ $agentName ?? 'the vendor' }} directly.</p>
    </div>
</body>
</html>
