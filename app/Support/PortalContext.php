<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * Which public portal a request belongs to, from its path. Copied from KLAES (App\Support\PortalContext, user
 * 2026-11-11: an error page on a portal must keep every link inside that portal, never the staff site's Home or
 * sign-in). The error pages use it. Null for the staff site.
 */
class PortalContext
{
    /** path prefix => [name, home route, sign-in route or null] */
    private const PORTALS = [
        'portal' => ['KLAES-CONTROL Applicant Portal', 'portal.dashboard', 'portal.login'],
    ];

    /** @return array{name:string, prefix:string, home:string, login:?string, back:string}|null */
    public static function fromRequest(?Request $request = null): ?array
    {
        $request ??= request();
        $first = strtolower((string) explode('/', trim($request->path(), '/'))[0]);
        if (! isset(self::PORTALS[$first])) {
            return null;
        }

        [$name, $home, $login] = self::PORTALS[$first];
        $homeUrl = Route::has($home) ? route($home) : url('/'.$first);
        $loginUrl = $login && Route::has($login) ? route($login) : null;

        // "Go back" only to a page of the same portal; anything else (the staff site, another site) goes home.
        $previous = url()->previous();
        $prevPath = strtolower(trim((string) parse_url($previous, PHP_URL_PATH), '/'));
        $back = ($prevPath === $first || str_starts_with($prevPath, $first.'/')) && $previous !== $request->fullUrl() ? $previous : $homeUrl;

        return ['name' => $name, 'prefix' => $first, 'home' => $homeUrl, 'login' => $loginUrl, 'back' => $back];
    }
}
