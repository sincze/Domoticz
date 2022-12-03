<?PHP

/**
 * Pass2PHP
 * php version 7.4
 *
 * @category Home_Automation
 * @package  Pass2PHP FULL script for Goodwe Inverter
 * @author   Sándor Incze
 * @license  GNU GPLv3
 * @link     https://github.com/sincze/Domoticz
 * @version  1.1 (22-01-2022) 
 * @version  1.2 (02-12-2022) Api Changed from V1 to V3.
 **/

// $ sudo apt-get update && sudo apt-get upgrade
// $ sudo apt-get install php7.4 php7.4-curl php7.4-gd php7.4-imap php7.4-json php7.4- php7.4-cli
// $ cd /home/pi/domoticz/scripts
// $ mkdir pass2php
// $ cd pass2php
// $ Add the downloaded RAW file here
// In Domoticz:
// Create Virtual Sensor -> Electric (instant+Counter)
// Check device idx (Setup-> Devices) for created Virtual Sensor
// Check Setup -> Settings -> Local Networks if '127.0.0.*' is present.

// In growatt-inverter.php 
// Modify **** to your username  line 45
// Modify **** to your password  line 46
// Modify **** to your stationid line 47
// Modify DOMOTICZDEVICE **** to your IDX  line 48

// In terminal execute 'php /home/pi/domoticz/scripts/pass2php/Goodwe.php'
// No errors should be seen, check domoticz created virtual sensor device & log for errors.
// If device is updated continue.
// $ sudo nano /etc/crontab
// add line: */5 * * * *   root    php /home/pi/domoticz/scripts/pass2php/goodwe-inverter.php >/dev/null 2>&1  

error_reporting(E_ALL);				      // 14-04-2019 Pass2PHP 3.0
ini_set("display_errors","on");			// 14-04-2019 Pass2PHP 3.0
date_default_timezone_set('Europe/Amsterdam');
define('time',$_SERVER['REQUEST_TIME']);	// Time is here
define('domoticz','http://127.0.0.1:8080/');

define('USERNAME', '****');
define('PASSWORD', '****');
define('STATIONID', '****');
define('DOMOTICZDEVICE', ***);

$continue=true;
$debug=true;

define('USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.169 Safari/537.36');	//Set a user agent. 
//define('COOKIE_FILE','/var/log/GoodWe.cookie');													//Where our cookie information will be stored (need to be writable!)
define('COOKIE_FILE','/home/pi/domoticz/scripts/GoodWe.cookie');													//Where our cookie information will be stored (need to be writable!)

// URLS
define('GOODWELOGINURL', 'https://eu.semsportal.com/Home/Login');
define('CLIENTIPISVN', 'https://eu.semsportal.com/GopsApi/Post?s=v2/Common/CheckClientIpIsVN');
//define('GOODWEDATAURL', 'https://eu.semsportal.com/GopsApi/Post?s=v1/PowerStation/GetMonitorDetailByPowerstationId');
define('GOODWEDATAURL', 'https://eu.semsportal.com/GopsApi/Post?s=v3/PowerStation/GetPlantDetailByPowerstationId');

// REFERER
define('LOGIN_REFERER', 'https://eu.semsportal.com/Home/Login');
	
// HEADERS
define('LOGINHEADER',array( 'Accept: application/json, text/javascript, */*; q=0.01',
							'Accept-Encoding: gzip, deflate, br',
							'Accept-Language: nl-NL,nl;q=0.9,en-US;q=0.8,en;q=0.7',
							'X-Requested-With: XMLHttpRequest'
							)
		);

