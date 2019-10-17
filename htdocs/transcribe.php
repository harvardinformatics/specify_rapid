<?php

session_start();

include_once("connection_library.php");
include_once("class_lib.php");
include_once("transcribe_lib.php");
include_once("imagehandler.php");

define("ENABLE_DUPLICATE_FINDING",TRUE);
$GLOBALS['BASEWIDTH']=20; // em for width of form fields

$error = "";
$targethostdb = "Not Set";
if (!function_exists('specify_connect')) {
   $error = 'Error: Database connection function not defined.';
}
@$connection = specify_connect();
if (!$connection) {
   $error =  'Error: No database connection. '. $targethostdb;
}

$display = '';
@$display = substr(preg_replace('/[^a-z]/','',$_GET['display']),0,20);
if ($display=='') {
   @$display = substr(preg_replace('/[^a-z]/','',$_POST['display']),0,20);
}
$tdisplay = $display;
$showDefault = true;

$username='';
$require_authentication = true;
$authenticated = false;
$user = new User();

if ($require_authentication) {
   if(!isset($_SESSION['session_id']) || @$_SESSION['session_id']!=session_id()) {
      $display = "logindialog";
      if (@$_POST['password']!="" && $_POST['username']!="") {
         // authenticate
         $password = substr(preg_replace("/[^A-Za-z0-9\&!#$%*+\/=?^_{|}~\-@.]*/","",$_POST['password']),0,255);
         $username = substr(preg_replace("/[^0-9A-Za-z\.\@\-\_]/","",$_POST['username']),0,255);
         $user = new User($username,$password);
         if ($user->authenticate()){
            $_SESSION["session_id"]=session_id();
            $_SESSION["username"]=$username;
            $_SESSION["agentid"]=$user->getAgentId();
            $_SESSION["fullname"]=$user->getFullName();
            $_SESSION["lastlogin"]=$user->getLastLogin();
            $_SESSION["about"]=$user->getAbout();
            if ($tdisplay == "") {
               $display = "setup";
            } else {
               $display = $tdisplay;
            }
            $user->setTicket($_SERVER['REMOTE_ADDR']);
         } else {
            $error = "Incorrect email address or password.";
         }
      } else {
         $error .= "Login to HUH Specify Data Transcription Form";
      }
   } else {
      $user = new User($_SESSION["username"],'');
      if ($user->validateTicket($_SESSION['user_ticket'],$_SERVER['REMOTE_ADDR'])) {
      	 $authenticated = true;
         $user->setFullname($_SESSION["fullname"]);
         $user->setAgentId($_SESSION["agentid"]);
         $user->setLastLogin($_SESSION["lastlogin"]);
         $user->setAbout($_SESSION["about"]);
      } else {
         // invalid or expired ticket, logout to clear session and go to login form.
         $authenticated = false;
         $display = 'logout';
      }
   }
} else {
   $authenticated = true;
}
if ($display=="") {
   $display="setup";
}

# Data Structures  *********************************

class targetReturn {
   public $barcode;
   public $mediauri;
   public $medialink;
   public $imagesetid;
   public $height;
   public $width;
}



# Supporting functions ******************************

function checkReady() {
    # TODO: ping image window

    # this check probably needs to go into javascript inside mainform.

    return true;
}

function fileCount($dir) {
   $files = scandir($dir,SCANDIR_SORT_ASCENDING);
   $filecount = 0;
   foreach ($files as $file) {
         if (substr($dir,-1,1)=="/") {
             $pathfile = $dir.$file;
         } else {
             $pathfile = $dir.'/'.$file;
         }
         if (is_file($pathfile) && substr($file,0,1)!=".") {
            $filecount++;
         }
   }
   return $filecount;
}
function jpgCount($dir) {
   $files = scandir($dir,SCANDIR_SORT_ASCENDING);
   $filecount = 0;
   foreach ($files as $file) {
         if (substr($dir,-1,1)=="/") {
             $pathfile = $dir.$file;
         } else {
             $pathfile = $dir.'/'.$file;
         }
         if (is_file($pathfile) && substr($file,0,1)!=".") {
            if ( (substr(strtoupper($file), -strlen('JPEG')) === 'JPEG') || (substr(strtoupper($file), -strlen('JPG')) === 'JPG')   ) {
                $filecount++;
            }
         }
   }
   return $filecount;
}
function dirList($dir,$depth) {
   global$connection;
   if ($depth>5) { return; }
   echo "<ul>";
   $files = scandir($dir,SCANDIR_SORT_ASCENDING);
   foreach ($files as $file) {
         if (substr($dir,-1,1)=="/") {
             $pathfile = $dir.$file;
         } else {
             $pathfile = $dir.'/'.$file;
         }
         if (is_dir($pathfile) && substr($file,0,1)!=".") {
            $pathbelowbase = substr($pathfile,strlen(BASE_IMAGE_PATH))."/";
            $sql = "select isnull(completed_date) notdone, completed_date, tr_batch_id from TR_BATCH where path = ? ";
            $stmt = $connection->prepare($sql);
            $stmt->bind_param("s",$pathbelowbase);
            $stmt->execute();
            $stmt->bind_result($notdone,$completed_date,$tr_batch_id);
            $stmt->store_result();
            if ($stmt->fetch()) {
                $exists = true;
            }
            if ($stmt->num_rows()==0) {
                $exists = false;
            }
            $filecount = jpgCount($pathfile);
            echo "<li>$file ($filecount jpegs) ";
            if ($exists===false) {
                if ($filecount>0) {
                   echo " <input type='button' class='processButton ui-button' type='button' onclick=' processBatch(\"$pathfile\"); ' value='Process' />";
                }
            } else {
                if ($notdone==1) { echo " Batch prepared for transcription. "; } else { echo " completed $completed_date "; }
            }
            echo "</li>";
            $stmt->free_result();
            $stmt->close();
            dirList("$pathfile/",$depth+1);
         }
   }
   echo "</ul>";
}

function doPrepareBatch() {
   global $connection;
   echo "<script type='text/javascript'>
         function processBatch(targetdir){
              $('#batchprogress').html('processing: '+targetdir);
              $('.processButton').attr('disabled', true).addClass('ui-state-disabled');
              $.ajax({
                  type: 'POST',
                  url: 'transcribe_handler.php',
                  data: {
                       action: 'processbatch',
                       directory: targetdir,
                  },
                  dataType: 'json',
                  success: function(data) {
                      if (data.error=='') {
                          $('#batchprogress').html('processed '+targetdir + ' ' + data.result);
                      } else {
                          $('#batchprogress').html('Error processing '+targetdir + ' ' + data.error);
                      }
                      console.log( data );
                      $('.processButton').attr('disabled', false).removeClass('ui-state-disabled');
                  },
                  error: function() {
                      $('#batchprogress').html('Error requesting processing for: '+targetdir);
                      console.log( 'processBatch Failed.  Ajax Error.');
                      $('.processButton').attr('disabled', false).removeClass('ui-state-disabled');
                  }

               });
            };
        </script>";
   echo "<div style='margin: .2em; ' ><form action='transcribe.php'><input type='submit' class='processButton ui-button' value='Return to Main Menu'></form></div>";
   echo "<div id='batchprogress' style='margin: .2em;'></div>";
   echo "<div id='directorytree'>";
   // TODO: Setup for production
   // dirList(BASE_IMAGE_PATH."/",1);
   dirList(BASE_IMAGE_PATH.BATCHPATH,1);  // for testing
   echo "</div>";
}

