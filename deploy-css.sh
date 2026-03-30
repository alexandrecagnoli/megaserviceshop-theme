#!/usr/bin/env bash
set -e

rsync -rtvz --no-perms --no-times \
  --exclude='.DS_Store' \
  megaservice/assets/dist/ \
  nuttyguru.com_j8g81qmwucn@93.90.201.182:/var/www/vhosts/nuttyguru.com/megaserviceshop.nuttyguru.com/themes/megaservice/assets/dist/

echo "✅ CSS/JS déployés."
