# Arbre « Accessoires Powerparts » — état au 23/09/2026

Relevé sur la préprod **après** réparation de l'arbre des catégories
(cf. `data/sql/2026-09-23_reparation_arbre_categories.sql`).

## À quoi sert ce document

Trancher, catégorie par catégorie, ce qui relève d'**Accessoires Powerparts** et ce qui
relève de **Pièces détachées d'origine**. La colonne de droite est le discriminant.

## Comment le lire

Pour chaque catégorie : le nombre de produits qui lui sont **directement** rattachés, puis la
part de ces produits qui sont **aussi** rattachés à Pièces détachées (catégories 39 et 40).

- **0 %** → accessoire Powerparts authentique
- **65 à 96 %** → pièce détachée qui déborde dans Powerparts

## Le constat en un chiffre

Sur les **36 791** produits du sous-arbre Powerparts, **31 660 sont aussi en pièces détachées**.
Il reste donc environ **5 100 accessoires Powerparts réels** — à rapprocher des **4 687**
rattachés directement à la catégorie 41 que montre le back-office.

**Le problème n'est pas l'arborescence, ce sont les rattachements produit-catégorie.**
Les catégories Powerparts sont légitimes ; des pièces détachées y sont associées en plus.

## Anomalies à traiter au passage

- **76 catégories vides nommées « électronique »** sous Guidon & commandes > Instruments
- **Doublon « Guidon »** : ids 74 (1 241 produits) et 247 (vide), sous le même parent

---

