<?php

include_once("imagehandler.php");
include_once("connection_library.php");

# Data Structures  *********************************

class PathFile {
   public $path;  // path to batch
   public $filename;  // next file in batch
   public $webPath; // path to file served by AWS, Cyverse, etc.
   public $position; // numeric position of filename in batch
   public $barcode; // barcode for this position, null if none
   public $batch_id; // ID of the batch
   public $filecount; // total files in the batch
}

# Supporting functions *****************************

/** Find out what the next batch is for the current user: the open (not completed) batch
 *  the user most recently worked on, by TR_USER_BATCH.last_accessed, falling back to the
 *  highest position for rows that have no last_accessed value.
 *
 *  @return a TR_BATCH object, or null if the user has no open batch.
 */
function getNextBatch() {
     global $connection, $user;
     $batch_id = null;
     // MySQL sorts NULL last_accessed values last in descending order.
     $sql = 'select trub.tr_batch_id from TR_USER_BATCH trub, TR_BATCH trb where trub.tr_batch_id = trb.tr_batch_id and trub.username = ? and trb.completed_date is null order by trub.last_accessed desc, trub.position desc limit 1';
     if ($statement = $connection->prepare($sql)) {
        $statement->bind_param("s",$_SESSION["username"]);
        $statement->execute();
        $statement->bind_result($found_batch_id);
        $statement->store_result();
        if ($statement->fetch()) {
          $batch_id = $found_batch_id;
        }
        $statement->free_result();
        $statement->close();
     } else {
       throw new Exception("Connection to database failed");
     }

     if ($batch_id==null) {
        // no open batch for this user
        return null;
     }

     $batch = new TR_BATCH();
     $batch->setID($batch_id);

     return $batch;
}

function getBatch($path) {

    $batch = new TR_BATCH();
    $batch->setPath($path);
    return $batch;
}

/**
 * Assemble startDate, endDate, and date precisions into an ISO date string.
 * @param startDate in yyyy-mm-dd format
 * @param startDatePrecision integer 1-3 specifies precision of start date 3=yyyy 2=yyyy-mm
 * @param endDate in yyyy-mm-dd format
 * @param endDatePrecision integer 1-3 specifies precision of end date 3=yyyy 2=yyyy-mm
 *
 * @return a string representing the provided date or date range in ISO format
 */
function dateBitsToString($startDate,$startDatePrecision,$endDate,$endDatePrecision) {
   $result = "";
   if ($startDate!=null && $startDate!="") {
     $result = $startDate;
     if ($startDatePrecision==3) {
        $result = substr($startDate,0,4);
     }
     if ($startDatePrecision==2) {
        $result = substr($startDate,0,7);
     }
   }
   if ($endDate!=null && $endDate!="") {
     if ($result!="") { $result = "$result/"; }
     if ($endDatePrecision==3) {
        $endDate = substr($endDate,0,4);
     }
     if ($endDatePrecision==2) {
        $endDate = substr($endDate,0,7);
     }
     $result = "$result$endDate";
   }
   return $result;
}

/** Return one barcode known for a file, or an empty string if none is known.
  *
  * @param pathbelowbase path from BASE_IMAGE_PATH to the file.
  * @param filename to check for barcodes (database lookup only)
  * @return a string containing a barcode, or an empty string if none was known.
  */
function getBarcodeForFilename($pathbelowbase, $filename) {
    global $connection;
    $result = "";
    $barcodes = ImageHandler::checkFilenameForBarcodes($pathbelowbase,$filename,false);
    if ($barcodes!=null && count($barcodes)>0){
       $result = $barcodes[0];
    }
    return $result;
}

/** Normalize a barcode string into the forms it may be stored in.
  *
  * @param barcode a barcode as entered by a user (may lack leading zeros).
  * @return an array of distinct candidate values: the 8 digit zero padded form
  *   and the form with leading zeros removed, empty array if no digits are present.
  */
function barcodeVariants($barcode) {
    $digits = substr(preg_replace('/[^0-9]/','',$barcode),0,20);
    if (strlen($digits)==0) { return array(); }
    $padded = str_pad($digits,8,"0",STR_PAD_LEFT);
    $unpadded = ltrim($digits,"0");
    // Note: strict comparisons are required, "12345"=="00012345" is true in PHP (numeric strings).
    $result = array($padded);
    if (strlen($unpadded)>0 && $unpadded!==$padded) { $result[] = $unpadded; }
    if ($digits!==$padded && $digits!==$unpadded) { $result[] = $digits; }
    return $result;
}

/** Find every place a barcode occurs in transcription batches (TR_BATCH_IMAGE.barcode).
  *
  * @param barcode the barcode to search for (zero padding is optional).
  * @return an array of associative arrays, one for each batch position where the barcode is found,
  *   with keys batch_id, batch_path, completed, position, filecount, path, filename, image_set_id, barcode.
  */
