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

## Microfiches (1 fichier par moto)

Format : 1 ligne = 1 hotspot (point cliquable). Les microfiches sont implicites :
on les déduit en groupant sur `(vue_eclatee_type, vue_eclatee_number, vue_eclatee)`.

| Fichier exemple    | Moto pivot              | Encodage      | Ordre de grandeur          |
|--------------------|-------------------------|---------------|----------------------------|
| `F0403X7.csv`      | GASGAS EC 300 2024      | UTF-8 / ASCII | ~424 Ko / 1466 hotspots / 45 microfiches / 25 catégories |

Le nom du fichier = la référence constructeur de la moto (= `serial_constructeur`
sur `ms_moto`). L'importer résout le pivot moto via ce serial. Si la moto n'est
pas encore en BDD (CSV motos pas encore importé), tous les hotspots sont skippés
avec un compteur explicite dans le rapport.

Format colonnes : voir brief §4.3.

## Samples (pour dev / tests)

Versionnés dans `modules/megaservice_microfiches/samples/` :
- **motos** : extrait ~30 lignes par marque (1ère occurrence par `MODELNUMBER`),
- **microfiches** : 100 premières lignes du CSV complet (couvre engine + frame).

## Workflow d'import

1. Importer **les motos d'abord** (KTM/HQV/GASGAS CSV) → remplit `ps_ms_moto`
   avec les `serial_constructeur`.
2. Importer ensuite **les microfiches** (1 CSV par moto, ex. F0403X7.csv) → l'importer
   résout le pivot via le nom de fichier et remplit `ps_ms_microfiche` +
   `ps_ms_microfiche_categorie` + `ps_ms_microfiche_hotspot`.
3. Vérifier le rapport d'import dans le BO (compteurs + erreurs + 'Autres').