```
ARBRE SOUS « ACCESSOIRES POWERPARTS » (41)
------------------------------------------------------------------------------------------------
57     Bagagerie                             100 produits  dont   0% aussi en pieces detachees
371    Divers                                 30 produits  dont   0% aussi en pieces detachees
397        Documentation                           1 produits  dont   0% aussi en pieces detachees
250    Échappement                         1697 produits  dont  65% aussi en pieces detachees
409        Autocollant                             8 produits  dont   0% aussi en pieces detachees
241        Commande d'échappement               304 produits  dont  96% aussi en pieces detachees
410        Protection thermique                   15 produits  dont   0% aussi en pieces detachees
375    Équipement & confort                 428 produits  dont   0% aussi en pieces detachees
320        Équipement                           662 produits  dont   0% aussi en pieces detachees
257    Freinage                             1819 produits  dont  90% aussi en pieces detachees
378        Disques de frein                       12 produits  dont   0% aussi en pieces detachees
432        Étriers de frein                       2 produits  dont   0% aussi en pieces detachees
374    Guidon & commandes                    472 produits  dont   0% aussi en pieces detachees
243        Allumage                              598 produits  dont  92% aussi en pieces detachees
411        Amortisseurs de direction              10 produits  dont   0% aussi en pieces detachees
258        Éclairage                           1509 produits  dont  93% aussi en pieces detachees
256        Faisceau électrique                 1980 produits  dont  96% aussi en pieces detachees
405        Fixations du guidon                    22 produits  dont   0% aussi en pieces detachees
74         Guidon                               1241 produits  dont  76% aussi en pieces detachees
247        Guidon                                  0 produits  dont   0% aussi en pieces detachees
385        Instruments                             0 produits  dont   0% aussi en pieces detachees
423            électronique                           0 produits  dont   0% aussi en pieces detachees
457            électronique                           0 produits  dont   0% aussi en pieces detachees
424            électronique                           0 produits  dont   0% aussi en pieces detachees
458            électronique                           0 produits  dont   0% aussi en pieces detachees
425            électronique                           0 produits  dont   0% aussi en pieces detachees
459            électronique                           0 produits  dont   0% aussi en pieces detachees
426            électronique                           0 produits  dont   0% aussi en pieces detachees
460            électronique                           0 produits  dont   0% aussi en pieces detachees
427            électronique                           0 produits  dont   0% aussi en pieces detachees
461            électronique                           0 produits  dont   0% aussi en pieces detachees
428            électronique                           0 produits  dont   0% aussi en pieces detachees
462            électronique                           0 produits  dont   0% aussi en pieces detachees
429            électronique                           0 produits  dont   0% aussi en pieces detachees
463            électronique                           0 produits  dont   0% aussi en pieces detachees
430            électronique                           0 produits  dont   0% aussi en pieces detachees
464            électronique                           0 produits  dont   0% aussi en pieces detachees
431            électronique                           0 produits  dont   0% aussi en pieces detachees
465            électronique                           0 produits  dont   0% aussi en pieces detachees
433            électronique                           0 produits  dont   0% aussi en pieces detachees
466            électronique                           0 produits  dont   0% aussi en pieces detachees
434            électronique                           0 produits  dont   0% aussi en pieces detachees
467            électronique                           0 produits  dont   0% aussi en pieces detachees
435            électronique                           0 produits  dont   0% aussi en pieces detachees
468            électronique                           0 produits  dont   0% aussi en pieces detachees
436            électronique                           0 produits  dont   0% aussi en pieces detachees
469            électronique                           0 produits  dont   0% aussi en pieces detachees
437            électronique                           0 produits  dont   0% aussi en pieces detachees
470            électronique                           0 produits  dont   0% aussi en pieces detachees
438            électronique                           0 produits  dont   0% aussi en pieces detachees
471            électronique                           0 produits  dont   0% aussi en pieces detachees
439            électronique                           0 produits  dont   0% aussi en pieces detachees
472            électronique                           0 produits  dont   0% aussi en pieces detachees
440            électronique                           0 produits  dont   0% aussi en pieces detachees
473            électronique                           0 produits  dont   0% aussi en pieces detachees
441            électronique                           0 produits  dont   0% aussi en pieces detachees
474            électronique                           0 produits  dont   0% aussi en pieces detachees
442            électronique                           0 produits  dont   0% aussi en pieces detachees
475            électronique                           0 produits  dont   0% aussi en pieces detachees
386            électronique                           0 produits  dont   0% aussi en pieces detachees
443            électronique                           0 produits  dont   0% aussi en pieces detachees
476            électronique                           0 produits  dont   0% aussi en pieces detachees
387            électronique                           0 produits  dont   0% aussi en pieces detachees
444            électronique                           0 produits  dont   0% aussi en pieces detachees
477            électronique                           0 produits  dont   0% aussi en pieces detachees
394            électronique                           0 produits  dont   0% aussi en pieces detachees
445            électronique                           0 produits  dont   0% aussi en pieces detachees
478            électronique                           0 produits  dont   0% aussi en pieces detachees
398            électronique                           0 produits  dont   0% aussi en pieces detachees
447            électronique                           0 produits  dont   0% aussi en pieces detachees
479            électronique                           0 produits  dont   0% aussi en pieces detachees
399            électronique                           0 produits  dont   0% aussi en pieces detachees
448            électronique                           0 produits  dont   0% aussi en pieces detachees
480            électronique                           0 produits  dont   0% aussi en pieces detachees
403            électronique                           0 produits  dont   0% aussi en pieces detachees
449            électronique                           0 produits  dont   0% aussi en pieces detachees
481            électronique                           0 produits  dont   0% aussi en pieces detachees
414            électronique                           0 produits  dont   0% aussi en pieces detachees
450            électronique                           0 produits  dont   0% aussi en pieces detachees
482            électronique                           0 produits  dont   0% aussi en pieces detachees
415            électronique                           0 produits  dont   0% aussi en pieces detachees
451            électronique                           0 produits  dont   0% aussi en pieces detachees
483            électronique                           0 produits  dont   0% aussi en pieces detachees
416            électronique                           0 produits  dont   0% aussi en pieces detachees
452            électronique                           0 produits  dont   0% aussi en pieces detachees
484            électronique                           0 produits  dont   0% aussi en pieces detachees
418            électronique                           0 produits  dont   0% aussi en pieces detachees
453            électronique                           0 produits  dont   0% aussi en pieces detachees
485            électronique                           0 produits  dont   0% aussi en pieces detachees
419            électronique                           0 produits  dont   0% aussi en pieces detachees
454            électronique                           0 produits  dont   0% aussi en pieces detachees
486            électronique                           0 produits  dont   0% aussi en pieces detachees
420            électronique                           0 produits  dont   0% aussi en pieces detachees
455            électronique                           0 produits  dont   0% aussi en pieces detachees
487            électronique                           0 produits  dont   0% aussi en pieces detachees
422            électronique                           0 produits  dont   0% aussi en pieces detachees
456            électronique                           0 produits  dont   0% aussi en pieces detachees
421        Leviers                                25 produits  dont   0% aussi en pieces detachees
413        Protège-main                          66 produits  dont   0% aussi en pieces detachees
376    Habillage & carénage                 865 produits  dont   0% aussi en pieces detachees
253        Carénage                            6088 produits  dont  88% aussi en pieces detachees
377        Pièces de carénage                  221 produits  dont   0% aussi en pieces detachees
252        Réservoir et Selle                  2115 produits  dont  86% aussi en pieces detachees
369    Logiciels                             153 produits  dont   0% aussi en pieces detachees
388    Moteur                                293 produits  dont   0% aussi en pieces detachees
357        Carburateur                             5 produits  dont   0% aussi en pieces detachees
235        Carter moteur                        2661 produits  dont  97% aussi en pieces detachees
240        Culasse et Distribution              1445 produits  dont  99% aussi en pieces detachees
236        Embrayage                             813 produits  dont  93% aussi en pieces detachees
251        Filtre à air                        1238 produits  dont  86% aussi en pieces detachees
259        Filtre à charbon actif               402 produits  dont  99% aussi en pieces detachees
242        Lubrification                         582 produits  dont  91% aussi en pieces detachees
393        Pièces moteur à quatre temps         10 produits  dont   0% aussi en pieces detachees
406        Protection                             21 produits  dont   0% aussi en pieces detachees
239        Refroidissement                      1149 produits  dont  94% aussi en pieces detachees
408        Tuyaux                                  1 produits  dont   0% aussi en pieces detachees
407        Ventilateur de refroidissement          9 produits  dont   0% aussi en pieces detachees
379    Outillage & transport                 162 produits  dont   0% aussi en pieces detachees
275        Outillage                             173 produits  dont   0% aussi en pieces detachees
404        Transport                              19 produits  dont   0% aussi en pieces detachees
380    Partie cycle                          543 produits  dont   0% aussi en pieces detachees
392        Béquille                              23 produits  dont   0% aussi en pieces detachees
248        Cadre                                2706 produits  dont  90% aussi en pieces detachees
384        Élément de suspension                67 produits  dont   0% aussi en pieces detachees
381        Partie-cycle street                    74 produits  dont   0% aussi en pieces detachees
401        Protection moteur 2 temps Offroa       29 produits  dont   0% aussi en pieces detachees
412        Protection moteur 4 temps Offroa       26 produits  dont   0% aussi en pieces detachees
417        Protection moteur Street               29 produits  dont   0% aussi en pieces detachees
249        Suspension arrière                  2231 produits  dont  97% aussi en pieces detachees
373    Roues & transmission                  350 produits  dont   0% aussi en pieces detachees
237        Boîte de vitesses                   1430 produits  dont  99% aussi en pieces detachees
400        Couronnes                              82 produits  dont   0% aussi en pieces detachees
255        Roue arrière                        1651 produits  dont  84% aussi en pieces detachees
254        Roue avant                            892 produits  dont  81% aussi en pieces detachees
238        Sélecteur de vitesses                707 produits  dont  98% aussi en pieces detachees
332    Suspensions WP                        447 produits  dont   0% aussi en pieces detachees
382        Pièces détachées WP                388 produits  dont   0% aussi en pieces detachees
356            Amortisseur                           229 produits  dont   0% aussi en pieces detachees
383        Produits WP                            30 produits  dont   0% aussi en pieces detachees
364            Direction                               1 produits  dont   0% aussi en pieces detachees
280            Fourche                               393 produits  dont   0% aussi en pieces detachees
391    Visserie et fixations                  39 produits  dont   0% aussi en pieces detachees
```
