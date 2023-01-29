<?PHP

/**
 * Pass2PHP
 * php version 7.4
 *
 * @category Home_Automation
 * @package  Pass2PHP FULL script for Ziggo Connect Modem (White Box)
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
// Create Virtual Sensors (3x) -> Temp1, Temp2, Text
// Check device idx (Setup-> Devices) for created Virtual Sensor
// Check Setup -> Settings -> Local Networks if '127.0.0.*' is present.
// In ziggo.php 
// Modify **** to your ZIGGO IP address line 43
// Modify **** to your password  line 44
// Modify DOMOTICZDEVICE tempsensor_idx**** to your IDX line 54
// Modify DOMOTICZDEVICE tempsenor_idx_tunner**** to your IDX line 55
// Modify DOMOTICZDEVICE connectstatus **** to your IDX line 56
// In terminal execute 'php /home/pi/domoticz/scripts/pass2php/ziggo.php'
// No errors should be seen, check domoticz created virtual sensor device & log for errors.
// If device is updated continue.
// $ sudo nano /etc/crontab
// add line: * * * * *   root    php /home/pi/domoticz/scripts/pass2php/ziggo.php >/dev/null 2>&1  

error_reporting(E_ALL);				      // 14-04-2019 Pass2PHP 3.0
ini_set("display_errors","on");			// 14-04-2019 Pass2PHP 3.0
date_default_timezone_set('Europe/Amsterdam');
define('time',$_SERVER['REQUEST_TIME']);	// Time is here

define('domoticz','http://127.0.0.1:8080/');

$baseurl='192.168.100.1';
$password='pwd for ziggobox';

$continue=true;
$debug=true;
$SessionId=false;
$SID=false;
$header=false;
$body=false;
$fun=1;

$tempsensor_idx=xxxx;
$tempsensor_idx_tunner=xxxx;
$connectstatus=xxxx;

define('USERNAME', 'NULL');
define('PASSWORD',  hash('sha256', $password));
define('USER_AGENT', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/104.0.0.0 Safari/537.36');	// Set a user agent. 

define('LOGINURL', $baseurl.'/common_page/login.html');

define('LOGINHEADER',array( 
		'Accept: */*'		
		)
	);
	
define('LOGINDATAURL', $baseurl.'/xml/setter.xml');
define('LOGINDATAURL_GETTER', $baseurl.'/xml/getter.xml');

define('LOGINDATAHEADER',array( 'Accept: text/plain, */*; q=0.01',
                            'Accept-Encoding: gzip, deflate',
							'Accept-Language: en-GB,en;q=0.9,nl-NL;q=0.8,nl;q=0.7,en-US;q=0.6',
							'X-Requested-With: XMLHttpRequest',
							'Content-Type: application/x-www-form-urlencoded; charset=UTF-8'
							)
		);

define('LOGIN_URL_REFERER', $baseurl.'/common_page/login.html');
define('LOGIN_DATAURL_REFERER', $baseurl.'/index.html');

header('Content-Type: application/json');

	getsession();
	login();
  // If you want to explore everything....
	//$funs = array(1,2,3,5,6,10,11,12,13,16,19,21,22,24,100,103,105,107,109,111,115,117,119,121,123,124,136,137,143,144,147,199,300,302,305,307,309,311,313,315,317,322,323,324,325,326,500,502,503,504);
	$funs = array(1,2,136,144,503,13);
	//var_dump($funs);
	foreach ($funs as $fun) {
		$outcome = (data_request($fun));
		if (is_array($outcome) || is_object($outcome)) {
			//print_r($outcome);
			if ($fun == 1) echo "Title ".$outcome->title.PHP_EOL;
			if ($fun == 1) {
				echo "Operational Status ".$outcome->operStatus.PHP_EOL;
				$state=$outcome->operStatus;
				$operator=$outcome->OperatorId;
				
				if ($state==1) { if (get_text($connectstatus) != "OPERATIONAL") ud($connectstatus,0,"OPERATIONAL"); }
				else { if (get_text($connectstatus) != "FAILURE") ud($connectstatus,0,"FAILURE"); }
			}
			if ($fun == 1) echo "Operator ".$outcome->OperatorId.PHP_EOL;
			if ($fun == 2) { echo "Uptime ".$outcome->cm_system_uptime.PHP_EOL; echo "Serienummer ".$outcome->cm_serial_number .PHP_EOL; }
			if ($fun == 136) 
			{
				echo "Device Temperatuur is: ".$outcome->Temperature.PHP_EOL;		  // 61
				$temp=$outcome->Temperature;
                        	if ($temp != get_temp($tempsensor_idx)) ud($tempsensor_idx,0,$temp);
			}
			if ($fun == 136) {
				echo "Tunner Temperatuur is: ".$outcome->TunnerTemperature.PHP_EOL;  // 80
				$temp=$outcome->TunnerTemperature;
                        	if ($temp != get_temp($tempsensor_idx_tunner)) ud($tempsensor_idx_tunner,0,$temp);
			}

			if ($fun == 136) echo "Device Operational State is: ".$outcome->OperState.PHP_EOL;
			if ($fun == 144) echo "Device Docsis Version: ".$outcome->cm_docsis_mode.PHP_EOL;
			if ($fun == 503) echo "Device MTA Last event: ".$outcome->MtaEventLog[0]->timestamp.PHP_EOL;
			if ($fun == 503) echo "Device MTA event priority: ".$outcome->MtaEventLog[0]->priority.PHP_EOL;
			if ($fun == 503) echo "Device MTA event code: ".$outcome->MtaEventLog[0]->code.PHP_EOL;
			if ($fun == 503) echo "Device MTA Last event message: ".$outcome->MtaEventLog[0]->message.PHP_EOL;
			if ($fun == 13) { 							
								echo 'Device log:'.PHP_EOL;
								echo '-----------'.PHP_EOL;
								foreach ($outcome->eventlog as $event) {
								//echo $event->time.' '.$event->prior.' '.$event->text.PHP_EOL;
								echo $event->time.' '.$event->prior.' '.parse_modemlog($event->text).PHP_EOL;
								}
							}

		}
	}

