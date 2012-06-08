<?php 

namespace GeoHelper;
use \GeoHelper\Support\Bounds, \GeoHelper\Support\LatLong, \GeoHelper\Support\Location;
use \SimpleXMLElement;

/**
 * Google geocoder
 * @see http://code.google.com/apis/maps/documentation/geocoding/
 */
class Google extends AbstractGeocoder
{
   /**
    * Geocode an address
    *
    * Options available:
    *
    * <ul>
    *   <li><b>bias</b> <i>(mixed)</i>: Bias the results based on a country or viewport. You can pass in the ccTLD or a Bounds object (default: null)</li>
    * </ul>   
    * @param string $address address to geocode
    * @param array $options options hash
    * @return Location Location object
    */
   public function geocode($address, $options = array())
   {
      $default_options = array(
         'address' => $address,
         'language' => 'en',
         'region' => null,
         'bounds' => null,
         'sensor' => 'false',
      );
      $options = array_merge($default_options, $options);
      
      if (!is_null($options['bounds']) && $options['bounds'] instanceof Bounds) {
         $options['bounds'] = $options['bounds']->sw->ll() . '|' . $options['bounds']->ne->ll();
      }
      if ($options['address'] instanceof Location) {
         $options['address'] = $options['address']->toGeocodableString();
      }
      
      try {
         $url = sprintf('http://maps.googleapis.com/maps/api/geocode/xml?%s', $this->buildParameterList($options));
         $result = $this->callWebService($url);
      } catch (Exception $e) {
         // error contacting service
         return new Location();
      }
      
      self::log("Google geocoding. Address: " . $address . ". Result: " . $result, LOG_INFO);
      
      return $this->xml2Location($result);
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
         'latlng' => LatLong::normalize($latlng),
         'language' => 'en',
         'region' => null,
         'sensor' => 'false',
      );
      $options = array_merge($default_options, $options);
      
      try {
         $url = sprintf('http://maps.googleapis.com/maps/api/geocode/xml?%s', $this->buildParameterList($options));
         $result = $this->callWebService($url);
      } catch (Exception $e) {
         // error contacting service
         return new Location();
      }
      
      self::log("Google reverse-geocoding. LL: " . $options['latlng']->ll() . ". Result: " . $result, LOG_INFO);
      
      return $this->xml2Location($result);
   }
   
   /**
    * Parses the Google xml document into a Location
    * @param string $xml XML body
    * @return Location Parsed location data
    */
   protected function xml2Location($xml)
   {
      $doc = new SimpleXMLElement($xml);
      $status = (string) $doc->status;
      
      if ($status == 'OK')
      {
         // google may return 0 or more results in result elements
         // grab them all
         $loc = null;
         foreach ($doc->result as $result) {
            $result = $this->extractResult($result);
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
      elseif ($status == 'OVER_QUERY_LIMIT')
      {
         // too many queries
         throw new TooManyQueriesException("Google returned a 620 status, too many queries. The given key has gone over the requests limit in the 24 hour period or has submitted too many requests in too short a period of time. If you're sending multiple requests in parallel or in a tight loop, use a timer or pause in your code to make sure you don't send the requests too quickly.");
      }
      else
      {
         // dammit, something else went wrong that we can't accurately count
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
      
      // basic information:
      $rc->lat = isset($result->geometry->location->lat) ? (string) $result->geometry->location->lat : null;
      $rc->lng = isset($result->geometry->location->lng) ? (string) $result->geometry->location->lng : null;
      $rc->full_address = isset($result->formatted_address) ? (string) $result->formatted_address : null;
      $rc->provider = 'google';
      
      // precision map
      $precision_map = array(
         'ROOFTOP' => 9,
         'RANGE_INTERPOLATED' => 8,
         'GEOMETRIC_CENTER' => 5,
         'APPROXIMATE' => 2,
      );

      // address parts
      $street_number = $street_name = null;
      foreach ($result->address_component as $component) {
         $types = is_array($component->type) ? $component->type : array($component->type);
         if (in_array('street_number', $types)) {
            $street_number = (string) $component->short_name;
         } elseif (in_array('route', $types)) {
            $street_name = (string) $component->long_name;
         } elseif (in_array('locality', $types)) {
            $rc->city = (string) $component->long_name;
         } elseif (in_array('administrative_area_level_1', $types)) {
            $rc->state = (string) $component->short_name;
            $rc->district = (string) $component->short_name;
         } elseif (in_array('postal_code', $types)) {
            $rc->zip = (string) $component->long_name;
         } elseif (in_array('country', $types)) {
            $rc->country_code = (string) $component->short_name;
            $rc->country = (string) $component->long_name;
         } elseif (in_array('administrative_area_level_2', $types)) {
            $rc->province = (string) $component->long_name;
         }
         
         if (trim($street_name) != '') {
            $rc->street_address = trim(implode(' ', array($street_number, $street_name)));
         }
      }
      
      // get first returned type (for more specific precision matching):
      $type = isset($result->type) ? (string) $result->type : '';
      switch (strtolower($type)) {
      	case 'administrative_area_level_2':
      		$rc->precision = 3; // county/parish
	      	break;
	      default:
	      	$rc->precision = $precision_map[(string) $result->geometry->location_type];
	      	break;
      }
      
      $rc->accuracy = self::$accuracy_map[$rc->precision];
      if ($street_name && $rc->accuracy == 'city') {
         $rc->accuracy = 'street';
         $rc->precision = 7;
      }
      
      if (isset($result->geometry->viewport)) {
         $sw = new LatLong(
            (string) $result->geometry->viewport->southwest->lat,
            (string) $result->geometry->viewport->southwest->lng
         );
         $ne = new LatLong(
            (string) $result->geometry->viewport->northeast->lat,
            (string) $result->geometry->viewport->northeast->lng
         );
         
         $rc->suggested_bounds = new Bounds($sw, $ne);
      }
      
      $rc->success = true;
      
      return $rc;
   }
}
