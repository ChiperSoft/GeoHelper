<?php 

namespace GeoHelper;
use Support\Bounds, Support\LatLong, Support\Location;


/**
 * Bing Maps geocoder
 * @see http://msdn.microsoft.com/en-us/library/ff701711.aspx
 */
class Bing extends AbstractGeocoder
{
   /**
    * Bing API key (required)
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
         'query' => ($address instanceof Location) ? $address->toGeocodableString() : $address,
         'output' => 'xml',
         'key' => self::$key,
      );
      $options = array_merge($default_options, $options);
            
      try {
         $url = sprintf('http://dev.virtualearth.net/REST/v1/Locations?%s', $this->buildParameterList($options));
         $result = $this->callWebService($url);
      } catch (Exception $e) {
         // error contacting service
         return new Location();
      }
      
      self::log("Bing geocoding. Address: " . $address . ". Result: " . $result, LOG_INFO);
      
      return $this->parseXml($result);
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
         'point' => LatLong::normalize($latlng),
         'output' => 'xml',
         'key' => self::$key,
      );
      $options = array_merge($default_options, $options);

      try {
         $point = rawurlencode($options['point']);
         unset($options['point']);
         
         $url = sprintf('http://dev.virtualearth.net/REST/v1/Locations/%s/?%s', $point, $this->buildParameterList($options));
         $result = $this->callWebService($url);
      } catch (Exception $e) {
         // error contacting service
         return new Location();
      }
      
      self::log("Bing reverse-geocoding. LL: " . $latlng . ". Result: " . $result, LOG_INFO);
      
      return $this->parseXml($result);
   }
   
   /**
    * Parse XML
    * @param string $xml XML doc
    * @return Location parsed location
    */
   protected function parseXml($xml)
   {
      $doc = new SimpleXmlElement($xml);
      $status = (string) $doc->StatusCode;
      
      if ($status == 200)
      {
         // bing may return 0 or more results in result elements
         // grab them all
         $loc = null;
         foreach ($doc->ResourceSets->ResourceSet->Resources->Location as $location) {
            $result = $this->extractResult($location);
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
      else
      {
         // error contacting the service
         return new Location();
      }
   }
   
   /**
    * Extracts locations from the xml
    * @param SimpleXmlElement $elm XML element
    * @return Location porsed location data
    */
   protected function extractResult($result)
   {
      $rc = new Location();
      $rc->provider = 'bing';
      
      // basic information:
      $rc->lat = isset($result->Point->Latitude) ? (string) $result->Point->Latitude : null;
      $rc->lng = isset($result->Point->Longitude) ? (string) $result->Point->Longitude : null;
      
      $rc->street_address = isset($result->Address->AddressLine) ? (string) $result->Address->AddressLine : null;
      $rc->city = isset($result->Address->Locality) ? (string) $result->Address->Locality : null;
      $rc->state = isset($result->Address->AdminDistrict) ? (string) $result->Address->AdminDistrict : null;
      $rc->province = isset($result->Address->AdminDistrict2) ? (string) $result->Address->AdminDistrict2 : null;
      $rc->zip = isset($result->Address->PostalCode) ? (string) $result->Address->PostalCode : null;
      $rc->full_address = isset($result->Address->FormattedAddress) ? (string) $result->Address->FormattedAddress : null;
      $rc->country = isset($result->Address->CountryRegion) ? (string) $result->Address->CountryRegion : null;
      
      $rc->accuracy = $this->determineAccuracy($rc);
      $rc->precision = array_search($rc->accuracy, self::$accuracy_map);
      
      if (isset($result->BoundingBox)) {
         $ne = new LatLong(
            (string) $result->BoundingBox->NorthLatitude,
            (string) $result->BoundingBox->EastLongitude
         );
         $sw = new LatLong(
            (string) $result->BoundingBox->SouthLatitude,
            (string) $result->BoundingBox->WestLongitude
         );
         
         $rc->suggested_bounds = new Bounds($ne, $sw);
      }
      
      $rc->success = true;
      
      return $rc;
   }
}