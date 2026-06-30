#!/usr/bin/env bash
# Pull the latest app image from GHCR and restart the Docker Compose stack.
# Run on the server after GitHub Actions builds and pushes a new image.
#
# One-time server setup:
#   1. Install Docker Engine + Compose plugin.
#   2. Clone this repo to your deploy path (e.g. /opt/passshare).
#   3. cp .env.docker.example .env and fill in production secrets.
#   4. Add the deploy user's SSH public key for GitHub Actions.
#   5. Set GHCR_USERNAME and a GitHub PAT with read:packages (GHCR_TOKEN) in the server .env
#      (CI may override GHCR_USERNAME via GITHUB_ACTOR on each deploy).
#
# Usage:
#   APP_IMAGE=ghcr.io/owner/passshare:sha ./docker/bin/deploy.sh [--tunnel]

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

export COMPOSE_ENV_FILES="${COMPOSE_ENV_FILES:-.env.compose}"

if [[ -z "${APP_IMAGE:-}" ]] && grep -qE '^APP_IMAGE=.+$' .env 2>/dev/null; then
    APP_IMAGE="$(grep -E '^APP_IMAGE=' .env | head -1 | cut -d= -f2- | tr -d '"')"
fi

if [[ -z "${APP_IMAGE:-}" ]]; then
    echo "APP_IMAGE is required (GHCR image tag, e.g. ghcr.io/owner/passshare:latest)." >&2
    exit 1
fi

export APP_IMAGE

if ! docker compose version >/dev/null 2>&1; then
    echo "Docker Compose plugin is required (docker compose)." >&2
    exit 1
fi

if [[ ! -f .env ]]; then
    echo "Missing .env — copy .env.docker.example to .env and configure production secrets." >&2
    exit 1
fi

GHCR_USERNAME="${GHCR_USERNAME:-${GITHUB_ACTOR:-}}"

if [[ -z "$GHCR_USERNAME" ]] && grep -qE '^GHCR_USERNAME=.+$' .env 2>/dev/null; then
    GHCR_USERNAME="$(grep -E '^GHCR_USERNAME=' .env | head -1 | cut -d= -f2- | tr -d '"')"
fi

if [[ -z "${GHCR_TOKEN:-}" ]] && grep -qE '^GHCR_TOKEN=.+$' .env 2>/dev/null; then
    GHCR_TOKEN="$(grep -E '^GHCR_TOKEN=' .env | head -1 | cut -d= -f2- | tr -d '"')"
fi

tunnel_mode=0

while [[ $# -gt 0 ]]; do
    case "$1" in
        --tunnel)
            tunnel_mode=1
            shift
            ;;
        *)
            echo "Unknown option: $1" >&2
            exit 1
            ;;
    esac
done

if [[ -n "${GHCR_TOKEN:-}" ]]; then
    if [[ -z "$GHCR_USERNAME" ]]; then
        echo "GHCR_USERNAME or GITHUB_ACTOR is required when GHCR_TOKEN is set." >&2
        exit 1
    fi

    echo "$GHCR_TOKEN" | docker login ghcr.io -u "$GHCR_USERNAME" --password-stdin
fi

if git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    git fetch origin main
    git checkout main
    git pull --ff-only origin main
fi

compose_files=(-f docker-compose.yml -f docker-compose.deploy.yml)
compose_profiles=()

if ((tunnel_mode)); then
    compose_profiles=(--profile tunnel)

    if ! grep -qE '^TUNNEL_TOKEN=.+$' .env; then
        echo "TUNNEL_TOKEN is required for --tunnel (Cloudflare Zero Trust → Networks → Tunnels)." >&2
        exit 1
    fi
fi

docker compose "${compose_files[@]}" pull app queue scheduler
docker compose "${compose_files[@]}" "${compose_profiles[@]}" up -d --remove-orphans

docker image prune -f --filter "until=24h" >/dev/null 2>&1 || true

if ((tunnel_mode)); then
    echo "Deployed ${APP_IMAGE} — app is reachable via Cloudflare Tunnel only."
else
    echo "Deployed ${APP_IMAGE} — app: http://127.0.0.1:${APP_PORT:-8080}"
    echo "Production: ./docker/bin/deploy.sh --tunnel"
fi
