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

/** calculation constants */
define('GEOHELPER_PI_DIV_RAD', 0.0174);
define('GEOHELPER_KMS_PER_MILE', 1.609);
define('GEOHELPER_NMS_PER_MILE', 0.868976242);
define('GEOHELPER_EARTH_RADIUS_IN_MILES', 3963.19);
define('GEOHELPER_EARTH_RADIUS_IN_KMS', GEOHELPER_EARTH_RADIUS_IN_MILES * GEOHELPER_KMS_PER_MILE);
define('GEOHELPER_EARTH_RADIUS_IN_NMS', GEOHELPER_EARTH_RADIUS_IN_MILES * GEOHELPER_NMS_PER_MILE);
define('GEOHELPER_MILES_PER_LATITUDE_DEGREE', 69.1);
define('GEOHELPER_KMS_PER_LATITUDE_DEGREE', GEOHELPER_MILES_PER_LATITUDE_DEGREE * GEOHELPER_KMS_PER_MILE);
define('GEOHELPER_NMS_PER_LATITUDE_DEGREE', GEOHELPER_MILES_PER_LATITUDE_DEGREE * GEOHELPER_NMS_PER_MILE);
define('GEOHELPER_LATITUDE_DEGREES', GEOHELPER_EARTH_RADIUS_IN_MILES / GEOHELPER_MILES_PER_LATITUDE_DEGREE);


/**
 * Contains basic geocoding elements
 * 
 * Classes inherting this class should have a $lat/$lng variable pair
 * 
 * Two forms of distance calculations are available:
 *   - Pythagorean Theory (flat Earth) - which assumes the world is flat and loses accuracy over long distances.
 *   - Haversine (sphere) - which is fairly accurate, but at a performance cost. (this is the default)
 *
 * Distance units supported are miles, kms and nms
 */
class Mappable
{
   
   /**
    * Default calculation formula (can be sphere or flat)
    * @var string
    */
   public static $default_formula = 'sphere';
   
   /**
    * Default calculation units (can be miles, kms or nms)
    * @var string
    */
   public static $default_units = 'miles';
   
   
   /**
    * Finds the distance between two points
    *
    * Options available:
    *
    * <ul>
    *   <li><b>units</b> <i>(string)</i>: Valid units are miles, kms and nms (default: miles)</li>
    *   <li><b>formula</b> <i>(string)</i>: Valid values are sphere and flat (default: sphere)</li>
    * </ul>
    * @param mixed $from Any object with lat/lng properties (preferrably a LatLong)
    * @param mixed $to Any object with lat/lng properties (preferrably a LatLong)
    * @param array $options options hash
    * @return float distance in specified units
    */
   public static function distanceBetween($from, $to, $options = array())
   {
      $from = LatLong::normalize($from);
      $to = LatLong::normalize($to);
      
      if ($from->equal($to)) {
         return 0.0;
      }
      
      $units = isset($options['units']) ? $options['units'] : static::$default_units;
      $formula = isset($options['formula']) ? $options['formula'] : static::$default_formula;
      
      if ($formula == 'sphere')
      {
         return self::unitsSphereMultiplier($units) * 
                acos(sin(deg2rad($from->lat)) * sin(deg2rad($to->lat)) + 
                cos(deg2rad($from->lat)) * cos(deg2rad($to->lat)) * 
                cos(deg2rad($to->lng) - deg2rad($from->lng)));
      }
      elseif ($formula == 'flat')
      {
         return sqrt(pow(self::unitsPerLatitudeDegree($units) * ($from->lat - $to->lat), 2) + 
                pow(self::unitsPerLongitudeDegree($from->lat, $units) * ($from->lng - $to->lng), 2));
      }
      
      throw new InvalidArgumentException('Invalid calculation formula provided.');
   }

   /**
    * Finds the heading in degrees from first to second point
    * @param mixed $from Any object with lat/lng properties (preferrably a LatLong)
    * @param mixed $to Any object with lat/lng properties (preferrably a LatLong)
    * @return float heading in degrees
    */
   public static function headingBetween($from, $to)
   {
      $from = LatLong::normalize($from);
      $to = LatLong::normalize($to);
      
      $d_lng = deg2rad($to->lng - $from->lng);
      $from_lat = deg2rad($from->lat);
      $to_lat = deg2rad($to->lat);
      
      $y = sin($d_lng) * cos($to_lat);
      $x = cos($from_lat) * sin($to_lat) - sin($from_lat) * cos($to_lat) * cos($d_lng);
      
      return self::toHeading(atan2($y, $x));
   }
   
   /**
    * Finds and enpoint for a start, heading and distance
    *
    * Options available:
    *
    * <ul>
    *   <li><b>units</b> <i>(string)</i>: Valid units are miles, kms and nms (default: miles)</li>
    * </ul>
    * @param mixed $start Any object with lat/lng properties (preferrably a LatLong)
    * @param float $heading heading
    * @param float $distance distance in units
    * @param array $options options hash
    * @return LatLong endpoint
    */
   public static function endpointFor($start, $heading, $distance, $options = array())
   {
      $units = isset($options['units']) ? $options['units'] : static::$default_units;
      if ($units == 'kms') {
         $radius = GEOHELPER_EARTH_RADIUS_IN_KMS;
      } elseif ($units == 'nms') {
         $radius = GEOHELPER_EARTH_RADIUS_IN_NMS;
      } else {
         $radius = GEOHELPER_EARTH_RADIUS_IN_MILES;
      }
      
      $start = LatLong::normalize($start);
      $lat = deg2rad($start->lat);
      $lng = deg2rad($start->lng);
      $heading = deg2rad($heading);
      
      $end_lat = asin(sin($lat) * cos($distance/$radius) +
                 cos($lat) * sin($distance/$radius) * cos($heading));
      
      $end_lng = $lng + atan2(sin($heading) * sin($distance/$radius) * cos($lat),
                 cos($distance/$radius) - sin($lat) * sin($end_lat));

      return new LatLong(rad2deg($end_lat), rad2deg($end_lng));
   }
   