define('DATAHEADER',array(  'Accept: */*',
							'Accept-Encoding: gzip, deflate, br',
							'Accept-Language: nl-NL,nl;q=0.9,en-US;q=0.8,en;q=0.7',
							'X-Requested-With: XMLHttpRequest',
							)
		);

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

	if ($debug) lg('Goodwe inverter: 4. First I need to login!');
	$resultlogin=login(USERNAME, PASSWORD );
	if($resultlogin) {
		if ($debug) lg('Goodwe inverter: 5. I was able to login lets retrieve data!');	
		$cookieresult=retrieve_data();								// If cookie was created retrieve data
		if ($cookieresult===false) {
			if ($debug) lg('Goodwe inverter: 6. Something Failed retrieving data with '.COOKIE_FILE);	
		}
	}	
	else {
		if ($debug) lg('Goodwe inverter: 7. Sorry something went wrong with login procedure!');	
	}
}	

function login($username,$password)
{
	global $debug;
	global $continue;

    header('Content-Type: application/json');

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, GOODWELOGINURL);
	curl_setopt($curl, CURLOPT_POST, true);									
	curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query( postvalues_login() ));	
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);						//We don't want any HTTPS errors.
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);						//We don't want any HTTPS errors.
	curl_setopt($curl, CURLOPT_COOKIEFILE, COOKIE_FILE); 
	curl_setopt($curl, CURLOPT_COOKIEJAR, COOKIE_FILE);						//Where our cookie details are saved.
	curl_setopt($curl, CURLOPT_HTTPHEADER, LOGINHEADER);
	curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);	
	curl_setopt($curl, CURLOPT_REFERER, LOGIN_REFERER); 					
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);						
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);						//Do we want to follow any redirects?
	$result=curl_exec($curl);									//Execute the login request.
	print_r($result);

	if(curl_errno($curl)){
		switch ($http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
 			case 200:   $continue=true;# OK
 	    				lg('GoodWe: Login: Expected HTTP code: ', $http_code);
        				break;
			case 302:   $continue=true;# OK
 	    				lg('GoodWe: Login: Expected HTTP code: ', $http_code);
        				break;        				
        	default:    $continue=false;
        				lg('GoodWe: Login: Unexpected HTTP code: ', $http_code);
		}
	}
	curl_close($curl);
	
	if ($debug) {
		if (file_exists(COOKIE_FILE)) lg ('Goodwe inverter: Cookie File: '.COOKIE_FILE.' exists!'); else lg ('Goodwe inverter: Cookie File: '.COOKIE_FILE.' does NOT exist!');	
	}
	
	if (is_writable(COOKIE_FILE)) {
			if ($debug) lg('Goodwe inverter: Cookie File: '.COOKIE_FILE.' is writable!'); 			
			$continue=true; 
	} else { 
			if ($debug) lg('Goodwe inverter: Cookie File: '.COOKIE_FILE.' NOT writable!');
			$continue=false;
		}
	//interpret the body of the login to see if proper login values have been returned
	$jsonresult = json_decode($result, JSON_PRETTY_PRINT);
	if ($jsonresult['code']>=0){
			$continue=true; 
	} else { 
			if ($debug) lg('Goodwe inverter: login failed with message: '.$jsonresult['msg']);
			$continue=false;
		}
	return $continue;
}

