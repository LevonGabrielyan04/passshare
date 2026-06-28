#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

export COMPOSE_ENV_FILES="${COMPOSE_ENV_FILES:-.env.compose}"

if ! docker compose version >/dev/null 2>&1; then
    echo "Docker Compose plugin is required (docker compose)." >&2
    echo "Install: https://docs.docker.com/compose/install/" >&2
    exit 1
fi

if [[ ! -f .env ]]; then
    cp .env.docker.example .env
    echo "Created .env from .env.docker.example — set APP_KEY, DB passwords, and PASSKEYS_USER_HANDLE_SECRET."
fi

missing=()
for key in APP_KEY DB_PASSWORD MARIADB_ROOT_PASSWORD PASSKEYS_USER_HANDLE_SECRET; do
    if ! grep -qE "^${key}=.+$" .env; then
        missing+=("$key")
    fi
done

if ((${#missing[@]} > 0)); then
    echo "Missing required .env values: ${missing[*]}" >&2
    exit 1
fi

command="${1:-up}"
shift || true

compose_files=(-f docker-compose.yml)
compose_profiles=()
tunnel_mode=0

if [[ "${1:-}" == "--tunnel" ]]; then
    tunnel_mode=1
    shift
    compose_profiles=(--profile tunnel)

    if [[ "${command}" == "up" ]] && ! grep -qE '^TUNNEL_TOKEN=.+$' .env; then
        echo "TUNNEL_TOKEN is required for tunnel mode (Cloudflare Zero Trust → Networks → Tunnels)." >&2
        exit 1
    fi
elif [[ "${command}" == "up" ]]; then
    compose_files+=(-f docker-compose.dev.yml)
fi

case "$command" in
    build)
        docker compose build --pull "$@"
        ;;
    up)
        docker compose "${compose_files[@]}" "${compose_profiles[@]}" up -d "$@"

        if ((tunnel_mode)); then
            echo "App is reachable only via Cloudflare Tunnel (no host port published)."
        else
            echo "App (local dev): http://127.0.0.1:${APP_PORT:-8080}"
            echo "Production: ./docker/bin/compose.sh up --tunnel"
        fi
        ;;
    down)
        docker compose "${compose_files[@]}" --profile tunnel down "$@"
        ;;
    logs)
        docker compose "${compose_files[@]}" "${compose_profiles[@]}" logs -f "$@"
        ;;
    *)
        docker compose "${compose_files[@]}" "$command" "$@"
        ;;
esac
