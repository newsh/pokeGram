<?php

$ini_array = parse_ini_file("config.ini");
define ( 'BOT_TOKEN', $ini_array['BOT_TOKEN'] );
define ( 'API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/' );
define ( 'DSN', 'mysql:host='.$ini_array['mysql:host'] );
define ( 'dbname', $ini_array['dbname'] );
define ( 'username', $ini_array['username'] );
define ( 'password', $ini_array['password'] );
//define ( 'WEBHOOK_ADDRESS', $ini_array['WEBHOOK_ADDRESS']); //Not needed as of right now.

function exec_curl_request($handle) {
	$response = curl_exec ( $handle );
	
	if ($response === false) {
		$errno = curl_errno ( $handle );
		$error = curl_error ( $handle );
		error_log ( "Curl returned error $errno: $error\n" );
		curl_close ( $handle );
		return false;
	}
	
	$http_code = intval ( curl_getinfo ( $handle, CURLINFO_HTTP_CODE ) );
	curl_close ( $handle );
	
	if ($http_code >= 500) {
		// do not wat to DDOS server if something goes wrong
		sleep ( 10 );
		return false;
	} else if ($http_code != 200) {
		$response = json_decode ( $response, true );
		error_log ( "Request has failed with error {$response['error_code']}: {$response['description']}\n" );
		if ($http_code == 401) {
			throw new Exception ( 'Invalid access token provided' );
		}
		return false;
	} else {
		$response = json_decode ( $response, true );
		if (isset ( $response ['description'] )) {
			error_log ( "Request was successfull: {$response['description']}\n" );
		}
		$response = $response ['result'];
	}
	
	return $response;
}
function apiRequest($method, $parameters) {
	if (! is_string ( $method )) {
		error_log ( "Method name must be a string\n" );
		return false;
	}
	
	if (! $parameters) {
		$parameters = array ();
	} else if (! is_array ( $parameters )) {
		error_log ( "Parameters must be an array\n" );
		return false;
	}
	
	foreach ( $parameters as $key => &$val ) {
		// encoding to JSON array parameters, for example reply_markup
		if (! is_numeric ( $val ) && ! is_string ( $val )) {
			$val = json_encode ( $val );
		}
	}
	$url = API_URL . $method . '?' . http_build_query ( $parameters );
	$url = str_replace ( '%25', '%', $url ); //Quick fix to enable newline in messages. '%' musn't be replaced by http encoding to '%25'
	$handle = curl_init ( $url );
	
	curl_setopt ( $handle, CURLOPT_RETURNTRANSFER, true );
	curl_setopt ( $handle, CURLOPT_CONNECTTIMEOUT, 5 );
	curl_setopt ( $handle, CURLOPT_TIMEOUT, 60 );
	
	return exec_curl_request ( $handle );
}
function getTelegramsBotId(){
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$botId = $db->query ("SELECT id FROM telegram_bot WHERE bot_token LIKE '" . BOT_TOKEN . "'")->fetch(PDO::FETCH_COLUMN);
	return $botId;
}
function showSettings($chat_id, $text) {
	apiRequest( "sendMessage", array (
			'chat_id' => $chat_id,
			'parse_mode' => 'HTML',
			"text" => $text,
			'reply_markup' => array ('keyboard' => array (array("\xf0\x9f\x94\x94 Pokemon Filter \xf0\x9f\x94\x94"), array("\xf0\x9f\x8c\x8e Location \xf0\x9f\x8c\x8e"), array("\xf0\x9f\x9a\xa9 Distance Limit \xf0\x9f\x9a\xa9"), array("\xf0\x9f\x8f\x83 Travel Mode \xf0\x9f\x9a\xb2"), array("Language"), array("Input API Key"), array("Return \xe2\xac\x85\xef\xb8\x8f")),'resize_keyboard' => true))
	);
}
function btnSetPokemonFilterPressed($chat_id) {
    showPokemonGenSelection($chat_id);
}
function btnSetTravelModePressed($chat_id) {
	apiRequest( "sendMessage", array (
					'chat_id' => $chat_id,
					"text" => "How do you like to catch Pokemon?",
					'reply_markup' => array ('keyboard' => array (array("Walking \xf0\x9f\x8f\x83"), array("Bycicle \xf0\x9f\x9a\xb2")),'resize_keyboard' => true))
	);
}
function setTravelMode($chat_id , $mode) {
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare("UPDATE user_data SET travelmode = :mode WHERE user_data.chat_id = :chat_id");
	$stmt->bindValue(':mode', $mode);
	$stmt->bindValue(':chat_id', $chat_id);
	$stmt->execute();
}
function getUserPokemonSorting($chat_id){
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare("SELECT pokemonSortFlag FROM user_data WHERE chat_id = :chat_id");
	$stmt->bindValue(':chat_id', $chat_id);
	$stmt->execute();
	$pokemonSortFlag = $stmt->fetch( PDO::FETCH_COLUMN);
	return $pokemonSortFlag;
}
function setUserPokemonSorting($chat_id, $sortingFlag){
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare("UPDATE user_data SET pokemonSortFlag = :sortingFlag WHERE user_data.chat_id = :chat_id");
	$stmt->bindValue(':sortingFlag', $sortingFlag);
	$stmt->bindValue(':chat_id', $chat_id);
	$stmt->execute();
}
function showPokemonGenSelection($chat_id) {
    $keyboard = array (
        'keyboard' => array (
            array("Gen 1 - Kanto"),
            array("Gen 2 - Johto"),
            array("Gen 3 - Hoenn"),
            array("Gen 4 - Sinno"),
            array("Return ↩️")
        ),
        'resize_keyboard' => false,
    );
    apiRequest ( "sendMessage", array (
        'chat_id' => $chat_id,
        'parse_mode' => 'HTML',
        'text' => "Please select a gen.",
        'reply_markup' => $keyboard
    ) );
}
function showPokemonFilter($gen, $chat_id , $text) {  //Shows a text and custom keyboard to user with his personal pokemon filter settings. 
	$keyBoardOfPokemons = array (  //This array represents all Pokemons. Only "Return" button added yet.
			'keyboard' => array (
					array("Return \xe2\x86\xa9\xef\xb8\x8f")
			),
			'resize_keyboard' => false,
	);
	switch($gen) {
	    case "Gen 1":
	        $idStart = 1;
	        $idEnd = 151;
	        break;
	    case "Gen 2":
	        $idStart = 152;
	        $idEnd = 251;
	        break;
	    case "Gen 3":
	        $idStart = 252;
	        $idEnd = 386;
	        break;
	    case "Gen 4":
	        $idStart = 387;
	        $idEnd = 493;
	        break;
	}
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	
	$hiddenPokemonIdList = array();
	
	$stmt = $db->prepare("SELECT hidden_pokemon from user_filters where chat_id like :chat_id");
	$stmt->bindValue(':chat_id', $chat_id);
	$stmt->execute();
	$hiddenPokemonIdList = $stmt->fetchAll(PDO::FETCH_COLUMN); //1. Get id list of user's hidden pokemons.
	
	$language = getUserLanguage($chat_id);
	$pokemonSortFlag = getUserPokemonSorting($chat_id);
	if($pokemonSortFlag == 0)
		$resultArray = $db->query ("SELECT id, " .$language. " from pokemon_localization WHERE id BETWEEN $idStart AND $idEnd GROUP BY $language"); //2. Get list of all pokemons names localized in user's language. Can't use prepared stmnt here, but its okay: gets sanitized in getUserLanguage() funtcion.
	else if($pokemonSortFlag == 1)
		$resultArray = $db->query ("SELECT id, " .$language. " from pokemon_localization WHERE id BETWEEN $idStart AND $idEnd");
	$rows = $resultArray->fetchAll();
	
	//3. Build keyboard with all Pokemons and user's current filter settings.
	foreach($rows as $pokemonLocalized) {
    	$btnText = $pokemonLocalized[$language];
    	if(in_array($pokemonLocalized['id'] , $hiddenPokemonIdList ))
    		$btnText = "\xf0\x9f\x94\x95" . $btnText; //Add  bell deactivated bell-emoji before pokemons name.
    	else 
    		$btnText = "\xf0\x9f\x94\x94" . $btnText; //Add bell activated bell-emoji before pokemons name.
    	array_push($keyBoardOfPokemons['keyboard'],array($btnText));
	}
	array_push($keyBoardOfPokemons['keyboard'],array("Return \xe2\x86\xa9\xef\xb8\x8f"));
	
	apiRequest ( "sendMessage", array (
			'chat_id' => $chat_id,
			'parse_mode' => 'HTML',
			'text' => $text,
			'reply_markup' => $keyBoardOfPokemons
	) );
}
function hidePokemon ( $pokemonName, $chat_id) {
	$pokemonId = getPokemonsIdByName($pokemonName, $chat_id);
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare("INSERT INTO user_filters VALUES (:chat_id, :pokemonId)");
	$stmt->bindValue('chat_id', $chat_id);
	$stmt->bindValue('pokemonId', $pokemonId);
	$stmt->execute();
}
function unhidePokemon ( $pokemonName, $chat_id) {
	$pokemonId = getPokemonsIdByName($pokemonName, $chat_id);
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare( "DELETE FROM user_filters WHERE chat_id LIKE :chat_id AND hidden_pokemon LIKE :pokemonId");
	$stmt->bindValue('chat_id', $chat_id);
	$stmt->bindValue('pokemonId', $pokemonId);
	$stmt->execute();
}
function unhideAllPokemon($chat_id) {
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare("DELETE FROM `user_filters` WHERE chat_id LIKE :chat_id");
	$stmt->bindValue(':chat_id', $chat_id);
	$stmt->execute();
}
function hidePokemonInBulk($chat_id) {  //Hides Pokemon in bulk. All Pokemons -but- "Very Rare, Super Rare and Ultra Rare" are hidden.

	$PokemonIdHideList = array(10,13,14,16,17,18,19,20,21,22,29,39,41,42,43,46,48,52,54,60,86,90,92,96,98,116,118,120,129,133);
	
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	foreach ($PokemonIdHideList as $pokemonId) {
		$stmt = $db->prepare("INSERT INTO user_filters (chat_id, hidden_pokemon) VALUES (:chat_id, :pokemon_id)");
		$stmt->bindValue(':chat_id', $chat_id);
		$stmt->bindValue(':pokemon_id', $pokemonId);
		$stmt->execute();
	}
}
function btnSetDistanceLimitPressed($chat_id) {
	apiRequest ( "sendMessage", array (
		'chat_id' => $chat_id,
		'parse_mode' => 'HTML',
		'text' => "Your current distance limit ist set to " . getDistanceLimit($chat_id) ."m. Set a new distance limit below and hit enter. Press /cancel to keep current value.",
		'reply_markup' => array ('force_reply' => true))
	);
}
function btnSetLanguagePressed($chat_id) {
		apiRequest( "sendMessage", array (
					'chat_id' => $chat_id,
					"text" => "Choose your language.",
					'reply_markup' => array ('keyboard' => array (array("English \xf0\x9f\x87\xba\xf0\x9f\x87\xb8"), array("Deutsch \xf0\x9f\x87\xa9\xf0\x9f\x87\xaa"), array("Français \xf0\x9f\x87\xab\xf0\x9f\x87\xb7"), array("ジャパニーズ \xf0\x9f\x87\xaf\xf0\x9f\x87\xb5"), array("Return \xe2\x86\xa9\xef\xb8\x8f")),'resize_keyboard' => true)) //, TODO: fix this: array("росси́йский \xf0\x9f\x87\xb7\xf0\x9f\x87\xba") 
	);
}
function btnInputAPIKeyPressed($chat_id) {
	apiRequest ( "sendMessage", array (
			'chat_id' => $chat_id,
			'parse_mode' => 'HTML',
			'text' => "Enter your Google Maps API key below.%0ANot interested? Press /cancel.%0AMore info? Press /moreInfo",
			'reply_markup' => array ('force_reply' => true))
	);
}
function btnSeeMapPressed($JsonCallbackData, $chat_id) {
	
	$latitude = $JsonCallbackData->la;
	$longitude = $JsonCallbackData->lo;
	$disappearTime = $JsonCallbackData->t;
	$pokemonId = $JsonCallbackData->p;
	$userLanguage = $JsonCallbackData->lan;
	$userLanguage = "name_" . $userLanguage;
	$pokemonName = getPokemonsNameById($pokemonId, $userLanguage);
	
	apiRequest( "sendVenue", array (
			'latitude' => $latitude,
			'longitude' => $longitude,
			'title' => "$pokemonName spotted!",
			'address' => "Disappears at $disappearTime",
			'chat_id' => $chat_id
	));
	
}
function setApiKey($apiKey, $chat_id) {
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	//TODO: verifyApiKey()
	$stmt = $db->prepare("UPDATE user_data SET api_key = :api_key WHERE user_data.chat_id = :chat_id");
	$stmt->bindValue(':api_key', $apiKey);
	$stmt->bindValue(':chat_id', $chat_id);
	$stmt->execute();
	
	$stmt = $db->prepare("UPDATE `user_data` SET `apiFlag` = '1' WHERE `user_data`.`chat_id` = :chat_id");
	$stmt->bindValue(':chat_id', $chat_id);
	$stmt->execute();
}
function setDistanceLimit($chat_id, $distance) {
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare( "UPDATE user_data SET distance_limit = :distance WHERE user_data.chat_id = :chat_id");
	$stmt->bindValue(':distance', $distance);
	$stmt->bindValue(':chat_id', $chat_id);
	$stmt->execute();
}
function getDistanceLimit($chat_id) {
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare( "SELECT distance_limit FROM user_data WHERE chat_id = :chat_id");
	$stmt->bindValue(':chat_id', $chat_id);
	$stmt->execute();
	$distanceLimit = $stmt->fetch( PDO::FETCH_COLUMN );
	return $distanceLimit;
	
}
function setAlarm($chat_id, $onOffFlag) {
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );

	$stmt = $db->prepare("UPDATE bot_user_map SET mute = :onOffFlag WHERE bot_user_map.chat_id = :chat_id AND bot_user_map.bot_id = :bot_id");
	$stmt->bindValue(':chat_id', $chat_id);
	$stmt->bindValue(':onOffFlag', $onOffFlag);
	$stmt->bindValue(':bot_id', getTelegramsBotId());
	
	//$bot_id = getTelegramsBotId();
	$stmt->execute();
}
function initUser($chat_id) {  
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare("INSERT INTO user_data (chat_id, distance_limit, language) VALUES (:chat_id, :distance_limit, :language)");
	$stmt->bindValue(':chat_id', $chat_id);
	$stmt->bindValue(':distance_limit', 2500);
	$stmt->bindValue(':language', 'en');
	$stmt->execute();
	
	$stmt = $db->prepare("INSERT INTO bot_user_map (bot_id, chat_id, mute) VALUES (:telegramBotId, :chat_id, 0)");
	$stmt->bindValue(':telegramBotId', getTelegramsBotId());
	$stmt->bindValue(':chat_id', $chat_id);
	$stmt->execute();

}
function getApiCounter($chat_id) {
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare("SELECT api_counter FROM user_data WHERE chat_id = :chat_id");
	$stmt->bindValue(':chat_id', $chat_id);
	$stmt->execute();
	$apiCounter = $stmt->fetch( PDO::FETCH_COLUMN);
	return $apiCounter;

}
function setLanguage($chat_id, $language) {

	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	
	$stmt = $db->prepare("UPDATE user_data SET language = :language WHERE user_data.chat_id = :chat_id");
	$stmt->bindValue(':chat_id', $chat_id);
	
	switch ($language) {
		
		case "English \xf0\x9f\x87\xba\xf0\x9f\x87\xb8":
			$stmt->bindValue(':language', en); 
			showSettings($chat_id, "Pokemons names will now appear in English language.");
			break;
		case "Deutsch \xf0\x9f\x87\xa9\xf0\x9f\x87\xaa":
			$stmt->bindValue(':language', de);
			showSettings($chat_id, "Die Namen deiner Pokemon erscheinen nun auf Deutsch.");
			break;
		case "Français \xf0\x9f\x87\xab\xf0\x9f\x87\xb7":
			$stmt->bindValue(':language', fr);
			showSettings($chat_id, "Pokemons names will now appear in French language.");
			break;
		case "росси́йский \xf0\x9f\x87\xb7\xf0\x9f\x87\xba":
			$stmt->bindValue(':language', ru);
			showSettings($chat_id, "Pokemons names will now appear in Russian language.");
			break;
		case "ジャパニーズ \xf0\x9f\x87\xaf\xf0\x9f\x87\xb5":
			$stmt->bindValue(':language', ja);
			showSettings($chat_id, "Pokemons names will now appear in Japanese language.");
			break;
		
	}
	$stmt->execute();
	
}
function getUserLanguage($user) {

	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare("SELECT language from user_data WHERE chat_id LIKE :user");
	$stmt->bindValue(':user', $user);
	$stmt->execute();
	$language = $stmt->fetch( PDO::FETCH_COLUMN );

	return "name_" . $language;
}
function getPokemonsNameById($pokemon_id, $lang) { //Returns Pokemons name in user's language by Id.

	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare("SELECT $lang from pokemon_localization WHERE id LIKE :pokemon_id");
	$stmt->bindValue(':pokemon_id', $pokemon_id);
	$stmt->execute();
	$pokemonName = $stmt->fetch ( PDO::FETCH_COLUMN );
	return $pokemonName;
}
function showMainMenu($chat_id, $text) {
	apiRequest( "sendMessage", array (
					'chat_id' => $chat_id,
					'text' => $text,
					'parse_mode' => 'HTML',
					'reply_markup' => array ('keyboard' => array (array("Settings \xe2\x9a\x99")),'resize_keyboard' => true)
					)
	);	
}
function getPokemonsIdByName($pokemon_name, $user) { //Returns Pokemons Id by name given in user's language.
	$language = getUserLanguage($user);
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare("SELECT id from pokemon_localization WHERE $language LIKE :pokemon_name"); //It's okay here not to bindValue() language. It is alraedy sanatized by getUsersLanguage.
	$stmt->bindValue(':pokemon_name', $pokemon_name);
	$stmt->execute();
	$pokemonId = $stmt->fetch(PDO::FETCH_COLUMN);
	
	return $pokemonId;
}
function setApiFlag($chat_id , $flag) {
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare("UPDATE user_data SET apiFlag = :flag WHERE user_data.chat_id = :chat_id");
	$stmt->bindValue(':flag', $flag);
	$stmt->bindValue(':chat_id', $chat_id);
	$stmt->execute();
}
function messageUser($chat_id, $text) {
	apiRequest( "sendMessage", array (
			'parse_mode' => 'HTML',
			'disable_web_page_preview' => true,
			'chat_id' => $chat_id,
			"text" => $text
	));
}
function checkTelegramHandlerDatabaseConnection ($chat_id) {
	//Retrieve BOT_TOKEN from DB rather than script itself. Only just once - to check if db and bot are set up correctly.
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$queriedBotTokenFromDB = $db->query ( "SELECT bot_token FROM telegram_bot WHERE bot_token LIKE '" . BOT_TOKEN . "'")->fetch ( PDO::FETCH_COLUMN );
	
	if(BOT_TOKEN == $queriedBotTokenFromDB) //Bot token in script matches bot token in db.
		messageUser($chat_id, "<code>Telegram Bot set up: \xe2\x9c\x85%0ADatabase set up: \xe2\x9c\x85</code>");
	else
		messageUser($chat_id, "Can't retrieve bot_token from db. Something went wrong.");
}
function showStatus($chat_id) { //Shows how many Pokemons are found recently and how many scanners are running. Works reasonably well: Scanner will be detected as runnning/online when he added at least one Pokemon to DB within last 30minutes.
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	
	$pokemonsFoundLast24h = $db->prepare("SELECT COUNT(bot_id) from pokemons WHERE bot_id = :bot_id  AND timestamp > :twoHoursAgo");  //How many Pokemons found in last 2 hrs?
	$pokemonsFoundLast24h->bindValue(':bot_id', getTelegramsBotId());
	$pokemonsFoundLast24h->bindValue(':twoHoursAgo', date('Y-m-d G:i:s' , strtotime("-2 hours")));
	
	
	$scannersCurrentlyOnline = $db->prepare("SELECT COUNT(DISTINCT(ipHash)) FROM pokemons WHERE bot_id = :bot_id AND timestamp > :thirtyMinutesAgo");//How many scanners are working?
	$scannersCurrentlyOnline->bindValue(':bot_id', getTelegramsBotId());
	$scannersCurrentlyOnline->bindValue(':thirtyMinutesAgo', date('Y-m-d G:i:s' , strtotime("-30 minutes")));
	
	$pokemonsFoundLast24h->execute();
	$scannersCurrentlyOnline->execute();
	
	$pokemonsFoundLast24h = $pokemonsFoundLast24h->fetch(PDO::FETCH_COLUMN);
	$scannersCurrentlyOnline = $scannersCurrentlyOnline->fetch(PDO::FETCH_COLUMN);
	
	messageUser($chat_id, "Scanners currently working: $scannersCurrentlyOnline%0APokemons found in past two hours: $pokemonsFoundLast24h");
}

