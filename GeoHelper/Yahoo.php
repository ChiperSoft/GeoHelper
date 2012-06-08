<?php 

namespace GeoHelper;
use \GeoHelper\Support\Bounds, \GeoHelper\Support\LatLong, \GeoHelper\Support\Location;


/**
 * Yahoo geocoder
 * @see http://developer.yahoo.com/maps/rest/V1/geocode.html
 */
class Yahoo extends AbstractGeocoder
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
         'appid' => self::$key,
         'output' => 'php',
      );
      $options = array_merge($default_options, $options);
      
      try {
         $url = sprintf('http://local.yahooapis.com/MapsService/V1/geocode?%s', $this->buildParameterList($options));
         $result = $this->callWebService($url);
      } catch (Exception $e) {
         // error contacting service
         return new Location();
      }
      
      self::log("Yahoo geocoding. Address: " . $address . ". Result: " . $result, LOG_INFO);
      
      return $this->parseResult($result);
   }
   
   /**
    * Parse XML into Location
    * @param string $result PHP result
    * @return Location Parsed location data
    */
   protected function parseResult($result)
   {   
      $obj = unserialize($result);
      
      $rc = new Location();
      $rc->provider = 'yahoo';
      
      if (isset($obj['ResultSet']['Result']))
      {
         $obj = $obj['ResultSet']['Result'];
         
         $rc->lat = isset($obj['Latitude']) ? $obj['Latitude'] : null;
         $rc->lng = isset($obj['Longitude']) ? $obj['Longitude'] : null;
         
         $rc->street_address = isset($obj['Address']) ? $obj['Address'] : null;
         $rc->city = isset($obj['City']) ? $obj['City'] : null;
         $rc->state = isset($obj['State']) ? $obj['State'] : null;
         $rc->zip = isset($obj['Zip']) ? $obj['Zip'] : null;
         $rc->country_code = isset($obj['Country']) ? $obj['Country'] : null;
         $rc->full_address = $this->buildFullAddress($rc);
         $rc->accuracy = isset($obj['precision']) ? $obj['precision'] : null;
         $rc->precision = array_search($rc->accuracy, self::$accuracy_map);
         
         $rc->success = true;
         
         $rc->all[] = $rc;
      }
      
      return $rc;
   }
}