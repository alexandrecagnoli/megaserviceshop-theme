#!/bin/bash
#
# Sauvegarde de la base Megaservice — dump compressé + rotation.
# ==============================================
#
# Au 25/09/2026, la préprod n'avait que trois dumps : 29/04 (2,6 Mo décompressés,
# inexploitable), 25/08 et 03/09. Les répertoires de sauvegarde de PrestaShop
# (admin…/backups, autoupgrade/backup) étaient vides. Trois semaines de travail
# en base — réparation de l'arbre des catégories, index de prix reconstruit,
# import des relations — n'étaient couvertes par rien.
#
# Les identifiants sont lus dans app/config/parameters.php : rien n'est écrit en
# dur ici, et le script suit un éventuel changement de mot de passe.
#
# Installation (scripts/ n'est PAS déployé par la CI) :
#   scp -i ~/.ssh/drpetrea_preprod scripts/cli/backup_db.sh \
#       nuttyguru.com_j8g81qmwucn@93.90.201.182:~/backup_db.sh
#   ssh … "chmod +x ~/backup_db.sh"
#
# Cron (tous les jours à 3 h 30) :
#   30 3 * * * /var/www/vhosts/nuttyguru.com/backup_db.sh >> /var/www/vhosts/nuttyguru.com/backups_db/cron.log 2>&1

set -euo pipefail

PS_ROOT="/var/www/vhosts/nuttyguru.com/megaserviceshop.nuttyguru.com"
# Hors du document root : un dump servi en HTTP, c'est toute la base publiée.
DEST="/var/www/vhosts/nuttyguru.com/backups_db"
KEEP=14
PHP="/opt/plesk/php/8.2/bin/php"

mkdir -p "$DEST"
chmod 700 "$DEST"

# Les identifiants sortent de la config PrestaShop, jamais du script.
eval "$("$PHP" -r '
$p = require "'"$PS_ROOT"'/app/config/parameters.php";
$d = $p["parameters"];
printf("DB_HOST=%s\nDB_NAME=%s\nDB_USER=%s\nDB_PASS=%s\n",
    escapeshellarg($d["database_host"]),
    escapeshellarg($d["database_name"]),
    escapeshellarg($d["database_user"]),
    escapeshellarg($d["database_password"]));
')"

STAMP="$(date +%Y-%m-%d_%H-%M-%S)"
FILE="$DEST/mss_ps8_${STAMP}.sql.gz"

echo "[$(date '+%F %T')] début — base $DB_NAME"

# --single-transaction : dump cohérent sans verrouiller le site (InnoDB).
# --quick : ligne à ligne, pour ne pas charger 2,3 M de lignes en mémoire.
# Le mot de passe passe par l'environnement, pas par la ligne de commande, pour
# qu'il n'apparaisse pas dans `ps`.
if ! MYSQL_PWD="$DB_PASS" mysqldump \
        --host="$DB_HOST" --user="$DB_USER" \
        --single-transaction --quick --default-character-set=utf8mb4 \
        --no-tablespaces \
        "$DB_NAME" 2>"$DEST/.last_error" | gzip -6 > "$FILE.part"; then
    echo "[$(date '+%F %T')] ÉCHEC du dump :"
    sed 's/^/    /' "$DEST/.last_error"
    rm -f "$FILE.part"
    exit 1
fi

# Renommage seulement en cas de succès : un .part qui traîne signale un dump
# interrompu, et n'est jamais pris pour une sauvegarde valide.
mv "$FILE.part" "$FILE"
chmod 600 "$FILE"

SIZE="$(du -h "$FILE" | cut -f1)"
echo "[$(date '+%F %T')] OK — $FILE ($SIZE)"

# Rotation : on ne garde que les KEEP plus récents.
mapfile -t OLD < <(ls -1t "$DEST"/mss_ps8_*.sql.gz 2>/dev/null | tail -n +$((KEEP + 1)))
if [ "${#OLD[@]}" -gt 0 ]; then
    printf '%s\n' "${OLD[@]}" | xargs -r rm -f
    echo "[$(date '+%F %T')] rotation — ${#OLD[@]} ancien(s) dump(s) supprimé(s)"
fi

echo "[$(date '+%F %T')] $(ls -1 "$DEST"/mss_ps8_*.sql.gz 2>/dev/null | wc -l) dump(s) conservé(s), $(du -sh "$DEST" | cut -f1) au total"
