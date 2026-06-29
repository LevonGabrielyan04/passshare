# DropKey

DropKey is a end-to-end encrypted application for sharing passwords and other secrets with specific people. Secrets are encrypted in the browser before they ever reach the server. The server stores only ciphertext and never sees the decryption password.

## How it works

1. **Create a Send** — Give it a name, add up to 100 viewer email addresses, write the secret, and choose when it expires (1 hour to 30 days).
2. **Optional password protection** — If you set a password, the message is encrypted client-side with **AES-256-GCM**. The key is derived with **Argon2id** in a Web Worker. The password is stripped from the form before submission and is never sent to the server.
3. **Share with viewers** — Only registered users whose emails you listed can open the Send. Owners can also view their own Sends.
4. **Decrypt in the browser** — Authorized viewers enter the shared password locally. Decryption runs off the main thread in a Web Worker.
5. **Automatic expiry** — Sends are permanently deleted after their expiry time. A scheduled task removes expired records every 30 minutes.

### Security model

| Layer | What it protects |
| --- | --- |
| Client-side E2E encryption | Message content from the server operator and database |
| Laravel `encrypted` cast | Stored payload at rest on the server |
| Passkeys + 2FA + email verification | Account access |
| Per-Send viewer ACL | Who can open a given Send |
| Short-lived Redis sessions | Session hijacking surface |
| Strict Content Security Policy | XSS and injection |

The application cannot decrypt password-protected messages. If you lose the password, the secret cannot be recovered.

## Requirements

- PHP 8.3+
- [Composer](https://getcomposer.org/)
- Node.js 20+ and npm
- MariaDB 11+
- Redis 7+

## Local development

### 1. Clone and install dependencies

```bash
git clone <repository-url> passshare
cd passshare

composer install
npm install
```

### 2. Environment

```bash
cp .env.example .env
php artisan key:generate
```

Generate a separate secret for passkey user handles (same `base64:` format as `APP_KEY`):

```bash
php artisan tinker --execute 'echo "base64:".base64_encode(random_bytes(32));'
```

Set the output as `PASSKEYS_USER_HANDLE_SECRET` in `.env`.

Configure database and Redis credentials in `.env`. The app expects MariaDB and Redis for sessions, cache, and queues.

### 3. Database

Create the MariaDB database, then run migrations:

```bash
php artisan migrate
```

### 4. Frontend assets

```bash
npm run build
```

For development with hot reload, Vite, and a queue listener:

```bash
composer run dev
```

Or run processes separately:

```bash
php artisan serve
npm run dev
```

### 5. Scheduled tasks

Expired Sends are removed by the `sends:delete-expired` command, scheduled **every 30 minutes** in `bootstrap/app.php`.

**Local development** — keep the scheduler running in a separate terminal:

```bash
php artisan schedule:work
```

**Production (cron)** — add a single cron entry on the server:

```cron
* * * * * cd /path/to/passshare && php artisan schedule:run >> /dev/null 2>&1
```

You can also run the cleanup manually:

```bash
php artisan sends:delete-expired
```

## Configuration

Key environment variables (see `.env.example` for the full list):

| Variable | Description |
| --- | --- |
| `APP_URL` | Public URL of the app (required for passkeys) |
| `PASSKEYS_USER_HANDLE_SECRET` | Secret for WebAuthn user handle derivation |
| `SESSION_LIFETIME` | Session lifetime in minutes (default: 5) |
| `MAX_SENDS_PER_USER` | Maximum active Sends per user (default: 15) |
| `MAX_MESSAGE_LENGTH` | Plaintext message limit before encryption (default: 1000) |
| `ENCRYPTED_MAX_MESSAGE_LENGTH` | Stored ciphertext limit (default: 5372) |
| `SEND_CACHE_TTL` | Send list cache TTL in minutes (default: 60) |
| `TRUSTED_PROXIES` | Proxy IPs when behind nginx or a tunnel (e.g. `127.0.0.1`) |

Password-protected Sends require a password of at least **15 characters** (`config/send.php`).

## Docker deployment

The repository includes a production-oriented Docker Compose stack (app, queue worker, scheduler, MariaDB, Redis, optional Cloudflare Tunnel).

```bash
cp .env.docker.example .env
# Set APP_KEY, DB passwords, PASSKEYS_USER_HANDLE_SECRET, and optionally TUNNEL_TOKEN

# Local Docker (localhost port)
./docker/bin/compose.sh up

# Production (Cloudflare Tunnel only, no published host port)
./docker/bin/compose.sh up --tunnel
```

Compose reads port mapping from `.env.compose` and application secrets from `.env` via `env_file`, so special characters in passwords are not mangled by variable interpolation.

The **scheduler** service runs `php artisan schedule:work` automatically. No separate cron setup is needed inside Docker.

Optional build-time secrets for the Livewire Flux license:

```bash
FLUX_USERNAME=...
FLUX_LICENSE_KEY=...
```

## Testing

PHP (Pest):

```bash
php artisan test --compact
```

JavaScript (Vitest):

```bash
npm test
```

Full CI-style check:

```bash
composer test
```

## Tech stack

- [Laravel 13](https://laravel.com/)
- [Livewire 4](https://livewire.laravel.com/) + [Flux UI](https://fluxui.dev/)
- [Laravel Fortify](https://laravel.com/docs/fortify) — registration, 2FA, passkeys
- [Spatie CSP](https://github.com/spatie/laravel-csp) — strict Content Security Policy
- Client crypto — Web Crypto API, Argon2id (`hash-wasm`), Web Workers

