<?php

use GeoHelper\Support\Location, GeoHelper\Support\LatLong;


class BaseGeocoderTestCase extends PHPUnit_Framework_TestCase
{
   public function setup()
   {
      $this->full_address = '100 Spear St, San Francisco, CA, 94105, US';
      $this->short_address = 'San Francisco, CA, US';
      $this->ip = '74.125.65.147';
      $this->latlng = new LatLong(37.7742, -122.417068);
      $this->success = new Location(array(
         'street_address' => '100 Spear St',
         'city' => 'San Francisco',
         'state' => 'CA',
         'zip' => '94105',
         'country_code' => 'US',
         'lat' => $this->latlng->lat,
         'lng' => $this->latlng->lng,
      ));
      $this->success->success = true;
      $this->short_success = new Location(array(
         'city' => 'San Francisco',
         'state' => 'CA',
         'country_code' => 'US',
      ));
      $this->short_success->success = true;
   }
}