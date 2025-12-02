#!/usr/bin/env bash

set -e

echo "➡Build du thème…"
npm run build

echo "➡Déploiement vers le serveur…"

rsync -rtvz --delete \
  --no-perms --no-times \
  --exclude='.DS_Store' \
  megaservice/ \
  nuttyguru.com_j8g81qmwucn@93.90.201.182:/var/www/vhosts/nuttyguru.com/megaserviceshop.nuttyguru.com/themes/megaservice/


echo "✅ Déploiement terminé."
