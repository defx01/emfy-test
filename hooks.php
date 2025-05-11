<?php

require_once 'config.php';

$amo = new AmoCRM();

// для отладки
logger(json_encode($_POST));

// Создание сделки
if (isset($_POST['leads']['add'])) {
	$amo->addNoteForLeadCreation($_POST['leads']['add'][0]);
}

// Создание контакта
if (isset($_POST['contacts']['add'])) {
    $amo->addNoteForContactCreation($_POST['contacts']['add'][0]);
}

// Обновление сделки
if (isset($_POST['leads']['update'])) {
    $amo->addNoteForLeadUpdate($_POST['leads']['update'][0]);
}

// Обновление контакта
if (isset($_POST['contacts']['update'])) {
    $amo->addNoteForContactUpdate($_POST['contacts']['update'][0]);
}