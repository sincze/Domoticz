#!/usr/bin/php
<?PHP

/**
 * Pass2PHP
 * php version 7.4
 *
 * @category Home_Automation
 * @package  Pass2PHP lightscript for SolarEdge Inverter based on USERNAME & PASSWORD (NO API KEY available)
 * @author   Sándor Incze
 * @license  GNU GPLv3
 * @link     https://github.com/sincze/Domoticz
 * 
 * @Version  06-10-2022 Initial Release
 **/

// $ sudo apt-get update && sudo apt-get upgrade
// $ sudo apt-get install php7.4 php7.4-curl php7.4-gd php7.4-imap php7.4-json php7.4-mcrypt php7.4- php7.0-cli
// $ cd /home/pi/domoticz/scripts
// $ mkdir pass2php
// $ cd pass2php
// $ Add the downloaded RAW file here

// In Domoticz:
// Create Virtual Sensor -> Electric (instant+Counter)
// Check device idx (Setup-> Devices) for created Virtual Sensor
// Check Setup -> Settings -> Local Networks if '127.0.0.*' is present.

// In solaredge.php 
// Modify **** to your username  line 47
// Modify **** to your password  line 48
// Modify DOMOTICZDEVICE **** to your IDX  line 49

// In terminal execute 'php /home/pi/domoticz/scripts/pass2php/solaredge.php'
// No errors should be seen, check domoticz created virtual sensor device & log for errors.
// If device is updated continue.

// $ sudo nano /etc/crontab
// add line: */5 * * * *   root    php /home/pi/domoticz/scripts/pass2php/solaredge.php >/dev/null 2>&1  

error_reporting(E_ALL);						// 14-04-2019 Pass2PHP 3.0
ini_set("display_errors","on");				// 14-04-2019 Pass2PHP 3.0
date_default_timezone_set('Europe/Amsterdam');
define('time',$_SERVER['REQUEST_TIME']);	
define('domoticz','http://127.0.0.1:8080/');

define('USERNAME', '');				//The email address of the account.
define('PASSWORD', '');				//The password  of the account.
define('DOMOTICZDEVICE', ****);			// Your IDX in here

define('USER_AGENT', 'okhttp/4.10.0');
define('COOKIE_FILE','/home/pi/domoticz/scripts/pass2php/solaredge.cookies');			//Where our cookie information will be stored 
define('LOGIN_FORM_URL', 'https://api.solaredge.com/solaredge-apigw/api/login?j_username='.USERNAME.'&j_password='.PASSWORD);
define('DATA_URL', 'https://api.solaredge.com/solaredge-apigw/api/v3/sites/'.SITE);
define('SITE', ****); // This is a number

$continue=true;
$debug=true;									// Debugging log only.
$target='';										// Debugging log only.
																								
//---------------------------------------------------------------------------------------
// BEGIN
//---------------------------------------------------------------------------------------

fullpackage();	

//---------------------------------------------------------------------------------------
// NEEDED FUNCTIONS
//---------------------------------------------------------------------------------------

function fullpackage()
{
	global $debug;

	$resultlogin=login();

	if($resultlogin) {
		if ($debug) lg('SolarEdge inverter: 2. I was able to login lets retrieve data!');	
			$cookieresult=retrieve_data();										// If cookie was created retrieve data
			if ($cookieresult===false) {
				if ($debug) lg('SolarEdge inverter: 3. Something Failed retrieving data with '.$cookie_file);	
			}
		}	
		else {
			if ($debug) lg('SolarEdge inverter: 7. Sorry something went wrong with login procedure!');	
		}
}	


//---------------------------------------------------------------------------------------
// NEEDED FUNCTIONS
//---------------------------------------------------------------------------------------

function login()
{
	global $debug;
	global $continue;
	global $target;
    
	$curl = curl_init();	
	curl_setopt($curl, CURLOPT_URL, LOGIN_FORM_URL);			// Set the URL that we want to send our POST request to. 
	curl_setopt($curl, CURLOPT_POST, true);						    // Tell cURL that we want to carry out a POST request.
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);		// We don't want any HTTPS errors.
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);		// We don't want any HTTPS errors.
	curl_setopt($curl, CURLOPT_COOKIEJAR, COOKIE_FILE);		// Where our cookie details are saved. This is typically required for authentication, as the session ID is usually saved in the cookie file.
	curl_setopt($curl, CURLOPT_COOKIEFILE, COOKIE_FILE); 
	curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);		// Sets the user agent. Some websites will attempt to block bot user agents. //Hence the reason I gave it a Chrome user agent.
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);			// Tells cURL to return the output once the request has been executed.
	curl_setopt($curl, CURLOPT_SSLVERSION, 1);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);			// Do we want to follow any redirects?
	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT,2);				  // 09-07-2019
	curl_exec($curl);											                // Execute the login request.
	$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);	// Check for errors!
	$target = curl_getinfo($curl, CURLINFO_EFFECTIVE_URL);// Capture Redirect URL
	$continue = analyze_http($http_code,'login');				  // Analyze HTTP Code
	curl_close($curl);
	if ($debug) {
		if (file_exists(COOKIE_FILE)) echo ('SolarEdge inverter: Cookie File '.COOKIE_FILE.' exists!'); else echo ('SolarEdge Cookie File: '.COOKIE_FILE.' does NOT exist!');	
	}
	
	if (is_writable(COOKIE_FILE)) {
			if ($debug) echo('SolarEdge inverter: Cookie File '.COOKIE_FILE.' is writable!'); 
			$continue=true; 
	} 
	else { 
		if ($debug) echo('SolarEdge inverter: Cookie File '.COOKIE_FILE.' NOT writable!');
		$continue=false;
	}
	return $continue;	
}