// HELPER FUNCTIONS

function getsession()
{
	global $SID, $SessionId,$header,$body;

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, LOGINURL);
	curl_setopt($curl, CURLOPT_HTTPHEADER, LOGINHEADER);
	curl_setopt($curl, CURLOPT_HEADER, true);								// true to include the header in the output. (needed for Set-Cookie sessionToken
	curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);			// Sets the user agent. Some websites will attempt to block bot user agents. 
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);				// Tells cURL to return the output once the request has been executed.	(	true to return the transfer as a string of the return value of curl_exec() instead of outputting it directly.)
	$result=curl_exec($curl);

	$header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
	$header = substr($result, 0, $header_size);
	$body = substr($result, $header_size);
	curl_close($curl);

	$SessionId=parse_result($result);
}

function login()
{
	global $SID, $SessionId,$header,$body;

	$postValues = array(
		'token'	=> $SessionId,
		'fun'		=> 15,
		'Username' => USERNAME,
		'Password' => PASSWORD
	);
	
	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, LOGINDATAURL);
	curl_setopt($curl, CURLOPT_POST, true);									// Tell cURL that we want to carry out a POST request.
	curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postValues));	// Set our post fields / date (from the array above). 
	curl_setopt($curl, CURLOPT_REFERER, LOGIN_URL_REFERER);
	curl_setopt($curl, CURLOPT_HTTPHEADER, LOGINDATAHEADER);
	curl_setopt($curl, CURLOPT_HEADER, true);
	curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);			// Sets the user agent. Some websites will attempt to block bot user agents. 
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);				// Tells cURL to return the output once the request has been executed.	
	$result=curl_exec($curl);												        // Execute the login request. (Expected successful;SID=XXXXXXXX )

	$header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
	$header = substr($result, 0, $header_size);
	$body = substr($result, $header_size);
	curl_close($curl);
	$SessionId=parse_result($result);
	$SID=parse_SID($result);
}

function parse_result($result)
{
	$sessionToken=false;
	preg_match_all('/^Set-Cookie:\s*([^;]*)/mi', $result, $matches);
	$cookies = array();
	foreach($matches[1] as $item) {
    parse_str($item, $cookie);
    $cookies = array_merge($cookies, $cookie);
	}
	$sessionToken= $cookies["sessionToken"];
return $sessionToken;
}

function parse_SID($result)
{
	$SID=false;
	$array = explode("\n", $result);  // Parse te cookie, last line is what we are interested in.
	
	foreach($array as $item) {
		if (str_contains($item, 'SID')) { 
			$SID = preg_replace("/[^0-9]/", "", $item ); // remove all non numeric
			break;
		}
	}
return $SID;
}

function parse_modemlog($message)
{
  $message = explode(';',$message);
  return $message[0];
}



function data_request($fun)
{
	global $debug, $SID, $SessionId,$header,$body;
    
	if ($debug) echo('Function called with fun: '.$fun);

	$postValues = array(
		'token'	=> $SessionId,
		'fun'		=> $fun
	);

	if ($debug) print_r($postValues);

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, LOGINDATAURL_GETTER);
	curl_setopt($curl, CURLOPT_POST, true);									// Tell cURL that we want to carry out a POST request.
	curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postValues));	// Set our post fields / date (from the array above). 
	curl_setopt($curl, CURLOPT_COOKIE, "Cookie: SID=".$SID."; sessionToken-".$SessionId);
	curl_setopt($curl, CURLOPT_REFERER, LOGIN_DATAURL_REFERER);
	curl_setopt($curl, CURLOPT_HEADER, true);
	curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);			// Sets the user agent. Some websites will attempt to block bot user agents. 
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);		  	// Tells cURL to return the output once the request has been executed.	
	curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);				// Do we want to follow any redirects?
	$result=curl_exec($curl);												        // Execute the login request.
	//print_r($result);

	$header_size = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
	$header = substr($result, 0, $header_size);
	$body = substr($result, $header_size);
	curl_close($curl);
	$SessionId=parse_result($result);
	if ($debug) echo 'Session ID for fun: '.$fun.' is '.$SessionId.' '.PHP_EOL;
	$xml=simplexml_load_string($body);
	return $xml;
	//print_r($body);
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

function get_status($idx)
{
        $url = (domoticz.'json.htm?type=devices&rid='.$idx);
        $result = json_decode (curl ($url), true);
        $result = array_shift($result['result']);
        //echo 'Device '.$result['Name'].' has the following status in domoticz '.$result['Status'];
        return $result['Status'];
}

function get_temp($idx)
{
        $url = (domoticz.'json.htm?type=devices&rid='.$idx);
        $result = json_decode (curl ($url), true);
        $result = array_shift($result['result']);
        return (round($result['Temp']));
}

function get_text($idx)
{
	$url = (domoticz.'json.htm?type=devices&rid='.$idx);
	$result = json_decode (curl ($url), true);
	$result = array_shift($result['result']);
	return ($result['Data']);
}

function curl($url){
	$headers=array('Content-Type: application/x-www-form-urlencoded; charset=UTF-8');
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
