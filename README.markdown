# GeoHelper

PHP 5 library to aid in development of map-based applications.  Original library nased on Ruby's [Geokit](http://geokit.rubyforge.org/).
Converted to PHP 5.3 namespacing by Jarvis Badgley (chiper at chipersoft dot com)

## What can it do?

Just about anything Geokit can do, minus the Rails specific helpers:

* **Distance calculations between two points on the earth.** Calculate the distance in miles or KM, with all the trigonometry abstracted away by GeoHelper.
* **Geocoding from multiple providers.** It supports Google, Yahoo, Bing, Geocoder.us, Geonames, and more. GeoHelper provides a uniform response structure from all of them. It also provides a fail-over mechanism, in case your input fails to geocode in one service.
* **IP-based location lookup utilizing hostip.info.** Provide an IP address, and get city name and latitude/longitude in return.

## Examples

Geocode an address:

    $api = new GeoHelper\MultiCoder();
    $work = $api->geocode('100 Spear st, San Francisco, CA');
    echo $work->ll()  // ll=latitude,longitude

Find the address near a latitude/longitude (reverse geocoding):

    $api = new GeoHelper\Google();
    $home = $api->reverseGeocode(array('37.792821', '-122.393992'));
    echo $home->fullAddress()
    >> 36-98 Mission St, San Francisco, CA 94105, USA

Find distances, headings, endpoints, and midpoints:

    $distance = $home->distanceFrom($work, array('units' => 'miles'));
    $heading = $home->headingTo($work); // result is in degrees, 0 is north
    $endpoint = $home->endpoint(90, 2); // two miles due east
    $midpoint = $home->midpointTo($work);

Test if a point is contained within bounds:

    $bounds = new GeoHelper\Support\Bounds($sw_point, $ne_point);
    $bounds->contains($home);

Find distance to a second location with on-the-fly geocoding:

    $api = new GeoHelper\MultiCoder();
    $location = $api->geocode('100 Spear St, San Francisco, CA');
    $distance = $location->distanceFrom('555 Battery St, San Francisco, CA');
   

## Configuration

To set the API keys for providers that require them:

    GeoHelper\GeocoderUS::$key = "username:password";
    GeoHelper\Yahoo::$key = "your_key";  // Yahoo v1
    GeoHelper\PlaceFinder::$key = "your_key";  // Yahoo v2
    GeoHelper\Bing::$key = "your_key";
   
To set the order of providers when using the `MultiCoder`:

    // valid keys are: GeocoderUs, Yahoo, PlaceFinder, Bing, Google 
    GeoHelper\MultiCoder::$provider_order = array('Google', 'PlaceFinder');
   
    // valid keys are: GeoPlugin, HostIp
    GeoHelper\MultiCoder::$ip_provider_order = array('GeoPlugin', 'HostIp');


## Running the tests

You'll need [PHPUnit 3.4+](http://www.phpunit.de/) installed to run the test suite.

* Open `test/phpunit.xml.dist` and modify as needed.
* Rename to `phpunit.xml`
* Run `phpunit` from within `/test` directory.


Copyright (c) 2010 Matthew Vince, released under the MIT license