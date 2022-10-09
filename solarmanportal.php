#!/usr/bin/php
<?PHP

/**
 * Pass2PHP
 * php version 7.4
 *
 * @category Home_Automation
 * @package  Pass2PHP lightscript for retrieving DOMOTICZ data of SOLIS / OMNIK Inverters from SOLARMAN portal
 * @author   Sándor Incze
 * @license  GNU GPLv3
 * @link     https://github.com/sincze/Domoticz
 * 
 * @Version  09-10-2022 Initial Release
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

// In solarmanportal.php 
// Modify **** to your ID obtained from the SOLARMAN portal *line 47*
// Modify **** to your Plant_ID obtained from the SOLARMAN portal *line 48*
// Modify DOMOTICZDEVICE **** to your IDX  *line 49*

// In terminal execute 'php /home/pi/domoticz/scripts/pass2php/solarmanportal.php'
// No errors should be seen, check domoticz created virtual sensor device & log for errors.
// If device is updated continue.

// $ sudo nano /etc/crontab
// add line: */5 * * * *   root    php /home/pi/domoticz/scripts/pass2php/solarmanportal.php >/dev/null 2>&1  

error_reporting(E_ALL);						// 14-04-2019 Pass2PHP 3.0
ini_set("display_errors","on");				// 14-04-2019 Pass2PHP 3.0
date_default_timezone_set('Europe/Amsterdam');
define('time',$_SERVER['REQUEST_TIME']);	
define('domoticz','http://127.0.0.1:8080/');

define('solarman_user_id','****');			
define('solarman_plant_id','****');
define('DOMOTICZDEVICE', ****);			// Your IDX in here

$continue=true;
$debug=true;

$inverters = array(     
	array( 'device' => DOMOTICZDEVICE, 'id' => solarman_user_id, 'plant_id' => solarman_plant_id)
);

foreach ($inverters as $inverter) {

	$url= 'http://apic-cdn.solarman.cn/v/ap.2.0/plant/get_plant_overview?uid='.$inverter['id'].'&plant_id='.$inverter['plant_id'];

	$curl = curl_init();
	curl_setopt($curl, CURLOPT_URL, $url);		
	curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($curl, CURLOPT_CONNECTTIMEOUT,2);							
	$result = curl_exec($curl);									
  
	if(curl_errno($curl)){
		switch ($http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
		 	case 200:   $continue=true;# OK
					 	lg('Solarman inverter: Data: Expected HTTP code: ', $http_code);
						break;
			case 302:   $continue=true;# OK
					 	lg('Solarman inverter: Data: Expected HTTP code: ', $http_code);
						break;        				
			default:    $continue=false;
						lg('Solarman inverter: Data: Unexpected HTTP code: ', $http_code);
		}
	}
	curl_close($curl);

	if ($continue) {
		$data=extract_data($result);
		if ($data != false) {										// Continue if we have real data
			$current_watt= $data['current_watt'];
			$total_power = $data['total_power'];
			$str = ( $current_watt.';'.$total_power);

      lg('Solarman '.$inverter['device'].' Inverter: '. $data['current_watt'].' for domoticz: '.$str);
			print('Solarman '.$inverter['device'].' Inverter: '. $data['current_watt'].' for domoticz: '.$str);
			echo PHP_EOL;
			ud($inverter['device'],0,$str);
		}
		else {
				if ($debug) lg('Solarman '.$inverter['device'].' inverter: No Data to work with, not received data!');
				$continue=false;	
		}
	}
}


function extract_data($jsonresult)		// 16-11-2019
{	
	global $debug;
	$result=false;

	$jsonresult = json_decode($jsonresult, JSON_PRETTY_PRINT);
	//header('Content-Type: application/json');
	//print_r($jsonresult['data']);
	if(!empty($jsonresult['power_out']['energy_accu']) && !empty($jsonresult['power_out']['power'])) {		
		if ($debug) lg('Solarman JSON results Found! '); 
		$result = array('current_watt' => $jsonresult['power_out']['power'], 'total_power' => $jsonresult['power_out']['energy_accu']);	
	}
	else {
		if ($debug)	lg('Solarman (extract_data) NO JSON results Found! ');
		$result=false;
		}
	return $result; // This is an array with the results
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
