<?php

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

/** Find out what the next batch is for the current user, and return the first file from that batch as a PathFile object.
 * 
 *  @return a PathFile containing the path for the first batch for the current user and the first file for the current user in that batch, 
 *     empty if no batch or no next file.
 */
function getNextBatch() {
     global $connection, $user;
     $result = new PathFile();
     // find the next batch to be worked on
     $sql = 'select b.path, ub.position, b.tr_batch_id from TR_USER_BATCH ub left join TR_BATCH b on ub.tr_batch_id = b.tr_batch_id  where username = ? and b.completed_date is null limit 1';
     if ($statement = $connection->prepare($sql)) {
        $statement->bind_param("s",$_SESSION["username"]);
        $statement->execute();
        $statement->bind_result($path, $position,$batch_id);
        $statement->store_result();
        while ($statement->fetch()) {
            $result->path = $path;
            $result->position = $position;
            $result->batch_id= $batch_id;
        }
     }
     // find the first file to work on in the batch 
     if (strlen($result->path)>0) {
        $files = scandir(BASE_IMAGE_PATH.$result->path,SCANDIR_SORT_ASCENDING);
        $result->filename = $files[$result->position + 2]; // position + 2 to account for the directory entries . and ..
        $result->filecount = count($files) - 2;
     }

     return $result;
}

function getBarcodeForFilename($path, $filename) { 
    global $connection, $user;
    $result = "";
    // check if in image_local_file
    $sql = "select id, barcode from IMAGE_LOCAL_FILE where filename = ? and path = ? limit 1";
     if ($statement = $connection->prepare($sql)) {
        $statement->bind_param("ss",$filename,$path);
        $statement->execute();
        $statement->bind_result($id, $barcode);
        $statement->store_result();
        while ($statement->fetch()) {
            $result = $barcode;
            $image_local_file_id = $id;
        }
     }
     // TODO: Failovers
     // if image_local_file_id is not null and barcode is null, file exists but needs barcode read.
     // if image_local_file_id is null and barcode is null, check image_object, but file may either not contain a barcode (cover/folder) or need to be databased.

    return $result;
} 

/**
 * Given a barcode number, find data for the transcription form associated with that barcode.
 * 
 * @param barcode the barcode number to look up, can be zero padded or not.
 * @return an associative array of key value pairs of data for the transcription form, including 
 *   key status with values NOTFOUND, ERROR, FOUND, and key error containing any error message.
 */
