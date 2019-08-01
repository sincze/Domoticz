<?PHP

/**
 * Pass2PHP
 * php version 7.0
 *
 * @category Home_Automation
 * @package  Pass2PHP FULL script for Goodwe Inverter
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
// Modify **** to your username  line 47
// Modify **** to your password  line 48
// Modify DOMOTICZDEVICE **** to your IDX  line 55
// In terminal execute 'php /home/pi/domoticz/scripts/pass2php/goodwe-inverter.php'
// No errors should be seen, check domoticz created virtual sensor device & log for errors.
// If device is updated continue.
// $ sudo nano /etc/crontab
// add line: */5 * * * *   root    php /home/pi/domoticz/scripts/pass2php/goodwe-inverter.php >/dev/null 2>&1  

error_reporting(E_ALL);				      // 14-04-2019 Pass2PHP 3.0
ini_set("display_errors","on");			// 14-04-2019 Pass2PHP 3.0
date_default_timezone_set('Europe/Amsterdam');
define('time',$_SERVER['REQUEST_TIME']);	// Time is here
define('domoticz','http://127.0.0.1:8080/');

retrieve_goodwe_data('test');

function retrieve_goodwe_data($command)
{
	global $debug;
	global $continue;

	define('USERNAME', '****');
	define('PASSWORD', '****');
	define('USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.169 Safari/537.36');		//Set a user agent. 
	define('COOKIE_FILE','/var/tmp/Goodwe.cookie');												//Where our cookie information will be stored (need to be writable!)

	define('GOODWELOGINURL', 'https://www.semsportal.com/Home/Login');		
	define('GOODWEDATAURL', 'https://www.semsportal.com/PowerStation/PowerStatusSnMin/');
	
	define('DOMOTICZDEVICE', ***);																														

	//$continue=true;
	//$debug=false;

	$postValues = array(
		'account'	=> USERNAME,
		'pwd'		=> PASSWORD,
		'code'	=> ''
	);

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, GOODWELOGINURL);
	curl_setopt($curl, CURLOPT_POST, true);									//Tell cURL that we want to carry out a POST request.
	curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postValues));	//Set our post fields / date (from the array above). 
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);						//We don't want any HTTPS errors.
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);						//We don't want any HTTPS errors.
	curl_setopt($curl, CURLOPT_COOKIEJAR, COOKIE_FILE);						//Where our cookie details are saved.
	curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);						//Sets the user agent. Some websites will attempt to block bot user agents. 
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);						//Tells cURL to return the output once the request has been executed.	
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);						//Do we want to follow any redirects?
	$result=curl_exec($curl);												//Execute the login request.
		
	$token=extract_token($result);											//Let's extract the token for a new session, just because we can.

	if(curl_errno($curl)){
		switch ($http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
 			case 200:   $continue=true;# OK
 	    				lg('GoodWe: Login: Expected HTTP code: '.$http_code);
        				break;
			case 302:   $continue=true;# OK
 	    				lg('GoodWe: Login: Expected HTTP code: '.$http_code);
        				break;        				
        	default:    $continue=false;
        				lg('GoodWe: Login: Unexpected HTTP code: '.$http_code);
		}
	}	
	curl_close($curl);															// End of login web requests
	
	if ($debug) {
		if (file_exists(COOKIE_FILE)) lg ('GoodWe Cookie File: '.COOKIE_FILE.' exists!'); else lg ('Cookie File: '.COOKIE_FILE.' does NOT exist!');	
	}
	
	if (is_writable(COOKIE_FILE)) {
			if ($debug) lg('GoodWe Cookie File: '.COOKIE_FILE.' is writable!'); 
			$continue=true; 
	} else { 
			if ($debug) lg('GoodWe  Cookie File: '.COOKIE_FILE.' NOT writable!');
			$continue=false;
	}

	if ($continue && $token != false) {								// Continue only to retrieve data if no web errors and we have a token
													
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, GOODWEDATAURL.$token);
		curl_setopt($curl, CURLOPT_COOKIEFILE, COOKIE_FILE); 
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);	
		curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);			//Sets the user agennt		
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);				//We don't want any HTTPS / SSL errors.
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);				//We don't want any HTTPS / SSL errors.
		curl_setopt ($curl, CURLOPT_SSLVERSION, 1);
		$result = curl_exec($curl);							
		
		if(curl_errno($curl)){
			switch ($http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
 				case 200:   $continue=true;# OK
 	    					lg('Goodwe inverter: Data: Expected HTTP code: '.$http_code);
        					break;
				case 302:   $continue=true;# OK
 	    					lg('Goodwe inverter: Data: Expected HTTP code: '.$http_code);
        					break;        				
        		default:    $continue=false;
        					lg('Goodwe inverter: Data: Unexpected HTTP code: '.$http_code);
			}
		}	
		curl_close($curl);											// End of DATA web requests
	}

	if ($continue) {									// Continue only if no errors occured with the web request

			$result=getStringpart($result);				// We need a Json string from the webrequest data
			$data=extract_data($result);				// We want the data in a nice format like JSON
			if ($data != false) {						// Continue if we have real data
				if ($debug) lg ('Goodwe inverter: Valid domoticz data retrieved!');
				$str=( $data['current_watt'].';'.$data['total_power']*1000 );			// Convert to Wh	
				lg('Goodwe Inverter: '. $data['current_watt'].' W, today: '.$data['today_power']. ' kWh, Inverter total: '.$data['total_power'].' kwH for domoticz: '.$str);
				ud(DOMOTICZDEVICE,0,$str);
			}	
			else lg ('Goodwe inverter: NO Valid domoticz data retrieved!');
		}		

}


