<?php

define ( 'BOT_TOKEN', 'XXXX' ); //Get this from @BotFather by typing /token.
define ( 'API_URL', 'https://api.telegram.org/bot' . BOT_TOKEN . '/' );
define ( 'DSN', 'mysql:host=XXXX' ); //Address of database.
define ( 'dbname', 'XXXX' ); //Db name you chose.
define ( 'username', 'XXXX' ); //Db username you chose.
define ( 'password', 'XXXXXX' ); //Db password you chose.
define ( 'MAXLATITUDE', 'XXXX'); //Following MAX/MIN values for latitude and longitude are optional to set. Set these values if you wish to sanatize data provided by other people scanners. It will filter all pokemons outside of the parameters you provide. Check min/max lat/long values on google maps for your city/area.
define ( 'MINLATITUDE', 'XXXX');
define ( 'MAXLONGITUDE', 'XXXX');
define ( 'MINLONGITUDE', 'XXXX');
define ( 'TIME_OFFSET', 2); //Add or substract (place negative number) time in hours if disappear time doesn't match your region. ('2'=> Timezone berlin/germany).

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
	$url = str_replace ( '%25', '%', $url ); // quick fix to enable newline in messages. '%' musn't be replaced by http encoding to '%25'
	//file_get_contents($url); // Delete this when running on webserver
	$handle = curl_init ( $url );

	curl_setopt ( $handle, CURLOPT_RETURNTRANSFER, true );
	curl_setopt ( $handle, CURLOPT_CONNECTTIMEOUT, 5 );
	curl_setopt ( $handle, CURLOPT_TIMEOUT, 60 );
	sleep(1);
	return exec_curl_request ( $handle );
}
function getTelegramsBotId(){
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$telegramBotId = $db->query ("SELECT id FROM telegram_bot WHERE bot_token LIKE '" . BOT_TOKEN . "'")->fetch(PDO::FETCH_COLUMN);
	return $telegramBotId; 
}
function getAddress( $lat, $lng ) {   
    
	$URL = "http://maps.googleapis.com/maps/api/geocode/json?latlng=$lat,$lng";
	
	$response = file_get_contents( $URL );
	$json = json_decode($response);
	
	
	return $json->results[0]->address_components[1]->long_name . " " . $json->results[0]->address_components[0]->long_name;
}
function getTravelData($chat_id, $latitude, $longitude, $mode) {

	if($mode == 1)
		$mode = "walking";
	else if($mode == 2)
		$mode = "bycicling";
	
	$homeCoordinates = getUsersLocation($chat_id);
	$apiKey = getUsersApiKey($chat_id);
	$urlAPI = "https://maps.googleapis.com/maps/api/distancematrix/json?origins=$homeCoordinates&destinations=$latitude,$longitude&key=$apiKey&mode=$mode";
	$response = file_get_contents($urlAPI);
	incrementApiCounter($chat_id); //Tracking mechanism how many calls to google's maps matrix are made. Limit at 2,500 / day
	$json = json_decode($response);
	return $json;
}
function getUsersLocation($chat_id) {
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare("SELECT pos_latitude, pos_longitude FROM user_data WHERE chat_id LIKE :chat_id");
	$stmt->bindValue(':chat_id', $chat_id);
	$stmt->execute();
	
	$resultRow = $stmt->fetch ( PDO::FETCH_ASSOC );
	return $resultRow['pos_latitude'] . "," . $resultRow['pos_longitude'];
}
function userPokemonFilterActivated($user, $pokemon_id) { //Returns true if detected Pokemon is hidden by user.
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare("SELECT * FROM user_filters WHERE chat_id LIKE :user AND hidden_pokemon LIKE :pokemon_id");
	$stmt->bindValue(':user', $user);
	$stmt->bindValue(':pokemon_id', $pokemon_id);
	$stmt->execute();
	$result = $stmt->fetch(PDO::FETCH_COLUMN);

	if($result)
		return true;
	else 
		return false;
}
function userDistanceFilterActivated($chat_id, $distance) { //Returns true if distance if beyond users set maximum distance.
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare("SELECT distance_limit FROM user_data WHERE chat_id LIKE :chat_id");
	$stmt->bindValue(':chat_id', $chat_id);
	$stmt->execute();
	
	$distanceLimitFromDb = $stmt->fetch ( PDO::FETCH_COLUMN );
	
	if( $distance > $distanceLimitFromDb )
		return true;
	else 
		return false;
	
}
function userMuteFilterActivated($chat_id) {
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare("SELECT mute FROM bot_user_map WHERE chat_id LIKE :chat_id");
	$stmt->bindValue(':chat_id', $chat_id);
	$stmt->execute();
	$mute = $stmt->fetch ( PDO::FETCH_COLUMN);
	
	if($mute == 1) { //Mute filter is set to ON
		return true;
	}
	else { //Mute filter is set to OFF
		return false;
	}
}
function incrementApiCounter($chat_id) {
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare("UPDATE user_data SET api_counter = api_counter + 1 WHERE chat_id = :chat_id");
	$stmt->bindValue(':chat_id', $chat_id);
	$stmt->execute();
}
function buildPreviewLink($pokemon_id) {//Returns url for pokemon's picture in web-preview.
	//$url = "http://domainToYourPicsOrGifs";
	//$url .= $pokemon_id . ".gif";  //Build link will look like "http://sprites.pokecheck.org/i/006.gif" with incoming id of '6'.
	//return $url;
}
function getPokemonsNameById($pokemon_id, $lang) { //Returns Pokemons name in user's language by Id.
	
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare("SELECT $lang from pokemon_localization WHERE id LIKE :pokemon_id");
	$stmt->bindValue(':pokemon_id', $pokemon_id);
	$stmt->execute();
	$pokemonName = $stmt->fetch ( PDO::FETCH_COLUMN );
	return $pokemonName;
}
function getUserLanguage($user) {
	
		$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
		$stmt = $db->prepare("SELECT language from user_data WHERE chat_id LIKE :user");
		$stmt->bindValue(':user', $user);
		$stmt->execute();
		$language = $stmt->fetch( PDO::FETCH_COLUMN );
	
		return "name_" . $language;
}
function userHasActiveApiKeyAndLocationSet($user) {
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare("SELECT apiFlag, pos_latitude from user_data WHERE chat_id LIKE :chat_id");
	$stmt->bindValue(':chat_id', $user);
	$stmt->execute();
	$row = $stmt->fetch ( PDO::FETCH_ASSOC );

	if ($row['apiFlag'] && $row['pos_latitude'])
		return true;
	else
		return false;
}
function getTravelMode($user) {
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare("SELECT travelmode FROM user_data WHERE chat_id LIKE :user");
	$stmt->bindValue(':user', $user);
	$stmt->execute();
	$travelMode = $stmt->fetch ( PDO::FETCH_COLUMN);
	if ($travelMode == 1)
		return 1;
	else if($travelMode == 2)
		return 2;
}
function getUsersApiKey($user) {
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare("SELECT api_key FROM user_data WHERE chat_id LIKE :user");
	$stmt->bindValue(':user', $user);
	$stmt->execute();
	$apiKey = $stmt->fetch ( PDO::FETCH_COLUMN);
	return $apiKey;
}
function getAllUsersArray() {//Retrieve all subscribed users for this specific bot and returns them as an array. 
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$result = $db->query ("SELECT chat_id FROM bot_user_map WHERE bot_id LIKE " . getTelegramsBotId());
	return $result->fetchAll(PDO::FETCH_COLUMN);
}
function isInBoundaries($data) { //Checks if incoming Data is within range of min/max lat/long values.
	
	$incomingLatitude = $data["message"]["latitude"];
	$incomingLongitude = $data["message"]["longitude"];
	
	if($incomingLatitude >= MINLATITUDE && $incomingLatitude <= MAXLATITUDE && $incomingLongitude >= MINLONGITUDE && $incomingLongitude <= MAXLONGITUDE) 
		return true;
	else if (MINLATITUDE == "XXXX")
		return true;
	else 
		return false;
		
}
function buildInlineBtn($latitude, $longitude, $pokemon_id, $userLang, $disappearTime) { //Builds button for inline keyboard. This needs to be less than 64 bytes, so some form of compression needed.

	$latitude = round($latitude,5); // lat/long with 5 decimal place precision is precise up to ~1m. Don't need more here.
	$longitude= round($longitude,5);
	$userLang = str_replace("name_", '', $userLang); //Remove "lang_" part from string. Will be rebuild from bot's script. Also query of Pokemon name. Would be too big to fit 64 bytes otherwise.


	$seeMapInlineBtn = array();
	$data = array('la'=>$latitude , 'lo' => $longitude, 'p' => $pokemon_id, 'lan'=> $userLang, 't' => $disappearTime); //Biggest size possible would be 64 bytes.
	$dataJsonEncoded = json_encode($data);

	$inlineBtn = (object) array('text' => 'See Map' , 'callback_data' => $dataJsonEncoded);
	array_push($seeMapInlineBtn, array($inlineBtn));
	return $seeMapInlineBtn;
}


