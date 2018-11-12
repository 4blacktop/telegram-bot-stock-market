<?php
// http://www.fileformat.info/info/emoji/list.htm
define('BOT_TOKEN', '23641sdghsfgjsPQ_PUJM1dww');
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');

function apiRequestWebhook($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  $parameters["method"] = $method;

  header("Content-Type: application/json");
  echo json_encode($parameters);
  return true;
}

function exec_curl_request($handle) {
  $response = curl_exec($handle);

  if ($response === false) {
    $errno = curl_errno($handle);
    $error = curl_error($handle);
    error_log("Curl returned error $errno: $error\n");
    curl_close($handle);
    return false;
  }

  $http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
  curl_close($handle);

  if ($http_code >= 500) {
    // do not wat to DDOS server if something goes wrong
    sleep(10);
    return false;
  } else if ($http_code != 200) {
    $response = json_decode($response, true);
    error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
    if ($http_code == 401) {
      throw new Exception('Invalid access token provided');
    }
    return false;
  } else {
    $response = json_decode($response, true);
    if (isset($response['description'])) {
      error_log("Request was successfull: {$response['description']}\n");
    }
    $response = $response['result'];
  }

  return $response;
}

function apiRequest($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  foreach ($parameters as $key => &$val) {
    // encoding to JSON array parameters, for example reply_markup
    if (!is_numeric($val) && !is_string($val)) {
      $val = json_encode($val);
    }
  }
  $url = API_URL.$method.'?'.http_build_query($parameters);

  $handle = curl_init($url);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);

  return exec_curl_request($handle);
}

function apiRequestJson($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  $parameters["method"] = $method;

  $handle = curl_init(API_URL);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);
  curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
  curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

  return exec_curl_request($handle);
}

