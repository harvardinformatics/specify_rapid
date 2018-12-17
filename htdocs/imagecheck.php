<?php

define("BASE_IMAGE_PATH","/var/www/htdocs/");
// define("PHPINCPATH","/var/phpincludes/");
define("PHPINCPATH","/var/www/phpincludes/");


include_once(PHPINCPATH."php-zxing/src/PHPZxing/PHPZxingBase.php");
include_once(PHPINCPATH."php-zxing/src/PHPZxing/PHPZxingDecoder.php");
include_once(PHPINCPATH."php-zxing/src/PHPZxing/PHPZxingInterface.php");
include_once(PHPINCPATH."php-zxing/src/PHPZxing/ZxingBarNotFound.php");
include_once(PHPINCPATH."php-zxing/src/PHPZxing/ZxingImage.php");

use PHPZxing\PHPZxingDecoder;

$config = array(
    'try_harder' => true,
    'multiple_bar_codes' => true
);

if (!function_exists("boolval")) { 
function boolval($val) {
  return $val;
}
}

$dir = BASE_IMAGE_PATH."huh_images/batch1/";

$files = scandir($dir,SCANDIR_SORT_ASCENDING);

$decoder = new PHPZxingDecoder($config);
$decoder->setJavaPath('/bin/java');

foreach ($files as $file) {

if (is_file("$dir$file")) { 
echo("$file \n");

$decodedData    = $decoder->decode("$dir$file");

// print_r($decodedData);

if (is_array($decodedData)) { 

foreach($decodedData as $result) { 
   if ($result!=null && $result->isFound() && trim($result->getFormat())=="CODE_39") { 
      echo($result->getImageValue()."\n"); 
   }
}
} else { 
   $result = $decodedData;
  
   if ($result!=null &&$result->isFound() && trim($result->getFormat())=="CODE_39") { 
      echo($result->getImageValue()."\n"); 
   } else { 
      echo("No Barcode Found.\n"); 
   }
}

}

}

?>