#!/bin/bash

# Builds the project artifact from sources and deploys it to Pantheon.

set -e

if [ "$LANDO_MOUNT" == "" ]; then
  echo "This script must be run from inside Lando."
  exit 0
fi

TMP=/tmp/deploy

terminus site:info $PANTHEON_SITE_NAME
rm -rf $TMP

git clone -n -b master $(terminus connection:info $PANTHEON_SITE_NAME.dev --field=git_url) $TMP
cd $TMP
git checkout HEAD -- vendor/autoload.php vendor/composer/autoload_real.php vendor/composer/autoload_static.php || true

mkdir build
cd build
cp -r $LANDO_MOUNT/.git .git
git checkout -f master
rm -rf .git

mv -t . $TMP/vendor $TMP/.git || true
composer build-assets
git add -A
git commit -q -m "Auto commit from source @ $(git -C $LANDO_MOUNT log --format=%h -1 master)"

CONNECTION_MODE=$(terminus env:info $PANTHEON_SITE_NAME.dev --field=connection_mode)

if [ "$CONNECTION_MODE" != "git" ]; then
  CODE_DIFF=$(terminus env:diffstat $PANTHEON_SITE_NAME.dev --format=json)

  if [ "$CODE_DIFF" != "[]" ]; then
    echo "You have uncommitted changes on Pantheon."
    echo "Please login to your Pantheon dashboard, commit those changes and then try again."
    exit 5
  fi

  terminus connection:set $PANTHEON_SITE_NAME.dev git
fi

git push origin master
rm -rf $TMP || true