$data = json_decode(file_get_contents('php://input'), true);

if(isInBoundaries($data)) {  //Only continue to parse incoming data when it's a pokemon from within specified min/max lat/long values. 
	$encounter_id = $data["message"]["encounter_id"];
	$db = new PDO ( DSN . ';dbname=' . dbname, username, password );
	$stmt = $db->prepare("INSERT INTO pokemons (encounter_id, ipHash, bot_id) VALUES (:encounter_id, :ipHash, :bot_id)"); //Try inserting scanned pokemon. Returns false if already in db. (Constraint is set).
	$stmt->bindValue(':encounter_id', $encounter_id);
	$stmt->bindValue(':ipHash', md5($_SERVER['REMOTE_ADDR']));
	$stmt->bindValue(':bot_id', getTelegramsBotId());
	$result = $stmt->execute();
	if($result) { //Found Pokemon matches boundaries and DB query returned true. New pokemon found! Only then start retrieving data.
		$usersArray = getAllUsersArray();
		$pokemon_id = $data["message"]["pokemon_id"];
		$latitude = $data["message"]["latitude"];
		$longitude = $data["message"]["longitude"];
		$disappearTime = date('H:i:s', $data["message"]["disappear_time"]); //Alternative: time_until_hidden_ms
		$disappearTime =  strtotime($disappearTime)+TIME_OFFSET*(3600); //Add +2hrs for Germany.
		$disappearTime = date('H:i:s', $disappearTime);
		
		$messageSendToUser = ""; //User will receive this message for incoming pokemon.
		
		$address = getAddress($latitude, $longitude); //Name of the Street where the Pokemon can be found.
		$previewLink = buildPreviewLink($pokemon_id);
		
		foreach($usersArray as $user) { //Now the message a user receives get's individually crafted.
			if(!(userMuteFilterActivated($user)) && !(userPokemonFilterActivated($user, $pokemon_id)) ) {  //Incoming Pokemom was not hidden by user AND user has his global mute settings not turned on.
				
				$userLang = getUserLanguage($user);
				$pokemonName = getPokemonsNameById($pokemon_id, $userLang);
				
				if(userHasActiveApiKeyAndLocationSet($user)) { //User is using API and location set? Build detailed message with distance and traveltime.
				
					$travelMode = getTravelMode($user); //Retrieves user's travel settings. Foot/Bike
					$travelData = getTravelData($user, $latitude, $longitude, $travelMode); //Retrieves distance and travel time according to user settings from google's distrance matrix.
				
					$distance = $travelData->rows[0]->elements[0]->distance->value;
					$travelTime = $travelData->rows[0]->elements[0]->duration->text;
					if($travelMode == 1)
						$travelTime = "\xf0\x9f\x8f\x83" . $travelTime; //Add running man emoji
						else if($travelMode == 2)
							$travelTime = "\xf0\x9f\x9a\xb2 ". $travelTime; //Add bycicle emoji
				
							$messageSendToUser = "<b>$pokemonName</b> spotted <b>$distance". "m</b> away.%0A\"$address\"<a href=\"$previewLink\">%0A\xf0\x9f\x95\x91</a> Disappears at <b>$disappearTime</b>.%0A$travelTime"; //Build Message here
				
				} else { //User has no API Key. Build message without distance and Traveltime
					$messageSendToUser = "<b>$pokemonName</b> spotted.%0A$address.<a href=\"$previewLink\">%0A\xf0\x9f\x95\x91</a> Disappears at <b>$disappearTime</b>.";
				}
				if(!(userDistanceFilterActivated($user, $distance))) { //Only send message when Pokemon is near enough - according to users distance filter. This will always be true if no location is set by user.
				
					
					$seeMapInlineBtn = buildInlineBtn($latitude, $longitude, $pokemon_id, $userLang, $disappearTime);
					
					
					apiRequest ( "sendMessage", array (
							'chat_id' => $user,
							'parse_mode' => 'HTML',
							'text' => $messageSendToUser,
							'reply_markup' => array('inline_keyboard' => $seeMapInlineBtn)
							)
					);
				} //else: Pokemon's distance exceeds users distance filter!	TODO: If difference is really huge, user is not in the area, should probably /alarmsoff, otherwise his API Key limit gets burned.
				
				
				
				
			}//else: User muted all alarms or Pokemon was hidden, so no message send.
				
		}
				
	}
	else; //Insert failed, most likely because alraedy in db. Do nothing then.
}
?>