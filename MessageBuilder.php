<?php
/**
Biblioteka implementująca BotAPI GG <https://boty.gg.pl>
Copyright (C) 2013 GG Network S.A. Marcin Bagiński <marcin.baginski@firma.gg.pl>
Modified by KsaR 2016 <https://github.com/KsaR99/>

This library is free software: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This library is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
See the GNU Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>
**/

define('BOTAPI_VERSION', 'GGBotApi-2.5-PHP');

define('FORMAT_NONE', 0x00);
define('FORMAT_BOLD_TEXT', 0x01);
define('FORMAT_ITALIC_TEXT', 0x02);
define('FORMAT_UNDERLINE_TEXT', 0x04);
define('FORMAT_NEW_LINE', 0x08);

define('IMG_FILE', true);
define('IMG_RAW', false);

require_once __DIR__.'/PushConnection.php';

/**
 * @brief Reprezentacja wiadomości
 */
class MessageBuilder
{
		/**
		 * Tablica numerów GG do których ma trafić wiadomość
		 */
		public $recipientNumbers = [];

		/**
		 * Określa czy wiadomość zostanie wysłana do numerów będących offline, domyślnie true
		 */
		public $sendToOffline = true;

		public $html = '';
		public $text = '';
		public $format = '';

		public $rgb = [0, 0, 0];

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
				$this->sendToOffline = true;

				$this->html = '';
				$this->text = '';
				$this->format = '';

