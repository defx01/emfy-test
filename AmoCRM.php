<?php

class AmoCRM
{
    private string $domain;
    private string $client_id;
    private string $client_secret;
    private string $redirect_uri;
    private string $token_url;
    
    public function __construct()
    {
        $this->domain = config('amocrm_domain');
        $this->client_id = config('amocrm_client_id');
        $this->client_secret = config('amocrm_client_secret');
        $this->redirect_uri = config('amocrm_redirect_uri');
        $this->token_url = "https://{$this->domain}/oauth2/access_token";
    }
    
    public function exchangeAuthorizationCode(string $code): array
    {
        $data = [
            'grant_type'    => 'authorization_code',
            'client_id'     => $this->client_id,
            'client_secret' => $this->client_secret,
            'code'          => $code,
            'redirect_uri'  => $this->redirect_uri,
        ];
        
        $response = $this->request($this->token_url, $data, ['Content-Type: application/x-www-form-urlencoded']);
        
        if (isset($response['access_token'])) {
            $response['created_at'] = time();
            file_put_contents(TOKEN_FILE, json_encode($response));
            return $response;
        }
        
        throw new Exception('Ошибка при получении токена: ' . json_encode($response));
    }
    
    private function request(string $url, array $data, array $headers = [], string $method = 'POST'): array
    {
        if (strtoupper($method) == 'GET') {
            $ch = curl_init($url . '?' . http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        } else {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            $isJson = in_array('Content-Type: application/json', $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $isJson ? json_encode($data) : http_build_query($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL error: $error");
        }
        
        return json_decode($response, true);
    }
    
    public function getAccessToken(): string
    {
        if (!file_exists(TOKEN_FILE)) {
            throw new Exception("Token file not found.");
        }
        
        $tokens = json_decode(file_get_contents(TOKEN_FILE), true);
        $expiresAt = $tokens['created_at'] + $tokens['expires_in'];
        
        if (time() >= $expiresAt) {
            $tokens = $this->refreshToken($tokens['refresh_token']);
            file_put_contents(TOKEN_FILE, json_encode($tokens));
        }
        
        return $tokens['access_token'];
    }
    
    private function refreshToken(string $refreshToken): array
    {
        $data = [
            'client_id'     => $this->client_id,
            'client_secret' => $this->client_secret,
            'grant_type'    => 'refresh_token',
            'refresh_token' => $refreshToken,
            'redirect_uri'  => $this->redirect_uri,
        ];
        
        $response = $this->request($this->token_url, $data, ['Content-Type: application/x-www-form-urlencoded']);
        if (isset($response['access_token'])) {
            $response['created_at'] = time();
            return $response;
        }
        
        throw new Exception('Не удалось обновить токен: ' . json_encode($response));
    }
    
    public function getUserNameById(int $userId): ?string
    {
        $url = "https://{$this->domain}/api/v4/users/{$userId}";
        
        $headers = [
            'Authorization: Bearer ' . $this->getAccessToken(),
            'Content-Type: application/json',
        ];
        
        $user = $this->request($url, [], $headers, 'GET');
        
        return $user['name'] ?? null;
    }
    
    public function saveLeadFile(array $lead): void
    {
        $filename = $lead['id'] . '.json';
        file_put_contents(LEADS_PATH . "/$filename", json_encode($lead));
    }
    
    public function saveContactFile(array $contact): void
    {
        $filename = $contact['id'] . '.json';
        file_put_contents(CONTACTS_PATH . "/$filename", json_encode($contact));
    }
    
    public function addNoteForLeadCreation(array $lead): bool
    {
        $this->saveLeadFile($lead);
        
        $leadName = $lead['name'];
        $responsible = $this->getUserNameById($lead['responsible_user_id']);
        $createdAt = date('Y-m-d H:i:s', $lead['created_at']);
        
        $text = "Карточка создана \n";
        $text .= "Название: {$leadName}\n";
        $text .= "Ответственный: {$responsible}\n";
        $text .= "Время создания: {$createdAt}";
        
        return $this->addNoteToLead($lead['id'], $text);
    }
    
    public function addNoteForLeadUpdate(array $lead): bool
    {
        $old_values = json_decode(file_get_contents(LEADS_PATH . "/{$lead['id']}.json"), true);
        unset($old_values['last_modified']);
        
        $fieldsMap = $this->getFieldsMap();
        
        $text = "Карточка изменена, было: \n";
        foreach ($old_values as $k => $v) {
        	if (array_key_exists($k, $lead) && $v !== $lead[$k]) {
                if ($k == 'updated_at') {
                	$updatedAt = date('Y-m-d H:i:s', $lead['updated_at']);
                    $text .= "{$fieldsMap[$k]}: $updatedAt \n";
                } else {
                    $text .= "{$fieldsMap[$k]}: {$old_values[$k]} \n";
                }
        	}
        }
    
        $this->saveLeadFile($lead);
    
        return $this->addNoteToLead($lead['id'], $text);
    }
    
    public function addNoteForContactCreation(array $contact): bool
    {
        $this->saveContactFile($contact);
        
        $contactName = $contact['name'];
        $responsible = $this->getUserNameById($contact['responsible_user_id']);
        $createdAt = date('Y-m-d H:i:s', $contact['created_at']);
    
        $text = "Контакт создан \n";
        $text .= "Имя: {$contactName}\n";
        $text .= "Ответственный: {$responsible}\n";
        $text .= "Время создания: {$createdAt}";
    
        return $this->addNoteToContact($contact['id'], $text);
    }
    
    public function addNoteForContactUpdate(array $contact): bool
    {
        $old_values = json_decode(file_get_contents(CONTACTS_PATH . "/{$contact['id']}.json"), true);
        unset($old_values['last_modified']);
        
        $fieldsMap = $this->getFieldsMap();
        
        $text = "Контакт изменен, было: \n";
        foreach ($old_values as $k => $v) {
            if (array_key_exists($k, $contact) && $v !== $contact[$k]) {
                if ($k == 'updated_at') {
                    $updatedAt = date('Y-m-d H:i:s', $contact['updated_at']);
                    $text .= "{$fieldsMap[$k]}: $updatedAt \n";
                } else {
                    $text .= "{$fieldsMap[$k]}: {$old_values[$k]} \n";
                }
            }
        }
        
        $this->saveContactFile($contact);
        
        return $this->addNoteToContact($contact['id'], $text);
    }
    
    public function addNote(string $url, string $text): bool
    {
        $data = [
            [
                'note_type' => 'common',
                'params'    => ['text' => $text]
            ]
        ];
        
        $headers = [
            'Authorization: Bearer ' . $this->getAccessToken(),
            'Content-Type: application/json'
        ];
        
        $response = $this->request($url, $data, $headers);
        
        return isset($response['_embedded']['notes']);
    }
    
    public function addNoteToLead(int $leadId, string $text): bool
    {
        $url = "https://{$this->domain}/api/v4/leads/{$leadId}/notes";
        
        return $this->addNote($url, $text);
    }
    
    public function addNoteToContact(int $contactId, string $text): bool
    {
        $url = "https://{$this->domain}/api/v4/contacts/{$contactId}/notes";
        
        return $this->addNote($url, $text);
    }
    
    public function getFieldsMap()
    {
        // Здесь должны быть запросы к /api/v4/{$entities}/custom_fields и кэширование карты полей
        $fieldsMap = [
            'name' => 'Название',
            'responsible_user_id' => 'Ответственный',
            'created_at' => 'Время создания',
            'updated_at' => 'Время изменения',
        ];
        
        return $fieldsMap;
    }
}