function processMessage($message) {  //Process incoming message.

	$text = $message ['text'];  //Message from bot user. Either typed or from button's label. Works virtually exactly the same.
	$textRepliedTo = $message ['reply_to_message'] ['text'];
	$chat_id = $message ['chat'] ['id'];
	if (isset ( $textRepliedTo )) { // Users has responded to bot's user input request.
		error_log("triggered");
		if($textRepliedTo == "Your current distance limit ist set to " . getDistanceLimit($chat_id) ."m. Set a new distance limit below and hit enter. Press /cancel to keep current value.") {
			//TODO: Check if input data is valid. Only numbers and not bigger than 6 digits ->999km (999.999m). Check for "m" or "meter". Remove it.
			setDistanceLimit($chat_id, $text);
			showMainMenu($chat_id, "Your distance limit is now set to <b>$text" . "m</b>.");
		}
		else if($textRepliedTo == "Enter your Google Maps API key below.\nNot interested? Press /cancel.\nMore info? Press /moreInfo") {
			setApiKey($text, $chat_id);
			showMainMenu($chat_id, "Key sucessfully submitted!");
		}
		
		
	}
	if (isset ( $message['location'] )) { // User sends his location. 
		
		$latitude = $message['location']['latitude'];
		$longitude = $message['location']['longitude']; 
		
		$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
		$stmt = $db->prepare("UPDATE user_data SET pos_latitude = :latitude, pos_longitude = :longitude WHERE user_data.chat_id = :chat_id");
		$stmt->bindValue(':latitude', $latitude);
		$stmt->bindValue(':longitude', $longitude);
		$stmt->bindValue(':chat_id', $chat_id);
		$stmt->execute();
		showSettings($chat_id, "Location sucessfully received!");    
	}
	if (isset ( $message ['text'] )) { // User has send text - Either by typing or pressing button.
		
		if (strpos ( $text, "/start" ) === 0) {
			initUser($chat_id);
			showMainMenu($chat_id, "Hello, Trainer!%0A%0AThis bot will tell you about Pokemons nearby.%0A%0ACheck /about to see how it works.");
		}
		else if ($text === "Settings \xe2\x9a\x99" || $text === "/settings") {
				showSettings($chat_id, "Choose an Option.");
		}
		else if($text === "\xf0\x9f\x8c\x8e Location \xf0\x9f\x8c\x8e") {
				apiRequest( "sendMessage", array (
					'chat_id' => $chat_id,
					"text" => "Press the button below to send your current position!%0ANot interested? Press /cancel.%0AMore info? Press /moreInfo.",
					'reply_markup' => array ('keyboard' => array (array (array("text" =>"\xf0\x9f\x93\xa1 . . . Send Position. . . \xf0\x9f\x93\xa1", "request_location"=>true))),'resize_keyboard' => true))
				);
		}
		else if($text === "/cancel" || $text === "Return \xe2\x86\xa9\xef\xb8\x8f" || $text === "/done") {
			showSettings($chat_id, "Waiting for Pokemon ...");
		}
		else if($text === "Return \xe2\xac\x85\xef\xb8\x8f") {
			showMainMenu($chat_id, "Waiting for Pokemon ...");           
		}
		else if($text === "\xf0\x9f\x94\x94 Pokemon Filter \xf0\x9f\x94\x94" || $text == "/hidepokemon") {
			btnSetPokemonFilterPressed($chat_id);
		}
		else if(substr($text,0,3) === "Gen") {
		    showPokemonFilter(substr($text,0,5), $chat_id , "Press Pokemons you want to hide or unhide.");
		}
		else if (strpos ( $text, "\xf0\x9f\x94\x95" ) === 0) {  //Used for pokemon filter menue. User is trying to unmute a pokemon. This is triggered when first symbol on pressed button is a DEACTIVATED bell-emoji. 
														
			$pokemonName = str_replace(array("\xf0\x9f\x94\x95"),'', $text); //Remove DEACTIVATED bell-emoji.
			unhidePokemon($pokemonName , $chat_id);
			messageUser($chat_id, "<b>$pokemonName</b> is not hidden anymore. You will be notified when it appears.");
			showPokemonGenSelection($chat_id);
		}
		else if (strpos ( $text, "\xf0\x9f\x94\x94" ) === 0) {  //Used for pokemon filter menue. User is trying to mute a pokemon. This is triggered when first symbol on pressed button is a ACTIVATED bell-emoji.
			$pokemonName = str_replace(array("\xf0\x9f\x94\x94"),'', $text); //Remove ACTIVATED bell-emoji.
			hidePokemon($pokemonName, $chat_id);
			messageUser($chat_id, "<b>$pokemonName</b> is now hidden. I won't send any notification when it appears.");
			showPokemonGenSelection($chat_id);
		}
		else if($text === "\xf0\x9f\x9a\xa9 Distance Limit \xf0\x9f\x9a\xa9") {
			btnSetDistanceLimitPressed($chat_id);
		}
		else if($text === "/alarmsoff") {
			setAlarm($chat_id, 1);
			messageUser($chat_id, "All alarms turned OFF!");
		}
		else if($text === "/alarmson") {
			setAlarm($chat_id, 0);
			messageUser($chat_id, "All alarms turned ON!");
		}
		else if($text === "/hidecommon") {
			hidePokemonInBulk($chat_id);
			messageUser($chat_id , "Common pokemons are now hidden.%0ADon't agree with filter settings? You can always customize them as you wish. You can also /unhideAll.");
		}
		else if($text === "/unhideall") {
			unhideAllPokemon($chat_id);
			messageUser($chat_id , "All Pokemons are now unhidden.");
		}
		else if($text === "/apicount") {
			messageUser($chat_id, "<pre>" . getApiCounter($chat_id) . "/2500 API Usage.</pre>");
		}
		else if($text === "Language" || $text === "/language") {
			btnSetLanguagePressed($chat_id);
		}
		else if($text === "English \xf0\x9f\x87\xba\xf0\x9f\x87\xb8") {
			setLanguage($chat_id, $text);
		}
		else if($text === "Deutsch \xf0\x9f\x87\xa9\xf0\x9f\x87\xaa") {
			setLanguage($chat_id, $text);
		}
		else if($text === "Français \xf0\x9f\x87\xab\xf0\x9f\x87\xb7") {
			setLanguage($chat_id, $text);
		}
		else if($text === "росси́йский \xf0\x9f\x87\xb7\xf0\x9f\x87\xba") { //TODO: To be implemented later.
			setLanguage($chat_id, $text);
		}
		else if($text === "ジャパニーズ \xf0\x9f\x87\xaf\xf0\x9f\x87\xb5") {
			setLanguage($chat_id, $text);
		}
		else if($text === "\xf0\x9f\x8f\x83 Travel Mode \xf0\x9f\x9a\xb2") {
			btnSetTravelModePressed($chat_id);
		}
		else if($text === "Walking \xf0\x9f\x8f\x83") {
			setTravelMode($chat_id , 1);
			showSettings($chat_id, "Travel Mode set to <b>Walking</b>.");
		}
		else if($text === "Bycicle \xf0\x9f\x9a\xb2") {
			setTravelMode($chat_id , 2);
			showSettings($chat_id , "Travel Mode set to <b>Bycicle</b>.");
		}
		else if($text === "Input API Key") {
			btnInputAPIKeyPressed($chat_id);
		}
		else if($text == "/moreInfo") {
			messageUser($chat_id, "<i>This function is optional</i>.%0AI can tell you how far away pokemon are and the time it will take you to get there.%0A%0AYou have to allow this bot to receive your location once and provide a Google Maps API Key.%0A%0AYou can get your own Google Maps API Key by following this easy <a href=\"https://pgm.readthedocs.io/en/develop/basic-install/google-maps.html\"> Guide</a>. This bot uses the Distance Matrix API, which can be called 2500 times per day. Use the Pokemon Filter Settings and you shouldn't reach this limit. Use /api for your current API usage from this bot. /cancel");
		}
		else if($text == "/about") {
			messageUser($chat_id, "This bot will tell you about pokemons nearby, reported by other people.%0A%0ADo you have a scanner running or know anyone running a scanner for this specific area using <a href=\"https://github.com/PokemonGoMap/PokemonGo-Map\">PokemonGo Map</a>?%0A%0AYou can make this bot even better by sharing what your scanner found.%0A%0AWant to create your own bot for your area? See <a href=\"https://github.com/newsh/pokeGram\">here</a> how to do it.");
		}
		else if($text == "/apion") {
			setApiFlag($chat_id , 1);
			messageUser($chat_id, "<code>API Key enabled.</code>");
		}
		else if($text == "/apioff") {
			setApiFlag($chat_id , 0);
			messageUser($chat_id, "<code>API Key disabled.</code>");
		}
		else if($text == "/initdb") {
			$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
			$db->query ( "INSERT INTO `telegram_bot` (`id`, `bot_token`) VALUES (NULL, '" . BOT_TOKEN . "')");
			checkTelegramHandlerDatabaseConnection($chat_id);
		}
		else if($text == "/status") {
			showStatus($chat_id);
		}
		else if($text == "/sortbyid") {
			setUserPokemonSorting($chat_id, 1);
			messageUser($chat_id , "Pokemon are now sorted by <b>id</b>.");
		}
		else if($text == "/sortbyname") {
			setUserPokemonSorting($chat_id, 0);
			messageUser($chat_id , "Pokemon are now sorted by <b>name</b>.");
		}
	}
	else {//User sends anything but text msg
			//("sendMessage", array('chat_id' => $chat_id, "text" => 'I only read text messages, sorry!'));
	}
} 

$content = file_get_contents ( "php://input" );
$update = json_decode ( $content, true );
if (!$update) {
	// Received wrong update, must not happen.
	exit ();
}
if (isset ( $update ["message"] )) {
	processMessage ( $update ["message"] );
}
if (isset ( $update ["callback_query"] )) {  //User has pressed inline button. Initiates callback_query
	
	$chat_id = $update["callback_query"]['from']['id'];
	$callbackData = $update["callback_query"]['data'];
	
	$JsonCallbackData = json_decode($callbackData);
	
	if(isset($JsonCallbackData->la)) { //Callback_query comes from "Get Map" Inline button.
		
		btnSeeMapPressed($JsonCallbackData, $chat_id);
	}
}

?>