   /**
    * Finds and enpoint between two points
    *
    * Options available:
    *
    * <ul>
    *   <li><b>units</b> <i>(string)</i>: Valid units are miles, kms and nms (default: miles)</li>
    * </ul>
    * @param mixed $from Any object with lat/lng properties (preferrably a LatLong)
    * @param mixed $to Any object with lat/lng properties (preferrably a LatLong)
    * @param array $options options hash
    * @return LatLong midpoint
    */
   public static function midpointBetween($from, $to, $options = array())
   {
      $from = LatLong::normalize($from);
      
      $units = isset($options['units']) ? $options['units'] : static::$default_units;
      
      $heading = $from->headingTo($to);
      $distance = $from->distanceTo($to, $options);
      
      return $from->endpoint($heading, $distance / 2, $options);
   }

   /**
    * Geocode an address using the GeoHelperMultiGeocoder
    * @param mixed $location a geocodable item
    * @param array $options options hash
    * @return Location location
    * @throws GeoHelperException on geocoding error
    */
   public static function geocode($location, $options = array())
   {
      $api = new MultiCoder();
            
      $rc = $api->geocode($location, $options);
      if ($rc->success()) {
         return $rc;
      }
      
      // all geocoders failed
      throw new GeoHelperException("Geocoding Failed");
   }

   /**
    * Converts radians into a heading
    * @param float $rad radians
    * @return float heading
    */
   protected static function toHeading($rad)
   {
      return fmod((rad2deg($rad) + 360), 360);
   }
   
   /**
    * Converts units into the sphere formula multiplier
    * @param string $units units
    * @return float multiplier
    */
   protected static function unitsSphereMultiplier($units)
   {
      if ($units == 'kms') {
         return GEOHELPER_EARTH_RADIUS_IN_KMS;
      } elseif ($units == 'nms') {
         return GEOHELPER_EARTH_RADIUS_IN_NMS;
      } else {
         return GEOHELPER_EARTH_RADIUS_IN_MILES;
      }
   }

   /**
    * Gets the number of units per latitude degree
    * @param string $units units
    * @return float units per latitude degree
    */
   protected static function unitsPerLatitudeDegree($units)
   {
      if ($units == 'kms') {
         return GEOHELPER_KMS_PER_LATITUDE_DEGREE;
      } elseif ($units == 'nms') {
         return GEOHELPER_NMS_PER_LATITUDE_DEGREE;
      } else {
         return GEOHELPER_MILES_PER_LATITUDE_DEGREE;
      }
   }
   
   /**
    * Gets the number of units per longitude degree
    * @param float $lat latitude
    * @param string $units units
    * @return float units per longitude degree
    */
   protected static function unitsPerLongitudeDegree($lat, $units)
   {
      $miles = abs(GEOHELPER_LATITUDE_DEGREES * cos($lat * GEOHELPER_PI_DIV_RAD));
      
      if ($units == 'kms') {
         return $miles * GEOHELPER_KMS_PER_MILE;
      } elseif ($units == 'nms') {
         return $miles * GEOHELPER_NMS_PER_MILE;
      } else {
         return $miles;
      }
   }

   /**
    * Finds the distance to another point
    *
    * Options available:
    *
    * <ul>
    *   <li><b>units</b> <i>(string)</i>: Valid units are miles, kms and nms (default: miles)</li>
    * </ul>
    * @param mixed $other Any object with lat/lng properties (preferrably a LatLong)
    * @param array $options options hash
    * @return float distance between the two points
    */
   public function distanceTo($other, $options = array())
   {
      return self::distanceBetween($this, $other, $options);
   }
   
   /**
    * Alias of distanceTo
    */
   public function distanceFrom($other, $options = array())
   {
      return $this->distanceTo($other, $options);
   }
   
   /**
    * Finds the heading to another point
    * @param mixed $other Any object with lat/lng properties (preferrably a LatLong)
    * @return float heading to other point
    */
   public function headingTo($other)
   {
      return self::headingBetween($this, $other);
   }
   
   /**
    * Finds the heading from another point
    * @param mixed $other Any object with lat/lng properties (preferrably a LatLong)
    * @return float heading from other point
    */
   public function headingFrom($other)
   {
      return self::headingBetween($other, $this);
   }
   
   /**
    * Finds the endpoint given a heading and distance
    *
    * Options available:
    *
    * <ul>
    *   <li><b>units</b> <i>(string)</i>: Valid units are miles, kms and nms (default: miles)</li>
    * </ul>
    * @param float $heading heading
    * @param float $distance distance
    * @param array $options options hash
    * @return LatLong endpoint
    */
   public function endpoint($heading, $distance, $options = array())
   {
      return self::endpointFor($this, $heading, $distance, $options);
   }

   /**
    * Finds the midpoint between a given point
    *
    * Options available:
    *
    * <ul>
    *   <li><b>units</b> <i>(string)</i>: Valid units are miles, kms and nms (default: miles)</li>
    * </ul>
    * @param mixed $other Any object with lat/lng properties (preferrably a LatLong)
    * @param array $options options hash
    * @return LatLong midpoint
    */
   public function midpointTo($other, $options = array())
   {
      return self::midpointBetween($this, $other, $options);
   }
}




