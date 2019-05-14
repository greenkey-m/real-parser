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


$ch = curl_init("https://b2b.i-t-p.pro/api");
//Аутентификация
$dataAuth = array("request" => array(
    "method" => "login",
    "module" => "system"
),
    "data" => array(
        "login" => "greenkey",
        "passwd" => "merlin"
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
    echo "<p class='new'>Auth success. session_id=" . $resAuth->data->session_id ."</p>";
else {
    echo "<p class='new'>Auth Error</p>\n";
    print_r($resAuth);
    die();
}
echo "<br/>";
//Запоминаем сессию
$session = $resAuth->data->session_id;

$hours = 24;
if (file_exists('itp-cat.json')) {
    $filedate = filectime ( 'itp-cat.json' );
    $datetime1 = new DateTime();
    $datetime1->setTimestamp ( $filedate );
    $datetime2 = new DateTime();
    $interval = $datetime2->diff($datetime1, true);
    $hours = $interval->h;
    $hours = $hours + ($interval->days*24);
}

if ($hours > 3) {
//Получение дерева категорий
    $ch = curl_init("https://b2b.i-t-p.pro/download/catalog/json/catalog_tree.json");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Cookie: session_id=' . $session )
    );
    $result = curl_exec($ch);
    curl_close ($ch);

    $fp = fopen('itp-cat.json', 'w');
    fwrite($fp, $result);
    fclose($fp);

} else {
    // Read JSON file
    $result = file_get_contents('itp-cat.json');
}
$resCatalogTree = json_decode($result);

echo "<h1 class='new'>Категории</h1>";

function list_cat($cats) {
    echo "<ul>";
    foreach ($cats as $cat) {
        echo "<li>".$cat->name."</li>";
        if (isset($cat->childrens)) {
            list_cat($cat->childrens);
        }
    }
    echo "</ul>";

}

list_cat($resCatalogTree);


echo "<h1 class='new'>Товары</h1>";

$hours = 24;
if (file_exists('itp-prod.json')) {
    $filedate = filectime ( 'itp-prod.json' );
    $datetime1 = new DateTime();
    $datetime1->setTimestamp ( $filedate );
    $datetime2 = new DateTime();
    $interval = $datetime2->diff($datetime1, true);
    $hours = $interval->h;
    $hours = $hours + ($interval->days*24);
}

if ($hours > 10) {
//Получение товаров
    $ch = curl_init("https://b2b.i-t-p.pro/download/catalog/json/products.json");
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Cookie: session_id=' . $session )
    );
    $result = curl_exec($ch);
    curl_close ($ch);

    $fp = fopen('itp-prod.json', 'w');
    fwrite($fp, $result);
    fclose($fp);

} else {
    // Read JSON file
    $result = file_get_contents('itp-prod.json');
}
$resProducts = json_decode($result);

foreach ($resProducts as $prod) {
    echo "<p>".$prod->name."</p>";
}
//print_r($resProducts);



echo "<h1 class='new'>Наличие и цена товаров</h1>";

$hours = 24;
if (file_exists('itp.json')) {
    $filedate = filectime ( 'itp.json' );
    $datetime1 = new DateTime();
    $datetime1->setTimestamp ( $filedate );
    $datetime2 = new DateTime();
    $interval = $datetime2->diff($datetime1, true);
    $hours = $interval->h;
    $hours = $hours + ($interval->days*24);
}

if ($hours > 3) {
//Список товаров получаем аналогичным образом
//Получение всех товаров в наличии их цены
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
    // Read JSON file
    $result = file_get_contents('itp.json');
}

$resPrices = json_decode($result);

foreach ($resPrices->data->products as $prod) {
    echo "<p>";
    print_r($prod);
    echo "</p>";
}


?>

</body>
</html>
