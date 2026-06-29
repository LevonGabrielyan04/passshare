<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class EncryptedMessage implements ValidationRule
{
    private const SALT_BYTES = 16;

    private const IV_BYTES = 12;

    /**
     * @param  Closure(string): void  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('The message must be a valid encrypted payload.');

            return;
        }

        try {
            $payload = json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            $fail('The message must be a valid encrypted payload.');

            return;
        }

        if (! is_array($payload)) {
            $fail('The message must be a valid encrypted payload.');

            return;
        }

        foreach (['ciphertext', 'salt', 'iv'] as $key) {
            if (! isset($payload[$key]) || ! is_string($payload[$key]) || $payload[$key] === '') {
                $fail('The message must be a valid encrypted payload.');

                return;
            }
        }

        $ciphertext = base64_decode($payload['ciphertext'], true);
        $salt = base64_decode($payload['salt'], true);
        $iv = base64_decode($payload['iv'], true);

        if (
            $ciphertext === false
            || $salt === false
            || $iv === false
            || $ciphertext === ''
            || strlen($salt) !== self::SALT_BYTES
            || strlen($iv) !== self::IV_BYTES
        ) {
            $fail('The message must be a valid encrypted payload.');

            return;
        }
    }
}
