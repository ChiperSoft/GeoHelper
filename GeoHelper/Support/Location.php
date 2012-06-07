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
 * Location class
 */
class Location extends LatLong
{
   /**
    * Street address
    * @var string
    */
   public $street_address = null;
   
   /**
    * City
    * @var string
    */
   public $city = null;
   
   /**
    * State
    * @var string
    */
   public $state = null;
   
   /**
    * Zipcode
    * @var string
    */
   public $zip = null;
   
   /**
    * Full address
    * @var string
    */
   public $full_address = null;
   
   /**
    * District
    * @var string
    */
   public $district = null;
   
   /**
    * Province
    * @var string
    */
   public $province = null;
   
   /**
    * Country
    * @var string
    */
   public $country = null;
   
   /**
    * Country code
    * @var string
    */
   public $country_code = null;
   
   /**
    * List of all matches
    * @var array
    */
   public $all = array();
   
   /**
    * Success flag
    * @var boolean
    */
   public $success = false;
   
   /**
    * Geocode provider used
    * @var string
    */
   public $provider = null;
   
   /**
    * Accuracy
    * @var string
    */
   public $accuracy = null;
   
   /**
    * Precision
    * @var integer
    */
   public $precision = null;
   
   /**
    * Suggested map bounds
    * @var Bounds
    */
   public $suggested_bounds = null;
   
   
   /**
    * Constructor
    * @param array $attr attributes hash
    */
   public function __construct($attr = array())
   {
      foreach ($attr as $key => $value) {
         $this->$key = $value;
      }
      
      parent::__construct($this->lat, $this->lng);
   }
   
   /**
    * Is location in the US?
    * @return boolean in US
    */
   public function isUs()
   {
      return $this->country_code == 'US';
   }
   
   /**
    * Was the geocoding a success?
    * @return boolean success
    */
   public function success()
   {
      return (bool) $this->success;
   }
   
   /**
    * Get the full address
    * @return string full address
    */
   public function fullAddress()
   {
      return !is_null($this->full_address) ? $this->full_address : $this->toGeocodableString();
   }
   
   /**
    * Get the street number if available
    * @return string street number
    */
   public function streetNumber()
   {
      if (preg_match('/([0-9]+)/', $this->street_address, $matches)) {
         return $matches[0];
      }
   }
   
   /**
    * Get the street name if available
    * @return string street name
    */
   public function streetName()
   {
      return !is_null($this->street_address) ? trim(substr($this->street_address, strlen($this->streetNumber()))) : '';
   }
   
   /**
    * Gets a geocodable representation of the object
    * @return string geocodable string
    */
   public function toGeocodableString()
   {
      $parts = array();
      foreach (array('street_address', 'district', 'city', 'province', 'state', 'zip', 'country_code') as $key) {
         if ($this->$key != '') {
            $parts[] = $this->$key;
         }
      }
      
      return trim(implode(', ', $parts));
   }
   
   /**
    * Convert to string
    * @return string converted string
    */
   public function __toString()
   {
      return $this->toGeocodableString();
   }
}