function processMessage($message) {
	// process incoming message
	$message_id = $message['message_id'];
	$chat_id = $message['chat']['id'];
	$read = readPDO($chat_id);
	if ($read) {
		if (isset($message['text'])) {
			// incoming text message
			$text = $message['text'];

			if (strpos($text, "/start") === 0) {
			$data = array(
				'userId' => $chat_id,
				'money' => '100000',
				'messageCount' => '0',
				'tmpMessageCount' => '0',
				'prevPosition' => '2'
			);  
			$start = startPDO($message_id, $chat_id, $data);
			$newTrendPDO = newTrendPDO($message_id, $chat_id, $read);
			
			// } else if ($text === json_decode('"\uD83D\uDD34"') . ' ПОКУПКА ' . json_decode('"\uD83D\uDD34"')) {// если пользователь выбрал ПОКУПКА
			} else if ($text === json_decode('"\u2B06"') . ' ПОКУПКА') {// если пользователь выбрал ПОКУПКА
				if ($read['prevPosition'] == '2') { // если пользователь СЛЕДУЕТ тренду	
					$message = sendMessageTrend($message_id, $chat_id, $read);
				} else { // если пользователь НЕ СЛЕДУЕТ тренду	
					$message = sendMessageCounterTrend($message_id, $chat_id, $read);
				}	
			
// json_decode('"\uD83D\uDD34"') . ' ПОКУПКА ' . json_decode('"\uD83D\uDD34"'),json_decode('"\uD83D\uDD34"') . ' ПРОДАЖА ' . json_decode('"\uD83D\uDD34"')
// json_decode('"\uD83D\uDD34"') . ' ПОКУПКА ' . json_decode('"\uD83D\uDD34"') . ' или ' . json_decode('"\uD83D\uDD34"') . ' ПРОДАЖА ' . json_decode('"\uD83D\uDD34"')
			
			} else if ($text === json_decode('"\u2B07"') . ' ПРОДАЖА') {// если пользователь выбрал ПРОДАЖА
				if ($read['prevPosition'] == '1') { // если пользователь СЛЕДУЕТ тренду	
					$message = sendMessageTrend($message_id, $chat_id, $read);
				} else { // если пользователь НЕ СЛЕДУЕТ тренду	
					$message = sendMessageCounterTrend($message_id, $chat_id, $read);
				}	
				
			} else if (strpos($text, "/stop") === 0) {
			// stop now
			} else {
			// apiRequestWebhook("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "text" => 'Выберите направление сделки',
			apiRequest("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "text" => "Выберите направление торговли c помощью кнопок под чатом:\n" . json_decode('"\u2B06"') . ' ПОКУПКА или ' . json_decode('"\u2B07"') . ' ПРОДАЖА',
					'reply_markup' => array(
					'keyboard' => array(array(json_decode('"\u2B06"') . ' ПОКУПКА',json_decode('"\u2B07"') . ' ПРОДАЖА')),
					'resize_keyboard' => true)));
			file_put_contents("comments.txt","\r\n" . $chat_id . "\t" . $text, FILE_APPEND);		
			}
		} else {// apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'I understand only text messages'));
			apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => "Выберите направление торговли c помощью кнопок под чатом:\n" . json_decode('"\u2B06"') . ' ПОКУПКА или ' . json_decode('"\u2B07"') . ' ПРОДАЖА',
			'reply_markup' => array(
			'keyboard' => array(array(json_decode('"\u2B06"') . ' ПОКУПКА',json_decode('"\u2B07"') . ' ПРОДАЖА')),
			'resize_keyboard' => true)));
		}
	} else { // нулевой readPDO
		$data = array(
			'userId' => $chat_id,
			'money' => '100000',
			'messageCount' => '0',
			'tmpMessageCount' => '0',
			'prevPosition' => '2'
		);  
		$start = startPDO($message_id, $chat_id, $data);
		$read = readPDO($chat_id);
		$newTrendPDO = newTrendPDO($message_id, $chat_id, $read);
		// apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'Подождите!'));
	}
}
// json_decode('"\u203C"')
// " . json_decode('"\u26F5\uFE0E"') . "
// " . json_decode('"\uD83D\uDE97"') . "
function sendMessageTrend($message_id, $chat_id, $read) { // отправляю сообщение, если пользователь СЛЕДУЕТ тренду
	$todayMoney = $read["money"] * ((mt_rand(-1, 25))/100);
	$money = $read["money"] + $todayMoney;
	
	
	
	$target = number_format((50000000 - $money), 2, '.', ' ');
	
	if ($target < 0) {
		$target = "\n\nВы достигли цели!";
	} else{
		$target = "\n\nДо цели осталось: " . $target;
	}
	
	
	
	
	
	apiRequest("sendMessage", array('chat_id' => $chat_id, "text" =>
		json_decode('"\uD83D\uDE00"') . 'Вы CЛЕДУЕТЕ стратегии, все правильно делаете!' .
		"\n\nВсего сделок: " . $read["messageCount"] . 
		"\nСделок в текущей сессии: " . $read["tmpMessageCount"] . 
		"\n\nСумма до сделки: " . number_format($read["money"], 2, '.', ' ') . 
		"\nПрибыль: " . number_format($todayMoney, 2, '.', ' ') . 
		"\nИтого на счету: " . number_format($money, 2, '.', ' ') . $target, 'reply_markup' => array(
		'keyboard' => array(array(json_decode('"\u2B06"') . ' ПОКУПКА',json_decode('"\u2B07"') . ' ПРОДАЖА')),
		'resize_keyboard' => true)));
		
		
	
		if ($money > 7500000 && $money <= 20000000)  { // Мерседес
			apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => json_decode('"\uD83D\uDE98"') ." У Вас в гараже новый Mercedes S Класса!"));
		} else if ($money > 20000000 && $money <= 30000000)  {
			apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => json_decode('"\uD83D\uDE98"') ." У Вас в гараже новый Mercedes S Класса!\n" . json_decode('"\uD83C\uDFE1"') . " Вы живете в прекрасной вилле на берегу океана!"));
		} else if ($money > 30000000 && $money <= 40000000)  {
			apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => json_decode('"\uD83D\uDE98"') ." У Вас в гараже новый Mercedes S Класса!\n" . json_decode('"\uD83C\uDFE1"') . " Вы живете в прекрасной вилле на берегу океана!\n" . json_decode('"\u26F5\uFE0E"') . " На причале - белоснежная яхта!"));
		} else if ($money > 40000000 && $money <= 50000000)  {
			apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => json_decode('"\uD83D\uDE98"') ." У Вас в гараже новый Mercedes S Класса!\n" . json_decode('"\uD83C\uDFE1"') . " Вы живете в прекрасной вилле на берегу океана!\n" . json_decode('"\u26F5\uFE0E"') . " На причале - белоснежная яхта!\n" . json_decode('"\u2708"'). " Частный самолет ждет Ваших распоряжений!"));			
		} else if ($money > 50000000) {
			// apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => json_decode('"\uD83D\uDCB0"') ." Вы просто больше не волнуетесь о деньгах!"));			
			$sendFinalImage = sendFinalImage($chat_id);
		} else { // ПОКА НЕ ПОБЕДА
		}	
		
		$read["money"] = $money;
		$read["userId"] = $chat_id;	
		$update = updatePDO($read);
		$newTrendPDO = newTrendPDO($message_id, $chat_id, $read);
}

