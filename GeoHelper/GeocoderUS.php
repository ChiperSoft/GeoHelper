<?php 

namespace GeoHelper;
use Support\Bounds, Support\LatLong, Support\Location;


/**
 * Geocoder.us
 * @see http://geocoder.us/help/
 */
class GeocoderUs extends AbstractGeocoder
{
   /**
    * Geocoder us key (optional) 'username:password'
    * @var string
    */
   public static $key;
   
   
   /**
    * Geocode an address
    * @param string $address address to geocode
    * @param array $options options hash
    * @return Location Location object
    */
   public function geocode($address, $options = array())
   {
      $default_options = array(
         'address' => ($address instanceof Location) ? $address->toGeocodableString() : $address,
         'parse_address' => '1',
      );
      $options = array_merge($default_options, $options);
      
      // check for login info
      if (self::$key) {
         $url = "http://" . self::$key . "@geocoder.us/member/service/namedcsv?%s";
      } else {
         $url = "http://geocoder.us/service/namedcsv/?%s";
      }
      
      try {
         $url = sprintf($url, $this->buildParameterList($options));
         $result = $this->callWebService($url);
      } catch (Exception $e) {
         // error contacting service
         return new Location();
      }
      
      self::log("Geocoder.us geocoding. Address: " . $address . ". Result: " . $result, LOG_INFO);
      
      return $this->parseResult($result);
   }
   
   /**
    * Parse result into Location
    * @param string $result result body
    * @return Location Parsed location data
    */
   protected function parseResult($result)
   {
      $addresses = array_map('trim', explode("\n", $result));
      $original = array_shift($addresses);
      
      if (substr($original, 0, 6) == 'error=') {
         // error!
         return new Location();
      }

      $loc = null;
      foreach ($addresses as $address) {
         if (trim($address) != '') {
            $result = $this->extractResult($address);
            if (is_null($loc)) {
               // first iteration and main result
               $loc = $result;
               $loc->all[] = $result;
            } else {
               // subsequent iteration (sub matches)
               $loc->all[] = $result;
            }
         }
      }
      
      return $loc;
   }
   
   /**
    * Extracts locations from the response
    * @param array $result response results
    * @return Location porsed location data
    */
   protected function extractResult($result)
   {
      $rc = new Location();
      $rc->provider = 'geocoderus';

      $named_parts = array_map('trim', explode(',', $result));
      $parts = array();
      foreach ($named_parts as $part) {
         if (strpos($part, '=') !== false) {
            list($key, $value) = explode('=', $part);
            $parts[trim($key)] = trim($value);
         }
      }
      
      $rc->lat = isset($parts['lat']) ? $parts['lat'] : null;
      $rc->lng = isset($parts['long']) ? $parts['long'] : null;
      $rc->city = isset($parts['city']) ? $parts['city'] : null;
      $rc->state = isset($parts['state']) ? $parts['state'] : null;
      $rc->zip = isset($parts['zip']) ? $parts['zip'] : null;
      $rc->country_code = 'US';  // must be US with this service!
      $rc->street_address = $this->buildStreetAddress($parts);
      $rc->full_address = $this->buildFullAddress($rc);
      $rc->accuracy = $this->determineAccuracy($rc);
      $rc->precision = array_search($rc->accuracy, self::$accuracy_map);
      
      $rc->success = true;
      
      return $rc;
   }
   
   /**
    * Builds a street address based on avialable fields
    * @param array $parts named address parts
    * @return string street address
    */
   protected function buildStreetAddress($parts)
   {
      $rc = '';
      foreach (array('number', 'prefix', 'street', 'type', 'suffix') as $key) {
         if (isset($parts[$key]) && trim($parts[$key]) != '') {
            $rc .= $parts[$key] . ' ';
         }
      }
      
      return trim($rc);
   }
}
