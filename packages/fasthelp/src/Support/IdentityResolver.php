<?php

namespace Tabadev\FastHelp\Support;

use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Tabadev\FastHelp\Models\Visitor;

class IdentityResolver
{
    /**
     * How long the visitor cookie should live, in minutes (~1 year).
     */
    private const COOKIE_LIFETIME_MINUTES = 60 * 24 * 365;

    public function resolve(): Identity
    {
        $cookieName = config('fasthelp.cookie', 'fasthelp_visitor');

        $token = request()->cookie($cookieName);

        $guard = config('fasthelp.auth_guard');
        $user = auth()->guard($guard)->user();

        $visitor = $token ? Visitor::query()->where('token', $token)->first() : null;

        if (! $visitor) {
            $token = Str::random(40);
            $visitor = new Visitor(['token' => $token]);

            Cookie::queue($cookieName, $token, self::COOKIE_LIFETIME_MINUTES);
        }

        $visitor->last_seen_at = now();

        if ($user && ! $visitor->user_id) {
            $visitor->user_id = $user->getKey();
        }

        $visitor->save();

        return new Identity($visitor, $user);
    }
}
