<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Electrozon parser script (runnig with cron)</title>
    <style>
        .error, .exception {
            color: red;
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
require $_SERVER['DOCUMENT_ROOT'].'/admin/config.php';

// Функция транслита имен категорий и товаров для SEO
function translit($s) {
    $s = (string) $s; // преобразуем в строковое значение
    $s = strip_tags($s); // убираем HTML-теги
    $s = str_replace(array("\n", "\r"), " ", $s); // убираем перевод каретки
    $s = preg_replace("/\s+/", ' ', $s); // удаляем повторяющие пробелы
    $s = trim($s); // убираем пробелы в начале и конце строки
    $s = function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s); // переводим строку в нижний регистр (иногда надо задать локаль)
    $s = strtr($s, array('а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'e','ж'=>'j','з'=>'z','и'=>'i','й'=>'y','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o','п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'c','ч'=>'ch','ш'=>'sh','щ'=>'shch','ы'=>'y','э'=>'e','ю'=>'yu','я'=>'ya','ъ'=>'','ь'=>''));
    $s = preg_replace("/[^0-9a-z-_ ]/i", "", $s); // очищаем строку от недопустимых символов
    $s = str_replace(" ", "-", $s); // заменяем пробелы знаком минус
    return $s; // возвращаем результат
}

// Переменная для рекурсивной функции составления пути категории
$pathy = array ();

