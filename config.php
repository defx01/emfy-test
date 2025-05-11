<?php

// Этот файл вместо единой точки входа, хелперов, конфигов и тд

/*ini_set('display_errors', true);
error_reporting(E_ALL);*/

define('ROOT_PATH', dirname(__FILE__));

const PRIVATE_PATH = ROOT_PATH . '/private';

const LEADS_PATH = PRIVATE_PATH . '/leads';

const CONTACTS_PATH = PRIVATE_PATH . '/contacts';

const TOKEN_FILE = PRIVATE_PATH . '/tokens.json';

function config($name, $default = null): string
{
    $config = [
        'amocrm_client_id' => '744d40a0-983c-4a1b-87e2-aa8c43b18561',
        'amocrm_client_secret' => '9QmZNKBGrwcJjUykK5anKHAIoLCUljWO97t9LhST6zk9KzACMM9MFcoJmDVBdKO7',
        'amocrm_domain' => 'avkrs.amocrm.ru',
        'amocrm_redirect_uri' => 'https://def01.ru/',
    ];
    
    return $config[$name] ?? $default;
}

function logger($data): void
{
    file_put_contents(PRIVATE_PATH . '/error.log', $data . "\n", FILE_APPEND);
}

require_once ROOT_PATH . '/AmoCRM.php';

session_start();