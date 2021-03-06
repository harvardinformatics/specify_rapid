<?php

// Example connection_library.php file.  Change paths in execstring, targethost, targetdatabase, username, 
// and password to fit local configuration.


define("BASE_IMAGE_PATH","/var/www/");  // base for retrieving image files, substitute for base_image_uri on local filesystem, must end with /
define("BASE_IMAGE_URI","http://localhost/");  // IRI for retrieving image files, substitute for base_image_path for web access

define("PHPINCPATH","/var/www/phpincludes/");  // include location for php zxing library

define("BATCHPATH","");  // path below BASE_IMAGE in which batches can be found, if left as "" then batches may be anywhere under BASE_IMAGE_PATH, must end with /

$debug = false;

// When using older php with safe_mode_exec_dir, put symbolic link to java in a phpexec directory
// Current php, just point to java executable.
$execstring = '/usr/bin/java -jar /var/www/phpinclude/Encryption.jar decrypt ';
$vdateexecstring = '/usr/bin/java -jar /var/www/phpexec/event_date_qc-2.0.0-SNAPSHOT.jar -v ';

function specify_connect() {
   global $targethostdb;
   $returnvalue = false;
  
   $targethost = 'localhost';
   $targetdatabase = 'specify';
   $targethostdb = "$targethost:$targetdatabase";

   $connection = mysqli_connect($targethost,'_username_','_password_', $targetdatabase);
   if ($connection) { 
      $connection->set_charset('utf8');
      $returnvalue = $connection;
   } else { 
      echo mysqli_connect_errorno();
   }
   return $returnvalue;
}

?>