function sendMessageCounterTrend($message_id, $chat_id, $read) { // отправляю сообщение, если пользователь НЕ СЛЕДУЕТ тренду
	$todayMoney = $read["money"] * ((mt_rand(-2, 1))/100);
	$money = $read["money"] + $todayMoney;
	
	
	$target = number_format((50000000 - $money), 2, '.', ' ');
	
	if ($target < 0) {
		$target = "\n\nВы достигли цели!";
	} else{
		$target = "\n\nДо цели осталось: " . $target;
	}
	
	
	apiRequest("sendMessage", array('chat_id' => $chat_id, "text" =>
		json_decode('"\u26A0"') . 'Вы НЕ CЛЕДУЕТЕ стратегии, сильно рискуете!' .
		"\n\nВсего сделок: " . $read["messageCount"] . 
		"\nСделок в текущей сессии: " . $read["tmpMessageCount"] . 
		"\n\nСумма до сделки: " . number_format($read["money"], 2, '.', ' ') . 
		"\nПрибыль: " . number_format($todayMoney, 2, '.', ' ') . 
		"\nИтого на счету: " . number_format($money, 2, '.', ' ') . $target, 'reply_markup' => array(
		'keyboard' => array(array(json_decode('"\u2B06"') . ' ПОКУПКА',json_decode('"\u2B07"') . ' ПРОДАЖА')),
		'resize_keyboard' => true)));
		
		if ($money > 7500000 && $money <= 20000000)  { // Мерседес
			apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => json_decode('"\uD83D\uDE98"') ." У Вас в гараже новый Mercedes S Класса!"));
		} else if ($money > 20000000 && $money <= 30000000)  {
			apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => json_decode('"\uD83D\uDE98"') ." У Вас в гараже новый Mercedes S Класса!\n" . json_decode('"\uD83C\uDFE1"') . " Вы живете в прекрасной вилле на берегу океана!"));
		} else if ($money > 30000000 && $money <= 40000000)  {
			apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => json_decode('"\uD83D\uDE98"') ." У Вас в гараже новый Mercedes S Класса!\n" . json_decode('"\uD83C\uDFE1"') . " Вы живете в прекрасной вилле на берегу океана!\n" . json_decode('"\u26F5\uFE0E"') . " На причале - белоснежная яхта!"));
		} else if ($money > 40000000 && $money <= 50000000)  {
			apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => json_decode('"\uD83D\uDE98"') ." У Вас в гараже новый Mercedes S Класса!\n" . json_decode('"\uD83C\uDFE1"') . " Вы живете в прекрасной вилле на берегу океана!\n" . json_decode('"\u26F5\uFE0E"') . " На причале - белоснежная яхта!\n" . json_decode('"\u2708"'). " Частный самолет ждет Ваших распоряжений!"));		
		
