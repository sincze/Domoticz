#!/usr/bin/php
<?PHP

/**
 * Pass2PHP
 * php version 7.0
 *
 * @category Home_Automation
 * @package  Pass2PHP lightscript for Growatt Inverter
 * @author   Sándor Incze
 * @license  GNU GPLv3
 * @link     https://github.com/sincze/Domoticz
 **/

// $ sudo apt-get update && sudo apt-get upgrade
// $ sudo apt-get install php7.0 php7.0-curl php7.0-gd php7.0-imap php7.0-json php7.0-mcrypt php7.0- php7.0-cli
// $ cd /home/pi/domoticz/scripts
// $ mkdir pass2php
// $ cd pass2php
// $ Add the downloaded RAW file here

// In Domoticz:
// Create Virtual Sensor -> Electric (instant+Counter)
// Check device idx (Setup-> Devices) for created Virtual Sensor
// Check Setup -> Settings -> Local Networks if '127.0.0.*' is present.

// In growatt-inverter.php 
// Modify **** to your username  line 49
// Modify **** to your password  line 50
// Modify DOMOTICZDEVICE **** to your IDX  line 69

// In terminal execute 'php /home/pi/domoticz/scripts/pass2php/growatt-inverter.php'
// No errors should be seen, check domoticz created virtual sensor device & log for errors.
// If device is updated continue.

// $ sudo nano /etc/crontab
// add line: */5 * * * *   root    php /home/pi/domoticz/scripts/pass2php/growatt-inverter.php >/dev/null 2>&1  

error_reporting(E_ALL);						
ini_set("display_errors","on");				
date_default_timezone_set('Europe/Amsterdam');
define('time',$_SERVER['REQUEST_TIME']);	
define('domoticz','http://127.0.0.1:8080/');

retrieve_growatt_data('test');

