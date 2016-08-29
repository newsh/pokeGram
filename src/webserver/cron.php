<?php
/* This script will free your database of all pokemon with timestamps older than 1hour and resets the user's api_counter. 
I would recommend to set up a cronjob -if possible- and run this script once a day at 12:00 am PST. This is when google resets their daily free quota for api calls.*/
define ( 'DSN', 'mysql:host=YOUR_IP_GOES_HERE' );
define ( 'dbname', '' );
define ( 'username', '' );
define ( 'password', '' );

$db = $db = new PDO ( DSN . ';dbname=' . dbname, username, password );
$resetApiCounter = $db->prepare("UPDATE `user_data` SET `api_counter` = 0");
$deletePokemonOlderThan60Minutes = $db->prepare("DELETE FROM pokemons WHERE timestamp < (NOW() - INTERVAL 60 MINUTE)"); 

$resetApiCounter->execute();
$deletePokemonOlderThan60Minutes->execute();

?>