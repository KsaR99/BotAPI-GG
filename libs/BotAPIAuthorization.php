<?php

/**
 * Pomocnicza klasa do autoryzacji przez HTTP
 */
class BotAPIAuthorization
{
    private $data = [
        'token' => null,
        'server' => null,
        'port' => null
    ];

    private $isValid;

    /**
     * @return bool true jeśli autoryzacja przebiegła prawidłowo
     */
    public function isAuthorized()
    {
        return $this->isValid;
    }

    public function __construct($ggid, $userName, $password)
    {
        $this->isValid = $this->getData($ggid, $userName, $password);
    }

    private function getData($ggid, $userName, $password)
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://botapi.gadu-gadu.pl/botmaster/getToken/'.$ggid,
            CURLOPT_USERPWD => $userName.':'.$password,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_VERBOSE => PushConnection::CURL_VERBOSE,
            CURLOPT_HTTPHEADER => ['BotApi-Version: '.MessageBuilder::BOTAPI_VERSION],
        ]);

        $xml = curl_exec($ch);

        curl_close($ch);

        $regexp = '~<token>(.+?)</token><server>(.+?)</server><port>(\d+)</port>~';

        if (!preg_match($regexp, $xml, $out)) {
            return false;
        }

        $this->data = [
            'token' => $out[1],
            'server' => $out[2],
            'port' => $out[3]
        ];

        return true;
    }

    /**
     * Pobiera aktywny token, port i adres BotMastera
     *
     * @return array
     */
    public function getServerAndToken()
    {
        return $this->data;
    }
}