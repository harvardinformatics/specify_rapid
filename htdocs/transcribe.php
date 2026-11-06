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

@$imgSrc = substr(preg_replace('/[^a-z]/','',$_GET['imgsrc']),0,20);
if ($imgSrc == 'RC') {
  $imgSrcLabel = 'RC';
} else {
  $imgSrc = 'AWS';
  $imgSrcLabel = 'AWS';
}

@$transcriptionMode = substr(preg_replace('/[^a-z]/','',$_GET['mode']),0,20);
if ($transcriptionMode == 'minimal') {
  $modeLabel = 'Minimal';
} else {
  $transcriptionMode = 'detailed';
  $modeLabel = 'Detailed';
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
   // Bug fix: previously an existing session always took precedence, so clicking
   // Logout (display=logout) or submitting the login form as a different user left
   // the previous user's session in place and the app kept acting as that user.
   if ($display=='logout') {
      // Explicit logout request: destroy the session and start a fresh, empty one.
      $user->logout();
      session_start();
      session_regenerate_id(true);
      $_SESSION = array();
   } elseif (@$_POST['password']!="" && @$_POST['username']!="") {
      // New login attempt: discard any existing session data (possibly for another
      // user) so that the credentials just submitted are the ones authenticated.
      $_SESSION = array();
      session_regenerate_id(true);
   }
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
            $authenticated = true;
            if ($tdisplay == "" || $tdisplay == "logout" || $tdisplay == "logindialog") {
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
         $user->logout();
      }
   }
} else {
   $authenticated = true;
}
if ($display=="") {
   $display="setup";
}
session_write_close();

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
      echo " <strong>Current Batch: [$batchPath]</strong>";
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

   // Search for a barcode across the transcription batches.
   echo "<div id='findbarcodediv' style='padding: 0.5em;' >";
   echo " <strong>Find a barcode:</strong> ";
   echo "<input type='text' id='findbarcode' name='findbarcode' size='10' maxlength='20' placeholder='Barcode' autocomplete='off'>";
   echo "<button type='button' id='findbarcodebutton' class='ui-button'>Find</button>";
   echo "<div id='findbarcoderesults' style='padding-top: 0.5em;'></div>";
   echo "</div>";
   echo "<script type='text/javascript'>
      function escapeHtml(value) {
         return String(value == null ? '' : value).replace(/[&<>\"']/g, function(c) {
            return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '\"':'&quot;', \"'\":'&#39;' }[c];
         });
      }
      function findBarcode() {
         var barcode = $('#findbarcode').val().replace(/[^0-9]/g,'');
         if (barcode.length==0) {
            $('#findbarcoderesults').html('Enter a barcode (digits only) to search for.');
            return;
         }
         $('#findbarcoderesults').html('Searching...');
         $.ajax({
            type: 'GET',
            url: 'transcribe_handler.php',
            dataType: 'json',
            data: { action: 'findbarcode', barcode: barcode },
            success: function(data) {
               if (data.error) {
                  $('#findbarcoderesults').html('Error: ' + escapeHtml(data.error));
                  return;
               }
               if (data.batches.length==0) {
                  $('#findbarcoderesults').html('Barcode ' + escapeHtml(data.barcode) + ' was not found in any batch.');
                  return;
               }
               var html = '<div>Barcode ' + escapeHtml(data.barcode) + ' found in ' + data.batches.length + ' batch position(s).</div>';
               html += \"<table class='findbarcodetable' style='border-collapse: collapse;'>\";
               html += '<tr><th align=left>Batch</th><th align=left>Status</th><th align=left>Position</th><th align=left>File</th><th></th></tr>';
               for (var i=0; i<data.batches.length; i++) {
                  var b = data.batches[i];
                  html += '<tr>';
                  html += '<td style=\"padding-right: 1em;\">' + escapeHtml(b.batch_path) + '</td>';
                  html += '<td style=\"padding-right: 1em;\">' + (b.completed==1 ? 'Done' : 'Open') + '</td>';
                  html += '<td style=\"padding-right: 1em;\">' + escapeHtml(b.position) + ' of ' + escapeHtml(b.filecount) + '</td>';
                  html += '<td style=\"padding-right: 1em;\">' + escapeHtml(b.filename) + '</td>';
                  html += \"<td><button type='button' class='ui-button findbarcodego' data-index='\" + i + \"'>Edit in batch at position \" + escapeHtml(b.position) + '</button></td>';
                  html += '</tr>';
               }
               html += '</table>';
               $('#findbarcoderesults').html(html);
               $('.findbarcodego').on('click', function() {
                  var b = data.batches[$(this).data('index')];
                  $('#cover').fadeIn(100);
                  dosetuppath(encodeURIComponent(b.batch_path), encodeURIComponent(b.path), encodeURIComponent(b.filename), b.position, 'standard');
               });
            },
            error: function() {
               $('#findbarcoderesults').html('Search failed.  Ajax Error.');
            }
         });
      }
      $('#findbarcodebutton').on('click', findBarcode);
      $('#findbarcode').on('keyup', function(event) {
         if (event.keyCode === 13) { findBarcode(); }
      });
   </script>";

   // Add a new record (or edit an existing one by barcode) outside of a batch.
   echo "<div id='addrecord' style='padding: 0.5em;' >";
   echo "<button type='button' class='ui-button' onclick='dosetupnewrecord(\"\");'>Add/Edit Records</button>";
   echo "</div>";
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
         if (!$height||!$width) {
            // $size = getImageSize($mediauri);
            // $width = $size[0];
            // $height = $size[1];
            // if (!$height) $height = 4344;
            // if (!$width ) $width = 2896;

            $height = 4344;
            $width = 2896;
         }
         $result->mediauri = $mediauri;
         //$mediauri = 'http://nrs.harvard.edu/urn-3:FMUS.HUH:s16-47087-301139-3';
         //$height =  4897;
         //$width  =  3420;
         $s = 400/$height; // scale factor
         $h = round($height*$s);
         $w = round($width*$s);
         $medialink = "";
         //$medialink .= "<a channel.postMessage(\"$barcode\"); '>$acronym $barcode</a>&nbsp; ";
         $medialink .= "<img id='image_div' onclick=' getClick(event,$h,$w,$height,$width,$mediaid);' src='$mediauri' width='$w' height='$h' style='background-color:lightgrey;'></div>";
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
   // list($width,$height) = getimagesize($pathfile);
   // if (!$height) $height = 4344;
   // if (!$width ) $width = 2896;
   $height = 4344;
   $width = 2896;
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
   $medialink = "<img id='image_div' onclick=' getClick(event,$h,$w,$height,$width,$mediaid);' src='$mediauri' width='$w' height='$h' style='background-color:lightgrey;'></div>";
   $result->medialink = $medialink;

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
   case 'newrecord':
      newRecordForm();
      break;
   case 'setup':
      doSetup();
      break;
   case 'logout':
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
/*
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
    global $transcriptionMode, $modeLabel, $imgSrc, $imgSrcLabel;

    echo "<button type='button' onclick='$(\"#cover\").fadeIn(100);   doclear();' class='ui-button' >Restart</button>";
    echo "<button type='button' onclick='ping();' class='ui-button' >Ping</button>";
    echo "<button type='button' id='minimalButton' class='ui-button' >$modeLabel</button>";
    echo "<button type='button' id='imgSrcButton' class='ui-button' >$imgSrcLabel</button>";
    echo "<button type='button' id='jumpButton' class='ui-button' >Jump to:</button><input id='jumpto' type='text' size=4 />";
}

function form() {
   global $user, $transcriptionMode, $imgSrc;

   @$config = substr(preg_replace('/[^a-z]/','',$_GET['config']),0,10);
   @$batchpath = urldecode($_GET['batch']);
   $position = 1;
   @$position= preg_replace('/[^0-9]/','',$_GET['position']);
   if ($position==null || $position=="") { $position = 1;  }

   $currentBatch = new TR_Batch();
   $currentBatch->setPath($batchpath);
   $filecount = $currentBatch->getFileCount();
   $file = $currentBatch->movePosition($position);
   $filepath = $file->path;
   $filename = $file->filename;

   $currentBatch->selectOrCreateUserForBatch();

   # Find out the barcode to load.
   if (strlen($filename)==0) {
      $target = target();
   } else {
      $target = targetfile($filepath,$filename);
   }

   $targetheight = $target->height;
   $targetwidth = $target->width;
   echo "
   <script>
        var enableServerLog = false;

        function logEvent(eventaction,eventdetails){
              if (!enableServerLog) {
                return;
              }

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

      $(document).ready(logEvent('start_transcription','$batchpath, $position'));

      window.onpopstate = function(e){
        var params = new URLSearchParams(window.location.search);
        var position = params.get('position');
        loadRecord(position);
      }
   </script>";

/*
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
   */

   $created = "New Record";
   @$defaultherbarium = substr(preg_replace('/[^A-Z]/','',$_GET['defaultherbarium']),0,5);
   if ($defaultherbarium=='') { $herbarium = "GH"; }
   @$defaultformat = substr(preg_replace('/[^A-Za-z ]/','',$_GET['defaultformat']),0,255);
   if ($defaultformat=='') { $defaultformat = "Sheet"; }
   @$defaultprepmethod = substr(preg_replace('/[^A-Za-z ]/','',$_GET['defaultprepmethod']),0,255);
   if ($defaultprepmethod=='') { $defaultprepmethod = "Pressed"; }
   @$defaultproject = substr(preg_replace('/[^0-9A-Za-z\. \-]/','',$_GET['defaultproject']),0,255);
   if ($defaultproject==null || strlen($defaultproject)==0 ) { $defaultproject = ''; }

   echo "<div class='hfbox' style='height: 1em;'>";
   echo navigation();
   echo "&nbsp;<span id='batch_info'>[$batchpath] file $position of $filecount. </span>";
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
   echo '<div class="flexbox" style="width: 30em;" id="transcribediv">';
   echo '<h3 style="display: none; margin-top: 1px; margin-bottom: 0px;">Transcribe into Fields</h3>';
   echo '<div class="flexbox">';

   echo "<table>";

   echo '<script>
     $(document).ready(function() {
          //channel.postMessage( { x:"355", y:"569", oh:"'.$target->height.'", ow:"'.$target->width.'", h:"700", w:"467", id:"'.$target->imagesetid.'" }  );
          channel.postMessage( { x:"353", y:"614", oh:"'.$target->height.'", ow:"'.$target->width.'", h:"700", w:"467", id:"'.$target->imagesetid.'" }  );
     });
   </script>
   ';

   fieldEnabalable("barcode","Barcode",'','required','[0-9]{8}','','Barcode must be an 8 digit number.','true');   // not zero padded when coming off barcode scanner.
   echo "<input type='hidden' name='barcodeval' id='barcodeval' value=''>"; // to carry submission of barcode with disabled barcode input.
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
           checkSaveButton();
       });
   });
   </script>
   ';

   @selectAcronym("herbariumacronym",$defaultherbarium);
   @field ("accessionnumber","Accession Num.",'','false');
   @selectTaxon("filedundername","Filed Under",'','','true','true');
   @selectTaxon ("currentname","Current Name",'','','true','true');
   @selectQualifier("currentqualifier","ID Qualifier",'');
   //@selectCollectorsID("identifiedby","Identified By",'','','false','false');
   //@field ("dateidentified","Date Identified",'','false','([0-9]{4}(-[0-9]{2}){0,2}){1}(/([0-9]{4}(-[0-9]{2}){0,2}){1}){0,1}','','Use of an ISO format is required: yyyy, yyyy-mm, yyyy-mm-dd, or yyyy-mm-dd/yyyy-mm-dd');
   //@field ("determinertext","Det. Text",'','false');
   @field ("provenance","Provenance",'','false');
   @selectCollectorsID("collectors","Collectors",'','','true','false');
   @field ("etal","Et al.",'','false');
   @field ("stationfieldnumber","Collector Number",'','false');
   @selectSeriesType("seriestype","Series Type",'','false','false');
   @field ("seriesid","Series Id",'','false');
   @field ("datecollected","Date Collected",'','false','([0-9]{4}(-[0-9]{2}){0,2}){1}(/([0-9]{4}(-[0-9]{2}){0,2}){1}){0,1}','','Use of an ISO format is required: yyyy, yyyy-mm, yyyy-mm-dd, or yyyy-mm-dd/yyyy-mm-dd','true');
   @field ("verbatimdate","Verbatim Date",'','false');
   @selectContainerID("container","Container",'','');
   @selectCollectingTripID("collectingtrip","Collecting Trip",'','','false');
   @selectHigherGeography ("geographyfilter","Geography Within",'','','','','false','true');
   @selectHigherGeographyFiltered ("highergeography","Higher Geography",'','','','','true');
   @field ("specificlocality","Verbatim locality",'','true');
   @field ("habitat","Habitat",'');
   @field ("frequency", "Frequency", '');
   @field ("specimendescription","Description",'','false');
   @field ("specimenremarks","Remarks",'','false');
   @selectProject("project","Project",$defaultproject);
   @staticvalueid("Special chars:","° × ± Ø ♀ ♂","specialchars");

   echo "<tr><td colspan=2>";
   echo "<input type='hidden' id='batch_id' name='batch_id' value='".$currentBatch->getBatchID()."' class='carryforward'>";
   echo "<input type='hidden' id='batch_position' name='batch_position' value='".$position."'>";
   echo "<input type='button' value='Save' id='saveButton' class='carryforward ui-button'> ";
   echo "<input type='button' value='Next', id='nextButton' class='carryforward ui-button ui-state-disabled'>";
   echo "<input type='button' value='Done', disabled='true' id='doneButton' class='carryforward ui-button ui-state-disabled'>";
   echo "<input type='button' value='Previous', id='previousButton'  disabled='true' class='carryforward ui-button ui-state-disabled'>";
   echo "</td></tr>";
   //echo "<tr><td colspan=2 style=' font-size: 0.9em;'>";
   //echo "For the autocomplete fields, quickly type a substring (wildcards allowed, e.g. <i>Su%Gray</i>), press the down arrow to make a selection from the picklist, hit enter, then tab out of the field..";
   //echo "Once you have entered a specimen record you must hit Save to save the record before hitting Next or Done.";
   //echo "</td></tr>";

   echo "<script>
        var transcriptionMode = '$transcriptionMode';
        var imgsrc            = '$imgSrc';
        var re_barcode = /^[0-9]{8}$/;
        var batchid = ".$currentBatch->getBatchID().";
        var batchpath = '".$currentBatch->getPath()."';

        soroStates = ['Arizona','Colorado','Kansas','Nebraska','New Mexico','Oklahoma','South Dakota','Texas','Utah','Wyoming'];

        var defaultHideFields = [];
        var soroHideFields = ['accessionnumber'];
        var poeHideFields = ['currentqualifier','container','collectingtrip', 'specimendescription']; // 'specimenremarks'
        // var minimalHideFields = ['currentqualifier','provenance','container','collectingtrip','specimendescription','identifiedby','dateidentified','determinertext','accessionnumber','collectingtrip','habitat','frequency','verbatimelevation','verbatimlat','verbatimlong','decimallat','decimallong','georeferencesource','coordinateuncertainty','specificlocality']; // 'specimenremarks'
        var minimalHideFields = ['currentqualifier','provenance','container','collectingtrip','specimendescription','identifiedby','dateidentified','determinertext','accessionnumber','collectingtrip','habitat','frequency','verbatimelevation','verbatimlat','verbatimlong','decimallat','decimallong','georeferencesource','coordinateuncertainty','specificlocality']; // 'specimenremarks'
        var minimalFields = ['barcode','herbariumacronym','filedundername','currentname','currentqualifier','geographyfilter','highergeography','specimenremarks','project','prepmethod','preptype'];

        // Enable/disable buttons based on position
        checkPosition($position);
        // Set hide/show fields for default project
        //projectConfig();
        checkSoRo();
        // Load record for current position
        loadRecord($position);

        // TODO: clean up dets with bad taxon names

        $('#minimalButton').click(function(event){

            if (transcriptionMode == 'detailed') {
              $('#feedback').html( 'Switched to minimal data capture...');
              $('#minimalButton').html('Minimal');
              transcriptionMode = 'minimal';
              showOnlyFields(minimalFields);
            } else {
              $('#feedback').html( 'Switched to detailed data capture...');
              $('#minimalButton').html('Detailed');
              transcriptionMode = 'detailed';
              hideFields(defaultHideFields);
            }

            var params = new URLSearchParams(window.location.search);
            params.set('mode', transcriptionMode);
            updateLocation(params);

            //projectConfig();

            event.preventDefault();
         });

         $('#imgSrcButton').click(function(event){

             if (imgsrc == 'AWS') {
               $('#feedback').html( 'Switched to RC image delivery...');
               $('#imgSrcButton').html('RC');
               imgsrc = 'RC';
             } else {
               $('#feedback').html( 'Switched to AWS image delivery...');
               $('#imgSrcButton').html('AWS');
               imgsrc = 'AWS';
             }

             var params = new URLSearchParams(window.location.search);
             params.set('imgsrc', imgsrc);
             updateLocation(params);
             event.preventDefault();
          });

         $('#nextButton').click(function(event){
             $('#feedback').html( 'Loading next...');
             logEvent('next_button_click',$('#batch_info').html());
             // clear fields
             $('#image_div').attr('src','data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=');
             $('#transcribeForm  input:not(.carryforward)').val('');
             var params = new URLSearchParams(window.location.search);
             var position = parseInt(params.get('position'));
             movePosition(position+1);
             event.preventDefault();
          });


         $('#previousButton').click(function(event){
             $('#feedback').html( 'Loading previous...');
             logEvent('previous_button_click',$('#batch_info').html());
             // clear fields
             $('#image_div').attr('src','data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=');
             $('#transcribeForm  input:not(.carryforward)').val('');
             var params = new URLSearchParams(window.location.search);
             var position = parseInt(params.get('position'));
             movePosition(position-1);
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

          $('#jumpButton').click(function(event){
             $('#feedback').html( 'Jumping to position...');
             jumpto($('#jumpto').val());
             event.preventDefault();
          });

          $('#jumpto').keyup(function(event) {
              if (event.keyCode === 13) {
                  $('#jumpButton').click();
              }
          });

          function jumpto(position) {
            if (isNaN(position)) return;
            movePosition(position);
          }

          $('#project').on( 'blur', function () {
              checkSoRo();
          });

          $('#project').keyup(function(event) {
            if (event.keyCode === 13) {
                checkSoRo();
            }
          });

          $('#highergeography').on( 'blur', function () {
              checkSoRo();
          });

          $('#highergeography').keyup(function(event) {
            if (event.keyCode === 13) {
                checkSoRo();
            }
          });


          /* Check if higher geography is a SoRo state,
           * if true, set fields to SoRo config
           */
          function checkSoRo() {
              $.ajax( {
                url: 'ajax_handler.php',
                dataType: 'json',
                data: {
                   druid_action: 'returnparentgeographyjson',
                   id: $('#highergeographyid').val(),
                   rank: 300
                },
                success: function( data ) {
                  if (soroStates.includes(data.value)) {
                    //hideFields(soroHideFields);
                    $('#feedback').html( '<mark>SoRo State; Please transcribe all fields</mark>' ) ;
                  } else {
                    //projectConfig();
                  }
                },
                error: function() {
                  $('#feedback').html( 'Failed Ajax call to check for SoRo states.' ) ;
                }
              } );
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

          /* minimal fields
          barcode - known, not editable - on save, go to next in list.
          project - default US and Canada, show
          format - default sheet, show
          preparation method - pressed default, show
          collectioncode - likely (GH, A, NEBC)
          highergeography - pick
          scientific name - filed under, plus qualifier - carry forward
          */

          function projectConfig() {
             var project = $('#project').val();
             switch (project) {
               case 'Plants on Edge/Endless Forms':
                hideFields(poeHideFields);
                break;
               default:
                if (transcriptionMode == 'detailed') {
                  hideFields(defaultHideFields);
                } else {
                  hideFields(minimalHideFields);
                }
             }
          }

          function hideFields(arr) {
            $('.inputField').each(function(){
              var idname = $(this).attr('id');
              if (arr.includes(idname)) {
                //$(this).hide()
                //$('label[for=' + idname + ']').hide();
                $(this).closest('tr').hide();
              } else {
                //$(this).show()
                //$('label[for=' + idname + ']').show();
                $(this).closest('tr').show();
              }
            });
          }

          function showOnlyFields(arr) {
              $('.inputField').each(function(){
                var idname = $(this).attr('id');
                if (arr.includes(idname)) {
                  $(this).closest('tr').show();
                } else {
                  $(this).closest('tr').hide();
                }
              });
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

              // set field to value, unless carryover field and value is null/empty
              if($('.carryforward[id][name='+field+']').length && (value==null || value=='')) {
                $('#'+field).css({'color':'darksalmon'});
              } else {
                $('#'+field).css({'color':'black'});
                $('#'+field).val(value);
              }

          }

          /* Update UI elements based on the position in the batch
           */
          function checkPosition(position) {

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

          }

          function loadFormData(data) {
              console.log('called loadFormData()');

              var barcodeval = data.barcode;
              if (data.barcode==null || data.barcode=='NOTFOUND' || data.barcode=='00000000') {
                  barcodeval = '';
              }

              if (! data.collectors && ! data.collectoragentid) {
                data.collectors = '[data not captured]';
                data.collectoragentid = 97236;
              }

              if (! data.specificlocality) {
                data.specificlocality = '[data not captured]';
              }

              $('#barcode').val(barcodeval);
              if ($('#barcode').val().length==0) {
                  $('#barcode').prop('disabled', null);
                  $('#recordcreated').html('New Record');
                  $('#feedback').html( 'Ready.');
              } else {
                  $('#barcode').prop('disabled', true);
                  $('#recordcreated').html(data.created);
                  setLoadedValue('container',data.container);
                  setLoadedValue('containerid',data.containerid);
                  setLoadedValue('prepmethod',data.prepmethod);
                  setLoadedValue('preptype',data.format);
                  setLoadedValue('accessionnumber',data.accessionnumber);
                  setLoadedValue('filedundername',data.filedundername);
                  setLoadedValue('filedundernameid',data.filedundernameid);
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
                  setLoadedValue('iscultivated',data.iscultivated);
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
                  setLoadedValue('seriesid',data.seriesid);
                  setLoadedValue('seriestype',data.seriestype);

                  $('#feedback').html( data.barcode + ' Loaded. Ready.' + data.error);
              }

              checkSaveButton();
          }

          function loadImage(data) {
            console.log('called loadImage(): ' + data.mediauri);
            $('#image_div').attr('src','data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=');
            $('#image_div').attr('src',data.mediauri);

            var imagesource = data.mediauri;
            var imagepath = data.path;
            var imagefilename = data.filename;
            var position = data.position;
            var filecount = data.filecount;
            channel.postMessage(  { action:'load', origheight:'$targetheight', origwidth:'$targetwidth', uri: imagesource, path: imagepath, filename: imagefilename }  );
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

          function updateLocation(params) {
            window.history.replaceState({}, '', window.location.origin + decodeURI(`\${location.pathname}?\${params}`));
          }
          function pushLocation(params) {
            window.history.pushState({}, '', window.location.origin + decodeURI(`\${location.pathname}?\${params}`));
          }

          function movePosition(position) {
            checkPosition(position);

            var params = new URLSearchParams(window.location.search);
            params.set('position', position);
            pushLocation(params);

            loadRecord(position);
          }

          function loadRecord(position) {
               console.log('called loadRecord() for batch ' + batchid + ' position ' + position + ' imgsrc ' + imgsrc);

                $.ajax({
                   type: 'GET',
                   url: 'transcribe_handler.php',
                   dataType: 'json',
                   data: {
                       action: 'getrecord',
                       batch_id: batchid,
                       position: position,
                       imgsrc: imgsrc
                   },
                   success: function(data) {
                     console.log(data);
                     loadFormData(data);
                     $('#batch_position').val(data.position);
                     $('#batch_info').html('[' + batchpath + '] file ' + data.position +' of $filecount.');
                     loadImage(data);
                     checkSoRo();
                   },
                   error: function() {
                      console.log('ajax call to transcribe_handler.php/action=getrecord failed');
                      $('#feedback').html( 'Failed.  Ajax Error.  Barcode: ' + ($('#barcode').val()) ) ;
                   }
               });
          }


   </script>";

   echo "<script>
         $('#saveButton').click(function(event){
               $('#saveButton').attr('disabled', true).addClass('ui-state-disabled');
               // handle disabled fields, copy data to val fields.
               $('#barcodeval').val($('#barcode').val());
               $('#feedback').html( 'Submitting: ' + ($('#barcode').val()) ) ;
               $.ajax({
                   type: 'POST',
                   url: 'transcribe_handler.php',
                   data: $('#transcribeForm').serialize(),
                   success: function(data) {
                       $('#feedback').html( data ) ;
                       checkSaveButton();
                   },
                   error: function(jqXHR, textStatus, errorThrown) {
                       $('#feedback').html('Ajax Error: ' +  textStatus + '; ' + errorThrown) ;
                       checkSaveButton();
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
   echo '<h3 style="display: none; margin-top: 1px; margin-bottom: 0px;">Click to zoom in other window.</h3>';
   echo '<div>';

   echo '<div class=flexbox>';
    $medialink = $target->medialink;
    echo "
        <script>
           channel.onmessage = function (e) {
               console.log(e);
               if (e.data=='loaded') {
                  logEvent('image_loaded',$('#batch_info').html())
                  // $('#feedback').html( 'Image Loaded');
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
   selectPrepMethod("prepmethod","Prep Method",$defaultprepmethod,'true','true');
   selectPrepType("preptype","Format",$defaultformat,'true','true');
   @selectCultivated("iscultivated");
   echo '</table>';
   echo '</div>';
   echo '</div>';


   $bak_basewidth = $GLOBALS['BASEWIDTH'];
   $GLOBALS['BASEWIDTH'] = 11;

   echo '<div id="geofieldsdiv" style="margin-top: 5px;">';
   echo '<h3 style="display: none; margin-top: 1px; margin-bottom: 0px;">Geodata fields</h3>';
   echo '<div>';
   echo '<table>';
   @field ("verbatimelevation","Verb. Elev.",'','false');
   @field ("verbatimlat","Verb. Lat.",'');
   @field ("verbatimlong","Verb. Long.",'');
   @field ("decimallat","Dec. Lat.",'','false','\-?[0-9]{1,2}(\.{1}[0-9]*)?');
   @field ("decimallong","Dec. Long.",'','false','\-?[0-1]?[0-9]{1,2}(\.{1}[0-9]*)?');
   @field ("georeferencesource",'Method','','false');
   //field ("datum","Datum",$datum); // almost never encountered on a label
   @field ("coordinateuncertainty","Uncertainty",'','false','[0-9]*');
   //@selectCollectorsID("georeferencedby","Georef. By",$georeferencedby,$georeferencedbyid,'false','false'); // This might only make sense in the data model for post-hoc georeferencing
   //@field ("dategeoreferenced","Georef. Date",$dategeoreferenced,'false','([0-9]{4}(-[0-9]{2}){0,2}){1}(/([0-9]{4}(-[0-9]{2}){0,2}){1}){0,1}','','Use of an ISO format is required: yyyy, yyyy-mm, yyyy-mm-dd, or yyyy-mm-dd/yyyy-mm-dd'); // doesn't make sense for label transcription, should be used for post-hoc georeferencing
   //utm($utmzone, $utmeasting, $utmnorthing); // rarely encountered during transcription
   echo '</table>';
   echo '</div>';
   echo '</div>';

   $GLOBALS['BASEWIDTH'] = $bak_basewidth;


   echo "</form>\n";

   echo '</div>'; //end rightside

   echo '</div>';

}

/** Display a form for adding a new record (or editing an existing record) by barcode,
 *  outside of the context of a transcription batch.  Modeled on form().
 *
 *  The form starts blank.  When a barcode is entered, any existing record for that barcode
 *  is loaded into the form, and any images for that barcode (linked to the specimen through
 *  IMAGE_SET_collectionobject, or found in TR_BATCH_IMAGE) are offered for display.
 */
function newRecordForm() {
   global $user, $imgSrc;

   @$startbarcode = substr(preg_replace('/[^0-9]/','',$_GET['barcode']),0,20);

   @$defaultherbarium = substr(preg_replace('/[^A-Z]/','',$_GET['defaultherbarium']),0,5);
   @$defaultformat = substr(preg_replace('/[^A-Za-z ]/','',$_GET['defaultformat']),0,255);
   if ($defaultformat=='') { $defaultformat = "Sheet"; }
   @$defaultprepmethod = substr(preg_replace('/[^A-Za-z ]/','',$_GET['defaultprepmethod']),0,255);
   if ($defaultprepmethod=='') { $defaultprepmethod = "Pressed"; }
   @$defaultproject = substr(preg_replace('/[^0-9A-Za-z\. \-]/','',$_GET['defaultproject']),0,255);
   if ($defaultproject==null || strlen($defaultproject)==0 ) { $defaultproject = ''; }

   // values passed to the javascript below
   $config = array(
      'startbarcode' => $startbarcode,
      'imgsrc' => $imgSrc,
      'username' => $_SESSION["username"],
      'defaultheight' => 4344,
      'defaultwidth' => 2896
   );

   echo "<div class='hfbox' style='height: 1em;'>";
   echo "<button type='button' onclick='doclear();' class='ui-button' >Back to Batches</button>";
   echo "<button type='button' id='newBlankButton' class='ui-button' >New Blank Record</button>";
   echo "<button type='button' id='openImageWindowButton' class='ui-button' >Open Image Window</button>";
   echo "<button type='button' onclick='ping();' class='ui-button' >Ping</button>";
   echo "&nbsp;<span id='record_info'>Add or edit a record by barcode.</span>";
   echo "</div>";
   echo "</div>";

   echo "<div class='flex-main hfbox' style='min-width: 1000px; padding: 0em;'>";
   echo "<form action='transcribe_handler.php' method='POST' id='transcribeForm' autocomplete='off' >\n";

   echo "<input type=hidden name='action' value='transcribe' class='carryforward fixedvalue'>";
   echo "<input type=hidden name='operator' value='".$user->getAgentId()."' class='carryforward fixedvalue'>";
   // Not working in a batch: empty batch id and position mean no TR_BATCH_IMAGE record is updated on save.
   echo "<input type='hidden' id='batch_id' name='batch_id' value='' class='carryforward fixedvalue'>";
   echo "<input type='hidden' id='batch_position' name='batch_position' value='' class='carryforward fixedvalue'>";
   echo '<script>
         $( function(){
            $("#transcribediv").accordion( { heightStyle: "fill" } ) ;
          });
   </script>';
   echo '<div style="display: inline-block; float: left; margin-right: 5px;" id="leftside">';
   echo '<div class="flexbox" style="width: 30em;" id="transcribediv">';
   echo '<h3 style="display: none; margin-top: 1px; margin-bottom: 0px;">Transcribe into Fields</h3>';
   echo '<div class="flexbox">';

   echo "<table>";

   @field("barcode","Barcode",'','required','[0-9]{1,8}','','Barcode must be a 1 to 8 digit number.','true');
   echo "<input type='hidden' name='barcodeval' id='barcodeval' value='' class='fixedvalue'>";

   @selectAcronym("herbariumacronym",$defaultherbarium);
   @field ("accessionnumber","Accession Num.",'','false');
   @selectTaxon("filedundername","Filed Under",'','','true','false');
   @selectTaxon ("currentname","Current Name",'','','true','false');
   @selectQualifier("currentqualifier","ID Qualifier",'');
   @field ("provenance","Provenance",'','false');
   @selectCollectorsID("collectors","Collectors",'','','true','false');
   @field ("etal","Et al.",'','false');
   @field ("stationfieldnumber","Collector Number",'','false');
   @selectSeriesType("seriestype","Series Type",'','false','false');
   @field ("seriesid","Series Id",'','false');
   @field ("datecollected","Date Collected",'','false','([0-9]{4}(-[0-9]{2}){0,2}){1}(/([0-9]{4}(-[0-9]{2}){0,2}){1}){0,1}','','Use of an ISO format is required: yyyy, yyyy-mm, yyyy-mm-dd, or yyyy-mm-dd/yyyy-mm-dd','true');
   @field ("verbatimdate","Verbatim Date",'','false');
   @selectContainerID("container","Container",'','');
   @selectCollectingTripID("collectingtrip","Collecting Trip",'','','false');
   @selectHigherGeography ("geographyfilter","Geography Within",'','','','','false','false');
   @selectHigherGeographyFiltered ("highergeography","Higher Geography",'','','','','true');
   @field ("specificlocality","Verbatim locality",'','true');
   @field ("habitat","Habitat",'');
   @field ("frequency", "Frequency", '');
   @field ("specimendescription","Description",'','false');
   @field ("specimenremarks","Remarks",'','false');
   @selectProject("project","Project",$defaultproject);
   @staticvalueid("Special chars:","° × ± Ø ♀ ♂","specialchars");

   echo "<tr><td colspan=2>";
   echo "<input type='button' value='Save' id='saveButton' class='carryforward ui-button ui-state-disabled' disabled='true'> ";
   echo "</td></tr>";

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

   // Selected image, click to zoom in the image window (as in form()).
   echo '<div id="imagezoomdiv" >';
   echo '<h3 style="display: none; margin-top: 1px; margin-bottom: 0px;">Click to zoom in other window.</h3>';
   echo '<div>';
   echo '<div class=flexbox>';
   echo "<div id='noimagemessage' style='width: 265px; height: 400px; background-color: lightgrey; display: flex; align-items: center; justify-content: center; text-align: center;'>Enter a barcode to find images.</div>";
   echo "<img id='image_div' src='data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=' height='400' style='background-color:lightgrey; display: none; cursor: crosshair;'>";
   // one short line under the image: where the image came from, and a link to choose among other images.
   echo "<div id='imageinfo' style='font-size: 0.8em; max-width: 30em; padding-top: 2px;'></div>";
   echo '</div>';
   echo '</div>';
   echo '</div>';

   echo '<div id="extrafieldsdiv" style="margin-top: 5px;">';
   echo '<h3 style="display: none; margin-top: 1px; margin-bottom: 0px;">Additional fields</h3>';
   echo '<div>';
   echo '<table>';
   @staticvalueid("Record Created:","New Record","recordcreated");
   selectPrepMethod("prepmethod","Prep Method",$defaultprepmethod,'true','false');
   selectPrepType("preptype","Format",$defaultformat,'true','false');
   @selectCultivated("iscultivated");
   echo '</table>';
   echo '</div>';
   echo '</div>';

   $bak_basewidth = $GLOBALS['BASEWIDTH'];
   $GLOBALS['BASEWIDTH'] = 11;
   echo '<div id="geofieldsdiv" style="margin-top: 5px;">';
   echo '<h3 style="display: none; margin-top: 1px; margin-bottom: 0px;">Geodata fields</h3>';
   echo '<div>';
   echo '<table>';
   @field ("verbatimelevation","Verb. Elev.",'','false');
   @field ("verbatimlat","Verb. Lat.",'');
   @field ("verbatimlong","Verb. Long.",'');
   @field ("decimallat","Dec. Lat.",'','false','\-?[0-9]{1,2}(\.{1}[0-9]*)?');
   @field ("decimallong","Dec. Long.",'','false','\-?[0-1]?[0-9]{1,2}(\.{1}[0-9]*)?');
   @field ("georeferencesource",'Method','','false');
   @field ("coordinateuncertainty","Uncertainty",'','false','[0-9]*');
   echo '</table>';
   echo '</div>';
   echo '</div>';
   $GLOBALS['BASEWIDTH'] = $bak_basewidth;

   echo "</form>\n";
   echo '</div>'; //end rightside
   echo '</div>';

   // Layover for choosing among several images for the barcode.
   echo "<div id='imagepickeroverlay' style='display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); z-index: 1000; overflow: auto;'>";
   echo "<div style='background: white; margin: 2em auto; width: 90%; padding: 1em; border-radius: 4px;'>";
   echo "<div style='display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.5em;'>";
   echo "<strong id='imagepickertitle'>Choose an image</strong>";
   echo "<button type='button' id='imagepickerclose' class='ui-button'>Close</button>";
   echo "</div>";
   echo "<div id='imagepickertiles' style='display: flex; flex-wrap: wrap; gap: 10px;'></div>";
   echo "</div>";
   echo "</div>";

   echo "<script>var newRecordConfig = ".json_encode($config).";</script>\n";
   echo <<<'EOT'
<script>
   var re_barcode = /^[0-9]{1,8}$/;
   var initialValues = {};     // field id -> value when the page was loaded (the blank form)
   var currentImages = [];     // images for the current barcode, from getimagesforbarcode
   var selectedImage = null;   // the image currently displayed
   var imageWindow = null;     // the zoom window
   var lastLookedUpBarcode = null;

   function escapeHtml(value) {
      return String(value == null ? '' : value).replace(/[&<>"']/g, function(c) {
         return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c];
      });
   }

   function padBarcode(value) {
      var digits = String(value).replace(/[^0-9]/g,'');
      if (digits.length==0 || digits.length>8) { return digits; }
      return ('00000000' + digits).slice(-8);
   }

   /* Record the initial (blank/default) value of each record field so the form can be reset. */
   function storeInitialValues() {
      $('#transcribeForm input[id], #transcribeForm select[id]').not('.fixedvalue').not('[type=button]').each(function() {
         initialValues[$(this).attr('id')] = $(this).val();
      });
   }

   /* Reset all record fields (except optionally the barcode) to their initial values. */
   function resetRecordFields(keepBarcode) {
      $.each(initialValues, function(id, value) {
         if (keepBarcode && id=='barcode') { return; }
         $('#'+id).val(value).css({'color':'black'});
      });
      $('#recordcreated').html('New Record');
   }

   function setLoadedValue(field,value) {
      if (value==null) { value = ''; }
      $('#'+field).css({'color':'black'});
      $('#'+field).val(value);
   }

   /* Load data from an existing record (getdataforbarcode response) into the form.  Mirrors loadFormData() in the batch form. */
   function loadFormData(data) {
      if (! data.collectors && ! data.collectoragentid) {
         data.collectors = '[data not captured]';
         data.collectoragentid = 97236;
      }
      if (! data.specificlocality) {
         data.specificlocality = '[data not captured]';
      }
      $('#recordcreated').html(escapeHtml(data.created));
      setLoadedValue('container',data.container);
      setLoadedValue('containerid',data.containerid);
      setLoadedValue('prepmethod',data.prepmethod);
      setLoadedValue('preptype',data.format);
      setLoadedValue('accessionnumber',data.accessionnumber);
      setLoadedValue('filedundername',data.filedundername);
      setLoadedValue('filedundernameid',data.filedundernameid);
      setLoadedValue('currentname',data.currentname);
      setLoadedValue('currentnameid',data.currentnameid);
      setLoadedValue('currentqualifier',data.currentqualifier);
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
      if (data.herbariumacronym) { setLoadedValue('herbariumacronym',data.herbariumacronym); }
      setLoadedValue('iscultivated',data.iscultivated==null ? '0' : data.iscultivated);
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
      setLoadedValue('seriesid',data.seriesid);
      setLoadedValue('seriestype',data.seriestype);
   }

   function checkSaveButton() {
      var barcode = $('#barcode').val();
      if (re_barcode.test(barcode) && barcode==lastLookedUpBarcode) {
         $('#saveButton').attr('disabled', false).removeClass('ui-state-disabled');
      } else {
         $('#saveButton').attr('disabled', true).addClass('ui-state-disabled');
      }
   }

   function updateLocationBarcode(barcode) {
      var params = new URLSearchParams(window.location.search);
      params.set('display', 'newrecord');
      if (barcode) { params.set('barcode', barcode); } else { params.delete('barcode'); }
      window.history.replaceState({}, '', window.location.pathname + '?' + params.toString());
   }

   /* Look up an existing record and images for a barcode. */
   function lookupBarcode(rawvalue) {
      var barcode = padBarcode(rawvalue);
      if (!re_barcode.test(barcode)) {
         $('#feedback').html('Barcode must be a 1 to 8 digit number.');
         lastLookedUpBarcode = null;
         checkSaveButton();
         return;
      }
      if (barcode==lastLookedUpBarcode) { return; }
      $('#barcode').val(barcode);
      $('#feedback').html('Looking up ' + barcode + '...');
      $.ajax({
         type: 'GET',
         url: 'transcribe_handler.php',
         dataType: 'json',
         data: { action: 'getdataforbarcode', barcode: barcode },
         success: function(data) {
            lastLookedUpBarcode = barcode;
            updateLocationBarcode(barcode);
            if (data.preparation_matches > 0) {
               // Barcode belongs to a preparation (unbarcoded item): saving here would be refused.
               resetRecordFields(true);
               lastLookedUpBarcode = null;  // do not allow saving
               $('#record_info').html('<strong>Edit ' + escapeHtml(barcode) + ' in Specify.</strong>');
               $('#feedback').html('<mark><strong>Warning:</strong> Barcode ' + escapeHtml(barcode) + ' is on a preparation (unbarcoded item) in the database. Edit this record in Specify; it cannot be saved from this form.</mark>');
            } else if (data.num_matches == 1) {
               resetRecordFields(true);
               loadFormData(data);
               $('#record_info').html('Editing existing record ' + escapeHtml(barcode) + '.');
               $('#feedback').html(escapeHtml(barcode) + ' Loaded. Ready.' + escapeHtml(data.error));
            } else if (data.num_matches > 1) {
               resetRecordFields(true);
               lastLookedUpBarcode = null;  // do not allow saving
               $('#record_info').html('Multiple records for ' + escapeHtml(barcode) + '.');
               $('#feedback').html('<strong>Warning:</strong> more than one match for Barcode: ' + escapeHtml(barcode) + ' Enter in Specify.');
            } else {
               resetRecordFields(true);
               $('#record_info').html('New record ' + escapeHtml(barcode) + '.');
               $('#feedback').html('No existing record for ' + escapeHtml(barcode) + '. Ready to add a new record.');
            }
            checkSaveButton();
         },
         error: function() {
            lastLookedUpBarcode = null;
            checkSaveButton();
            $('#feedback').html('Failed.  Ajax Error.  Barcode: ' + escapeHtml(barcode));
         }
      });
      loadImagesForBarcode(barcode);
   }

   /* Find images for a barcode; display the most recent one, and offer the rest in the image picker. */
   function loadImagesForBarcode(barcode) {
      currentImages = [];
      showImage(null);
      $('#noimagemessage').html('Searching for images...');
      $.ajax({
         type: 'GET',
         url: 'transcribe_handler.php',
         dataType: 'json',
         data: { action: 'getimagesforbarcode', barcode: barcode, imgsrc: newRecordConfig.imgsrc },
         success: function(data) {
            if ($('#barcode').val()!=barcode) { return; }  // barcode changed while waiting
            if (data.error) {
               $('#noimagemessage').html('Error: ' + escapeHtml(data.error));
               return;
            }
            currentImages = data.images;  // sorted newest first by the server
            if (currentImages.length>0) {
               showImage(0);
            } else {
               $('#noimagemessage').html('No images found for this barcode.');
            }
         },
         error: function() {
            $('#noimagemessage').html('Image lookup failed.  Ajax Error.');
         }
      });
   }

   /* Describe where an image came from, with links to edit it in its batch(es). */
   function imageSourceHtml(index) {
      var img = currentImages[index];
      var sources = [];
      if (img.linked) { sources.push('Linked to record'); }
      for (var j=0; j<img.batches.length; j++) {
         var b = img.batches[j];
         sources.push('Batch ' + escapeHtml(b.batch_path) + ' position ' + escapeHtml(b.position) +
            " <a href='#' class='openinbatch' data-image='" + index + "' data-batch='" + j + "'>[edit in batch]</a>");
      }
      if (img.create_date) { sources.push('Added ' + escapeHtml(String(img.create_date).substring(0,10))); }
      return sources.join('; ');
   }

   /* Display an image (index into currentImages, or null to clear) and send it to the image window. */
   function showImage(index) {
      if (index==null || index>=currentImages.length) {
         selectedImage = null;
         $('#image_div').attr('src','data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=').hide();
         $('#noimagemessage').html('Enter a barcode to find images.').show();
         $('#imageinfo').html('');
         return;
      }
      selectedImage = currentImages[index];
      var h = 400;
      var w = Math.round(selectedImage.pixel_width * (400/selectedImage.pixel_height));
      $('#noimagemessage').hide();
      $('#image_div').attr('src','data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs=');
      $('#image_div').attr({ 'src': selectedImage.url, 'height': h, 'width': w }).show();
      var info = '<div>' + imageSourceHtml(index) + '</div>';
      var others = currentImages.length - 1;
      if (others > 0) {
         info += "<div><a href='#' id='moreimageslink'>" + others + ' more image' + (others==1 ? '' : 's') + ' available to choose from</a></div>';
      }
      $('#imageinfo').html(info);
      $('#moreimageslink').on('click', function(event) { event.preventDefault(); openImagePicker(); });
      sendImageToWindow();
   }

   /* Show a layover with a tiled view of all images for the barcode; clicking one selects it. */
   function openImagePicker() {
      var html = '';
      for (var i=0; i<currentImages.length; i++) {
         var img = currentImages[i];
         var current = (img===selectedImage);
         html += '<div class="imagepickertile" data-index="' + i + '" style="width: 220px; padding: 6px; border: 3px solid ' + (current ? 'steelblue' : '#ddd') + '; border-radius: 4px; cursor: pointer; font-size: 0.8em; background: white;">';
         html += '<img src="' + escapeHtml(img.thumbnail) + '" style="display: block; height: 300px; max-width: 220px; margin: 0 auto; background-color: lightgrey;" alt="image ' + (i+1) + '">';
         html += '<div style="padding-top: 4px;">' + (current ? '<strong>Currently displayed.</strong> ' : '') + imageSourceHtml(i) + '</div>';
         html += '</div>';
      }
      $('#imagepickertitle').html('Choose an image for ' + escapeHtml($('#barcode').val()) + ' (' + currentImages.length + ' available)');
      $('#imagepickertiles').html(html);
      $('.imagepickertile').on('click', function(event) {
         if ($(event.target).hasClass('openinbatch')) { return; }
         closeImagePicker();
         showImage($(this).data('index'));
      });
      $('#imagepickeroverlay').fadeIn(100);
   }

   function closeImagePicker() {
      $('#imagepickeroverlay').fadeOut(100);
   }

   /* Send the selected image to the magnified image window.  The window may still be loading
    * (it is opened just before this page), so resend until it reports the image has loaded.
    */
   var imageWindowAcknowledged = false;
   var imageWindowRetries = 0;
   function sendImageToWindow() {
      if (selectedImage==null) { return; }
      imageWindowAcknowledged = false;
      imageWindowRetries = 0;
      postImageToWindow();
   }
   function postImageToWindow() {
      if (selectedImage==null || imageWindowAcknowledged) { return; }
      var path = '';
      var filename = '';
      if (selectedImage.batches.length>0) {
         path = selectedImage.batches[0].path;
         filename = selectedImage.batches[0].filename;
      }
      channel.postMessage( { action:'load', origheight: String(selectedImage.pixel_height), origwidth: String(selectedImage.pixel_width), uri: selectedImage.url, path: path, filename: filename } );
      imageWindowRetries++;
      if (imageWindowRetries < 6) { setTimeout(postImageToWindow, 1500); }
   }

   /* Reopen the magnified image window if it was closed. */
   function openImageWindow() {
      window.open('displayimage.php?mode=imagefile&imgsrc=' + encodeURIComponent(newRecordConfig.imgsrc) + '&path=&filename=', '_blank', 'modal=yes');
      sendImageToWindow();
   }

   /* Links to work on an image in its batch: close this form's image window first, dosetuppath opens a new one. */
   $(document).on('click', '.openinbatch', function(event) {
      event.preventDefault();
      event.stopPropagation();
      var b = currentImages[$(this).data('image')].batches[$(this).data('batch')];
      channel.postMessage('close');
      dosetuppath(encodeURIComponent(b.batch_path), encodeURIComponent(b.path), encodeURIComponent(b.filename), b.position, 'standard');
   });

   $('#imagepickerclose').on('click', function(event) { event.preventDefault(); closeImagePicker(); });
   $('#imagepickeroverlay').on('click', function(event) { if (event.target===this) { closeImagePicker(); } });
   $(document).on('keyup', function(event) { if (event.keyCode === 27) { closeImagePicker(); } });

   /* Clicks on the displayed image zoom the image window (same message as getClick() in the batch form). */
   $('#image_div').on('click', function(event) {
      if (selectedImage==null) { return; }
      var h = $('#image_div').height();
      var w = $('#image_div').width();
      channel.postMessage( { x: event.offsetX, y: event.offsetY, h: h, w: w, oh: selectedImage.pixel_height, ow: selectedImage.pixel_width, id: selectedImage.image_set_id } );
   });

   channel.onmessage = function (e) {
      console.log(e);
      if (e.data=='loaded') {
         // image window finished loading an image, zoom onto the label area as the batch form does.
         imageWindowAcknowledged = true;
         if (selectedImage!=null) {
            channel.postMessage( { x:'353', y:'614', oh: String(selectedImage.pixel_height), ow: String(selectedImage.pixel_width), h:'700', w:'467', id:'' } );
         }
      }
   };

   $('#barcode').on('blur', function () { lookupBarcode($('#barcode').val()); });
   $('#barcode').on('keyup', function (event) {
      if (event.keyCode === 13) { lookupBarcode($('#barcode').val()); }
      else if ($('#barcode').val()!=lastLookedUpBarcode) { checkSaveButton(); }
   });
   $('#barcode').on('invalid', function () { this.setCustomValidity('Barcode must be a 1 to 8 digit number.'); });
   $('#barcode').on('input', function () { this.setCustomValidity(''); });

   $('#openImageWindowButton').on('click', function(event) { openImageWindow(); event.preventDefault(); });

   $('#newBlankButton').on('click', function(event) {
      resetRecordFields(false);
      lastLookedUpBarcode = null;
      currentImages = [];
      showImage(null);
      $('#record_info').html('Add or edit a record by barcode.');
      $('#feedback').html('Ready.');
      updateLocationBarcode('');
      checkSaveButton();
      $('#barcode').focus();
      event.preventDefault();
   });

   $('#saveButton').on('click', function(event){
      event.preventDefault();
      var barcode = $('#barcode').val();
      if (!re_barcode.test(barcode) || barcode!=lastLookedUpBarcode) {
         $('#feedback').html('Enter a barcode and tab out of the field before saving.');
         return;
      }
      $('#saveButton').attr('disabled', true).addClass('ui-state-disabled');
      $('#barcodeval').val(barcode);
      $('#feedback').html('Submitting: ' + escapeHtml(barcode));
      $.ajax({
         type: 'POST',
         url: 'transcribe_handler.php',
         data: $('#transcribeForm').serialize(),
         success: function(data) {
            $('#feedback').html( data );
            checkSaveButton();
         },
         error: function(jqXHR, textStatus, errorThrown) {
            $('#feedback').html('Ajax Error: ' +  escapeHtml(textStatus) + '; ' + escapeHtml(errorThrown));
            checkSaveButton();
         }
      });
   });

   $(document).ready(function() {
      storeInitialValues();
      checkSaveButton();
      if (newRecordConfig.startbarcode) {
         $('#barcode').val(newRecordConfig.startbarcode);
         lookupBarcode(newRecordConfig.startbarcode);
      } else {
         $('#barcode').focus();
      }
   });
</script>
EOT;
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
      echo "<input id=$name name=$name class='inputField' value='$default' $regex $placeholder $validationmessage  style='width: ".$GLOBALS['BASEWIDTH']."em; ' $disabled >";
   } else {
      if ($validationmessage!='') {
         $validationmessage = "validationMessage='Required Field. $validationmessage'";
      }
      echo "<input id=$name name=$name class='inputField' value='$default' required='$required' $regex $placeholder $validationMessage  style='width: ".$GLOBALS['BASEWIDTH']."em; ' $disabled >";
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
      echo "<input id=$name name=$name class='inputField' value='$default' $regex $placeholder $validationmessage  style='width: ".$width."em; ' $disabled >";
   } else {
      if ($validationmessage!='') {
         $validationmessage = "validationMessage='Required Field. $validationmessage'";
      }
      echo "<input id=$name name=$name class='inputField' value='$default' required='$required' $regex $placeholder $validationmessage  style='width: ".$width."em; ' $disabled >";
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
   if ($carryforward=='true') { $carryforward = "carryforward"; } else { $carryforward=""; }
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
        <input id='$field' name='$field' value='$default'  style='width: 9em; ' class='inputField $carryforward' >
     </div>
  </td></tr>
   ";
   echo $returnvalue;
}

function selectPrepType($field,$label,$default,$required='true',$carryforward='true') {
   if ($carryforward=='true') { $carryforward = "carryforward"; } else { $carryforward=""; }
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
        <input id='$field' name='$field' value='$default'  style='width: 9em; ' class='inputField $carryforward' >
     </div>
  </td></tr>
   ";
   echo $returnvalue;
}

function selectSeriesType($field,$label,$default,$required='false',$carryforward='false') {
   if ($carryforward=='true') { $carryforward = "carryforward"; } else { $carryforward=""; }
   $returnvalue = "
  <script>
  $( function() {
    $( '#$field' ).autocomplete({
      source: 'ajax_handler.php?druid_action=returndistinctseriestype',
      minLength: 0,
      delay: 500,
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
        <input id='$field' name='$field' value='$default'  style=' width: ". $GLOBALS['BASEWIDTH'] ."em; ' class='inputField $carryforward' >
     </div>
  </td></tr>
   ";
   echo $returnvalue;
}


function utm($utmzonedefault='', $utmeastingdefault='', $utmnorthingdefault='') {
	echo "<tr><td>\n";
	echo "<label for='utmzone'>UTM Zone,Easting,Northing</label>";
	echo "</td><td>\n";
	echo "<input name=utmzone class='inputField' value=$utmzonedefault dojoType='dijit.form.ValidationTextBox' required='false' regExp='[0-9]+[A-Z]' style='width: 4em;' >";
	echo "<input name=utmeasting class='inputField' value=$utmeastingdefault dojoType='dijit.form.ValidationTextBox' required='false' regExp='[0-9]+' style='width: 8em;' >";
	echo "<input name=utmnorthing class='inputField' value=$utmnorthingdefault dojoType='dijit.form.ValidationTextBox' required='false' regExp='[0-9]+' style='width: 8em;' >";
   echo "</td></tr>\n";
}

function preptypeselect($name,$label,$default,$required,$storeId,$table,$field) {
   $returnvalue = "<tr><td><div dojoType='dojo.data.ItemFileReadStore' jsId='$storeId'
	 url='ajax_handler.php?druid_action=returndistinctjsonpreptype&table=$table&field=$field'> </div>";
   $returnvalue .= "<label for=\"$name\">$label</label></td><td>
	<input type=text name=$name id=$name class='inputField' dojoType='dijit.form.FilteringSelect'
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
   if ($carryforward=='true') { $carryforward = "carryforward"; } else { $carryforward=""; }
   $returnvalue .= "<label for=\"$field\">$label</label></td><td>
    <input type=text name=$field id=$field $req value='$value' style=' width: ".$GLOBALS['BASEWIDTH']."em; $style ' class='inputField $carryforward'  >
    <input type=hidden name=$fieldid id=$fieldid $req value='$valueid' class='$carryforward' >
    </td></tr>";
   $returnvalue .= '
      <script>
         $(function() {
            $( "#'.$field.'" ).autocomplete({
               minLength: 3,
               delay: 500,
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
    <input type=text name=$field id=$field $req class='inputField' value='$value' style=' width: ". $GLOBALS['BASEWIDTH'] ."em; ' >
    <input type=hidden name=$fieldid id=$fieldid required='$required'  value='$valueid' >
    </td></tr>";
   $returnvalue .= '
      <script>
         $(function() {
            $( "#'.$field.'" ).autocomplete({
               minLength: 3,
               delay: 500,
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
	<input type=text name=$name id=$name class='inputField' dojoType='dijit.form.FilteringSelect'
	store='$storeId' required='$required'
	searchAttr='name' value='$default' style=' width: ". $GLOBALS['BASEWIDTH'] ."em; '></td></tr>";
   echo $returnvalue;
}

function selectYesNo($field,$label) {
	echo "<tr><td>\n";
	echo "<label for='$field'>$label</label>";
	echo "</td><td>\n";
	echo '<select name="'.$field.'" class="inputField">
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
	echo '<select name="'.$field.'" id="'.$field.'" class="inputField">';
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
   echo "<label for='$field'>Herbarium</label>";
   echo "</td><td>\n";
   if ($default=="GH") { $ghs = 'selected="selected"'; $as = ""; $fhs = ""; $amess=""; $econs=""; $nebcs=""; }
   if ($default=="A") { $ghs = ""; $as = 'selected="selected"'; $fhs = ""; $amess=""; $econs=""; $nebcs=""; }
   if ($default=="FH") { $ghs = ""; $as = ""; $fhs = 'selected="selected"'; $amess=""; $econs=""; $nebcs=""; }
   if ($default=="AMES") { $ghs = ""; $as = ""; $fhs = ""; $amess='selected="selected"'; $econs=""; $nebcs=""; }
   if ($default=="ECON") { $ghs = ""; $as = ""; $fhs = ""; $amess=""; $econs='selected="selected"'; $nebcs=""; }
   if ($default=="NEBC") { $ghs = ""; $as = ""; $fhs = ""; $amess=""; $econs=""; $nebcs='selected="selected"'; }
   echo "<select id=\"$field\" name=\"$field\" class='carryforward inputField'>
	<option value=\"GH\" $ghs>GH</option>
	<option value=\"A\" $as>A</option>
	<option value=\"NEBC\" $nebcs>NEBC</option>
	<option value=\"FH\" $fhs>FH</option>
	<option value=\"AMES\" $amess>AMES</option>
	<option value=\"ECON\" $econs>ECON</option>
	</select>";
   echo "</td></tr>\n";
}

function selectCultivated($field) {
   echo "<tr><td>\n";
   echo "<label for='$field'>Cultivated</label>";
   echo "</td><td>\n";
   echo "<select id=\"$field\" name=\"$field\" class='inputField'>
	<option value=\"0\" selected=\"selected\">NO</option>
	<option value=\"1\">YES</option>
	</select>";
   echo "</td></tr>\n";
}

function selectTaxon($field,$label,$value,$valueid,$required='false',$carryforward='false') {
   $returnvalue = "<tr><td>";
   $fieldid = $field."id";
   if ($required=='true') { $req = " required='true' "; } else { $req = ''; }
   if ($carryforward=='true') { $carryforward = "carryforward"; } else { $carryforward=""; }
   $returnvalue .= "<label for=\"$field\">$label</label></td><td>
	<input type=text name=$field id=$field $req  value='$value' style=' width:". $GLOBALS['BASEWIDTH'] ."em;' class='inputField $carryforward'>
	<input type=hidden name=$fieldid id=$fieldid $req  value='$valueid' class='$carryforward' >
    </td></tr>";
   $returnvalue .= '
      <script>
         $(function() {
            $( "#'.$field.'" ).autocomplete({
               minLength: 4,
               delay: 500,
               searchAttr: "label",
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
	<input type=text name=$field id=$field class='inputField' dojoType='dijit.form.FilteringSelect'
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
   if ($carryforward=='true') { $carryforward = "carryforward"; } else { $carryforward=""; }
   $returnvalue .= "<label for=\"$field\">$label</label></td><td>
    <input type=text name=$field id=$field $req  value='$value' style=' width: ". $GLOBALS['BASEWIDTH'] ."em; ' class='inputField $carryforward' >
    <input type=hidden name=$fieldid id=$fieldid $req  value='$valueid' class='$carryforward' >
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
             minLength: 4,
             delay: 500,
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
	<input type='text' name=$field id=$field class='inputField' dojoType='dijit.form.FilteringSelect'
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
	<input type='text' name=$field id=$field class='inputField' dojoType='dijit.form.FilteringSelect'
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
   if ($carryforward=='true') { $carryforward = "carryforward"; } else { $carryforward=""; }
   $returnvalue .= "<label for=\"$field\">$label</label></td><td>
    <input type=text name=$field id=$field $req  value='$value' style=' width: ". $GLOBALS['BASEWIDTH'] ."em; ' class='inputField $carryforward' >
    <input type=hidden name=$fieldid id=$fieldid $req  value='$valueid' class='$carryforward' >
    </td></tr>";
   $returnvalue .= '
      <script>
         $(function() {
            $( "#'.$field.'" ).autocomplete({
               minLength: 4,
               delay: 500,
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
    <input type=text name=$field id=$field class='inputField' value='$value' style=' width: ". $GLOBALS['BASEWIDTH'] ."em; ' >
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
             minLength: 4,
             delay: 500,
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
        <input name='$field' id='$field' value='$default' $req style='width: ".$GLOBALS['BASEWIDTH']."em; ' class='inputField carryforward' >
     </div>
  </td></tr>
    ";
    echo $returnvalue;
}


?>
