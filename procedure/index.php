<?php

// Устаналиваем формат ответа JSON
header('Content-Type: application/json');

// Проверяем, что в строке запроса имеются нужные данные
$request = $_SERVER['REQUEST_URI'];
$matches = [];
if (!preg_match('/.*\/(users\/[0-9]+\/services\/[0-9]+\/tarifs?$)/iu', $request, $matches)) {
    die('{"result": "error"}');
}

// Загружаем и используем конфиг только в том случае, если запрос был правильным
require_once 'db_cfg.php';
$link = mysqli_connect(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

// Разбиваем запрос на массив
$uri = explode('/', $matches[1]);

// Получаем тарифы
$query = 'SELECT `id`, `title`, `price`, `pay_period`, `speed`, `pay_period`, `link` FROM `tarifs` WHERE tarifs.tarif_group_id = (SELECT `tarif_group_id` FROM `tarifs` WHERE tarifs.id = (SELECT services.tarif_id FROM services WHERE services.user_id = ' . $uri[1] . ' AND services.ID = ' . $uri[3] . '))';
$result = mysqli_query($link, $query);

// Если ничего не найдено, то возвращаем ошибку
if (mysqli_num_rows($result) === 0 || !$result) {
    die('{"result": "error"}');
}

// Собираем тарифы в один массив
$tarifs = [];
while ($row = mysqli_fetch_assoc($result)) {
    $tarifs[] = $row;
}

// Пристваиваем тарифам новую дату оплаты и указываем цену без копеек
foreach ($tarifs as $key => $value) {
    $tarifs[$key]['price'] = number_format($tarifs[$key]['price'], 0, '', '');
    $tarifs[$key]['new_payday'] = strtotime('+' . $tarifs[$key]['pay_period'] . ' month') . date('O');
}

// Если запрос GET
if ($uri[4] === 'tarifs' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $return = [];

    $return['result'] = 'ok';
    $return['tarifs']['title'] = $tarifs[0]['title'];
    $return['tarifs']['link'] = $tarifs[0]['link'];
    $return['tarifs']['speed'] = $tarifs[0]['speed'];
    $return['tarifs']['tarifs'] = $tarifs;

    echo json_encode($return);
} // Если запрос PUT
else if ($uri[4] === 'tarif' && $_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Зибираем запрос PUT
    $put = file_get_contents('php://input');
    $data = json_decode($put, true);

    // Если не указан новый ID тарифа
    if (empty($data['tarif_id'])) {
        die('{"result": "error"}');
    }

    // Ищем тариф среди выбранного списка
    foreach ($tarifs as $tarif) {
        if ($tarif['id'] == $data['tarif_id']) {
            $setTarif = $tarif;
        }
    }

    // Устанавливаем новый тариф пользователя
    $query = 'UPDATE `services` SET `tarif_id` = ' . $setTarif['id'] . ', `payday` = \'' . date('Y-m-d', strtotime('+' . $setTarif['pay_period'] . ' month')) . '\' WHERE `user_id` = ' . $uri[1] . ' AND `id` = ' . $uri[3];
    $result = mysqli_query($link, $query);

    if (!$result) {
        die('{"result": "error"}');
    } else {
        echo '{"result": "ok"}';
    }
}