/* 		if ($money > 120000 && $money <= 150000)  { // Мерседес
			apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => json_decode('"\uD83D\uDE98"') ." У Вас в гараже новый Mercedes S Класса!"));
		} else if ($money > 150000 && $money <= 200000)  {
			apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => json_decode('"\uD83D\uDE98"') ." У Вас в гараже новый Mercedes S Класса!\n" . json_decode('"\uD83C\uDFE1"') . " Вы живете в прекрасной вилле на берегу океана!"));
		} else if ($money > 200000 && $money <= 250000)  {
			apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => json_decode('"\uD83D\uDE98"') ." У Вас в гараже новый Mercedes S Класса!\n" . json_decode('"\uD83C\uDFE1"') . " Вы живете в прекрасной вилле на берегу океана!\n" . json_decode('"\u26F5\uFE0E"') . " На причале - белоснежная яхта!"));
		} else if ($money > 250000 && $money <= 300000)  {
			apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => json_decode('"\uD83D\uDE98"') ." У Вас в гараже новый Mercedes S Класса!\n" . json_decode('"\uD83C\uDFE1"') . " Вы живете в прекрасной вилле на берегу океана!\n" . json_decode('"\u26F5\uFE0E"') . " На причале - белоснежная яхта!\n" . json_decode('"\u2708"'). " Частный самолет ждет Ваших распоряжений!")); */			
		} else if ($money > 50000000) {
			// apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => json_decode('"\uD83D\uDCB0"') ." Вы просто больше не волнуетесь о деньгах!"));	
			$sendFinalImage = sendFinalImage($chat_id);			
		} else { // ПОКА НЕ ПОБЕДА
		}	
		
		$read["money"] = $money;
		$read["userId"] = $chat_id;	
		$update = updatePDO($read);
		$newTrendPDO = newTrendPDO($message_id, $chat_id, $read);
}


function newTrendPDO($message_id, $chat_id, $read) {
	if (!$read) {
		error_log("No query\n");
		return false;
	}
	
	$read['prevPosition'] = round(mt_rand(1, 2)); // генерация ТВ: 1 - ШОРТ, 2 - ЛОНГ
	
	$host = 'localhost';
	$dbname = 'dbname';
	$user =  'user';
	$pass = 'pass';

	try {  
		$DBH = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);  
		$DBH->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		echo "<br />nextTrendPDO connect ok!";
	}  
	catch(PDOException $e) {  
		echo $e->getMessage();  
	}

	
	// "text" => "Выберите направление торговли c помощью кнопок под чатом:" . json_decode('"\uD83D\uDD34"') . ' ПОКУПКА ' . json_decode('"\uD83D\uDD34"') . ' или ' . json_decode('"\uD83D\uDD34"') . ' ПРОДАЖА ' . json_decode('"\uD83D\uDD34"'),
	// 'keyboard' => array(array(json_decode('"\u2B06"') . ' ПОКУПКА',json_decode('"\u2B07"') . ' ПРОДАЖА')),
	
	if ($read['prevPosition'] > 1) { // это сгенерерированный ЛОНГ
	apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => json_decode('"\u203C"') . "Внимание!\nСформировалась точка входа на ПОКУПКУ\nВыберите направление сделки кнопками под чатом:\n" . json_decode('"\u2B06"') . ' ПОКУПКА или ' . json_decode('"\u2B07"') . ' ПРОДАЖА',
	'reply_markup' => array(
			'keyboard' => array(array(json_decode('"\u2B06"') . ' ПОКУПКА',json_decode('"\u2B07"') . ' ПРОДАЖА')),
			'resize_keyboard' => true)));
	} else { // это сгенерерированный ШОРТ
	apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => json_decode('"\u203C"') . "Внимание!\nСформировалась точка входа на ПРОДАЖУ\nВыберите направление сделки кнопками под чатом:\n" . json_decode('"\u2B06"') . ' ПОКУПКА или ' . json_decode('"\u2B07"') . ' ПРОДАЖА',
	'reply_markup' => array(
			'keyboard' => array(array(json_decode('"\u2B06"') . ' ПОКУПКА',json_decode('"\u2B07"') . ' ПРОДАЖА')),
			'resize_keyboard' => true)));
	}			

	$query = "UPDATE users SET prevPosition = " . $read['prevPosition'] . "	WHERE userId = " . $chat_id;
	
	$STH = $DBH->exec($query);
	
	$DBH = null; // закрывает подключение 
	return true;
}


