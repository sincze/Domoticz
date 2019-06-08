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
// Modify **** to your username  line 44
// Modify **** to your password  line 45
// Modify DOMOTICZDEVICE **** to your IDX  line 49
// In terminal execute 'php /home/pi/domoticz/scripts/pass2php/growatt-inverter.php'
// No errors should be seen, check domoticz created virtual sensor device & log for errors.
// If device is updated continue.
// $ sudo nano /etc/crontab
// add line: */5 * * * *   root    php /home/pi/domoticz/scripts/pass2php/growatt-inverter.php >/dev/null 2>&1  

error_reporting(E_ALL);				      // 14-04-2019 Pass2PHP 3.0
ini_set("display_errors","on");			// 14-04-2019 Pass2PHP 3.0
date_default_timezone_set('Europe/Amsterdam');
define('time',$_SERVER['REQUEST_TIME']);	// Time is here
define('domoticz','http://127.0.0.1:8080/');

retrieve_goodwe_data('test');

function retrieve_goodwe_data($command)
{
	define('USERNAME', '******');
	define('PASSWORD', '******');
	define('USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/74.0.3729.169 Safari/537.36');		//Set a user agent. 
	define('COOKIE_FILE','/home/pi/Goodwe.cookie');															//Where our cookie information will be stored (need to be writable!)

	define('DOMOTICZDEVICE', **);

	define('GOODWELOGINURL', 'https://eu.semsportal.com/Home/Login');
	define('LOGINHEADER',array( 'Accept: application/json, text/javascript, */*; q=0.01',
								'Accept-Language: en-US,en;q=0.9,nl-NL;q=0.8,nl;q=0.7',
								'X-Requested-With: XMLHttpRequest'
								)
			);

	define('DATAHEADER',array( 'Accept: */*',
							'Accept-Encoding: gzip, deflate, br',
							'Accept-Language: en-US,en;q=0.9,nl-NL;q=0.8,nl;q=0.7',
							'X-Requested-With: XMLHttpRequest'
						)
			);

	define('GOODWEDATAURL', 'https://eu.semsportal.com/GopsApi/Post?s=BigScreen/GetPowerStationPacByDay');
	
	$continue=true;
	$debug=true;

	$postValues = array(
		'account'	=> USERNAME,
		'pwd'		=> PASSWORD,
		'code'	=> ''
	);

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, GOODWELOGINURL);
	curl_setopt($curl, CURLOPT_POST, true);									                //Tell cURL that we want to carry out a POST request.
	curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postValues));	//Set our post fields / date (from the array above). 
	curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);						          //We don't want any HTTPS errors.
	curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);						          //We don't want any HTTPS errors.
	curl_setopt($curl, CURLOPT_COOKIEFILE, COOKIE_FILE); 
	curl_setopt($curl, CURLOPT_COOKIEJAR, COOKIE_FILE);						          //Where our cookie details are saved.
	curl_setopt($curl, CURLOPT_HTTPHEADER, LOGINHEADER);
	curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);						//Sets the user agent. Some websites will attempt to block bot user agents. 
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);						  //Tells cURL to return the output once the request has been executed.	
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);						  //Do we want to follow any redirects?
	$result=curl_exec($curl);												              //Execute the login request.
	
	$token=extract_token($result);
	
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
		if (file_exists(COOKIE_FILE)) lg ('Cookie File: '.COOKIE_FILE.' exists!'); else lg ('Cookie File: '.COOKIE_FILE.' does NOT exist!');	
	}
	
	if (is_writable(COOKIE_FILE)) {
			if ($debug) lg('Cookie File: '.COOKIE_FILE.' is writable!'); 
			$continue=true; 
	} else { 
			if ($debug) lg('Cookie File: '.COOKIE_FILE.' NOT writable!');
			$continue=false;
	}

	if ($continue) {
		$postValues = array(		
			'str'	=> '{"api":"BigScreen/GetPowerStationPacByDay","param":{"id":"'.$token.'","date":""}}'
		);

		$referer='https://eu.semsportal.com/PowerStation/PlantDetailCharts/'.$token;

		$curl = curl_init();
		//curl_setopt($curl, CURLOPT_URL, GOODWEDATAURL.$token);
		curl_setopt($curl, CURLOPT_URL, GOODWEDATAURL);
		curl_setopt($curl, CURLOPT_POST, true);				
		curl_setopt($curl, CURLOPT_HTTPHEADER, DATAHEADER);
		curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postValues));
		curl_setopt($curl, CURLOPT_COOKIEJAR, COOKIE_FILE);	
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
 	    					lg('Growatt inverter: Data: Expected HTTP code: ', $http_code);
        					break;
				case 302:   $continue=true;# OK
 	    					lg('Growatt inverter: Data: Expected HTTP code: ', $http_code);
        					break;        				
        		default:    $continue=false;
        					lg('Growatt inverter: Data: Unexpected HTTP code: ', $http_code);
			}
		}	
		curl_close($curl);

		if ($continue) {

			$data=extract_data($result);
			$str=( $data['current_watt'].';'. $data['today_power']*1000 );
			lg('Goodwe Inverter: '. $data['current_watt'].' for domoticz: '.$str);
			ud(DOMOTICZDEVICE,0,$str);
		}		
	}
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

function extract_data($queryresult)
{
	$result=false;
	$queryresult = json_decode($queryresult, JSON_PRETTY_PRINT);
	$result = array('today_power' => $queryresult['data']['today_power'], 'current_watt' => $queryresult['data']['pac']);	
	return $result;
}

/*
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
*/

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
