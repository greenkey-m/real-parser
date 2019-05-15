<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ITP connector script (runnig with cron)</title>
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

CONST ITP_API_PATH = 'https://b2b.i-t-p.pro/api';

CONST ERROR = 'ERROR: ';
CONST EXCEPT = 'EXCEPTION: ';
CONST NORMAL = 'NORMAL';
CONST H1 = 'H1';
CONST H2 = 'H2';
CONST H3 = 'H3';
CONST UL = 'UL';
CONST ULE = 'ULE';
CONST LI = 'LI';

// подключение конфига магазина
//require('../../../config.php');
require $_SERVER['DOCUMENT_ROOT'] . '/admin/config.php';

//Создаем файл лога с именем temp
$logfile = fopen("temp", 'a+');

/**
 * Запись в лог-файл
 * Возвращает false, если не удалась
 *
 * @param $s - строка для записи
 * @param $type - тип записи (ERROR, EXCEPTION, NORMAL)
 * @param $class - класс для экрана
 */
function logging($s, $type, $class='') {
    global $logfile;
    // TODO Сделать проверку записи
    fwrite($logfile, $type . $s. "\n");
    switch ($type) {
        case H1: echo "<h1 class='".$class."'>".$s."</h1>"; break;
        case H2: echo "<h2 class='".$class."'>".$s."</h2>"; break;
        case H3: echo "<h3 class='".$class."'>".$s."</h3>"; break;
        case UL: echo "<ul>"; break;
        case ULE: echo "</ul>"; break;
        case LI: echo "<li class='".$class."'>".$s."</li>"; break;
        default: echo "<p class='".$class."'>".$s."</p>";
    }
}


/**
 * Функция транслита имен категорий и товаров для SEO
 *
 * @param $s
 * @return mixed|string|string[]|null
 */
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


/**
 * Авторизация в сервисе,
 * возвращает идентификатор сессии,
 * либо false если неуспешно
 *
 * @param $login - логин
 * @param $pass - пароль
 * @return string - идентиикатор сессии
 */
function itp_auth($login, $pass) {
    $ch = curl_init(ITP_API_PATH);
//Аутентификация
    $dataAuth = array("request" => array(
        "method" => "login",
        "module" => "system"
    ),
        "data" => array(
            "login" => $login,
            "passwd" => $pass
        )
    );
    $dataAuthString = json_encode($dataAuth);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
    curl_setopt($ch, CURLOPT_POSTFIELDS, $dataAuthString);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Length: ' . strlen($dataAuthString)
    ));
    $result = curl_exec($ch);
    curl_close ($ch);
    $resAuth = json_decode($result);
    if (($resAuth) && ($resAuth->success) && ($resAuth->success == 1))
        logging("Auth success. session_id=" . $resAuth->data->session_id, NORMAL);
    else {
        logging("Auth Error", ERROR);
        logging(serialize($resAuth), NORMAL);
        // TODO Тут надо правильно все прервать. Вероятно, надо исключениями
        die();
    }
    //Возвращаем сессию
    return $resAuth->data->session_id;
}

/**
 * Возвращает время сушествования файла в часах
 * Если файла нет, возвращает 24
 *
 * @param $file - имя файла
 * @return float|int - часы
 * @throws Exception
 */
function gethours($file) {
    $hours = 24;
    if (file_exists($file)) {
        $filedate = filectime ( $file );
        $datetime1 = new DateTime();
        $datetime1->setTimestamp ( $filedate );
        $datetime2 = new DateTime();
        $interval = $datetime2->diff($datetime1, true);
        $hours = $interval->h;
        $hours = $hours + ($interval->days*24);
    }
    return $hours;
}


