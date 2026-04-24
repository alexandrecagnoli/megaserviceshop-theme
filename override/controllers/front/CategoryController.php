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
        15 => 'full', // Équipements pilotes
    ];

    public function initContent()
    {
        parent::initContent();

        $category_id = (int) $this->category->id;
        $template = isset(self::$CATEGORY_TEMPLATES[$category_id])
            ? self::$CATEGORY_TEMPLATES[$category_id]
            : 'default';

        $this->context->smarty->assign([
            'ms_category_template' => $template,
            'ms_is_full_width'     => $template === 'full',
        ]);
    }
}