function getStringpart($string) 			// Generate Nice JSON file from scraped data
{
	$begin = 'var pw_info = ';
	$end ='}};';

	$startpos=strpos($string,$begin);
	$endpos=strpos($string,$end,$startpos);
	$endpos=$endpos-$startpos;
	$string=substr($string,$startpos+14,$endpos-12);

return $string;
}


function extract_data($jsonresult)
{	
	$debug=true;
	$result=false;

	$jsonresult = json_decode($jsonresult, JSON_PRETTY_PRINT);
	if(!empty($jsonresult['kpi']['pac']) && !empty($jsonresult['kpi']['power'])) {
		
		if ($debug) lg('Goodwe JSON results Found! '); 
/*
		if (scheme() =="night") {
			$result = array('today_power' => $jsonresult['kpi']['power'], 'current_watt' => 0, 'total_power' => $jsonresult['kpi']['total_power']);	// If it is dark outside... there is no production!
		}
		else $result = array('today_power' => $jsonresult['kpi']['power'], 'current_watt' => $jsonresult['kpi']['pac'], 'total_power' => $jsonresult['kpi']['total_power']);	
*/
		$result = array('today_power' => $jsonresult['kpi']['power'], 'current_watt' => $jsonresult['kpi']['pac'], 'total_power' => $jsonresult['kpi']['total_power']);

	}
	else {
		if ($debug)	lg('Goodwe NO JSON results Found! ');
		$result=false;
		}
	return $result; // This is an array with the results
}

function extract_token($queryresult)
{
	global $debug;
	$token=false;
	
	$queryresult = json_decode($queryresult, JSON_PRETTY_PRINT);

	if(!empty($queryresult['data']['redirect'])) {
		$token=$queryresult['data']['redirect'];
		$token = explode("/",$token);
		if (array_key_exists('3',$token)) { 		
			if ($debug) lg('Goodwe token exists! '.$token[3]); 
			$token = $token[3];
		}  
		else {
			if ($debug) lg('Goodwe token does NOT exist! ');
			$token=false;
		}
	}
	else {
		if ($debug) lg('Goodwe data field not found or invalid! ');
	}			
		
	return $token;
}



/*
function extract_token($queryresult)
{
	global $debug;
	$token=false;
	//$debug=false;
	
	$queryresult = json_decode($queryresult, JSON_PRETTY_PRINT);

	$token=$queryresult['data']['redirect'];
	$token = explode("/",$token);
	if (array_key_exists('3',$token)) { 		
		if ($debug) lg('Goodwe token exists! '.$token[3]); 
		$token = $token[3];
	}  
	else {
		if ($debug) lg('Goodwe token does NOT exist! ');
		$token=false;
	}
	return $token;
}
*/


function extract_SessionId($cookie_file)
{
	$sessionId=false;
	
	if (file_exists($cookie_file))
	{
  		$fn = fopen(COOKIE_FILE,"r");
   		while(! feof($fn))  {
		$result = fgets($fn);
		if (strpos($result, 'ASP.NET_SessionId') !== false) {
    	    $result = str_replace(array("\n", "\r"), '', $result);		// Remove new line delimter
    	    $result = preg_split("/[\t]/", $result);					// Create an array
    		$sessionId=$result[6];
			}	  		
  		}
  	fclose($fn);
	}
return $sessionId;
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