function lookupBatchesForBarcode($barcode) {
    global $connection;
    $result = array();
    $variants = barcodeVariants($barcode);
    if (count($variants)==0) { return $result; }
    // pad the variant list to exactly three values so that a single prepared statement can be used.
    while (count($variants)<3) { $variants[] = $variants[0]; }
    $sql = "select tbi.tr_batch_id, trb.path, IF(trb.completed_date is not null, 1, 0) as completed,
                   tbi.position, tbi.barcode, imo.image_set_id, imlf.path, imlf.filename,
                   (select count(*) from TR_BATCH_IMAGE c where c.tr_batch_id = tbi.tr_batch_id) as filecount
              from TR_BATCH_IMAGE tbi
              join TR_BATCH trb on tbi.tr_batch_id = trb.tr_batch_id
              left join IMAGE_OBJECT imo on tbi.image_object_id = imo.id
              left join IMAGE_LOCAL_FILE imlf on imo.image_local_file_id = imlf.id
             where tbi.barcode in (?,?,?)
             order by completed, trb.path, tbi.position";
    if ($statement = $connection->prepare($sql)) {
        $statement->bind_param("sss",$variants[0],$variants[1],$variants[2]);
        $statement->execute();
        $statement->bind_result($batch_id,$batch_path,$completed,$position,$foundbarcode,$image_set_id,$path,$filename,$filecount);
        $statement->store_result();
        while ($statement->fetch()) {
            $row = array();
            $row['batch_id'] = $batch_id;
            $row['batch_path'] = $batch_path;
            $row['completed'] = $completed;
            $row['position'] = $position;
            $row['filecount'] = $filecount;
            $row['barcode'] = $foundbarcode;
            $row['image_set_id'] = $image_set_id;
            $row['path'] = ($path===null) ? '' : rtrim($path,'/').'/';
            $row['filename'] = ($filename===null) ? '' : $filename;
            $result[] = $row;
        }
        $statement->free_result();
        $statement->close();
    } else {
        throw new Exception("Query preparation failed: ".$connection->error);
    }
    return $result;
}

/** Image URLs without depending on kiki.
  *
  * Image objects in the "huhimagestorage" repository have URLs of the form
  * http://kiki.huh.harvard.edu/databases/image.php?id=<IMAGE_LOCAL_FILE.id>[&convert=jpeg].
  * kiki's image.php (specify_web project) looks up that IMAGE_LOCAL_FILE row and redirects to
  * the copy of the file on S3, or, for TIFF files or convert=jpeg, returns a text notice instead
  * of an image.  resolveImageUrl() does the same conversion here, so images are loaded from S3
  * directly.
  */

/** True if a REPOSITORY.URL_PREFIX points at an image.php service like kiki's. */
function isImagePhpUrlPrefix($url_prefix) {
    return preg_match('#/databases/image\.php\?$#', (string)$url_prefix) == 1;
}

/** The S3 URL for a file in huh image storage, using the same path mapping as kiki's image.php:
  * huhimagestorage/HerbariaN/... becomes herbariaN/..., and huhimagestorage/huhspecimenimages/...
  * becomes huhspecimenimages/....
  *
  * @param path IMAGE_LOCAL_FILE.path (e.g. huhimagestorage/huhspecimenimages/JPG-Preview/)
  * @param filename IMAGE_LOCAL_FILE.filename
  */
function s3UrlForLocalFile($path, $filename) {
    $path = preg_replace("/huhimagestorage\/Herbaria/", "herbaria", (string)$path);
    $path = preg_replace("/huhimagestorage\/huhspecimenimages/", "huhspecimenimages", $path);
    if (strlen($path)>0 && substr($path,-1)!='/') { $path .= '/'; }
    return "https://s3.amazonaws.com/" . $path . $filename;
}

/** Look up path, filename and mimetype of an IMAGE_LOCAL_FILE row, caching results for the request.
  * @return an associative array with keys path, filename, mimetype, or null if not found.
  */
function lookupImageLocalFile($image_local_file_id) {
    global $connection;
    static $cache = array();
    if (array_key_exists($image_local_file_id, $cache)) { return $cache[$image_local_file_id]; }
    $result = null;
    $sql = "select path, filename, mimetype from IMAGE_LOCAL_FILE where id = ?";
    if ($statement = $connection->prepare($sql)) {
        $statement->bind_param("i", $image_local_file_id);
        $statement->execute();
        $statement->bind_result($path, $filename, $mimetype);
        $statement->store_result();
        if ($statement->fetch()) {
            $result = array('path'=>$path, 'filename'=>$filename, 'mimetype'=>$mimetype);
        }
        $statement->free_result();
        $statement->close();
    }
    $cache[$image_local_file_id] = $result;
    return $result;
}

/** The URL to use for an image object, given its REPOSITORY.URL_PREFIX and IMAGE_OBJECT.URI.
  * URLs through kiki's image.php are converted to the S3 URL kiki would redirect to; other
  * URLs are returned unchanged.
  *
  * @return the URL, or null if there is no displayable image (no URI, IMAGE_LOCAL_FILE row
  *   not found, or a TIFF / convert=jpeg request, for which kiki returns a text notice).
  */