function retrieve_growatt_data($command)
{
	define('USERNAME', '*****');																		// The username or email address of the account.
	define('PASSWORD', '*****');// The Password of the account
	$pw =  md5(PASSWORD);					// No Need to double md5
								// replace leading 0 by c for Growatt
	for ($i = 0; $i < strlen($pw); $i=$i+2){
		if ($pw[$i]=='0')
        	{
                   $pw=substr_replace($pw,'c',$i,1);
	        }
	}
	
	define('USER_AGENT', 'Dalvik/2.1.0 (Linux; U; Android 9; ONEPLUS A6003 Build/PKQ1.180716.001)');	// Set a user agent. 
	define('COOKIE_FILE','/home/pi/domoticz/scripts/pass2php/growatt.cookie');							// Where our cookie information will be stored (need to be writable!)
	define('HEADER',array('Content-Type: application/x-www-form-urlencoded;charset=UTF-8',
					  'Connection: keep-alive',
					  'Host: server.growatt.com',
					  'User-Agent: Domoticz/1.0'));
	define('LOGIN_FORM_URL', 'https://server.growatt.com/newTwoLoginAPI.do'); 							// 16-06-2022: URL of the login form. 
	define('LOGIN_ACTION_URL', 'https://server.growatt.com/newTwoLoginAPI.do');							// 20-08-2023: Updated tnx to "oepi-loepi".
	define('DATA_URL', 'https://server.growatt.com/newPlantAPI.do?action=getUserCenterEnertyData');
	define('DOMOTICZDEVICE', '****');																	// 'idx_here' For Watt / Daily Return																												
	$continue=true;

	$postValues = array(
		'userName'	=> USERNAME,
		'password'	=> $pw
	);

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, LOGIN_ACTION_URL);			// Set the URL that we want to send our POST request to. In this	case, it's the action URL of the login form.
	curl_setopt($curl, CURLOPT_POST, true);					// Tell cURL that we want to carry out a POST request.
	curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postValues));	// Set our post fields / date (from the array above). 
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);			// We don't want any HTTPS errors.
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);			// We don't want any HTTPS errors.
	curl_setopt($curl, CURLOPT_COOKIEJAR, COOKIE_FILE);			// Where our cookie details are saved. 
																			// This is typically required for authentication, as the session ID is usually saved in the cookie file.
	curl_setopt($curl, CURLOPT_HTTPHEADER, HEADER);
	curl_setopt($curl, CURLOPT_COOKIEFILE, COOKIE_FILE); 
	curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);			// Sets the user agent. Some websites will attempt to block bot user agents. //Hence the reason I gave it a Chrome user agent.
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);			// Tells cURL to return the output once the request has been executed.
	curl_setopt($curl, CURLOPT_REFERER, LOGIN_FORM_URL);			// Allows us to set the referer header. In this particular case, 
																			// we are fooling the server into thinking that we were referred by the login form.
	curl_setopt ($curl, CURLOPT_SSLVERSION, 1);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);			// Do we want to follow any redirects?
	$result=curl_exec($curl);						// Execute the login request.

	if(curl_errno($curl)){
		switch ($http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
 			case 200:   $continue=true;# OK
 	    				lg('Growatt inverter: Login: Expected HTTP code: '. $http_code);
        				break;
			case 302:   $continue=true;# OK
 	    				lg('Growatt inverter: Login: Expected HTTP code: '. $http_code);
        				break;        				
        	default:    $continue=false;
        				lg('Growatt inverter: Login: Unexpected HTTP code: '. $http_code);
		}
	}
	curl_close($curl);

	if (file_exists(COOKIE_FILE)) lg ('Cookie File: '.COOKIE_FILE.' exists!'); else lg ('Cookie File: '.COOKIE_FILE.' does NOT exist!');
	if (is_writable(COOKIE_FILE)) lg ('Cookie File: '.COOKIE_FILE.' is writable!'); else lg ('Cookie File: '.COOKIE_FILE.' NOT writable!');

	if ($continue) {

		$curl = curl_init();
		//$url='http://server-api.growatt.com/newPlantAPI.do?action=getUserCenterEnertyData';
		//$url='http://server.growatt.com/newPlantAPI.do?action=getUserCenterEnertyData';		// 20-08-2023 Updated URL
 		//curl_setopt($curl, CURLOPT_URL, $url);		
		curl_setopt($curl, CURLOPT_URL, DATA_URL);		//We should be logged in by now. Let's attempt to access a password protected page	
		curl_setopt($curl, CURLOPT_COOKIEJAR, COOKIE_FILE);					
		curl_setopt($curl, CURLOPT_COOKIEFILE, COOKIE_FILE); 
		curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);					
		curl_setopt($curl, CURLOPT_POSTFIELDS, "language=1" );
		curl_setopt($curl, CURLOPT_HTTPHEADER, HEADER);
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);						
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);						
		curl_setopt ($curl, CURLOPT_SSLVERSION, 1);
		$result = curl_exec($curl);											

		if(curl_errno($curl)){
			switch ($http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
 				case 200:   $continue=true;# OK
 	    					lg('Growatt inverter: Data: Expected HTTP code: '. $http_code);
        					break;
				case 302:   $continue=true;# OK
 	    					lg('Growatt inverter: Data: Expected HTTP code: '. $http_code);
        					break;        				
        		default:    $continue=false;
        					lg('Growatt inverter: Data: Unexpected HTTP code: '. $http_code);
			}
		}	
		curl_close($curl);
		
		if ($continue) {

			$data = json_decode($result, JSON_PRETTY_PRINT);
			
			if(!empty($data['todayStr']) && !empty($data['totalValue'])) {
			
				if lg('Growatt inverter: I did find JSON data to work with!');
				$nowpower = (float)$data['powerValue'];
				$todaypower = (float)$data['todayValue'];	// kWH			
				$allpower = (float)$data['totalValue'];		// [totalStr] => 1505.4kWh	[totalValue] => 1505.4
    				$allpower = $allpower*1000;			// Convert to Wh	

				$str=( $nowpower.';'. $allpower );	#times 1000 to convert the 0.1kWh to 100 WattHour and to convert 2.1kWh to 2100 WattHour
				lg('Growatt Inverter: '. $nowpower.' for domoticz: '.$str);
				ud(DOMOTICZDEVICE,0,$str,'GrowattInverter: Generation updated');
			}
		}
	}	
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
