<?php
session_start();

$debug = false;
if ($debug) {
   echo  "[". $_SESSION["user_ticket"] . "][". $_SESSION["session_id"] ."]";
        // See PHP documentation.
        mysqli_report(MYSQLI_REPORT_ERROR ^ MYSQLI_REPORT_STRICT);
} else {
        mysqli_report(MYSQLI_REPORT_OFF);
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

class Result {
  public $success;
  public $html;
  public $errors;
}

include_once('class_lib.php');  // contains declaration of User() class
include_once('transcribe_lib.php');
include_once('imagehandler.php');  // ImageHandler class for support of setting up batches and checking images for barcodes.

// *******
// *******  You must provide connections.php or a replacement means of
// *******  having a database connection in scope before including druid_handler.
// *******
// *******  Warning: You must limit the rights of the user for this
// *******  connection with appropriate (e.g. select only) privileges.
// *******
include_once('connection_library.php'); // contains declaration of make_database_connection()
if (!function_exists('specify_connect')) {
   echo 'Error: Database connection function not defined.';
}
@$connection = specify_connect();

// check authentication
$authenticated=false;
if (isset($_SESSION['user_ticket'])) {
   $u = new User();
   $u->setEmail($_SESSION["username"]);
   if ($u->validateTicket($_SESSION['user_ticket'],$_SERVER['REMOTE_ADDR'])) {
      $u->setFullname($_SESSION["fullname"]);
      $u->setAgentId($_SESSION["agentid"]);
      $u->setLastLogin($_SESSION["lastlogin"]);
      $u->setAbout($_SESSION["about"]);
      $authenticated = true;
   }
}

if ($connection && $authenticated) {

   if ($_SERVER['REQUEST_METHOD'] === 'POST') {
      @$action = substr(preg_replace('/[^a-z]/','',$_POST['action']),0,45);
   } else {
      @$action = substr(preg_replace('/[^a-z]/','',$_GET['action']),0,45);
   }

   if ($debug) { echo "[$action]"; }
   if ($debug) { print_r($_SERVER); }

   $alpha = "ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ";

   switch ($action) {
      case 'interpretdate':
         @$verbatimdate = $_GET['verbatimdate'];
         $exec = $vdateexecstring . escapeshellarg($verbatimdate) . " 2>&1";
         $isodate = shell_exec($exec);

         echo "$isodate";

         break;

      case 'processbatch':
         @$directory= $_POST['directory'];

         $toencode = array();
         header("Content-type: application/json");
         if ($directory!="") {
             $result = ImageHandler::scanAndStoreDirectory(substr($directory,strlen(BASE_IMAGE_PATH)+1));
             $toencode['result']=$result;
             $toencode['error']='';
         } else {
             $toencode['']='';
             $toencode['error']="No directory provided to process.";

         }
         echo json_encode($toencode);
         break;

      case 'updatedosetup':
         // to update the doSetup page upon changing a batch to work on.
         @$targetBatchDir = $_POST['targetBatch'];
         if($targetBatchDir=="") {
            $targetBatch = getNextBatch();
         } else {
            $targetBatch = getBatch($targetBatchDir);
         }
         $targetBatchFirst = getFirstFileInBatch($targetBatchDir);
         $position = $targetBatch->position + 1;
         echo " <strong>Batch: [$targetBatch->path]</strong>";
         echo "<button type='button' onclick=' $(\"#cover\").fadeIn(100); dosetuppath(\"".urlencode($targetBatch->path)."\",\"".urlencode($targetBatch->filename)."\",\"$targetBatch->position\",\"standard\");' class='ui-button ui' >Start from $position</button>";
         if ($position > 1) {
              echo "<button type='button' onclick=' $(\"#cover\").fadeIn(100); dosetuppath(\"".urlencode($targetBatch->path)."\",\"".urlencode($targetBatchFirst->filename)."\",\"0\",\"standard\");' class='ui-button'>Start from first.</button>";
         }
         echo " First File: [$targetBatch->filename]";
         break;

      case 'getnextrecord':
         $ok = false;
         @$id = $_GET['batch_id'];
         @$position = $_GET['position'];  // zero based position in file array
         // lookup the filename for this position
         $batch = new TR_BATCH();
         $batch->setID($id);
         $path = $batch->getPath();
         $ir = $batch->getFile($position);
         $filename = $ir->filename;

         // IMAGE_LOCAL_FILE.path is expected to end with a /
         if (substr($path,-1,1)!="/") {
            $path = "$path/";
         }
         // IMAGE_LOCAL_FILE.path is expected to not start with a /
         if (substr($path,0,1)=="/") {
             $path = substr($path,1);
         }
         $toencode=array();
         $toencode['path']=$path;
         $toencode['filename']=$filename;

         // lookup the barcode for this filename
         $barcode = getBarcodeForFilename($path,$filename);

         // lookup the data for this barcode.
         $dataarray = lookupDataForBarcode($barcode);
         $values = "";
         switch ($dataarray['status']) {
            case "NOTFOUND":
              if($barcode==null || strlen(trim($barcode))==0) {
                   $toencode['barcode']='NOTFOUND';
                   $toencode['path']=$path;
                   $toencode['filename']=$filename;
              } else {
                   $toencode['barcode']=$barcode;
              }
              $values = json_encode($toencode);
              $ok = true;
              break;

            case "FOUND":
              $values = json_encode($dataarray);
              $ok = true;
              break;
            case "ERROR":
            default:
              break;
         }

         header("Content-type: application/json");
         if ($ok) {
            $response = $values;
         } else {
            $response = '{}';
         }
         echo $response;
         break;

      case 'getnextimage':
         $ok = false;
         @$id = $_GET['batch_id'];
         $currentBatch = new TR_Batch();
         $currentBatch->setID($id);
         $path = $currentBatch->getPath();
         //$pathfile = $currentBatch->getNextFile();
         $pathfile = $currentBatch->incrementFile();
         $position1 = $pathfile->position +1;  // convert zero based file array position to 1 based file x of y position.
         $filecount = $currentBatch->getFileCount();
         $mediauri = BASE_IMAGE_URI.$pathfile->path."/".$pathfile->filename;
         $path= $pathfile->path;
         $filename = $pathfile->filename;
         $values = "{ \"src\":\"$mediauri\", \"position1\":\"$position1\", \"filecount\":\"$filecount\", \"path\":\"$path\", \"filename\":\"$filename\" }";
         if (strlen($pathfile->filename)>0) { $ok=true; }

         header("Content-type: application/json");
         if ($ok) {
            $response = $values;
         } else {
            $response = '{}';
         }
         echo $response;
         break;

      case 'getpreviousimage':
         $ok = false;
         @$id = $_GET['batch_id'];
         $currentBatch = new TR_Batch();
         $currentBatch->setID($id);
         $path = $currentBatch->getPath();
         //$pathfile = $currentBatch->getNextFile();
         $pathfile = $currentBatch->decrementFile();
         $position1 = $pathfile->position +1;  // convert zero based file array position to 1 based file x of y position.
         $filecount = $currentBatch->getFileCount();
         $mediauri = BASE_IMAGE_URI.$pathfile->path."/".$pathfile->filename;
         $path= $pathfile->path;
         $filename = $pathfile->filename;
         $values = "{ \"src\":\"$mediauri\", \"position1\":\"$position1\", \"filecount\":\"$filecount\", \"path\":\"$path\", \"filename\":\"$filename\" }";
         if (strlen($pathfile->filename)>0) { $ok=true; }

         header("Content-type: application/json");
         if ($ok) {
            $response = $values;
         } else {
            $response = '{}';
         }
         echo $response;
         break;

      case 'getdataforbarcode':
         @$barcode = $_GET['barcode'];
         $result = lookupDataForBarcode($barcode);
         echo json_encode($result);
         break;

      case 'transcribe':
         $feedback = "";

         $todo = 100;
         $truncation = false;
         $truncated = "";
         @$collectors= $_POST['collectors'];
         @$collectorsid= substr(preg_replace('/[^0-9]/','',$_POST['collectorsid']),0,huh_agentvariant::AGENTID_SIZE);
         @$etal= substr(preg_replace('/[^A-Za-z&\[\]\, \.0-9]/','',$_POST['etal']),0,huh_collector::ETAL_SIZE);
         @$fieldnumber= substr(preg_replace('/[^A-Za-z\- \.0-9\,\/]/','',$_POST['fieldnumber']),0,huh_collectingevent::STATIONFIELDNUMBER_SIZE);
         @$stationfieldnumber= substr(preg_replace('/[^A-Za-z\- \.0-9\,\/\(\)\[\]=#]/','',$_POST['stationfieldnumber']),0,huh_collectingevent::STATIONFIELDNUMBER_SIZE);
         @$accessionnumber= substr(preg_replace('/[^A-Za-z\- \.0-9\,\/]/','',$_POST['accessionnumber']),0,huh_collectingevent::STATIONFIELDNUMBER_SIZE);
         @$verbatimdate= substr($_POST['verbatimdate'],0,huh_collectingevent::VERBATIMDATE_SIZE);
         @$datecollected= substr(preg_replace('/[^\-\/0-9]/','',$_POST['datecollected']),0,40);  // allow larger than date to parse ISO date range
         @$datecollectedval= substr(preg_replace('/[^\-\/0-9]/','',$_POST['datecollectedval']),0,40);  // allow larger than date to parse ISO date range
         @$herbariumacronym= substr(preg_replace('/[^A-Z]/','',$_POST['herbariumacronym']),0,huh_fragment::TEXT1_SIZE);
         @$barcode= substr(preg_replace('/[^0-9]/','',$_POST['barcode']),0,huh_fragment::IDENTIFIER_SIZE);
         @$barcodeval= substr(preg_replace('/[^0-9]/','',$_POST['barcodeval']),0,huh_fragment::IDENTIFIER_SIZE);
         @$provenance= substr(preg_replace('/^A-Za-z 0-9\[\]\.\-\,\(\)\?\;\:]/','',$_POST['provenance']),0,huh_fragment::PROVENANCE_SIZE);
         @$filedundername= substr(preg_replace('/[^A-Za-z[:alpha:]\(\) 0-9.]/','',$_POST['filedundername']),0,huh_taxon::FULLNAME_SIZE);
         @$filedundernameid= substr(preg_replace('/[^0-9]/','',$_POST['filedundernameid']),0,huh_taxon::TAXONID_SIZE);
         @$filedunderqualifier= substr(preg_replace('/[^A-Za-z]/','',$_POST['filedunderqualifier']),0,huh_determination::QUALIFIER_SIZE);
         @$currentname= substr(preg_replace('/[^A-Za-z[:alpha:]\(\) 0-9.]/','',$_POST['currentname']),0,huh_taxon::FULLNAME_SIZE);
         @$currentnameid= substr(preg_replace('/[^0-9]/','',$_POST['currentnameid']),0,huh_taxon::TAXONID_SIZE);
         @$currentqualifier= substr(preg_replace('/[^A-Za-z]/','',$_POST['currentqualifier']),0,huh_determination::QUALIFIER_SIZE);
         @$identifiedby= substr(preg_replace('/[^0-9]/','',$_POST['identifiedby']),0,huh_determination::DETERMINERID_SIZE);
         @$determinertext= substr(preg_replace('/[^A-Za-z[:alpha:]'.$alpha.'0-9+\;\:() \.\-\,\[\]\&\'\/?#"ñ°]/','',$_POST['determinertext']),0,huh_determination::TEXT1_SIZE);
         @$dateidentified= substr(preg_replace('/[^0-9\-\/]/','',$_POST['dateidentified']),0,huh_determination::DETERMINEDDATE_SIZE);
         @$highergeography= $_POST['highergeography'];
         @$highergeographyid= substr(preg_replace('/[^0-9]/','',$_POST['highergeographyid']),0,huh_geography::GEOGRAPHYID_SIZE);
         @$specificlocality = substr(preg_replace('/[^A-Za-z[:alpha:]'.$alpha.'0-9+\;\:() \.\-\,\[\]\&\'\/?#"ñ°]/','',$_POST['specificlocality']),0,huh_locality::LOCALITYNAME_SIZE);
         @$prepmethod = substr(preg_replace('/[^A-Za-z]/','',$_POST['prepmethod']),0,huh_preparation::PREPTYPEID_SIZE);
         @$format = substr(preg_replace('/[^A-Za-z]/','',$_POST['preptype']),0,huh_preptype::NAME_SIZE);

         @$verbatimlat= substr(preg_replace('/[^0-9\. \'"°NSn-]/','',$_POST['verbatimlat']),0,huh_locality::LAT1TEXT_SIZE);
         @$verbatimlong= substr(preg_replace('/[^0-9\. \'"°EWew\-]/','',$_POST['verbatimlong']),0,huh_locality::LONG1TEXT_SIZE);
         @$decimallat= substr(preg_replace('/[^0-9\.\-]/','',$_POST['decimallat']),0,huh_locality::LATITUDE1_SIZE);
         @$decimallong= substr(preg_replace('/[^0-9\.\-]/','',$_POST['decimallong']),0,huh_locality::LONGITUDE1_SIZE);
         @$datum= substr(preg_replace('/[^A-Za-z0-9]/','',$_POST['datum']),0,huh_locality::DATUM_SIZE);
         @$coordinateuncertanty= substr(preg_replace('/[^0-9]/','',$_POST['coordinateuncertanty']),0,huh_geocoorddetail::MAXUNCERTAINTYEST_SIZE);
         @$georeferencedby= substr(preg_replace('/[^0-9]/','',$_POST['georeferencedby']),0,huh_agentvariant::NAME_SIZE);
         @$georeferencedate= substr(preg_replace('/[^\-\/0-9]/','',$_POST['georeferencedate']),0,huh_geocoorddetail::GEOREFDETDATE_SIZE);
         @$georeferencesource= substr(preg_replace('/[^A-Za-z]/','',$_POST['georeferencesource']),0,huh_locality::LATLONGMETHOD_SIZE);
         @$utmzone= substr(preg_replace('/[^0-9A-Z]/','',$_POST['utmzone']),0,huh_localitydetail::UTMZONE_SIZE);
         @$utmeasting= substr(preg_replace('/[^0-9]/','',$_POST['utmeasting']),0,huh_localitydetail::UTMEASTING_SIZE);
         @$utmnorthing= substr(preg_replace('/[^0-9]/','',$_POST['utmnorthing']),0,huh_localitydetail::UTMNORTHING_SIZE);

         @$typestatus= substr(preg_replace('/[^A-Za-z]/','',$_POST['typestatus']),0,huh_determination::TYPESTATUSNAME_SIZE);
         @$typeconfidence= substr(preg_replace('/[^A-Za-z]/','',$_POST['typeconfidence']),0,huh_determination::CONFIDENCE_SIZE);
         @$basionym= substr(preg_replace('/[^0-9]/','',$_POST['basionym']),0,huh_taxon::FULLNAME_SIZE);
         @$publication= substr(preg_replace('/[^[:alpha:]A-Za-z 0-9]/','',$_POST['publication']),0,huh_referencework::REFERENCEWORKID_SIZE);
         @$page= substr(preg_replace('/[^0-9 A-Za-z\,\(\)\-\:\;\.\[\]]/','',$_POST['page']),0,huh_taxoncitation::TEXT1_SIZE);
         @$datepublished= substr(preg_replace('/[^0-9 A-Za-z\,\(\)\-\:\;\.\[\]]/','',$_POST['datepublished']),0,huh_taxoncitation::TEXT2_SIZE);
         @$isfragment= substr(preg_replace('/[^0-9a-z]/','',$_POST['isfragment']),0,1);   // taxon
         @$habitat= substr(preg_replace('/[^A-Za-z&!\' 0-9\[\]\.\-\,\(\)\?\:\;]/','',$_POST['habitat']),0,huh_collectingevent::REMARKS_SIZE);
         @$host = substr(preg_replace('/[^A-Za-z 0-9\[\]\.\-\,\(\)\?\;\:]/','',$_POST['host']),0,900);
         @$substrate= substr(preg_replace('/[^A-Za-z[:alpha:]'.$alpha.'0-9+\;\:() \.\-\,\[\]\&\'\/?#"ñ°]/','',$_POST['substrate']),0,huh_fragment::TEXT2_SIZE);
         @$phenology= substr(preg_replace('/[^A-Za-z ]/','',$_POST['phenology']),0,huh_fragment::PHENOLOGY_SIZE);
         @$verbatimelevation= substr(preg_replace('/[^A-Za-z0-9\-\.\, \[\]\(\)\? \&\']/','',$_POST['verbatimelevation']),0,huh_locality::VERBATIMELEVATION_SIZE);
         @$namedplace = substr(preg_replace('/[^A-Za-z0-9[:alpha:]\-\.\, \[\]\(\)\? \&\']/','',$_POST['namedplace']),0,huh_locality::NAMEDPLACE_SIZE);
         @$minelevation= substr(preg_replace('/[^0-9\.]/','',$_POST['minelevation']),0,huh_locality::MINELEVATION_SIZE);
         @$maxelevation= substr(preg_replace('/[^0-9\.]/','',$_POST['maxelevation']),0,huh_locality::MAXELEVATION_SIZE);
         @$specimenremarks= substr(preg_replace('/[^A-Za-z[:alpha:]0-9\- \.\,\;\:\&\'\]\[]/','',$_POST['specimenremarks']),0,huh_collectionobject::REMARKS_SIZE);
         @$specimendescription= substr(preg_replace('/[^A-Za-z[:alpha:]0-9\- \.\,\;\:\&\'\]\[]/','',$_POST['specimendescription']),0,huh_collectionobject::DESCRIPTION_SIZE);
         @$itemdescription= substr(preg_replace('/[^A-Za-z[:alpha:]0-9\- \.\,\;\:\&\'\]\[]/','',$_POST['itemdescription']),0,huh_fragment::DESCRIPTION_SIZE);
         @$container= substr(preg_replace('/[^0-9]/','',$_POST['container']),0,huh_collectionobject::CONTAINERID_SIZE);
 		     @$collectingtrip = substr(preg_replace('/[^0-9]/','',$_POST['collectingtrip']),0,huh_collectingevent::COLLECTINGTRIPID_SIZE);
         @$storagelocation= substr(preg_replace('/[^A-Za-z'.$alpha.'0-9+\;\:() \.\-\,\[\]\&\'\/?#"ñ]/','',$_POST['storagelocation']),0,huh_preparation::STORAGELOCATION_SIZE);
         @$project= substr(preg_replace('/[^A-Za-z\. \-0-9]/','',$_POST['project']),0,huh_project::PROJECTNAME_SIZE);
         @$storage= substr(preg_replace('/[^0-9]/','',$_POST['storage']),0,huh_storage::STORAGEID_SIZE); // subcollection
         @$exsiccati= substr(preg_replace('/[^0-9]/','',$_POST['exsiccati']),0,huh_referencework::REFERENCEWORKID_SIZE);
         @$fascicle= substr(preg_replace('/[^A-Za-z\. 0-9]/','',$_POST['fascicle']),0,huh_fragmentcitation::TEXT1_SIZE);
         @$exsiccatinumber= substr(preg_replace('/[^A-Za-z\. 0-9]/','',$_POST['exsiccatinumber']),0,huh_fragmentcitation::TEXT2_SIZE);

         @$batchid = $_POST['batch_id'];
         //@$= substr(preg_replace('/[^0-9]/','',$_POST['']),0,huh_);

         if ( @($collectors!=$_POST['collectors']) )  { $truncation = true; $truncated .= "Collector: [$collectors] "; }
         if ( @($collectorsid!=$_POST['collectorsid']) )  { $truncation = true; $truncated .= "CollectorsID: [$collectorsid] "; }
         if ( @($etal!=$_POST['etal']) ) { $truncation = true; $truncated .= "etal : [$etal] "; }
         if ( @($fieldnumber!=$_POST['fieldnumber']) ) { $truncation = true; $truncated .= "fieldnumber : [$fieldnumber] "; }
         if ( @($stationfieldnumber!=$_POST['stationfieldnumber']) ) { $truncation = true; $truncated .= "stationfieldnumber : [$stationfieldnumber] "; }
         if ( @($accessionnumber!=$_POST['accessionnumber']) ) { $truncation = true; $truncated .= "accessionnumber : [$accessionnumber] "; }
         if ( @($verbatimdate!=$_POST['verbatimdate']) ) { $truncation = true; $truncated .= "verbatimdate : [$verbatimdate] "; }
         if ( @($datecollected!=$_POST['datecollected']) ) { $truncation = true; $truncated .= "datecollected : [$datecollected] "; }
         if ( @($datecollectedval!=$_POST['datecollectedval']) ) { $truncation = true; $truncated .= "datecollectedval : [$datecollectedval] "; }
         if ( @($herbariumacronym!=$_POST['herbariumacronym']) ) { $truncation = true; $truncated .= "herbariumacronym : [$herbariumacronym] "; }
         if ( @($barcode!=$_POST['barcode']) ) { $truncation = true; $truncated .= "barcode : [$barcode] "; }
         if ( @($provenance!=$_POST['provenance']) ) { $truncation = true; $truncated .= "provenance : [$provenance] "; }
         if ( @($filedundername!=$_POST['filedundername']) ) { $truncation = true; $truncated .= "filedundername : [$filedundername] "; }
         if ( @($filedundernameid!=$_POST['filedundernameid']) ) { $truncation = true; $truncated .= "filedundernameid : [$filedundernameid] "; }
         if ( @($filedunderqualifier!=$_POST['filedunderqualifier']) ) { $truncation = true; $truncated .= "filedunderqualifier : [$filedunderqualifier] "; }
         if ( @($currentname!=$_POST['currentname']) ) { $truncation = true; $truncated .= "currentname : [$currentname] "; }
         if ( @($currentnameid!=$_POST['currentnameid']) ) { $truncation = true; $truncated .= "currentnameid : [$currentnameid] "; }
         if ( @($currentqualifier!=$_POST['currentqualifier']) ) { $truncation = true; $truncated .= "currentqualifier : [$currentqualifier] "; }
         if ( @($identifiedby!=$_POST['identifiedby']) ) { $truncation = true; $truncated .= "identifiedby : [$identifiedby] "; }
         if ( @($determinertext!=$_POST['determinertext']) ) { $truncation = true; $truncated .= "determinertext : [$determinertext] "; }
         if ( @($dateidentified!=$_POST['dateidentified']) ) { $truncation = true; $truncated .= "dateidentified : [$dateidentified] "; }
         if ( @($highergeography!=$_POST['highergeography']) ) { $truncation = true; $truncated .= "highergeography : [$highergeography] "; }
         if ( @($highergeographyid!=$_POST['highergeographyid']) ) { $truncation = true; $truncated .= "highergeographyid : [$highergeographyid] "; }
         if ( @($specificlocality!=$_POST['specificlocality']) ) { $truncation = true; $truncated .= "specificlocality : [$specificlocality] "; }
         if ( @($prepmethod!=$_POST['prepmethod']) ) { $truncation = true; $truncated .= "prepmethod : [$prepmethod] "; }
         if ( @($format!=$_POST['preptype']) ) { $truncation = true; $truncated .= "preptype/format : [$format] "; }

         if ( @($verbatimlat!=$_POST['verbatimlat']) ) { $truncation = true; $truncated .= "verbatimlat : [$verbatimlat] "; }
         if ( @($verbatimlong!=$_POST['verbatimlong']) ) { $truncation = true; $truncated .= "verbatimlong : [$verbatimlong] "; }
         if ( @($decimallat!=$_POST['decimallat']) ) { $truncation = true; $truncated .= "decimallat : [$decimallat] "; }
         if ( @($decimallong!=$_POST['decimallong']) ) { $truncation = true; $truncated .= "decimallong : [$decimallong] "; }
         if ( @($datum!=$_POST['datum']) ) { $truncation = true; $truncated .= "datum : [$datum] "; }
         if ( @($coordinateuncertanty!=$_POST['coordinateuncertanty']) ) { $truncation = true; $truncated .= "coordinateuncertanty : [$coordinateuncertanty] "; }
         if ( @($georeferencedby!=$_POST['georeferencedby']) ) { $truncation = true; $truncated .= "georeferencedby : [$georeferencedby] "; }
         if ( @($georeferencedate!=$_POST['georeferencedate']) ) { $truncation = true; $truncated .= "georeferencedate : [$georeferencedate] "; }
         if ( @($georeferencesource!=$_POST['georeferencesource']) ) { $truncation = true; $truncated .= "georeferencesource : [$georeferencesource] "; }
         if ( @($utmzone!=$_POST['utmzone']) ) { $truncation = true; $truncated .= "utmzone : [$utmzone] "; }
         if ( @($utmeasting!=$_POST['utmeasting']) ) { $truncation = true; $truncated .= "utmeasting : [$utmeasting] "; }
         if ( @($utmnorthing!=$_POST['utmnorthing']) ) { $truncation = true; $truncated .= "utmnorthing : [$utmnorthing] "; }

         if ( @($typestatus!=$_POST['typestatus']) ) { $truncation = true; $truncated .= "typestatus : [$typestatus] "; }
         if ( @($typeconfidence!=@$_POST['typeconfidence']) ) { $truncation = true; $truncated .= "typeconfidence : [$typeconfidence] "; }
         if ( @($basionym!=$_POST['basionym']) ) { $truncation = true; $truncated .= "basionym : [$basionym] "; }
         if ( @($publication!=$_POST['publication']) ) { $truncation = true; $truncated .= "publication : [$publication] "; }
         if ( @($page!=$_POST['page']) ) { $truncation = true; $truncated .= "page : [$page] "; }
         if ( @($datepublished!=$_POST['datepublished']) ) { $truncation = true; $truncated .= "datepublished : [$datepublished] "; }
         if ( @($isfragment!=$_POST['isfragment']) ) { $truncation = true; $truncated .= "isfragment : [$isfragment] "; }
         if ( @($habitat!=$_POST['habitat']) ) { $truncation = true; $truncated .= "habitat : [$habitat] "; }
         if ( @($host!=$_POST['host']) ) { $truncation = true; $truncated .= "host : [$host] "; }
         if ( @($substrate!=$_POST['substrate']) ) { $truncation = true; $truncated .= "substrate : [$substrate] "; }
         if ( @($phenology!=$_POST['phenology']) ) { $truncation = true; $truncated .= "phenology : [$phenology] "; }
         if ( @($verbatimelevation!=$_POST['verbatimelevation']) ) { $truncation = true; $truncated .= "verbatimelevation : [$verbatimelevation] "; }
         if ( @($minelevation!=@$_POST['minelevation']) ) { $truncation = true; $truncated .= "minelevation : [$minelevation] "; }
         if ( @($maxelevation!=@$_POST['maxelevation']) ) { $truncation = true; $truncated .= "maxelevation : [$maxelevation] "; }
         if ( @($specimenremarks!=$_POST['specimenremarks']) ) { $truncation = true; $truncated .= "specimenremarks : [$specimenremarks] "; }
         if ( @($specimendescription!=$_POST['specimendescription']) ) { $truncation = true; $truncated .= "specimendescription : [$specimendescription] "; }
         if ( @($itemdescription!=$_POST['itemdescription']) ) { $truncation = true; $truncated .= "itemdescription : [$itemdescription] "; }
         if ( @($container!=$_POST['container']) ) { $truncation = true; $truncated .= "container : [$container] "; }
         if ( @($collectingtrip!=$_POST['collectingtrip']) ) { $truncation = true; $truncated .= "collectingtrip : [$collectingtrip] "; }
         if ( @($storagelocation!=$_POST['storagelocation']) ) { $truncation = true; $truncated .= "storagelocation : [$storagelocation] "; }
         if ( @($project!=$_POST['project']) ) { $truncation = true; $truncated .= "project : [$project] "; }
         if ( @($storage!=$_POST['storage']) ) { $truncation = true; $truncated .= "storage : [$storage] "; }  // subcollection

         // transcribe doesn't capture the determiner, provide a value.
         if ($determinertext=='') { $determinertext = '[data not captured]'; }
         if ($identifiedby=='') { $identifiedby = '[data not captured]'; }
         // barcode field isn't passed if disabled, value stored in barcodeval instead.
         if ($barcode=='' && strlen($barcodeval)>0 ) { $barcode = $barcodeval; }
         // same for date collected, field is normall disabled
         if ($datecollected=='' && strlen($datecollectedval)>0 ) { $datecollected = $datecollectedval; }
         // more clearly named fields
         $currentdetermination = $currentname;
         $currentdeterminationid = $currentnameid;
         $fiidentificationqualifier = $filedunderqualifier;
         $identificationqualifier = $currentqualifier;

         echo ingest();

         // Commenting out the call to save images.
         // At this time, all images should be preprocessed and exist in the database
         // If this call is ever re-enabled, the function createImageSetFromLocalFile
         // will need to be revisited so that paths and names are handled the same as the ingest process
         //echo storeImageObject($batchid,$barcode);

       break;
       default:
         echo "Unknown Action [$action]";
   }

} else {
   // if not connnected and authenticated.
   echo "Session Expired.";
}

function storeImageObject ($batchid,$barcode) {
   global $connection;
   $currentBatch = new TR_Batch();
   $currentBatch->setID($batchid);
   $pathfile = $currentBatch->getCurrentFile();
   $path= $currentBatch->getPath();
   $filename = $pathfile->filename;
   huh_image_set_custom::createImageSetFromLocalFile($path,$filename,$barcode);
}

function ingest() {
   global $connection, $debug,
   $truncation, $truncated, $collectorsid,
   $collectors,$etal,$fieldnumber,$stationfieldnumber,$accessionnumber,$verbatimdate,$datecollected,$herbariumacronym,$barcode,$provenance,
   $filedundername,$fiidentificationqualifier,$currentdetermination,$identificationqualifier,
   $filedundernameid, $currentdeterminationid,
   $highergeography,
   $specificlocality,$prepmethod,$format,$verbatimlat,$verbatimlong,$decimallat,$decimallong,$datum,
   $coordinateuncertanty,$georeferencedby,$georeferencedate,$georeferencesource,$typestatus, $basionym,
   $publication,$page,$datepublished,$isfragment,$habitat,$phenology,$verbatimelevation,$minelevation,$maxelevation,
   $identifiedby,$dateidentified,$specimenremarks,$specimendescription,$itemdescription,$container,$collectingtrip,$utmzone,$utmeasting,$utmnorthing,
   $project, $storagelocation, $storage, $namedplace,
   $exsiccati,$fascicle,$exsiccatinumber, $host, $substrate, $typeconfidence, $determinertext;

   if ($debug) { echo "ingest()"; }
   $fail = false;
   $feedback = "";

   if ($truncation) {
     $fail = true;
     $feedback = "Data truncation: $truncated";
     if ($debug) { echo $feedback; }
   }
   // handle nulls
   if ($collectors=='') { $collectors = null; }
   if ($etal=='') { $etal = null; }
   if ($fieldnumber=='') { $fieldnumber = null; }
   if ($stationfieldnumber=='') { $stationfieldnumber = null; }
   if ($accessionnumber=='') { $accessionnumber = null; }
   if ($provenance=='') { $provenance = null; }
   if ($verbatimdate=='') { $verbatimdate = null; }
   if ($datecollected=='') {
      $datecollected = null;
      $startdate = null;
      $enddate = null;
      $startdateprecision = 1;
      $enddateprecision = 1;
   } else {
      $date = new DateRangeWithPrecision($datecollected);
      if ($date->isBadValue()) {
          $fail = true;
          $feedback .= "Unrecognized date format: " . $datecollected;
      } else {
         $startdate = $date->getStartDate();
         if ($startdate=='0000-00-00' || strlen($startdate)==0 || substr($startdate,0,4)=='0000' || strpos($startdate,'-00')!==FALSE) {
             $fail = true;
             $feedback .= "Unrecognized start date [$startdate] in: " . $datecollected;
         }
         $startdateprecision = $date->getStartDatePrecision();
         $enddate = $date->getEndDate();
         if ($enddate=='0000-00-00' || substr($enddate,0,4)=='0000' || strpos($enddate,'-00')!==FALSE) {
             $fail = true;
             $feedback .= "Unrecognized end date [$enddate] in: " . $datecollected;
         }
         $enddateprecision = $date->getEndDatePrecision();
      }
   }
   if ($herbariumacronym=='') { $herbariumacronym = null; }
   if ($fiidentificationqualifier=='') { $fiidentificationqualifier = null; }
   if ($currentdetermination=='') { $currentdetermination = null; }
   if ($identificationqualifier=='') { $identificationqualifier = null; }
   if ($verbatimlat=='') { $verbatimlat = null; }
   if ($verbatimlong=='') { $verbatimlong = null; }
   if ($decimallat=='') { $decimallat = null; }
   if ($decimallong=='') { $decimallong = null; }
   if ($datum=='') { $datum = null; }
   if ($coordinateuncertanty=='') {
      $coordinateuncertanty = null;
      $maxuncertantyestunit = null;
   } else {
      $maxuncertantyestunit = 'm';
   }
   if ($georeferencedby=='') { $georeferencedby = null; }
   if ($georeferencedate=='') { $georeferencedate = null; }
   if ($georeferencesource=='') { $georeferencesource = null; }
   if ($utmzone=='') { $utmzone = null; }
   if ($utmeasting=='') { $utmeasting = null; }
   if ($utmnorthing=='') { $utmnorthing = null; }
   if ($utmeasting!=null) {
      if (preg_match('/^[0-9]{6}$/',$utmeasting)==1 && preg_match('/^[0-9]{7}$/', $utmnorthing)==1) {
         // OK, specify takes UTM easting and northing in meters, can't abstract to MGRS or USNG
      } else {
         $fail = true;
         $feedback .= "UTM Easting and northing must be in meters, there must be 6 digits in the easting and 7 in the northing.";
      }
   }

   if ($typestatus=='') { $typestatus = null; }
   if ($typeconfidence=='') { $typeconfidence = null; }
   if ($basionym=='') { $basionym = null; }
   if ($publication=='') { $publication = null; }
   if ($page=='') { $page = null; }
   if ($datepublished=='') { $datepublished = null; }
   if ($isfragment=='') { $isfragment = null; }
   if ($habitat=='') { $habitat = null; }
   if ($host=='') { $host = null; }
   if ($substrate=='') { $substrate = null; }
   if ($phenology=='') { $phenology = 'NotDetermined'; }
   if ($verbatimelevation=='') { $verbatimelevation = null; }
   if ($minelevation=='') { $minelevation = null; }
   if ($maxelevation=='') { $maxelevation = null; }
   if ($identifiedby=='') { $identifiedby = null; }
   if ($determinertext=='') { $determinertext = null; }
   if ($container=='') { $container = null; }
   if ($collectingtrip=='') { $collectingtrip = null; }
   if ($storagelocation=='') { $storagelocation = null; }
   if ($project=='') { $project = null; }
   if ($storage=='') { $storage = null; }  // subcollection
   if ($exsiccati=='') { $exsiccati = null; }
   if ($fascicle=='') { $fascicle = null; }
   if ($exsiccatinumber=='') { $exsiccatinumber = null; }
   if ($dateidentified=='') {
      $dateidentified = null;
      $dateidentifiedprecision = 1;
   } else {
      if (preg_match("/^[1-2][0-9]{3}-[0-9]{2}-[0-9]{2}$/",$dateidentified)) {
         $dateidentified = $dateidentified;
         $dateidentifiedprecision = 1;
      } else {
         if (preg_match("/^[1-2][0-9]{3}-[0-9]{2}$/",$dateidentified)) {
            $dateidentified = $dateidentified . "-01";
            $dateidentifiedprecision = 2;
         } else {
            if (preg_match("/^[1-2][0-9]{3}$/",$dateidentified)) {
               $dateidentified = $dateidentified . "-01-01";
               $dateidentifiedprecision = 3;
            } else {
               $fail = true;
               $feedback .= "Unrecognized date format: " . $dateidentified ;
            }
         }
      }
   }
   if ($specimenremarks=='') { $specimenremarks = null; }
   if ($specimendescription=='') { $specimendescription = null; }
   if ($itemdescription=='') { $itemdescription = null; }

   $latlongtype = 'point';
   if ($decimallat==null && $decimallong==null) {
      $latlongtype=null;
   }

   $df = "";
   if ($debug) {
      $df.=" ";
      $df.= "collectors=[$collectors] ";
      $df.= "collectorsid=[$collectorsid] ";
      $df.= "etal=[$etal] ";
      $df.= "fieldnumber=[$fieldnumber] ";
      $df.= "stationfieldnumber=[$stationfieldnumber] ";
      $df.= "accessionnumber=[$accessionnumber] ";
      $df.= "verbatimdate=[$verbatimdate] ";
      $df.= "datecollected=[$datecollected] ";
      $df.= "herbariumacronym=[$herbariumacronym] ";
      $df.= "barcode=[$barcode] ";  // required
      $df.= "provenance=[$provenance] ";
      $df.= "filedundername=[$filedundername] ";  // required
      $df.= "fiidentificationqualifier=[$fiidentificationqualifier] ";
      $df.= "currentdetermination=[$currentdetermination] ";
      $df.= "identificationqualifier=[$identificationqualifier] ";
      $df.= "highergeography=[$highergeography] ";  // required
      $df.= "specificlocality=[$specificlocality] ";  // required
      $df.= "prepmethod=[$prepmethod] "; // required
      $df.= "format=[$format] ";  // required
      $df.= "verbatimlat=[$verbatimlat] ";
      $df.= "verbatimlong=[$verbatimlong] ";
      $df.= "decimallat=[$decimallat] ";
      $df.= "decimallong=[$decimallong] ";
      $df.= "datum=[$datum] ";
      $df.= "coordinateuncertanty=[$coordinateuncertanty] ";
      $df.= "georeferencedby=[$georeferencedby] ";
      $df.= "georeferencedate=[$georeferencedate] ";
      $df.= "georeferencesource=[$georeferencesource] ";
      $df.= "typestatus=[$typestatus] ";
      $df.= "typeconfidence=[$typeconfidence] ";
      $df.= "basionym=[$basionym] ";
      $df.= "publication=[$publication] ";
      $df.= "page=[$page] ";
      $df.= "datepublished=[$datepublished] ";
      $df.= "isfragment=[$isfragment] ";
      $df.= "habitat=[$habitat] ";
      $df.= "phenology=[$phenology] ";
      $df.= "verbatimelevation=[$verbatimelevation] ";
      $df.= "namedplace=[$namedplace] ";
      $df.= "minelevation=[$minelevation] ";
      $df.= "maxelevation=[$maxelevation] ";
      $df.= "identifiedby=[$identifiedby] ";
      $df.= "determinertext=[$determinertext] ";
      $df.= "dateidentified=[$dateidentified] ";
      $df.= "container=[$container] ";
      $df.= "collectingtrip=[$collectingtrip] ";
      $df.= "storagelocation=[$storagelocation] ";
      $df.= "project=[$project] ";
      $df.= "host=[$host] ";
      $df.= "substrate=[$substrate] ";
      $df.= "exsiccati=[$exsiccati] ";
      $df.= "fascicle=[$fascicle] ";
      $df.= "exsiccatinumber=[$exsiccatinumber] ";
      $df.= "specimenremarks=[$specimenremarks] ";
      $df.= "specimendescription=[$specimendescription] ";
      $df.= "itemdescription=[$itemdescription] ";
   }

   if ($barcode=='') {
      $fail = true;
      $feedback .= "Missing a required value: ";
      $feedback.= "Barcode.";
   }

   // zero pad barcode up to 8 digits if needed
   $barcode = str_pad($barcode,8,"0",STR_PAD_LEFT);
   // Test for validly formed barcode
   if (!preg_match("/^[0-9]{8}$/",$barcode)) {
      $fail = true;
      $feedback .= "Barcode [$barcode] is invalid.  Must be zero padded with exactly 8 digits: ";
   }

  // Test for required elements:
   if ($highergeography=='' || $herbariumacronym=='' || $filedundername=='' || $prepmethod=='' || $format=='' || $specificlocality=='' || $barcode=='' || $collectors=='' ) {
      $fail = true;
      $feedback .= "Missing a required value: ";
      if ($highergeography=='') {
         $feedback.= "Geography. ";
      }
      if ($herbariumacronym=='') {
         $feedback.= "Herbarium. ";
      }
      if ($filedundername=='') {
         $feedback.= "Filed under name. ";
      }
      if ($prepmethod=='') {
         $feedback.= "Prep Method. ";
      }
      if ($format=='') {
         $feedback.= "Format. ";
      }
      if ($specificlocality=='') {
         $feedback.= "Locality.";
      }
      if ($collectors=='') {
        $feedback.= "Collector.";
      }
   }

   $currentuserid = $_SESSION["agentid"];

   $link = "";

   if (!$fail) {
      //  persist
      $adds = "";

      // begin transaction
      $connection->autocommit(false);

         $exists = FALSE;

         // check for duplicate barcode
         $sql = "select count(*) ct from fragment where identifier = ? union select count(*) ct from preparation where identifier = ? ";
         $statement = $connection->prepare($sql);
         if ($statement) {
            $statement->bind_param("ss", $barcode,$barcode);
            $statement->execute();
            $statement->bind_result($barcodematchcount);
            $statement->store_result();
            if ($statement->num_rows>0) {
               if ($statement->fetch()) {
                  if ($statement->num_rows==1) {
                      if ($barcodematchcount!='0') {
         		         $exists = TRUE;
                         $feedback.= "Barcode [$barcode] in database.";
                      }
                  } else {
                     $fragmatchcount = $barcodematchcount;
                     if ($statement->fetch()) {
                         if ($fragmatchcount!='0' || $barcodematchount!='0') {
         		            $exists = TRUE;
                            $feedback.= "Barcode [$barcode] in database.";
                         }
                     } else {
                        $fail = true;
                        $feedback.= "Query Error " . $connection->error . " " .  $sql;
                     }
                  }
               } else {
                  $fail = true;
                  $feedback.= "Query Error " . $connection->error . " " . $sql;
               }
            } else {
               $fail = true;
               $feedback.= "Query error. Returned other than two rows [" . $statement->num_rows . "] on check for barcode: " . $barcode;
            }
            $statement->free_result();
         } else {
            $fail = true;
            $feedback.= "Query error: " . $connection->error . " " . $sql;
         }

         if (!$fail) {
            if ($exists) {
               // attempt to update existing specimen record
               $sql = "select f.fragmentid, f.collectionobjectid, ce.collectingeventid, l.localityid " .
                       "from fragment f left join collectionobject co on f.collectionobjectid = co.collectionobjectid " .
                       "left join collectingevent ce on co.collectingeventid = ce.collectingeventid " .
                       "left join locality l on ce.localityid = l.localityid " .
                       "where f.identifier = ? ";
                $statement = $connection->stmt_init();
                $statement = $connection->prepare($sql);
                if ($statement) {
                    $statement->bind_param("s", $barcode);
                    $statement->execute();
                    $statement->bind_result($fragmentid,$collectionobjectid,$collectingeventid,$localityid);
                    $statement->store_result();
                    if ($statement->num_rows==1) {
                       if ($statement->fetch()) {
                           // set project if it isn't set.
                           if($project!="") {
                               $sql = "select count(*) as projectcount from project_colobj where projectid in (select projectid from project where projectname = ?) and collectionobjectid = ? ";
                               $statement1 = $connection->prepare($sql);
                               if ($statement1) {
                                   $statement1->bind_param("si", $project,$collectionobjectid);
                                   $statement1->execute();
                                   $statement1->bind_result($projectcount);
                                   $statement1->store_result();
                                   $addProject = false;
                                   if ($statement1->fetch()){
                                      if ($projectcount==0) { $addProject = true; } else { $feedback.="Project exists."; }
                                   } else {
                                      $fail = true;
                                      $feedback.= "Query Error looking up project. " . $connection->error  . " ";
                                   }
                                   $statement1->free_result();
                                   $statement1->close();
                                   if ($addProject==true) {
                                       $sql = "insert into project_colobj(projectid, collectionobjectid) values ((select projectid from project where projectname= ? ), ? )";
                                       $statement1 = $connection->prepare($sql);
                                       if ($statement1) {
                                          $statement1->bind_param("si", $project, $collectionobjectid);
                                          $statement1->execute();
                                          $rows = $connection->affected_rows;
                                          if ($rows==1) { $feedback = $feedback . " Added Project. "; }
                                       } else {
                                          $fail = true;
                                          $feedback.= "Query Error looking up project_colobj. " . $connection->error  . " ";
                                       }
                                       $statement1->close();
                                   }
                               } else {
                                   $fail = true;
                                   $feedback.= "Query Error looking up project. " . $connection->error  . " ";
                               }
                           }
                           if ($collectingeventid!=null) {
                               $countco = countCollectionObjectsForEvent($collectingeventid);
                               if ($countco < 0) {
                                   $fail = true;
                                   $feedback.= "Query Error looking up collectingevent " . $connection->error .  " ";
                               } elseif ($countco==0) {
                                   $fail = true;
                                   $feedback.= "Error: no collection objects found for collectingevent. ";
                               } elseif ($countco==1) {
                                  $statement1 = $connection->stmt_init();

                                  $sql = "update collectingevent set stationfieldnumber=?, remarks=?, startdate=?, startdateprecision=?, enddate=?, enddateprecision=?, verbatimdate=?, version=version+1, modifiedbyagentid=?, timestampmodified=now() where collectingeventid = ? ";
                                  $statement1 = $connection->prepare($sql);
                                  if ($statement1) {
                                      $statement1->bind_param("sssisisii", $stationfieldnumber, $habitat, $startdate, $startdateprecision, $enddate, $enddateprecision, $verbatimdate, $currentuserid, $collectingeventid);
                                      $statement1->execute();
                                      $rows = $connection->affected_rows;
                                      if ($rows==1) { $feedback = $feedback . " Updated CollectingEvent. "; }
                                      if ($rows==0) { $feedback = $feedback . " CollectingEvent unchanged. "; }
                                  } else {
                                      $fail = true;
                                      $feedback.= "Query Error modifying locality. " . $connection->error  . " ";
                                  }

                                  $statement1->close();
                               } else {
                                  $sql = "insert into collectingevent ( TimestampCreated, TimestampModified, Version, EndDate, EndDatePrecision, EndDateVerbatim, EndTime, Method, Remarks, StartDate , StartDatePrecision, StartDateVerbatim, StartTime, StationFieldNumber, VerbatimDate, VerbatimLocality, Visibility, LocalityID, CollectingEventAttributeID, DisciplineID, ModifiedByAgentID, CollectingTripID, CreatedByAgentID, VisibilitySetByID ) select TimestampCreated, TimestampModified, Version, EndDate, EndDatePrecision, EndDateVerbatim, EndTime, Method, Remarks, StartDate , StartDatePrecision, StartDateVerbatim, StartTime, StationFieldNumber, VerbatimDate, VerbatimLocality, Visibility, LocalityID, CollectingEventAttributeID, DisciplineID, ModifiedByAgentID, CollectingTripID, CreatedByAgentID, VisibilitySetByID from collectingevent where collectingeventid = ? ";
                                  // more than one collection object, need to create new collecting event
                                  $statement = $connection->prepare($sql);
                                  if ($statement) {
                                      $statement->bind_param("i", $collectingeventid);
                                      $statement->execute();
                                      $rows = $connection->affected_rows;
                                      if ($rows==1) {
                                         $newcollectingeventid = $statement->insert_id;
                                         $feedback = $feedback . " Cloned CollectingEvent to [$newcollectingeventid]. ";
                                      }
                                      $sql = "update collectionobject set collectingeventid = ?, version=version+1 where collectionobjectid = ?";
                                      $statement = $connection->prepare($sql);
                                      if ($statement) {
                                          $statement->bind_param("ii", $newcollectingeventid,$collectionobjectid);
                                          $statement->execute();
                                          $rows = $connection->affected_rows;
                                          if ($rows==1) { $feedback = $feedback . " Relinked collectionobject. "; }
                                          $sql = "update collectingevent set stationfieldnumber=?, remarks=?, startdate=?, startdateprecision=?, enddate=?, enddateprecision=?, verbatimdate=?, version=version+1, modifiedbyagentid=?, timestampmodified=now() where collectingeventid = ? ";
                                          $statement1 = $connection->prepare($sql);
                                          if ($statement1) {
                                              $statement1->bind_param("sssisisii", $stationfieldnumber, $habitat, $startdate, $startdateprecision, $enddate, $enddateprecision, $verbatimdate, $currentuserid, $newcollectingeventid);
                                              $statement1->execute();
                                              $rows = $connection->affected_rows;
                                              if ($rows==1) { $feedback = $feedback . " Updated CollectingEvent. "; }
                                              if ($rows==0) { $feedback = $feedback . " CollectingEvent unchanged. "; }
                                              $collectingeventid = $newcollectingeventid;
                                          } else {
                                              $fail = true;
                                              $feedback.= "Query Error splitting/modifying locality. " . $connection->error . " ";
                                          }
                                      }
                                   } else {
                                        $fail = true;
                                        $feedback.= "Query Error splitting collectingevent. " . $connection->error . " ";
                                   }
                               }
                               if (!$fail && $collectingeventid==null) {
                                   $fail = true;
                                   $feedback.= "Error: No collectingevent found/created.";
                               }

                               // lookup agentid for collector
                               if (strlen(trim($collectorsid))==0 && strlen($collectors)>0) {
                                  $sql = "select distinct agentid from agentvariant where name = ? and vartype = 4 ";
                                  $param = "s";
                                  $statement = $connection->prepare($sql);
                                  if ($statement) {
                                     $statement->bind_param($param,$collectors);
                                     $statement->execute();
                                     $statement->bind_result($agentid);
                                     $statement->store_result();
                                     if ($statement->num_rows==1) {
                                        if ($statement->fetch()) {
                                           // retrieves collector.agentid
                                           $collectorsid = $agentid;
                                        } else {
                                           $fail = true;
                                           $feedback.= "Query Error " . $connection->error;
                                        }
                                     } else {
                                        $fail = true;
                                        $feedback.= "No Match for collector agent: " . $collectors;
                                     }
                                     $statement->free_result();
                                     $statement->close();
                                  } else {
                                     $fail = true;
                                     $feedback.= "Query error: " . $connection->error . " " . $sql;
                                  }
                               }

                               // add/update collector
                               if ($collectingeventid!=null) {
                                  $sql = "select collectorid from collector where collectingeventid = ? ";
                                  $statement = $connection->prepare($sql);
                                  if ($statement) {
                                        $statement->bind_param("i", $collectingeventid);
                                        $statement->execute();
                                        $statement->bind_result($collectorid);
                                        $statement->store_result();
                                        if ($statement->num_rows>0) {
                                            if ($statement->fetch()) {
                                               $sql = "update collector set etal = ?, agentid = ?, version=version+1, modifiedbyagentid=?, timestampmodified=now() where collectorid = ? ";
                                               $s2 = $connection->prepare($sql);
                                               $s2->bind_param("siii",$etal,$collectorsid,$currentuserid,$collectorid);
                                               $s2->execute();
                                               $rows = $connection->affected_rows;
                                               if ($rows==1) {
                                                  $feedback = $feedback . " Updated Collector. ";
                                               } else {
                                                  $fail = true;
                                                  $feedback.= "Error updating collector. " . $connection->error . " ";
                                               }
                                               $s2->close();
                                            } else {
                                               $fail = true;
                                               $feedback.= "Query Error locating collector. " . $connection->error . " ";
                                            }
                                        } else {
                                            $sql = "insert into collector (etal,agentid,timestampcreated,version,createdbyagentid) values (?,?,now(),0,?); ";
                                            $s2 = $connection->prepare($sql);
                                            $s2->bind_param("sii",$etal,$collectorsid,$currentuserid);
                                            $s2->execute();
                                            $rows = $connection->affected_rows;
                                            if ($rows==1) {
                                               $collectorid = $statement->insert_id;
                                               $feedback = $feedback . "Added collector [$collectorid]. ";
                                            }
                                            $s2->close();
                                        }
                                        $statement->free_result();
                                        $statement->close();
                                   } else {
                                      $fail = true;
                                      $feedback.= "Query Error looking up collector. " . $connection->error . " ";
                                  }
                               }
                           } // has collector

                           if ($localityid!=null) {
                               $countco = countCollectionObjectsForLocality($localityid);
                               if ($countco < 0) {
                                   $fail = true;
                                   $feedback.= "Query Error looking up locality " . $connection->error .  " ";
                               } elseif ($countco==0) {
                                   $fail = true;
                                   $feedback.= "Error: no collection objects found for locality. ";
                               } elseif ($countco==1) {
                                  $statement1 = $connection->stmt_init();
                                  $sql = "update locality set localityname = ?, verbatimelevation = ?, namedplace=?, version=version+1, modifiedbyagentid=?, timestampmodified=now() where localityid = ? ";
                		              $statement1 = $connection->prepare($sql);
                                  if ($statement1) {
                                      $statement1->bind_param("sssii", $specificlocality, $verbatimelevation, $namedplace, $currentuserid, $localityid);
                                      $statement1->execute();
                                      $rows = $connection->affected_rows;
                                      if ($rows==1) { $feedback = $feedback . " Updated Locality. "; }
                                      if ($rows==0) { $feedback = $feedback . " Locality unchanged. "; }
                                  } else {
                                      $fail = true;
                                      $feedback.= "Query Error modifying locality. " . $connection->error  . " ";
                                  }
                               } else {
                                  // more than one collection object, need to create new locality
                                  $sql = "insert into locality (TimestampCreated, TimestampModified, Version, Datum, ElevationAccuracy, ElevationMethod, GML, GUID, Lat1Text, Lat2Text, LatLongAccuracy, LatLongMethod, LatLongType, Latitude1, Latitude2, LocalityName, Long1Text, Long2Text, Longitude1, Longitude2, MaxElevation, MinElevation, NamedPlace, OriginalElevationUnit, OriginalLatLongUnit, RelationToNamedPlace, Remarks, ShortName, SrcLatLongUnit, VerbatimElevation, Visibility, DisciplineID, ModifiedByAgentID, VisibilitySetByID, CreatedByAgentID, GeographyID) select TimestampCreated, TimestampModified, Version, Datum, ElevationAccuracy, ElevationMethod, GML, GUID, Lat1Text, Lat2Text, LatLongAccuracy, LatLongMethod, LatLongType, Latitude1, Latitude2, LocalityName, Long1Text, Long2Text, Longitude1, Longitude2, MaxElevation, MinElevation, NamedPlace, OriginalElevationUnit, OriginalLatLongUnit, RelationToNamedPlace, Remarks, ShortName, SrcLatLongUnit, VerbatimElevation, Visibility, DisciplineID, ModifiedByAgentID, VisibilitySetByID, CreatedByAgentID, GeographyID from locality where localityid = ?";
                		              $statement = $connection->prepare($sql);
                                  if ($statement) {
                                      $statement->bind_param("i", $localityid);
                                      $statement->execute();
                                      $rows = $connection->affected_rows;
                                      if ($rows==1) {
                                         $newlocalityid = $statement->insert_id;
                                         $feedback = $feedback . " Cloned Locality to [$newlocalityid]. ";
                                      }
                                      $sql = "update collectingevent set localityid = ? where collectingeventid = ?";
                		              $statement = $connection->prepare($sql);
                                      if ($statement) {
                                          $statement->bind_param("ii", $newlocalityid, $collectingeventid);
                                          $statement->execute();
                                          $rows = $connection->affected_rows;
                                          if ($rows==1) { $feedback = $feedback . " Relinked collectingevent. "; }
                                          $sql = "update locality set localityname = ?, verbatimelevation = ?, namedplace=?, version=version+1, modifiedbyagentid=?, timestampmodified=now() where localityid = ? ";
                        		          $statement = $connection->prepare($sql);
                                          if ($statement) {
                                              $statement->bind_param("sssii", $specificlocality, $verbatimelevation, $namedplace, $currentuserid, $newlocalityid);
                                              $statement->execute();
                                              $rows = $connection->affected_rows;
                                              if ($rows==1) { $feedback = $feedback . " Updated Locality. "; }
                                              if ($rows==0) { $feedback = $feedback . " Locality unchanged. "; }
                                          } else {
                                              $fail = true;
                                              $feedback.= "Query Error splitting/modifying locality. " . $connection->error . " ";
                                          }
                                          $statement->free_result();
                                          $statement->close();
                                      }
                                   } else {
                                        $fail = true;
                                        $feedback.= "Query Error splitting locality. " . $connection->error . " ";
                                   }
                               }
                           } // end localityid

                           // look up/update determinations.
                           // are the determinations the same
                           $namesidentical = FALSE;
                           if ($filedundername==$currentdetermination) {
                              // See BugID: 588 if both names are the same, only add filed under, and mark it as current.
                              $namesidentical = TRUE;
                              // Consequences:  If the names are the same: One determination record with both filed under and current flags set.
                              // If the names are different, two records, one filed under name without
                              // a determiner, and one current name (if creating here, both lack determiner and date determined)
                           }

                           // make sure that we have taxonid values.
                           if ($filedundernameid==null||strlen(trim($filedundernameid))==0) {
                               $filedundernameid = huh_taxon_custom::lookupTaxonIdForName($filedundername);
                           }
                           if ($currentdeterminationid==null||strlen(trim($currentdeterminationid))==0) {
                               $currentdeterminationid = huh_taxon_custom::lookupTaxonIdForName($currentdetermination);
                           }
                           // look up the agentid for data not captured agent.
                           $determinerid = null;
                           $sql = "select distinct agentid from agentvariant where name = '[data not captured]' and vartype = 4 ";
                           $statement = $connection->prepare($sql);
                           if ($statement) {
                              $statement->execute();
                              $statement->bind_result($determinerid);
                              $statement->store_result();
                              if ($statement->num_rows==1) {
                                 if ($statement->fetch()) {
                                    // retrieves determiner agentid
                                 } else {
                                    $fail = true;
                                    $feedback.= "Query Error " . $connection->error;
                                 }
                              } else {
                                 $fail = true;
                                 $feedback.= "No Match for agent: '[data not captured]' " . $identifiedby;
                              }
                              $statement->free_result();
                              $statement->close();
                           } else {
                              $fail = true;
                              $feedback.= "Error preparing lookup agent query " . $connection->error;
                           }

                           // what is the number of determinations in the database now for this fragment?
                           $detcount = huh_determination_custom::countDeterminations($fragmentid);
                           if ($detcount==0) {
                              // Insert the determination(s) provided.
                              if ($namesidentical===FALSE) {
                                 // only add the filed under name if the names are different.
                                 // add the filed under name as a separate determination from the current determination.

                                 // yesno1 = isLabel (no)
                                 // yesno2 = isFragment (of type) (no)
                                 // yesno3 = isFiledUnder (yes)
                                 // iscurrent = isCurrent (no)
                                   $sql = "insert into determination (taxonid, fragmentid,createdbyagentid, qualifier, " .
                                           " yesno1, yesno2, yesno3, iscurrent,timestampcreated, version,collectionmemberid) " .
                                           " values (?,?,?,?,0,0,1,0,now(),0,4) ";
                                   $statement = $connection->prepare($sql);
                                   if ($statement) {
                                      $statement->bind_param('iiis', $filedundernameid,$fragmentid,$currentuserid,$fiidentificationqualifier);
                                      if ($statement->execute()) {
                                         $determinationid = $statement->insert_id;
                                         $adds .= "det=[$determinationid]";
                                         $pluraldet = "s";  // for output, added more than one determination.
                                      } else {
                                         $fail = true;
                                         $feedback.= "Unable to save filed under name: " . $connection->error;
                                      }
                                      $statement->free_result();
                                   } else {
                                      $fail = true;
                                      $feedback.= "Query error: " . $connection->error . " " . $sql;
                                   }
                                }
                                if (!$fail) {
                                   // Current determination
                                   // Always add.  May also be filed under name.

                                   // yesno1 = isLabel (yes)
                                   $islabel = 1;  // we are capturing it in transcription, so must be label name
                                   // yesno2 = isFragment (of type) (no)
                                   // yesno3 = isFiledUnder (no) unless namesidentical, then (yes)
                                   $isfiledunder = 0;
                                   if ($namesidentical===TRUE) {
                                      $isfiledunder = 1;
                                   }
                                   // iscurrent = isCurrent (yes)
                                   $sql = "insert into determination (taxonid, fragmentid,createdbyagentid, qualifier, determinerid, determineddate, determineddateprecision, " .
                                                    " yesno1, yesno2, yesno3, iscurrent,timestampcreated, version,collectionmemberid, text1) " .
                                                    " values (?,?,?,?,?,?,?,?,0,?,1,now(),0,4,?) ";
                                   $statement = $connection->prepare($sql);
                                   if ($statement) {
                                      $statement->bind_param('iiisisiiis', $currentdeterminationid, $fragmentid, $currentuserid, $identificationqualifier, $determinerid, $dateidentified, $dateidentifiedprecision, $islabel, $isfiledunder, $determinertext);
                                      if ($statement->execute()) {
                                         $determinationid = $statement->insert_id;
                                         $adds .= "det=[$determinationid]";
                                      } else {
                                         $fail = true;
                                         $feedback.= "Unable to save current determination: " . $connection->error;
                                      }
                                      $statement->free_result();
                                   } else {
                                      $fail = true;
                                      $feedback.= "Query error: " . $connection->error . " " . $sql;
                                   }

                                }
                                if (!$fail) {
                                    $feedback.= "Added Determination$pluraldet";
                                    if ($debug) {  $feedback.=" $adds "; }
                                }
                                // end no determinations currently in the database.
                           } else {
                               // one or more determinations currently exist in the database, check if we should insert or update.
                               // is the currentdetermination consistent with rapid entry?
                               // is the filedunderdetermination consistent with rapid entry?
                               $adds = "";
                               $filedunder = huh_determination_custom::lookupFiledUnderDetermination($fragmentid);
                               $updatefiledunder = false;
                               if ( huh_determination_custom::consistentWithTranscribeCapture($filedunder)) {
                                    // ok to update
                                    $existingfiledundername = $filedunder["taxonname"];
                                    $existingfiledundernameid = $filedunder["taxonid"];
                                    $existingfiledunderqualifier = $filedunder["qualifier"];
                                    $existingfiledunderdetermination = $filedunder["determinationid"];
                                    $updatefiledunder = true;
                               }
                               $current = huh_determination_custom::lookupCurrentDetermination($fragmentid);
                               $updatecurrent = false;
                               if ( huh_determination_custom::consistentWithTranscribeCapture($current)) {
                                    // ok to update
                                    $existingcurrentname = $current["taxonname"];
                                    $existingcurrentnameid = $current["taxonid"];
                                    $existingidentificationqualifier = $current["qualifier"];
                                    $existingcurrentdetermination = $current["determinationid"];
                                    $updatecurrent = true;
                               }

                               // Insert/update determinations.
                               if ($updatefiledunder && !$fail) {
                                   // Update filedunder determination
                                   if ($existingfiledundernameid!=$filedundernameid && $existingfiledunderqualifier!=$fiidentificationqualifier) {
                                      $sql = "update determination set taxonid = ?, qualifier = ?, version=version+1,  modifiedbyagentid=?, timestampmodified=now() where determinationid = ? ";
                                      $statement = $connection->prepare($sql);
                                      if ($statement) {
                                         $statement->bind_param("isii",$filedundernameid,$fiidentificationqualifier,$currentuserid,$existingfiledunderdetermination);
                                         $statement->execute();
                                         $statement->close();
                                      } else {
                                         $fail = true;
                                         $feedback.= "Error preparing update determination query " . $connection->error;
                                      }
                                   } else {
                                      $feedback.= "Filed Under unchanged. ";
                                   }
                               } else {
                                   if ($namesidentical===FALSE) {
                                      // insert new filedunder
                                      $sql = "update determination set iscurrent = false, version=version+1,  modifiedbyagentid=?, timestampmodified=now() where determinationid = ? and iscurrent = true ";
                                      $statement = $connection->prepare($sql);
                                      if ($statement) {
                                         $statement->bind_param("ii",$currentuserid, $existingfiledunderdetermination);
                                         $statement->execute();
                                         $statement->close();
                                      } else {
                                         $fail = true;
                                         $feedback.= "Error preparing update determination query " . $connection->error;
                                      }
                                   }
                                   // yesno1 = isLabel (no)
                                   // yesno2 = isFragment (of type) (no)
                                   // yesno3 = isFiledUnder (yes)
                                   // iscurrent = isCurrent (no)
                                   $sql = "insert into determination (taxonid, fragmentid,createdbyagentid, qualifier, " .
                                           " yesno1, yesno2, yesno3, iscurrent,timestampcreated, version,collectionmemberid) " .
                                           " values (?,?,?,?,0,0,1,0,now(),0,4) ";
                                   $statement = $connection->prepare($sql);
                                   if ($statement) {
                                      $statement->bind_param('iiis', $filedundernameid,$fragmentid,$currentuserid,$fiidentificationqualifier);
                                      if ($statement->execute()) {
                                         $determinationid = $statement->insert_id;
                                         $adds .= "det=[$determinationid]";
                                         $pluraldet = "s";  // for output, added more than one determination.
                                      } else {
                                         $fail = true;
                                         $feedback.= "Unable to save filed under name: " . $connection->error;
                                      }
                                      $statement->free_result();
                                   } else {
                                      $fail = true;
                                      $feedback.= "Query error: " . $connection->error . " " . $sql;
                                   }
                               }
                               if ($updatecurrent && !$fail) {
                                   // Update current determination
                                   if ($existingcurrentnameid!=$currentdeterminationid && $existingidentificationqualifier!=$identificationqualifier) {
                                      $sql = "update determination set taxonid = ?, qualifier = ?, version=version+1, modifiedbyagentid=?, timestampmodified=now()  where determinationid = ? ";
                                      $statement = $connection->prepare($sql);
                                      if ($statement) {
                                         $statement->bind_param("isii",$currentdeterminationid,$identificationqualifier,$currentuserid,$existingcurrentdetermination);
                                         $statement->execute();
                                         $statement->close();
                                      } else {
                                         $fail = true;
                                         $feedback.= "Error preparing update determination query " . $connection->error;
                                      }
                                   } else {
                                      $feedback.= "Current unchanged. ";
                                   }
                               } else {
                                   // insert new current
                                   $sql = "update determination set iscurrent = false, version=version+1,  modifiedbyagentid=?, timestampmodified=now() where determinationid = ? and iscurrent=true ";
                                   $statement = $connection->prepare($sql);
                                   if ($statement) {
                                      $statement->bind_param("ii",$currentuserid,$existingcurrentdetermination);
                                      $statement->execute();
                                      $statement->close();
                                   } else {
                                      $fail = true;
                                      $feedback.= "Error preparing update determination query " . $connection->error;
                                   }

                                   // yesno1 = isLabel (yes)
                                   $islabel = 1;  // we are capturing it in transcription, so must be label name
                                   // yesno2 = isFragment (of type) (no)
                                   // yesno3 = isFiledUnder (no) unless namesidentical, then (yes)
                                   $isfiledunder = 0;
                                   if ($namesidentical===TRUE) {
                                      $isfiledunder = 1;
                                   }
                                   // iscurrent = isCurrent (yes)
                                   $sql = "insert into determination (taxonid, fragmentid,createdbyagentid, qualifier, determinerid, determineddate, determineddateprecision, " .
                                                    " yesno1, yesno2, yesno3, iscurrent,timestampcreated, version,collectionmemberid, text1) " .
                                                    " values (?,?,?,?,?,?,?,?,0,?,1,now(),0,4,?) ";
                                   $statement = $connection->prepare($sql);
                                   if ($statement) {
                                      $statement->bind_param('iiisisiiis', $currentdeterminationid, $fragmentid, $currentuserid, $identificationqualifier, $determinerid, $dateidentified, $dateidentifiedprecision, $islabel, $isfiledunder, $determinertext);
                                      if ($statement->execute()) {
                                         $determinationid = $statement->insert_id;
                                         $adds .= "det=[$determinationid]";
                                      } else {
                                         $fail = true;
                                         $feedback.= "Unable to save current determination: " . $connection->error;
                                      }
                                      $statement->free_result();
                                   } else {
                                      $fail = true;
                                      $feedback.= "Query error: " . $connection->error . " " . $sql;
                                   }

                                }
                                if (!$fail) {
                                    if ($adds!="") {
                                       $feedback.= "Added Determination$pluraldet";
                                       if ($debug) {  $feedback.=" $adds "; }
                                    }
                                }
                           } // end handle existing determinations


                       } else {
                          //  statement.fetch to retrieve fragmentid, collectionobjectid, collectingeventid, localityid failed.
                          $fail = true;
                          $feedback.= "Query Error " . $connection->error . " " .  $sql;
                       }

                       // done one and only one fragment
                   } elseif ($statement->num_rows==0) {
                       // zero rows matching provided barcode (on fragment.identifier).

                       // check for preparation barcode.
                       $sql = "select preparationid from preparation where identifier = ? ";
                       $statement->bind_param("s", $barcode);
                       $statement->execute();
                       $statement->bind_result($preparationid);
                       $statement->store_result();
                       if ($statement->$fetch) {
                          $feedback.="preparationid=[$preparationid] ";
                       }
                       $statement->free_result();
                       $statement->close();
                       // TODO: add support for barcode on prepration

                       $fail = true;
                       $feedback.= "Barcode on preparation not yet supported. Enter in Specify. ";

                   } else {
                      // error condition, more than fragment with barcode.
                      $fail = true;
                      $feedback.= "Query error. Returned other than one row [" . $statement->num_rows . "] on check for barcode: " . $barcode;
                   }
               } else {
                  $fail = true;
                  $feedback.= "Query error 503." . $connection->error . " " . $sql;
               }


            } else {
               // create new specimen record

               $feedback = ingestCollectionObject();


            } // end: exists
         } // end: not fail, check if barcode exists

   } // end: not fail, barcode to check

   if ($fail) {
      $connection->rollback();
      @$feedback = "<div style='background-color: #FF8695;'><strong>Save Failed: $feedback</strong> $adds $df</div>" ;
   } else {
      $connection->commit();
      if ($debug) {
         $feedback .= " $adds";
      }
      $feedback = "<div style='background-color: #B3FF9F;'>OK $link $feedback $df</div>";
   }
   //$connection->close();

   return $feedback;

}  // end ingest


function lookupDataForBarcode($barcode) {
   global $connection;
   $result = array();

   $barcode = str_pad($barcode,8,"0",STR_PAD_LEFT);

   $result['barcode']=$barcode;

   $huh_fragment = new huh_fragment();
   $matches = $huh_fragment->loadArrayByIdentifier($barcode);
   $num_matches = count($matches);
   $result['num_matches'] = $num_matches;
   if ($num_matches==1) {
       $result['status'] = "FOUND";
       $match = $matches[0];
       $match->load($match->getFragmentID());
       $result['prepmethod'] = $match->getPrepMethod();

       // get filedundername, currentname, filedunderqualifier, currentqualifier
       $filedunder = huh_determination_custom::lookupFiledUnderDetermination($match->getFragmentID());
       $result['filedundername'] = $filedunder["taxonname"];
       $result['filedundernameid'] = $filedunder["taxonid"];
       $result['filedunderqualifier'] = $filedunder["qualifier"];
       $current = huh_determination_custom::lookupCurrentDetermination($match->getFragmentID());
       $result['currentname'] = $current["taxonname"];
       $result['currentnameid'] = $current["taxonid"];
       $result['currentqualifier'] = $current["qualifier"];

       $related = $match->loadLinkedTo();
       $rcolobj = $related['CollectionObjectID'];
       $rprep = $related['PreparationID'];
       $rcolobj->load($rcolobj->getCollectionObjectID());
       $rprep->load($rprep->getPreparationID());
       $result['formatid'] = $rprep->getPrepTypeID();
       $related = $rprep->loadLinkedTo();
       $rpreptype = $related['PrepTypeID'];
       $rpreptype->load($rpreptype->getPrepTypeID());
       $result['format'] = $rpreptype->getName();
       $result['created'] = $rcolobj->getTimestampCreated();
       $related = $rcolobj->loadLinkedTo();
       $proj = new huh_project_custom();
       $result['project'] = $proj->getFirstProjectForCollectionObject($rcolobj->getCollectionObjectID());
       $rcoleve = $related['CollectingEventID'];
       $rcoleve->load($rcoleve->getCollectingEventID());
       $result['stationfieldnumber'] = $rcoleve->getStationFieldNumber();
       $result['datecollected'] = dateBitsToString($rcoleve->getStartDate(), $rcoleve->getStartDatePrecision(), $rcoleve->getEndDate(), $rcoleve->getEndDatePrecision());
       $result['verbatimdate'] = $rcoleve->getVerbatimDate();
       $result['habitat'] = $rcoleve->getRemarks();
       $related = $rcoleve->loadLinkedTo();
       $rcollector = $rcoleve->loadLinkedFromcollector();
       $collectoragentid = $rcollector->getAgentID();
       $result['collectoragentid'] = $collectoragentid;
       $result['collectors'] = huh_collector_custom::getCollectorVariantName($collectoragentid);
       $result['etal'] = $rcollector->getEtAl();
       $rlocality = $related['LocalityID'];
       $rlocality->load($rlocality->getLocalityID());
       $result['namedPlace'] = $rlocality->getNamedPlace();
       $result['verbatimElevation'] = $rlocality->getVerbatimElevation();
       $result['specificLocality'] = $rlocality->getLocalityName();
       $related = $rlocality->loadLinkedTo();
       $rgeography = $related['GeographyID'];
       $rgeography->load($rgeography->getGeographyID());
       $result['geographyid'] = $rgeography->getGeographyID();
       $result['geography'] = $rgeography->getFullName();
       $result['error']="";
   } else {
       $result['status'] = "ERROR";
       $result['error']="Item Not Found for [$barcode], matches=$num_matches. ";
   }

   return $result;
}

/**
 * count the number of collection object records associated with a locality.
 * @param localityid the localityid of the locality to lookup.
 * @return the count of the number of collection object records related to the locality, or -1 on an error.
 */
function countCollectionObjectsForLocality($localityid) {
   global $connection, $debug, $feedback;
   $result = -1;
   $sql = "select count(collectionobjectid) from locality l left join collectingevent ce on l.localityid = ce.localityid " .
          "left join collectionobject co on ce.collectingeventid = co.collectingeventid " .
          "where l.localityid = ? ";
   $statement = $connection->prepare($sql);
   if ($statement) {
      $statement->bind_param("i", $localityid);
      $statement->execute();
      $statement->bind_result($count);
      $statement->store_result();
      if ($statement->num_rows==1) {
          if ($statement->fetch()) {
             $result = $count;
          }
      }
      $statement->free_result();
   } else {
      $feedback = $feedback . "Query Error [556] " . $connection->error;
   }
   return $result;
}

/**
 * count the number of collection object records associated with a locality.
 * @param localityid the localityid of the locality to lookup.
 * @return the count of the number of collection object records related to the locality, or -1 on an error.
 */
function countCollectionObjectsForEvent($collectingeventid) {
   global $connection, $debug, $feedback;
   $result = -1;
   $sql = "select count(collectionobjectid) from collectingevent ce  " .
          "left join collectionobject co on ce.collectingeventid = co.collectingeventid " .
          "where ce.collectingeventid = ? ";
   $statement = $connection->prepare($sql);
   if ($statement) {
      $statement->bind_param("i", $collectingeventid);
      $statement->execute();
      $statement->bind_result($count);
      $statement->store_result();
      if ($statement->num_rows==1) {
          if ($statement->fetch()) {
             $result = $count;
          }
      }
      $statement->free_result();
   } else {
      $feedback = $feedback . "Query Error [558] " . $connection->error;
   }
   return $result;
}


?>
