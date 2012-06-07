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
 * Bounds class
 */
class Bounds
{
   /**
    * Southwest bounds
    * @var LatLong
    */
   public $sw = null;
   
   /**
    * Northeast bounds
    * @var LatLong
    */
   public $ne = null;
   
   
   /**
    * Creates bounds based on point and radius
    * @param LatLong $point point
    * @param float $radius radius
    * @param array $options options hash
    * @return Bounds bounds
    */
   public static function fromPointAndRadius($point, $radius, $options = array())
   {
      $point = LatLong::normalize($point);
      
      $p0 = $point->endpoint(0, $radius, $options);
      $p90 = $point->endpoint(90, $radius, $options);
      $p180 = $point->endpoint(180, $radius, $options);
      $p270 = $point->endpoint(270, $radius, $options);
      
      $sw = new LatLong($p180->lat, $p270->lng);
      $ne = new LatLong($p0->lat, $p90->lng);
      
      return new Bounds($sw, $ne);
   }
   
   /**
    * Takes two points and creates a bounds, but first will normalize the points
    * @param mixed $thing first point
    * @param mixed $other second point
    * @return Bounds bounds
    */
   public static function normalize($thing, $other = null)
   {
      if ($thing instanceof Bounds) {
         return $thing;
      }
      
      if (is_null($other) && is_array($thing) && count($thing) == 2) {
         list($thing, $other) = $thing;
      }
      
      return new Bounds(LatLong::normalize($thing), LatLong::normalize($other));
   }
   
   /**
    * Constructor
    * @param LatLong $sw Southwest lat/lng
    * @param LatLong $ne Northeast lat/lng
    */
   public function __construct($sw, $ne)
   {
      if (!($sw instanceof LatLong) || !($ne instanceof LatLong)) {
         throw new InvalidArgumentException('Arguments must be instances of a LatLong class.');
      }
      
      $this->sw = $sw;
      $this->ne = $ne;
   }
   
   /**
    * Finds the center of the bounds
    * @return LatLong midpoint
    */
   public function center()
   {
      return $this->sw->midpointTo($this->ne);
   }
   
   /**
    * Does the bounds contain the point?
    * @param LatLong $point point
    * @return boolean contains the point
    */
   public function contains($point)
   {
      $point = LatLong::normalize($point);
      $rc = $point->lat > $this->sw->lat && $point->lat < $this->ne->lat;
      
      if ($this->crossesMeridian()) {
         $rc = $rc && ($point->lng < $this->ne->lng || $point->lng > $this->sw->lng);
      } else {
         $rc = $rc && ($point->lng < $this->ne->lng && $point->lng > $this->sw->lng);
      }
      
      return (bool) $rc;
   }
   
   /**
    * Does the bounds cross the prime meridian?
    * @return boolean crosses the meridian
    */
   public function crossesMeridian()
   {
      return $this->sw->lng > $this->ne->lng;
   }
   
   /**
    * Are two bounds equal?
    * @param Bounds $bounds other bounds
    * @return boolean are equal
    */
   public function equal($other)
   {
      return ($other instanceof Bounds) ? $this->sw == $other->sw && $this->ne == $other->ne : false;
   }

   /**
    * Returns a LatLong whose coordinates represent the size of a rectangle defined by these bounds
    *
    * Equivalent to Google Maps API's .toSpan() method on GLatLng's
    * @return LatLong span
    */
   public function toSpan()
   {
      $lat_span = abs($this->ne->lat - $this->sw->lat);
      $lng_span = abs($this->crossesMeridian() ? 360 + $this->ne->lng - $this->sw->lng : $this->ne->lng - $this->sw->lng);
      
      return new LatLong($lat_span, $lng_span);
   }

   /** 
    * Convert to string
    * @return string converted string
    */
   public function __toString()
   {
      return $this->sw . ',' . $this->ne;
   }
}
