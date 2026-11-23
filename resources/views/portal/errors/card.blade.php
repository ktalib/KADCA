{{-- Error card for the applicant portal. Every link stays inside the portal: portal home, portal sign-in, and Go back
     only to a page of the portal (App\Support\PortalContext, copied from KLAES). Standalone: no sidebar, no session needed. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <x-theme-init />
        <title>{{ $title }} &mdash; {{ $portal['name'] }}</title>
        <link rel="icon" type="image/png" href="{{ asset('images/logo.png') }}">
        @vite(['resources/css/app.css'])
    </head>
    <body class="flex min-h-screen items-center justify-center bg-slate-50 px-4 py-10 font-sans antialiased dark:bg-slate-950">
        <x-layout.preloader :delay="350" />
        <main class="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm dark:border-slate-800 dark:bg-slate-900">
            <img src="{{ asset('images/logo.png') }}" alt="KADCA KLAES-CONTROL logo" class="mx-auto h-20 w-20 object-contain">
            <p class="mt-4 inline-flex rounded-full bg-indigo-50 px-3 py-1 text-[11px] font-bold uppercase tracking-widest text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300">{{ $portal['name'] }}</p>
            <p class="mt-5 text-5xl font-black text-slate-200 dark:text-slate-700">{{ $code }}</p>
            <h1 class="mt-2 text-xl font-bold text-slate-900 dark:text-white">{{ $title }}</h1>
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-400">{{ $message }}</p>
            <div class="mt-7 flex flex-col gap-2 sm:flex-row sm:justify-center">
                <a href="{{ $portal['back'] }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-200 dark:hover:bg-slate-700">Go back</a>
                <a href="{{ $portal['home'] }}" class="rounded-lg bg-indigo-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-indigo-500">Portal home</a>
                @if ($portal['login'] && in_array((string) $code, ['419', '403'], true))
                    <a href="{{ $portal['login'] }}" class="rounded-lg border border-indigo-300 px-4 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-50 dark:border-indigo-500/40 dark:text-indigo-300 dark:hover:bg-indigo-500/10">Sign in</a>
                @endif
            </div>
        </main>
    </body>
</html>
