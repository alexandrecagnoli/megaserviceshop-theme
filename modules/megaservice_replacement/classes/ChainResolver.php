<?php
/**
 * Megaservice — Résolution transitive des chaînes de remplacement.
 *
 * Une référence remplacée peut pointer vers une référence elle-même remplacée
 * (A→B→C). On précalcule la référence FINALE à l'import : zéro coût en front,
 * comportement déterministe, chaînes auditables (cf. SPEC §3.2).
 *
 * Logique VOLONTAIREMENT pure (aucune dépendance PrestaShop / base) pour être
 * testable en CLI : cf. tests/cli/test_chain_resolver.php.
 *
 * Règles :
 *   - chaîne qui aboutit à une réf non remplacée   → chain_status = ok
 *   - cycle détecté OU profondeur > MAX_DEPTH      → chain_status = loop,
 *     ref_final = dernière référence saine
 *   - maillon de type `set` rencontré              → on s'arrête AVANT d'entrer
 *     (on ne compose pas des sets de sets) : ref_final = la tête du set,
 *     final_is_set = true, chain_status = ok
 *   - référence remplaçante vide                   → chain_status = dead_end
 */

class MsReplacementChainResolver
{
    /** Profondeur maximale de chaîne avant de considérer une boucle (SPEC §3.2). */
    const MAX_DEPTH = 10;

    /**
     * Enrichit chaque ligne de sa résolution.
     *
     * @param array<int,array<string,mixed>> $rows lignes normalisées :
     *        ref_replaced, ref_replacement, conversion_type ('replace'|'set')
     * @return array<int,array<string,mixed>> mêmes lignes + ref_final,
     *         final_is_set, chain_depth, chain_status
     */
    public static function resolve(array $rows)
    {
        $index = self::buildIndex($rows);

        foreach ($rows as &$row) {
            $res = self::resolveOne(
                (string) $row['ref_replaced'],
                (string) $row['ref_replacement'],
                $index
            );
            $row['ref_final']    = $res['ref_final'];
            $row['final_is_set'] = $res['final_is_set'];
            $row['chain_depth']  = $res['chain_depth'];
            $row['chain_status'] = $res['chain_status'];
        }
        unset($row);

        return $rows;
    }

    /**
     * Index : référence remplacée → comment elle est remplacée.
     *   is_set : tête d'un set (1:N) → on ne traverse pas
     *   target : pour un 1:1, l'unique référence remplaçante
     *
     * Garde-fou : si une réf porte PLUSIEURS lignes `replace`, c'est un 1:N
     * déguisé (fichier incohérent) → on la traite comme un set, donc on ne la
     * traverse pas. Sans ça, on choisirait une cible au hasard.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<string,array<string,mixed>>
     */
    private static function buildIndex(array $rows)
    {
        $index = [];

        foreach ($rows as $row) {
            $from = (string) $row['ref_replaced'];
            if ($from === '') {
                continue;
            }
            if (!isset($index[$from])) {
                $index[$from] = ['is_set' => false, 'target' => null, 'count' => 0];
            }
            ++$index[$from]['count'];

            if (($row['conversion_type'] ?? '') === MsReplacement::TYPE_SET) {
                $index[$from]['is_set'] = true;
            } else {
                $index[$from]['target'] = (string) $row['ref_replacement'];
            }
        }

        foreach ($index as &$entry) {
            if (!$entry['is_set'] && $entry['count'] > 1) {
                $entry['is_set'] = true; // 1:N déguisé → non traversable
            }
        }
        unset($entry);

        return $index;
    }

    /**
     * Suit la chaîne depuis la référence remplaçante d'une ligne.
     *
     * @param string $origin réf remplacée de la ligne (pour détecter A→B→A)
     * @param string $start  réf remplaçante de la ligne (point de départ)
     * @param array<string,array<string,mixed>> $index
     * @return array<string,mixed>
     */
    private static function resolveOne($origin, $start, array $index)
    {
        if ($start === '') {
            return self::result(null, false, 1, MsReplacement::CHAIN_DEAD_END);
        }

        $current = $start;
        $depth   = 1;
        // L'origine est "vue" dès le départ : sinon A→B→A ne serait pas un cycle.
        $seen    = [$origin => true, $start => true];

        while (true) {
            // La réf courante n'est elle-même pas remplacée → terminus.
            if (!isset($index[$current])) {
                return self::result($current, false, $depth, MsReplacement::CHAIN_OK);
            }

            $entry = $index[$current];

            // Tête de set : on s'arrête AVANT d'entrer (pas de set de sets).
            if ($entry['is_set']) {
                return self::result($current, true, $depth, MsReplacement::CHAIN_OK);
            }

            $next = $entry['target'];
            if ($next === null || $next === '') {
                return self::result($current, false, $depth, MsReplacement::CHAIN_OK);
            }

            // Cycle ou profondeur max → on garde la dernière référence saine.
            if (isset($seen[$next]) || $depth >= self::MAX_DEPTH) {
                return self::result($current, false, $depth, MsReplacement::CHAIN_LOOP);
            }

            $seen[$next] = true;
            $current     = $next;
            ++$depth;
        }
    }

    /** @return array<string,mixed> */
    private static function result($refFinal, $finalIsSet, $depth, $status)
    {
        return [
            'ref_final'    => $refFinal,
            'final_is_set' => (bool) $finalIsSet,
            'chain_depth'  => (int) $depth,
            'chain_status' => $status,
        ];
    }
}