function doSetup() {
   global $connection;

   @$targetBatchDir = $_GET['targetBatch'];

   if($targetBatchDir=="") {
      $targetBatch = getNextBatch();
   } else {
      $targetBatch = getBatch($targetBatchDir);
   }

   $batchPath = $targetBatch->getPath();
   $targetBatchCurrent = $targetBatch->getCurrentFile();
   $targetBatchFirst = $targetBatch->getFile(1);
   $position = $targetBatchCurrent->position;
   echo "<div style='padding-left: 0.5em;'><h2 style='margin: 0.2em;'>Transcribe data from Images into Specify-HUH</h2></div>";
   echo "<div style='padding: 0.5em;' id='setupBatchControls'>";
   if ($batchPath==null || strlen($batchPath)==0) {
     echo " <strong>No Current Batch.</strong>";
   } else {
      echo " <strong>Batch: [$batchPath]</strong>";
      echo "<button type='button' onclick=' $(\"#cover\").fadeIn(100); dosetuppath(\"".urlencode($batchPath)."\",\"".urlencode($targetBatchFirst->path)."\",\"".urlencode($targetBatchFirst->filename)."\",\"1\",\"standard\");' class='ui-button'>Start from first.</button>";
      if ($position > 1) {
        echo "<button type='button' onclick=' $(\"#cover\").fadeIn(100); dosetuppath(\"".urlencode($batchPath)."\",\"".urlencode($targetBatchCurrent->path)."\",\"".urlencode($targetBatchCurrent->filename)."\",\"$targetBatchCurrent->position\",\"standard\");' class='ui-button ui' >Start from $position</button>";
      }
      //echo " First File: [$targetBatchFirst->filename]";
   }
   echo "</div>";
//   echo "<form method='GET' action='transcribe.php'><div id='pickbatch' style='padding: 0.5em;' >";
   echo "<div id='pickbatch' style='padding: 0.5em;' >";
   echo " <strong>Work on another batch:</strong>";
   echo "<select id='targetBatch' name='targetBatch'>";
   $sql = "select trb.path, IF(trb.completed_date is not null, 1, 0) as completed, group_concat(trub.username separator ',') as users from TR_BATCH trb left join TR_USER_BATCH trub on trb.tr_batch_id = trub.tr_batch_id and trub.position > 1 group by trb.tr_batch_id order by completed, path asc";
   if ($statement = $connection->prepare($sql)) {
       $statement->execute();
       $statement->bind_result($path,$completed,$users);
       $statement->store_result();
       $selected = "";
       while ($statement->fetch()) {
          if($completed==0) {
             $complete = "";
          } else {
             $complete="Done: ";
          }
          if (strlen($users)>0) {
            $usersworking = " ($users)";
          } else {
            $usersworking = "";
          }
          $upath = urlencode($path);

          if ($path==$batchPath) {
            $selected = "selected";
          } else {
            $selected = "";
          }

          echo "<option value='$path' $selected >$complete$path$usersworking</option>";
       }
       $statement->free_result();
       $statement->close();
   } else {
       echo "Error preparing query: ".$connection->error;
   }

   echo "</select></div>";
//   echo "</select><button type='submit' value='Pick Batch' class='ui-button'>Pick Batch</button></div></form>";
   echo "<script type='text/javascript'>
      $('#targetBatch').on('change', function() {
           $.ajax({
                  type: 'POST',
                  url: 'transcribe_handler.php',
                  data: {
                       action: 'updatedosetup',
                       targetBatch: $('#targetBatch').find(\"option:selected\").val()
                  },
                  success: function(data) {
                      $('#setupBatchControls').html(data);
                  },
                  error: function() {
                      console.log( 'updatedosetup Failed.  Ajax Error.');
                  }
           });
      });
   </script>";
//    echo "<div id='pickbatch' style='padding: 0.5em;' >";
//    echo "<form method='GET' action='transcribe.php?display=preparebatch' >";
//    echo "<input type='hidden' id='display' name='display' value='preparebatch'>";
//    echo "<button type='submit' value='Pick Batch' class='ui-button'>Prepare New Batch</button>";
//    echo "</form></div>";
//    echo "<div>";
//    if (!defined("BASE_IMAGE_PATH")) {
//        echo "<h3>Error: BASE_IMAGE_PATH must be defined in connection_library.php</h3>";
//    }
//    if (!defined("BATCHPATH")) {
//        echo "<h3>Error: BATCHPATH must be defined in connection_library.php</h3>";
//    }
//    if (!defined("PHPINCPATH")) {
//        echo "<h3>Error: PHPINCPATH must be defined in connection_library.php</h3>";
//    }
//    echo "<h3>Some design Assumptions of this application:</h3><ul>";
//    echo "<li>Data is being transcribed into database fields off of labels in herbarium sheet images.</li>";
//    echo "<li>Users have dual monitors and are using Firefox.</li>";
//    echo "<li>Barcodes identify items, barcoded preparations are not supported.</li>";
//    echo "<li>Database records for specimens and images may or may not exist prior to transcription.</li>";
//    echo "<li>Not all images are of herbarium sheets (some are of covers)</li>";
//    echo "<li>When image files are sorted in by their filename, this sort produces the correct sequence of images for transcription.</li>";
//    echo "<li>One directory contains one batch of work, and can be pre-processed to check image files for barcodes.</li>";
//    echo "<li>Saving a record saves both specimen and image records into the database.</li>";
//    echo "<li>Taxon and higher geography records exist or will be entered by users through Specify-HUH.</li>";
//    echo "<li>Saving identifications when identifications already exist in the database will add new identifications unless the existing identifications appear to have been entered through this application.</li>";
//    echo "</ul>";
//    echo "<h3>The Barcode field has unexpected properties.</h3><ul>";
//    echo "<li>When a barcode is known for an image, the barcode field will be disabled.</li>";
//    echo "<li>When no barcode is known for an image, the barcode field will be enabled and empty.</li>";
//    echo "<li>When the enabled barcode field loses focus, a search is run on the database for that barcode and all non-carry forward fields will have their data replace by data from the database, while all non-empty carry forward fields will be populated with data retrieved from the database.</li>";
//    echo "<li>When multiple barcodes are present in an image, you can save one record, click the delta button next to the barcode field to make it editable, and enter another barcode that is present on the sheet, along with its data.</li>";
//    echo "</ul></div>";
}

function target() {
   global $connection;
   $result = new targetReturn();
   $sql = "select distinct f.text1, f.identifier
         from locality l left join collectingevent ce on l.localityid = ce.localityid
                         left join collectionobject co on ce.collectingeventid = co.collectingeventid
                         left join fragment f on co.collectionobjectid = f.collectionobjectid
                         left join IMAGE_SET_collectionobject isco on co.collectionobjectid = isco.collectionobjectid
                         left join IMAGE_OBJECT io on isco.imagesetid = io.image_set_id
          where localityname = '[data not captured]'
                and isco.collectionobjectid is not null
                and object_type_id = 4 and hidden_flag = 0 and active_flag = 1
          limit ? ";
   $limit = 1;
   if ($statement = $connection->prepare($sql)) {
       $statement->bind_param("i", $limit);
       $statement->execute();
       $statement->bind_result($acronym, $barcode);
       $statement->store_result();
       while ($statement->fetch()) {
         $result->barcode = $barcode;
         $media = imageDataForBarcode($barcode);
         $mediauri = $media->url;
         $mediaid = $media->image_set_id;
         $height = $media->pixel_height;
         $width = $media->pixel_width;
         if ($height==0||$height==null) {
            $size = getImageSize($mediauri);
            $width = $size[0];
            $height = $size[1];
         }
         $result->mediauri = $mediauri;
         //$mediauri = 'http://nrs.harvard.edu/urn-3:FMUS.HUH:s16-47087-301139-3';
         //$height =  4897;
         //$width  =  3420;
         $s = 400/$height; // scale factor
         $h = round($height*$s);
         $w = round($width*$s);
         $medialink = "";
         $medialink .= "<a channel.postMessage(\"$barcode\"); '>$acronym $barcode</a>&nbsp; ";
         $medialink .= "<img id='image_div' onclick=' getClick(event,$h,$w,$height,$width,$mediaid);' src='$mediauri' width='$w' height='$h'></div>";
         $result->medialink = $medialink;
       }
       $statement->close();
   }
   return $result;
}

function targetfile($path,$filename) {
   global $connection;
   $result = new targetReturn();

   $acronym = 'A';
   $barcode = '999999999';
   $result->barcode = $barcode;
   $mediaid = '';
   if (substr($path,-1)=="/") {
       $localpathfile = "$path$filename";
   } else {
       $localpathfile = "$path/$filename";
   }
   $pathfile = BASE_IMAGE_PATH.'/'.$localpathfile;
   list($width,$height) = getimagesize($pathfile);
   $result->height=$height;
   $result->width=$width;

   $mediauri = BASE_IMAGE_URI.$localpathfile;
   $result->mediauri = $mediauri;

   //$height = 5616;
   //$width = 3744;
   $s = 400/$height; // scale factor
   $h = round($height*$s);
   $w = round($width*$s);

   // $medialink = "<a channel.postMessage(\"$barcode\"); '>$acronym $barcode</a>&nbsp; ";
   $medialink = "<img id='image_div' onclick=' getClick(event,$h,$w,$height,$width,$mediaid);' src='$mediauri' width='$w' height='$h'></div>";
   $result->medialink = $medialink;

   //$barcode = ImageHandler::checkFilenameForBarcode($path,$filename,false);
   //$result->barcode = $barcode;

   // TODO: Lookup barcode image height, image width from path and filename.
   /*
   $sql = "select distinct f.text1, f.identifier
         from locality l left join collectingevent ce on l.localityid = ce.localityid
                         left join collectionobject co on ce.collectingeventid = co.collectingeventid
                         left join fragment f on co.collectionobjectid = f.collectionobjectid
                         left join IMAGE_SET_collectionobject isco on co.collectionobjectid = isco.collectionobjectid
                         left join IMAGE_OBJECT io on isco.imagesetid = io.image_set_id
          where localityname = '[data not captured]'
                and isco.collectionobjectid is not null
                and object_type_id = 4 and hidden_flag = 0 and active_flag = 1
          limit ? ";
   $limit = 1;
   if ($statement = $connection->prepare($sql)) {
       $statement->bind_param("i", $limit);
       $statement->execute();
       $statement->bind_result($acronym, $barcode);
       $statement->store_result();
       while ($statement->fetch()) {
         $result->barcode = $barcode;
         $media = imageDataForBarcode($barcode);
echo "[$media->image_set_id]";
         $mediauri = $media->url;
         $mediaid = $media->image_set_id;
         $height = $media->pixel_height;
         $width = $media->pixel_width;
         if ($height==0||$height==null) {
            $size = getImageSize($mediauri);
            $width = $size[0];
            $height = $size[1];
         }
         $result->mediauri = $mediauri;
         //$mediauri = 'http://nrs.harvard.edu/urn-3:FMUS.HUH:s16-47087-301139-3';
         //$height =  4897;
         //$width  =  3420;
         $s = 200/$height; // scale factor
         $h = round($height*$s);
         $w = round($width*$s);
         $medialink = "";
         $medialink .= "<a channel.postMessage(\"$barcode\"); '>$acronym $barcode</a>&nbsp; ";
         $medialink .= "<img id='image_div' onclick=' getClick(event,$h,$w,$height,$width,$mediaid);' src='$mediauri' width='$w' height='$h'></div>";
         $result->medialink = $medialink;
       }
       $statement->close();
   }
   */
   return $result;
}
function imageForBarcode($barcode) {
   global $connection;
   $result = "";
   $sql = " select concat(url_prefix,uri) as url
            from IMAGE_OBJECT io left join REPOSITORY on io.repository_id = REPOSITORY.id
            left join IMAGE_SET_collectionobject isco on io.image_set_id = isco.imagesetid
            left join fragment f on f.collectionobjectid = isco.collectionobjectid
            where identifier = ? and object_type_id = 3 and hidden_flag = 0 and active_flag = 1
            limit 1 ";
   if ($statement = $connection->prepare($sql)) {
       $statement->bind_param("s", $barcode);
       $statement->execute();
       $statement->bind_result($url);
       if ($statement->fetch()) {
         $result .= $url;
       } else {
          echo $connection->error;
       }
       $statement->close();
   } else {
          echo $connection->error;
   }
   return $result;
}

function imageDataForBarcode($barcode) {
   global $connection;
   $result = new ImageReturn();
   $sql = " select image_set_id, concat(url_prefix,uri) as url, pixel_height, pixel_width
            from IMAGE_OBJECT io left join REPOSITORY on io.repository_id = REPOSITORY.id
            left join IMAGE_SET_collectionobject isco on io.image_set_id = isco.imagesetid
            left join fragment f on f.collectionobjectid = isco.collectionobjectid
            where identifier = ? and object_type_id = 3 and hidden_flag = 0 and active_flag = 1
            limit 1 ";
   if ($statement = $connection->prepare($sql)) {
       $statement->bind_param("s", $barcode);
       $statement->execute();
       $statement->bind_result($image_set_id, $url, $pixel_height, $pixel_width);
       if ($statement->fetch()) {
         $result->image_set_id = $image_set_id;
         $result->url = $url;
         $result->pixel_height = $pixel_height;
         $result->pixel_width = $pixel_width;
       } else {
          echo $connection->error;
       }
       $statement->close();
   } else {
          echo $connection->error;
   }
   return $result;
}



# Show the page with the selected display mode.

$apage = new TPage();
$apage->setTitle("HUH Data Transcription Form");
$apage->setErrorMessage($error);

echo $apage->getHeader($user);

// Check to see if conditions are setup for displaying the main data entry form, if not, go to setup.
if ($display=='mainform') {
   if (!checkReady()) {
       $display = "setup";
   }
}

switch ($display) {

   case 'mainform':
      form();
      break;
   case 'preparebatch':
      doPrepareBatch();
      break;
   case 'setup':
      doSetup();
      break;
   case 'logout':
      $user->logout();
   case "logindialog":
   default:
      $email = $username;
      if (@$_GET['email']!="") {
         $username = substr(preg_replace("/[^0-9A-Za-z\.\@\-\_]/","",$_GET['email']),0,255);
      }
      $title = "Specify Login";
      echo '<div class="flexbox">';
      echo "
               <form method=POST action='transcribe.php' name=loginform id=loginform>
               Specify username:<input type=textbox name=username value='$username'>
               Password:<input type=password name=password>
               <input type=hidden name=action value='getpassword'>
               <input type=hidden name=display value='setup'>
               <input type=submit value='Login' class='ui-button'>
               </form>";
      echo "<script type='text/javascript'>
               function get_password(){
                  $('#pwresult').innerHTML = 'Checking'
                  var pwgetter = {
                     url: 'ajax_handler.php',
                     handleAs: 'text',
                     load: function(response, ioArgs){
                       dojo.byId('pwresult').innerHTML = response;
                     },
                     error: function(data){
                        dojo.byId('pwresult').innerHTML='Error:'+data;
                     },
                     timeout: 4000,
                     form: 'loginform'
                  }
                  dojo.xhrGet(pwgetter);
               }
               </script>";
      echo "</div>";
      break;
}

echo $apage->getFooter($user);


// ********************************************************************************
// ****  Supporting functions  ****************************************************
// ********************************************************************************

// ** Work in progress

function targetlist() {
   global $connection;

   $result = "";
   $sql = "select distinct f.text1, f.identifier from locality l left join collectingevent ce on l.localityid = ce.localityid left join collectionobject co on ce.collectingeventid = co.collectingeventid left join fragment f on co.collectionobjectid = f.collectionobjectid left join IMAGE_SET_collectionobject isco on co.collectionobjectid = isco.collectionobjectid  where localityname = '[data not captured]' and isco.collectionobjectid is not null limit ? ";
   $limit = 10;
   if ($statement = $connection->prepare($sql)) {
       $statement->bind_param("i", $limit);
       $statement->execute();
       $statement->bind_result($acronym, $barcode);
       while ($statement->fetch()) {
         $result .= "<a href='transcribe.php?barcode=$barcode'>$acronym $barcode</a>&nbsp; ";
       }
       $statement->close();
   }
   return $result;
}
/*
function imageForBarcode($barcode) {
   global $connection;
   $result = "";
   $sql = " select concat(url_prefix,uri)
            from IMAGE_OBJECT io left join REPOSITORY on io.repository_id = REPOSITORY.id
            left join IMAGE_SET_collectionobject isco on io.image_set_id = isco.imagesetid
            left join fragment f on f.collectionobjectid = isco.collectionobjectid
            where identifier = ? and object_type_id = 4 and hidden_flag = 0 and active_flag = 1
            limit 1 ";
   if ($statement = $connection->prepare($sql)) {
       $statement->bind_param("s", $barcode);
       $statement->execute();
       $statement->bind_result($url);
       if ($statement->fetch()) {
         $result .= $url;
       }
       $statement->close();
   }
   return $result;
}
*/

// ** Functions

function navigation() {
    echo "<button type='button' onclick='$(\"#cover\").fadeIn(100);   doclear();' class='ui-button' >Restart</button>";
    echo "<button type='button' onclick='ping();' class='ui-button' >Ping</button>";
}


function form() {
   global $user;

/* Supported field list:

barcode
created
herbarium
format
prepmethod
project
[geographyfilter]
[geographyfilterid]
highergeography
highergeographyid
filedundername
filedundernameid
filedunderqualifier
currentname
currentnameid
currentqualifier
collectingtrip
collectors
etal
specificlocality
stationfieldnumber
verbatimdate
datecollected
namedplace
verbatimelevation
habitat

*/

   @$config = substr(preg_replace('/[^a-z]/','',$_GET['config']),0,10);
   @$filename = preg_replace('/[^-a-zA-Z0-9._]/','',urldecode($_GET['filename']));
   @$batchpath = urldecode($_GET['batch']);
   $position = 1;
   @$position= preg_replace('/[^0-9]/','',$_GET['position']);
   if ($position==null || $position=="") { $position = 1;  }

   switch ($config) {
      case 'minimal':
          $config="minimal";
          break;
      case 'standard':
      default :
          $config="standard";
   }


   $currentBatch = new TR_Batch();
   $currentBatch->setPath($batchpath);
   $filecount = $currentBatch->getFileCount();
   $currentBatch->moveTo($position);
   $file = $currentBatch->getFile($position);
   $filepath = $file->path;
   $filename = $file->filename;

   $currentBatch->selectOrCreateUserForBatch();

   # Find out the barcode to load.
   if (strlen($filename)==0) {
      $target = target();
   } else {
      $target = targetfile($filepath,$filename);
   }
   $targetbarcode = $file->barcode;
   $targetheight = $target->height;
   $targetwidth = $target->width;
   echo "
   <script>
        function logEvent(eventaction,eventdetails){
              if(eventdetails=='') { eventdetails = 'event'; }
              $.ajax({
                  type: 'POST',
                  url: 'transcribe_logger.php',
                  data: {
                       action: eventaction,
                       username: '".$_SESSION["username"]."',
                       batch_id: ".$currentBatch->getBatchID().",
                       details: eventdetails
                  },
                  success: function(data) {
                      console.log( data );
                  },
                  error: function() {
                      console.log( 'logEvent Failed.  Ajax Error.');
                  }
              });
         };

      $(document).ready(logEvent('start_transcription','$filepath, $filename, $position'));
   </script>";


   $habitat = "";

   $huh_fragment = new huh_fragment();
   $matches = $huh_fragment->loadArrayByIdentifier($targetbarcode);
   $num_matches = count($matches);
   $project = null;
   if ($num_matches==1) {
       $match = $matches[0];
       $match->load($match->getFragmentID());
       $prepmethod = $match->getPrepMethod();

       $provenance = $match->getProvenance();
       $created = $match->getTimestampCreated();

       // get filedundername, currentname, filedunderqualifier, currentqualifier
       $filedunder = huh_determination_custom::lookupFiledUnderDetermination($match->getFragmentID());
       $determinationid = $filedunder["determinationid"];
       $filedundername = $filedunder["taxonname"];
       $filedunderalternatename = $filedunder["alternatename"];
       $filedundernameid = $filedunder["taxonid"];
       // check if determination is there with no taxon
       if (strlen(trim($determinationid)) > 0 && ($filedundernameid==null || trim($filedundernameid)=='')) {
         $filedundernameid = -1;

         if (strlen(trim($filedunderalternatename))>0) {
           $filedundername="[Alt name: $filedunderalternatename]";
         } else {
           $filedundername="[Det has no taxon]";
         }
       }
       $filedunderqualifier = $filedunder["qualifier"];

       $current = huh_determination_custom::lookupCurrentDetermination($match->getFragmentID());
       $determinationid = $current["determinationid"];
       $currentname = $current["taxonname"];
       $currentalternatename = $current["alternatename"];
       $currentnameid = $current["taxonid"];
       if (strlen(trim($determinationid)) > 0 && ($currentnameid==null || trim($currentnameid)=='')) {
         $currentnameid = -1;

         if (strlen(trim($filedunderalternatename))>0) {
           $currentname="[Alt name: $currentalternatename]";
         } else {
           $currentname="[Det has no taxon]";
         }
       }

       $currentqualifier = $current["qualifier"];
       $identifiedbyid = $current["determinerid"];
       $identifiedby = huh_collector_custom::getCollectorVariantName($identifiedbyid);
       $dateidentified = dateBitsToString($current["determineddate"], $current["determineddateprecision"], null, null);
       $determinertext = $current["determinertext"];

       $related = $match->loadLinkedTo();
       $rcolobj = $related['CollectionObjectID'];
       $specimendescription = $rcolobj->getDescription();
       $specimenremarks = $rcolobj->getRemarks();
       $frequency = $rcolobj->getText4();
       $rprep = $related['PreparationID'];
       //$rcolobj->load($rcolobj->getCollectionObjectID()); // already loaded
       //$rprep->load($rprep->getPreparationID()); // already loaded
       $formatid = $rprep->getPrepTypeID();
       $related = $rprep->loadLinkedTo();
       $rpreptype = $related['PrepTypeID'];
       //$rpreptype->load($rpreptype->getPrepTypeID()); // already loaded
       $format = $rpreptype->getName();
       $related = $rcolobj->loadLinkedTo();
       $rcontainer = $related['ContainerID'];
       $container = $rcontainer->getName();
       $containerid = $rcontainer->getContainerID();
       $proj = new huh_project_custom();
       //$project = $proj->getFirstProjectForCollectionObject($rcolobj->getCollectionObjectID());
       $rcoleve = $related['CollectingEventID'];
       //$rcoleve->load($rcoleve->getCollectingEventID()); // already loaded
       $stationfieldnumber = $rcoleve->getStationFieldNumber();
       $datecollected = dateBitsToString($rcoleve->getStartDate(), $rcoleve->getStartDatePrecision(), $rcoleve->getEndDate(), $rcoleve->getEndDatePrecision());
       $verbatimdate = $rcoleve->getVerbatimDate();
       $habitat = $rcoleve->getRemarks();
       $related = $rcoleve->loadLinkedTo();
       $rcollector = $rcoleve->loadLinkedFromcollector();
       $collectoragentid = $rcollector->getAgentID();
       $collectors = huh_collector_custom::getCollectorVariantName($collectoragentid);
       $etal = $rcollector->getEtAl();
       $rlocality = $related['LocalityID'];
       $rcollectingtrip = $related['CollectingTripID'];
       $collectingtrip = $rcollectingtrip->getCollectingTripName();
       $collectingtripid = $rcollectingtrip->getCollectingTripID();
       //$rlocality->load($rlocality->getLocalityID()); // already loaded
       $namedPlace = $rlocality->getNamedPlace();
       $verbatimElevation = $rlocality->getVerbatimElevation();
       $specificLocality = $rlocality->getLocalityName();
       $related = $rlocality->loadLinkedTo();
       $rgeography = $related['GeographyID'];
       //$rgeography->load($rgeography->getGeographyID()); // already loaded
       $geographyid = $rgeography->getGeographyID();
       $geography = $rgeography->getFullName();

       $verbatimlat = $rlocality->getLat1Text();
       $verbatimlong = $rlocality->getLong1Text();
       $decimallat = $rlocality->getLatitude1();
       $decimallong = $rlocality->getLongitude1();
       $coordinateuncertainty = $rlocality->getLatLongAccuracy();
       $georeferencesource = $rlocality->getLatLongMethod();

   } elseif ($num_matches==0) {
       // set defaults to create new record
       $prepmethod = "Pressed";
       $defaultformat = "Sheet";
       $created = "New Record";
   } else {
       // error condition, found more than one fragment by the barcode.
       $created = "Problem: Found $num_matches item records, enter through Specify-HUH";
   }

   @$cleardefaultgeography = substr(preg_replace('/[^01]/','',$_GET['cleardefaultgeography']),0,2);
   if ($cleardefaultgeography==1) {
       $defaultcountry = "";
       $defaultprimary = "";
       $geographyfilter= "";
       $geographyfilterid= "";
   } else {
        @$defaultcountry = substr(preg_replace('/[^A-Za-z[:alpha:] ]/','',$_GET['defaultcountry']),0,255);
        @$defaultprimary = substr(preg_replace('/[^A-Za-z[:alpha:] ]/','',$_GET['defaultprimary']),0,255);
        @$geographyfilter= $_GET['geographyfilter'];
        @$geographyfilterid= $_GET['geographyfilter'];
   }
   @$defaultherbarium = substr(preg_replace('/[^A-Z]/','',$_GET['defaultherbarium']),0,5);
   if ($defaultherbarium=='') { $herbarium = "GH"; }
   if ($num_matches==1) {
       $herbarium = $match->getText1();
   }
   @$defaultformat = substr(preg_replace('/[^A-Za-z ]/','',$_GET['defaultformat']),0,255);
   if ($defaultformat=='') { $defaultformat = "Sheet"; }
   @$defaultprepmethod = substr(preg_replace('/[^A-Za-z ]/','',$_GET['defaultprepmethod']),0,255);
   if ($defaultprepmethod=='') { $defaultprepmethod = "Pressed"; }
   @$defaultproject = substr(preg_replace('/[^0-9A-Za-z\. \-]/','',$_GET['defaultproject']),0,255);
   if ($defaultproject==null || strlen($defaultproject)==0 ) { $defaultproject = 'US and Canada - Mass Digitization'; }
   if ($project==null || strlen($project)==0) { $project = $defaultproject; }

   echo "<div class='hfbox' style='height: 1em;'>";
   echo navigation();
   echo "&nbsp;<span id='batch_info'>Starting batch $batchpath with $filecount files.  [$targetbarcode]</span>&nbsp;[<span id='current_position'>$position</span>]";
   echo "</div>";
   echo "</div>";

   echo "<div class='flex-main hfbox' style='min-width: 1000px; padding: 0em;'>";
   echo "<form action='transcribe_handler.php' method='POST' id='transcribeForm' autocomplete='off' >\n";

   echo "<input type=hidden name='action' value='transcribe' class='carryforward'>";
   echo "<input type=hidden name='operator' value='".$user->getAgentId()."' class='carryforward'>";
   echo '<script>
         $( function(){
            $("#transcribediv").accordion( { heightStyle: "fill" } ) ;
          });
   </script>';
   echo '<div style="display: inline-block; float: left; margin-right: 5px;" id="leftside">';
   echo '<div style="width: 30em;" id="transcribediv" >';
   echo '<h3 style=" margin-top: 1px; margin-bottom: 0px;">Transcribe into Fields</h3>';
   echo '<div>';

   echo "<table>";

   echo '<script>
     $(document).ready(function() {
          //channel.postMessage( { x:"355", y:"569", oh:"'.$target->height.'", ow:"'.$target->width.'", h:"700", w:"467", id:"'.$target->imagesetid.'" }  );
          channel.postMessage( { x:"353", y:"614", oh:"'.$target->height.'", ow:"'.$target->width.'", h:"700", w:"467", id:"'.$target->imagesetid.'" }  );
     });
   </script>
   ';

   //if ($test=="true") {
      //@staticvalue("Project",$defaultproject);
      //echo "<input type='hidden' name='project' id='project' value='$defaultproject' class='carryforward'>";
   //} else {

   if (strlen($targetbarcode==0)) {
       $enabled = 'true';
   } else {
       $enabled = 'false';
   }
   fieldEnabalable("barcode","Barcode",$targetbarcode,'required','[0-9]{8}','','Barcode must be an 8 digit number.',$enabled);   // not zero padded when coming off barcode scanner.
   echo "<input type='hidden' name='barcodeval' id='barcodeval' value='$targetbarcode'>"; // to carry submission of barcode with disabled barcode input.
   // TODO: on loss of focus, check database for record and reload data.
   // ******************
   echo '<script>
   $(function () {
       $("#barcode").on( "invalid", function () {
           this.setCustomValidity("Barcode must be a 1 to 8 digit number.");
       });
       $("#barcode").on( "input", function () {
           this.setCustomValidity("");
       });
       $("#barcode").on( "blur", function () {
           loadDataForBarcode($("#barcode").val());
       });
   });
   </script>
   ';

   if ($config=="minimal") {
       /*
       barcode - known, not editable - on save, go to next in list.
       project - default US and Canada, show
       format - default sheet, show
       preparation method - pressed default, show
       collectioncode - likely (GH, A, NEBC)
       highergeography - pick
       scientific name - filed under, plus qualifier - carry forward
       */
       @selectAcronym("herbariumacronym",$herbarium);
       @selectTaxon("filedundername","Filed Under",$filedundername,$filedundernameid,'true','true');
       @selectHigherGeography ("highergeography","Higher Geography",$geography,$geographyid,'','','true');
       @field ("datecollected","Date Collected",$datecollected,'false','([0-9]{4}(-[0-9]{2}){0,2}){1}(/([0-9]{4}(-[0-9]{2}){0,2}){1}){0,1}','','Use of an ISO format is required: yyyy, yyyy-mm, yyyy-mm-dd, or yyyy-mm-dd/yyyy-mm-dd','true');
       @field ("verbatimdate","Verbatim Date",$verbatimdate,'false');
        // Verbatim date auto-parsing disabled for now, not deemed useful to workflow and some edge cases would need to be resolved
        // echo "
        // <script>
        //    $('#verbatimdate').blur(function() {
        //       if (!$(this).val().trim()) {
        //         $('#datecollected').val('');
        //       } else {
        //
        //        $('#datecollected').prop('disabled', true);
        //        var verbatim = $('#verbatimdate').val();
        //        $.ajax({
        //            type: 'GET',
        //            url: 'transcribe_handler.php',
        //            data: {
        //                action: 'interpretdate',
        //                verbatimdate: verbatim
        //            },
        //            success: function(data) {
        //                if (data!='') {
        //                  $('#datecollected').val(data);
        //                  $('#datecollected').prop('disabled', field);
        //                }
        //            }
        //        });
        //
        //      }
        //    });
        // </script>
        // ";
        @selectProject("project","Project",$defaultproject);
   } else { //if ($config=="standard") {

       @selectAcronym("herbariumacronym",$herbarium);
       @selectTaxon("filedundername","Filed Under",$filedundername,$filedundernameid,'true','true');
       @selectTaxon ("currentname","Current Name",$currentname,$currentnameid,'true','true');
       @selectQualifier("currentqualifier","ID Qualifier",$currentqualifier);
       @selectCollectorsID("identifiedby","Identified By",$identifiedby,$identifiedbyid,'false','false');
       @field ("dateidentified","Date Identified",$dateidentified,'false','([0-9]{4}(-[0-9]{2}){0,2}){1}(/([0-9]{4}(-[0-9]{2}){0,2}){1}){0,1}','','Use of an ISO format is required: yyyy, yyyy-mm, yyyy-mm-dd, or yyyy-mm-dd/yyyy-mm-dd');
       @field ("determinertext","Det. Text",$determinertext,'false');
       @field ("provenance","Provenance",$provenance,'false');
       @selectCollectorsID("collectors","Collectors",$collectors,$collectoragentid,'true','false');
       @field ("etal","Et al.",$etal,'false');
       @field ("stationfieldnumber","Collector Number",$stationfieldnumber,'false');
       @field ("datecollected","Date Collected",$datecollected,'false','([0-9]{4}(-[0-9]{2}){0,2}){1}(/([0-9]{4}(-[0-9]{2}){0,2}){1}){0,1}','','Use of an ISO format is required: yyyy, yyyy-mm, yyyy-mm-dd, or yyyy-mm-dd/yyyy-mm-dd','true');
       @field ("verbatimdate","Verbatim Date",$verbatimdate,'false');
       @selectContainerID("container","Container",$container,$containerid);
       @selectCollectingTripID("collectingtrip","Collecting Trip",$collectingtrip,$collectingtripid,'false');
       @selectHigherGeography ("geographyfilter","Geography Within",$geographyfilter,$geographyfilterid,'','','false','true');
       @selectHigherGeographyFiltered ("highergeography","Higher Geography",$geography,$geographyid,'','','true');

       @field ("specificlocality","Verbatim locality",$specificLocality,'true');
       @field ("habitat","Habitat",$habitat);
       @field ("frequency", "Frequency", $frequency);

       @field ("specimendescription","Description",$specimendescription,'false');
       @field ("specimenremarks","Remarks",$specimenremarks,'false');
       @selectProject("project","Project",$defaultproject);

   }

   echo "<tr><td colspan=2>";
   echo "<input type='hidden' name='batch_id' value='".$currentBatch->getBatchID()."' class='carryforward'>";
   echo "<input type='button' value='Save' id='saveButton' class='carryforward ui-button'> ";
   echo "<input type='button' value='Next', id='nextButton' class='carryforward ui-button'>";
   echo "<input type='button' value='Done', disabled='true' id='doneButton' class='carryforward ui-button ui-state-disabled'>";
   echo "<input type='button' value='Previous', id='previousButton'  disabled='true' class='carryforward ui-button'>";
   echo "</td></tr>";
   //echo "<tr><td colspan=2 style=' font-size: 0.9em;'>";
   //echo "For the autocomplete fields, quickly type a substring (wildcards allowed, e.g. <i>Su%Gray</i>), press the down arrow to make a selection from the picklist, hit enter, then tab out of the field..";
   //echo "Once you have entered a specimen record you must hit Save to save the record before hitting Next or Done.";
   //echo "</td></tr>";

   echo "<script>
        var re_barcode = /^[0-9]{8}$/;

         $('#nextButton').click(function(event){
               $('#feedback').html( 'Loading next...');
               logEvent('next_button_click',$('#batch_info').html());
               // clear fields
               $('#transcribeForm  input:not(.carryforward)').val('');

               // load next image
               $.ajax({
                   type: 'GET',
                   url: 'transcribe_handler.php',
                   dataType: 'json',
                   data: {
                       action: 'getnextimage',
                       batch_id: ".$currentBatch->getBatchID()."
                   },
                   success: function(data) {
                     console.log(data);
                     $('#image_div').attr('src',data.src);
                     var imagesource = data.src;
                     var imagepath = data.path;
                     var imagefilename = data.filename;
                     var position = data.position;
                     $('#current_position').html(data.position);
                     var filecount = data.filecount;
                     channel.postMessage(  { action:'load', origheight:'$targetheight', origwidth:'$targetwidth', uri: imagesource, path: imagepath, filename: imagefilename }  );
                     $('#batch_info').html('".$currentBatch->getPath()." file ' + position +' of $filecount.');

                     // load data for this record.
                     loadNextData(position,".$currentBatch->getBatchID().");
                   },
                   error: function() {
                       $('#feedback').html( 'Failed.  Ajax Error.  Barcode: ' + ($('#barcode').val()) ) ;
                       //$('#nextButton').prop('disabled',true)
                   }
               });
               event.preventDefault();
          });


         $('#previousButton').click(function(event){

               $('#feedback').html( 'Loading next...');
               logEvent('previous_button_click',$('#batch_info').html());
               // clear fields
               $('#transcribeForm  input:not(.carryforward)').val('');

               // load next image
               $.ajax({
                   type: 'GET',
                   url: 'transcribe_handler.php',
                   dataType: 'json',
                   data: {
                       action: 'getpreviousimage',
                       batch_id: ".$currentBatch->getBatchID()."
                   },
                   success: function(data) {
                     console.log(data.src);
                     $('#image_div').attr('src',data.src);
                     var imagesource = data.src;
                     var imagepath = data.path;
                     var imagefilename = data.filename;
                     var position = data.position;
                     $('#current_position').html(data.position);
                     var filecount = data.filecount;
                     channel.postMessage(  { action:'load', origheight:'$targetheight', origwidth:'$targetwidth', uri: imagesource, path: imagepath, filename: imagefilename }  );
                     $('#batch_info').html('".$currentBatch->getPath()." file ' + position +' of $filecount.');

                     // load data for this record.
                     loadNextData(position,".$currentBatch->getBatchID().");
                   },
                   error: function() {
                       $('#feedback').html( 'Failed.  Ajax Error.  Barcode: ' + ($('#barcode').val()) ) ;
                   }
               });
               event.preventDefault();
          });


          $('#doneButton').click(function(event){
               $('#feedback').html( 'Completing batch...');
               logEvent('done',$('#batch_info').html());
               // move position to mark batch as done
               $.ajax({
                   type: 'GET',
                   url: 'transcribe_handler.php',
                   dataType: 'json',
                   data: {
                       action: 'donebatch',
                       batch_id: ".$currentBatch->getBatchID()."
                   },
                   success: function(data) {
                     console.log(data);
                     doclear();
                   },
                   error: function() {
                       $('#feedback').html( 'Failed.  Ajax Error.  Barcode: ' + ($('#barcode').val()) ) ;
                   }
               });
               event.preventDefault();
          });

          /* TODO: Check SoRo
           */
          function checkSoRo() {


          }

          /* Enable or disable Save button
           */
          function checkSaveButton() {
            var barcode = $('#barcode').val();
            if (re_barcode.test(barcode)) {
                $('#saveButton').attr('disabled', false).removeClass('ui-state-disabled');
            } else {
                $('#saveButton').attr('disabled', true).addClass('ui-state-disabled');
            }
          }

          /* Set the value of a field if the field is empty or if the field is not a carryforward field.
           * @param field the id of the input for which to set the value
           * @param value the new value to set (unless the field is a carryforward with an existing value).
           */
          function setLoadedValue(field,value) {

              if ($('select[name='+field+']').length) {
                $('#'+field).css({'color':'black'});
              } else {
                $('#'+field).css({'background-color':'#FFFFFF'});
              }

              // carryforward field different from previous record, change color to notify user
              // if($('.carryforward[id][name='+field+']').length && $('#'+field).val()!=value && (value!=null || value!='')) {
              //  $('#'+field).css({'background-color':'#FFFAA2'});
              // } else {
              //  $('#'+field).css({'background-color':'#FFFFFF'});
              //}

              // set field to value, unless carryover field and value is null/empty
              if($('.carryforward[id][name='+field+']').length && (value==null || value=='')) {
                $('#'+field).css({'color':'darksalmon'});
              } else {
                $('#'+field).css({'color':'black'});
                $('#'+field).val(value);
              }

          }

          function loadFormData(data) {
              var barcodeval = data.barcode;
              if (data.barcode==null || data.barcode=='NOTFOUND' || data.barcode=='00000000') {
                  barcodeval = '';
              }

              $('#barcode').val(barcodeval);
              if ($('#barcode').val().length==0) {
                  $('#barcode').prop('disabled', null);
                  $('#recordcreated').html('New Record');
                  $('#feedback').html( 'Ready.');
              } else {
                  $('#barcode').prop('disabled', true);
                  $('#recordcreated').html(data.created);
                  //setLoadedValue('project',data.project);
                  setLoadedValue('container',data.container);
                  setLoadedValue('containerid',data.containerid);
                  setLoadedValue('prepmethod',data.prepmethod);
                  setLoadedValue('preptype',data.format);
                  setLoadedValue('filedundername',data.filedundername);
                  setLoadedValue('filedundernameid',data.filedundernameid);
                  setLoadedValue('filedunderqualifier',data.filedunderqualifier);
                  setLoadedValue('currentname',data.currentname);
                  setLoadedValue('currentnameid',data.currentnameid);
                  setLoadedValue('currentqualifier',data.currentqualifier);
                  setLoadedValue('identifiedby',data.identifiedby);
                  setLoadedValue('identifiedbyid',data.identifiedbyid);
                  setLoadedValue('dateidentified',data.dateidentified);
                  setLoadedValue('determinertext',data.determinertext);
                  setLoadedValue('specificlocality',data.specificlocality);
                  setLoadedValue('habitat',data.habitat);
                  setLoadedValue('frequency',data.frequency);
                  setLoadedValue('highergeography',data.geography);
                  setLoadedValue('highergeographyid',data.geographyid);
                  setLoadedValue('verbatimelevation',data.verbatimelevation);
                  setLoadedValue('collectors',data.collectors);
                  setLoadedValue('collectorsid',data.collectoragentid);
                  setLoadedValue('etal',data.etal);
                  setLoadedValue('stationfieldnumber',data.stationfieldnumber);
                  setLoadedValue('verbatimdate',data.verbatimdate);
                  setLoadedValue('datecollected',data.datecollected);
                  setLoadedValue('herbariumacronym',data.herbariumacronym);
                  setLoadedValue('provenance',data.provenance);
                  setLoadedValue('specimendescription',data.specimendescription);
                  setLoadedValue('specimenremarks',data.specimenremarks);
                  setLoadedValue('collectingtrip',data.collectingtrip);
                  setLoadedValue('collectingtripid',data.collectingtripid);
                  setLoadedValue('verbatimlat',data.verbatimlat);
                  setLoadedValue('verbatimlong',data.verbatimlong);
                  setLoadedValue('decimallat',data.decimallat);
                  setLoadedValue('decimallong',data.decimallong);
                  setLoadedValue('coordinateuncertainty',data.coordinateuncertainty);
                  setLoadedValue('georeferencesource',data.georeferencesource);

                  $('#feedback').html( data.barcode + ' Loaded. Ready.' + data.error);
              }

              checkSaveButton();

            /*
            num_matches
            prepmethod
            filedundername
            filedundernameid
            filedunderqualifier
            currentname
            currentnameid
            currentqualifier
            formatid
            format
            created
            project
            stationfieldnumber
            datecollected
            verbatimdate
            habitat
            collectoragentid
            collectors
            etal
            namedPlace
            verbatimElevation
            specificLocality
            geographyid
            geography
            error
            */
          // find the corresponding input (input id = data key) in transcribeForm

          // if the input value is empty, replace it with the value from data.

          // if the input value is not empty, and the input is not in class carry_forward, set the value from the data.
          // (if the input value is not empty, and the input is in class carry_forward, leave unchanged).

          }

          function loadDataForBarcode(barcodevalue) {
               console.log('called loadDataForBarcode() with ' + barcodevalue);
               $.ajax({
                   type: 'GET',
                   url: 'transcribe_handler.php',
                   dataType: 'json',
                   data: {
                       action: 'getdataforbarcode',
                       barcode: barcodevalue
                   },
                   success: function(data) {
                     console.log(data);

                     $('#barcode').val(barcodevalue);
                     $('#staticbarcode').html(data.barcode);
                     if (data.barcode==null || $('#barcode').val().length==0) {
                        $('#barcode').prop('disabled', null);
                     } else {
                        $('#barcode').val(data.barcode);
                        $('#barcode').prop('disabled', true);
                     }

                     // interate through the elements in data
                     if (data.num_matches == 0) {
                         $('#recordcreated').html('New Record');
                         $('#feedback').html( 'Ready.');
                     } else if (data.num_matches > 1) {
                         $('#feedback').html( '<strong>Warning:</strong> more than one match for Barcode: ' + $('#barcode').val() + ' Enter in Specify. '  );
                     } else {
                         $('#recordcreated').html(data.created);
                         loadFormData(data);
                         $('#feedback').html( data.barcode + ' Loaded. Ready.' + data.error);
                     }

                   },
                   error: function() {
                       $('#feedback').html( 'Failed.  Ajax Error.  Barcode: ' + ($('#barcode').val()) ) ;
                   }
               });
          }

          function loadNextData(position,batch_id) {
               console.log('called loadNextData() with ' + position + ',' +batch_id);

               if(position > 1) {
                   $('#previousButton').attr('disabled', false).removeClass('ui-state-disabled');
               } else {
                   $('#previousButton').attr('disabled', true).addClass('ui-state-disabled');
               }

               if(position < $filecount) {
                   $('#nextButton').attr('disabled', false).removeClass('ui-state-disabled');
                   $('#doneButton').attr('disabled', true).addClass('ui-state-disabled');
               } else {
                   $('#nextButton').attr('disabled', true).addClass('ui-state-disabled');
                   $('#doneButton').attr('disabled', false).removeClass('ui-state-disabled');
               }

               var params = new URLSearchParams(window.location.search);
               params.set('position', position);
               window.history.pushState({}, '', decodeURIComponent(`${location.pathname}?${params}`));

                $.ajax({
                   type: 'GET',
                   url: 'transcribe_handler.php',
                   dataType: 'json',
                   data: {
                       action: 'getnextrecord',
                       batch_id: batch_id,
                       position: position
                   },
                   success: function(data) {
                     console.log(data);
                     loadFormData(data);
                   },
                   error: function() {
                      console.log('ajax call to transcribe_handler.php/action=getnextrecord failed');
                      $('#feedback').html( 'Failed.  Ajax Error.  Barcode: ' + ($('#barcode').val()) ) ;
                   }
               });
          }


   </script>";

   echo "<script>
         $('#saveButton').click(function(event){
               // handle disabled fields, copy data to val fields.
               $('#barcodeval').val($('#barcode').val());
               $('#feedback').html( 'Submitting: ' + ($('#barcode').val()) ) ;
               $.ajax({
                   type: 'POST',
                   url: 'transcribe_handler.php',
                   data: $('#transcribeForm').serialize(),
                   success: function(data) {
                       $('#feedback').html( data ) ;
                   },
                   error: function() {
                       $('#feedback').html( 'Failed.  Ajax Error.  Barcode: ' + ($('#barcode').val()) ) ;
                   }
               });
               event.preventDefault();
          });
   </script>";

   echo "</table>";
   echo '</div>';  // end of accordion

   echo '</div>';
   echo '</div>'; //end leftside

   echo '<div style="display: inline-block; float: right;" id="rightside">';

   echo '<script>
         $( function(){
            $("#imagezoomdiv").accordion( { heightStyle: "content" } ) ;
            $("#extrafieldsdiv").accordion( { heightStyle: "content" } ) ;
            $("#geofieldsdiv").accordion( { heightStyle: "content" } ) ;
          });
   </script>';
   echo '<div id="imagezoomdiv" >';
   echo '<h3 style=" margin-top: 1px; margin-bottom: 0px;">Click to zoom in other window.</h3>';
   echo '<div>';

   #$mediauri = "http://nrs.harvard.edu/urn-3:FMUS.HUH:s19-00000001-315971-2";
   #$mediauri = "https://s3.amazonaws.com/huhwebimages/94A28BC927D6407/type/full/460286.jpg";
   #$mediauri = imageForBarcode($targetbarcode);

   echo '<div class=flexbox>';
    $medialink = $target->medialink;
    echo "
        <script>
           channel.onmessage = function (e) {
               console.log(e);
               if (e.data=='loaded') {
                  logEvent('image_loaded',$('#batch_info').html())
                  $('#feedback').html( 'Image Loaded');
                  // trigger zoom onto loaded image.
                  // channel.postMessage( { x:'355', y:'569', oh:'5616', ow:'3744', h:'700', w:'467', id:'' }  );
                  channel.postMessage( { x:'353', y:'614', oh:'".$target->height."', ow:'".$target->width."', h:'700', w:'467', id:'' }  );
               } else {
                  logEvent('message',e.data)
               }
           }

           function getClick(event,height, width, origheight,origwidth,imagesetid){
               xpos = event.offsetX?(event.offsetX):event.pageX-document.getElementById('image_div').offsetLeft;
               ypos = event.offsetY?(event.offsetY):event.pageY-document.getElementById('image_div').offsetTop;
               channel.postMessage( { x:xpos, y:ypos, h:height, w:width, oh:origheight, ow:origwidth, id:imagesetid } )
           }

        </script>
    ";
   echo "$medialink";
   echo '</div>';
   echo '</div>';


   echo '<div id="extrafieldsdiv" style="margin-top: 5px;">';
   echo '<h3 style="display: none; margin-top: 1px; margin-bottom: 0px;">Additional fields</h3>';
   echo '<div>';
   echo '<table>';
   @staticvalueid("Record Created:",$created,"recordcreated");
   selectPrepMethod("prepmethod","Prep Method",$prepmethod,'true','true');
   selectPrepType("preptype","Format",$defaultformat,'true','true');
   echo '</table>';
   echo '</div>';
   echo '</div>';

   if ($config=="minimal") {
     // nada
   } else {
     // TODO FIXME add these fields to the
     // 1) initial page load, 2) barcodelookup method (a. data result, b. setLoadedValue()), 3) ingest
     $verbatimlat='';
     $verbatimlong='';
     $decimallat='';
     $decimallong='';
     $coordinateuncertainty='';
     $georeferencesource='';


     $bak_basewidth = $GLOBALS['BASEWIDTH'];
     $GLOBALS['BASEWIDTH'] = 11;

     echo '<div id="geofieldsdiv" style="margin-top: 5px;">';
     echo '<h3 style="display: none; margin-top: 1px; margin-bottom: 0px;">Geodata fields</h3>';
     echo '<div>';
     echo '<table>';
     @field ("verbatimelevation","Verb. Elev.",$verbatimElevation,'false');
     @field ("verbatimlat","Verb. Lat.",$verbatimlat);
     @field ("verbatimlong","Verb. Long.",$verbatimlong);
     @field ("decimallat","Dec. Lat.",$decimallat,'false','\-?[0-9]{1,2}(\.{1}[0-9]*)?');
     @field ("decimallong","Dec. Long.",$decimallong,'false','\-?[0-1]?[0-9]{1,2}(\.{1}[0-9]*)?');
     @field ("georeferencesource",'Method',$georeferencesource,'false');
     //field ("datum","Datum",$datum); // almost never encountered on a label
     @field ("coordinateuncertainty","Uncertainty",$coordinateuncertainty,'false','[0-9]*');
     //@selectCollectorsID("georeferencedby","Georef. By",$georeferencedby,$georeferencedbyid,'false','false'); // This might only make sense in the data model for post-hoc georeferencing
     //@field ("dategeoreferenced","Georef. Date",$dategeoreferenced,'false','([0-9]{4}(-[0-9]{2}){0,2}){1}(/([0-9]{4}(-[0-9]{2}){0,2}){1}){0,1}','','Use of an ISO format is required: yyyy, yyyy-mm, yyyy-mm-dd, or yyyy-mm-dd/yyyy-mm-dd'); // doesn't make sense for label transcription, should be used for post-hoc georeferencing
     //utm($utmzone, $utmeasting, $utmnorthing); // rarely encountered during transcription
     echo '</table>';
     echo '</div>';
     echo '</div>';

     $GLOBALS['BASEWIDTH'] = $bak_basewidth;
   }

   echo "</form>\n";

   echo '</div>'; //end rightside

   echo '</div>';


   /*
   echo '<div class="flexbox"><div id="testimage"><img src="'.$mediauri.'" width="360"></div><div class="flexbox"><div id="imgtarget" style="width: 680px;"></div></div></div>';
   echo "<script>
         $(document).ready(function(){
               $('#testimage').zoom({
                    url: '$mediauri',
                    magnify: 1.1,
                    target: 'imgtarget',
                    on: 'togglehold'
               });
          });
   </script>";
   echo "<div>Scale zoom to:<span id=scale>1.1</span>&nbsp;&nbsp;";
   echo '<span onclick="$(\'#testimage\').trigger(\'zoom.destroy\'); $(\'#testimage\').zoom({ url:\''.$mediauri.'\',magnify:0.4,target:\'imgtarget\',on:\'togglehold\'}); $(\'#scale\').html(\'0.4\');">[---]</span>';
   echo '<span onclick="$(\'#testimage\').trigger(\'zoom.destroy\'); $(\'#testimage\').zoom({ url:\''.$mediauri.'\',magnify:0.6,target:\'imgtarget\',on:\'togglehold\'}); $(\'#scale\').html(\'0.6\');">[--]</span>';
   echo '<span onclick="$(\'#testimage\').trigger(\'zoom.destroy\');$(\'#testimage\').zoom({ url:\''.$mediauri.'\',magnify:0.8,target:\'imgtarget\',on:\'togglehold\'}); $(\'#scale\').html(\'0.8\');">[-]</span>';
   echo '<span onclick="$(\'#testimage\').trigger(\'zoom.destroy\'); $(\'#testimage\').zoom({ url:\''.$mediauri.'\',magnify:1.1,target:\'imgtarget\',on:\'togglehold\'}); $(\'#scale\').html(\'1.1\');">[1]</span>';
   echo '<span onclick="$(\'#testimage\').trigger(\'zoom.destroy\'); $(\'#testimage\').zoom({ url:\''.$mediauri.'\',magnify:1.2,target:\'imgtarget\',on:\'togglehold\'}); $(\'#scale\').html(\'1.2\');">[+]</span>';
   echo '<span onclick="$(\'#testimage\').trigger(\'zoom.destroy\'); $(\'#testimage\').zoom({ url:\''.$mediauri.'\',magnify:1.4,target:\'imgtarget\',on:\'togglehold\'}); $(\'#scale\').html(\'1.4\');">[++]</span>';
   echo '<span onclick="$(\'#testimage\').trigger(\'zoom.destroy\'); $(\'#testimage\').zoom({ url:\''.$mediauri.'\',magnify:1.6,target:\'imgtarget\',on:\'togglehold\'}); $(\'#scale\').html(\'1.6\');">[+++]</span>';
   echo '</div>';

   echo '</div>';
   echo '</div>';
   */

}

function field($name, $label, $default="", $required='false', $regex='', $placeholder='', $validationmessage='', $enabled='true') {
   echo "<tr><td>\n";
   echo "<label for='$name'>$label</label>";
   echo "</td><td>\n";
   if ($regex!='') {
      $regex = "pattern='$regex'";
   }
   if ($validationmessage!='') {
      $validationmessage = "validationMessage='$validationmessage'";
   }
   if ($placeholder!='') {
      $placeholder = "placeHolder='$placeholder'";
   }
   if ($enabled=='false') {
      $disabled = "disabled='true'";
   } else {
      $disabled = '';
   }
   if ($required=='false') {
      echo "<input id=$name name=$name value='$default' $regex $placeholder $validationmessage  style='width: ".$GLOBALS['BASEWIDTH']."em; ' $disabled >";
   } else {
      if ($validationmessage!='') {
         $validationmessage = "validationMessage='Required Field. $validationmessage'";
      }
      echo "<input id=$name name=$name value='$default' required='$required' $regex $placeholder $validationMessage  style='width: ".$GLOBALS['BASEWIDTH']."em; ' $disabled >";
   }
   echo "</td></tr>\n";
}


function fieldEnabalable($name, $label, $default="", $required='false', $regex='', $placeholder='', $validationmessage='', $enabled='true') {
   echo "<tr><td>\n";
   echo "<label for='$name'>$label</label>";
   echo "</td><td>\n";
   if ($regex!='') {
      $regex = "pattern='$regex'";
   }
   if ($validationmessage!='') {
      $validationmessage = "validationMessage='$validationmessage'";
   }
   if ($placeholder!='') {
      $placeholder = "placeHolder='$placeholder'";
   }
   if ($enabled=='false') {
      $disabled = "disabled='true'";
   } else {
      $disabled = '';
   }
   //$width = BASEWIDTH - 2;
   $width = $GLOBALS['BASEWIDTH'];
   if ($required=='false') {
      echo "<input id=$name name=$name value='$default' $regex $placeholder $validationmessage  style='width: ".$width."em; ' $disabled >";
   } else {
      if ($validationmessage!='') {
         $validationmessage = "validationMessage='Required Field. $validationmessage'";
      }
      echo "<input id=$name name=$name value='$default' required='$required' $regex $placeholder $validationmessage  style='width: ".$width."em; ' $disabled >";
   }
   //echo "<input type='button' value='Δ' id='enable$name' onclick=' doEnable$name(); event.preventDefault();' class='carryforward ui-button'>";
   echo "<script>
         function doEnable$name(){
            $('#$name').prop('disabled', null);
          };
   </script>";
   echo "</td></tr>\n";
}

function selectPrepMethod($field,$label,$default,$required='true',$carryforward='true') {
   if ($carryforward=='true') { $carryforward = " class='carryforward' "; } else { $carryforward=""; }
   $returnvalue = "
  <script>
  $( function() {
    $( '#$field' ).autocomplete({
      source: 'ajax_handler.php?druid_action=returndistinctjqaprepmethod',
      minLength: 0,
      select: function( event, ui ) {
         // alert( 'Selected: ' + ui.item.value + ' aka ' + ui.item.id );
      }
    });
  } );
  </script>
  <tr><td>
  <label for='$field'>$label</label>
  </td><td>
     <div class='ui-widget'>
        <input id='$field' name='$field' value='$default'  style='width: 9em; ' $carryforward >
     </div>
  </td></tr>
   ";
   echo $returnvalue;
}

function selectPrepType($field,$label,$default,$required='true',$carryforward='true') {
   if ($carryforward=='true') { $carryforward = " class='carryforward' "; } else { $carryforward=""; }
   $returnvalue = "
  <script>
  $( function() {
    $( '#$field' ).autocomplete({
      source: 'ajax_handler.php?druid_action=returndistinctjqapreptype',
      minLength: 0,
      select: function( event, ui ) {
         // alert( 'Selected: ' + ui.item.value + ' aka ' + ui.item.id );
      }
    });
  } );
  </script>
  <tr><td>
  <label for='$field'>$label</label>
  </td><td>
     <div class='ui-widget'>
        <input id='$field' name='$field' value='$default'  style='width: 9em; ' $carryforward >
     </div>
  </td></tr>
   ";
   echo $returnvalue;
}


function utm($utmzonedefault='', $utmeastingdefault='', $utmnorthingdefault='') {
	echo "<tr><td>\n";
	echo "<label for='utmzone'>UTM Zone,Easting,Northing</label>";
	echo "</td><td>\n";
	echo "<input name=utmzone value=$utmzonedefault dojoType='dijit.form.ValidationTextBox' required='false' regExp='[0-9]+[A-Z]' style='width: 4em;' >";
	echo "<input name=utmeasting value=$utmeastingdefault dojoType='dijit.form.ValidationTextBox' required='false' regExp='[0-9]+' style='width: 8em;' >";
	echo "<input name=utmnorthing value=$utmnorthingdefault dojoType='dijit.form.ValidationTextBox' required='false' regExp='[0-9]+' style='width: 8em;' >";
   echo "</td></tr>\n";
}

function preptypeselect($name,$label,$default,$required,$storeId,$table,$field) {
   $returnvalue = "<tr><td><div dojoType='dojo.data.ItemFileReadStore' jsId='$storeId'
	 url='ajax_handler.php?druid_action=returndistinctjsonpreptype&table=$table&field=$field'> </div>";
   $returnvalue .= "<label for=\"$name\">$label</label></td><td>
	<input type=text name=$name id=$name dojoType='dijit.form.FilteringSelect'
	store='$storeId'
	searchAttr='name' value='$default' ></td></tr>";
   echo $returnvalue;
}

function staticvalue($label,$default) {
	$returnvalue = "<tr><td><label>$label</label></td><td>$default</td></tr>";
	echo $returnvalue;
}
function staticvalueid($label,$default,$id) {
	$returnvalue = "<tr><td><label>$label</label></td><td><span id='$id'>$default</span></td></tr>";
	echo $returnvalue;
}

function selectHigherGeography($field,$label,$value,$valueid, $defaultcountry='', $defaultprimary='',$required='true',$carryforward='false') {
   $returnvalue = "<tr><td>";
   $fieldid = $field."id";
   if ($required=='true') { $req = " required='true' "; } else { $req = ''; }
   if ($field=='geographyfilter') { $style = " background-color: lightgrey; "; } else { $style = ""; }
   if ($carryforward=='true') { $carryforward = " class='carryforward' "; } else { $carryforward=""; }
   $returnvalue .= "<label for=\"$field\">$label</label></td><td>
    <input type=text name=$field id=$field $req  value='$value' style=' width: ".$GLOBALS['BASEWIDTH']."em; $style ' $carryforward  >
    <input type=hidden name=$fieldid id=$fieldid $req value='$valueid' $carryforward >
    </td></tr>";
   $returnvalue .= '
      <script>
         $(function() {
            $( "#'.$field.'" ).autocomplete({
               minLength: 2,
               source: function( request, response ) {
                  $.ajax( {
                    url: "ajax_handler.php",
                    dataType: "json",
                    data: {
                       druid_action: "geoidgeojson",
                       term: request.term,
                       rank: 300,
                       field: "'.$field.'"
                    },
                    success: function( data ) {
                       response( data );
                    }
                  } );
                },
                select: function( event, ui ) {
                    $("#'.$fieldid.'").val(ui.item.id);
                }
            } );
         } );
      </script>
   ';
   echo $returnvalue;
}

function selectHigherGeographyFiltered($field,$label,$value,$valueid, $defaultcountry='', $defaultprimary='',$required='true') {
   $returnvalue = "<tr><td>";
   $fieldid = $field."id";
   if ($required=='true') { $req = " required='true' "; } else { $req = ''; }
   $returnvalue .= "<label for=\"$field\">$label</label></td><td>
    <input type=text name=$field id=$field $req  value='$value' style=' width: ". $GLOBALS['BASEWIDTH'] ."em; ' >
    <input type=hidden name=$fieldid id=$fieldid required='$required'  value='$valueid' >
    </td></tr>";
   $returnvalue .= '
      <script>
         $(function() {
            $( "#'.$field.'" ).autocomplete({
               minLength: 2,
               source: function( request, response ) {
                  $.ajax( {
                    url: "ajax_handler.php",
                    dataType: "json",
                    data: {
                       druid_action: "geoidgeojson",
                       term: request.term,
                       within: $("#geographyfilter").val()
                    },
                    success: function( data ) {
                       response( data );
                    }
                  } );
                },
                select: function( event, ui ) {
                    $("#'.$fieldid.'").val(ui.item.id);
                }
            } );
         } );
      </script>
   ';
   echo $returnvalue;
}

function fieldselectpicklist($name,$label,$default,$required,$storeId,$picklistid) {
   if ($required=='true') { $req = '&required=true'; } else { $req = ''; }
   $returnvalue = "<tr><td><div dojoType='dojo.data.ItemFileReadStore' jsId='$storeId'
	 url='ajax_handler.php?druid_action=returndistinctjsonpicklist&field=value&picklistid=$picklistid$req'> </div>";
   $returnvalue .= "<label for=\"$name\">$label</label></td><td>
	<input type=text name=$name id=$name dojoType='dijit.form.FilteringSelect'
	store='$storeId' required='$required'
	searchAttr='name' value='$default' style=' width: ". $GLOBALS['BASEWIDTH'] ."em; '></td></tr>";
   echo $returnvalue;
}

function selectYesNo($field,$label) {
	echo "<tr><td>\n";
	echo "<label for='$field'>$label</label>";
	echo "</td><td>\n";
	echo '<select name="'.$field.'" >
	<option value="" selected>&nbsp;&nbsp;&nbsp;</option>
	<option value="1">Yes</option>
	<option value="0">No</option>
	</select>';
	echo "</td></tr>\n";
}

function selectQualifier($field,$label,$default) {
	echo "<tr><td>\n";
	echo "<label for='$field'>$label</label>";
	echo "</td><td>\n";
	echo '<select name="'.$field.'" id="'.$field.'">';
    if ($default=="") { $s0 = 'selected="selected"'; $sss = ""; $sq = ""; $sn=""; $scf=""; $ssl=""; $saf=""; }
    if ($default=="SensuStricto") { $s0 = ''; $sss = 'selected="selected"'; $sq = ""; $sn=""; $scf=""; $ssl=""; $saf=""; }
    if ($default=="InQuestion") { $s0 = ''; $sss = ""; $sq = 'selected="selected"'; $sn=""; $scf=""; $ssl=""; $saf=""; }
    if ($default=="Not") { $s0 = ''; $sss = ""; $sq = ""; $sn='selected="selected"'; $scf=""; $ssl=""; $saf=""; }
    if ($default=="Compare") { $s0 = ''; $sss = ""; $sq = ""; $sn=""; $scf='selected="selected"'; $ssl=""; $saf=""; }
    if ($default=="SensuLato") { $s0 = ''; $sss = ""; $sq = ""; $sn=""; $scf=""; $ssl='selected="selected"'; $saf=""; }
    if ($default=="Affine") { $s0 = ''; $sss = ""; $sq = ""; $sn=""; $scf=""; $ssl=""; $saf='selected="selected"'; }
	echo "<option value='' $s0></option>";
    echo "<option value='SensuStricto' $sss>s. str.</option>";
    echo "<option value='InQuestion' $sq>?</option>";
    echo "<option value='Not' $sn>not</option>";
    echo "<option value='Compare' $cfs>cf.</option>";
    echo "<option value='SensuLato' $ssl>s. lat.</option>";
    echo "<option value='Affine' $saf>aff.</option>";
	echo '</select>';
	echo "</td></tr>\n";
}

function selectAcronym($field,$default) {
   echo "<tr><td>\n";
   echo "<label for='$field'>Herbarium acronym</label>";
   echo "</td><td>\n";
   if ($default=="GH") { $ghs = 'selected="selected"'; $as = ""; $fhs = ""; $amess=""; $econs=""; $nebcs=""; }
   if ($default=="A") { $ghs = ""; $as = 'selected="selected"'; $fhs = ""; $amess=""; $econs=""; $nebcs=""; }
   if ($default=="FH") { $ghs = ""; $as = ""; $fhs = 'selected="selected"'; $amess=""; $econs=""; $nebcs=""; }
   if ($default=="AMES") { $ghs = ""; $as = ""; $fhs = ""; $amess='selected="selected"'; $econs=""; $nebcs=""; }
   if ($default=="ECON") { $ghs = ""; $as = ""; $fhs = ""; $amess=""; $econs='selected="selected"'; $nebcs=""; }
   if ($default=="NEBC") { $ghs = ""; $as = ""; $fhs = ""; $amess=""; $econs=""; $nebcs='selected="selected"'; }
   echo "<select id=\"$field\" name=\"$field\" class='carryforward'>
	<option value=\"GH\" $ghs>GH</option>
	<option value=\"A\" $as>A</option>
	<option value=\"NEBC\" $nebcs>NEBC</option>
	<option value=\"FH\" $fhs>FH</option>
	<option value=\"AMES\" $amess>AMES</option>
	<option value=\"ECON\" $econs>ECON</option>
	</select>";
   echo "</td></tr>\n";
}

function selectTaxon($field,$label,$value,$valueid,$required='false',$carryforward='false') {
   $returnvalue = "<tr><td>";
   $fieldid = $field."id";
   if ($required=='true') { $req = " required='true' "; } else { $req = ''; }
   if ($carryforward=='true') { $carryforward = " class='carryforward' "; } else { $carryforward=""; }
   $returnvalue .= "<label for=\"$field\">$label</label></td><td>
	<input type=text name=$field id=$field $req  value='$value' style=' width:". $GLOBALS['BASEWIDTH'] ."em;' $carryforward >
	<input type=hidden name=$fieldid id=$fieldid $req  value='$valueid' $carryforward >
    </td></tr>";
   $returnvalue .= '
      <script>
         $(function() {
            $( "#'.$field.'" ).autocomplete({
               minLength: 2,
               source: function( request, response ) {
                  $.ajax( {
                    url: "ajax_handler.php",
                    dataType: "json",
                    data: {
                       druid_action: "taxonidtaxonjsonp",
                       term: request.term
                    },
                    success: function( data ) {
                       response( data );
                    }
                  } );
                },
                select: function( event, ui ) {
                    $("#'.$fieldid.'").val(ui.item.id);
                }
            } );
         } );
      </script>
   ';
   echo $returnvalue;
}

function selectBasionymID($field,$label,$required='false') {
  if ($required=='true') { $req = " required='true' "; } else { $req = ''; }
	$returnvalue = "<tr><td><div dojoType='custom.ComboBoxReadStore' jsId='taxonStore$field'
	url='ajax_handler.php?druid_action=returndistinctjsonidnamelimited&table=huh_taxon&field=FullName'> </div>";
	$width = $GLOBALS['BASEWIDTH'] - 3;
	$returnvalue .= "<label for=\"$field\">$label</label></td><td>
	<input type=text name=$field id=$field dojoType='dijit.form.FilteringSelect'
	store='taxonStore$field' $req searchDelay='900' hasDownArrow='false'
	style='width: ".$width."em; border-color: blue; '
	searchAttr='name' value='' >
	<button id='buttonReset$field' dojoType='dijit.form.Button' data-dojo-type='dijit/form/Button' type='button'
	onclick=\"dijit.byId('$field').reset();\"  data-dojo-props=\"iconClass:'dijitIconClear'\" ></button>
	</td></tr>";
	echo $returnvalue;
}

function selectCollectorsID($field,$label,$value,$valueid,$required='false',$carryforward='false') {
   $returnvalue = "<tr><td>";
   $fieldid = $field."id";
   if ($required=='true') { $req = " required='true' "; } else { $req = ''; }
   if ($carryforward=='true') { $carryforward = " class='carryforward' "; } else { $carryforward=""; }
   $returnvalue .= "<label for=\"$field\">$label</label></td><td>
    <input type=text name=$field id=$field $req  value='$value' style=' width: ". $GLOBALS['BASEWIDTH'] ."em; ' $carryforward >
    <input type=hidden name=$fieldid id=$fieldid $req  value='$valueid' $carryforward >
    </td></tr>";
   $returnvalue .= '
      <script>

        $( "#'.$field.'" ).focusout(
          function() {
            if ($("#'.$field.'").val()=="") {
              $("#'.$fieldid.'").val("");
            }
          }
        );

       $(function() {
          $( "#'.$field.'" ).autocomplete({
             minLength: 2,
             delay: 400,
             source: function( request, response ) {
                $.ajax( {
                  url: "ajax_handler.php",
                  dataType: "json",
                  data: {
                     druid_action: "collagentidjson",
                     term: request.term
                  },
                  success: function( data ) {
                     response( data );
                  }
                } );
              },
              select: function( event, ui ) {
                  $("#'.$fieldid.'").val(ui.item.id);
              }
          } );
       } );
    </script>
   ';
   echo $returnvalue;
}


function selectRefWorkID($field,$label,$required='false',$exsiccati='false') {
   if ($required=='true') { $req = " required='true' "; } else { $req = ''; }
   if ($exsiccati=='true') {
       $returnvalue = "<tr><td><div dojoType='custom.ComboBoxReadStore' jsId='agentStore$field'
	      url='ajax_handler.php?druid_action=returndistinctjsonexsiccati' > </div>";
   } else {
       $returnvalue = "<tr><td><div dojoType='custom.ComboBoxReadStore' jsId='agentStore$field'
	      url='ajax_handler.php?druid_action=returndistinctjsontitle' > </div>";
   }
   $returnvalue .= "<label for=\"$field\">$label</label></td><td>
	<input type='text' name=$field id=$field dojoType='dijit.form.FilteringSelect'
	store='agentStore$field' $req searchDelay='900' hasDownArrow='false' style='border-color: blue;'
	searchAttr='name' value='' >
   <button id='buttonReset$field' dojoType='dijit.form.Button' data-dojo-type='dijit/form/Button' type='button'
   onclick=\"dijit.byId('$field').reset();\"  data-dojo-props=\"iconClass:'dijitIconClear'\" ></button></td></tr>";
   echo $returnvalue;
}

function selectStorageID($field,$label,$required='false') {
   if ($required=='true') { $req = " required='true' "; } else { $req = ''; }
   $returnvalue = "<tr><td><div dojoType='custom.ComboBoxReadStore' jsId='agentStore$field'
	      url='ajax_handler.php?druid_action=returndistinctjsonstorage' > </div>";
   $returnvalue .= "<label for=\"$field\">$label</label></td><td>
	<input type='text' name=$field id=$field dojoType='dijit.form.FilteringSelect'
	store='agentStore$field' $req searchDelay='900' hasDownArrow='false' style='border-color: blue;'
	searchAttr='name' value='' >
	<button id='buttonReset$field' dojoType='dijit.form.Button' data-dojo-type='dijit/form/Button' type='button'
    onclick=\"dijit.byId('$field').reset();\"  data-dojo-props=\"iconClass:'dijitIconClear'\" ></button></td></tr>";
   echo $returnvalue;
}

// function selectContainerID($field,$label,$required='false') {
//   if ($required=='true') { $req = " required='true' "; } else { $req = ''; }
// 	$returnvalue = "<tr><td><div dojoType='custom.ComboBoxReadStore' jsId='agentStore$field'
// 	 url='ajax_handler.php?druid_action=returndistinctjsoncontainer' > </div>";
// 	$returnvalue .= "<label for=\"$field\">$label</label></td><td>
// 	<input type='text' name=$field id=$field dojoType='dijit.form.FilteringSelect'
// 	store='agentStore$field' $req searchDelay='900' hasDownArrow='false' style='border-color: blue;'
// 	searchAttr='name' value='' >
// 	<button id='buttonReset$field' dojoType='dijit.form.Button' data-dojo-type='dijit/form/Button' type='button'
//     onclick=\"dijit.byId('$field').reset();\"  data-dojo-props=\"iconClass:'dijitIconClear'\" ></button></td></tr>";
// 	echo $returnvalue;
// }

function selectContainerID($field,$label,$value,$valueid,$required='false',$carryforward='false') {
   $returnvalue = "<tr><td>";
   $fieldid = $field."id";
   if ($required=='true') { $req = " required='true' "; } else { $req = ''; }
   if ($carryforward=='true') { $carryforward = " class='carryforward' "; } else { $carryforward=""; }
   $returnvalue .= "<label for=\"$field\">$label</label></td><td>
    <input type=text name=$field id=$field $req  value='$value' style=' width: ". $GLOBALS['BASEWIDTH'] ."em; ' $carryforward >
    <input type=hidden name=$fieldid id=$fieldid $req  value='$valueid' $carryforward >
    </td></tr>";
   $returnvalue .= '
      <script>
         $(function() {
            $( "#'.$field.'" ).autocomplete({
               minLength: 2,
               delay: 400,
               source: function( request, response ) {
                  $.ajax( {
                    url: "ajax_handler.php",
                    dataType: "json",
                    data: {
                       druid_action: "returnjsoncontainer",
                       term: request.term
                    },
                    success: function( data ) {
                       response( data );
                    }
                  } );
                },
                select: function( event, ui ) {
                    $("#'.$fieldid.'").val(ui.item.id);
                }
            } );
         } );
      </script>
   ';
   echo $returnvalue;
}

function selectCollectingTripID($field,$label,$value,$valueid,$carryforward='false') {
   $returnvalue = "<tr><td>";
   $fieldid = $field."id";
   $returnvalue .= "<label for=\"$field\">$label</label></td><td>
    <input type=text name=$field id=$field  value='$value' style=' width: ". $GLOBALS['BASEWIDTH'] ."em; ' >
    <input type=hidden name=$fieldid id=$fieldid value='$valueid' >
    </td></tr>";
   $returnvalue .= '
      <script>

        $( "#'.$field.'" ).focusout(
          function() {
            if ($("#'.$field.'").val()=="") {
              $("#'.$fieldid.'").val("");
            }
          }
        );

       $(function() {
          $( "#'.$field.'" ).autocomplete({
             minLength: 2,
             source: function( request, response ) {
                $.ajax( {
                  url: "ajax_handler.php",
                  dataType: "json",
                  data: {
                     druid_action: "colltripidcolltripjson",
                     term: request.term
                  },
                  success: function( data ) {
                     response( data );
                  }
                } );
              },
              select: function( event, ui ) {
                  $("#'.$fieldid.'").val(ui.item.id);
              }
          } );
       } );
      </script>
   ';
   echo $returnvalue;
}

function selectProject($field,$label,$default,$required='false') {
    if ($required=='true') { $req = " required='true' "; } else { $req = ''; }
    $returnvalue = "
  <script>
  $( function() {
    $( '#$field' ).autocomplete({
      source: 'ajax_handler.php?druid_action=returndistinctjqaproject',
      minLength: 2,
      select: function( event, ui ) {
         // alert( 'Selected: ' + ui.item.value + ' aka ' + ui.item.id );
      }
    });
  } );
  </script>
  <tr><td>
  <label for='$field'>$label</label>
  </td><td>
     <div class='ui-widget'>
        <input name='$field' id='$field' value='$default' $req style='width: ".$GLOBALS['BASEWIDTH']."em; ' class='carryforward' >
     </div>
  </td></tr>
    ";
    echo $returnvalue;
}


?>
