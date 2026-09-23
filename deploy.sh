#!/usr/bin/env bash
#
# Déploiement vers la préprod.
#
# Ce script ne déployait QUE le thème (megaservice/), alors que le site a besoin
# de trois arborescences plus un vidage de cache. Un dev qui le lançait mettait
# en ligne une version partielle sans s'en apercevoir — typiquement un module
# modifié qui ne partait pas (cf. TECH_DEBT 🟠). La clé SSH de déploiement n'est
# d'ailleurs pas sur les postes de dev mais dans les secrets GitHub, donc le
# rsync direct échouait de toute façon.
#
# Il délègue désormais au workflow GitHub Actions, seul chemin complet :
# thème + overrides + modules + vidage du cache PrestaShop.
#
# Usage : bash deploy.sh [branche]   (défaut : la branche courante)

set -euo pipefail

BRANCH="${1:-$(git rev-parse --abbrev-ref HEAD)}"

if ! command -v gh >/dev/null 2>&1; then
  echo "✗ La CLI GitHub (gh) est requise : https://cli.github.com" >&2
  exit 1
fi

if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
  echo "✗ Des modifications ne sont pas commitées." >&2
  echo "  La CI déploie ce qui est sur le DISTANT : commite et pousse d'abord." >&2
  exit 1
fi

LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse "origin/$BRANCH" 2>/dev/null || echo "")
if [ "$LOCAL" != "$REMOTE" ]; then
  echo "✗ '$BRANCH' locale et distante diffèrent — la CI déploierait autre chose." >&2
  echo "  Lance : git push origin $BRANCH" >&2
  exit 1
fi

echo "➡ Déploiement de '$BRANCH' via GitHub Actions…"
gh workflow run deploy.yml --ref "$BRANCH"

sleep 6
RUN=$(gh run list --workflow=deploy.yml --limit 1 --json databaseId -q '.[].databaseId')
echo "➡ Run #$RUN — suivi en direct :"
gh run watch "$RUN" --exit-status

echo "✅ Déployé : thème + overrides + modules, cache vidé."
