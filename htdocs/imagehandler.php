<?php

include_once("connection_library.php");
// PHPINCPATH must be defined in connnection_libary.php.
// PHPZxing library must be installed at the PHPINCPATH

include_once(PHPINCPATH."php-zxing/src/PHPZxing/PHPZxingBase.php");
include_once(PHPINCPATH."php-zxing/src/PHPZxing/PHPZxingDecoder.php");
include_once(PHPINCPATH."php-zxing/src/PHPZxing/PHPZxingInterface.php");
include_once(PHPINCPATH."php-zxing/src/PHPZxing/ZxingBarNotFound.php");
include_once(PHPINCPATH."php-zxing/src/PHPZxing/ZxingImage.php");

use PHPZxing\PHPZxingDecoder;


if (!function_exists("boolval")) { 
// for older versions of php.
function boolval($val) {
  // boolval is run on the values in the config array by PHPZxingDecoder.
  // passthrough is ok as replacement if config array allready contains boolean values.
  return $val;
}
}


class ImageHandler {
 
   private $dir;   //$dir = BASE_IMAGE_PATH."huh_images/batch1/";

   public static function scanAndStoreDirectory($path) {
      global $connection;
      $error = false;

      $result = "";
      // IMAGE_LOCAL_FILE.path is expected to end with a /
      if (substr($path,-1,1)!="/") {  
         $path = "$path/";
      }
      // IMAGE_LOCAL_FILE.path is expected to not start with a /
      if (substr($path,0,1)=="/") { 
          $path = substr($path,1);
      }

      // BASE_IMAGE_PATH must be defined in connection_library.php
      $dir = BASE_IMAGE_PATH.$path;
      if (file_exists($dir)) { 
         try { 
            @$files = scandir($dir,SCANDIR_SORT_ASCENDING);
         } catch (Exception $e) { 
            $result.="Error" + $e->getMessage();
            $error = true;
         }
      } else {
         $result.="Error: file not found.";
         $error = true;
      }

      $config = array(
          'try_harder' => true,
          'multiple_bar_codes' => true
      );
      $decoder = new PHPZxingDecoder($config);
      $decoder->setJavaPath('/bin/java');

      if (!$error) { 
      $addedfilecount = 0;
      $updatedfilecount = 0;
      foreach ($files as $file) {
         $pathfile = $dir.$file;
         if (is_file($pathfile)) {
            // only examine files, don't traverse into subdirectories.
            $sql = "select id, path, filename, barcode from IMAGE_LOCAL_FILE where path = ? and filename = ? ";
            $statement = $connection->prepare($sql);
            if ($statement) {
                $statement->bind_param("ss", $path, $file);
                $statement->execute();
                $statement->bind_result($id,$ilfpath,$filename,$barcode);
                $statement->store_result();
                if ($statement->num_rows>0) {
                   // record for file exists
                   if ($statement->fetch()) { 
                        if ($barcode=="") { 

                            if (preg_match("/(A|GH|FH|NEBC|ECON)([0-9]{8}).*/",$file,$matches)===true) { 
                               $barcode=$matches[2];
                            } else { 

                               $barcodes = self::checkFileForBarcodes($pathfile);
                               if (is_array($barcodes)) {
                                   $sql = "update IMAGE_LOCAL_FILE set barcode = ? ";

                                   $updatedfilecount++;
                               } 
                            }
                        } else { 
                            // file with known barcode
                        } 
                   } else { 
                       $result.= "Query Error: " . $connection->error  . " ";
                       $error = true;
                   } 
                } else { 
                   // no record for file exists 
                   $barcode = "";
                   $barcodes = self::checkFileForBarcodes($pathfile);
                   if (is_array($barcodes) && count($barcodes)>0) { 
                      $barcode = $barcodes[0];
                   } 
                   $sql = "insert into IMAGE_LOCAL_FILE (base,path,filename,extension,barcode,mimetype) values (?,?,?,?,?,?) ";
                   $statement1 = $connection->prepare($sql);
                   if ($statement1) {
                       $mimetype = mime_content_type($pathfile);
                       $extension = pathinfo($pathfile, PATHINFO_EXTENSION);
                       $base = BASE_IMAGE_PATH;
                       $statement1->bind_param("ssssss", $base, $path,$file,$extension,$barcode,$mimetype);
                       $statement1->execute();
                       $rows = $connection->affected_rows;
                       if ($rows==1) { $addedfilecount++; }
                   } else {
                       $result.= "Insert Error: " . $connection->error  . " ";
                       $error = true;
                   }
                   $statement1->close();
                   
                }
                $statement->free_result();
                $statement->close();
            } else { 
               $result.= "Query Error: " . $connection->error  . " ";
               $error = true;
            }
         } 
         // TODO: Add IMAGE_OBJECT, IMAGE_SET, IMAGE_BATCH
         $image_batch_id = null;

      } //end for each files
      } // if not error 

      if (!$error) { 
 
         $sql = "select tr_batch_id from TR_BATCH where path = ? ";
         $statement = $connection->prepare($sql);
         $exists==false;
         if ($statement) {
             $statement->bind_param("s",$path);
             $statement->execute();
             $statement->bind_result($tr_batch_id);
             $statement->store_result();
             if ($statement->fetch()) { 
                 $exists=true;
             }
             $statement->free_result();
             $statement->close();
         } else {
             $result.= "Query Error: " . $connection->error  . " ";
             $error = true;
         }
         if(!$exists) { 
            $sql = "insert into TR_BATCH (path, image_batch_id) values (?, ?) ";
            $statement1 = $connection->prepare($sql);
            if ($statement1) {
                $statement1->bind_param("si",$path,$image_batch_id);
                $statement1->execute();
                $rows = $connection->affected_rows;
                if ($rows==1) { $result .= "Batch Added. "; }
                $statement1->close();
            } else {
                $result.= "Insert Error: " . $connection->error  . " ";
                $error = true;
            }
         } else { 

         // TODO: Update Batch
         $sql = "update TR_BATCH set image_batch_id = ? where path = ? ";

         }

      }
      if (!$error) { 
          $result .= "Added $addedfilecount IMAGE_LOCAL_FILE records.  Updated $updatedfilecount records.";
      } else { 
          $result .= "<strong>Error</strong> Added $addedfilecount IMAGE_LOCAL_FILE records.  Updated $updatedfilecount records.";
      }
      return $result;
   } // end scan method


