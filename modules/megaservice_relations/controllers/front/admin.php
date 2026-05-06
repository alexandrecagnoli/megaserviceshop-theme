<?php
/**
 * Endpoint AJAX appelé depuis le BO (page produit, onglet Relations Powerparts).
 * Authentification : présence d'un cookie psAdmin avec id_employee.
 *
 * Actions :
 *   - search : autocomplete produits (nom + référence)
 *   - save   : remplace les relations d'un produit + type
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_ . 'megaservice_relations/classes/ProductRelationService.php';

class Megaservice_relationsAdminModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        header('Content-Type: application/json; charset=utf-8');

        // Auth : cookie psAdmin avec id_employee
        $cookie = new Cookie('psAdmin');
        if (!$cookie->id_employee) {
            $this->respond(['success' => false, 'message' => 'Auth required'], 401);
        }

        $action = Tools::getValue('action');
        switch ($action) {
            case 'search':
                $this->actionSearch();
                break;
            case 'save':
                $this->actionSave();
                break;
            default:
                $this->respond(['success' => false, 'message' => 'Unknown action']);
        }
    }

    private function actionSearch()
    {
        $q = trim((string) Tools::getValue('q'));
        $exclude = (int) Tools::getValue('exclude');
        if (mb_strlen($q) < 2) {
            $this->respond(['success' => true, 'products' => []]);
        }

        $idLang = (int) $this->context->language->id;
        $like   = '%' . pSQL($q) . '%';

        $sql = 'SELECT p.`id_product`, pl.`name`, pl.`link_rewrite`, p.`reference`, im.`id_image`
                FROM `' . _DB_PREFIX_ . 'product` p
                INNER JOIN `' . _DB_PREFIX_ . 'product_lang` pl
                  ON p.`id_product` = pl.`id_product` AND pl.`id_lang` = ' . $idLang . '
                LEFT JOIN `' . _DB_PREFIX_ . 'image` im
                  ON p.`id_product` = im.`id_product` AND im.`cover` = 1
                WHERE p.`active` = 1
                AND p.`id_product` != ' . $exclude . '
                AND (pl.`name` LIKE "' . $like . '" OR p.`reference` LIKE "' . $like . '")
                LIMIT 10';

        $rows = Db::getInstance()->executeS($sql);
        $out = [];
        foreach ((array) $rows as $r) {
            $idImage = (int) $r['id_image'];
            $coverUrl = $idImage
                ? $this->context->link->getImageLink($r['link_rewrite'], $idImage, 'small_default')
                : '';
            $out[] = [
                'id_product' => (int) $r['id_product'],
                'name'       => $r['name'],
                'reference'  => (string) $r['reference'],
                'cover_url'  => $coverUrl,
            ];
        }
        $this->respond(['success' => true, 'products' => $out]);
    }

    private function actionSave()
    {
        $idSource = (int) Tools::getValue('id_product_source');
        $type     = (string) Tools::getValue('type');
        $itemsRaw = (string) Tools::getValue('items');

        if (!$idSource || !in_array($type, MsProductRelationService::allTypes(), true)) {
            $this->respond(['success' => false, 'message' => 'Bad params']);
        }

        $items = json_decode($itemsRaw, true);
        if (!is_array($items)) {
            $items = [];
        }

        $ok = MsProductRelationService::replaceRelations($idSource, $type, $items);
        $this->respond(['success' => (bool) $ok]);
    }

    private function respond(array $data, $statusCode = 200)
    {
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