function retrieve_data()
{	
	global $continue;
	global $debug;
	global $target;
	
	if ($debug) echo('SolarEdge inverter: Retrieve Data function called!'.PHP_EOL);

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, DATA_URL);			//We should be logged in by now. Let's attempt to access a password protected page
	curl_setopt($curl, CURLOPT_HTTPGET, true);
	curl_setopt($curl, CURLOPT_COOKIEFILE, COOKIE_FILE); 
	curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);					//Use the same user agent, just in case it is used by the server for session validation.	
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);						//We don't want any HTTPS / SSL errors.
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);						//We don't want any HTTPS / SSL errors.
	curl_setopt($curl, CURLOPT_SSLVERSION, 1);
	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT,2);						// 09-07-2019
	$result = curl_exec($curl);		
	//print_r($result);												//Execute the GET request and print out the result.
	$http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
	$continue = analyze_http($http_code,'retrieve_data');				// Analyze HTTP Code
	curl_close($curl);
	
//	header('Content-Type: application/json');

	if ($continue) {
		if ($debug) echo('SolarEdge inverter: Let have a look at the received data!');

		$data = json_decode($result, JSON_PRETTY_PRINT);
		//print_r($data);
		
		//echo $data['fieldOverview']['fieldOverview']['lifeTimeData']['energy'].PHP_EOL;
		//echo $data['fieldOverview']['fieldOverview']['lastDayData']['energy'].PHP_EOL;
		//echo $data['fieldOverview']['fieldOverview']['currentPower']['currentPower'].PHP_EOL;
		
		if(!empty($data['fieldOverview']['fieldOverview']['lifeTimeData']['energy'])) {
						
			$nowpower = round((float)$data['fieldOverview']['fieldOverview']['currentPower']['currentPower'],2);
			$todaypower = (float)$data['fieldOverview']['fieldOverview']['lastDayData']['energy'];		// WH			
			$allpower = (float)$data['fieldOverview']['fieldOverview']['lifeTimeData']['energy'];		   		
			$str=( $nowpower.';'.$allpower ); 
      if ($debug) print $str;
			ud(DOMOTICZDEVICE,0,$str);
		}
		else $continue=false;
	}	
	return $continue;
}


function analyze_http($http_code=501,$function='')
{
	global $continue;
	global $debug;

	if ($debug) echo('SolarEdge inverter: Analyze HTTP for function '.$function.' called with HTTP code: '.$http_code);
	switch ($http_code) {
 		case 200:   $continue=true;# OK
 	  					if ($debug) echo('SolarEdge inverter: Expected HTTP code: '.$http_code);
      					break;
		case 302:   $continue=true;# OK
 	 					if ($debug) echo('SolarEdge inverter: Expected HTTP code: '.$http_code);
       					break;        				
    	default:    $continue=false;
       					if ($debug) echo('SolarEdge inverter: Unexpected HTTP code: '.$http_code);
		}
	return $continue;
}

function lg($msg)   // Can be used to write loglines to separate file or to internal domoticz log. Check settings.php for value.
{
	curl(domoticz.'json.htm?type=command&param=addlogmessage&message='.urlencode('--->> '.$msg));		
}

function ud($idx,$nvalue,$svalue,$check=false)
{	
	curl(domoticz.'json.htm?type=command&param=udevice&idx='.$idx.'&nvalue='.$nvalue.'&svalue='.$svalue);
	lg(' (udevice) | '.$idx.' => '.$nvalue.','.$svalue);
}

function curl($url){
	$headers=array('Content-Type: application/json');
	$ch=curl_init();
	curl_setopt($ch,CURLOPT_URL,$url);
	curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
 	curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($ch,CURLOPT_TIMEOUT, 3);
	curl_setopt($ch,CURLOPT_USERAGENT,'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.13) Gecko/20080311 Firefox/2.0.0.13');
	curl_setopt($ch,CURLOPT_HTTPHEADER,$headers);
	$data=curl_exec($ch);
	curl_close($ch);
	return $data;
}



?>
