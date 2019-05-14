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

//Получение дерева категорий
$ch = curl_init("https://b2b.i-t-p.pro/download/catalog/json/catalog_tree.json");
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Cookie: session_id=' . $session )
);
$result = curl_exec($ch);
curl_close ($ch);
$resCatalogTree = json_decode($result);

echo "<h1 class='new'>Категории</h1>";
foreach ($resCatalogTree as $cat) {
    echo $cat->name."<br/>";
    if ($cat->childrens) {
        print_r($cat->childrens);
    }
}

//print_r($resCatalogTree);


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
$resProducts = json_decode($result);
//print_r($resProducts);


?>

</body>
</html>
