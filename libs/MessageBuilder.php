<?php

/**
 * Biblioteka implementująca BotAPI GG <https://boty.gg.pl>
 * Wymagane: PHP 5.6+, php-cURL.
 *
 * Copyright (C) 2013-2016 Xevin Consulting Limited Marcin Bagiński <marcin.baginski@firma.gg.pl>
 * Modified by KsaR 2016-2017 <https://github.com/KsaR99/>
 *
 * Ten plik jest częścią "Biblioteki BotAPI-GG"
 *
 * "Biblioteka BotAPI-GG" jest wolnym oprogramowaniem: możesz go rozprowadzać dalej
 * i/lub modyfikować na warunkach Powszechnej Licencji Publicznej GNU,
 * wydanej przez Fundację Wolnego Oprogramowania - według wersji 3 tej
 * Licencji lub (według twojego wyboru) którejś z późniejszych wersji.
 *
 * "Biblioteka BotAPI-GG" rozpowszechniany jest z nadzieją, iż będzie on
 * użyteczny - jednak BEZ JAKIEJKOLWIEK GWARANCJI, nawet domyślnej
 * gwarancji PRZYDATNOŚCI HANDLOWEJ albo PRZYDATNOŚCI DO OKREŚLONYCH
 * ZASTOSOWAŃ. W celu uzyskania bliższych informacji sięgnij do Powszechnej Licencji Publicznej GNU.
 *
 * Z pewnością wraz z "Biblioteką BotAPI-GG" otrzymałeś też egzemplarz
 * Powszechnej Licencji Publicznej GNU (GNU General Public License).
 * Jeśli nie - zobacz <http://www.gnu.org/licenses/>
 */

/**
 * @brief Reprezentacja wiadomości
 */
class MessageBuilder
{
    /**
     * Lista odbiorców ( numery GG )
     */
    public $recipientNumbers = null;

    private $html = '';
    private $text = '';
    private $format = null;

    const BOTAPI_VERSION = 'GGBotAPI-3.1;PHP-'.PHP_VERSION;
    const IMG_FILE = true;
    const IMG_RAW = false;

    /**
     * Konstruktor MessageBuilder
     */
    public function __construct()
    {
    }

    /**
     * Czyści całą wiadomość
     */
    public function clear()
    {
        $this->recipientNumbers = null;

        $this->html = '';
        $this->text = '';
        $this->format = null;
    }

    /**
     * Dodaje tekst do wiadomości
     *
     * @param string $text Tekst do wysłania
     *
     * @return MessageBuilder this
     */
    public function addText($text)
    {
        $text = str_replace(["\n", "\r\r"], ["\r\n", "\r"], $text);
        $html = str_replace("\r\n", '<br>', htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8'));

        if ($this->format !== null) {
            $this->text .= $text;
        }

        $this->html .= $html;

        return $this;
    }

    /**
     * Dodaje tekst do wiadomości
     *
     * @param string $html Tekst do wysłania w HTMLu
     *
     * @return MessageBuilder this
     */
    public function addRawHtml($html)
    {
        $this->html .= $html;

        return $this;
    }

    /**
     * Ustawia tekst do wiadomości
     *
     * @param string $html Tekst do wysłania w HTMLu
     *
     * @return MessageBuilder this
     */
    public function setRawHtml($html)
    {
        $this->html = $html;

        return $this;
    }

    /**
     * Ustawia tekst wiadomości alternatywnej
     *
     * @param string $message Tekst do wysłania dla GG 7.7 i starszych
     *
     * @return MessageBuilder this
     */
    public function setAlternativeText($message)
    {
        $this->format = null;
        $this->text = $message;

        return $this;
    }

    /**
     * Dodaje obraz do wiadomości
     *
     * @param string $pathOrContent Ścieżka do pliku graficznego/zawartość
     * @param bool $isPath Wskazuje czy obrazek/ścieżka do obrazka
     * 
     * @return MessageBuilder this
     */
    public function addImage($pathOrContent, $isPath = self::IMG_FILE)
    {
        $p = new PushConnection;
        $p->authorize();

        if (!PushConnection::$authorization->isAuthorized()) {
            throw new MessageBuilderException("Użyj 'PushConnection' przed użyciem ".__METHOD__);
        }

        if ($isPath) {
            $content = file_get_contents($pathOrContent);
        } else {
            $content =& $pathOrContent;
        }

        $crc = crc32($content);
        $length = strlen($content);
        $hash = sprintf('%08x%08x', $crc, $length);

        if (!$p->existsImage($hash) && !$p->putImage($content)) {
            throw new UnableToSendImageException('Nie udało się wysłać obrazka');
        }

        $this
            ->addRawHtml('<img name="'.$hash.'">')
            ->format .= pack('vCCCVV', strlen($this->text), 0x80, 0x09, 0x01, $length, $crc);

        return $this;
    }

    /**
     * Ustawia odbiorców wiadomości
     *
     * @param int|string|array $recipientNumbers GG odbiorców
     *
     * @return MessageBuilder this
     */
    public function setRecipients($recipientNumbers)
    {
        $this->recipientNumbers = (array)$recipientNumbers;

        return $this;
    }

    /**
     * Tworzy sformatowaną wiadomość do wysłania do BotMastera
     */
    public function getProtocolMessage()
    {
        $formatLen = strlen($this->format);

        return pack('VVVV',
            strlen($this->html)+1,
            strlen($this->text)+1,
            0,
            ($formatLen ? $formatLen+3 : 0))
            .  $this->html."\0$this->text\0"
            . ($formatLen ? pack('Cv', 0x02, $formatLen).$this->format : '');
    }

    /**
     * Zwraca sformatowaną wiadomość do wysłania do BotMastera
     */
    public function reply()
    {
        if ($this->recipientNumbers !== null) {
            header('To: '.implode(',', $this->recipientNumbers));
        }

        header('BotApi-Version: '.self::BOTAPI_VERSION);

        echo $this->getProtocolMessage();
    }
}
