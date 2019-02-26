#!/usr/bin/env bash

composer install

if [ ! -d "docroot/themes/contrib" ]; then
  mkdir docroot/themes/contrib
fi

if [ ! -d "docroot/themes/contrib/umami" ]; then
  cp -R docroot/core/profiles/demo_umami/themes/umami docroot/themes/contrib/
fi

if [ ! -d "docroot/modules/contrib/demo_umami_content" ]; then
  cp -R docroot/core/profiles/demo_umami/modules/demo_umami_content docroot/modules/contrib/
fi

lando drush site-install config_installer config_installer_sync_configure_form.sync_directory=../config/sync/ --yes
lando drush ev '\Drupal::classResolver()->getInstanceFromDefinition(Drupal\demo_umami_content\InstallHelper::class)->importContent();'
lando drush ev '\Drupal::classResolver()->getInstanceFromDefinition(Drupal\demo_umami_search_content\InstallHelper::class)->importContent();'
lando drush search-api:reset-tracker
lando drush search-api:index
lando drush cr
