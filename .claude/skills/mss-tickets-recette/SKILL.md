---
name: mss-tickets-recette
description: Traiter les tickets de la base Notion « Tickets de recette » de Megaserviceshop un par un, avec présentation et feu vert d'Alex avant chaque correction.
---

# Mission : traiter les tickets de recette Megaserviceshop (périmètre technique Claude Code uniquement)

Tu travailles sur le site PrestaShop de Megaserviceshop (MSS). Le suivi des bugs remontés par le client en recette est la base Notion **Tickets de recette** (page 🧪 Recette site, data source `collection://25d7058c-d89f-40d3-bda3-0c420e5879fa`). Ta mission : traiter, comme des tickets, tous ceux qui relèvent de TON périmètre technique — et seulement ceux-là — en suivant le protocole complet documenté dans le doc projet `claude/protocole-tickets-recette-claude-code.md` (à lire intégralement avant de commencer).

## 1. Récupérer les tickets
- Interroge la base Notion « Tickets de recette ».
- Liste tous les tickets dont Statut = « À traiter ».
- Trie-les par Sévérité (Bloquant > Majeur > Mineur > Cosmétique), puis par date de création la plus ancienne à égalité de sévérité (Règle n°1 du protocole).
- Lis le contenu complet de chaque ticket (Type / Zone / Sévérité / Détails / Lien page préprod / Captures), pas juste le titre.

## 2. Filtrer sur TON périmètre
Tu ne traites QUE ce qui est un bug de comportement du site sur le périmètre technique de Claude Code : PrestaShop, modules `megaservice_*`, imports, front, affichage.

EXCLUS de ton périmètre, à NE PAS coder — signale-les juste dans ton récap final :
- toute demande qui implique une décision métier non actée en COPROJ / Journal des décisions (règle de dispo, politique de précommande, taxonomie, terminologie affichée) ;
- tout changement visuel/design non cadré (maquette, charte) ;
- toute donnée métier manquante ou à fournir par le client (Excel d'arborescence, référence de kit, export montabilité, etc.) — ne jamais improviser une donnée absente ;
- les tickets déjà marqués « Refusé / Non retenu ».

Si un ticket est hors périmètre : ne pas coder, commenter sur le ticket pourquoi (quelle décision manque, ou vers qui rediriger), laisser le Statut à « À traiter ».

## 3. Règle absolue : présenter et attendre le feu vert AVANT chaque ticket
Avant de commencer CHAQUE ticket (et un seul à la fois), présente à Alex :
1. le ticket (titre + sévérité + ce que tu comptes faire concrètement) ;
2. les fichiers/pages/modules que tu vas toucher ;
3. les informations qui te manquent, s'il y en a.

Puis attends son feu vert explicite avant d'écrire la moindre ligne de code. Si une info manque, demande-la et n'attaque pas tant qu'Alex n'a pas répondu — ne devine jamais une valeur à partir d'un cas voisin. Si la session n'est pas surveillée et qu'aucune réponse n'arrive, passe au ticket suivant du même périmètre dont le feu vert est aussi en attente, et signale en fin de session la liste des tickets en attente de feu vert.

## 4. Respecter les décisions actées du projet
Avant de corriger, vérifie les décisions déjà actées (Journal des décisions Notion, CDC, `suivi-projet.md`) pertinentes au ticket, pour ne pas réinventer une règle déjà tranchée ou en contredire une.

## 5. Workflow de statut, jamais de raccourci
`À traiter → En cours → À vérifier → Résolu → Fermé`
- Passe le ticket en « En cours » dès le feu vert reçu.
- Une fois corrigé et testé en local : passe à « À vérifier » — jamais directement à « Résolu ». Seul le testeur qui a ouvert le ticket valide un « Résolu » après re-test sur la préprod.
- Commente sur le ticket : cause identifiée, ce qui a été modifié (fichier/module), comment re-tester.
- Si non reproductible : ne pas fermer soi-même, documenter en commentaire et laisser à « À vérifier » pour qu'Alex tranche.
- Ne notifie personne d'autre que Alex. Ne modifie aucun ticket hors de ton périmètre.

## 6. Récap final
À la fin de la session, fournis : les tickets traités (avec leur nouveau statut), ceux laissés de côté avec la raison (hors périmètre / décision manquante), et ceux en attente d'un feu vert ou d'une info d'Alex.

Commence toujours par présenter la liste filtrée des tickets de ton périmètre, classés par priorité, avant d'attaquer le premier.
