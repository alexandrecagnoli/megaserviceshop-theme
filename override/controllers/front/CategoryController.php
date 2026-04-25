<?php

class CategoryController extends CategoryControllerCore
{
    /**
     * Mapping ID catégorie => template
     *
     * Templates disponibles :
     *   'default' — 3 colonnes + sidebar filtres
     *   'full'    — 4 colonnes pleine largeur, sous-catégories en cards
     *
     * Pour ajouter une catégorie : ajouter son ID (visible dans l'URL PS, ex: /15-equipements → 15)
     */
    private static $CATEGORY_TEMPLATES = [
        14 => 'full', // Lifestyle (vêtements)
        15 => 'full', // Équipements pilotes
    ];

    /**
     * Catégories racines qui affichent le contexte "moto sélectionnée"
     * (bandeau moto dans le header + cat-card dans la sidebar).
     * S'applique à la racine ET à toutes ses sous-catégories (test nested set).
     */
    private static $MOTO_CONTEXT_ROOT_IDS = [
        41, // Accessoires Powerparts
    ];

    public function initContent()
    {
        parent::initContent();

        $category_id = (int) $this->category->id;
        $template = isset(self::$CATEGORY_TEMPLATES[$category_id])
            ? self::$CATEGORY_TEMPLATES[$category_id]
            : 'default';

        $this->context->smarty->assign([
            'ms_category_template'  => $template,
            'ms_is_full_width'      => $template === 'full',
            'ms_show_moto_context'  => $this->isInMotoContextSubtree(),
        ]);
    }

    private function isInMotoContextSubtree()
    {
        if (empty(self::$MOTO_CONTEXT_ROOT_IDS) || !$this->category->id) {
            return false;
        }

        foreach (self::$MOTO_CONTEXT_ROOT_IDS as $root_id) {
            $root = new Category((int) $root_id);
            if (!$root->id) {
                continue;
            }
            if ($this->category->nleft >= $root->nleft && $this->category->nright <= $root->nright) {
                return true;
            }
        }

        return false;
    }
}
