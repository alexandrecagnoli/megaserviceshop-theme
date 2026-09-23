#!/usr/bin/env bash
#
# OBSOLÈTE — ne plus utiliser. Conservé pour intercepter les habitudes.
#
# Ce script faisait un rsync direct de megaservice/assets/dist/ vers la préprod.
# Il était cassé de deux façons :
#
#   1. Il ne synchronisait que `assets/dist/`, alors que le bundle JS du thème
#      est généré dans `assets/js/theme.js`. Le JS n'était donc jamais déployé —
#      seul le CSS partait, malgré le nom « CSS only » laissant croire au reste.
#
#   2. Il s'appuyait sur une clé SSH absente des postes de dev (elle vit dans les
#      secrets GitHub), donc il échouait en « Permission denied (publickey) ».
#
# Un déploiement partiel du thème est par ailleurs risqué : le CSS et le JS
# doivent rester cohérents avec les templates, qui ne partaient pas non plus.
#
# Utiliser `bash deploy.sh`, qui délègue à la CI : thème + overrides + modules,
# build inclus, cache vidé.

echo "✗ deploy-css.sh est obsolète et ne déploie plus rien." >&2
echo "" >&2
echo "  Il ne poussait que assets/dist/, sans le bundle JS (assets/js/theme.js)" >&2
echo "  ni les templates — et la clé SSH qu'il utilisait n'est pas sur ce poste." >&2
echo "" >&2
echo "  Utilise :  bash deploy.sh" >&2
exit 1