function resolveImageUrl($url_prefix, $uri) {
    if ($url_prefix===null || $uri===null || $uri==='') { return null; }
    if (!isImagePhpUrlPrefix($url_prefix)) { return $url_prefix . $uri; }
    $params = array();
    parse_str((string)$uri, $params);
    $id = isset($params['id']) ? preg_replace('/[^0-9]/', '', $params['id']) : '';
    $convert = isset($params['convert']) ? preg_replace('/[^a-z]/', '', $params['convert']) : '';
    if (strlen($id)==0) { return null; }
    $file = lookupImageLocalFile(intval($id));
    if ($file==null) { return null; }
    if ($file['mimetype']=='image/tiff' || $convert=='jpeg' || $convert=='jpg') { return null; }
    return s3UrlForLocalFile($file['path'], $file['filename']);
}

/** Find the images available for display for an image set.
  *
  * @param image_set_id the IMAGE_SET to look up.
  * @return an associative array with keys url, thumbnail, pixel_height, pixel_width
  *   (url/thumbnail are null if no web accessible image object is known).
  */
function lookupImageUrlsForImageSet($image_set_id) {
    global $connection;
    $result = array('url'=>null,'thumbnail'=>null,'pixel_height'=>null,'pixel_width'=>null,'create_date'=>null);
    // object_type_id 3 is the web jpeg used by the edit form (see TR_Batch::getFile()), 2 is a thumbnail.
    $sql = "select io.object_type_id, repo.url_prefix, io.uri, io.pixel_height, io.pixel_width, io.create_date
              from IMAGE_OBJECT io
              left join REPOSITORY repo on io.repository_id = repo.id
             where io.image_set_id = ? and io.active_flag = 1
             order by io.object_type_id";
    if ($statement = $connection->prepare($sql)) {
        $statement->bind_param("i",$image_set_id);
        $statement->execute();
        $statement->bind_result($object_type_id,$url_prefix,$uri,$pixel_height,$pixel_width,$create_date);
        $statement->store_result();
        $rows = array();
        while ($statement->fetch()) {
            $rows[] = array($object_type_id,$url_prefix,$uri,$pixel_height,$pixel_width,$create_date);
        }
        $statement->free_result();
        $statement->close();
        foreach ($rows as $row) {
            list($object_type_id,$url_prefix,$uri,$pixel_height,$pixel_width,$create_date) = $row;
            // the image set's date is the latest create date of any of its image objects
            if ($create_date!=null && ($result['create_date']==null || strcmp($create_date,$result['create_date'])>0)) {
                $result['create_date'] = $create_date;
            }
            if ($object_type_id==3 && $result['url']==null) {
                $url = resolveImageUrl($url_prefix, $uri);
                if ($url!=null) {
                    $result['url'] = $url;
                    $result['pixel_height'] = $pixel_height;
                    $result['pixel_width'] = $pixel_width;
                }
            }
            if ($object_type_id==2 && $result['thumbnail']==null) {
                $result['thumbnail'] = resolveImageUrl($url_prefix, $uri);
            }
        }
    }
    return $result;
}

/** usort comparison function, orders image sets from lookupImagesForBarcode() newest first.
  * IMAGE_SET ids are assigned in insertion order, so the highest id is the most recently added.
  * (IMAGE_OBJECT.CREATE_DATE is empty for most image objects, so it is only used to break ties.)
  */
function compareImagesNewestFirst($a, $b) {
    $c = intval($b['image_set_id']) - intval($a['image_set_id']);
    if ($c==0) { $c = strcmp((string)$b['create_date'], (string)$a['create_date']); }
    return $c;
}

/** Find all images associated with a barcode, either linked to the specimen record
  * through IMAGE_SET_collectionobject, or present in a transcription batch (TR_BATCH_IMAGE).
  *
  * @param barcode the barcode to look up.
  * @param imgsrc 'RC' to prefer local files served from BASE_IMAGE_URI for batch images, otherwise web images.
  * @return an array of associative arrays, one per image set, each with keys image_set_id, url,
  *   thumbnail, pixel_height, pixel_width, linked (true if linked to the specimen record) and
  *   batches (array of batch positions from lookupBatchesForBarcode() in which the image occurs).
  */
