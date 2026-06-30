<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

class VerifyEmailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->resolveUser();

        if (! hash_equals((string) $user->getKey(), (string) $this->route('id'))) {
            return false;
        }

        if (! hash_equals(sha1($user->getEmailForVerification()), (string) $this->route('hash'))) {
            return false;
        }

        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Get the user associated with the verification link.
     */
    public function user($guard = null): User
    {
        return $this->resolveUser();
    }

    /**
     * Resolve the user from the signed verification link.
     */
    protected function resolveUser(): User
    {
        return User::query()->findOrFail($this->route('id'));
    }
}
