<?php 

namespace GeoHelper;
use \GeoHelper\Support\Bounds, \GeoHelper\Support\LatLong, \GeoHelper\Support\Location;
use \SimpleXmlElement;

/**
 * HostIP geocoder
 * @see http://www.hostip.info/use.html
 */
class HostIP extends AbstractGeocoder
{
   /**
    * Geocode an IP Address
    * @param string $ip IP address to geocode
    * @param array $options options hash
    * @return Location Location object
    */
   public function geocode($ip, $options = array())
   {
      if (!preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})?$/', $ip)) {
         // TODO: validate local ips to auto skip?
         return new Location();
      }
      
      $default_options = array(
         'ip' => $ip,
         'position' => 'true',
      );
      $options = array_merge($default_options, $options);
      
      try {
         $url = sprintf('http://api.hostip.info/?%s', $this->buildParameterList($options));
         $result = $this->callWebService($url);
      } catch (Exception $e) {
         // error contacting service
         return new Location();
      }
      
      self::log("Hostip geocoding. IP: " . $ip . ". Result: " . $result, LOG_INFO);
      
      return $this->parseXml($result);
   }
   
   /**
    * Parse response xml
    * @param string $xml response xml
    * @return Location parsed location
    */
   protected function parseXml($xml)
   {
      // load fixing namespace issue: http://bugs.php.net/bug.php?id=48049
      $doc = new SimpleXmlElement(str_replace(':', '_', $xml));
      
      $rc = new Location();
      $rc->provider = 'hostip';
      
      $doc = $doc->gml_featureMember->Hostip;
      
      if (isset($doc->gml_name)) {
         if (substr((string) $doc->gml_name, 0, 1) == '(') {
            // error geocoding
            return $rc;
         }

         list($rc->city, $rc->state) = array_map('trim', explode(', ', (string) $doc->gml_name));
      }
      
      $rc->country = isset($doc->countryName) ? (string) $doc->countryName : null;
      $rc->country_code = isset($doc->countryAbbrev) ? (string) $doc->countryAbbrev : null;
      
      if (isset($doc->ipLocation->gml_pointProperty->gml_Point->gml_coordinates)) {
         list($rc->lng, $rc->lat) = array_map('trim', explode(',', $doc->ipLocation->gml_pointProperty->gml_Point->gml_coordinates));
      }
      
      $rc->full_address = $this->buildFullAddress($rc);
      
      if (!is_null($rc->city) && trim($rc->city) != '') {
         $rc->accuracy = 'city';
         $rc->precision = array_search($rc->accuracy, self::$accuracy_map);
         $rc->success = true;
         $rc->all[] = $rc;
      }
      
      return $rc;
   }
}