function updatePDO($read) {
	if (!$read) {
		error_log("No query\n");
		return false;
	}
	
	$host = 'localhost';
	$dbname = 'dbname';
	$user = 'tuserotmba';
	$pass = 'pass';

	try {  
		$DBH = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);  
		$DBH->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	}  
	catch(PDOException $e) {  
		echo $e->getMessage();  
	}

	$query = "UPDATE users SET money = " . $read['money'] .  ", prevPosition = " . $read['prevPosition'] . ", messageCount = messageCount + 1, tmpMessageCount = tmpMessageCount + 1	WHERE userId = " . $read['userId'];
	
	$STH = $DBH->exec($query);
	
	$DBH = null; // закрывает подключение 
	return true;
}

function readPDO($userId) {
	if (!$userId) {
		error_log("No userId\n");
		return false;
	}
	
	$host = 'localhost';
	$dbname = 'dbname';
	$user = 'user';
	$pass = 'pass';

	try {  
		$DBH = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);  
		$DBH->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		echo "<br />selectPDO connect ok!";
	}  
	catch(PDOException $e) {  
		echo $e->getMessage();  
	}
	
	$STH = $DBH->query('SELECT * FROM users WHERE userId = ' . $userId);
	$result = $STH->fetch(PDO::FETCH_ASSOC);
	
	$DBH = null; // закрывает подключение 
	return $result;
}

function startPDO($message_id, $chat_id, $data) {
	if (!$data) {
		error_log("No query\n");
		// echo "No query";
		return false;
	}
	
	$host = 'localhost';
	$dbname = 'dbname';
	$user = 'user';
	$pass = 'pass';

	try {  
		$DBH = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);  
		$DBH->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
		// echo "<br />startPDO connect ok!";
	}  
	catch(PDOException $e) {  
		echo $e->getMessage();  
	}

	$query = "INSERT INTO users
	SET userId = " . $data['userId'] . ", money = " . $data['money'] . ", messageCount = 1, tmpMessageCount = 1, prevPosition = " . $data['prevPosition'] . "
	ON DUPLICATE KEY
	UPDATE money = " . $data['money'] . ", prevPosition = " . $data['prevPosition'] . ", messageCount = messageCount + 1, tmpMessageCount = 1
	";
	
	$STH = $DBH->exec($query);
	
	$DBH = null; // закрывает подключение 
	
	// apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => "Добро пожаловать!\n\nВы начинаете с суммы 100 000.00 руб\n\nСледуйте за трендом, выбирайте направление сделки кнопками под чатом:\nПОКУПКА или ПРОДАЖА.\n\nУдачи!" , 'reply_markup' => array(
	
	apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => "Добро пожаловать!\n\nВы на борту увлекательного тренажёра, который показывает всю силу Биржи.\nВы начинаете с суммы 100 000.00 руб.\n\nВаша цель - 50 миллионов руб.\n\nВаша главная задача - принимать решения.\n\nХорошие решения приведут Вас к процветанию. Дадут вам новый «Мерседес», виллу у райского моря, частный реактивный самолёт и белоснежную яхту...\nПлохие решения погубят Ваши мечты.\n\nСледуйте за трендом, выбирайте направление сделки кнопками под чатом:\n" . json_decode('"\u2B06"') . " ПОКУПКА или " . json_decode('"\u2B07"') . "ПРОДАЖА.\n\nНачинайте сейчас, и Удачи Вам!" , 'reply_markup' => array(
		'keyboard' => array(array(json_decode('"\u2B06"') . ' ПОКУПКА',json_decode('"\u2B07"') . ' ПРОДАЖА')),
		'resize_keyboard' => true)));
	
	return true;
}


