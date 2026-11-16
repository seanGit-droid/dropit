#!/usr/bin/env bash
set -e

# Some managed Docker runtimes re-enable Apache modules at container start.
# Ensure the PHP image has exactly one MPM before Apache launches.
a2dismod mpm_event mpm_worker 2>/dev/null || true
a2enmod mpm_prefork rewrite >/dev/null

# Railway healthchecks use PORT, while the existing public domain targets 80.
# Serve both ports when they differ so a healthy image can pass its rollout
# checks without changing the existing domain configuration.
if [[ -n "${PORT:-}" && "$PORT" != "80" ]]; then
  if [[ ! "$PORT" =~ ^[0-9]+$ ]] || (( PORT < 1 || PORT > 65535 )); then
    echo "Invalid HTTP PORT" >&2
    exit 1
  fi
  printf 'Listen %s\n' "$PORT" > /etc/apache2/conf-available/dropit-port.conf
  a2enconf dropit-port >/dev/null
  sed -i "s/<VirtualHost \*:80>/<VirtualHost *:80 *:${PORT}>/" /etc/apache2/sites-available/000-default.conf
fi
exec apache2-foreground