				$this->rgb = [0, 0, 0];
		}

		/**
		 * Dodaje tekst do wiadomości
		 *
		 * @param string $text tekst do wysłania
		 * @param int $formatBits styl wiadomości (FORMAT_*_TEXT), domyślnie brak
		 * @param int $R, $G, $B składowe koloru tekstu w formacie RGB
		 *
		 * @return MessageBuilder this
		 */
		public function addText($text, $formatBits = FORMAT_NONE, $R = 0, $G = 0, $B = 0)
		{
				if ($formatBits & FORMAT_NEW_LINE) {
						$text.= "\r\n";
				}

				$text = str_replace("\r\r", "\r", str_replace("\n", "\r\n", $text));
				$html = str_replace("\r\n", '<br>', htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8'));

				if ($this->format !== null) {
						$rgb = &$this->rgb;

						$this->format.= pack('vC', mb_strlen($this->text, 'UTF-8'),
								($formatBits & FORMAT_BOLD_TEXT) |
								($formatBits & FORMAT_ITALIC_TEXT) |
								($formatBits & FORMAT_UNDERLINE_TEXT) |
								(1 || $R != $rgb[0] || $G != $rgb[1] || $B != $rgb[2]) * 0x08).
						pack('CCC', $R, $G, $B);

						$rgb = [$R, $G, $B];
						$this->text.= $text;
				}

				if ($R || $G || $B) {
						$html = '<span style="color:#'.
								substr('00'.dechex($R), -2).
								substr('00'.dechex($G), -2).
								substr('00'.dechex($B), -2).
								'">'.$html.'</span>';
				}

				if ($formatBits & FORMAT_BOLD_TEXT) {
						$html = '<b>'.$html.'</b>';
				}
				if ($formatBits & FORMAT_ITALIC_TEXT) {
						$html = '<i>'.$html.'</i>';
				}
				if ($formatBits & FORMAT_UNDERLINE_TEXT) {
						$html = '<u>'.$html.'</u>';
				}

				$this->html.= $html;

				return $this;
		}

		/**
		 * Dodaje tekst do wiadomości
		 *
		 * @param string $bbcode tekst do wysłania w formacie BBCode
		 *
		 * @return MessageBuilder this
		 */
		public function addBBcode($bbcode)
		{
				$tagsLength = $start = 0;
				$heap = [];
				$bbcode = str_replace('[br]', "\n", $bbcode);
				$regexp = '~\[(/)?(b|i|u|color)(=#?[0-9a-fA-F]{6})?\]~';

				while (preg_match($regexp, $bbcode, $out, PREG_OFFSET_CAPTURE, $start)) {
						$s = substr($bbcode, $start, $out[0][1]-$start);
						$c = [0, 0, 0];

						if (!empty($s)) {
								$flags = 0;
								$c = [0, 0, 0];

								foreach ($heap as $h) {
										switch ($h[0]) {
												case 'b': $flags|= 0x01; break;
												case 'i': $flags|= 0x02; break;
												case 'u': $flags|= 0x04; break;
												case 'color': $c = $h[1]; break;
										}
								}

								$this->addText($s, $flags, $c[0], $c[1], $c[2]);
						}

						if ($out[1][0] == '') {
								switch ($out[2][0]) {
										case 'b':
										case 'i':
										case 'u':
												$heap[] = [$out[2][0]];
										break;
										case 'color':
												$c = hexdec(substr($out[3][0], -6));
												$c = [($c>>16) & 0xFF, ($c>>8) & 0xFF, ($c>>0) & 0xFF];
												$heap[] = ['color', $c];
										break;
								}
						} else {
								array_pop($heap);
						}

						$tagsLength += $tagLen = strlen($out[0][0]);
						$start = $out[0][1]+$tagLen;
				}

				$s = substr($bbcode, $start);

				if (!empty($s)) {
						$this->addText($s);
				}

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
				$this->html.= $html;

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
		public function addImage($path, $isFile = IMG_FILE)
		{
				if (empty(PushConnection::$lastAuthorization)) {
						throw new Exception('Użyj klasy PushConnection');
				}
				if ($isFile == IMG_FILE) {
						$content = file_get_contents($path);
				}

				$crc = crc32($content);
				$fileLen = strlen($content);
				$hash = sprintf('%08x%08x', $crc, $fileLen);
				$p = new PushConnection;

				if (!$p->existsImage($hash) && !$p->putImage($content)) {
						throw new Exception('Nie udało się wysłać obrazka');
				}

				$this->format.= pack('vCCCVV', strlen($this->text), 0x80, 0x09, 0x01, $fileLen, $crc);
				$this->addRawHtml('<img name="'.$hash.'">');

				return $this;
		}

		/**
		 * Ustawia odbiorców wiadomości
		 *
		 * @param int|string|array $recipientNumbers numer/y GG odbiorców
		 *
		 * @return MessageBuilder this
		 */
		public function setRecipients($recipientNumbers)
		{
				$this->recipientNumbers = (array)$recipientNumbers;

				return $this;
		}

		/**
		 * Ustawia czy dostarczyć do OFFLINE
		 *
		 * @param $sendToOffline bool|int
		 *
		 * @return MessageBuilder this
		 */
		public function setSendToOffline($sendToOffline)
		{
				$this->sendToOffline = $sendToOffline;

				return $this;
		}

		/**
		 * Tworzy sformatowaną wiadomość do wysłania do BotMastera
		 */
		public function getProtocolMessage()
		{
				if (!preg_match('#^<span[^>]*>.+</span>$#s', $this->html, $o) || isset($o[0]) && $o[0] != $this->html) {
						$this->html = '<span style="color:#000000;font-family:'.
						'\'MS Shell Dlg 2\';font-size:9pt">'.$this->html.'</span>';
				}

				return pack('VVVV', strlen($this->html)+1, strlen($this->text)+1, 0,
						(empty($this->format) ? 0 : strlen($this->format)+3)).
						$this->html."\0".$this->text."\0".
						(empty($this->format) ? '' :
								pack('Cv', 0x02, strlen($this->format)).$this->format
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
				if (!$this->sendToOffline) {
						header('Send-to-offline: 0');
				}

				header('BotApi-Version: '.BOTAPI_VERSION);

				echo $this->getProtocolMessage();
		}
}
