<?php

session_start();

include_once("connection_library.php");
include_once("class_lib.php");
include_once("transcribe_lib.php");

define("ENABLE_DUPLICATE_FINDING",TRUE);
define("BASEWIDTH",21);  // em for width of form fields


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
}



# Supporting functions ******************************

function checkReady() { 
    # TODO: ping image window

    # this check probably needs to go into javascript inside mainform.
   
    return true;
}

function doSetup() { 
     //$target = target();
     //$barcode = $target->barcode;
     $targetBatch = getNextBatch();
     // echo "<button type='button' onclick=' $(\"#cover\").fadeIn(100); dosetup(\"$barcode\");' class='ui-button'>Start</button>";
     echo "<button type='button' onclick=' $(\"#cover\").fadeIn(100); dosetuppath(\"".urlencode($targetBatch->path)."\",\"".urlencode($targetBatch->filename)."\",\"$targetBatch->position\",\"test\");' class='ui-button'>Start (test mode)</button>";
     echo "&nbsp;";
     echo "<button type='button' onclick=' $(\"#cover\").fadeIn(100); dosetuppath(\"".urlencode($targetBatch->path)."\",\"".urlencode($targetBatch->filename)."\",\"$targetBatch->position\",\"standard\");' class='ui-button'>Start (production mode)</button>";
     echo " with [$targetBatch->path][$targetBatch->filename]";
     // echo " with [$barcode]";
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

   $result->barcode = '999999999';
   $mediauri = BASE_IMAGE_URI.$path."/".$filename;
   $result->mediauri = $mediauri;

   $height = 5616;
   $width = 3744;
   $s = 700/$height; // scale factor 
   $h = round($height*$s);
   $w = round($width*$s);

   $medialink .= "<a channel.postMessage(\"$barcode\"); '>$acronym $barcode</a>&nbsp; ";
   $medialink .= "<img id='image_div' onclick=' getClick(event,$h,$w,$height,$width,$mediaid);' src='$mediauri' width='$w' height='$h'></div>";
   $result->medialink = $medialink;
   
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
            where identifier = ? and object_type_id = 4 and hidden_flag = 0 and active_flag = 1
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
            where identifier = ? and object_type_id = 4 and hidden_flag = 0 and active_flag = 1
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

echo $apage->getFooter();


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

function form() {
   global $user;

   @$config = substr(preg_replace('/[^a-z]/','',$_GET['config']),0,10);
   @$test = substr(preg_replace('/[^a-z]/','',$_GET['test']),0,10);
   @$filename = preg_replace('/[^-a-zA-Z0-9._]/','',urldecode($_GET['filename']));
   @$path = urldecode($_GET['path']);

   switch ($config) { 
      case 'minimal':
          $config="minimal";
          break;
      case 'standard':
      default :
          $config="standard";
   }

   # Find out the barcode to load.
   if (strlen($filename)==0) { 
      $target = target(); 
   } else { 
      $target = targetfile($path,$filename);
   }
   $targetbarcode = $target->barcode;

   $currentBatch = new TR_Batch();
   $currentBatch->setPath($path);

   $habitat = "";

   echo "[$targetbarcode]";

   if ($test=="true") { 
       // populate with data for testing
       $prepmethod = "Pressed";
       $format = "Sheet";
   } else { 
       // load data from database
   }
   $huh_fragment = new huh_fragment();
   $matches = $huh_fragment->loadArrayByIdentifier($targetbarcode);
   $num_matches = count($matches);
   $project = null;
   if ($num_matches==1) { 
       $match = $matches[0];
       $match->load($match->getFragmentID());
       $prepmethod = $match->getPrepMethod();
       $related = $match->loadLinkedTo();
       $rcolobj = $related['CollectionObjectID'];
       $rprep = $related['PreparationID'];
       $rcolobj->load($rcolobj->getCollectionObjectID());
       $rprep->load($rprep->getPreparationID());
       $formatid = $rprep->getPrepTypeID();
       $related = $rprep->loadLinkedTo();
       $rpreptype = $related['PrepTypeID'];
       $rpreptype->load($rpreptype->getPrepTypeID());
       $format = $rpreptype->getName();
       $created = $rcolobj->getTimestampCreated();
       $habitat = $rcolobj->getText1();
       $related = $rcolobj->loadLinkedTo();
       $proj = new huh_project_custom();
       $project = $proj->getFirstProjectForCollectionObject($rcolobj->getCollectionObjectID());
       $rcoleve = $related['CollectingEventID'];
       $rcoleve->load($rcoleve->getCollectingEventID());
       $stationfieldnumber = $rcoleve->getStationFieldNumber();
       $datecollected = dateBitsToString($rcoleve->getStartDate(), $rcoleve->getStartDatePrecision(), $rcoleve->getEndDate(), $rcoleve->getEndDatePrecision());
       $verbatimdate = $rcoleve->getVerbatimDate();
       $related = $rcoleve->loadLinkedTo();
       $rlocality = $related['LocalityID'];
       $rlocality->load($rlocality->getLocalityID());
       $namedPlace = $rlocality->getNamedPlace();
       $verbatimElevation = $rlocality->getVerbatimElevation();
       $specificLocality = $rlocality->getLocalityName();
       
       
       
   }

   @$cleardefaultgeography = substr(preg_replace('/[^01]/','',$_GET['cleardefaultgeography']),0,2);
   if ($cleardefaultgeography==1) {
       $defaultcountry = "";
       $defaultprimary = "";
   } else { 
        @$defaultcountry = substr(preg_replace('/[^A-Za-z[:alpha:] ]/','',$_GET['defaultcountry']),0,255);
        @$defaultprimary = substr(preg_replace('/[^A-Za-z[:alpha:] ]/','',$_GET['defaultprimary']),0,255);
   }
   @$defaultherbarium = substr(preg_replace('/[^A-Z]/','',$_GET['defaultherbarium']),0,5);
   if ($defaultherbarium=='') { $defaultherbarium = "FH"; }
   if ($num_matches==1) { 
       $defaultherbarium = $match->getText1();
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
   echo "</div>";
   echo "</div>";
   echo "<div class='flex-main hfbox' style='padding: 0em;'>";  

   echo "<form action='transcribe_handler.php' method='POST' id='transcribeForm' >\n";
   echo "<input type=hidden name='action' value='transcribe' class='carryforward'>";
   echo "<input type=hidden name='operator' value='".$user->getAgentId()."' class='carryforward'>";
   if ($test=="true") { 
      echo "<input type=hidden name='test' value='true' class='carryforward'>";
   } else { 
      echo "<input type=hidden name='test' value='false' class='carryforward'>";
   }
   echo '<script>
         $( function(){
            $("#transcribediv").accordion( { heightStyle: "fill" } ) ;
          });
   </script>';
   echo '<div style="width: 34em;" id="transcribediv" >';
   echo '<h3>Transcribe into Fields</h3>';
   echo '<div>';

   echo "<table>";


   @staticvalue("Record Created:",$created);
   if ($test) { 
      @staticvalue("Project",$defaultproject);  
      echo "<input type='hidden' name='project' id='project' value='$defaultproject' class='carryforward'>";
   } else { 
      selectProject("defaultproject","Project",$defaultproject);  
   }
   @staticvalue("Barcode:",$targetbarcode);
   echo "<input type='hidden' name='barcode' id='barcode' value='$targetbarcode' class='carryforward'>";
   // field ("barcode","Barcode",$targetbarcode,'required','[0-9]{1,8}');   // not zero padded when coming off barcode scanner.
   echo '<script>
   $(function () {
       $("#barcode").on( "invalid", function () {
           this.setCustomValidity("Barcode must be a 1 to 8 digit number.");
       });
       $("#barcode").on( "input", function () {
           this.setCustomValidity("");
       });
   }); 
   </script>
   ';
   if ($test) { 
      @staticvalue("Prep Method",$prepmethod); 
      echo "<input type='hidden' name='prepmethod' id='prepmethod' value='$prepmethod' class='carryforward'>";
      @staticvalue("Format:",$defaultformat); 
      echo "<input type='hidden' name='preptype' id='preptype' value='$defaultformat' class='carryforward'>";
   } else {
      @field ("prepmethod","Prep Method",$prepmethod,'true');  // TODO
      selectPrepType("preptype","Format:",$defaultformat,'true'); 
   }
   
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
       selectAcronym("herbariumacronym",$defaultherbarium);
       @selectHigherGeography ("highergeography","Higher Geography",$geography,$geographyid,'','','true'); 
       @selectTaxon("filedundername","Filed Under",$filedundername,$filedundernameid,'true');  

   } elseif ($config=="standard") { 

       @selectTaxon("filedundername","Filed Under",$filedundername,$filedundernameid,'true','true');  
       @selectQualifier("filedunderqualifier","ID Qualifier",$filedunderqualifier); 
       @selectTaxon ("currentname","Current Name",$currentname,$currentnameid,'true','true'); 
       @selectQualifier("currentqualifier","ID Qualifier",$filedunderqualifier); 

       selectAcronym("herbariumacronym",$defaultherbarium);
       /* 
       Longer list (12 fields, for comparison)
       project - default US and Canada, show  
       barcode - known, not editable - on save, go to next in list.
       format - default sheet, show 
       preparation method - pressed default, show
       scientific name - 
          filed under  -   carry forward
          plus qualifier - carry forward
       scientific name - 
          current -- carry forward
          qualifier
       collectioncode - likely (GH, A, NEBC)
       collecting trip - pick
       highergeography - pick
       *verbatim locality
       collectors, et al
       *collector number
       *verbatim date collected
       *date collected
       */
        @selectCollectingTripID ("collectingtrip","Collecting Trip",$collectingtrip,$collectingtripid,'false'); 
        @selectHigherGeography ("highergeography","Higher Geography",$geography,$geographyid,'','','true'); 

        @field ("specificlocality","Verbatim locality",$specificLocality,'true'); 

        @selectCollectorsID("collectors","Collectors",$collectors,$collectoragentid,'true','false'); 
        @field ("etal","Et al.",$etal,'false');    

        @field ("stationfieldnumber","Collector Number",$stationfieldnumber,'false'); 
        @field ("verbatimdate","Verbatim Date",$verbatimdate,'false'); 
        @field ("datecollected","Date Collected",$datecollected,'false','([0-9]{4}(-[0-9]{2}){0,2}){1}(/([0-9]{4}(-[0-9]{2}){0,2}){1}){0,1}','2010-03-18','Use of an ISO format is required: yyyy, yyyy-mm, yyyy-mm-dd, or yyyy-mm-dd/yyyy-mm-dd'); 
   } else { 
        @selectTaxon("filedundername","Filed Under",$filedundername,$filedundernameid,'true');  
        @selectQualifier("filedunderqualifier","ID Qualifier",$filedunderqualifier); 
        @selectTaxon ("currentname","Current Name",$currentname,$currentnameid,'true'); 
        @selectQualifier("currentqualifier","ID Qualifier",$filedunderqualifier); 

        selectAcronym("herbariumacronym",$defaultherbarium);
        @selectHigherGeography ("highergeography","Higher Geography",$geography,$geographyid,'','','true'); 

        @field ("specificlocality","Verbatim locality",$specificLocality,'true'); 
        @field ("stationfieldnumber","Collector Number",$stationfieldnumber,'false'); 
        @field ("datecollected","Date Collected",$datecollected,'false','[0-9-/]+','2010-03-18'); 
        @field ("verbatimdate","Verbatim Date",$verbatimdate,'false'); 
        field ("habitat","Habitat",$habitat); 
        @field ("namedplace","Named place",$namedPlace); 
        @field ("verbatimelevation","verbatimElevation",$verbatimElevation,'false'); 
   }

   echo "<tr><td>";
   echo "<input type='submit' value='Save' id='saveButton' class='carryforward ui-button'> ";
   echo "<input type='button' value='Next', disabled='true' id='nextButton' class='carryforward ui-button'>";
   echo "</td></tr>";

   echo "<script>
         $('#nextButton').click(function(event){
               $('#feedback').html( 'Loading next...');
               // clear fields 
               $('#transcribeForm  input:not(.carryforward)').val('');

               // load next image
  
               // load data

               event.preventDefault();
          });
   </script>";

   if ($test=="true") { 
       // in test mode, only log data capture rate
   echo "<script>
         $('#transcribeForm').submit(function(event){
               $('#feedback').html( 'Submitting: ' + ($('#barcode').val()) ) ;
               $.ajax({ 
                   type: 'POST',
                   url: 'transcribe_logger.php',
                   data: $('#transcribeForm').serialize(),
                   success: function(data) { 
                       $('#feedback').html( data ) ;
                       $('#nextButton').prop('disabled',false) 
                   },
                   error: function() { 
                       $('#feedback').html( 'Failed.  Ajax Error.  Barcode: ' + ($('#barcode').val()) ) ;
                       $('#nextButton').prop('disabled',true) 
                   }
               });
               event.preventDefault();
          });
   </script>";
   } else { 
       // otherwise, save the changes 
   echo "<script>
         $('#transcribeForm').submit(function(event){
               $('#feedback').html( 'Submitting: ' + ($('#barcode').val()) ) ;
               $.ajax({ 
                   type: 'POST',
                   url: 'transcribe_handler.php',
                   data: $('#transcribeForm').serialize(),
                   success: function(data) { 
                       $('#feedback').html( data ) ;
                       $('#nextButton').prop('disabled',false) 
                   },
                   error: function() { 
                       $('#feedback').html( 'Failed.  Ajax Error.  Barcode: ' + ($('#barcode').val()) ) ;
                       $('#nextButton').prop('disabled',true) 
                   }
               });
               event.preventDefault();
          });
   </script>";
   }
   

   echo "</table>";
   echo '</div>';  // end of accordion


   echo '</div>';

   echo "</form>\n";

   echo '<script>
         $( function(){
            $("#imagezoomdiv").accordion( { heightStyle: "content" } ) ;
          });
   </script>';
   echo '<div id="imagezoomdiv" >';
   echo '<h3>Click to zoom in other window.</h3>';
   echo '<div>';

   #$mediauri = "http://nrs.harvard.edu/urn-3:FMUS.HUH:s19-00000001-315971-2";
   #$mediauri = "https://s3.amazonaws.com/huhwebimages/94A28BC927D6407/type/full/460286.jpg";
   #$mediauri = imageForBarcode($targetbarcode);
   
   echo '<div class=flexbox>';
    $medialink = $target->medialink;
    echo "
        <script>
           channel.onmessage = function (e) { console.log(e); }

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

function field($name, $label, $default="", $required='false', $regex='', $placeholder='', $validationmessage='') {
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
   if ($required=='false') { 
      echo "<input id=$name name=$name value='$default' $regex $placeholder $validationmessage  style='width: ".BASEWIDTH."em; ' >";
   } else { 
      if ($validationmessage!='') {
         $validationmessage = "validationMessage='Required Field. $validationmessage'";
      }
      echo "<input id=$name name=$name value='$default' required='$required' $regex $placeholder $validationMessage  style='width: ".BASEWIDTH."em; ' >";
   }
   echo "</td></tr>\n";
}

function selectPrepType($field,$label,$default,$required='false',$carryforward='true') {
   if ($carryforward=='true') { $carryforward = " class='carryforward' "; } else { $carryforward=""; } 
   $returnvalue = "
  <script>
  $( function() {
    $( '#$field' ).autocomplete({
      source: 'ajax_handler.php?druid_action=returndistinctjqapreptype',
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
        <input id='$field' value='$default'  style='width: ".BASEWIDTH."em; ' $carryforward >
     </div>
  </td></tr>
   ";
   echo $returnvalue;
}



function utm() {
	echo "<tr><td>\n";
	echo "<label for='utmzone'>UTM Zone,Easting,Northing</label>";
	echo "</td><td>\n";
	echo "<input name=utmzone value='' dojoType='dijit.form.ValidationTextBox' required='false' regExp='[0-9]+[A-Z]' style='width: 4em;' >";
	echo "<input name=utmeasting value='' dojoType='dijit.form.ValidationTextBox' required='false' regExp='[0-9]+' style='width: 8em;' >";
	echo "<input name=utmnorthing value='' dojoType='dijit.form.ValidationTextBox' required='false' regExp='[0-9]+' style='width: 8em;' >";
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

function geographyselect($name,$label,$default,$required,$rank) {
	// $returnvalue = "<tr><td><div dojoType='dojo.data.ItemFileReadStore' jsId='store$name$rank'
	$returnvalue .= "<label for=\"$name\">$label</label></td><td>
	<input type=text name=$name id=$name dojoType='dijit.form.FilteringSelect' 
	store='store$name$rank' required='$required'
	searchAttr='name' value='$default' ></td></tr>";
	echo $returnvalue;
}

function selectHigherGeography($field,$label,$value,$valueid, $defaultcountry='', $defaultprimary='',$required='true') { 
   $returnvalue = "<tr><td>";
   $fieldid = $field."id";
   $returnvalue .= "<label for=\"$field\">$label</label></td><td>
    <input type=text name=$field id=$field required='$required'  value='$value' style=' width: 25em; ' >
    <input type=hidden name=$fieldid id=$fieldid required='$required'  value='$valueid' >
    </td></tr>";
   $returnvalue .= '
      <script>
         $(function() {
            $( "#'.$field.'" ).autocomplete({
               minLength: 3,
               source: function( request, response ) {
                  $.ajax( {
                    url: "ajax_handler.php",
                    dataType: "json",
                    data: {
                       druid_action: "geoidgeojson",
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

function fieldselectpicklist($name,$label,$default,$required,$storeId,$picklistid) {
   if ($required=='true') { $req = '&required=true'; } else { $req = ''; } 
   $returnvalue = "<tr><td><div dojoType='dojo.data.ItemFileReadStore' jsId='$storeId'
	 url='ajax_handler.php?druid_action=returndistinctjsonpicklist&field=value&picklistid=$picklistid$req'> </div>";
   $returnvalue .= "<label for=\"$name\">$label</label></td><td>
	<input type=text name=$name id=$name dojoType='dijit.form.FilteringSelect' 
	store='$storeId' required='$required'   
	searchAttr='name' value='$default' ></td></tr>";
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
   echo "<select name=\"$field\" >
	<option value=\"GH\" $ghs>GH</option>
	<option value=\"A\" $as>A</option>
	<option value=\"FH\" $fhs>FH</option>
	<option value=\"AMES\" $amess>AMES</option>
	<option value=\"ECON\" $econs>ECON</option>
	<option value=\"NEBC\" $nebcs>NEBC</option>
	</select>";
   echo "</td></tr>\n";
}

function selectTaxon($field,$label,$value,$valueid,$required='false',$carryforward='false') {
   $returnvalue = "<tr><td>";
   $fieldid = $field."id";
   if ($carryforward=='true') { $carryforward = " class='carryforward' "; } else { $carryforward=""; } 
   $returnvalue .= "<label for=\"$field\">$label</label></td><td>
	<input type=text name=$field id=$field required='$required'  value='$value' style=' width: 25em; ' $carryforward >
	<input type=hidden name=$fieldid id=$fieldid required='$required'  value='$valueid' $carryforward >
    </td></tr>";
   $returnvalue .= '
      <script>
         $(function() {
            $( "#'.$field.'" ).autocomplete({
               minLength: 3,
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
	$returnvalue = "<tr><td><div dojoType='custom.ComboBoxReadStore' jsId='taxonStore$field'
	url='ajax_handler.php?druid_action=returndistinctjsonidnamelimited&table=huh_taxon&field=FullName'> </div>";
	$width = BASEWIDTH - 3;
	$returnvalue .= "<label for=\"$field\">$label</label></td><td>
	<input type=text name=$field id=$field dojoType='dijit.form.FilteringSelect'
	store='taxonStore$field' required='$required' searchDelay='300' hasDownArrow='false'
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
   if ($carryforward=='true') { $carryforward = " class='carryforward' "; } else { $carryforward=""; }
   $returnvalue .= "<label for=\"$field\">$label</label></td><td>
    <input type=text name=$field id=$field required='$required'  value='$value' style=' width: 25em; ' $carryforward >
    <input type=hidden name=$fieldid id=$fieldid required='$required'  value='$valueid' $carryforward >
    </td></tr>";
   $returnvalue .= '
      <script>
         $(function() {
            $( "#'.$field.'" ).autocomplete({
               minLength: 3,
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
   if ($exsiccati=='true') { 
       $returnvalue = "<tr><td><div dojoType='custom.ComboBoxReadStore' jsId='agentStore$field'
	      url='ajax_handler.php?druid_action=returndistinctjsonexsiccati' > </div>";
   } else { 
       $returnvalue = "<tr><td><div dojoType='custom.ComboBoxReadStore' jsId='agentStore$field'
	      url='ajax_handler.php?druid_action=returndistinctjsontitle' > </div>";
   }
   $returnvalue .= "<label for=\"$field\">$label</label></td><td>
	<input type='text' name=$field id=$field dojoType='dijit.form.FilteringSelect' 
	store='agentStore$field' required='$required' searchDelay='300' hasDownArrow='false' style='border-color: blue;' 
	searchAttr='name' value='' >
   <button id='buttonReset$field' dojoType='dijit.form.Button' data-dojo-type='dijit/form/Button' type='button' 
   onclick=\"dijit.byId('$field').reset();\"  data-dojo-props=\"iconClass:'dijitIconClear'\" ></button></td></tr>";
   echo $returnvalue;
}

function selectStorageID($field,$label,$required='false') {
   $returnvalue = "<tr><td><div dojoType='custom.ComboBoxReadStore' jsId='agentStore$field'
	      url='ajax_handler.php?druid_action=returndistinctjsonstorage' > </div>";
   $returnvalue .= "<label for=\"$field\">$label</label></td><td>
	<input type='text' name=$field id=$field dojoType='dijit.form.FilteringSelect' 
	store='agentStore$field' required='$required' searchDelay='300' hasDownArrow='false' style='border-color: blue;' 
	searchAttr='name' value='' >
	<button id='buttonReset$field' dojoType='dijit.form.Button' data-dojo-type='dijit/form/Button' type='button' 
    onclick=\"dijit.byId('$field').reset();\"  data-dojo-props=\"iconClass:'dijitIconClear'\" ></button></td></tr>";
   echo $returnvalue;
}

function selectContainerID($field,$label,$required='false') {
	$returnvalue = "<tr><td><div dojoType='custom.ComboBoxReadStore' jsId='agentStore$field'
	 url='ajax_handler.php?druid_action=returndistinctjsoncontainer' > </div>";
	$returnvalue .= "<label for=\"$field\">$label</label></td><td>
	<input type='text' name=$field id=$field dojoType='dijit.form.FilteringSelect' 
	store='agentStore$field' required='$required' searchDelay='300' hasDownArrow='false' style='border-color: blue;'
	searchAttr='name' value='' >
	<button id='buttonReset$field' dojoType='dijit.form.Button' data-dojo-type='dijit/form/Button' type='button' 
    onclick=\"dijit.byId('$field').reset();\"  data-dojo-props=\"iconClass:'dijitIconClear'\" ></button></td></tr>";
	echo $returnvalue;
}

function selectCollectingTripID($field,$label,$value,$valueid,$carryforward='false') {
   $returnvalue = "<tr><td>";
   $fieldid = $field."id";
   $returnvalue .= "<label for=\"$field\">$label</label></td><td>
    <input type=text name=$field id=$field  value='$value' style=' width: 25em; ' >
    <input type=hidden name=$fieldid id=$fieldid value='$valueid' >
    </td></tr>";
   $returnvalue .= '
      <script>
         $(function() {
            $( "#'.$field.'" ).autocomplete({
               minLength: 3,
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
        <input id='$field' value='$default'  style='width: ".BASEWIDTH."em; ' >
     </div>
  </td></tr>
    ";
    echo $returnvalue;
}



function pagefooter() {
   global $targethostdb;
   echo '
	<div dojoType="dijit.layout.ContentPane" region="bottom" layoutPriority="3" splitter="false">
        <div id="feedback">Status</div>
	</div>
	<div dojoType="dijit.layout.ContentPane" region="bottom" layoutPriority="2" splitter="false">
        Database: ' . $targethostdb . ' 
	</div>
	';
   echo "</div>\n";
   echo "</body>\n";
   echo "</html>";
}

?>