   public static function scanDirectory($directory) { 

      $dir = $directory;
      $files = scandir($dir,SCANDIR_SORT_ASCENDING);
      
      $config = array(
          'try_harder' => true,
          'multiple_bar_codes' => true
      );
      $decoder = new PHPZxingDecoder($config);
      $decoder->setJavaPath('/bin/java');
      
      foreach ($files as $file) {
         
         if (is_file("$dir$file")) { 
            echo("$file \n");
            $barcodes = self::checkFileForBarcodes("$dir$file");
            if (is_array($barcodes)) { 
               foreach ($barcodes as $barcode) {
                  echo("$barcode \n");  
               }
            }
         }
      
      } //end for each files
   
   } // end scan method
   
   /** 
    * Given a filename, check that file for barcodes.
    * 
    * @param file the path and filename for file to check.
    * @return an array of barcodes found, empty array if no barcodes
    *  were found.
    */
   public static function checkFileForBarcodes($file) { 

      $retval = array();

      $config = array(
          'try_harder' => true,
          'multiple_bar_codes' => true
      );
      $decoder = new PHPZxingDecoder($config);
      $decoder->setJavaPath('/bin/java');
      
      if (is_file($file)) { 
         $decodedData = $decoder->decode($file);
         // print_r($decodedData);
         if (is_array($decodedData)) { 
            // contains more than one barcode
            foreach($decodedData as $result) { 
               if ($result!=null && $result->isFound() && trim($result->getFormat())=="CODE_39") { 
                  array_push($retval,$result->getImageValue()); 
               }
            }
         } else { 
            // contains zero or one barcode
            $result = $decodedData;
           
            if ($result!=null &&$result->isFound() && trim($result->getFormat())=="CODE_39") { 
               array_push($retval,$result->getImageValue()); 
            } else { 
               // No Barcode Found.
            }
         }
      } // endif is_file
      
      return $retval;
   } // end check file for barcode method


