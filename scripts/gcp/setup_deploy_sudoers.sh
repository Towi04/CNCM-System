#!/usr/bin/env bash
# Run once on the GCP VM as a sudo-capable user:
#   sudo bash scripts/gcp/setup_deploy_sudoers.sh
set -euo pipefail

DEPLOY_USER="${DEPLOY_USER:-deploy}"
WEB_USER="${WEB_USER:-www-data}"

id "$DEPLOY_USER" >/dev/null

usermod -aG "$WEB_USER" "$DEPLOY_USER" || true

# Passwordless sudo for deploy operations used by scripts/gcp/deploy_release.sh
cat > /etc/sudoers.d/deploy-hay <<EOF
${DEPLOY_USER} ALL=(ALL) NOPASSWD: /bin/chown, /usr/bin/chown, /bin/chmod, /usr/bin/chmod, /usr/bin/find, /bin/cp, /usr/bin/cp, /bin/rm, /usr/bin/rm, /bin/mkdir, /usr/bin/mkdir, /usr/bin/tee, /usr/bin/systemctl, /usr/bin/composer, /usr/bin/php, /usr/bin/test, /bin/test
EOF
chmod 440 /etc/sudoers.d/deploy-hay
visudo -cf /etc/sudoers.d/deploy-hay

echo "OK: sudoers for ${DEPLOY_USER} installed. Re-run Deploy GCP → staging."
