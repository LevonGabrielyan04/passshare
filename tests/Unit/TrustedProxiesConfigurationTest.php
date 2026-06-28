<?php

use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Tests\TestCase;

uses(TestCase::class);

test('application configures trusted proxies from app config at boot', function () {
    $reflection = new ReflectionProperty(TrustProxies::class, 'alwaysTrustProxies');

    expect($reflection->getValue())->toBe(config('app.trusted_proxies', ''));
});

test('trusted proxies wiring uses cached config values', function () {
    TrustProxies::flushState();

    config(['app.trusted_proxies' => '203.0.113.10,198.51.100.5']);

    TrustProxies::at(config('app.trusted_proxies', ''));

    $reflection = new ReflectionProperty(TrustProxies::class, 'alwaysTrustProxies');

    expect($reflection->getValue())->toBe('203.0.113.10,198.51.100.5');
});

test('docker nginx upstream proxy wiring trusts sanitized forwarded proto from cloudflare tunnel', function () {
    TrustProxies::flushState();
    TrustProxies::at('127.0.0.1');

    $request = Request::create(
        'http://localhost:8080/',
        'GET',
        [],
        [],
        [],
        [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_HOST' => 'example.com',
            'HTTP_X_FORWARDED_PORT' => '443',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
            'HTTP_CF_CONNECTING_IP' => '203.0.113.50',
            'HTTP_CF_VISITOR' => '{"scheme":"https"}',
        ],
    );

    (new TrustProxies)->handle($request, fn (Request $request) => $request);

    expect($request->secure())->toBeTrue()
        ->and($request->getHost())->toBe('example.com')
        ->and($request->getPort())->toBe(443)
        ->and($request->ip())->toBe('203.0.113.50');
});

test('client remote addr is not trusted when nginx does not sanitize upstream proxy', function () {
    TrustProxies::flushState();
    TrustProxies::at('127.0.0.1');

    $request = Request::create(
        'http://localhost:8080/',
        'GET',
        [],
        [],
        [],
        [
            'REMOTE_ADDR' => '203.0.113.50',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.50',
        ],
    );

    (new TrustProxies)->handle($request, fn (Request $request) => $request);

    expect($request->secure())->toBeFalse();
});

test('forged cloudflare headers are ignored when request does not come from nginx proxy', function () {
    TrustProxies::flushState();
    TrustProxies::at('127.0.0.1');

    $request = Request::create(
        'http://localhost:8080/',
        'GET',
        [],
        [],
        [],
        [
            'REMOTE_ADDR' => '172.18.0.5',
            'HTTP_X_FORWARDED_PROTO' => 'https',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.99',
            'HTTP_CF_CONNECTING_IP' => '203.0.113.99',
            'HTTP_CF_VISITOR' => '{"scheme":"https"}',
        ],
    );

    (new TrustProxies)->handle($request, fn (Request $request) => $request);

    expect($request->secure())->toBeFalse()
        ->and($request->ip())->toBe('172.18.0.5');
});
