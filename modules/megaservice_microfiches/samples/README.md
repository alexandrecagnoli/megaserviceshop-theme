# samples — extraits CSV pour tests

Échantillons des 3 CSV motos constructeur, ~30 lignes chacun (1ʳᵉ occurrence
par `MODELNUMBER` unique). Sert à :

- valider le mapping taxonomie (`type`, `cylindree`, `is_electric`, `core_name`),
- alimenter les tests unitaires de `MsMotosImporter`,
- itérer sur le parsing (détection encodage, délimiteur, normalisation header).

| Fichier                          | Source                          | Encodage           |
|----------------------------------|---------------------------------|--------------------|
| `sample_KTM_MOTORCYCLES.csv`     | `data/imports/KTM_MOTORCYCLES.csv`    | **ISO-8859-1**     |
| `sample_HQV_MOTORCYCLES.csv`     | `data/imports/HQV_MOTORCYCLES.csv`    | UTF-8 / ASCII      |
| `sample_GASGAS_MOTORCYCLES.csv`  | `data/imports/GASGAS_MOTORCYCLES.csv` | UTF-8 / ASCII      |

L'encodage **n'a pas été normalisé** volontairement : on veut que l'importer
détecte et convertisse en UTF-8 sur le sample KTM (vrai cas de prod).

## Régénération

```bash
cd data/imports
for f in KTM_MOTORCYCLES.csv HQV_MOTORCYCLES.csv GASGAS_MOTORCYCLES.csv; do
  dest=../../modules/megaservice_microfiches/samples/sample_$f
  head -1 "$f" > "$dest"
  awk -F';' 'NR>1 && $1 ~ /^\$M-/ && !seen[$1]++ {print; if (++c >= 30) exit}' "$f" >> "$dest"
done
```

## Pollution observée

Les CSV constructeur contiennent du **bruit HTML** en début de fichier
(fragments `<td style=...>`, `<tr ...>`, `</tr>`) avant les vrais rows.
L'importer filtre sur `^\$M-` pour ne garder que les vrais `MODELNUMBER`.
Voir `TECH_DEBT.md`.
