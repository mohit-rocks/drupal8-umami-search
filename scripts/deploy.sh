#!/bin/bash

# Deploys the build and runs database and configuration updates.

set -e

if [ "$LANDO_MOUNT" == "" ]; then
  echo "This script must be run from inside Lando."
  exit 0
fi

terminus drush $PANTHEON_SITE_NAME.dev -- sset system.maintenance_mode TRUE

if /app/scripts/deploy_code.sh ; then
  terminus drush $PANTHEON_SITE_NAME.dev -- updb -y
  terminus drush $PANTHEON_SITE_NAME.dev -- cim -y
fi

terminus drush $PANTHEON_SITE_NAME.dev -- sset system.maintenance_mode FALSE