function lookupImagesForBarcode($barcode, $imgsrc='AWS') {
    global $connection;
    $sets = array();
    $variants = barcodeVariants($barcode);
    if (count($variants)==0) { return array(); }
    $padded = $variants[0];

    // images linked to the specimen record
    $sql = "select distinct isco.imagesetid
              from fragment f
              join IMAGE_SET_collectionobject isco on f.collectionobjectid = isco.collectionobjectid
             where f.identifier = ?
             order by isco.imagesetid";
    if ($statement = $connection->prepare($sql)) {
        $statement->bind_param("s",$padded);
        $statement->execute();
        $statement->bind_result($image_set_id);
        $statement->store_result();
        while ($statement->fetch()) {
            $sets[$image_set_id] = array('image_set_id'=>$image_set_id, 'linked'=>true, 'batches'=>array(), 'localuri'=>null);
        }
        $statement->free_result();
        $statement->close();
    } else {
        throw new Exception("Query preparation failed: ".$connection->error);
    }

    // images in transcription batches
    $batches = lookupBatchesForBarcode($barcode);
    foreach ($batches as $batchrow) {
        $id = $batchrow['image_set_id'];
        if ($id==null) { $id = 'batch'.$batchrow['batch_id'].'_'.$batchrow['position']; }
        if (!array_key_exists($id,$sets)) {
            $sets[$id] = array('image_set_id'=>$batchrow['image_set_id'], 'linked'=>false, 'batches'=>array(), 'localuri'=>null);
        }
        $sets[$id]['batches'][] = $batchrow;
        if ($sets[$id]['localuri']==null && strlen($batchrow['filename'])>0 && defined('BASE_IMAGE_URI')) {
            $sets[$id]['localuri'] = BASE_IMAGE_URI.$batchrow['path'].$batchrow['filename'];
        }
    }

    // attach display urls
    $result = array();
    foreach ($sets as $set) {
        $urls = array('url'=>null,'thumbnail'=>null,'pixel_height'=>null,'pixel_width'=>null,'create_date'=>null);
        if ($set['image_set_id']!=null) {
            $urls = lookupImageUrlsForImageSet($set['image_set_id']);
        }
        // same fallback as the getrecord action: use the local file if requested or if no web image is known.
        if (($imgsrc=='RC' || $urls['url']==null) && $set['localuri']!=null) {
            $urls['url'] = $set['localuri'];
        }
        if ($urls['url']==null) { continue; }  // nothing displayable
        if ($urls['thumbnail']==null) { $urls['thumbnail'] = $urls['url']; }
        if (!$urls['pixel_height'] || !$urls['pixel_width']) {
            // same defaults as targetfile() in transcribe.php
            $urls['pixel_height'] = 4344;
            $urls['pixel_width'] = 2896;
        }
        $set['url'] = $urls['url'];
        $set['thumbnail'] = $urls['thumbnail'];
        $set['pixel_height'] = $urls['pixel_height'];
        $set['pixel_width'] = $urls['pixel_width'];
        $set['create_date'] = $urls['create_date'];
        unset($set['localuri']);
        $result[] = $set;
    }
    // most recently added image first (highest image set id).
    usort($result, 'compareImagesNewestFirst');
    return $result;
}

/** Return the markup for the barcode link icon (an external link to the specimen page on the HUH website).
 *  Starts greyed out; updateBarcodeLink() in the page header activates it when the barcode is found.
 */
function barcodeLinkIcon() {
   return "<a id='barcodelink' class='barcodelink' target='_blank' rel='noopener' aria-disabled='true' tabindex='-1' title='Barcode not found in the database'>"
        . "<svg viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' aria-hidden='true'>"
        . "<path d='M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6'/><polyline points='15 3 21 3 21 9'/><line x1='10' y1='14' x2='21' y2='3'/></svg></a>";
}

/** Echo the "Current Batch" controls shown on the batch selection page.
 *
 *  @param targetBatch a TR_Batch, or null if there is no current batch.
 */
function echoCurrentBatchControls($targetBatch) {
   $batchPath = null;
   if ($targetBatch!=null) {
      $batchPath = $targetBatch->getPath();
   }
   if ($batchPath==null || strlen($batchPath)==0) {
     echo " <strong>No Current Batch.</strong>";
     return;
   }
   $targetBatchCurrent = $targetBatch->getCurrentFile();
   $targetBatchFirst = $targetBatch->getFile(1);
   echo " <strong>Current Batch:</strong> <span style=\"font-family: ui-monospace, Menlo, Consolas, 'Courier New', monospace; font-size: 0.85em;\">[$batchPath]</span>";
   if ($targetBatchFirst!=null) {
      echo "<button type='button' onclick=' $(\"#cover\").fadeIn(100); dosetuppath(\"".urlencode($batchPath)."\",\"".urlencode($targetBatchFirst->path)."\",\"".urlencode($targetBatchFirst->filename)."\",\"1\",\"standard\");' class='ui-button'>Start from first</button>";
   }
   if ($targetBatchCurrent!=null && $targetBatchCurrent->position > 1) {
      $position = $targetBatchCurrent->position;
      echo "<button type='button' onclick=' $(\"#cover\").fadeIn(100); dosetuppath(\"".urlencode($batchPath)."\",\"".urlencode($targetBatchCurrent->path)."\",\"".urlencode($targetBatchCurrent->filename)."\",\"$position\",\"standard\");' class='ui-button ui' style='margin-left: 0.4em;' >Start from $position</button>";
   }
}

# Classes *******************************************

class TR_Batch {

  private $batch_id;
  private $path;
  private $image_batch_id;
  private $completed_date;
  private $filecount;

