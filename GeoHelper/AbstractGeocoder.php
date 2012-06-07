<?php
/*
 * This file is part of the GeoHelper library.
 *
 * (c) Matthew Vince <matthew.vince@phaseshiftllc.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace GeoHelper;

/**
 * Base geocoder
 */
class AbstractGeocoder
{
	
   /**
    * Default calculation units (can be miles, kms or nms)
    * @var string
    */
   public static $default_units = 'miles';

   /**
    * Default calculation formula (can be sphere or flat)
    * @var string
    */
   public static $default_formula = 'sphere';

   /**
    * Multigeocoder provider order
    * @var array
    */
   public static $provider_order = array('Google', 'PlaceFinder');

   /**
    * Multigeocoder ip provider order
    * @var array
    */
   public static $ip_provider_order = array('GeoPlugin', 'HostIp');
   
   /**
    * Request timeout
    * @var integer
    */
   public static $request_timeout = 2;
   
   /**
    * Proxy address (if needed)
    * @var string
    */
   public static $proxy_address = null;
   
   /**
    * Proxy port (if needed)
    * @var integer
    */
   public static $proxy_port = null;
   
   /**
    * Proxy username (if needed)
    * @var string
    */
   public static $proxy_user = null;
   
   /**
    * Proxy password (if needed)
    * @var string
    */
   public static $proxy_pass = null;
   
   /**
    * Max number of socket reads
    * @var integer
    */
   public static $read_retries = 3;
   
   /**
    * Logger instance
    * Can be any object that responds to log($message, $level) - like syslog()
    * @var mixed
    */
   public static $logger = null;
      
   /**
    * Accuracy map for translating between accuracy (string) and precision (integer)
    * @var array
    */
   public static $accuracy_map = array(
      0 => 'unknown', 
      1 => 'country', 
      2 => 'state', 
      3 => 'county',
      4 => 'city',
      5 => 'zip',
      6 => 'street',
      7 => 'intersection',
      8 => 'address',
      9 => 'building',
   );
   
   /**
    * Geocode an address
    * @param string $address address to geocode
    * @param array $options options hash
    * @return Location Location object
    */
   public function geocode($address, $options = array())
   {
       // no geocode for this one
       return new Location();
   }
    
   /**
     * Reverse-geocode a lat long combination
     * @param mixed $latlng LatLong object or string containing lat/lng information
     * @param array $options options hash
     * @return Location Location object
     */
    public function reverseGeocode($latlng, $options = array())
    {
       // no reverse geocode for this one
       return new Location();
    }  
   
   /**
    * Build full address from result (if it's not provided in response)
    * @param Location $result location
    * @return string full address
    */
   protected function buildFullAddress($result)
   {
      $rc = '';
      
      if (trim($result->street_address) != '') {
         $rc .= $result->street_address . ', ';
      }
      if (trim($result->city) != '') {
         $rc .= $result->city . ', ';
      }
      if (trim($result->state) != '') {
         $rc .= $result->state . ', ';
      }
      if (trim($result->zip) != '') {
         $rc .= $result->zip . ', ';
      }
      if (trim($result->country_code) != '') {
         $rc .= $result->country_code;
      }
         
      return trim($rc, ' ,');
   }
   
   /**
    * Tries to guess accuracy based on returned results
    * @param Location $result location
    * @return string accuracy
    */
   protected function determineAccuracy($rc)
   {
      if (trim($rc->streetNumber()) != '') {
         return 'address'; 
      }
      if (trim($rc->street_address) != '') {
         return 'street';
      } elseif (trim($rc->zip) != '') {
         return 'zip';
      } elseif (trim($rc->city) != '') {
         return 'city';
      } elseif (trim($rc->state) != '') {
         return 'state';
      } elseif (trim($rc->country_code != '')) {
         return 'country';
      } else {
         return 'unknown';
      }
   }
   
   /**
    * Builds the parameter list for the request url
    * @param array $options options hash
    * @return string parameters
    */
   protected function buildParameterList($options)
   {
      $opts = array();
      foreach ($options as $key => $value) {
         if (!is_null($value)) {
            $opts[] = rawurlencode($key) . '=' . rawurlencode($value);
         }
      }
      
      return implode('&', $opts);
   }
   
   /**
    * Log message
    * @param string $message log message
    * @param int $level log level (syslog() level)
    */
   protected static function log($message, $level = LOG_INFO)
   {
      if (self::$logger && is_callable(array(self::$logger, 'log'))) {
         self::$logger->log($message, $level);
      }
   }
   
   /**
    * Makes HTTP request to geocoder service
    * @param string $url URL to request
    * @return string service response
    * @throws Exception if cURL library is not installed
    * @throws Exception on cURL error
    */
   protected function callWebService($url)
   {
      if (!function_exists('curl_init')) {
         throw new RuntimeException('The cURL library is not installed.');
      }
      
      $url_info = parse_url($url);
      
      $curl = curl_init();
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
      curl_setopt($curl, CURLOPT_URL, $url);
      curl_setopt($curl, CURLOPT_HEADER, false);
      curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
      curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, self::$request_timeout);
      curl_setopt($curl, CURLOPT_TIMEOUT, self::$request_timeout);
      
      // check for proxy
      if (!is_null(self::$proxy_address)) {
         curl_setopt($curl, CURLOPT_PROXY, self::$proxy_address . ':' . self::$proxy_port);
         curl_setopt($curl, CURLOPT_PROXYUSERPWD, self::$proxy_user . ':' . self::$proxy_pass);
      }

      // check for http auth:
      if (isset($url_info['user'])) {
         $user_name = $url_info['user'];
         $password = isset($url_info['pass']) ? $url_info['pass'] : '';
         
         curl_setopt($curl, CURLOPT_USERPWD, $user_name . ':' . $password);
      }
      
      $error = 'error';
      $retries = 0;
      while (trim($error) != '' && $retries < self::$read_retries) {
         $rc = curl_exec($curl);
         $error = curl_error($curl);
         $retries++;
      }
      curl_close($curl);
      
      if (trim($error) != '') {
         throw new Exception($error);
      }
      
      return $rc;
   }
}


/**
 * General geocoding error
 */
class GeoHelperException extends \Exception {}

/**
 * Too many queries to web service error
 */
class TooManyQueriesException extends GeoHelperException {}
