# samples — extraits CSV pour tests

Échantillons versionnés des CSV constructeur (motos + microfiches) pour
valider le parsing, le mapping et alimenter les tests unitaires.

## CSV motos (3 fichiers)

~30 lignes chacun (1ʳᵉ occurrence par `MODELNUMBER` unique). Sert à :

- valider le mapping taxonomie (`type`, `cylindree`, `is_electric`, `core_name`),
- alimenter les tests unitaires de `MotosImporter`,
- itérer sur le parsing (détection encodage, délimiteur, normalisation header).

| Fichier                          | Source                          | Encodage           |
|----------------------------------|---------------------------------|--------------------|
| `sample_KTM_MOTORCYCLES.csv`     | `data/imports/KTM_MOTORCYCLES.csv`    | **ISO-8859-1**     |
| `sample_HQV_MOTORCYCLES.csv`     | `data/imports/HQV_MOTORCYCLES.csv`    | UTF-8 / ASCII      |
| `sample_GASGAS_MOTORCYCLES.csv`  | `data/imports/GASGAS_MOTORCYCLES.csv` | UTF-8 / ASCII      |

L'encodage **n'a pas été normalisé** volontairement : on veut que l'importer
détecte et convertisse en UTF-8 sur le sample KTM (vrai cas de prod).

### Régénération motos

```bash
cd data/imports
for f in KTM_MOTORCYCLES.csv HQV_MOTORCYCLES.csv GASGAS_MOTORCYCLES.csv; do
  dest=../../modules/megaservice_microfiches/samples/sample_$f
  head -1 "$f" > "$dest"
  awk -F';' 'NR>1 && $1 ~ /^\$M-/ && !seen[$1]++ {print; if (++c >= 30) exit}' "$f" >> "$dest"
done
```

## CSV microfiches (1 fichier par moto)

100 premières lignes du CSV complet, suffisant pour couvrir les deux types
(`engine` + `frame`) et plusieurs catégories. Sert à tester :

- résolution moto pivot (`model` du CSV → `serial_constructeur` de `ms_moto`),
- mapping catégorie (`vue_eclatee_type` engine→moteur / frame→cycle),
- auto-création catégorie si absente (`code=TODO_<partie>_<num>`, brief §6.1.E),
- idempotence (clé naturelle microfiche : id_moto+id_categorie+nom_constructeur).

| Fichier                  | Moto pivot                      | Encodage      |
|--------------------------|---------------------------------|---------------|
| `sample_F0403X7.csv`     | GASGAS EC 300 2024              | UTF-8 / ASCII |

### Régénération microfiches

```bash
head -100 data/imports/F0403X7.csv > modules/megaservice_microfiches/samples/sample_F0403X7.csv
```

## Pollution observée

Les CSV motos constructeur contiennent du **bruit HTML** en début de fichier
(fragments `<td style=...>`, `<tr ...>`, `</tr>`) avant les vrais rows.
L'importer motos filtre sur `^\$M-` pour ne garder que les vrais `MODELNUMBER`.
Voir `TECH_DEBT.md`. Les CSV microfiches sont propres (pas de pollution observée).
