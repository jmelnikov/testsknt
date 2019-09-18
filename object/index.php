<?php
require_once 'CIndex.php';

header('Content-Type: application/json');

$ci = new CIndex();
if(!$ci->router()) {
    die('{"result": "error"}');
}

if(!$ci->prepare_data()) {
    die('{"result": "error"}');
}

if($_SERVER['REQUEST_METHOD'] === 'GET') {
    echo $ci->get_user_tarifs();
    exit();
}

if($_SERVER['REQUEST_METHOD'] === 'PUT') {
    if(!$ci->set_user_tarif()) {
        die('{"result": "error"}');
    } else {
        echo '{"result": "ok"}';
        exit();
    }
}