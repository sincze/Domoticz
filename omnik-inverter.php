#!/usr/bin/php
<?PHP

/**
 * Pass2PHP
 * php version 7.0
 *
 * @category Home_Automation
 * @package  Pass2PHP FULL script for Omnik Inverter, needs to be used with regular Pass2PHP
 * @author   Sándor Incze
 * @license  GNU GPLv3
 * @link     https://github.com/sincze/Domoticz
 **/
  
define('rootdir','/var/www/html/secure2/');
require(rootdir.'pass2php_include/settings.php');

if(isset($_REQUEST['text'])){ retrieve_omnik_data_api($_REQUEST['text']); }             // 02-04-2019 OMNIK API CHANGED!, The Command to parse is ; separated

function retrieve_omnik_data_api($command)			// 01-4-2019 NEW API IMPLEMENTED
{
    define('USERNAME', 'guest');			//The username or email address of the account.
    define('PASSWORD', '');					//The password of the account.
    define('USER_AGENT', 'Mozilla/5.0 (Windows NT 5.1) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.2309.372 Safari/537.36');			//Set a user agent. This basically tells the server that we are using Chrome ;)
    define('COOKIE_FILE', 'cookie.txt');	//Where our cookie information will be stored (needed for authentication).
 
    define('LOGIN_FORM_URL', 'https://www.omnikportal.com/Terminal/TerminalDefault.aspx?come=Public');		//URL of the login form.
    define('LOGIN_ACTION_URL', 'https://www.omnikportal.com/Terminal/TerminalDefault.aspx?come=Public');	//Login action URL. Sometimes, this is the same URL as the login form.
    $continue=true;
  
    $postValues = array(
        'username' => USERNAME,
        'password' => PASSWORD
    );

    $curl = curl_init();														//Initiate cURL.
    curl_setopt($curl, CURLOPT_URL, LOGIN_ACTION_URL);							//Set the URL that we want to send our POST request to. In this	case, it's the action URL of the login form.
    curl_setopt($curl, CURLOPT_POST, true);										//Tell cURL that we want to carry out a POST request.
    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($postValues));		//Set our post fields / date (from the array above). 
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);							//We don't want any HTTPS errors.
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);							//We don't want any HTTPS errors.
    curl_setopt($curl, CURLOPT_COOKIEJAR, COOKIE_FILE);							//Where our cookie details are saved. This is typically required for authentication, as the session ID is usually saved in the cookie file.
    curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);							//Sets the user agent. Some websites will attempt to block bot user agents. //Hence the reason I gave it a Chrome user agent.
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);							//Tells cURL to return the output once the request has been executed.
    curl_setopt($curl, CURLOPT_REFERER, LOGIN_FORM_URL);						//Allows us to set the referer header. In this particular case, we are fooling the server into thinking that we were referred by the login form.
    curl_setopt($curl, CURLOPT_SSLVERSION, 4);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);							//Do we want to follow any redirects?
    curl_exec($curl);															//Execute the login request.
 
	if(curl_errno($curl)){
		switch ($http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
 			case 200:   $continue=true;# OK
 	    				//lg('Omnikinverter: Login: Expected HTTP code: ', $http_code);
        				break;
        	default:    $continue=false;
        				lg('Omnikinverter: Login: Unexpected HTTP code: ', $http_code);
		}
	}

  	if ($continue) {

	    $inverters = array(     
	                        array( 'device' => "Omnik_1", 'id' => omnik_id_1 ),  // My PV id's that I monitor.
	                        array( 'device' => "Omnik_2", 'id' => omnik_id_2 ),
	                        array( 'device' => "Omnik_3", 'id' => omnik_id_3 )
	                        );

	    foreach ($inverters as $inverter) {
	        $url='https://www.omnikportal.com/AjaxService.ashx?ac=upTerminalMain&psid='.$inverter['id'].'&random='.rand();
	        //lg('Omnikinverter: '.$inverter['device'].' '.$url);
	        curl_setopt($curl, CURLOPT_URL, $url);						//We should be logged in by now. Let's attempt to access a password protected page
	        curl_setopt($curl, CURLOPT_COOKIEJAR, COOKIE_FILE);			//Use the same cookie file.
	        curl_setopt($curl, CURLOPT_USERAGENT, USER_AGENT);			//Use the same user agent, just in case it is used by the server for session validation.
	        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);				//We don't want any HTTPS / SSL errors.
	        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);				//We don't want any HTTPS / SSL errors.
	        curl_setopt($curl, CURLOPT_SSLVERSION, 4);
	        
	        $result = curl_exec($curl);									// Execute the GET request and print out the result.
	
			if(curl_errno($curl)){
				switch ($http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE)) {
 					case 200:   $continue=true;# OK
 	    						//lg('Omnikinverter: Data: Expected HTTP code: ', $http_code);
        						break;
        			default:    $continue=false;
        						lg('Omnikinverter: Data: Unexpected HTTP code: ', $http_code);
				}
			}	
		
			if ($continue) {

		        $data = json_decode($result, JSON_PRETTY_PRINT);

		        $nowpower = $data[0]['nowpower'];
		        $todaypower = $data[0]['daypower'];                        

		        $nowpower = filter_var( $nowpower, FILTER_SANITIZE_NUMBER_FLOAT);
		        $todaypower = filter_var( $todaypower, FILTER_SANITIZE_NUMBER_FLOAT);
		               
		        $str=( (round($nowpower)*10).';'.($todaypower*10) );
		        lg('Omnikinverter: '.$inverter['device'].' result: '.(round($nowpower)*10).' and: '.($todaypower*10).' for domoticz: '.$str);
		        if (isset($nowpower) && isset($todaypower) ) ud($inverter['device'],0,$str,'Omnikinverter: Generation updated');
		    }
		}
	}
	curl_close($curl);														// close cURL resource, and free up system resources
}

?>
