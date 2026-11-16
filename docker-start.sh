#!/usr/bin/env bash
set -e

# Some managed Docker runtimes re-enable Apache modules at container start.
# Ensure the PHP image has exactly one MPM before Apache launches.
a2dismod mpm_event mpm_worker 2>/dev/null || true
a2enmod mpm_prefork rewrite >/dev/null
exec apache2-foreground
