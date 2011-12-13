<?php

$debug = false;

$execstring = '/var/www/phpexec/java -jar /var/www/phpinclude/Encryption.jar decrypt ';

function specify_connect() {
   global $targethostdb;
   $returnvalue = false;
  
   $targethost = 'localhost';
   $targetdatabase = 'specify';
   $targethostdb = "$targethost:$targetdatabase";

   $connection = mysqli_connect($targethost,'username','password', $targetdatabase);
   if ($connection) { 
      $connection->set_charset('utf8');
      $returnvalue = $connection;
   } else { 
      echo mysqli_connect_errorno();
   }
   return $returnvalue;
}

?>
