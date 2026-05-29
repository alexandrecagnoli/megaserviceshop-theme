# data/imports — CSV sources constructeur

Ce dossier est destiné à recevoir les **gros CSV constructeur** utilisés par les
importers du module `megaservice_microfiches`. Les `.csv` qui y sont déposés
sont **gitignorés** (volumineux, données fournisseur, non versionnables).

## Motos

| Fichier attendu          | Marque  | Encodage  | Ordre de grandeur |
|--------------------------|---------|-----------|-------------------|
| `KTM_MOTORCYCLES.csv`    | KTM     | ISO-8859-1 (Latin-1) | ~32 Mo / ~144k lignes / ~1224 modèles |
| `HQV_MOTORCYCLES.csv`    | HQV     | UTF-8 / ASCII        | ~6.5 Mo / ~24k lignes / ~435 modèles  |
| `GASGAS_MOTORCYCLES.csv` | GASGAS  | UTF-8 / ASCII        | ~2 Mo / ~7k lignes / ~189 modèles     |

Format colonnes (commun aux 3) :

```
MODELNUMBER;annee;article number;active/inactive;picture;Struktur DE;...;structure FR;Category FR;model name (FR);text (FR);...
```

Variantes du nom de colonne année selon source : `anne` (KTM, accent perdu en
ISO-8859-1), `annee` (HQV), `ANNEE` (GASGAS) — l'importer normalise.

## Samples (pour dev / tests)

Un extrait de ~30 lignes par marque (1ère occurrence par `MODELNUMBER` unique)
est versionné dans `modules/megaservice_microfiches/samples/`. Il sert pour les
tests unitaires et la validation du mapping taxonomie — pas pour la prod.

## Workflow d'import

1. Déposer les CSV constructeur dans ce dossier.
2. Lancer l'import (CLI ou BO — voir `modules/megaservice_microfiches/README.md`).
3. Vérifier les logs / le rapport d'import.