   /* Given a filename, check to see if one or more barcodes are known for that filename, and 
    * optionally fail over to scaning the file for barcodes.
    * @param pathbelowbase the path to file from BASE_IMAGE_PATH.
    * @param file the filename to check.
    * @param scanfile (default=true) if no barcode is known for a file, scan the file for barcodes.
    *
    * @return an array containing the barcodes for the file, or an empty array if no barcodes are found.
    */
   public static function checkFilenameForBarcodes($pathbelowbase,$file,$scanfile=true) { 
       global $connection;
       $retval = array();

       if (substr($pathbelowbase,-1)=="/") { 
          $localpathfile = "$pathbelowbase$file";
       } else { 
          $localpathfile = "$pathbelowbase/$file";
       }
       $pathfile = BASE_IMAGE_PATH.'/'.$localpathfile;

       // check to see if the filename contains the barcode number, if so, return that.
       $amatch = preg_match("/^(A|FH|GH|NEBC|ECON)([0-9]+).*/",$file,$matches);
       if ($amatch===1) { 
          $retval = $matches[2];
       } else { 
          // look to see if a database record exists for this file with the barcode.
          $sql = "select id, path, filename, barcode from IMAGE_LOCAL_FILE where path = ? and filename = ? ";
          $statement = $connection->prepare($sql);
          if ($statement) {
                $statement->bind_param("ss", $pathbelowbase, $file);
                $statement->execute();
                $statement->bind_result($id,$dbpath,$dbfilename,$barcode);
                $statement->store_result();
                if ($statement->num_rows>0) {
                   // record for file exists
                   if ($statement->fetch()) {
                     $image_local_file_id = $id;
                     if (trim($barcode)!="") { 
                        $retval[] = $barcode;
                     }
                   } else { 
                      throw new Exception("Error querying IMAGE_LOCAL_FILE: " . $connection->error );
                   }
                }
                $statement->free_result();
                $statement->close();
          } else { 
             throw new Exception("Error preparing query on IMAGE_LOCAL_FILE: " . $connection->error );
          } 
          // Also check IMAGE_OBJECT.
          $sql = "select distinct identifier, object_name from IMAGE_OBJECT io " . 
                 " left join IMAGE_SET_collectionobject isco on io.image_set_id = isco.imagesetid " . 
                 " left join fragment f on isco.collectionobjectid = f.collectionobjectid where object_name = ? ";
          $statement = $connection->prepare($sql);
          if ($statement) {
                $statement->bind_param("s", $localpathfile);
                $statement->execute();
                $statement->bind_result($barcode, $object_name);
                $statement->store_result();
                if ($statement->num_rows>0) {
                   // record for file exists
                   if ($statement->fetch()) {
                      $image_local_file_id = $id;
                      if (trim($barcode)!="") { 
                          if (!array_key_exists($barcode,$retval)) {
                             $retval[] = $barcode;
                          }
                      }
                   } else { 
                      throw new Exception("Error querying IMAGE_OBJECT: " . $connection->error );
                   }
                }
                $statement->free_result();
                $statement->close();
          } else { 
             throw new Exception("Error preparing query on IMAGE_OBJECT: " . $connection->error );
          } 

          if ($scanfile===true && $retval=="") { 
             // otherwise try to find barcodes in the image and add them to the list
             $barcodes = self::checkFileForBarcodes("$pathfile");
             if (is_array($barcodes) && count($barcodes)>0) {
                $retval = array_unique(array_merge($retval,$barcodes));   
             }
          }
       }
       return $retval;
   }

   /* Given a filename, check to see if a barcode is known for that filename, and 
    * optionally fail over to scaning the file for a barcode.
    * @param pathbelowbase the path to file from BASE_IMAGE_PATH.
    * @param file the filename to check.
    * @param scanfile (default=true) if no barcode is known for a file, scan the file for a barcode.
    *
    * @return the barcode for the file (one from list if the file has more than one barcode), or null
    *   if no barcode was found.
    */
   public static function checkFilenameForBarcode($pathbelowbase,$file,$scanfile=true) { 
       $barcodearray = ImageHandler::checkFilenameForBarcodes($pathbelowbase,$file,$scanfile);
       $retval = array_shift($barcodearray); // get the first element from the array of barcodes
       return $retval;
   }

} // end class

//echo(ImageHandler::checkFilenameForBarcode("foo","A01234567.jpg"));
//ImageHandler::scanDirectory(BASE_IMAGE_PATH."huh_images/batch1/");

?>