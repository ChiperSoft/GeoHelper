<?php 

namespace GeoHelper;
use \GeoHelper\Support\Bounds, \GeoHelper\Support\LatLong, \GeoHelper\Support\Location;

/**
 * Yahoo PlaceFinder geocoder
 * @see http://developer.yahoo.com/geo/placefinder/guide/index.html
 */
class PlaceFinder extends AbstractGeocoder
{
   /**
    * Yahoo key (required)
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
         'location' => ($address instanceof Location) ? $address->toGeocodableString() : $address,
         'locale' => null,
         'flags' => 'SXP',
         'gflags' => 'A',
         'appid' => self::$key,
      );
      $options = array_merge($default_options, $options);
      
      try {
         $url = sprintf('http://where.yahooapis.com/geocode?%s', $this->buildParameterList($options));
         $result = $this->callWebService($url);
      } catch (Exception $e) {
         // error contacting service
         return new Location();
      }
      
      // print_r($result);
      
      self::log("Yahoo PlaceFinder geocoding. Address: " . $address . ". Result: " . $result, LOG_INFO);
      
      return $this->parseResult($result);
   }
   
   /**
    * Reverse-geocode a lat long combination
    * @param mixed $latlng LatLong object or string containing lat/lng information
    * @param array $options options hash
    * @return Location Location object
    */
   public function reverseGeocode($latlng, $options = array())
   {
      $default_options = array(
         'location' => LatLong::normalize($latlng),
         'locale' => null,
         'flags' => 'SXP',
         'gflags' => 'AR',
         'appid' => self::$key,
      );
      $options = array_merge($default_options, $options);
      
      try {
         $url = sprintf('http://where.yahooapis.com/geocode?%s', $this->buildParameterList($options));
         $result = $this->callWebService($url);
      } catch (Exception $e) {
         // error contacting service
         return new Location();
      }
      
      self::log("Yahoo PlaceFinder reverse-geocoding. LL: " . $latlng . ". Result: " . $result, LOG_INFO);
      
      return $this->parseResult($result);
   }
   
   /**
    * Parse result (serialized PHP) into Location
    * @param string $result serialized PHP
    * @return Location Parsed location data
    */
   protected function parseResult($result)
   {
      $obj = unserialize($result);
      
      if (isset($obj['ResultSet'])) {
         if (isset($obj['ResultSet']['Error']) 
             && $obj['ResultSet']['Error'] == 0
             && $obj['ResultSet']['Found'] > 0
        ) {
            // placefinder may return 0 or more results in result elements
            // grab them all
            $loc = null;
            foreach ($obj['ResultSet']['Result'] as $result) {
               $result = self::extractResult($result);
               if (is_null($loc)) {
                  // first iteration and main result
                  $loc = $result;
                  $loc->all[] = $result;
               } else {
                  // subsequent iteration (sub matches)
                  $loc->all[] = $result;
               }
            }
            
            return $loc;
         }
      }
   
      // nothing found or geocoding error
      return new Location();
   }
   
   /**
    * Extracts locations from the response
    * @param array $result response results
    * @return Location porsed location data
    */
   protected function extractResult($result)
   {
      $rc = new Location();
      $rc->provider = 'placefinder';
      
      $rc->lat = isset($result['latitude']) ? $result['latitude'] : null;
      $rc->lng = isset($result['longitude']) ? $result['longitude'] : null;
      
      $rc->street_address = $result['line1'];
      $rc->city = $result['city'];
      $rc->state = $result['statecode'];
      $rc->zip = $result['uzip'];
      $rc->district = '';
      $rc->province = $result['county'];
      $rc->country = $result['country'];
      $rc->country_code = $result['countrycode'];
      $rc->full_address = $this->buildFullAddress($rc);
      
      $rc->accuracy = $this->translatePrecision($result['quality']);
      $rc->precision = array_search($rc->accuracy, self::$accuracy_map);
      
      if (isset($result['boundingbox'])) {
         $ne = new LatLong(
            $result['boundingbox']['north'],
            $result['boundingbox']['east']
         );
         $sw = new LatLong(
            $result['boundingbox']['south'],
            $result['boundingbox']['west']
         );
         
         $rc->suggested_bounds = new Bounds($ne, $sw);
      }
      
      $rc->success = true;
      
      return $rc;
   }
   
   /**
    * Translate the precision to accuracy string
    * @see http://developer.yahoo.com/geo/placefinder/guide/responses.html#address-quality
    * @param integer $precision precision
    * @return string accuracy
    */
   protected function translatePrecision($precision)
   {
      $code = 0;
      if ($precision == 99) {
         $code = 9;
      } elseif ($precision < 99 && $precision >= 84) {
         $code = 8;
      } elseif ($precision <= 82 && $precision >= 80) {
         $code = 7;
      } elseif ($precision <= 75 && $precision >= 70) {
         $code = 6;
      } elseif ($precision <= 64 && $precision >= 59) {
         $code = 5;
      } elseif ($precision <= 50 && $precision >= 39) {
         $code = 4;
      } elseif ($precision <= 30 && $precision >= 29) {
         $code = 3;
      } elseif ($precision <= 20 && $precision >= 19) {
         $code = 2;
      } elseif ($precision <= 10 && $precision >= 9) {
         $code = 1;
      }
      
      return self::$accuracy_map[$code];
   }
}

