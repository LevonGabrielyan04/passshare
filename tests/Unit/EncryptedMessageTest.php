<?php

use App\Rules\EncryptedMessage;

function validateEncryptedMessage(mixed $value): array
{
    $failures = [];

    (new EncryptedMessage)->validate('message', $value, function (string $message) use (&$failures): void {
        $failures[] = $message;
    });

    return $failures;
}

it('accepts a valid encrypted payload', function () {
    expect(validateEncryptedMessage(fakeEncryptedMessage()))->toBeEmpty();
});

it('rejects plaintext messages', function (mixed $value) {
    expect(validateEncryptedMessage($value))->not->toBeEmpty();
})->with([
    'plain string' => 'top secret',
    'invalid json' => '{not-json',
    'missing fields' => '{"ciphertext":"abc"}',
    'empty ciphertext' => json_encode([
        'ciphertext' => '',
        'salt' => base64_encode(random_bytes(16)),
        'iv' => base64_encode(random_bytes(12)),
    ]),
    'invalid base64' => json_encode([
        'ciphertext' => '!!!',
        'salt' => base64_encode(random_bytes(16)),
        'iv' => base64_encode(random_bytes(12)),
    ]),
    'wrong salt length' => json_encode([
        'ciphertext' => base64_encode(random_bytes(8)),
        'salt' => base64_encode(random_bytes(8)),
        'iv' => base64_encode(random_bytes(12)),
    ]),
    'wrong iv length' => json_encode([
        'ciphertext' => base64_encode(random_bytes(8)),
        'salt' => base64_encode(random_bytes(16)),
        'iv' => base64_encode(random_bytes(8)),
    ]),
]);
