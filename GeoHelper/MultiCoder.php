<?php 

namespace GeoHelper;
use Support\Bounds, Support\LatLong, Support\Location;

/**
 * MultiGeocoder
 *
 * Calls multiple geocoder providers as defined in GeoHelper::$provider_order. It will
 * return the first successful attempt once found.
 */
class MultiCoder extends AbstractGeocoder
{
   /**
    * Geocode an address using multiple providers if needed
    * @param string $address address or ip address to geocode
    * @param array $options options hash
    * @return Location Location object
    */
   public function geocode($address, $options = array())
   {
      $is_ip_geocoding = preg_match('/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/', $address);
      $order = $is_ip_geocoding ? GeoHelper::$ip_provider_order : GeoHelper::$provider_order;
      
      foreach ($order as $provider) {
         try {
            $api = new $provider();
            $rc = $api->geocode($address, $options);
            if ($rc->success()) {
               return $rc;
            }
         } catch (Exception $e) {
            self::log("Something has gone very wrong during geocoding, OR you have configured an invalid class name in GeoHelper::\$provider_order. Address: $address. Provider: $provider", LOG_INFO);
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
      foreach (GeoHelper::$provider_order as $provider) {
         try {
            $api = new $provider();
            $rc = $api->reverseGeocode($latlng, $options);
            if ($rc->success()) {
               return $rc;
            }
         } catch (Exception $e) {
            self::log("Something has gone very wrong during geocoding, OR you have configured an invalid class name in GeoHelper::\$provider_order. LatLong: $latlng. Provider: $provider", LOG_INFO);
         }
         
         // everything has failed :(
         return new Location();
      }
   }
}