// Рекурсивная функция для составления пути категории
function getpath($id) {
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

try {

    //Создаем файл лога с именем temp
    $logfile = fopen("temp", 'a+');

    // TODO Как вариант, проверка когда последний раз был парсер
    // если менее чем параметр частоты, то остановить скрипт
    // проверка по дате лога
    // в целом, запуск скрипта осуществляется по cron

    // загрузка файла из electrozon.ru
    if (!copy(ELECTROZON_PATH, 'market_filial_new.yml.xml'))
        throw new Exception('File '.ELECTROZON_PATH.' with goods from electozon.ru not availiable.');

    // Загружаем DOM из файла
    $doc = new DOMDocument();
    $doc->load('market_filial_new.yml.xml');

    // Выбираем все категории товаров
    $cats = $doc->getElementsByTagName('category');
    // Выбираем все товары
    //$prods = $doc->getElementsByTagName( 'offer' );
    $prods = array();

    // Составляем массив категорий, с id родителя, для составления пути категории (нужно для БД opencart)
    $cat_array = array();
    foreach ($cats as $cat) {
        $parent_id = $cat->getAttribute('parentId');
        if (!$parent_id) $parent_id = 0;
        $cat_array[$cat->getAttribute('id')] = $parent_id;
    }

    // подключение к БД магазина
    $mysqli = new mysqli("localhost", DB_USERNAME, DB_PASSWORD, DB_DATABASE);
    if ($mysqli->connect_errno) {
        throw new Exception('Cannot connect to MySQL DB: (' . $mysqli->connect_errno . ') ' . $mysqli->connect_error);
    }
    echo $mysqli->host_info . "<br/>\n";

    // Получаем базовую наценку для товаров
    $q = 'SELECT `value` FROM ' . DB_PREFIX . 'setting WHERE `key`="dashboard_electroparser_markup"';
    if (!$result = $mysqli->query($q)) {
        throw new Exception('Markup price not found or not set in opencart shop. Install extension or setup this setting.');
    }
    $base_markup = (Int)$result->fetch_object()->value;
    // Получаем таблицу с наценками по категориям (если наценки нет, то в массиве будет пустое значение
    $q = 'SELECT c.category_id, c.parent_id, d.name, m.markup FROM `' . DB_PREFIX .
        'category` c JOIN `' . DB_PREFIX . 'category_description` d ON c.category_id = d.category_id LEFT JOIN `' . DB_PREFIX .
        'category_markup` m ON (c.category_id = m.category_id)';

    $category_markup = array();
    if (!$result = $mysqli->query($q)) {
        // Такой таблицы (категорий) нет
        throw new Exception('Categories table not found');
    } else {
        if ($result->num_rows > 0) {
            //TODO что удобнее, ассоциативный или объект? fetch_object()
            while ($obj = $result->fetch_assoc()) {
                $category_markup[] = $obj;
            }
            //print_r ($category_markup);
        } else {
            // Нет категорий вообще, поэтому будет установлена базовая наценка для всех
            // важно - наценки не могут существовать без категорий в БД!
            fwrite($logfile, 'No categories in DB');
            echo "No categories in DB<br>";
        }
    }


    // Проходим по всем категориям, которые получили из файла, сверяем с теми категориями, которые есть в БД
    // Помечаем те, которые только появились в логе как new
    // В конце помечаем те, которые отсутствуют как deleted
    // Если изменено название - помечаем это как changed
    // Остальные - hold
    foreach ($cats as $cat) {
        // Название категории
        $name = $cat->nodeValue;
        // Идентификатор
        $category_id = $cat->getAttribute('id');
        // Идентификатор родителя
        $parent_id = 0;
        $parent_id = $cat->getAttribute('parentId');
        // Помечаем, если родителя нет или = 0 то это верхняя категория top = 0
        if (!$parent_id || ($parent_id == 0)) {
            $parent_id = 0;
            $top = 1;
        } else {
            $top = 0;
        }
        // Создаем транслит
        // TODO его надо будет записывать в таблицу re_seo_url!
        $link = translit($name);
        // Пока запишем в описание категории
        $desc = $link;
        // Ищем такую категорию в существующей таблице
        // Проверяем, не изменялось ли название
        $hold = array_search($category_id, array_column($category_markup, 'category_id'));
        if ($hold === FALSE) $state = "new"; else {
            if ($category_markup[$hold]['name'] === $name) $state = "hold"; else $state = "changed";
            // также надо проверить родителя, вдруг его перенесли в другую категорию!
            if ($category_markup[$hold]['parent_id'] <> $parent_id) $state = "changed";
        }
        $category_markup[$hold]['state'] = $state;

        // Вставляем в главную таблицу
        // Column - не имеет значения, он задает сколько столбцов при выводе товара
        // Sort_order - порядок сортировки
        // Status - активен (1)
        // TODO даты
        // Дата добавление - берем текущую (можно оставлять старую, если статус hold
        // Дата изменения - ставим текущую, если изменяем, для тех! у кого state - changed
        $q = "INSERT INTO re_category(category_id, parent_id, top, `column`, sort_order, status, date_added, date_modified) VALUES " .
            "($category_id, $parent_id, $top, 0, 0, 1, now(), now())";
        if (!$mysqli->query($q)) {
            fwrite($logfile, "Cannot write: (" . $mysqli->errno . ") " . $mysqli->error."\n");
            echo "Cannot write: (" . $mysqli->errno . ") " . $mysqli->error;
        }

        // Вставляем в таблицу описаний
        // language_id - берем дефолтный 1 TODO надо прочитать какой язык в настройках дефолтный
        // name - имя
        // description - пустой, никаких описаний не передается, его не изменять! если не мняется название
        // TODO чтобы можно было задавать свои описания и они сохранялись
        // meta_title - записывать туда название, это для заголовка страницы
        // Записывать только если state - new!
        $q = "INSERT INTO re_category_description(category_id, language_id, `name`, description, meta_title) VALUES " .
            "($category_id, 1, '$name', '$desc', '$name')";
        if (!$mysqli->query($q)) {
            fwrite($logfile, "Cannot write: (" . $mysqli->errno . ") " . $mysqli->error."\n");
            echo "Cannot write: (" . $mysqli->errno . ") " . $mysqli->error;
        }


        // Обнуляем массив для составления пути категории
        $pathy = array();
        // Вычисляем путь
        getpath($category_id);
        // Разворачиваем его в обратную сторону
        $pathy = array_reverse($pathy);
        // Сохраняем путь в таблицу с путем, и записываем уровень каждой записи level, верхний уровень 0
        foreach ($pathy as $level => $catlevel) {
            $q = "INSERT INTO re_category_path(category_id, path_id, level) VALUES " .
                "($category_id, $catlevel, $level)";
            if (!$mysqli->query($q)) {
                fwrite($logfile, "Cannot write: (" . $mysqli->errno . ") " . $mysqli->error."\n");
                echo "Cannot write: (" . $mysqli->errno . ") " . $mysqli->error;
            }
        }

        // Делаем записть в таблицу с магазинами
        // TODO тут надо будет определять, в какой магаз записывать
        $q = "INSERT INTO re_category_to_store(category_id, store_id) VALUES " .
            "($category_id, 0)";
        if (!$mysqli->query($q)) {
            fwrite($logfile, "Cannot write: (" . $mysqli->errno . ") " . $mysqli->error."\n");
            echo "Cannot write: (" . $mysqli->errno . ") " . $mysqli->error;
        }

        // записываем это в лог temp
        fwrite($logfile, "#$state>>> $category_id - $name - $parent_id - $link\n");
        echo "<p>$state - $category_id - $name - $parent_id - $link</p>\n";

    }

    // Проходим по массиву, определяем какие были удалены
    foreach ($category_markup as $key => $item) {
        if (!isset($item['state'])) {
            $item['state'] = 'deleted';
            $category_markup[$key]['state'] = 'deleted';
            // TODO записываем это в лог temp
            echo "<p>" . $item['state'] . " - " . $item['category_id'] . " - " . $item['name'] . " - " . $item['parent_id'] . "</p>\n";
            fwrite($logfile, "#" . $item['state'] . ">>> " . $item['category_id'] . " - " . $item['name'] . " - " . $item['parent_id'] . "\n");
            // TODO надо ли удаленные категории делать неактивными???
            // Возможно, надо проверять, если это категории из Электрозона, то делать неактивными,
            // на случай если будут и другие категории
        };
    }


    // TODO подгружаем базу в скрипт товары из БД
    // TODO если нужно сверять появление новых, изменения и удаление старых
    // такой вариант скрипта значительно загружает ОП, хоть и быстрее
    /*$products = array();
    // Получаем таблицу с товарами, которые есть в БД
    $q = 'SELECT p.product_id, p.model, p.image, p.manufacturer_id, p.price, p.date_available, p.date_added, p.date_modified, d.name, d.description, c.category_id FROM `'.DB_PREFIX.
        'product` p JOIN `'.DB_PREFIX.'product_description` d ON p.product_id = d.product_id JOIN `'.DB_PREFIX.
        'product_to_category` c ON (p.product_id = c.product_id)';
    if (!$result = $mysqli->query($q)) {
        // Такой таблицы еще нет (нет продуктов вообще)
        echo "Products not found";
    } else {
        //TODO что удобнее, ассоциативный или объект? fetch_object()
        while ($obj = $result->fetch_assoc()) {
            $products[] = $obj;
            print_r($obj);
            echo "<br/>";
        }
        //print_r ($products);
    }*/

    // Получаем текущую дата-время для записи в БД
    $date_now = date("Y-m-d H:i:s");

    // Проходим по массиву товаров
    foreach ($prods as $prod) {
        $product_id = $prod->getAttribute('id');                                        // product_id
        $available = $prod->getAttribute('available');                                  // доступность, true, false
        $url = $prod->getElementsByTagName('url')->item(0)->nodeValue;
        //TODO нужно будет считываеть спецификацию и все изображения товара по ссылке
        $category_id = $prod->getElementsByTagName('categoryId')->item(0)->nodeValue;   // category_id
        $price = (int)$prod->getElementsByTagName('price')->item(0)->nodeValue;        // price
        // Пересчитываем цену в соответствии с наценкой, в %
        $markup = array_search($category_id, array_column($category_markup, 'markup'));
        // Если наценка не установлена, ставим дефолтную, базовую
        if (!$markup) $markup = $base_markup;
        $price = (int)round($price * (1 + $markup / 100));
        // Если вдруг цена не указана (в файле такое встречается)
        if (!$price) $price = 0;

        // проверить, есть ли основное изображение
        $picnodes = $prod->getElementsByTagName('picture');
        if ($picnodes->length > 0) {
            // Получаем путь тзображения
            $picture = $prod->getElementsByTagName('picture')->item(0)->nodeValue;      // image
            // разбиваем путь на части
            $path_parts = pathinfo($picture);
            // составляем локальный путь картинки
            $image = "catalog/product/" . $path_parts['filename'] . "." . $path_parts['extension'];
            // TODO если файл присутствует, надо ли сравнить? например, копировать если отличается
            if (!file_exists("./image/" . $image)) {
                copy($picture, "./image/".$image);
            } else {
                // Возможная проверка на изменение изображения
                // Check changing file
                // $contents = file_get_contents($picture);
                // $md5file = md5($contents);
                // if ($md5file == md5_file("./image/".$image) - not change
                // echo "file exists! ";
            }
        } else {
            // если изображения нет, вставляем пустое поле
            $image = "";
        }

        // TODO либо просто очищаем таблицы с товаром перед записью, если не надо контролировать изменения

        // Заменяем апострофы на кавычку. Можно заменять на код апострофа, в принципе
        $name = str_replace("'", '"', $prod->getElementsByTagName('name')->item(0)->nodeValue);                 // name         // meta_title
        // Получаем имя производителя manufacturer
        $vendor = $prod->getElementsByTagName('vendor')->item(0)->nodeValue;
        // код товара по производителю
        $model = $prod->getElementsByTagName('vendorCode')->item(0)->nodeValue;                                                // model
        // описание товара, короткое. полная спецификация берется со страницы товара!
        $desc = str_replace("'", '"', $prod->getElementsByTagName('description')->item(0)->nodeValue);          // description
        // TODO Штрикод - пока не используется, но можно привязать его, и выводить КОД ДЛЯ СКАНЕРА! можно печатать
        $barcode = $prod->getElementsByTagName('barcode')->item(0)->nodeValue;
        // TODO получить параметры товара и сверить их с существующими пармаметрами
        // TODO Если таких параметров в БД нет, то их надо создать, и записать для этого товара
        // TODO Если есть, то просто записать их
        // TODO Для этого надо сначала подгрузить таблицу с параметрами в скрипт

        // Запрашиваем этот товар в существующей таблице, если он есть, сравниваем с обновлением
        // Если отличается, то меняем дату СОЗДАНИЯ, если не отличается - то дату МОДИФИКАЦИИ
        // потому что цена не проверяется, да и в целом проверить невозможно, а так мы сможем
        // вычислить те товары, которые быди удалены в обновлении
        // Если отсутствует такой товар, то значит новый, добавляем, даты одинаковые
        // Дату устанавливаем с помощью PHP, чтобы она была одинакова
        $q = 'SELECT p.product_id, p.model, p.image, p.manufacturer_id, p.price, p.date_available, p.date_added, p.date_modified, d.name, d.description, c.category_id FROM `' . DB_PREFIX .
            'product` p JOIN `' . DB_PREFIX . 'product_description` d ON p.product_id = d.product_id JOIN `' . DB_PREFIX .
            'product_to_category` c ON (p.product_id = c.product_id) WHERE p.product_id = ' . $product_id;
        // Этот статус не останется, т.к. мы не сверяем данные
        $state = "hold";
        if (!$result = $mysqli->query($q)) {
            throw new Exception('No product tables, or DB not available.');
        } else {
            if ($result->num_rows > 0) {
                //$product = $result->fetch_object();
                $state = "changed";
            } else {
                $state = "new";
            }
        }

        // сохраняем в основную таблицу
        $q = "INSERT INTO re_product(product_id, model, quantity, stock_status_id, image, manufacturer_id, price, tax_class_id, date_available, status, date_added, date_modified) VALUES " .
            "($product_id, '$model', 100, 7, '$image', 0, $price, 9, now(), 1, now(), now())";
        if (!$mysqli->query($q)) {
            fwrite($logfile, "Cannot write: (" . $mysqli->errno . ") " . $mysqli->error."\n");
            echo "Cannot write: (" . $mysqli->errno . ") " . $mysqli->error;
        }

        // сохраняем описание товара
        // TODO для разных языков? только для русского!
        // TODO Meta-description - туда загружаем короткое описание
        // TODO Спецификацию загружаем в description
        // TODO Либо проработать сохранение в специальную вкладку, которую можно создать с помощью materialize extension -  шаблоне
        $q = "INSERT INTO re_product_description(product_id, language_id, `name`, description, meta_title) VALUES " .
            "($product_id, 1, '$name', '$desc', '$name')";
        if (!$mysqli->query($q)) {
            fwrite($logfile, "Cannot write: (" . $mysqli->errno . ") " . $mysqli->error."\n");
            echo "Cannot write: (" . $mysqli->errno . ") " . $mysqli->error;
        }

        // сохраняем таблицу с очками, которые даются за товар (ставим по умолчанию 0)
        $q = "INSERT INTO re_product_reward(product_id, customer_group_id, points) VALUES " .
            "($product_id, 1, 0)";
        if (!$mysqli->query($q)) {
            fwrite($logfile, "Cannot write: (" . $mysqli->errno . ") " . $mysqli->error."\n");
            echo "Cannot write: (" . $mysqli->errno . ") " . $mysqli->error;
        }

        // сохраняем связь товара с категорией
        // TODO тут можно привязать показ товара во всех родительских категориях! если надо
        $q = "INSERT INTO re_product_to_category(product_id, category_id) VALUES " .
            "($product_id, $category_id)";
        if (!$mysqli->query($q)) {
            echo "Cannot write: (" . $mysqli->errno . ") " . $mysqli->error;
        }

        // сохраняем связь с магазином
        // TODO нужно проверять какой магазин активен?
        $q = "INSERT INTO re_product_to_store(product_id, store_id) VALUES " .
            "($product_id, 0)";
        if (!$mysqli->query($q)) {
            fwrite($logfile, "Cannot write: (" . $mysqli->errno . ") " . $mysqli->error."\n");
            echo "Cannot write: (" . $mysqli->errno . ") " . $mysqli->error;
        }

        // Сохраняем в лог temp внесение записи о товаре
        fwrite($logfile, "#$state>>> $product_id - $name - $picture<br>\n");
        echo "$state - $product_id - $name - $picture<br>\n";

    }

    $products_del = array();
    // TODO Делаем запрос в базу, на предмет тех товаров, которых там нет
    // TODO определяем по дате модификации, если она старая - значит товар был удален из обновления
    // TODO Надо ли их делать неактивными???
    $q = "SELECT p.product_id, p.model, p.image, p.manufacturer_id, p.price, p.date_available, p.date_added, p.date_modified, d.name, d.description, c.category_id FROM `" . DB_PREFIX .
        "product` p JOIN `" . DB_PREFIX . "product_description` d ON p.product_id = d.product_id JOIN `" . DB_PREFIX .
        "product_to_category` c ON (p.product_id = c.product_id) WHERE p.date_modified <> '".$date_now."'";
    if (!$result = $mysqli->query($q)) {
        // Ошибка при работе с таблицей товаров! Недоступна?
        throw new Exception('Error while connect to product tables, possible DB not available');
    } else {
        //TODO что удобнее, ассоциативный или объект? fetch_object()
        while ($obj = $result->fetch_assoc()) {
            $products_del[] = $obj;
            fwrite($logfile, "#deleted>>> ".$obj['product_id']." - ".$obj['name']." - ".$obj['image']."\n");
            echo "deleted - ".$obj['product_id']." - ".$obj['name']." - ".$obj['image']."<br>\n";
            //print_r($obj);
        }
    }

    // TODO переименовываем temp log с именем в которое входит дата и время создания
    // можно имя использовать для определения срока в админке, либо по атрибутам файла
    // также в админке выводить что происходило с категориями и товарами

} catch (Throwable $e) {
    echo '<p class="error">ERROR: '.$e->getMessage();
    // запись в лог работы парсера
    fwrite($logfile, "ERROR: ".$e->getMessage()."\n");
    //print_r($e);
} catch (Exception $e) {
    echo '<p class="exception">EXCEPTION: '.$e->getMessage().'</p>';
    // запись в лог работы парсера
    fwrite($logfile, "EXCEPTION: ".$e->getMessage()."\n");
    //print_r($e);
}

fclose($logfile);

?>

</body>
</html>
