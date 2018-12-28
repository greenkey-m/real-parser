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

    public function saveAllCategories($cats) {
        // Очищаем таблицу со скидками
        $q = "TRUNCATE TABLE " . DB_PREFIX . "category_markup";
        if (!$this->db->query($q)) {
            // Что делать если не удалось очистить
            return false;
        } else {
            // Записываем полученные из формы данные
            foreach ($cats as $cat_id => $markup) {
                if (!$markup) $markup = 0;
                $q = "INSERT INTO `" . DB_PREFIX . "category_markup` (`category_id`, `markup`,`status`,`date_added`,`date_modified`) ".
                    "VALUES (".$cat_id.", ".$markup.", 1, now(), now())";
                if (!$this->db->query($q)) {
                    return false;
                }
            }
            return true;
        }

    }

    public function changeCategory ($category, $withChidren = false) {
        // проверить существует ли такая категория вообще
        // если присутствует наценка то обновляем
        $result = $this->db->query("SELECT * FROM `" . DB_PREFIX . "category_markup` WHERE `category_id`=".$category['category_id']);
        if (!$result) {
            // такой наценки нет
            $this->db->query("INSERT INTO `" . DB_PREFIX . "category_markup` SET `markup`=".$category['markup'].
                ",`status`=1,`date_added`=now(),`date_modified`=now() WHERE `category_id`=".$category['category_id']);
        } else {
            // наценка есть, обновляем
            $this->db->query("UPDATE `" . DB_PREFIX . "category_markup` SET `markup`=10,`status`=1,`date_added`=now(),`date_modified`=now() WHERE `category_id`=1");
        }
        // TODO проверить работу, еще - как записать дочерние?
    }
}