<?php
/*
 * This file is part of the GeoHelper library.
 *
 * (c) Matthew Vince <matthew.vince@phaseshiftllc.com>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

// include libraries

class GeoHelperAutoloader {
   public function __construct() {
      spl_autoload_register(array($this, 'loader'));
   }
   private function loader($className) {
      //only process our classes
      if (array_shift(explode('\\', $className)) !== 'GeoHelper') return;
      
      $file = __DIR__.DIRECTORY_SEPARATOR.str_replace('\\', DIRECTORY_SEPARATOR, ltrim($className, '\\')).".php";
      include_once $file;
   }
}

new GeoHelperAutoloader();