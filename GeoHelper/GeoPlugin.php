<?php 

namespace GeoHelper;

/**
 * GeoPlugin IP geocoder
 * @see http://www.geoplugin.com/webservices
 */
class GeoPlugin extends AbstractGeocoder
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
      );
      $options = array_merge($default_options, $options);
      
      try {
         $url = sprintf('http://www.geoplugin.net/xml.gp?%s', $this->buildParameterList($options));
         $result = $this->callWebService($url);
      } catch (Exception $e) {
         // error contacting service
         return new Location();
      }
      
      self::log("Geoplugin geocoding. IP: " . $ip . ". Result: " . $result, LOG_INFO);
      
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
      $latlng = LatLong::normalize($latlng);
      $default_options = array(
         'lat' => $latlng->lat,
         'long' => $latlng->lng,
         'format' => 'xml',
      );
      $options = array_merge($default_options, $options);
      
      try {
         $url = sprintf('http://www.geoplugin.net/extras/location.gp?%s', $this->buildParameterList($options));
         $result = $this->callWebService($url);
      } catch (Exception $e) {
         // error contacting service
         return new Location();
      }
      
      self::log("Geoplugin reverse-geocoding. LL: " . $latlng . ". Result: " . $result, LOG_INFO);
      
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
      
      $rc = new Location();
      $rc->provider = 'geoplugin';
      
      // reverse geocode city is in geoplugin_place
      if (isset($doc->geoplugin_city)) {
         $rc->city = (string) $doc->geoplugin_city;
      } elseif (isset($doc->geoplugin_place)) {
         $rc->city = (string) $doc->geoplugin_place;
      }
      
      // reverse geocode state is in regionAbbreviated
      if (isset($doc->geoplugin_regionAbbreviated)) {
         $rc->state = (string) $doc->geoplugin_regionAbbreviated;
      } elseif (isset($doc->geoplugin_regionCode)) {
         $rc->state = (string) $doc->geoplugin_regionCode;
      }
      
      $rc->country = isset($doc->geoplugin_countryName) ? (string) $doc->geoplugin_countryName : null;
      $rc->country_code = isset($doc->geoplugin_countryCode) ? (string) $doc->geoplugin_countryCode : null;
      $rc->lat = isset($doc->geoplugin_latitude) ? (float) $doc->geoplugin_latitude : null;
      $rc->lng = isset($doc->geoplugin_longitude) ? (float) $doc->geoplugin_longitude : null;
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