  // construct a new tr_batch, then call setPath(path) to initialize the tr_batch object from path.
  function setPath($a_path) {
    global $connection;

    $this->path = $a_path;
    $sql = 'select tr_batch_id, image_batch_id, completed_date from TR_BATCH where path = ?';
    if ($statement = $connection->prepare($sql)) {
       $statement->bind_param("s",$this->path);
       $statement->execute();
       $statement->bind_result($batch_id, $image_batch_id, $completed_date);
       $statement->store_result();
       if ($statement->fetch()) {
           $this->batch_id = $batch_id;
           $this->image_batch_id = $image_batch_id;
           $this->completed_date = $completed_date;
           // make sure there is a TR_USER_BATCH entry for this batch for the current user.
           $this->selectOrCreateUserForBatch();
       } else {
         throw new Exception('Batch not found for path '. $a_path);
       }
       $statement->close();
    } else {
        throw new Exception('Database connection failed.');
    }
  }

  // construct a new tr_batch, then call setID(id) to initialize the tr_batch object from a tr_batch_id.
  function setID($id) {
     global $connection;
     if (strlen($id)>0) {
        $this->batch_id = $id;
        $sql = 'select tr_batch_id, image_batch_id, completed_date, path from TR_BATCH where tr_batch_id = ?';
        if ($statement = $connection->prepare($sql)) {
           $statement->bind_param("i",$id);
           $statement->execute();
           $statement->bind_result($batch_id, $image_batch_id, $completed_date, $path);
           $statement->store_result();
           if ($statement->fetch()) {
               $this->batch_id = $batch_id;
               $this->path = $path;
               $this->image_batch_id = $image_batch_id;
               $this->completed_date = $completed_date;
               // make sure there is a TR_USER_BATCH entry for this batch for the current user.
               $this->selectOrCreateUserForBatch();
           }
           $statement->close();
           if (strlen($this->batch_id)==0) {
               throw new Exception('Batch not found for id '. $id);
           }
        } else {
            throw new Exception('Problem with database connection or query preparation.');
        }
     } else {
         throw new Exception('No id provided for batch.');
     }
  }

  function getBatchID() {
     return $this->batch_id;
  }
  function getPath() {
     return $this->path;
  }
  function getImageBatchID() {
     return $this->image_batch_id;
  }
  function getCompletedDate() {
     return $this->completed_date;
  }

  /**
   * Mark batch as complete
   */
   function setCompleted() {
     global $connection, $user;

     $this->movePosition(1);

     $sql = 'update TR_BATCH set completed_date = now() where tr_batch_id = ?';
     if ($statement = $connection->prepare($sql)) {
        $statement->bind_param("i", $this->batch_id);
        $statement->execute();
     } else {
       throw new Exception("Database connection failed");
     }
   }

  function getFileCount() {
    global $connection;

    if (! isset($this->filecount)) {

      $sql = "select count(*) from TR_BATCH_IMAGE where tr_batch_id = ?";
      if ($statement = $connection->prepare($sql)) {
         $statement->bind_param("i", $this->getBatchID());
         $statement->execute();
         $statement->bind_result($count);
         $statement->store_result();
         if ($statement->fetch()) {
           $this->filecount = $count;
         } else {
           throw new Exception("Could not find images for batch id ".$this->getBatchId());
         }
      } else {
        throw new Exception("Database connection failed");
      }

    }

    return $this->filecount;
  }


  function selectOrCreateUserForBatch() {
     global $connection, $user;
     $sql = 'select count(*) from TR_USER_BATCH where tr_batch_id = ? and username = ? ';
     if ($statement = $connection->prepare($sql)) {
        $statement->bind_param("is",$this->batch_id,$_SESSION["username"]);
        $statement->execute();
        $statement->bind_result($usercount);
        $statement->store_result();
        if ($statement->fetch()) {
           if ($usercount==0) {
               $sql = 'insert into TR_USER_BATCH (tr_batch_id,username) values (?,?) ';
               if ($statement1 = $connection->prepare($sql)) {
                   $statement1->bind_param("is",$this->batch_id,$_SESSION["username"]);
                   $statement1->execute();
                   $statement1->close();
               }
           }
        }
        $statement->free_result();
        $statement->close();
     }

  }

  /** Adjust position from current position by $i
   */
  function adjustPosition($i) {
    global $connection, $user;

    // Look up current position
    $sql = 'select ub.position, b.tr_batch_id from TR_USER_BATCH ub left join TR_BATCH b on ub.tr_batch_id = b.tr_batch_id  where b.tr_batch_id = ? and username = ?';
    if ($statement = $connection->prepare($sql)) {
       $statement->bind_param("is",$this->getBatchId(),$_SESSION["username"]);
       $statement->execute();
       $statement->bind_result($position, $batch_id);
       $statement->store_result();
       if ($statement->fetch()) {
         // nothing
       } else {
         throw new Exception("Could not find current batch/position for tr_batch_id [{$this->getBatchId()}]");
       }
       $statement->close();
    } else {
      throw new Exception("Database connection failed");
    }

    return $this->movePosition($position + $i);
  }