function retrieve_data()
{	
	global $continue;
	global $debug;

	// Some check to see if we are from UN
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, CLIENTIPISVN);
	curl_setopt($curl, CURLOPT_POST, true);
	curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query( postvalues_clientip() ));	
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);						//We don't want any HTTPS errors.
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);						//We don't want any HTTPS errors.
	curl_setopt($curl, CURLOPT_COOKIEFILE, COOKIE_FILE); 
	curl_setopt($curl, CURLOPT_COOKIEJAR, COOKIE_FILE);						//Where our cookie details are saved.
	curl_setopt($curl, CURLOPT_HTTPHEADER, DATAHEADER);
	curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);						
	curl_setopt($curl, CURLOPT_REFERER, LOGIN_REFERER); 
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);						
	$result=curl_exec($curl);									//Execute the login request.
	curl_close($curl);

	// Retrieve DATA
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, GOODWEDATAURL);
	curl_setopt($curl, CURLOPT_POST, true);				
	curl_setopt($curl, CURLOPT_HTTPHEADER, DATAHEADER);
	curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query( postvalues_PowerstationId(STATIONID) ));
	curl_setopt($curl, CURLOPT_COOKIEJAR, COOKIE_FILE);	
	curl_setopt($curl, CURLOPT_COOKIEFILE, COOKIE_FILE);
	curl_setopt($curl, CURLOPT_REFERER, LOGIN_REFERER); 
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);	
	curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);			//Sets the user agennt		
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);				//We don't want any HTTPS / SSL errors.
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);				//We don't want any HTTPS / SSL errors.
	curl_setopt($curl, CURLOPT_SSLVERSION, 1);
	curl_setopt($curl, CURLOPT_ENCODING, '');	
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);						
	$result = curl_exec($curl);							
	//print('Result of query');
	//print_r($result);

	if(curl_errno($curl)){
		switch ($http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
				case 200:   $continue=true;# OK
	    					lg('Goodwe inverter: Data: Expected HTTP code: ', $http_code);
       					break;
			case 302:   $continue=true;# OK
	    					lg('Goodwe inverter: Data: Expected HTTP code: ', $http_code);
       					break;        				
       		default:    $continue=false;
       					lg('Goodwe inverter: Data: Unexpected HTTP code: ', $http_code);
		}
	}	
	curl_close($curl);

	if ($continue) {
			$data=extract_data($result);
			if ($data != false) {										// Continue if we have real data
				$current_watt= $data['current_watt'];
				$total_power = $data['total_power']*1000;
		    		$str = ( $current_watt.';'.$total_power);

				lg('Goodwe Inverter: '. $data['current_watt'].' for domoticz: '.$str);
				ud(DOMOTICZDEVICE,0,$str);
			}
			else {
					if ($debug) lg('Goodwe inverter: No Data to work with, not received data!');
					$continue=false;	
			}
	}
return $continue;														// close cURL resource, and free up system resources
}


function postvalues_login()
{
	$postValues = array(
		'account'	=> USERNAME,
		'pwd'		=> PASSWORD,
		'code'	    => ''
	);
	return $postValues;
}

function postvalues_clientip()
{
	$postValues = array(
		'str'	=> '{"api":"v2/Common/CheckClientIpIsVN"}'
	);
	return $postValues;
}

function postvalues_getdataformat()
{
	$postValues = array(
		'str'	=> '{"api":"v2/Common/GetDateFormatSettingList"}'		
	);
	return $postValues;
}

function postvalues_PowerstationId($token)
{
	$postValues = array(
//			'str'   => '{"api":"v1/PowerStation/GetMonitorDetailByPowerstationId","param":{"powerStationId":"'.$token.'"}}'
			'str'   => '{"api":"v3/PowerStation/GetPlantDetailByPowerstationId","param":{"powerStationId":"'.$token.'"}}'
		);
	return $postValues;
}


function extract_data($jsonresult)		// 16-11-2019
{	
	global $debug;
	$result=false;

	$jsonresult = json_decode($jsonresult, JSON_PRETTY_PRINT);
	//header('Content-Type: application/json');
	//print_r($jsonresult['data']);
	if(!empty($jsonresult['data']['kpi']['power']) && !empty($jsonresult['data']['kpi']['pac'])) {
		
		if ($debug) lg('Goodwe JSON results Found! '); 
		$result = array('today_power' => $jsonresult['data']['kpi']['power'], 'current_watt' => $jsonresult['data']['kpi']['pac'], 'total_power' => $jsonresult['data']['kpi']['total_power']);	
    }
	else {
		if ($debug)	lg('Goodwe (extract_data) NO JSON results Found! ');
		$result=false;
		}
	return $result; // This is an array with the results
}

function extract_token($queryresult)
{
	$token=false;
	$queryresult = json_decode($queryresult, JSON_PRETTY_PRINT);
	$token=$queryresult['data']['redirect'];
	$token = explode("/",$token);
	$token = $token[3];
	return $token;
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