try {
    $session = itp_auth('greenkey', 'merlin');

    // подключение к БД магазина
    $mysqli = new mysqli("localhost", DB_USERNAME, DB_PASSWORD, DB_DATABASE);


    if (gethours('itp-cat.json') > 3) {
        //Получение дерева категорий
        $ch = curl_init("https://b2b.i-t-p.pro/download/catalog/json/catalog_tree.json");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Cookie: session_id=' . $session )
        );
        $result = curl_exec($ch);
        curl_close ($ch);

        // Сохраняем в кэш файл
        $fp = fopen('itp-cat.json', 'w');
        fwrite($fp, $result);
        fclose($fp);

    } else {
        // Читаем из кэша
        $result = file_get_contents('itp-cat.json');
    }
    // Получаем дерево каталогов для обработки
    $resCatalogTree = json_decode($result);

    logging('Категории', H1, 'new');

    // Получаем установленный язык для магазина (из настроек) и таблицы
    $q = "SELECT language_id FROM " . DB_PREFIX . "language WHERE `code`=(SELECT `value` FROM " . DB_PREFIX . "setting WHERE `key`='config_language')";
    if (!$result = $mysqli->query($q)) {
        throw new Exception('Problem in opencart installation settings.');
    }
    $base_lang = 1;
    $base_lang = (Int)$result->fetch_object()->language_id;

    // Получаем базовую наценку для товаров
    $q = 'SELECT `value` FROM ' . DB_PREFIX . 'setting WHERE `key`="dashboard_electroparser_markup"';
    if (!$result = $mysqli->query($q)) {
        throw new Exception('Markup price not found or not set in opencart shop. Install extension or setup this setting.');
    }
    $base_markup = (Int)$result->fetch_object()->value;
    logging("Base markup in Real connector is ".$base_markup." %", NORMAL);

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
            while ($obj = $result->fetch_assoc()) {
                $category_markup[] = $obj;
            }
        } else {
            // Нет категорий вообще, поэтому будет установлена базовая наценка для всех
            // важно - наценки не могут существовать без категорий в БД!
            logging("No categories in DB", NORMAL);
        }
    }
    // TODO Сдедать запись наценок в базу!!!!!!!!!!

    // Очищаем дополнительные таблицы с описаниями и путями TRUNCATE TABLE Table1
    $q = "TRUNCATE TABLE " . DB_PREFIX . "category_description";
    if (!$mysqli->query($q)) {
        fwrite($logfile, "Cannot truncate category table: (" . $mysqli->errno . ") " . $mysqli->error . "\n");
        echo "Cannot truncate category table: (" . $mysqli->errno . ") " . $mysqli->error;
    }
    $q = "TRUNCATE TABLE " . DB_PREFIX . "category_path";
    if (!$mysqli->query($q)) {
        fwrite($logfile, "Cannot truncate category table: (" . $mysqli->errno . ") " . $mysqli->error . "\n");
        echo "Cannot truncate category table: (" . $mysqli->errno . ") " . $mysqli->error;
    }
    $q = "TRUNCATE TABLE " . DB_PREFIX . "category_to_store";
    if (!$mysqli->query($q)) {
        fwrite($logfile, "Cannot truncate category table: (" . $mysqli->errno . ") " . $mysqli->error . "\n");
        echo "Cannot truncate category table: (" . $mysqli->errno . ") " . $mysqli->error;
    }



    // Рекурсивно проходим по дереву категорий
    function list_cat($cats, $parent) {
        global $category_markup, $base_lang, $mysqli;

        logging('', UL);
        foreach ($cats as $cat) {

            // Название категории
            $name = $cat->name;
            // Идентификатор
            $category_id = $cat->id;
            // Идентификатор родителя
            $parent_id = end($parent);
            // Помечаем, если родителя нет или = 0 то это верхняя категория top = 0
            if (!$parent_id || ($parent_id == 0)) {
                $parent_id = 0;
                $top = 1;
            } else {
                $top = 0;
            }
            // Создаем транслит
            $link = translit($name);
            // Пока запишем в описание категории
            $desc = $link;
            // Ищем такую категорию в существующей таблице
            // Проверяем, не изменялось ли название
            $hold = array_search($category_id, array_column($category_markup, 'category_id'));
            if ($hold === FALSE) {
                $state = "new";
                $hold =  $category_id;
                // TODO вот тут проверить. так мы добавляем новую категорию в этот массив, а надо ли?
            } else {
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

            if ($state == "new") {
                $q = "INSERT INTO " . DB_PREFIX . "category(category_id, parent_id, top, `column`, sort_order, status, date_added, date_modified) VALUES " .
                    "($category_id, $parent_id, $top, 0, 0, 1, now(), now())";
            };
            if ($state == "changed") {
                $q = "UPDATE " . DB_PREFIX . "category SET parent_id=$parent_id, top=$top, date_modified=now()  WHERE category_id = $category_id";
            }
            if ($state <> "hold") {
                if (!$mysqli->query($q)) {
                    logging("Cannot write category: (" . $mysqli->errno . ") " . $mysqli->error, ERROR);
                }
            }

            // Вставляем в таблицу описаний
            // language_id - берем из настроек $base_lang
            // name - имя
            // description - пустой, никаких описаний не передается, его не изменять! если не мняется название
            // meta_title - записывать туда название, это для заголовка страницы
            // Записывать только если state - new!
            $q = "INSERT INTO " . DB_PREFIX . "category_description(category_id, language_id, `name`, description, meta_title) VALUES " .
                "($category_id, $base_lang, '$name', '$desc', '$name')";
            if (!$mysqli->query($q)) {
                logging("Cannot write category: (" . $mysqli->errno . ") " . $mysqli->error, ERROR);
            }


            // Сохраняем путь в таблицу с путем, и записываем уровень каждой записи level, верхний уровень 0
            foreach ($parent as $level => $catlevel) {
                if ($level > 0) {
                    $q = "INSERT INTO " . DB_PREFIX . "category_path(category_id, path_id, level) VALUES " .
                        "($category_id, $catlevel, $level - 1)";
                    if (!$mysqli->query($q)) {
                        logging("Cannot write category: (" . $mysqli->errno . ") " . $mysqli->error, ERROR);
                    }
                }
            }
            // Записываем последнию запись пути
            $level = count($parent) - 1;
            $q = "INSERT INTO " . DB_PREFIX . "category_path(category_id, path_id, level) VALUES " .
                "($category_id, $category_id, $level)";
            if (!$mysqli->query($q)) {
                logging("Cannot write category: (" . $mysqli->errno . ") " . $mysqli->error, ERROR);
            }


            // Делаем записать в таблицу с магазинами
            // TODO тут надо будет определять, в какой магаз записывать
            $q = "INSERT INTO " . DB_PREFIX . "category_to_store(category_id, store_id) VALUES " .
                "($category_id, 0)";
            if (!$mysqli->query($q)) {
                logging("Cannot write category: (" . $mysqli->errno . ") " . $mysqli->error,ERROR);
            }

            // Сохраняем seo ссылку на категорию
            $q = "SELECT * FROM " . DB_PREFIX . "seo_url WHERE (`query` = 'category_id=$category_id') AND (store_id = 0) AND (language_id = 1)";
            if (!$result = $mysqli->query($q)) {
                // Такой таблицы (ссылки) нет
                logging("Table with seo links not found",ERROR);
                throw new Exception('Table with seo links not found');
            } else {
                if ($result->num_rows > 0) {
                    // Обновляем запись
                    //$q = "UPDATE " . DB_PREFIX . "seo_url SET keyword='$link' WHERE (query = 'category_id=$category_id') AND (store_id = 0) AND (language_id = 1)";
                    // TODO нужен параметр, который определит, надо ли перезаписывать. Может имена были исправлены вручную
                    // пока не изменяем!
                } else {
                    // Вставляем запись
                    $q = "INSERT INTO " . DB_PREFIX . "seo_url (store_id, language_id, query, keyword) VALUES (0, 1, 'category_id=$category_id', '$link')";

                    if (!$result = $mysqli->query($q)) {
                        // Невозможно записать ссылку
                        logging("Cannot save seo link for category",ERROR);
                        // Тут необязательно делать критический сброс
                        throw new Exception('Cannot save seo link for category');
                    }
                }
            }


            logging($cat->name, LI);
            if (isset($cat->childrens)) {
                $next_parent = $parent;
                array_push($next_parent, $category_id);
                list_cat($cat->childrens, $next_parent);
            }
        }
        logging('', ULE);
    }
    list_cat($resCatalogTree, [0]);

    // TODO проход по массиву, записать в лог какие категории были удалены

    logging('Товары', H1, 'new');

    if (gethours('itp-prod.json') > 10) {
        //Получение товаров
        $ch = curl_init("https://b2b.i-t-p.pro/download/catalog/json/products.json");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Cookie: session_id=' . $session )
        );
        $result = curl_exec($ch);
        curl_close ($ch);

        // TODO Тут и везде встроить проверку на исключения
        $fp = fopen('itp-prod.json', 'w');
        fwrite($fp, $result);
        fclose($fp);

    } else {
        // Читаем из кэша
        $result = file_get_contents('itp-prod.json');
    }

    // Получаем массив товаров
    $resProducts = json_decode($result);

    foreach ($resProducts as $prod) {
        //logging($prod->sku.' - '.$prod->name.' - '.$prod->part, NORMAL);
    }
    // С самим каталогом ничего делать не надо, а вот по наличию надо загружать отсюда данные

    logging('Наличие и цена товаров', H1, 'new');

    if (gethours('itp.json') > 3) {
        //Список товаров получаем аналогичным образом
        //Получение всех товаров в наличии их цены
        //TODO Сделать вывод сборны, данные товаров только те, которые в наличии
        $ch = curl_init("https://b2b.i-t-p.pro/api");
        $dataAuth = array("request" => array(
            "method" => "get_active_products",
            "model"  => "client_api",
            "module" => "platform"
        ),
            "session_id" => $session
        );
        $dataAuthString = json_encode($dataAuth);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $dataAuthString);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Length: ' . strlen($dataAuthString)
        ));
        $result = curl_exec($ch);
        curl_close ($ch);

        $fp = fopen('itp.json', 'w');
        fwrite($fp, $result);
        fclose($fp);

    } else {
        // Читаем тз кэша
        $result = file_get_contents('itp.json');
    }

    // Получаем данные о наличии
    $resPrices = json_decode($result);


    // Получаем текущую дата-время для записи в БД
    $date_now = date("Y-m-d H:i:s");

    // Очищаем дополнительные таблицы товаров.
    // В основной и в описании в конце удалим те, которые были исключены из обновления,
    // Предварительно сохранив их в лог
    $q = "TRUNCATE TABLE " . DB_PREFIX . "product_reward";
    if (!$mysqli->query($q)) {
        fwrite($logfile, "Cannot truncate product table: (" . $mysqli->errno . ") " . $mysqli->error . "\n");
        echo "Cannot truncate product table: (" . $mysqli->errno . ") " . $mysqli->error;
    }
    $q = "TRUNCATE TABLE " . DB_PREFIX . "product_to_category";
    if (!$mysqli->query($q)) {
        fwrite($logfile, "Cannot truncate product table: (" . $mysqli->errno . ") " . $mysqli->error . "\n");
        echo "Cannot truncate product table: (" . $mysqli->errno . ") " . $mysqli->error;
    }
    $q = "TRUNCATE TABLE " . DB_PREFIX . "product_to_store";
    if (!$mysqli->query($q)) {
        fwrite($logfile, "Cannot truncate product table: (" . $mysqli->errno . ") " . $mysqli->error . "\n");
        echo "Cannot truncate product table: (" . $mysqli->errno . ") " . $mysqli->error;
    }
    $q = "TRUNCATE TABLE " . DB_PREFIX . "product_attribute";
    if (!$mysqli->query($q)) {
        fwrite($logfile, "Cannot truncate product attribute table: (" . $mysqli->errno . ") " . $mysqli->error . "\n");
        echo "Cannot truncate product attribute table: (" . $mysqli->errno . ") " . $mysqli->error;
    }


    // Счетчик для тестирования кода
    $counter = 0;

    // TODO Проверка наличия записей
    // Читаем из массива товары
    foreach ($resPrices->data->products as $prod) {
        // Для тестирования. прерывает выполнение парсера.
        $counter++;
        //if ($counter > 100) break;

        $key = array_search($prod->sku, array_column($resProducts, 'sku'));

        $product_id = $prod->sku;                                                                   // product_id
        $available = true;                                                                          // доступность, true, false
        $url = '';
        //TODO нужно будет считываеть спецификацию и все изображения товара по ссылке
        $category_id = $resProducts[$key]->category;                                                // category_id
        $price = $prod->price;                                                                      // price
        // Пересчитываем цену в соответствии с наценкой, в %
        $markup = array_search($category_id, array_column($category_markup, 'markup'));
        // Если наценка не установлена, ставим дефолтную, базовую
        if (!$markup) $markup = $base_markup;
        $price = (int)round($price * (1 + $markup / 100));
        // Если вдруг цена не указана (в файле такое встречается)
        if (!$price) $price = 0;
        // Округляем все цены до десятков в большую сторону
        $price = ceil($price/10) * 10;
        // если изображения нет, вставляем пустое поле
        $image = "";

        // Заменяем апострофы на кавычку. Можно заменять на код апострофа, в принципе
        $name = str_replace("'", '&#39;', $resProducts[$key]->name);                 // name         // meta_title
        $link = translit($name);
        // Получаем имя производителя manufacturer
        $vendor = str_replace("'", '&#39;', $resProducts[$key]->vendor);
        // код товара по производителю
        $model = $resProducts[$key]->part;                                                          // model
        // описание товара, короткое. полная спецификация берется со страницы товара!
        $desc = str_replace("'", '&#39;', $name);                                    // description
        // TODO Штрикод - пока не используется, но можно привязать его, и выводить КОД ДЛЯ СКАНЕРА! можно печатать
        $barcode = '';

        // Записываем vendor в таблицу manufacturer, если его нет, читаем полученный новый id
        // Если он есть, берем его id для записи в таблицу товара
        // TODO учесть наличие возможных нескольких магазинов! тогда надо вторую таблицу цеплять
        $manufacturer_id = 0;
        $q = "SELECT * FROM " . DB_PREFIX . "manufacturer WHERE (`name` = '$vendor')";
        if (!$result = $mysqli->query($q)) {
            // Недоступна таблица поставщиков!
            logging('Manufacturer (vendor) table not available '.$q, ERROR);
            throw new Exception('Manufacturer (vendor) table not available'.$q);
        } else {
            if ($result->num_rows == 0) {
                // Создаем запись производителя, получаем его id
                $q = "INSERT INTO " . DB_PREFIX . "manufacturer (`name`, sort_order) ".
                    "VALUES ('$vendor', 0)";
                if (!$result = $mysqli->query($q)) {
                    // Недоступна таблица поставщиков!
                    logging('Cannot insert to Manufacturer (vendor) table not available', ERROR);
                    throw new Exception('Cannot insert to Manufacturer (vendor) table not available');
                }
                $manufacturer_id = $mysqli->insert_id;
                // Сохраняем таблицу производитель = магазин
                $q = "INSERT INTO " . DB_PREFIX . "manufacturer_to_store (manufacturer_id, store_id) ".
                    "VALUES ($manufacturer_id, 0)";
                if (!$result = $mysqli->query($q)) {
                    // Недоступна таблица поставщиков!
                    logging('Manufacturer (vendor) shop table not available', ERROR);
                    throw new Exception('Manufacturer (vendor) shop table not available');
                }
            } else {
                // Получаем его id, для записи в таблицу товара из первой полученной строки
                $obj = $result->fetch_assoc();
                $manufacturer_id = $obj['manufacturer_id'];
            }
        }

        // Запрашиваем этот товар в существующей таблице, если он есть, сравниваем с обновлением
        // Если отсутствует такой товар, то значит новый, добавляем, даты одинаковые
        // Если товар существует, то обновляем, и дату модификации
        // Дату устанавливаем с помощью PHP, чтобы она была одинакова
        // Ниже - это полный запрос, на получение всех данных товара для сверки
        /*$q = 'SELECT p.product_id, p.model, p.image, p.manufacturer_id, p.price, p.date_available, p.date_added, p.date_modified, d.name, d.description, c.category_id FROM `' . DB_PREFIX .
            'product` p JOIN `' . DB_PREFIX . 'product_description` d ON p.product_id = d.product_id JOIN `' . DB_PREFIX .
            'product_to_category` c ON (p.product_id = c.product_id) WHERE p.product_id = ' . $product_id;*/
        // Берем короткий запрос, для проверки наличия товара
        $q = 'SELECT product_id FROM `' . DB_PREFIX . 'product` WHERE product_id = ' . $product_id;
        // Этот статус не останется, т.к. мы не сверяем данные, будет либо changed либо new
        $state = "hold";
        if (!$result = $mysqli->query($q)) {
            throw new Exception('No product tables, or DB not available.');
        } else {
            if ($result->num_rows > 0) {
                //$product = $result->fetch_object();
                // сверять не будем, просто обновляем
                $state = "changed";
            } else {
                $state = "new";
            }
        }

        // Вот эти данные вероятно придется парсить, хотя одна картинка есть и тут
        $spec = "";
        $page['images'] = Array();

        // сохраняем в основную таблицу
        if ($state == "new") {
            $q = "INSERT INTO " . DB_PREFIX . "product(product_id, model, sku, quantity, stock_status_id, image, " .
                "manufacturer_id, price, tax_class_id, date_available, status, date_added, date_modified) VALUES " .
                "($product_id, '$model', 'ITP-$product_id', 100, 7, '$image', $manufacturer_id, $price, 9, '$date_now', 1, '$date_now', '$date_now')";
        } else {
            $q = "UPDATE " . DB_PREFIX . "product SET model='$model', image='$image', " .
                "price=$price, date_modified='$date_now' WHERE product_id = $product_id";
        }
        if (!$mysqli->query($q)) {
            logging("Cannot write product: (" . $mysqli->errno . ") " . $mysqli->error, ERROR);
        }

        // Meta-description - туда загружаем короткое описание
        // Спецификацию с сайта загружаем в description
        // Оттуда жк загружаем все дополнительные изображения в product_image

        // сохраняем описание товара
        if ($state == "new") {
            $q = "INSERT INTO " . DB_PREFIX . "product_description(product_id, language_id, `name`, description, " .
                "meta_title, meta_description) VALUES ($product_id, $base_lang, '$name', '$spec', '$name', '$desc')";
        } else {
            $q = "UPDATE " . DB_PREFIX . "product_description SET `name`='$name', " .
                "meta_title='$name', meta_description='$desc' WHERE product_id = $product_id";
        }
        if (!$mysqli->query($q)) {
            logging("Cannot write product desc: (" . $mysqli->errno . ") " . $mysqli->error, ERROR);
        }


        // сохраняем таблицу с очками, которые даются за товар (ставим по умолчанию 0)
        $q = "INSERT INTO " . DB_PREFIX . "product_reward(product_id, customer_group_id, points) VALUES " .
            "($product_id, 1, 0)";
        if (!$mysqli->query($q)) {
            logging("Cannot write product reward: (" . $mysqli->errno . ") " . $mysqli->error, ERROR);
        }

        // сохраняем связь товара с категорией
        // можно привязать показ товара во всех родительских категориях, если надо
        $q = "INSERT INTO " . DB_PREFIX . "product_to_category(product_id, category_id) VALUES " .
            "($product_id, $category_id)";
        if (!$mysqli->query($q)) {
            logging("Cannot write product category: (" . $mysqli->errno . ") " . $mysqli->error, ERROR);
        }

        // сохраняем связь с магазином
        // TODO в какой магазин сохранять - из настроек парсера, по умолчанию = 0 (основной)
        $q = "INSERT INTO " . DB_PREFIX . "product_to_store(product_id, store_id) VALUES " .
            "($product_id, 0)";
        if (!$mysqli->query($q)) {
            logging("Cannot write product store: (" . $mysqli->errno . ") " . $mysqli->error, ERROR);
        }

        // Сохраняем seo ссылку на товар
        // TODO добавить указание магазина и языка из настроек! и для категорий тоже
        $q = "SELECT * FROM " . DB_PREFIX . "seo_url WHERE (`query` = 'product_id=$product_id') AND (store_id = 0) AND (language_id = 1)";
        if (!$result = $mysqli->query($q)) {
            // Такой таблицы (ссылки) нет
            fwrite($logfile,'Table with seo links not found');
            logging("Table with seo links not found", ERROR);
            throw new Exception('Table with seo links not found');
        } else {
            if ($result->num_rows > 0) {
                // Обновляем запись
                $q = "UPDATE " . DB_PREFIX . "seo_url SET keyword='$link' WHERE (query = 'product_id=$product_id') AND (store_id = 0) AND (language_id = 1)";
                // TODO нужен параметр, который определит, надо ли перезаписывать. Может имена были исправлены вручную
            } else {
                // Вставляем запись
                $q = "INSERT INTO " . DB_PREFIX . "seo_url (store_id, language_id, query, keyword) VALUES (0, 1, 'product_id=$product_id', '$link')";
            }
        }
        if (!$result = $mysqli->query($q)) {
            // Невозможно записать ссылку
            logging("Cannot save seo link for product", ERROR);
            // Тут необязательно делать критический сброс
            throw new Exception('Cannot save seo link for product');
        }

        logging($prod->sku . " - " . $prod->price . ' - ' .$resProducts[$key]->name, NORMAL);

        // TODO Делаем запрос в базу, на предмет тех товаров, которых удалены из обновления
        // TODO Вычислять товар можно как по дате, так и по дополнительным таблицам, которые очищались от записей


    }

} catch (Throwable $e) {
    logging($e->getMessage(), ERROR, 'error');
    //print_r($e);
} catch (Exception $e) {
    logging($e->getMessage(), EXCEPT, 'exception');
    //print_r($e);
}


fclose($logfile);
// переименовываем temp log с именем в которое входит дата и время создания
rename('temp', date("Y-m-d_H-i").".log");

?>

</body>
</html>
