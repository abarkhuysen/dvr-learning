<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    @fluxAppearance
    @include('partials.head')
</head>
<body class="bg-[#FDFDFC] dark:bg-[#0a0a0a] text-[#1b1b18] flex p-6 lg:p-8 items-center lg:justify-center min-h-screen flex-col">
{{ $slot }}
@fluxScripts
</body>
</html>
