<?php
/*******
Biblioteka implementująca BotAPI GG http://boty.gg.pl/
Copyright (C) 2013 GG Network S.A. Marcin Bagiński <marcin.baginski@firma.gg.pl>

This library is free software: you can redistribute it and/or modify
it under the terms of the GNU Lesser General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This library is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU Lesser General Public License for more details.

You should have received a copy of the GNU Lesser General Public License
along with this program. If not, see<http://www.gnu.org/licenses/>.
*******/

require_once(dirname(__FILE__).'/MessageBuilder.php');

define('CURL_VERBOSE', false); // zmienić na true, jeśli chce się uzyskać dodatkowe informacje debugowe


define('STATUS_AWAY', 'away');
define('STATUS_FFC', 'ffc');
define('STATUS_BACK', 'back');
define('STATUS_DND', 'dnd');
define('STATUS_INVISIBLE', 'invisible');


PushConnection::$BOTAPI_LOGIN='';
PushConnection::$BOTAPI_PASSWORD='';
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
	public static $BOTAPI_LOGIN=NULL;
	public static $BOTAPI_PASSWORD=NULL;
	static $lastGg;
	static $lastAuthorization;
	private $authorization;
	private $gg;

	/**
	 * Konstruktor PushConnection - przeprowadza autoryzację
	 *
	 * @param int $botGGNumber numer GG bota
	 * @param string $userName login
	 * @param string $password hasło
	 */
	public function __construct($botGGNumber=NULL, $userName=NULL, $password=NULL)
	{
		if (empty(self::$lastAuthorization) || !self::$lastAuthorization->isAuthorized() || ($botGGNumber!=self::$lastGg && ($botGGNumber!==NULL))) {
			if ($userName===NULL && self::$BOTAPI_LOGIN!==NULL) $userName=self::$BOTAPI_LOGIN;
			if ($password===NULL && self::$BOTAPI_PASSWORD!==NULL) $password=self::$BOTAPI_PASSWORD;

			self::$lastGg=$botGGNumber;
			self::$lastAuthorization=new BotAPIAuthorization(self::$lastGg, $userName, $password);
		}

		$this->gg=self::$lastGg;
		$this->authorization=self::$lastAuthorization;
	}

	/**
	 * Wysyła wiadomość (obiekt lub tablicę obiektów MessageBuilder) do BotMastera.
	 *
	 * @param array,MessageBuilder $message obiekt lub tablica obiektów MessageBuilder
	 */
	public function push($messages)
	{
		if (!$this->authorization->isAuthorized())
			return false;


		$count=0;
		if (!is_array($messages))
			$messages=array($messages);


		$data=$this->authorization->getServerAndToken();
		foreach ($messages as $message) {
			$ch=$this->getSingleCurlHandle();

			curl_setopt($ch, CURLOPT_HTTPHEADER, array('BotApi-Version: '.BOTAPI_VERSION, 'Token: '.$data['token'], 'Send-to-offline: '.(($message->sendToOffline) ? '1' : '0')));
			curl_setopt($ch, CURLOPT_URL, 'https://'.$data['server'].'/sendMessage/'.$this->gg);
			curl_setopt($ch, CURLOPT_POSTFIELDS, 'to='.join(',', $message->recipientNumbers).'&msg='.urlencode($message->getProtocolMessage()));

			$r=curl_exec($ch);
			$s=curl_getinfo($ch);
			curl_close($ch);

			$count+=(strpos($r, '<result><status>0</status></result>')!==false);
		}


		return $count;
	}

	/**
	 * Ustawia opis botowi.
	 *
	 * @param string $statusDescription Treść opisu
	 * @param string $status Typ opisu
	 * @param null $graphic Nieużywane. Zostaje dla kompatybilności
	 */
	public function setStatus($statusDescription, $status='', $graphic=NULL)
	{
		if (!$this->authorization->isAuthorized())
			return false;


		$statusDescription=urlencode($statusDescription);

		switch ($status) {
			case STATUS_AWAY: $h=((empty($statusDescription)) ? 3 : 5); break;
			case STATUS_FFC: $h=((empty($statusDescription)) ? 23 : 24); break;
			case STATUS_BACK: $h=((empty($statusDescription)) ? 2 : 4); break;
			case STATUS_DND: $h=((empty($statusDescription)) ? 33 : 34); break;
			case STATUS_INVISIBLE: $h=((empty($statusDescription)) ? 20 : 22); break;
			default: $h=0; break;
		}


		$data=$this->authorization->getServerAndToken();

		$ch=$this->getSingleCurlHandle();
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('BotApi-Version: '.BOTAPI_VERSION, 'Token: '.$data['token']));
		curl_setopt($ch, CURLOPT_URL, 'https://'.$data['server'].'/setStatus/'.$this->gg);
		curl_setopt($ch, CURLOPT_POSTFIELDS, 'status='.$h.'&desc='.$statusDescription);

		$r=curl_exec($ch);
		curl_close($ch);

		return strpos($r, '<result><status>0</status></result>')!==false;
	}

	/**
	 * Tworzy i zwraca uchwyt do nowego żądania cUrl
	 *
	 * @return $resource cURL handle
	 */
	private function getSingleCurlHandle()
	{
		$data=$this->authorization->getServerAndToken();

		$ch=curl_init();
		curl_setopt_array($ch, array(
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
				CURLOPT_VERBOSE => CURL_VERBOSE
			));

		return $ch;
	}

	/**
	 * Nie ma opisów graficznych. Zostaje dla kompatybilności
	 */
	public function getUserbars()
	{
		trigger_error('Nie ma opisów graficznych', ((defined('E_USER_DEPRECATED')) ? E_USER_DEPRECATED : E_USER_NOTICE));
		return array();
	}

	/**
	 *Tworzy i zwraca uchwyt do nowego żądania cUrl
	 */
	private function imageCurl($type, $post)
	{
		$data=$this->authorization->getServerAndToken();

		$ch=$this->getSingleCurlHandle();
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('BotApi-Version: '.BOTAPI_VERSION, 'Token: '.$data['token'], 'Expect: '));
		curl_setopt($ch, CURLOPT_URL, 'https://botapi.gadu-gadu.pl/botmaster/'.$type.'Image/'.$this->gg);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		$r=curl_exec($ch);
		curl_close($ch);

		return $r;
	}

	/**
	 * Wysyła obrazek do Botmastera
	 */
	public function putImage($data)
	{
		if (!$this->authorization->isAuthorized())
			return false;

		$r=$this->imageCurl('put', $data);
		return strpos($r, '<result><status>0</status><hash>')!==false;
	}

	/**
	 * Pobiera obrazek z Botmastera
	 */
	public function getImage($hash)
	{
		if (!$this->authorization->isAuthorized())
			return false;

		$r=explode("\r\n\r\n", $this->imageCurl('get', 'hash='.$hash), 2);
		return $r[1];
	}

	/**
	 * Sprawdza czy Botmaster ma obrazek
	 */
	public function existsImage($hash)
	{
		if (!$this->authorization->isAuthorized())
			return false;

		$r=$this->imageCurl('exists', 'hash='.$hash);
		return strpos($r, '<result><status>0</status><hash>')!==false;
	}

	/**
	 * Sprawdza, czy numer jest botem
	 */
	public function isBot($ggid)
	{
		if (!$this->authorization->isAuthorized())
			return false;

		$data=$this->authorization->getServerAndToken();

		$ch=$this->getSingleCurlHandle();
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('BotApi-Version: '.BOTAPI_VERSION, 'Token: '.$data['token']));
		curl_setopt($ch, CURLOPT_URL, 'https://botapi.gadu-gadu.pl/botmaster/isBot/'.$this->gg);
		curl_setopt($ch, CURLOPT_POSTFIELDS, 'check_ggid='.$ggid);

		$r=curl_exec($ch);
		curl_close($ch);

		return strpos($r, '<result><status>1</status></result>')!==false;
	}
}

