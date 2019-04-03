<?php

include_once("imagehandler.php");
include_once("connection_library.php");

# Data Structures  *********************************

class PathFile {
   public $path;  // path to batch
   public $filename;  // next file in batch
   public $position; // numeric position of filename in batch
   public $batch_id; // ID of the batch
   public $filecount; // total files in the batch
}

# Supporting functions *****************************

/** Find out what the next batch is for the current user, and return the file at the current position from that batch as a PathFile object.
 *
 *  @return a TR_BATCH object.
 */
function getNextBatch() {
     global $connection, $user;
     // find the next batch to be worked on
     // TODO: db should be improved to record the last batch the user worked on
     $sql = 'select trub.position, trub.tr_batch_id from TR_USER_BATCH trub, TR_BATCH trb where trub.tr_batch_id = trb.tr_batch_id and trub.username = ? and trb.completed_date is null order by trub.position desc limit 1';
     if ($statement = $connection->prepare($sql)) {
        $statement->bind_param("s",$_SESSION["username"]);
        $statement->execute();
        $statement->bind_result($position,$batch_id);
        $statement->store_result();
        if ($statement->fetch()) {
          // nothing
        } else {
          $position = 1;
          $batch_id = 1;
        }
     } else {
       throw new Exception("Connection to database failed");
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
   if ($startDate!="") {
     $result = $startDate;
     if ($startDatePrecision==3) {
        $result = substr($startDate,0,4);
     }
     if ($startDatePrecision==2) {
        $result = substr($startDate,0,7);
     }
   }
   if ($endDate!="") {
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
     if (strlen($a_path)>0) {
        $this->path = $a_path;
        $sql = 'select tr_batch_id, image_batch_id, completed_date from TR_BATCH where path = ?';
        if ($statement = $connection->prepare($sql)) {
           $statement->bind_param("s",$this->path);
           $statement->execute();
           $statement->bind_result($batch_id, $image_batch_id, $completed_date);
           $statement->store_result();
           while ($statement->fetch()) {
               $this->batch_id = $batch_id;
               $this->image_batch_id = $image_batch_id;
               $this->completed_date = $completed_date;
               // make sure there is a TR_USER_BATCH entry for this batch for the current user.
               $this->selectOrCreateUserForBatch();
           }
           $statement->close();
           if (strlen($this->batch_id)==0) {
               throw new Exception('Batch not found for path '. $path);
           }
        } else {
            throw new Exception('Unable to connect to database.');
        }
     } else {
         throw new Exception('No path provided for batch.');
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
               throw new Exception('Batch not found for path '. $path);
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

  function movePosition($i) {
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

     // Check if there is a file at the new position
     $nextposition = $position + $i;
     $sql = 'select position from TR_BATCH_IMAGE where tr_batch_id = ? and position = ?';
     if ($statement = $connection->prepare($sql)) {
        $statement->bind_param("ii",$this->getBatchId(),$nextposition);
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
     $sql = "update TR_USER_BATCH set position = ? where username = ? and tr_batch_id = ?";
     if ($statement = $connection->prepare($sql)) {
        $statement->bind_param("isi",$nextposition,$_SESSION["username"],$batch_id);
        $statement->execute();
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
  	return $this->movePosition(1);
  }

  /** Find the previous file in this batch for the current user and move to it.
   *
   * @return a PathFile object containing the previous file and it's path, empty if no previous file found.
   */
  function decrementFile() {
  	return $this->movePosition(-1);
  }

  /** Find the file at a specified position in this batch without moving to it.
   *
   * @param position position of the file to return (starts at 1).
   * @return a PathFile object containing the file and it's path, null if no file found at the provided position.
   */
  function getFile($position) {
     global $connection, $user;
     $result = new PathFile();

     $sql = "select imlf.path, imlf.filename from TR_BATCH_IMAGE tbi, IMAGE_OBJECT imo, IMAGE_LOCAL_FILE imlf where tbi.IMAGE_OBJECT_ID = imo.ID and imo.IMAGE_LOCAL_FILE_ID = imlf.ID and tbi.TR_BATCH_ID = ? and tbi.POSITION = ? ;";

     if ($statement = $connection->prepare($sql)) {
        $statement->bind_param("ii", $this->getBatchID(), $position);
        $statement->execute();
        $statement->bind_result($path, $filename);
        $statement->store_result();
        if ($statement->fetch()) {
            $result->path = rtrim($path, '/') . '/';
            $result->filename = $filename;
            $result->position = $position;
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

  /** Reset the position in this batch for the current user to the specified position.
   *
   * @param position the position to move to.
   */
  function moveTo($position) {
     global $connection, $user;
     $result = new PathFile();
     // find the current batch
     $sql = 'update TR_USER_BATCH set position = ? where tr_batch_id = ? and username = ?';
     if ($statement = $connection->prepare($sql)) {
        $statement->bind_param("iis",$position, $this->batch_id,$_SESSION["username"]);
        $statement->execute();
     }
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
      $returnvalue .= '<header class="hfbox">';
      if ($error!="") {
      	$returnvalue .= "<h2>$error</h2>";
      }
      if ($user!=null) {
         if ($user->getAuthenticationState()==true) {
      	     $returnvalue .= $user->getUserHtml($this->targetPage);
             $returnvalue .= "
<script>

   const channel = new BroadcastChannel('imageclicks');
   const pingchannel = new BroadcastChannel('ping');

   function dosetup(barcode) {

      window.open('displayimage.php?mode=image&startbarcode='+barcode ,'_blank','modal=yes');

      window.location.href = 'transcribe.php?display=mainform&barcode='+barcode;

   }

   function dosetuppath(path,filename,position,mode) {

      window.open('displayimage.php?mode=imagefile&path='+path+'&filename='+filename ,'_blank','modal=yes');

      var added = '';
      if (mode=='test') { added = '&test=true'; }
      if (mode=='testminimal') { added = '&test=true&config=minimal'; }
      window.location.href = 'transcribe.php?display=mainform&path='+path+'&filename='+filename+'&position='+position+added;
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
         }
      }
      $returnvalue .= '</header>';
      $returnvalue .= '<div class="hfbox" style="padding: 0em;" >';
      return $returnvalue;
   }

   public function getFooter() {
   global $targethostdb;
   $returnvalue = '
	</div><!-- flex-main -->
        <div class="hfbox"><span id="loading"><img src="ui-anim_basic_16x16.gif">&nbsp;</span><span id="feedback">Status</span></div>
	<footer class="hfbox">Database: ' . $targethostdb . '</footer>
    <script>
       $(document).ready( function(){ $("#loading").hide(); } );
       $(document).ajaxStart(function(){ $("#loading").show(); });
       $(document).ajaxStop(function(){ $("#loading").hide(); });
    </script>
	';
   $returnvalue .= "</body>\n";
   $returnvalue .= "</html>";
   return $returnvalue;
   }

   public function getJQueryPageHead() {
     $returnvalue = "
       <script src='/jquery/jquery-3.2.1.min.js'></script>
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
    padding: 0.5em;
}
       </style>
     ";
     return $returnvalue;
   }

}

?>
