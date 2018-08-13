<?php

// Example connection_library.php file.  Change paths in execstring, targethost, targetdatabase, username, 
// and password to fit local configuration.


define("BASE_IMAGE_PATH","/var/www/");
define("BASE_IMAGE_URI","http://localhost/");

$debug = false;

// When using older php with safe_mode_exec_dir, put symbolic link to java in a phpexec directory
// Current php, just point to java executable.
$execstring = '/usr/bin/java -jar /var/www/phpinclude/Encryption.jar decrypt ';

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
