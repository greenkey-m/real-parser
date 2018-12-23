<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Electrozon parser script (runnig with cron)</title>
    <style>
        .error, .exception {
            color: red;
        }
        .deleted {
            color: #78909c;
        }
        .new {
            color: blue;
        }
    </style>
</head>
<body>

<?php
/**
 * Created by PhpStorm.
 * User: matt
 * Date: 04.12.2018
 * Time: 14:20
 */

CONST ELECTROZON_PATH = 'https://electrozon.ru/files/market_filial_new.yml';

// подключение конфига магазина
//require('../../../config.php');
require $_SERVER['DOCUMENT_ROOT'] . '/admin/config.php';

// Функция транслита имен категорий и товаров для SEO
function translit($s)
{
    $s = (string)$s; // преобразуем в строковое значение
    $s = strip_tags($s); // убираем HTML-теги
    $s = str_replace(array("\n", "\r"), " ", $s); // убираем перевод каретки
    $s = preg_replace("/\s+/", ' ', $s); // удаляем повторяющие пробелы
    $s = trim($s); // убираем пробелы в начале и конце строки
    $s = function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s); // переводим строку в нижний регистр (иногда надо задать локаль)
    $s = strtr($s, array('а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'j', 'з' => 'z', 'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch', 'ы' => 'y', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya', 'ъ' => '', 'ь' => ''));
    $s = preg_replace("/[^0-9a-z-_ ]/i", "", $s); // очищаем строку от недопустимых символов
    $s = str_replace(" ", "-", $s); // заменяем пробелы знаком минус
    return $s; // возвращаем результат
}

// Переменная для рекурсивной функции составления пути категории
$pathy = array();

// Рекурсивная функция для составления пути категории
function getpath($id)
{
    // найти элемент с id
    // внести его в массив
    // если у него есть родитель, повторить
    // если нет, остановить
    global $cat_array, $pathy;
    $pathy[] = $id;
    if ($cat_array[$id]) {
        if ($cat_array[$id] > 0) {
            getpath($cat_array[$id]);
        }
    }
}

function get_page_parser($url) {
    $html = file_get_html($url);
    $spec = $html->find('#tab-all > table');
    $data['spec'] = $spec[0]->outertext;
    $imgs = $html->find('#gallery-product-thumbs > ul > li > a');
    $data['images'] = array();
    foreach($imgs as $e)
        $data['images'][] = $e->href;
    return $data;
}



try {

    // подключение к БД магазина
    $mysqli = new mysqli("localhost", DB_USERNAME, DB_PASSWORD, DB_DATABASE);
    if ($mysqli->connect_errno) {
        throw new Exception('Cannot connect to MySQL DB: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    echo $mysqli->host_info . "<br/>\n";


} catch (Throwable $e) {
    echo '<p class="error">ERROR: ' . $e->getMessage();
    // запись в лог работы парсера
    fwrite($logfile, "ERROR: " . $e->getMessage() . "\n");
    //print_r($e);
} catch (Exception $e) {
    echo '<p class="exception">EXCEPTION: ' . $e->getMessage() . '</p>';
    // запись в лог работы парсера
    fwrite($logfile, "EXCEPTION: " . $e->getMessage() . "\n");
    //print_r($e);
}

fclose($logfile);

?>

</body>
</html>
