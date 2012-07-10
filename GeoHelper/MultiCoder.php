<?php 

namespace GeoHelper;
use \GeoHelper\Support\Bounds, \GeoHelper\Support\LatLong, \GeoHelper\Support\Location;

/**
 * MultiGeocoder
 *
 * Calls multiple geocoder providers as defined in MultiCoder::$provider_order. It will
 * return the first successful attempt once found.
 */
class MultiCoder extends AbstractGeocoder
{
   
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
    * Geocode an address using multiple providers if needed
    * @param string $address address or ip address to geocode
    * @param array $options options hash
    * @return Location Location object
    */
   public function geocode($address, $options = array())
   {
      $is_ip_geocoding = preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/', $address);
      $order = $is_ip_geocoding ? static::$ip_provider_order : static::$provider_order;
      
      foreach ($order as $provider) {
         $provider = "\\GeoHelper\\$provider";
         try {
            $api = new $provider();
            $rc = $api->geocode($address, $options);
            if ($rc->success()) {
               return $rc;
            }
         } catch (Exception $e) {
            self::log("Something has gone very wrong during geocoding, OR you have configured an invalid class name in MultiCoder::\$provider_order. Address: $address. Provider: $provider", LOG_INFO);
         }
      }
      
      // everything has failed :(
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
      foreach (MultiCoder::$provider_order as $provider) {
         try {
            $api = new $provider();
            $rc = $api->reverseGeocode($latlng, $options);
            if ($rc->success()) {
               return $rc;
            }
         } catch (Exception $e) {
            self::log("Something has gone very wrong during geocoding, OR you have configured an invalid class name in MultiCoder::\$provider_order. LatLong: $latlng. Provider: $provider", LOG_INFO);
         }
         
         // everything has failed :(
         return new Location();
      }
   }
}
