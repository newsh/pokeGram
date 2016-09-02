# Instructions

What you need to get this running:
* Webserver supporting SSL
* mySQL database
* Telegram messenger - available on [all platforms](https://telegram.org/), mobile and desktop
* _optional:_ Running PokemonGo scanner. Use your own or ask others to set a webhook to your chat bot

###1. Create a bot

 To create a bot add [@BotFather](https://telegram.me/BotFather) to your Telegram contacts, type /newbot and follow the instructions given.
 
 \- If you want your bot to be found by users familiar with pokeGram, choose a username like pokegramCITYNAME\_bot_ or _pokegramZIPCODE\_bot_. or combination of both.


###2. Set up database

 Create a database with [this  .sql file] (/src/).

###3. Upload files to your webserver

 Create a folder on your webserver and upload these [files] (/src/webserver). Both .php files need to be accessible.
###4.  Configure your settings

 Edit _config.ini_ and fill in your credentials.
###4. Set a webhook
 Set a webhook by opening 
 
 `https://api.telegram.org/botYOUR_BOT_TOKEN_HERE/setwebhook?url=URL_TO_YOUR_POKEGRAMBOT.PHP-FILE`
 
 _note:_ use 'https:', do not accidentally remove 'bot' part from URL before pasting your bot-token, do not use telegramHandler.php file for this.
###5. Hook bot to database
Add your bot to Telegrams contacts by searching it's name.

type `/initdb` into the chat, hit send. You will receive a confirmation in case everything is working as intended.

Type /start again to receive notifications for pokemons like a regular user.
###6. Spread the word!
 Add URL of your `telegramHandler.php` file to any scanner's webhook. Found in `/config/config.ini`.  
 
##Further bot customization  
Further bot customization can be done with [@botfather's](https://telegram.me/BotFather) help.  
-- `/setuserpic` set a user pic for your bot  

-- `/setdescription` set up a text every user can read when first starting your bot. You can place your contact info here if you wish. Just in case someone wants to share his scanner data.  
-- `/setcommands` make your bot easier to navigate by adding preset commands. They will appear when starting to type `"/"`. You can paste this list when asked to give your commands: 

`status - see bot's current status`  
`alarmson - turn alarms on`  
`alarmsoff - turn alarms off`   
`hidecommon - hide all common pokemon`  
`unhideall - unhide all pokemon`  
`apion - personal api key will be used`  
`apioff - personal api key will not be used`  
`sortbyid - sort pokemon by id`  
`sortbyname - sort pokemon by name`
  
    
*All changes can take some time to update. Removing and re-adding bot to contacts can help too.*
