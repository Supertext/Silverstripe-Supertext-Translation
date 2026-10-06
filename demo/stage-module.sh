#!/bin/sh
# Copies the module's files into demo/module, which the demo installs through a Composer
# path repository. Run it before `composer install`/`composer update` in demo/project.
set -e
cd "$(dirname "$0")/.."
rm -rf demo/module
mkdir -p demo/module
cp -R composer.json _config client lang src templates demo/module/
