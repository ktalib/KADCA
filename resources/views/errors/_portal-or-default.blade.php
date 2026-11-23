{{-- Error pages: on the applicant portal (/portal/...) the card keeps every link inside the portal (KLAES rule, user
     2026-11-11, App\Support\PortalContext); everywhere else Laravel's own page is shown unchanged.
     Expects $code, $title, $message. --}}
@php
    $portal = rescue(fn () => \App\Support\PortalContext::fromRequest(), null, false);
@endphp
@if ($portal)
    @include('portal.errors.card', ['portal' => $portal, 'code' => $code, 'title' => $title, 'message' => $message])
@else
    {!! view()->file(base_path('vendor/laravel/framework/src/Illuminate/Foundation/Exceptions/views/'.$code.'.blade.php'), ['exception' => $exception ?? null])->render() !!}
@endif