/**
 * Pomocnicza klasa do autoryzacji przez HTTP
 */
class BotAPIAuthorization
{
	private $data=array(
			'token' => NULL,
			'server' => NULL,
			'port' => NULL
		);
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
		$this->isValid=$this->getData($ggid, $userName, $password);
	}

	private function getData($ggid, $userName, $password)
	{
		$ch=curl_init();
		curl_setopt_array($ch, array(
				CURLOPT_URL => 'https://botapi.gadu-gadu.pl/botmaster/getToken/'.$ggid,
				CURLOPT_USERPWD => $userName.':'.$password,
				CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_VERBOSE => CURL_VERBOSE,
				CURLOPT_HTTPHEADER => array('BotApi-Version: '.BOTAPI_VERSION),
			));

		$xml=curl_exec($ch);
		curl_close($ch);

		$match1=preg_match('/<token>(.+?)<\/token>/', $xml, $tmpToken);
		$match2=preg_match('/<server>(.+?)<\/server>/', $xml, $tmpServer);
		$match3=preg_match('/<port>(.+?)<\/port>/', $xml, $tmpPort);

		if (!($match1 && $match2 && $match3))
			return false;

		$this->data['token']=$tmpToken[1];
		$this->data['server']=$tmpServer[1];
		$this->data['port']=$tmpPort[1];

		return true;
	}

	/**
	 * Pobiera aktywny token, port i adres BotMastera
	 *
	 * @return bool false w przypadku błędu
	 */
	public function getServerAndToken()
	{
		return $this->data;
	}
}
