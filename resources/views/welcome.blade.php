<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="{{ config('app.name') }} — end-to-end encrypted password and secret sharing. Secrets are encrypted in your browser before they ever reach the server.">

        <title>{{ config('app.name') }} — Share secrets with zero trust</title>

        <link rel="icon" href="/favicon.png" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        @fonts

        @vite(['resources/css/app.css'])
    </head>
    <body class="landing-gradient text-[var(--landing-dominant-foreground)] antialiased">
        {{-- Sticky header --}}
        <header
            id="site-header"
            class="fixed inset-x-0 top-0 z-50 border-b border-transparent transition-all duration-300"
        >
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <a href="{{ route('home') }}" class="group flex items-center gap-3">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-emerald-500/15 ring-1 ring-emerald-500/30 transition group-hover:bg-emerald-500/25">
                        <x-app-logo-icon class="size-5 fill-emerald-400" />
                    </span>
                    <span class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</span>
                </a>

                <nav class="hidden items-center gap-8 text-sm text-zinc-400 md:flex">
                    <a href="#features" class="transition hover:text-white">Features</a>
                    <a href="#how-it-works" class="transition hover:text-white">How it works</a>
                    <a href="#security" class="transition hover:text-white">Security</a>
                    <a href="#faq" class="transition hover:text-white">FAQ</a>
                </nav>

                <div class="hidden items-center gap-3 md:flex">
                    @auth
                        <a href="{{ route('dashboard') }}" class="landing-cta-secondary px-5 py-2 text-sm">
                            Dashboard
                        </a>
                    @else
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="text-sm text-zinc-400 transition hover:text-white">
                                Log in
                            </a>
                        @endif
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="landing-cta-primary px-5 py-2 text-sm">
                                Get started
                            </a>
                        @endif
                    @endauth
                </div>

                {{-- Mobile menu --}}
                <details class="group relative md:hidden">
                    <summary class="flex size-10 cursor-pointer list-none items-center justify-center rounded-lg border border-white/10 bg-white/5 [&::-webkit-details-marker]:hidden">
                        <svg class="size-5 group-open:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        </svg>
                        <svg class="hidden size-5 group-open:block" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                        <span class="sr-only">Menu</span>
                    </summary>
                    <div class="absolute end-0 top-full mt-2 w-56 rounded-xl border border-white/10 bg-[var(--landing-surface)] p-3 shadow-xl">
                        <nav class="flex flex-col gap-1 text-sm">
                            <a href="#features" class="rounded-lg px-3 py-2 text-zinc-300 hover:bg-white/5 hover:text-white">Features</a>
                            <a href="#how-it-works" class="rounded-lg px-3 py-2 text-zinc-300 hover:bg-white/5 hover:text-white">How it works</a>
                            <a href="#security" class="rounded-lg px-3 py-2 text-zinc-300 hover:bg-white/5 hover:text-white">Security</a>
                            <a href="#faq" class="rounded-lg px-3 py-2 text-zinc-300 hover:bg-white/5 hover:text-white">FAQ</a>
                            <hr class="my-2 border-white/10">
                            @auth
                                <a href="{{ route('dashboard') }}" class="rounded-lg px-3 py-2 font-medium text-emerald-400 hover:bg-white/5">Dashboard</a>
                            @else
                                @if (Route::has('login'))
                                    <a href="{{ route('login') }}" class="rounded-lg px-3 py-2 text-zinc-300 hover:bg-white/5 hover:text-white">Log in</a>
                                @endif
                                @if (Route::has('register'))
                                    <a href="{{ route('register') }}" class="rounded-lg px-3 py-2 font-medium text-emerald-400 hover:bg-white/5">Get started</a>
                                @endif
                            @endauth
                        </nav>
                    </div>
                </details>
            </div>
        </header>

        <main>
            {{-- Hero --}}
            <section class="landing-grid-pattern relative overflow-hidden pt-28 pb-20 sm:pt-36 sm:pb-28">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-b from-transparent via-transparent to-[var(--landing-dominant)]"></div>
                <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-3xl text-center">
                        <p class="landing-fade-in mb-6 inline-flex items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-4 py-1.5 text-sm font-medium text-emerald-300">
                            <span class="size-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            Zero-knowledge secret sharing
                        </p>
                        <h1 class="landing-fade-in landing-fade-in-delay-1 text-4xl font-bold tracking-tight sm:text-5xl lg:text-6xl">
                            Share passwords.<br>
                            <span class="bg-gradient-to-r from-emerald-300 to-teal-400 bg-clip-text text-transparent">Never share trust.</span>
                        </h1>
                        <p class="landing-fade-in landing-fade-in-delay-2 mx-auto mt-6 max-w-2xl text-lg leading-relaxed text-zinc-400">
                            {{ config('app.name') }} encrypts secrets in your browser before they reach our servers.
                            Only people you invite can decrypt them — and we can never read your message.
                        </p>
                        <div class="landing-fade-in landing-fade-in-delay-3 mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                            @if (Route::has('register'))
                                <a href="{{ route('register') }}" class="landing-cta-primary w-full sm:w-auto">
                                    Create your first Send
                                    <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                    </svg>
                                </a>
                            @endif
                            <a href="#how-it-works" class="landing-cta-secondary w-full sm:w-auto">
                                See how it works
                            </a>
                        </div>
                    </div>

                    {{-- Product mockup --}}
                    <div class="landing-fade-in landing-fade-in-delay-4 relative mx-auto mt-16 max-w-4xl">
                        <div class="absolute -inset-4 rounded-3xl bg-emerald-500/10 blur-3xl"></div>
                        <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-[var(--landing-surface)] shadow-2xl shadow-black/40">
                            <div class="flex items-center gap-2 border-b border-white/10 px-4 py-3">
                                <span class="size-3 rounded-full bg-red-500/80"></span>
                                <span class="size-3 rounded-full bg-amber-500/80"></span>
                                <span class="size-3 rounded-full bg-emerald-500/80"></span>
                                <span class="ms-4 text-xs text-zinc-500">New Send — encrypted client-side</span>
                            </div>
                            <div class="grid gap-6 p-6 sm:grid-cols-2 sm:p-8">
                                <div class="space-y-4">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-zinc-500">Send name</label>
                                        <div class="rounded-lg border border-white/10 bg-white/5 px-4 py-2.5 text-sm">Production database credentials</div>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-zinc-500">Viewers</label>
                                        <div class="flex flex-wrap gap-2">
                                            <span class="rounded-full bg-emerald-500/15 px-3 py-1 text-xs text-emerald-300 ring-1 ring-emerald-500/30">dev@acme.io</span>
                                            <span class="rounded-full bg-emerald-500/15 px-3 py-1 text-xs text-emerald-300 ring-1 ring-emerald-500/30">ops@acme.io</span>
                                        </div>
                                    </div>
                                    <div>
                                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-zinc-500">Expires in</label>
                                        <div class="rounded-lg border border-white/10 bg-white/5 px-4 py-2.5 text-sm">24 hours</div>
                                    </div>
                                </div>
                                <div class="space-y-4">
                                    <div>
                                        <label class="mb-1.5 block text-xs font-medium uppercase tracking-wider text-zinc-500">Secret message</label>
                                        <div class="relative rounded-lg border border-white/10 bg-white/5 p-4 font-mono text-xs leading-relaxed text-zinc-400">
                                            <span class="blur-[3px] select-none">host: db.prod.internal<br>user: deploy_svc<br>pass: ••••••••••••</span>
                                            <div class="absolute inset-0 flex items-center justify-center">
                                                <span class="flex items-center gap-2 rounded-full bg-emerald-500/20 px-3 py-1.5 text-xs font-medium text-emerald-300 ring-1 ring-emerald-500/40">
                                                    <svg class="size-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                                                    </svg>
                                                    AES-256-GCM encrypted
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex items-center justify-between rounded-lg border border-emerald-500/20 bg-emerald-500/5 px-4 py-3">
                                        <div class="flex items-center gap-2 text-xs text-emerald-300">
                                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                                            </svg>
                                            Password stripped before upload
                                        </div>
                                        <span class="text-xs text-zinc-500">Argon2id · Web Worker</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Social proof --}}
            <section class="border-y border-white/5 bg-[var(--landing-surface)]/50 py-12">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <p class="mb-8 text-center text-sm font-medium uppercase tracking-widest text-zinc-500">Built for teams who take security seriously</p>
                    <div class="grid grid-cols-2 gap-8 sm:grid-cols-4">
                        <div class="text-center">
                            <p class="text-3xl font-bold text-emerald-400">AES-256</p>
                            <p class="mt-1 text-sm text-zinc-500">GCM encryption</p>
                        </div>
                        <div class="text-center">
                            <p class="text-3xl font-bold text-emerald-400">Argon2id</p>
                            <p class="mt-1 text-sm text-zinc-500">Key derivation</p>
                        </div>
                        <div class="text-center">
                            <p class="text-3xl font-bold text-emerald-400">0</p>
                            <p class="mt-1 text-sm text-zinc-500">Plaintext on server</p>
                        </div>
                        <div class="text-center">
                            <p class="text-3xl font-bold text-emerald-400">100</p>
                            <p class="mt-1 text-sm text-zinc-500">Viewers per Send</p>
                        </div>
                    </div>
                </div>
            </section>

            {{-- Features --}}
            <section id="features" class="py-20 sm:py-28">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Security without the friction</h2>
                        <p class="mt-4 text-lg text-zinc-400">
                            Every layer is designed so your secrets stay yours — from creation to expiry.
                        </p>
                    </div>

                    <div class="mt-16 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ([
                            ['icon' => 'M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z', 'title' => 'Client-side encryption', 'desc' => 'Secrets are encrypted in your browser with AES-256-GCM before submission. The server stores only ciphertext.'],
                            ['icon' => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z', 'title' => 'Invite-only access', 'desc' => 'List up to 100 viewer emails per Send. Only registered users on that list — plus you — can open it.'],
                            ['icon' => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z', 'title' => 'Automatic expiry', 'desc' => 'Set a lifetime from 1 hour to 30 days. Expired Sends are permanently deleted — no lingering secrets.'],
                            ['icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z', 'title' => 'Hardened accounts', 'desc' => 'Passkeys, two-factor authentication, and email verification protect who can access your Sends.'],
                            ['icon' => 'M13 10V3L4 14h7v7l9-11h-7z', 'title' => 'Off-thread crypto', 'desc' => 'Encryption and decryption run in Web Workers so your UI stays responsive during heavy Argon2id operations.'],
                            ['icon' => 'M3 15a4 4 0 004 4h9a5 5 0 10-.1-9.999 5.002 5.002 0 10-9.78 2.096A4.001 4.001 0 003 15z', 'title' => 'Defense in depth', 'desc' => 'Strict Content Security Policy, short-lived Redis sessions, and Laravel encrypted casts at rest.'],
                        ] as $feature)
                            <div class="group h-full rounded-2xl border border-white/10 bg-[var(--landing-surface)] p-6 transition hover:border-emerald-500/30 hover:bg-[var(--landing-surface-elevated)]">
                                <div class="mb-4 flex size-11 items-center justify-center rounded-xl bg-emerald-500/15 text-emerald-400 ring-1 ring-emerald-500/25 transition group-hover:bg-emerald-500/25">
                                    <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="{{ $feature['icon'] }}" />
                                    </svg>
                                </div>
                                <h3 class="text-lg font-semibold">{{ $feature['title'] }}</h3>
                                <p class="mt-2 text-sm leading-relaxed text-zinc-400">{{ $feature['desc'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- How it works --}}
            <section id="how-it-works" class="border-y border-white/5 bg-[var(--landing-surface)]/30 py-20 sm:py-28">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="mx-auto max-w-2xl text-center">
                        <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Five steps. Zero exposure.</h2>
                        <p class="mt-4 text-lg text-zinc-400">From creation to decryption, your secret never touches our servers in plaintext.</p>
                    </div>

                    <ol class="mx-auto mt-16 max-w-3xl space-y-0">
                        @foreach ([
                            ['step' => '01', 'title' => 'Create a Send', 'desc' => 'Name it, add viewer emails, write your secret, and pick an expiry between 1 hour and 30 days.'],
                            ['step' => '02', 'title' => 'Encrypt locally', 'desc' => 'Optional password protection encrypts the message with AES-256-GCM. The key is derived via Argon2id in a Web Worker.'],
                            ['step' => '03', 'title' => 'Share securely', 'desc' => 'Only registered users whose emails you listed can open the Send. You can always view your own Sends too.'],
                            ['step' => '04', 'title' => 'Decrypt in browser', 'desc' => 'Authorized viewers enter the shared password locally. Decryption runs off the main thread — we never see it.'],
                            ['step' => '05', 'title' => 'Auto-delete', 'desc' => 'When the timer runs out, the Send is permanently removed. No recovery, no residue.'],
                        ] as $item)
                            <li class="relative flex gap-6 pb-12 last:pb-0">
                                <div class="flex flex-col items-center">
                                    <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-emerald-500/15 text-sm font-bold text-emerald-400 ring-1 ring-emerald-500/30">{{ $item['step'] }}</span>
                                    <span class="mt-2 w-px grow bg-gradient-to-b from-emerald-500/30 to-transparent last:hidden"></span>
                                </div>
                                <div class="pt-1.5">
                                    <h3 class="text-lg font-semibold">{{ $item['title'] }}</h3>
                                    <p class="mt-2 text-sm leading-relaxed text-zinc-400">{{ $item['desc'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </section>

            {{-- Security model --}}
            <section id="security" class="py-20 sm:py-28">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="grid items-center gap-12 lg:grid-cols-2">
                        <div>
                            <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">We cannot read your secrets</h2>
                            <p class="mt-4 text-lg leading-relaxed text-zinc-400">
                                {{ config('app.name') }} is built on a zero-knowledge model. Password-protected Sends are encrypted before they leave your browser.
                                If you lose the password, the secret cannot be recovered — by design.
                            </p>
                            <ul class="mt-8 space-y-3">
                                @foreach (['Client-side E2E encryption protects content from operators', 'Laravel encrypted cast secures data at rest', 'Per-Send viewer ACL controls who can open', 'Strict CSP blocks XSS and injection attacks'] as $point)
                                    <li class="flex items-start gap-3 text-sm text-zinc-300">
                                        <svg class="mt-0.5 size-5 shrink-0 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                        {{ $point }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                        <div class="overflow-hidden rounded-2xl border border-white/10 bg-[var(--landing-surface)]">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b border-white/10 bg-white/5">
                                        <th class="px-5 py-3 text-start font-medium text-zinc-400">Layer</th>
                                        <th class="px-5 py-3 text-start font-medium text-zinc-400">Protection</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-white/5">
                                    @foreach ([
                                        ['Client-side E2E', 'Message content from server operator'],
                                        ['Laravel encrypted cast', 'Stored payload at rest'],
                                        ['Passkeys + 2FA', 'Account access'],
                                        ['Per-Send viewer ACL', 'Who can open a Send'],
                                        ['Redis sessions', 'Session hijacking surface'],
                                        ['Content Security Policy', 'XSS and injection'],
                                    ] as [$layer, $protection])
                                        <tr class="transition hover:bg-white/[0.02]">
                                            <td class="px-5 py-3.5 font-medium text-zinc-200">{{ $layer }}</td>
                                            <td class="px-5 py-3.5 text-zinc-400">{{ $protection }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </section>

            {{-- FAQ --}}
            <section id="faq" class="py-20 sm:py-28">
                <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
                    <div class="text-center">
                        <h2 class="text-3xl font-bold tracking-tight sm:text-4xl">Frequently asked questions</h2>
                        <p class="mt-4 text-lg text-zinc-400">Everything you need to know before sharing your first secret.</p>
                    </div>

                    <div class="mt-12 space-y-3">
                        @foreach ([
                            ['q' => 'Can '.config('app.name').' staff read my secrets?', 'a' => 'No. Password-protected Sends are encrypted in your browser before upload. We store only ciphertext and never receive your decryption password. We cannot recover a lost password.'],
                            ['q' => 'Who can view a Send?', 'a' => 'Only registered users whose email addresses you listed as viewers, plus you as the owner. There are no public links — access is strictly invite-based.'],
                            ['q' => 'What encryption is used?', 'a' => 'Password protection uses AES-256-GCM for encryption. Keys are derived with Argon2id in a Web Worker. At rest, Laravel\'s encrypted cast adds another layer of server-side protection.'],
                            ['q' => 'How long do Sends last?', 'a' => 'You choose an expiry between 1 hour and 30 days when creating a Send. Expired Sends are permanently deleted by a scheduled task that runs every 30 minutes.'],
                            ['q' => 'How many Sends can I have?', 'a' => 'Each user can have up to 15 active Sends at a time, with up to 100 viewer emails per Send and a 1,000-character plaintext message limit before encryption.'],
                            ['q' => 'What protects my account?', 'a' => 'Accounts support passkeys, two-factor authentication (TOTP), and email verification. Sessions are short-lived and stored in Redis to minimize hijacking risk.'],
                            ['q' => 'Is this better than sharing via chat or email?', 'a' => 'Yes. Chat and email leave plaintext copies in message history, logs, and backups. '.config('app.name').' encrypts before transmission, limits access to named viewers, and auto-deletes on expiry.'],
                            ['q' => 'What happens if I forget the Send password?', 'a' => 'The secret cannot be recovered. This is intentional — it proves we never had access to the decryption key. Only share passwords through a separate secure channel.'],
                        ] as $faq)
                            <details class="group rounded-xl border border-white/10 bg-[var(--landing-surface)] open:border-emerald-500/20 open:bg-[var(--landing-surface-elevated)]">
                                <summary class="flex cursor-pointer list-none items-center justify-between gap-4 px-5 py-4 font-medium [&::-webkit-details-marker]:hidden">
                                    {{ $faq['q'] }}
                                    <svg class="size-5 shrink-0 text-zinc-500 transition group-open:rotate-180" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </summary>
                                <p class="border-t border-white/5 px-5 py-4 text-sm leading-relaxed text-zinc-400">{{ $faq['a'] }}</p>
                            </details>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- Final CTA --}}
            <section class="relative overflow-hidden py-20 sm:py-28">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-r from-emerald-500/10 via-transparent to-teal-500/10"></div>
                <div class="relative mx-auto max-w-4xl px-4 text-center sm:px-6 lg:px-8">
                    <h2 class="text-3xl font-bold tracking-tight sm:text-4xl lg:text-5xl">
                        Stop sharing secrets in plain text
                    </h2>
                    <p class="mx-auto mt-6 max-w-2xl text-lg text-zinc-400">
                        Create your first Send in minutes. Encrypt locally, share with confidence, and let expiry handle the cleanup.
                    </p>
                    <div class="mt-10 flex flex-col items-center justify-center gap-4 sm:flex-row">
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="landing-cta-primary w-full sm:w-auto">
                                Get started free
                                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                </svg>
                            </a>
                        @endif
                        @if (Route::has('login'))
                            <a href="{{ route('login') }}" class="landing-cta-secondary w-full sm:w-auto">
                                Log in to your account
                            </a>
                        @endif
                    </div>
                </div>
            </section>
        </main>

        {{-- Footer --}}
        <footer class="border-t border-white/5 bg-[var(--landing-surface)]/50">
            <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
                <div class="grid gap-8 sm:grid-cols-2 lg:grid-cols-4">
                    <div class="sm:col-span-2 lg:col-span-1">
                        <a href="{{ route('home') }}" class="flex items-center gap-3">
                            <span class="flex size-8 items-center justify-center rounded-lg bg-emerald-500/15 ring-1 ring-emerald-500/30">
                                <x-app-logo-icon class="size-4 fill-emerald-400" />
                            </span>
                            <span class="font-semibold">{{ config('app.name') }}</span>
                        </a>
                        <p class="mt-4 max-w-xs text-sm leading-relaxed text-zinc-500">
                            End-to-end encrypted secret sharing for teams who refuse to compromise on security.
                        </p>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-400">Product</h3>
                        <ul class="mt-4 space-y-2 text-sm text-zinc-500">
                            <li><a href="#features" class="transition hover:text-white">Features</a></li>
                            <li><a href="#how-it-works" class="transition hover:text-white">How it works</a></li>
                            <li><a href="#security" class="transition hover:text-white">Security</a></li>
                            <li><a href="#faq" class="transition hover:text-white">FAQ</a></li>
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-400">Account</h3>
                        <ul class="mt-4 space-y-2 text-sm text-zinc-500">
                            @if (Route::has('login'))
                                <li><a href="{{ route('login') }}" class="transition hover:text-white">Log in</a></li>
                            @endif
                            @if (Route::has('register'))
                                <li><a href="{{ route('register') }}" class="transition hover:text-white">Register</a></li>
                            @endif
                            @auth
                                <li><a href="{{ route('dashboard') }}" class="transition hover:text-white">Dashboard</a></li>
                            @endauth
                        </ul>
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold uppercase tracking-wider text-zinc-400">Security</h3>
                        <ul class="mt-4 space-y-2 text-sm text-zinc-500">
                            <li>AES-256-GCM encryption</li>
                            <li>Argon2id key derivation</li>
                            <li>Zero-knowledge architecture</li>
                            <li>Automatic secret expiry</li>
                        </ul>
                    </div>
                </div>
                <div class="mt-12 flex flex-col items-center justify-between gap-4 border-t border-white/5 pt-8 sm:flex-row">
                    <p class="text-sm text-zinc-500">&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
                    <p class="text-xs text-zinc-600">Secrets encrypted client-side. Server stores ciphertext only.</p>
                </div>
            </div>
        </footer>

        <script nonce="{{ Illuminate\Support\Facades\Vite::cspNonce() }}">
            const header = document.getElementById('site-header');
            const onScroll = () => {
                header.classList.toggle('border-white/10', window.scrollY > 16);
                header.classList.toggle('bg-[var(--landing-dominant)]/80', window.scrollY > 16);
                header.classList.toggle('backdrop-blur-lg', window.scrollY > 16);
            };
            onScroll();
            window.addEventListener('scroll', onScroll, { passive: true });
        </script>
    </body>
</html>
