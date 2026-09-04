# Scripts de diagnostic ponctuel

Scripts CLI utilitaires, hors périmètre d'un module précis — bootstrap PrestaShop complet, à lancer en SSH sur le serveur. Jamais routés en HTTP, aucun risque de fuite en front.

Usage générique :

```
php scripts/cli/<script>.php [arguments]
```

Le bootstrap remonte l'arborescence depuis son propre emplacement jusqu'à trouver `config/config.inc.php` — fonctionne peu importe où le script est déposé sur le serveur (racine du site ou sous-dossier), tant qu'il reste sous la racine PrestaShop.
