<?php

namespace App\Support\Csp;

use App\Support\ApplicationOrigins;
use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;
use Spatie\Csp\Scheme;
use Spatie\Csp\Value;

class StrictPolicyPreset implements Preset
{
    public function configure(Policy $policy): void
    {
        $origins = $this->allowedOrigins();

        $policy
            ->add(Directive::DEFAULT, $origins)
            ->add(Directive::BASE, $origins)
            ->add(Directive::CONNECT, $this->connectSources())
            ->add(Directive::FONT, $origins)
            ->add(Directive::FORM_ACTION, $origins)
            ->add(Directive::FRAME, $this->frameSources())
            ->add(Directive::FRAME_ANCESTORS, Keyword::NONE)
            ->add(Directive::IMG, $origins)
            ->add(Directive::MANIFEST, $origins)
            ->add(Directive::MEDIA, $origins)
            ->add(Directive::OBJECT, Keyword::NONE)
            ->add(Directive::SCRIPT, $this->scriptSources($origins))
            ->add(Directive::STYLE, $origins)
            ->add(Directive::WORKER, [...$origins, Scheme::BLOB])
            ->addNonce(Directive::SCRIPT)
            ->addNonce(Directive::STYLE);

        if (str_starts_with(config('app.url'), 'https://')) {
            $policy->add(Directive::UPGRADE_INSECURE_REQUESTS, Value::NO_VALUE);
        }
    }

    /**
     * @return list<string>
     */
    private function allowedOrigins(): array
    {
        $origins = ApplicationOrigins::webAuthn();

        if (in_array(config('app.env'), ['local', 'testing'])) {
            $origins = [
                ...$origins,
                ...$this->localDevAppOrigins(),
                ...$this->viteDevOrigins(),
                ...$this->loopbackViteDevOrigins(),
            ];
        }

        return array_values(array_unique($origins));
    }

    /**
     * @param  list<string>  $origins
     * @return list<string|Keyword>
     */
    private function scriptSources(array $origins): array
    {
        $sources = [...$origins, Keyword::UNSAFE_WEB_ASSEMBLY_EXECUTION];

        if (config('turnstile.enabled')) {
            $sources[] = 'https://challenges.cloudflare.com';
        }

        return $sources;
    }

    /**
     * @return list<string|Keyword>
     */
    private function frameSources(): array
    {
        if (config('turnstile.enabled')) {
            return ['https://challenges.cloudflare.com'];
        }

        return [Keyword::NONE];
    }

    /**
     * @return list<string>
     */
    private function connectSources(): array
    {
        $sources = [
            ...$this->allowedOrigins(),
            'https://api.pwnedpasswords.com',
        ];

        if (in_array(config('app.env'), ['local', 'testing'])) {
            $sources = [
                ...$sources,
                ...$this->viteDevWebSocketOrigins(),
                ...$this->loopbackViteDevWebSocketOrigins(),
            ];
        }

        if (config('turnstile.enabled')) {
            $sources[] = 'https://challenges.cloudflare.com';
        }

        return array_values(array_unique($sources));
    }

    /**
     * @return list<string>
     */
    private function localDevAppOrigins(): array
    {
        $devHost = $this->viteDevHost();

        if ($devHost === null || $this->isLoopbackHost($devHost)) {
            return [];
        }

        return ["https://{$devHost}"];
    }

    /**
     * @return list<string>
     */
    private function viteDevOrigins(): array
    {
        $host = $this->viteDevHost();

        if ($host === null) {
            return [];
        }

        $origins = [];

        foreach ($this->viteDevPorts() as $port) {
            $origins[] = "https://vite.{$host}:{$port}";
            $origins[] = "https://{$host}:{$port}";
        }

        return $origins;
    }

    /**
     * @return list<string>
     */
    private function viteDevWebSocketOrigins(): array
    {
        $host = $this->viteDevHost();

        if ($host === null) {
            return [];
        }

        $origins = [];

        foreach ($this->viteDevPorts() as $port) {
            $origins[] = "wss://vite.{$host}:{$port}";
            $origins[] = "wss://{$host}:{$port}";
        }

        return $origins;
    }

    private function viteDevHost(): ?string
    {
        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (! is_string($appHost) || $appHost === '') {
            return null;
        }

        if ($this->isLoopbackHost($appHost)) {
            return basename(base_path()).'.test';
        }

        $requestHost = request()->getHost();

        if ($requestHost !== '' && ! $this->isLoopbackHost($requestHost)) {
            return $requestHost;
        }

        return $appHost;
    }

    private function isLoopbackHost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
    }

    /**
     * @return list<int>
     */
    private function viteDevPorts(): array
    {
        return [5173];
    }

    /**
     * @return list<string>
     */
    private function loopbackViteDevOrigins(): array
    {
        if (! $this->shouldAllowLoopbackViteOrigins()) {
            return [];
        }

        $origins = [];

        foreach ($this->viteDevPorts() as $port) {
            $origins[] = "http://127.0.0.1:{$port}";
            $origins[] = "http://localhost:{$port}";
        }

        return $origins;
    }

    /**
     * @return list<string>
     */
    private function loopbackViteDevWebSocketOrigins(): array
    {
        if (! $this->shouldAllowLoopbackViteOrigins()) {
            return [];
        }

        $origins = [];

        foreach ($this->viteDevPorts() as $port) {
            $origins[] = "ws://127.0.0.1:{$port}";
            $origins[] = "ws://localhost:{$port}";
        }

        return $origins;
    }

    private function shouldAllowLoopbackViteOrigins(): bool
    {
        if (! in_array(config('app.env'), ['local', 'testing'], true)) {
            return false;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        if (is_string($appHost) && $this->isLoopbackHost($appHost)) {
            return true;
        }

        $requestHost = request()->getHost();

        return $requestHost !== '' && $this->isLoopbackHost($requestHost);
    }
}
