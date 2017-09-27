<?php

/**
 * Biblioteka implementująca BotAPI GG <https://boty.gg.pl>
 * Copyright (C) 2013-2016 Xevin Consulting Limited Marcin Bagiński <marcin.baginski@firma.gg.pl>
 * Modified by KsaR 2016-2017 <https://github.com/KsaR99/>
 *
 * This library is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
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
 * Autoryzuje połączenie w trakcie tworzenia i wysyła wiadomości do BotMastera.
 */
class PushConnection
{
    /**
     * Obiekt autoryzacji
     *
     * Typ BotAPIAuthorization
     */
    public static $BOTAPI_LOGIN = null;
    public static $BOTAPI_PASSWORD = null;
    static $lastGg;
    static $lastAuthorization;
    private $authorization;
    private $gg;

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
     * Konstruktor PushConnection - przeprowadza autoryzację
     *
     * @param int $botGGNumber numer GG bota
     * @param string $userName login
     * @param string $password hasło
     */
    public function __construct($botGGNumber = null, $userName = null, $password = null)
    {
        if (empty(self::$lastAuthorization) || !self::$lastAuthorization->isAuthorized()
        || ($botGGNumber !== self::$lastGg && $botGGNumber !== null)) {
            if ($userName === null && self::$BOTAPI_LOGIN !== null) {
                $userName = self::$BOTAPI_LOGIN;
            }

            if ($password === null && self::$BOTAPI_PASSWORD !== null) {
                $password = self::$BOTAPI_PASSWORD;
            }

            self::$lastGg = $botGGNumber;
            self::$lastAuthorization = new BotAPIAuthorization(self::$lastGg, $userName, $password);
        }

        $this->gg = self::$lastGg;
        $this->authorization = self::$lastAuthorization;
    }

    /**
     * Wysyła wiadomość (obiekt lub tablicę obiektów MessageBuilder) do BotMastera.
     *
     * @param array|MessageBuilder $message obiekt lub tablica obiektów MessageBuilder
     */
    public function push($messages)
    {
        if (!$this->authorization->isAuthorized()) {
            return false;
        }

        if (!is_array($messages)) {
            $messages = [$messages];
        }

        $data = $this->authorization->getServerAndToken();

        foreach ($messages as $message) {
            $ch = $this->getSingleCurlHandle();

            curl_setopt_array($ch, [
                CURLOPT_URL => 'https://'.$data['server'].'/sendMessage/'.$this->gg,
                CURLOPT_POSTFIELDS => 'msg='.urlencode($message->getProtocolMessage())
                                    . '&to='.implode(',', (array)$message->recipientNumbers),
                CURLOPT_HEADER => false,
                CURLOPT_HTTPHEADER => [
                    'BotApi-Version: '.MessageBuilder::BOTAPI_VERSION,
                    'Token: '.$data['token']
            ]]);

            $r = curl_exec($ch);

            curl_close($ch);

            if (strpos($r, '<status>0</status>') === false) {
                throw new UnableToSendMessageException('Nie udało się wysłać wiadomości.');
            }
        }
    }

    /**
     * Ustawia opis botowi.
     *
     * @param string $descr Treść opisu
     * @param string $status Typ opisu
     */
    public function setStatus($descr, $status = '')
    {
        if (!$this->authorization->isAuthorized()) {
            return;
        }

        switch ($status) {
            case self::STATUS_AWAY: $h = empty($descr) ? 3 : 5; break;
            case self::STATUS_FFC: $h = empty($descr) ? 23 : 24; break;
            case self::STATUS_BACK: $h = empty($descr) ? 2 : 4; break;
            case self::STATUS_DND: $h = empty($desrc) ? 33 : 34; break;
            case self::STATUS_INVISIBLE: $h = empty($descr) ? 20 : 22; break;
            default: $h = 0;
        }

        $data = $this->authorization->getServerAndToken();
        $ch = $this->getSingleCurlHandle();

        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://'.$data['server'].'/setStatus/'.$this->gg,
            CURLOPT_POSTFIELDS => 'status='.$h.'&desc='.urlencode($descr),
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => [
                'BotApi-Version: '.MessageBuilder::BOTAPI_VERSION,
                'Token: '.$data['token']
            ]
        ]);

        $res = curl_exec($ch);

        curl_close($ch);

        if (strpos($res, '<status>0</status>') === false) {
            throw new UnableToSetStatusExteption('Niepowodzenie ustawiania statusu.');
        }
    }

    /**
     * Tworzy i zwraca uchwyt do nowego żądania cURL
     *
     * @return resource cURL handle
     */
    private function getSingleCurlHandle()
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FAILONERROR => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_BINARYTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HEADER => true,
            CURLOPT_VERBOSE => self::CURL_VERBOSE
        ]);

        return $ch;
    }

    /**
     * Tworzy i zwraca uchwyt do nowego żądania cURL
     */
    private function imageCurl($type, $post)
    {
        $ch = $this->getSingleCurlHandle();

        curl_setopt_array($ch, [
            CURLOPT_URL => "https://botapi.gadu-gadu.pl/botmaster/{$type}Image/{$this->gg}",
            CURLOPT_POSTFIELDS => $post,
            CURLOPT_HTTPHEADER => [
                'BotApi-Version: '.MessageBuilder::BOTAPI_VERSION,
                'Token: '.$this->authorization->getServerAndToken()['token'],
                'Expect: '
            ]
        ]);

        $r = curl_exec($ch);

        curl_close($ch);

        return $r;
    }

    /**
     * Wysyła obrazek do Botmastera
     */
    public function putImage($data)
    {
        return ($this->authorization->isAuthorized()) ?
          strpos($this->imageCurl('put', $data), '<status>0</status>') !== false :
          false;
    }

    /**
     * Pobiera obrazek z Botmastera
     */
    public function getImage($hash)
    {
        return ($this->authorization->isAuthorized()) ?
          explode("\r\n\r\n", $this->imageCurl('get', 'hash='.$hash), 2)[1] :
          false;
    }

    /**
     * Sprawdza czy Botmaster ma obrazek
     */
    public function existsImage($hash)
    {
        return ($this->authorization->isAuthorized()) ?
          strpos($this->imageCurl('exists', 'hash='.$hash), '<status>0</status>') !== false :
          false;
    }

    /**
     * Sprawdza, czy numer jest botem
     */
    public function isBot($ggid)
    {
        if (!$this->authorization->isAuthorized()) {
            return false;
        }

        $ch = $this->getSingleCurlHandle();

        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://botapi.gadu-gadu.pl/botmaster/isBot/'.$this->gg,
            CURLOPT_POSTFIELDS => 'check_ggid='.$ggid,
            CURLOPT_HEADER => false,
            CURLOPT_HTTPHEADER => [
                'BotApi-Version: '.MessageBuilder::BOTAPI_VERSION,
                'Token: '.$this->authorization->getServerAndToken()['token']
            ]
        ]);

        $r = curl_exec($ch);
        curl_close($ch);

        return strpos($r, '<status>1</status>') !== false;
    }
}