// function sendFinalImage($chat_id, $text, $discount, $bot_url, $url_send) {
function sendFinalImage($chat_id) {
	// $bot_url    = "https://api.telegram.org/bot235708901:AAEDAWl015G3Or89NSKxdYCFIkezlQJO6As/";
	$bot_url    = "https://api.telegram.org/bot236412659:AAGa5NG2JhbaypK64CCx1lXFPQ_PUJM1dww/";
	$url_send = $bot_url . "sendPhoto?chat_id=" . $chat_id;


	// массив с урлами file_id уже отправленных картинок
	$fileidArray = array(
	"AgADBQADu6cxG_NeFw6AXyDeTdI1J6LgsTIABJCq1h3czLjB8cABAAEC",
	"AgADBQADvKcxG_NeFw5XiQABf3cExdwnsb8yAATLTZiAbhICBzYMAgABAg",
	"AgADBQADvacxG_NeFw5NG9AScee4d7GUvzIABD-OQ9fDf9j1-w0CAAEC",
	"AgADBQADvqcxG_NeFw4nG3fIjlypy3aUvzIABPxXXhgfOIy66QsCAAEC"
	);

	// отправка картинки по file_id
	foreach ($fileidArray as $filename) {
		$post_fields = array('chat_id'   => $chat_id,
			'photo'     => "$filename");

		$ch = curl_init(); 
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
			"Content-Type:multipart/form-data"
		));
		curl_setopt($ch, CURLOPT_URL, $url_send); 
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields); 
		$output = curl_exec($ch); 
		
		// apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => $output));	
	}



	apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => json_decode('"\uD83D\uDCB0"') ."Поздравляю, Вы победили!\n\nТеперь у Вас есть выбор:\n1. Вы можете продолжать эту виртуальную игру\n2. Вы можете начать игру с начала, нажав сюда: /start\n3. Вы принимаете решение получить все это на самом деле!\nЗаходите по ссылке ПРЯМО СЕЙЧАС и двигайтесь к своей МЕЧТЕ!\nhttp://tradingmba.ru/bot/"));	
	return true;
}



function genSendImage($chat_id, $text, $discount, $bot_url, $url_send) {
$url_send = $bot_url . "sendPhoto?chat_id=" . $chat_id;
$filename = "img/skidka-" . date("Ymd-His", time()) . "-" . sha1($name) . ".jpg";

// apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => "DEBUG: $chat_id, $text, $discount, $bot_url, $url_send"));

// генерация картинки
$im = @imagecreatefromjpeg('w222-800x600.jpg');// Создание изображения
$white = imagecolorallocate($im, 255, 255, 255);// Создание цветов
$code = mt_rand(100000,900000); // код скидки
$chksum = substr($discount, 0, 1) + substr($discount, 1, 1); // генерация контрольной суммы: сумма двух первых цифр скидки - это крайнее справа число в коде
$text = "$name\nCкидка на сумму: $discount руб.\nКод: " . $code . $discount . $chksum . "\nСрок действия: 24 часа";
$font = 'ubuntu.ttf'; // Шрифт
imagettftext($im, 42, 0, 40, 370, $white, $font, $text);// Текст
imagejpeg ($im, $filename, 75);
imagedestroy($im);

// отправка картинки
$post_fields = array('chat_id'   => $chat_id,
    'photo'     => "@$filename");

$ch = curl_init(); 
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    "Content-Type:multipart/form-data"
));
curl_setopt($ch, CURLOPT_URL, $url_send); 
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); 
curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields); 
$output = curl_exec($ch); 

// print_r($output); 

// apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => "DEBUG: output: $output"));
return true;
}




define('WEBHOOK_URL', 'https://WEBHOOK_URL/bot.php');

if (php_sapi_name() == 'cli') {
  // if run from console, set or delete webhook
  apiRequest('setWebhook', array('url' => isset($argv[1]) && $argv[1] == 'delete' ? '' : WEBHOOK_URL));
  exit;
}


$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
  // receive wrong update, must not happen
  exit;
}

if (isset($update["message"])) {
  processMessage($update["message"]);
}
?>
