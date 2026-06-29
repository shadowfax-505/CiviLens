<?php

namespace App\Support\Http;

use App\Models\User;
use Illuminate\Http\Request;

class AuthenticatedUser
{
    public static function from(Request $request): User
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
}