  /** Move current position in batch to $i
   */
  function movePosition($i) {
     global $connection, $user;

     $batch_id = $this->getBatchId();
     $username = $_SESSION["username"];

     // Check if there is a file at the new position
     $nextposition = $i;
     $sql = 'select position from TR_BATCH_IMAGE where tr_batch_id = ? and position = ?';
     if ($statement = $connection->prepare($sql)) {
        $statement->bind_param("ii",$batch_id,$nextposition);
        $statement->execute();
        $statement->bind_result($position2);
        $statement->store_result();
        if ($statement->fetch()) {
          // nothing
        } else {
          // No such position, go back to beginning, set position to 1
          $nextposition = 1;
        }
        $statement->close();
     } else {
       throw new Exception("Database connection failed");
     }

     // Update position
     // Record when the user last worked in this batch; getNextBatch() uses this to find the current batch.
     $sql = "update TR_USER_BATCH set position = ?, last_accessed = now() where username = ? and tr_batch_id = ?";
     if ($statement = $connection->prepare($sql)) {
        $statement->bind_param("isi",$nextposition,$username,$batch_id);
        $statement->execute();

        //if (mysql_affected_rows() < 1) {
        //  throw new Exception("Could not update position [$nextposition] for batch [$batch_id], user [$username]");
        //}

        $statement->close();
     } else {
       throw new Exception("Database connection failed");
     }

     return $this->getFile($nextposition);
  }

  /** Find the next file in this batch for the current user and move to it.
   *
   * @return a PathFile object containing the next file and it's path, empty if no next file found.
   */
  function incrementFile() {
  	return $this->adjustPosition(1);
  }

  /** Find the previous file in this batch for the current user and move to it.
   *
   * @return a PathFile object containing the previous file and it's path, empty if no previous file found.
   */
  function decrementFile() {
  	return $this->adjustPosition(-1);
  }

  /** Find the file at a specified position in this batch without moving to it.
   *
   * @param position position of the file to return (starts at 1).
   * @return a PathFile object containing the file and it's path, null if no file found at the provided position.
   */
  function getFile($position) {
     global $connection, $user;
     $result = new PathFile();

     $sql = "select imlf.path, imlf.filename, tbi.barcode, imo2.object_name, repo.ID, repo.URL_PREFIX, imo2.uri
             from TR_BATCH_IMAGE tbi
             join IMAGE_OBJECT imo on tbi.IMAGE_OBJECT_ID = imo.ID
             left join IMAGE_LOCAL_FILE imlf on imo.IMAGE_LOCAL_FILE_ID = imlf.ID
             left join IMAGE_OBJECT imo2 on imo.IMAGE_SET_ID = imo2.IMAGE_SET_ID and imo2.ACTIVE_FLAG = 1 and imo2.OBJECT_TYPE_ID = 3
             left join REPOSITORY repo on imo2.REPOSITORY_ID = repo.ID
             where tbi.TR_BATCH_ID = ?
               and tbi.POSITION = ?
             order by imo2.ID desc ;";

     if ($statement = $connection->prepare($sql)) {
        $statement->bind_param("ii", $this->getBatchID(), $position);
        $statement->execute();
        $statement->bind_result($path, $filename, $barcode, $objectname, $repoid, $repourl, $objecturi);
        $statement->store_result();
        // if an image set has more than one active web (type 3) image object, the newest is first.
        $found = $statement->fetch();
        $statement->free_result();
        $statement->close();
        if ($found) {
            $result->path = $path ? rtrim($path, '/') . '/' : '';
            $result->filename = $filename;
            $result->position = $position;
            $result->barcode = $barcode;

            // local path and filename of the image, below BASE_IMAGE_PATH
            $localuri = $result->path . $filename;
            $result->webPath = null;
            if ($repoid && $repourl && $objecturi) {
              // web image object, with kiki image.php URLs converted to S3
              $result->webPath = resolveImageUrl($repourl, $objecturi);
            }
            if ($result->webPath==null && strncmp('huhimagestorage/huhspecimenimages/', $localuri, 34) == 0) {
              // images stored under huhspecimenimages are also served from S3
              $result->webPath = s3UrlForLocalFile($result->path, $filename);
            }

            $result->batch_id = $this->getBatchID();
            $result->filecount = $this->getFileCount();
        } else {
          return null;
        }
     } else {
       throw new Exception("Connection to database failed");
     }

     return $result;
  }


  function getCurrentFile() {
     global $connection, $user;

     // find the current batch and the position in it for this user
     $sql = 'select position from TR_USER_BATCH where tr_batch_id = ? and username = ?';
     if ($statement = $connection->prepare($sql)) {
        $statement->bind_param("is",$this->batch_id,$_SESSION["username"]);
        $statement->execute();
        $statement->bind_result($position);
        $statement->store_result();
        if ($statement->fetch()) {
            // nothing
        } else {
          $position = 1;
        }
        $statement->close();
     } else {
       throw new Exception("Connection to the database failed");
     }
     return $this->getFile($position);
  }
}

