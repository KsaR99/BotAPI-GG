<?php

/**
 * Pomocnicza klasa do autoryzacji przez HTTP
 */
class BotAPIAuthorization
{
    private $data;
    private $isValid;

    /**
     * @return bool true jeśli autoryzacja przebiegła prawidłowo
     */
    public function isAuthorized()
    {
        return $this->isValid;
    }

    public function __construct($gg, $email, $pass)
    {
        $this->isValid = $this->authorize($gg, $email, $pass);
    }

    private function authorize($gg, $email, $pass)
    {
        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://botapi.gadu-gadu.pl/botmaster/getToken/'.$gg,
            CURLOPT_USERPWD => $email.':'.$pass,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_VERBOSE => PushConnection::CURL_VERBOSE,
            CURLOPT_HTTPHEADER => ['BotApi-Version: '.MessageBuilder::BOTAPI_VERSION],
        ]);

        $xml = curl_exec($ch);

        curl_close($ch);

        if (preg_match('~<token>(.+?)</token><server>(.+?)</server>~', $xml, $data)) {
            $this->data = [
                'token' => $data[1],
                'server' => $data[2]
            ];

            return true;
        }

        return false;
    }

    /**
     * Zwraca aktywny token/adres BotMastera
     *
     * @return array
     */
    public function getData(...$args)
    {
        $data = [];

        foreach ($args as $arg) {
            $data[$arg] = $this->data[$arg];
        }

        return $data;
    }
}