function getDataForBarcode($barcode) { 
   $result = array( "status"=> "NOTFOUND", "error"=> "", "barcode"=> "", "created"=> "", "herbarium"=> "", "format"=> "", "prepmethod"=> "", "project"=> "", "highergeography"=> "", "highergeographyid"=> "", "filedundername"=> "", "filedundernameid"=> "", "filedunderqualifier"=> "", "currentname"=> "", "currentnameid"=> "", "currentqualifier"=> "", "collectingtrip"=> "", "collectors"=> "", "etal"=> "", "specificlocality"=> "", "stationfieldnumber"=> "", "verbatimdate"=> "", "datecollected"=> "", "namedplace"=> "", "verbatimelevation"=> "", "habitat"=> "" );

   // TODO: Implement.

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
           }
           if (strlen($this->path)>0) {
                $files = scandir(BASE_IMAGE_PATH.$this->path,SCANDIR_SORT_ASCENDING);
                $this->filecount = count($files) - 2;
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
           while ($statement->fetch()) {
               $this->batch_id = $batch_id;
               $this->path = $path;
               $this->image_batch_id = $image_batch_id;
               $this->completed_date = $completed_date;
           }
           if (strlen($this->path)>0) {
                $files = scandir(BASE_IMAGE_PATH.$this->path,SCANDIR_SORT_ASCENDING);
                $this->filecount = count($files) - 2;
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
     return $this->filecount;
  }

  /** Find the next file in this batch for the current user and move to it.
   * 
   * @return a PathFile object containing the next file and it's path, empty if no next file found.
   */
  function incrementFile() { 
     global $connection, $user;
     $result = new PathFile();
     // find the current batch
     $sql = 'select b.path, ub.position, b.tr_batch_id from TR_USER_BATCH ub left join TR_BATCH b on ub.tr_batch_id = b.tr_batch_id  where b.tr_batch_id = ? and username = ? and b.completed_date is null order by path limit 1';
     if ($statement = $connection->prepare($sql)) {
        $statement->bind_param("is",$this->batch_id,$_SESSION["username"]);
        $statement->execute();
        $statement->bind_result($path, $position,$batch_id);
        $statement->store_result();
        while ($statement->fetch()) {
            $result->path = $path;
            $result->position = $position;
        }
        $statement->close();
     }
     // find the current file in the batch 
     if (strlen($result->path)>0) {
        $files = scandir(BASE_IMAGE_PATH.$result->path,SCANDIR_SORT_ASCENDING);
        $result->filename = $files[$result->position + 2]; // position + 2 to account for the directory entries . and .. 
        $result->filecount = count($files) - 2;
        
        // does next file exist: 
        if (array_key_exists($result->position + 2 + 1,$files)) { 
           // move to next file in batch
           $result->position = $result->position + 1; // increment position to the next file.
           $result->filename = $files[$result->position + 2]; 
           // persist
           $sql = "update TR_USER_BATCH set position = position + 1 where username = ? and tr_batch_id = ?";
           if ($statement = $connection->prepare($sql)) {
              $statement->bind_param("si",$_SESSION["username"],$batch_id);
              $statement->execute();
              $statement->close();
           }
        } else { 
           // Done with batch
           $result = new PathFile();
           //TODO: enable when not in test mode.
           if (0==1) { 
               // mark batch as done
               $sql = "update TR_BATCH set completed_date = now() where tr_batch_id = ?";
               if ($statement = $connection->prepare($sql)) {
                  $statement->bind_param("i",$batch_id);
                  $statement->execute();
                  $statement->close();
               }
           } else { 
              // reset batch for user.
              $sql = "update TR_USER_BATCH set position = 0 where username = ? and tr_batch_id = ?";
              if ($statement = $connection->prepare($sql)) {
                 $statement->bind_param("si",$_SESSION["username"],$batch_id);
                 $statement->execute();
                 $statement->close();
              }
           }
        }
     }
     return $result;
  }

  /** Find the next file in this batch for the current user without moving to it.
   * 
   * @return a PathFile object containing the next file and it's path, empty if no next file found.
   */
  function getNextFile() { 
     global $connection, $user;
     $result = new PathFile();
     // find the current batch
     $sql = 'select b.path, ub.position from TR_USER_BATCH ub left join TR_BATCH b on ub.tr_batch_id = b.tr_batch_id  where b.tr_batch_id = ? and username = ? and b.completed_date is null order by path limit 1';
     if ($statement = $connection->prepare($sql)) {
        $statement->bind_param("is",$this->batch_id,$_SESSION["username"]);
        $statement->execute();
        $statement->bind_result($path, $position);
        $statement->store_result();
        while ($statement->fetch()) {
            $result->path = $path;
            $result->position = $position;
        }
        $statement->close();
     }
     // find the current file in the batch 
     if (strlen($result->path)>0) {
        $files = scandir(BASE_IMAGE_PATH.$result->path,SCANDIR_SORT_ASCENDING);
        $result->filename = $files[$result->position + 2]; // position + 2 to account for the directory entries . and .. 
        $result->filecount = count($files) - 2;

        // does next file exist: 
        if (array_key_exists($result->position + 2 + 1,$files)) {
           // move to next file in batch
           $result->position = $result->position + 1; // increment position to the next file.
           $result->filename = $files[$result->position + 2];
        }
     }
     return $result;
  }

  /** Find the file at a specified position in this batch without moving to it.
   * 
   * @param position0 zero based position of the file to return.
   * @return a PathFile object containing the file and it's path, empty if no file found at the provided position.
   */
  function getFile($position0) {
     global $connection, $user;
     $result = new PathFile();
     // find the current file in the batch 
     if (strlen($result->path)>0) {
        $files = scandir(BASE_IMAGE_PATH.$this->path,SCANDIR_SORT_ASCENDING);
        $result->filename = $files[$result->position0 + 2]; // position0 + 2 to account for the directory entries . and .. 
        $result->filecount = count($files) - 2;
     }
     return $result;
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
        <div class="hfbox"  id="feedback">Status</div>
	<footer class="hfbox">Database: ' . $targethostdb . '</footer>
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