class TPage extends Page {

   function __construct() {
      parent::__construct();
      $this->targetPage = "transcribe.php";
   }

   public function getHeader($user) {
      $error = $this->errormessage;
      $returnvalue = "<!DOCTYPE html>\n";
      $returnvalue .= "<html>\n";
      $returnvalue .= "<head>\n";
      $returnvalue .= "<meta charset='utf-8'/>\n";
      $returnvalue .= "<title>".$this->title."</title>\n";
      $returnvalue .= $this->getJQueryPageHead();
      $returnvalue .= $this->getHeadStyle();
      $returnvalue .= "</head>\n";
      $returnvalue .= "<body>\n";
      $returnvalue .= '<header>';
      if ($error!="") {
      	$returnvalue .= "<h2>$error</h2>";
      }
      if ($user!=null) {
         if ($user->getAuthenticationState()==true) {
             $returnvalue .= "
<script>

   const channel = new BroadcastChannel('imageclicks');
   const pingchannel = new BroadcastChannel('ping');

   // Window features for the magnified image window: a popup filling the right half of the
   // user's screen (the screen this page is on), so that it does not cover the form.
   function imageWindowFeatures() {
      var availLeft = (typeof screen.availLeft === 'number') ? screen.availLeft : 0;
      var availTop = (typeof screen.availTop === 'number') ? screen.availTop : 0;
      var width = Math.max(500, Math.round(screen.availWidth / 2));
      var height = screen.availHeight;
      var left = availLeft + screen.availWidth - width;
      return 'popup=yes,width=' + width + ',height=' + height + ',left=' + left + ',top=' + availTop;
   }
   function dosetup(barcode) {

      window.open('displayimage.php?mode=image&startbarcode='+barcode ,'_blank',imageWindowFeatures());

      window.location.href = 'transcribe.php?display=mainform&barcode='+barcode;

   }

   function dosetuppath(batchpath,filepath,filename,position,mode) {

      window.open('displayimage.php?mode=imagefile&imgsrc=AWS&path='+filepath+'&filename='+filename ,'_blank',imageWindowFeatures());

      var added = '';
      if (mode=='test') { added = '&test=true'; }
      if (mode=='testminimal') { added = '&test=true&config=minimal'; }
      window.location.href = 'transcribe.php?display=mainform&batch='+batchpath+'&position='+position+added;
   }
   // Open the Add/Edit Records form, for a barcode (an existing record) or blank.  For a blank form, the form opens
   // the magnified image window itself once a barcode with images is entered.
   function dosetupnewrecord(barcode) {
      var added = '';
      if (barcode) {
         added = '&barcode=' + encodeURIComponent(barcode);
         // Opening an existing record: open the magnified image window now, while handling the click,
         // as dosetuppath() does for batches.  A window opened later by the form (after its lookup)
         // is not part of a click and would be blocked.  The form reuses this window by its name.
         window.open('displayimage.php?mode=imagefile&imgsrc=AWS&path=&filename=', 'transcribeimagewindow', imageWindowFeatures());
      }
      window.location.href = 'transcribe.php?display=newrecord' + added;
   }
   function doclear() {
      channel.postMessage('close');
      window.location.href = 'transcribe.php?display=setup';

   }
   function ping() {
      pingchannel.postMessage('ping');
   }

   function goNext() {
      alert(\"TODO: Go next, load next image.\");
   }

   pingchannel.onmessage = function(e) {
      console.log(e);
      if (e.data=='ping') {
         pingchannel.postMessage('pong');
      }
      if (e.data=='pong') {
         alert('pong');
      }
   }


</script>
<style>
  .ui-autocomplete-loading {
    background: white url('ui-anim_basic_16x16.gif') right center no-repeat;
  }
</style>
              ";
             $returnvalue .= <<<'EOT'
<style>
  .barcodelink { display: inline-block; vertical-align: middle; margin-left: 4px; line-height: 0; color: #b0b0b0; cursor: default; pointer-events: none; }
  .barcodelink.active { color: #2e6da4; cursor: pointer; pointer-events: auto; }
  .barcodelink svg { width: 16px; height: 16px; }
</style>
<script>
   /* Activate the barcode link icon (linking to the specimen page on the HUH website) when the barcode
    * was found in the database (fragment or preparation table); otherwise show it greyed out.
    */
   function updateBarcodeLink(barcode, found) {
      var link = $('#barcodelink');
      var digits = String(barcode == null ? '' : barcode).replace(/[^0-9]/g, '');
      if (found && digits.length > 0 && digits.length <= 8) {
         var padded = ('00000000' + digits).slice(-8);
         link.attr('href', 'https://data.huh.harvard.edu/databases/specimen_search.php?barcode=' + encodeURIComponent(padded));
         link.attr('title', 'Open specimen ' + padded + ' on the HUH website');
         link.attr('aria-disabled', 'false').attr('tabindex', '0').addClass('active');
      } else {
         link.removeAttr('href');
         link.attr('title', 'Barcode not found in the database');
         link.attr('aria-disabled', 'true').attr('tabindex', '-1').removeClass('active');
      }
   }
</script>
EOT;
         }
      }
      $returnvalue .= '</header>';
      $returnvalue .= '<div class="hfbox" style="padding: 0em;" >';
      return $returnvalue;
   }

   public function getFooter($user=null) {
   global $targethostdb;
//	<footer class="hfbox">Database: ' . $targethostdb . '</footer>
   $returnvalue = '
	</div><!-- flex-main -->
        <div class="hfbox"><span id="loading"><img src="ui-anim_basic_16x16.gif">&nbsp;</span><span id="feedback">Status</span></div>
    <script>
       $(document).ready( function(){ $("#loading").hide(); } );
       $(document).ajaxStart(function(){ $("#loading").show(); });
       $(document).ajaxStop(function(){ $("#loading").hide(); });
    </script>
	';
   if (isset($user) && $user->getAuthenticationState()==true) {
     $returnvalue .= "<div class='hfbox'>";
     $returnvalue .= $user->getUserHtml($this->targetPage);
     $returnvalue .= "</div>";
   }
   $returnvalue .= "</body>\n";
   $returnvalue .= "</html>";
   return $returnvalue;
   }

   public function getJQueryPageHead() {
     $returnvalue = "
       <script src='https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js'></script>
       <script src='/jquery/zoom-master/jquery.zoom.js'></script>

       <script src='/jquery/jquery-ui-1.12.1.custom/jquery-ui.min.js'></script>
       <link rel='stylesheet' type='text/css' href='/jquery/jquery-ui-1.12.1.custom/jquery-ui.theme.min.css' />
       <script>
         $( ':submit' ).button({ classes: { 'ui-button': 'highlight' } });
       </script>
     ";
     return $returnvalue;
   }
   public function getHeadStyle() {
     $returnvalue = "
       <style>
html, body {
   height: 100%;
}
body {
   display: flex;
   flex-direction: column;
}
.flex-main {
    display: flex;
    flex-direction: row;
    align-items: stretch;
    flex-grow: 10;
}
.hfbox  {
    padding: 0.5em;
    border: 1px solid grey;
    border-radius: 3px;
}
.flexbox  {
    padding: 0em;
    display: flex;
    flex-direction: column;
    flex: 2;
}
       </style>
     ";
     return $returnvalue;
   }

}


/* ** Per-user settings, stored in TR_USER_SETTING (username, setting_name, setting_value, updated).
 *  A user with no row for a setting uses the default for that setting.
 */

/** Return the value of a setting for a user, or null if the user has not saved it. */
function getUserSetting($username, $settingname) {
   global $connection;
   $value = null;
   $sql = 'select setting_value from TR_USER_SETTING where username = ? and setting_name = ?';
   $statement = $connection->prepare($sql);
   if (!$statement) { throw new Exception('Query error: ' . $connection->error); }
   $statement->bind_param('ss', $username, $settingname);
   $statement->execute();
   $statement->bind_result($found);
   $statement->store_result();
   if ($statement->fetch()) { $value = $found; }
   $statement->free_result();
   $statement->close();
   return $value;
}

/** Save the value of a setting for a user, replacing any existing value. */
function setUserSetting($username, $settingname, $value) {
   global $connection;
   $sql = 'insert into TR_USER_SETTING (username, setting_name, setting_value) values (?, ?, ?) ' .
          'on duplicate key update setting_value = values(setting_value)';
   $statement = $connection->prepare($sql);
   if (!$statement) { throw new Exception('Query error: ' . $connection->error); }
   $statement->bind_param('sss', $username, $settingname, $value);
   if (!$statement->execute()) {
      $error = $statement->error;
      $statement->close();
      throw new Exception('Unable to save setting: ' . $error);
   }
   $statement->close();
}

/** Remove a setting for a user, so that the default applies. */
function deleteUserSetting($username, $settingname) {
   global $connection;
   $sql = 'delete from TR_USER_SETTING where username = ? and setting_name = ?';
   $statement = $connection->prepare($sql);
   if (!$statement) { throw new Exception('Query error: ' . $connection->error); }
   $statement->bind_param('ss', $username, $settingname);
   if (!$statement->execute()) {
      $error = $statement->error;
      $statement->close();
      throw new Exception('Unable to remove setting: ' . $error);
   }
   $statement->close();
}

/** The fields carried over by New Record on the Add/Edit Records form, as saved by the user,
 *  or null if the user uses the defaults (no saved setting, or an unreadable one).
 */
function getCarryoverFields($username) {
   $value = getUserSetting($username, 'carryover_fields');
   if ($value===null) { return null; }
   $fields = json_decode($value, true);
   if (!is_array($fields)) { return null; }
   return array_values(array_filter($fields, 'isValidFieldName'));
}

function isValidFieldName($name) {
   return is_string($name) && preg_match('/^[a-z_]{1,64}$/', $name);
}

?>
