<?php

/**
 * Biblioteka implementująca BotAPI GG <https://boty.gg.pl>
 * Wymagane: PHP 5.6+, php-cURL.
 *
 * Copyright (C) 2013-2016 Xevin Consulting Limited Marcin Bagiński <marcin.baginski@firma.gg.pl>
 * Modified by KsaR 2016-2017 <https://github.com/KsaR99/>
 *
 * This library is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License,
 * or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * See the GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>
 */

/**
 * @brief Klasa reprezentująca połączenie PUSH z BotMasterem.
 */
class PushConnection
{
    /**
     * Obiekt autoryzacji
     *
     * Typ BotAPIAuthorization
     */
    public static $authorization;
    private static $authorizationData;

    public static $BOTAPI_LOGIN;
    public static $BOTAPI_PASSWORD;

    /**
     * Statusy GG
     */
    const STATUS_AWAY = 'away';
    const STATUS_FFC = 'ffc';
    const STATUS_BACK = 'back';
    const STATUS_DND = 'dnd';
    const STATUS_INVISIBLE = 'invisible';

    /**
     * Curl debug
     * domyślnie: false
     */
    const CURL_VERBOSE = false;

    /**
     * Konstruktor PushConnection - inicjuje dane autoryzacji
     *
     * @param int $gg Numer GG bota
     * @param string $email Login
     * @param string $pass Hasło
     */
    public function __construct($gg = null, $email = null, $pass = null)
    {
        if (self::$authorizationData === null) {
            self::$authorizationData = [
                'gg' => $gg,
                'email' => ($email !== null && self::$BOTAPI_LOGIN === null) ? $email : self::$BOTAPI_LOGIN,
                'pass' => ($pass !== null && self::$BOTAPI_PASSWORD === null) ? $pass : self::$BOTAPI_PASSWORD
            ];
        }
    }

    /**
     * Autoryzuje bota.
     */
    public function authorize()
    {
        if (self::$authorization === null && self::$authorizationData !== null) {
            self::$authorization = new BotAPIAuthorization(
                self::$authorizationData['gg'],
                self::$authorizationData['email'],
                self::$authorizationData['pass']
            );
        }
    }

    /**
     * Wysyła wiadomość (obiekt lub tablicę obiektów MessageBuilder) do BotMastera.
     *
     * @param array|MessageBuilder $message Obiekt lub tablica obiektów MessageBuilder
     */
    public function push($messages)
    {
        $this->authorize();

        if (!self::$authorization->isAuthorized()) {
            return false;
        }

        if (!is_array($messages)) {
            $messages = [$messages];
        }

        $data = self::$authorization->getData('token', 'server');

        foreach ($messages as $message) {
            $result = $this->executeCurl([
                CURLOPT_URL => 'https://'.$data['server'].'/sendMessage/'.self::$authorizationData['gg'],
                CURLOPT_POSTFIELDS => 'msg='.urlencode($message->getProtocolMessage())
                                    . '&to='.implode(',', (array)$message->recipientNumbers),
                CURLOPT_HTTPHEADER => [
                    'BotApi-Version: '.MessageBuilder::BOTAPI_VERSION,
                    'Token: '.$data['token']
            ]]);

            if (strpos($result, '<status>0</status>') === false) {
                throw new UnableToSendMessageException('Nie udało się wysłać wiadomości.');
            }
        }
    }

    /**
     * Ustawia opis botowi.
     *
     * @param string $description Treść opisu
     * @param string $status Typ opisu
     */
    public function setStatus($description, $status = '')
    {
        $this->authorize();

        if (!self::$authorization->isAuthorized()) {
            return;
        }

        switch ($status) {
            case self::STATUS_AWAY: $h = empty($description) ? 3 : 5; break;
            case self::STATUS_FFC: $h = empty($description) ? 23 : 24; break;
            case self::STATUS_BACK: $h = empty($description) ? 2 : 4; break;
            case self::STATUS_DND: $h = empty($description) ? 33 : 34; break;
            case self::STATUS_INVISIBLE: $h = empty($description) ? 20 : 22; break;
            default: $h = 0;
        }

        $data = self::$authorization->getData('token', 'server');
        $result = $this->executeCurl([
            CURLOPT_URL => 'https://'.$data['server'].'/setStatus/'.self::$authorizationData['gg'],
            CURLOPT_POSTFIELDS => "status=$h&desc=".urlencode($description),
            CURLOPT_HTTPHEADER => [
                'BotApi-Version: '.MessageBuilder::BOTAPI_VERSION,
                'Token: '.$data['token']
            ]
        ]);

        if (strpos($result, '<status>0</status>') === false) {
            throw new UnableToSetStatusExteption('Niepowodzenie ustawiania statusu.');
        }
    }

    /**
     * Wykonuje żądania cURL
     * @param array $opt Opcje cURL
     *
     * @return string
     */
    private function executeCurl($opt)
    {
        $ch = curl_init();

        curl_setopt_array($ch, $opt + [
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FAILONERROR => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HEADER => false,
            CURLOPT_VERBOSE => self::CURL_VERBOSE
        ]);

        $result = curl_exec($ch);

        curl_close($ch);

        return $result;
    }

    /**
     * Tworzy i zwraca uchwyt do nowego żądania cURL
     */
    private function imageCurl($type, $data)
    {
        return $this->executeCurl([
            CURLOPT_URL => "https://botapi.gadu-gadu.pl/botmaster/{$type}Image/".self::$authorizationData['gg'],
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HEADER => true,
            CURLOPT_HTTPHEADER => [
                'BotApi-Version: '.MessageBuilder::BOTAPI_VERSION,
                'Token: '.self::$authorization->getData('token')['token']
            ]
        ]);
    }

    /**
     * Wysyła obrazek do Botmastera
     */
    public function putImage($data)
    {
        $this->authorize();

        return (self::$authorization->isAuthorized())
            ? strpos($this->imageCurl('put', $data), '<status>0</status>') !== false
            : false;
    }

    /**
     * Pobiera obrazek z Botmastera
     */
    public function getImage($hash)
    {
        $this->authorize();

        return (self::$authorization->isAuthorized())
            ? explode("\r\n\r\n", $this->imageCurl('get', 'hash='.$hash), 2)[1]
            : false;
    }

    /**
     * Sprawdza, czy Botmaster ma obrazek
     */
    public function existsImage($hash)
    {
        $this->authorize();

        return (self::$authorization->isAuthorized())
            ? strpos($this->imageCurl('exists', 'hash='.$hash), '<status>0</status>') !== false
            : false;
    }

    /**
     * Sprawdza, czy numer jest botem
     */
    public function isBot($ggid)
    {
        $this->authorize();

        if (!self::$authorization->isAuthorized()) {
            return false;
        }

        $result = $this->executeCurl([
            CURLOPT_URL => 'https://botapi.gadu-gadu.pl/botmaster/isBot/'.self::$authorizationData['gg'],
            CURLOPT_POSTFIELDS => 'check_ggid='.$ggid,
            CURLOPT_HTTPHEADER => [
                'BotApi-Version: '.MessageBuilder::BOTAPI_VERSION,
                'Token: '.self::$authorization->getData('token')['token']
            ]
        ]);

        return strpos($result, '<status>1</status>') !== false;
    }
}
