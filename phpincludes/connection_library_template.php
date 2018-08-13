<?php

// Example connection_library.php file.  Change paths in execstring, targethost, targetdatabase, username, 
// and password to fit local configuration.

$debug = false;

$execstring = '/var/www/phpexec/java -jar /var/www/phpinclude/Encryption.jar decrypt ';

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