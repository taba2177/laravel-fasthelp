<?php

namespace Tabadev\FastHelp\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

class AgentResolver
{
    public function isAgent(?Authenticatable $user): bool
    {
        if ($user === null) {
            return false;
        }

        $resolver = config('fasthelp.agents.resolver');

        if (is_callable($resolver)) {
            return (bool) $resolver($user);
        }

        return DB::table('fasthelp_agents')
            ->where('user_id', $user->getAuthIdentifier())
            ->exists();
    }
}
