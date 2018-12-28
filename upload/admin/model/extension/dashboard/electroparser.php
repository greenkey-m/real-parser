<?php
/**
 * Created by PhpStorm.
 * User: matt
 * Date: 14.12.2018
 * Time: 18:12
 */

class ModelExtensionDashboardElectroparser extends Model {

    private $categories;

    public function getParserDate() {

        //$query = $this->db->query("SELECT `key`, `data`, `date_added` FROM `" . DB_PREFIX . "customer_activity` ORDER BY `date_added` DESC LIMIT 0,5");
        //return $query->rows;

        return "";
    }

    public function loadAllCategories() {
        // Если таблицы нет, то ее надо создать!
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "category c LEFT JOIN " .
            DB_PREFIX . "category_description cd ON (c.category_id = cd.category_id) LEFT JOIN " .
            DB_PREFIX . "category_markup cm ON (c.category_id = cm.category_id) LEFT JOIN ".
            DB_PREFIX . "category_to_store c2s ON (c.category_id = c2s.category_id) WHERE cd.language_id = '" . (int)$this->config->get('config_language_id') . "' 
            AND c2s.store_id = '" . (int)$this->config->get('config_store_id') . "'  AND c.status = '1'");

        return $query->rows;
    }

    public function changeCategory ($category, $withChidren = false) {
        // если присутствует то обновляем
        $this->db->query("UPDATE `re_category_markup` SET `markup`=10,`status`=1,`date_added`=now(),`date_modified`=now() WHERE `category_id`=1");
        // иначе вставляем

    }
}