# Protocole de traitement des tickets de recette — Claude Code (MEGASERVICESHOP)

Source unique : base Notion **Tickets de recette** (page 🧪 Recette site), filtrée sur `Statut = À traiter`.

## Règle n°1 — Un ticket à la fois, jamais en lot

Ne jamais ouvrir ou corriger plusieurs tickets en parallèle dans la même session de travail. Traiter un ticket jusqu'à son terme (ou jusqu'à blocage identifié) avant de passer au suivant.

**Ordre de traitement** : trier les tickets `À traiter` par Sévérité (Bloquant > Majeur > Mineur > Cosmétique), puis par date de création la plus ancienne à égalité de sévérité.

## Règle n°2 — Vérifier le périmètre AVANT de commencer

Un ticket n'est traité que s'il correspond à un **bug de comportement du site** (code, import, affichage, règle déjà actée mal implémentée) sur le périmètre technique de Claude Code (PrestaShop, modules `megaservice_*`, imports, front).

**Hors périmètre — ne jamais agir, signaler et laisser le ticket en l'état :**
- Une demande qui implique une décision métier non actée (règle de dispo, politique de précommande, structure de taxonomie/arborescence, terminologie affichée) — même si la correction technique semble triviale, si la *règle* elle-même n'a pas été validée en COPROJ / Journal des décisions, ce n'est pas à Claude Code de trancher.
- Une demande de changement visuel/design non cadré (maquette, charte).
- Une donnée métier manquante ou à fournir par le client (Excel d'arborescence, référence de kit, export montabilité, etc.) — Claude Code ne doit pas improviser une donnée qu'il n'a pas.

Si un ticket est hors périmètre : **ne pas coder**, ajouter un commentaire sur le ticket expliquant pourquoi (quelle décision manque, ou vers qui rediriger), et laisser le Statut à `À traiter` (ou proposer qu'Alex requalifie le ticket).

## Règle n°3 — Présenter le ticket et attendre le feu vert AVANT de commencer

Une fois le périmètre vérifié (Règle n°2), ne pas attaquer directement la correction. Avant de commencer CHAQUE ticket, présenter à Alex :
1. le ticket (titre + sévérité + ce qui est compté faire concrètement),
2. les fichiers/pages/modules qui vont être touchés,
3. les informations qui manquent, s'il y en a.

Puis attendre le feu vert explicite d'Alex avant d'écrire la moindre ligne de code. Un seul ticket présenté à la fois — ne pas enchaîner la présentation de plusieurs tickets avant d'avoir eu une réponse.

Si Alex ne répond pas dans l'immédiat (session non surveillée) : ne pas avancer sur ce ticket, passer au ticket suivant du même périmètre si son feu vert à lui aussi est en attente, et signaler clairement en fin de session la liste des tickets en attente de feu vert.

## Règle n°4 — Ne jamais avancer sur une incertitude

Si une information nécessaire à la correction manque ou est ambiguë (étapes de reproduction, comportement attendu précis, référence produit concernée, quelle page/URL, quel environnement, quelle version des données), **Claude Code s'arrête et pose la question** avant d'écrire la moindre ligne de code — jamais d'hypothèse silencieuse, même « raisonnable ».

Formulation à utiliser (dans un commentaire sur le ticket Notion, ou dans la réponse à Alex) :
> **Bloqué — information manquante pour le ticket [Titre]** : <question précise>. Je ne commence pas la correction tant que ce point n'est pas clarifié, pour éviter de corriger le mauvais problème.

Ne jamais deviner une valeur (une référence produit, une date, une règle de priorité) à partir d'un cas voisin sans confirmation explicite.

## Règle n°5 — Workflow de statut, jamais de raccourci

`À traiter → En cours → À vérifier → Résolu → Fermé`

- Passer `En cours` dès le feu vert reçu (Règle n°3) et le début du traitement effectif du ticket.
- Une fois la correction faite et testée en local par Claude Code : passer à **`À vérifier`** — jamais directement à `Résolu`. Seul le testeur qui a ouvert le ticket peut valider un `Résolu` après re-test sur la préprod.
- Ajouter en commentaire du ticket : ce qui a été identifié comme cause, ce qui a été modifié (fichier(s)/module concerné), et comment le re-tester.
- Si le ticket s'avère non reproductible : ne pas le fermer soi-même, le documenter en commentaire (« non reproduit, voir détails ») et laisser le statut à `À vérifier` pour qu'Alex/le testeur tranche, ou proposer `Refusé / Non retenu` en expliquant pourquoi sans le trancher seul si le motif touche une décision produit.

## Règle n°6 — Traçabilité

Chaque ticket traité (corrigé, bloqué, ou jugé hors périmètre) doit porter une trace écrite du raisonnement — pas seulement un changement de statut silencieux. Ça permet à Alex de reprendre la main sans devoir redemander le contexte.

## Récap final de session

À la fin de chaque session de traitement de tickets, fournir à Alex : les tickets traités (avec leur nouveau statut), ceux laissés de côté avec la raison (hors périmètre, décision manquante), et ceux en attente d'un feu vert ou d'une info de sa part.

---

## Gabarit pour lancer un ticket auprès de Claude Code

```
Ticket Notion : [lien ou Ticket ID]
Titre : [titre]
Type / Zone / Sévérité : [valeurs]
Détails : [copier le champ Détails]
Lien page préprod : [URL]
Captures : [décrire ou joindre]

Traite ce ticket seul, dans le périmètre technique défini (protocole tickets recette).
Si une info manque ou qu'une règle métier n'est pas actée, arrête-toi et demande — ne devine rien.
Avant de coder, présente le plan (fichiers touchés) et attends mon feu vert.
Une fois corrigé, passe le ticket en "À vérifier" avec un résumé de la correction, jamais en "Résolu".
```
