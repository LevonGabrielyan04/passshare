<?php

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Fortify\Fortify;

class AuthenticateUser
{
    /**
     * Attempt to authenticate the user by email or nickname.
     */
    public function __invoke(Request $request): ?User
    {
        $login = $request->string(Fortify::username())->toString();
        $user = $this->findUser($login);

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            return null;
        }

        return $user;
    }

    /**
     * Find a user by email address or nickname.
     */
    private function findUser(string $login): ?User
    {
        if (str_contains($login, '@')) {
            return User::query()->where('email', $login)->first();
        }

        return User::query()->whereRaw('LOWER(name) = ?', [$login])->first();
    }
}
