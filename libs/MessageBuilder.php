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
 * @brief Reprezentacja wiadomości
 */
class MessageBuilder
{
    /**
     * Lista odbiorców ( numery GG )
     */
    public $recipientNumbers = [];

    public $html = '';
    public $text = '';
    public $format = null;

    const BOTAPI_VERSION = 'GGBotAPI-3.0;PHP-'.PHP_VERSION;
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
        $this->recipientNumbers = [];

        $this->html = '';
        $this->text = '';
        $this->format = null;
    }

    /**
     * Dodaje tekst do wiadomości
     *
     * @param string $text tekst do wysłania
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
     * @param string $html tekst do wysłania w HTMLu
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
     * @param string $html tekst do wysłania w HTMLu
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
     * @param string $message tekst do wysłania dla GG 7.7 i starszych
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
     * @param string $path ścieżka do pliku graficznego
     *
     * @return MessageBuilder this
     */
    public function addImage($path, $isFile = self::IMG_FILE)
    {
        if (empty(PushConnection::$lastAuthorization)) {
            throw new MessageBuilderException('Użyj klasy PushConnection');
        }

        $content = '';

        if ($isFile) {
            $content .= file_get_contents($path);
        }

        $crc = crc32($content);
        $contentLength = strlen($content);
        $hash = sprintf('%08x%08x', $crc, $contentLength);

        $p = new PushConnection;

        if (!$p->existsImage($hash) && !$p->putImage($content)) {
            throw new UnableToSendImageException('Nie udało się wysłać obrazka');
        }

        $this->format .= pack('vCCCVV', strlen($this->text), 0x80, 0x09, 0x01, $contentLength, $crc);
        $this->addRawHtml('<img name="'.$hash.'">');

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

        return pack('VVVV', strlen($this->html)+1, strlen($this->text)+1, 0,
          (empty($this->format) ? 0 : $formatLen+3)).$this->html."\0$this->text\0".
          (empty($this->format) ? '' : pack('Cv', 0x02, $formatLen).$this->format
        );
    }

    /**
     * Zwraca na wyjście sformatowaną wiadomość do wysłania do BotMastera
     */
    public function reply()
    {
        if (!empty($this->recipientNumbers)) {
            header('To: '.implode(',', $this->recipientNumbers));
        }

        header('BotApi-Version: '.self::BOTAPI_VERSION);

        echo $this->getProtocolMessage();
    }
}

/**
 * Baza dla wszystkich wyjątków.
 */
class MessageBuilderException extends Exception {}

/**
 * Wyjątki.
 */
class UnableToSendImageException extends MessageBuilderException {}
