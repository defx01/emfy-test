<?php

require_once 'config.php';

if (isset($_GET['code'], $_GET['state']))
{
    if ($_GET['state'] !== $_SESSION['oauth2_state']) {
        exit('Ошибка безопасности: state не совпадает.');
    }
    
    $amo = new AmoCRM();
    
    try {
        $tokens = $amo->exchangeAuthorizationCode($_GET['code']);
        echo 'Токены успешно получены и сохранены.';
    } catch (\Throwable $e) {
        echo 'Ошибка: ' . $e->getMessage();
    }
}
else
{
    $_SESSION['oauth2_state'] = bin2hex(random_bytes(16));
    echo '<div><script
            class="amocrm_oauth"
            charset="utf-8"
            data-client-id="' . config('amocrm_client_id') . '"
            data-title="Установить интеграцию"
            data-compact="false"
            data-class-name="className"
            data-color="default"
            data-state="' . $_SESSION['oauth2_state'] . '"
            src="https://www.amocrm.ru/auth/button.min.js"
          ></script></div>';
}