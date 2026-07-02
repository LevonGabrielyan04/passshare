@if (config('turnstile.enabled'))
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer nonce="{{ Illuminate\Support\Facades\Vite::cspNonce() }}"></script>

    <div
        class="cf-turnstile"
        data-sitekey="{{ config('turnstile.site_key') }}"
        data-theme="auto"
    ></div>

    @error('cf-turnstile-response')
        <flux:text class="text-sm text-red-600 dark:text-red-400">{{ $message }}</flux:text>
    @enderror
@endif
