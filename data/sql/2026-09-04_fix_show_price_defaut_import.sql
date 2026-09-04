-- ============================================================================
-- Correction show_price=0 par defaut a l'import — a executer UNE FOIS en
-- phpMyAdmin, APRES un dump de la base.
--
-- Contexte : cf. docs/SPEC_disponibilite_stock.md §8-10. show_price est une
-- colonne persistante (pas un calcul dynamique) qui masque prix ET bouton
-- d'achat quand a 0, independamment de available_for_order/out_of_stock.
-- Verifie le 04/09/2026 : 47 915/47 916 produits a 0, aucun mapping d'import
-- ne pilote ce champ -> c'est la valeur par defaut a la creation, pas un
-- choix intentionnel.
--
-- Ciblage : uniquement les produits deja juges vendables par la regle de
-- disponibilite existante (available_for_order=1). Ne touche PAS aux
-- produits available_for_order=0 (§3 cas 3/4 du brief, non commandables,
-- prix reste legitimement masque pour eux).
-- ============================================================================

-- ── 0. Etat des lieux — a lancer seul, avant tout ───────────────────────────
SELECT COUNT(*) AS produits_a_corriger
FROM ps_product
WHERE available_for_order = 1 AND show_price = 0;

-- ── 1. Correction ────────────────────────────────────────────────────────
UPDATE ps_product
SET show_price = 1
WHERE available_for_order = 1 AND show_price = 0;

UPDATE ps_product_shop
SET show_price = 1
WHERE available_for_order = 1 AND show_price = 0;

-- ── 2. Verification — doit renvoyer 0 ────────────────────────────────────
SELECT COUNT(*) AS restants
FROM ps_product
WHERE available_for_order = 1 AND show_price = 0;
