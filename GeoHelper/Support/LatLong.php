<?php
/*
 * This file is part of the GeoHelper library.
 *
 * (c) Matthew Vince <matthew.vince@phaseshiftllc.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace GeoHelper\Support;


/**
 * LatLong class
 */
class LatLong extends Mappable
{
   /**
    * Latitude
    * @var float
    */
   public $lat = null;
   
   /**
    * Longitude
    * @var float
    */
   public $lng = null;
   
   
   /**
    * Normalizes a lat long pair
    *
    * Possible parameters:
    *    1) two arguments (lat,lng)
    *    2) a string in the format "37.1234,-129.1234" or "37.1234 -129.1234"
    *    3) a string which can be geocoded on the fly
    *    4) an array in the format [37.1234,-129.1234]
    *    5) a LatLong or Location (which is just passed through as-is)
    * @param mixed $thing Anything that can be converted into a LatLong
    * @param mixed $other Anything that can be converted into a LatLong
    * @return LatLong converted LatLong
    */
   public static function normalize($thing, $other = null)
   {
      if (!is_null($other)) {
         $thing = array($thing, $other);
      }
      
      if (is_string($thing))
      {
         $thing = trim($thing);
         $matches = array();
         if (preg_match('/(\-?\d+\.?\d*)[, ] ?(\-?\d+\.?\d*)$/', $thing, $matches)) {
            return new LatLong($matches[1], $matches[2]);
         } else {
            return self::geocode($thing);
         }
      }
      elseif (is_array($thing) && count($thing) == 2)
      {
         // passed as lat/lng pair
         return new LatLong($thing[0], $thing[1]);
      }
      elseif (($thing instanceof LatLong))
      {
         // no need to convert
         return $thing;
      }

      // nothing worked
      throw new InvalidArgumentException('Could not normalize argument into LatLong.');
   }
   
   /**
    * Constructor
    * @param float $lat latitude
    * @param float $lng longitude
    */
   public function __construct($lat = null, $lng = null)
   {
      $this->lat = (float) $lat;
      $this->lng = (float) $lng;
   }
   
   /**
    * Ouputs lat/lng pair comma separated string
    * @return string lat/lng pair
    */
   public function ll()
   {
      return $this->lat . ',' . $this->lng;
   }
   
   /**
    * Check if one LatLong is equal to another (same lat/lng pair)
    * @param mixed $other Item to compare to
    * @return boolean equal
    */
   public function equal($other)
   {
      return ($other instanceof LatLong) ? ($this->lat == $other->lat && $this->lng == $other->lng) : false;
   }
   
   /**
    * Convert to string
    * @return string converted object
    */
   public function __toString()
   {
      return $this->ll();
   }

   /**
    * Reverse geocode the LatLong
    *
    * @param array $options options hash
    * @return Location location if found
    */
   public function reverseGeocode($options = array())
   {
      $api = new GeoHelperMultiGeocoder();
            
      $rc = $api->reverseGeocode($this, $options);
      if ($rc->success()) {
         return $rc;
      }
      
      // all geocoders failed
      throw new GeoHelperException("All Geocoders Failed");
   }
}
