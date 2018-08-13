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

   @$action = substr(preg_replace('/[^a-z]/','',$_GET['action']),0,45);

   $debug = false;

   if ($debug) { echo "[$action]"; }

   $alpha = "ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûüýþÿ";

   switch ($action) {
      case 'interpretdate': 
         @$verbatimdate = $_GET['verbatimdate'];
         $exec = $vdateexecstring . escapeshellarg($verbatimdate) . " 2>&1";
         $isodate = shell_exec($exec);

         echo "$isodate";

         break;   
      case 'transcribe':
         $feedback = "";
         
         $todo = 100;
         $truncation = false;
         $truncated = "";
         @$collectors= substr(preg_replace('/[^0-9]/','',$_GET['collectors']),0,huh_agentvariant::NAME_SIZE);
         @$etal= substr(preg_replace('/[^A-Za-z&\, \.0-9]/','',$_GET['etal']),0,huh_collector::ETAL_SIZE);
         @$fieldnumber= substr(preg_replace('/[^A-Za-z\- \.0-9\,\/]/','',$_GET['fieldnumber']),0,huh_collectingevent::STATIONFIELDNUMBER_SIZE);
         @$stationfieldnumber= substr(preg_replace('/[^A-Za-z\- \.0-9\,\/\(\)\[\]=#]/','',$_GET['stationfieldnumber']),0,huh_collectingevent::STATIONFIELDNUMBER_SIZE);
         @$accessionnumber= substr(preg_replace('/[^A-Za-z\- \.0-9\,\/]/','',$_GET['accessionnumber']),0,huh_collectingevent::STATIONFIELDNUMBER_SIZE);
         @$verbatimdate= substr($_GET['verbatimdate'],0,huh_collectingevent::VERBATIMDATE_SIZE);
         @$datecollected= substr(preg_replace('/[^\-\/0-9]/','',$_GET['datecollected']),0,40);  // allow larger than date to parse ISO date range
         @$herbariumacronym= substr(preg_replace('/[^A-Z]/','',$_GET['herbariumacronym']),0,huh_fragment::TEXT1_SIZE);
         @$barcode= substr(preg_replace('/[^0-9]/','',$_GET['barcode']),0,huh_fragment::IDENTIFIER_SIZE);
         @$provenance= substr(preg_replace('/^A-Za-z 0-9\[\]\.\-\,\(\)\?\;\:]/','',$_GET['provenance']),0,huh_fragment::PROVENANCE_SIZE);
         @$filedundername= substr(preg_replace('/[^A-Za-z[:alpha:]\(\) 0-9]/','',$_GET['filedundername']),0,huh_taxon::FULLNAME_SIZE);
         @$fiidentificationqualifier= substr(preg_replace('/[^A-Za-z]/','',$_GET['fiidentificationqualifier']),0,huh_determination::QUALIFIER_SIZE);
         @$currentdetermination= substr(preg_replace('/[^A-Za-z[:alpha:]\(\) 0-9]/','',$_GET['currentdetermination']),0,huh_taxon::FULLNAME_SIZE);
         @$identificationqualifier= substr(preg_replace('/[^A-Za-z]/','',$_GET['identificationqualifier']),0,huh_determination::QUALIFIER_SIZE);
         @$identifiedby=      substr(preg_replace('/[^0-9]/','',$_GET['identifiedby']),0,huh_determination::DETERMINERID_SIZE);
         @$determinertext= substr(preg_replace('/[^A-Za-z[:alpha:]'.$alpha.'0-9+\;\:() \.\-\,\[\]\&\'\/?#"ñ°]/','',$_GET['determinertext']),0,huh_determination::TEXT1_SIZE);
         @$dateidentified= substr(preg_replace('/[^0-9\-\/]/','',$_GET['dateidentified']),0,huh_determination::DETERMINEDDATE_SIZE);
         @$highergeography= substr(preg_replace('/[^0-9]/','',$_GET['highergeography']),0,huh_geography::GEOGRAPHYID_SIZE);
         @$specificlocality = substr(preg_replace('/[^A-Za-z[:alpha:]'.$alpha.'0-9+\;\:() \.\-\,\[\]\&\'\/?#"ñ°]/','',$_GET['specificlocality']),0,huh_locality::LOCALITYNAME_SIZE);
         @$prepmethod = substr(preg_replace('/[^A-Za-z]/','',$_GET['prepmethod']),0,huh_preparation::PREPTYPEID_SIZE);
         @$format = substr(preg_replace('/[^A-Za-z]/','',$_GET['format']),0,huh_preptype::NAME_SIZE);

         @$verbatimlat= substr(preg_replace('/[^0-9\. \'"°NSn-]/','',$_GET['verbatimlat']),0,huh_locality::LAT1TEXT_SIZE);
         @$verbatimlong= substr(preg_replace('/[^0-9\. \'"°EWew\-]/','',$_GET['verbatimlong']),0,huh_locality::LONG1TEXT_SIZE);
         @$decimallat= substr(preg_replace('/[^0-9\.\-]/','',$_GET['decimallat']),0,huh_locality::LATITUDE1_SIZE);
         @$decimallong= substr(preg_replace('/[^0-9\.\-]/','',$_GET['decimallong']),0,huh_locality::LONGITUDE1_SIZE);
         @$datum= substr(preg_replace('/[^A-Za-z0-9]/','',$_GET['datum']),0,huh_locality::DATUM_SIZE);
         @$coordinateuncertanty= substr(preg_replace('/[^0-9]/','',$_GET['coordinateuncertanty']),0,huh_geocoorddetail::MAXUNCERTAINTYEST_SIZE);
         @$georeferencedby= substr(preg_replace('/[^0-9]/','',$_GET['georeferencedby']),0,huh_agentvariant::NAME_SIZE);
         @$georeferencedate= substr(preg_replace('/[^\-\/0-9]/','',$_GET['georeferencedate']),0,huh_geocoorddetail::GEOREFDETDATE_SIZE);
         @$georeferencesource= substr(preg_replace('/[^A-Za-z]/','',$_GET['georeferencesource']),0,huh_locality::LATLONGMETHOD_SIZE);
         @$utmzone= substr(preg_replace('/[^0-9A-Z]/','',$_GET['utmzone']),0,huh_localitydetail::UTMZONE_SIZE);
         @$utmeasting= substr(preg_replace('/[^0-9]/','',$_GET['utmeasting']),0,huh_localitydetail::UTMEASTING_SIZE);
         @$utmnorthing= substr(preg_replace('/[^0-9]/','',$_GET['utmnorthing']),0,huh_localitydetail::UTMNORTHING_SIZE);

         @$typestatus= substr(preg_replace('/[^A-Za-z]/','',$_GET['typestatus']),0,huh_determination::TYPESTATUSNAME_SIZE);
         @$typeconfidence= substr(preg_replace('/[^A-Za-z]/','',$_GET['typeconfidence']),0,huh_determination::CONFIDENCE_SIZE);
         @$basionym= substr(preg_replace('/[^0-9]/','',$_GET['basionym']),0,huh_taxon::FULLNAME_SIZE);
         @$publication= substr(preg_replace('/[^[:alpha:]A-Za-z 0-9]/','',$_GET['publication']),0,huh_referencework::REFERENCEWORKID_SIZE);
         @$page= substr(preg_replace('/[^0-9 A-Za-z\,\(\)\-\:\;\.\[\]]/','',$_GET['page']),0,huh_taxoncitation::TEXT1_SIZE);
         @$datepublished= substr(preg_replace('/[^0-9 A-Za-z\,\(\)\-\:\;\.\[\]]/','',$_GET['datepublished']),0,huh_taxoncitation::TEXT2_SIZE);
         @$isfragment= substr(preg_replace('/[^0-9a-z]/','',$_GET['isfragment']),0,1);   // taxon 
         @$habitat= substr(preg_replace('/[^A-Za-z 0-9\[\]\.\-\,\(\)\?\:\;]/','',$_GET['habitat']),0,huh_collectingevent::REMARKS_SIZE); 
         @$host = substr(preg_replace('/[^A-Za-z 0-9\[\]\.\-\,\(\)\?\;\:]/','',$_GET['host']),0,900); 
         @$substrate= substr(preg_replace('/[^A-Za-z[:alpha:]'.$alpha.'0-9+\;\:() \.\-\,\[\]\&\'\/?#"ñ°]/','',$_GET['substrate']),0,huh_fragment::TEXT2_SIZE);
         @$phenology= substr(preg_replace('/[^A-Za-z ]/','',$_GET['phenology']),0,huh_fragment::PHENOLOGY_SIZE);
         @$verbatimelevation= substr(preg_replace('/[^A-Za-z0-9\-\.\, \[\]\(\)\? \&\']/','',$_GET['verbatimelevation']),0,huh_locality::VERBATIMELEVATION_SIZE);
         @$namedplace = substr(preg_replace('/[^A-Za-z0-9[:alpha:]\-\.\, \[\]\(\)\? \&\']/','',$_GET['namedplace']),0,huh_locality::NAMEDPLACE_SIZE);
         @$minelevation= substr(preg_replace('/[^0-9\.]/','',$_GET['minelevation']),0,huh_locality::MINELEVATION_SIZE);
         @$maxelevation= substr(preg_replace('/[^0-9\.]/','',$_GET['maxelevation']),0,huh_locality::MAXELEVATION_SIZE);
         @$specimenremarks= substr(preg_replace('/[^A-Za-z[:alpha:]0-9\- \.\,\;\:\&\'\]\[]/','',$_GET['specimenremarks']),0,huh_collectionobject::REMARKS_SIZE);
         @$specimendescription= substr(preg_replace('/[^A-Za-z[:alpha:]0-9\- \.\,\;\:\&\'\]\[]/','',$_GET['specimendescription']),0,huh_collectionobject::DESCRIPTION_SIZE);
         @$itemdescription= substr(preg_replace('/[^A-Za-z[:alpha:]0-9\- \.\,\;\:\&\'\]\[]/','',$_GET['itemdescription']),0,huh_fragment::DESCRIPTION_SIZE);
         @$container= substr(preg_replace('/[^0-9]/','',$_GET['container']),0,huh_collectionobject::CONTAINERID_SIZE);
 		@$collectingtrip = substr(preg_replace('/[^0-9]/','',$_GET['collectingtrip']),0,huh_collectingevent::COLLECTINGTRIPID_SIZE);
         @$storagelocation= substr(preg_replace('/[^A-Za-z'.$alpha.'0-9+\;\:() \.\-\,\[\]\&\'\/?#"ñ]/','',$_GET['storagelocation']),0,huh_preparation::STORAGELOCATION_SIZE);
         @$project= substr(preg_replace('/[^A-Za-z\. \-0-9]/','',$_GET['project']),0,huh_project::PROJECTNAME_SIZE);
         @$storage= substr(preg_replace('/[^0-9]/','',$_GET['storage']),0,huh_storage::STORAGEID_SIZE); // subcollection
         @$exsiccati= substr(preg_replace('/[^0-9]/','',$_GET['exsiccati']),0,huh_referencework::REFERENCEWORKID_SIZE);
         @$fascicle= substr(preg_replace('/[^A-Za-z\. 0-9]/','',$_GET['fascicle']),0,huh_fragmentcitation::TEXT1_SIZE);
         @$exsiccatinumber= substr(preg_replace('/[^A-Za-z\. 0-9]/','',$_GET['exsiccatinumber']),0,huh_fragmentcitation::TEXT2_SIZE);
            
         //@$= substr(preg_replace('/[^0-9]/','',$_GET['']),0,huh_);

         if ( @($collectors!=$_GET['collectors']) )  { $truncation = true; $truncated .= "Collector: [$collectors] "; }  
         if ( @($etal!=$_GET['etal']) ) { $truncation = true; $truncated .= "etal : [$etal] "; }
         if ( @($fieldnumber!=$_GET['fieldnumber']) ) { $truncation = true; $truncated .= "fieldnumber : [$fieldnumber] "; }
         if ( @($stationfieldnumber!=$_GET['stationfieldnumber']) ) { $truncation = true; $truncated .= "stationfieldnumber : [$stationfieldnumber] "; }
         if ( @($accessionnumber!=$_GET['accessionnumber']) ) { $truncation = true; $truncated .= "accessionnumber : [$accessionnumber] "; }
         if ( @($verbatimdate!=$_GET['verbatimdate']) ) { $truncation = true; $truncated .= "verbatimdate : [$verbatimdate] "; }
         if ( @($datecollected!=$_GET['datecollected']) ) { $truncation = true; $truncated .= "datecollected : [$datecollected] "; }
         if ( @($herbariumacronym!=$_GET['herbariumacronym']) ) { $truncation = true; $truncated .= "herbariumacronym : [$herbariumacronym] "; }
         if ( @($barcode!=$_GET['barcode']) ) { $truncation = true; $truncated .= "barcode : [$barcode] "; }
         if ( @($provenance!=$_GET['provenance']) ) { $truncation = true; $truncated .= "provenance : [$provenance] "; }
         if ( @($filedundername!=$_GET['filedundername']) ) { $truncation = true; $truncated .= "filedundername : [$filedundername] "; }
         if ( @($fiidentificationqualifier!=$_GET['fiidentificationqualifier']) ) { $truncation = true; $truncated .= "fiidentificationqualifier : [$fiidentificationqualifier] "; }
         if ( @($currentdetermination!=$_GET['currentdetermination']) ) { $truncation = true; $truncated .= "currentdetermination : [$currentdetermination] "; }
         if ( @($identificationqualifier!=$_GET['identificationqualifier']) ) { $truncation = true; $truncated .= "identificationqualifier : [$identificationqualifier] "; }
         if ( @($identifiedby!=$_GET['identifiedby']) ) { $truncation = true; $truncated .= "identifiedby : [$identifiedby] "; }
         if ( @($determinertext!=$_GET['determinertext']) ) { $truncation = true; $truncated .= "determinertext : [$determinertext] "; }
         if ( @($dateidentified!=$_GET['dateidentified']) ) { $truncation = true; $truncated .= "dateidentified : [$dateidentified] "; }
         if ( @($highergeography!=$_GET['highergeography']) ) { $truncation = true; $truncated .= "highergeography : [$highergeography] "; }
         if ( @($specificlocality!=$_GET['specificlocality']) ) { $truncation = true; $truncated .= "specificlocality : [$specificlocality] "; } 
         if ( @($prepmethod!=$_GET['prepmethod']) ) { $truncation = true; $truncated .= "prepmethod : [$prepmethod] "; } 
         if ( @($format!=$_GET['format']) ) { $truncation = true; $truncated .= "format : [$format] "; } 

         if ( @($verbatimlat!=$_GET['verbatimlat']) ) { $truncation = true; $truncated .= "verbatimlat : [$verbatimlat] "; }
         if ( @($verbatimlong!=$_GET['verbatimlong']) ) { $truncation = true; $truncated .= "verbatimlong : [$verbatimlong] "; }
         if ( @($decimallat!=$_GET['decimallat']) ) { $truncation = true; $truncated .= "decimallat : [$decimallat] "; }
         if ( @($decimallong!=$_GET['decimallong']) ) { $truncation = true; $truncated .= "decimallong : [$decimallong] "; }
         if ( @($datum!=$_GET['datum']) ) { $truncation = true; $truncated .= "datum : [$datum] "; }
         if ( @($coordinateuncertanty!=$_GET['coordinateuncertanty']) ) { $truncation = true; $truncated .= "coordinateuncertanty : [$coordinateuncertanty] "; }
         if ( @($georeferencedby!=$_GET['georeferencedby']) ) { $truncation = true; $truncated .= "georeferencedby : [$georeferencedby] "; }
         if ( @($georeferencedate!=$_GET['georeferencedate']) ) { $truncation = true; $truncated .= "georeferencedate : [$georeferencedate] "; }
         if ( @($georeferencesource!=$_GET['georeferencesource']) ) { $truncation = true; $truncated .= "georeferencesource : [$georeferencesource] "; }
         if ( @($utmzone!=$_GET['utmzone']) ) { $truncation = true; $truncated .= "utmzone : [$utmzone] "; }
         if ( @($utmeasting!=$_GET['utmeasting']) ) { $truncation = true; $truncated .= "utmeasting : [$utmeasting] "; }
         if ( @($utmnorthing!=$_GET['utmnorthing']) ) { $truncation = true; $truncated .= "utmnorthing : [$utmnorthing] "; }

         if ( @($typestatus!=$_GET['typestatus']) ) { $truncation = true; $truncated .= "typestatus : [$typestatus] "; }
         if ( @($typeconfidence!=@$_GET['typeconfidence']) ) { $truncation = true; $truncated .= "typeconfidence : [$typeconfidence] "; }
         if ( @($basionym!=$_GET['basionym']) ) { $truncation = true; $truncated .= "basionym : [$basionym] "; }
         if ( @($publication!=$_GET['publication']) ) { $truncation = true; $truncated .= "publication : [$publication] "; }
         if ( @($page!=$_GET['page']) ) { $truncation = true; $truncated .= "page : [$page] "; }
         if ( @($datepublished!=$_GET['datepublished']) ) { $truncation = true; $truncated .= "datepublished : [$datepublished] "; }
         if ( @($isfragment!=$_GET['isfragment']) ) { $truncation = true; $truncated .= "isfragment : [$isfragment] "; }
         if ( @($habitat!=$_GET['habitat']) ) { $truncation = true; $truncated .= "habitat : [$habitat] "; }
         if ( @($host!=$_GET['host']) ) { $truncation = true; $truncated .= "host : [$host] "; }
         if ( @($substrate!=$_GET['substrate']) ) { $truncation = true; $truncated .= "substrate : [$substrate] "; }
         if ( @($phenology!=$_GET['phenology']) ) { $truncation = true; $truncated .= "phenology : [$phenology] "; }
         if ( @($verbatimelevation!=$_GET['verbatimelevation']) ) { $truncation = true; $truncated .= "verbatimelevation : [$verbatimelevation] "; }
         if ( @($minelevation!=@$_GET['minelevation']) ) { $truncation = true; $truncated .= "minelevation : [$minelevation] "; }
         if ( @($maxelevation!=@$_GET['maxelevation']) ) { $truncation = true; $truncated .= "maxelevation : [$maxelevation] "; }
         if ( @($specimenremarks!=$_GET['specimenremarks']) ) { $truncation = true; $truncated .= "specimenremarks : [$specimenremarks] "; }
         if ( @($specimendescription!=$_GET['specimendescription']) ) { $truncation = true; $truncated .= "specimendescription : [$specimendescription] "; }
         if ( @($itemdescription!=$_GET['itemdescription']) ) { $truncation = true; $truncated .= "itemdescription : [$itemdescription] "; }
         if ( @($container!=$_GET['container']) ) { $truncation = true; $truncated .= "container : [$container] "; }
         if ( @($collectingtrip!=$_GET['collectingtrip']) ) { $truncation = true; $truncated .= "collectingtrip : [$collectingtrip] "; }
         if ( @($storagelocation!=$_GET['storagelocation']) ) { $truncation = true; $truncated .= "storagelocation : [$storagelocation] "; }
         if ( @($project!=$_GET['project']) ) { $truncation = true; $truncated .= "project : [$project] "; }
         if ( @($storage!=$_GET['storage']) ) { $truncation = true; $truncated .= "storage : [$storage] "; }  // subcollection

         echo ingest();

       break;
       default: 
         echo "Unknown Action [$action]";
   }

}

function ingest() { 
   global $connection, $debug,
   $truncation, $truncated,
   $collectors,$etal,$fieldnumber,$stationfieldnumber,$accessionnumber,$verbatimdate,$datecollected,$herbariumacronym,$barcode,$provenance,
   $filedundername,$fiidentificationqualifier,$currentdetermination,$identificationqualifier,$highergeography,
   $specificlocality,$prepmethod,$format,$verbatimlat,$verbatimlong,$decimallat,$decimallong,$datum,
   $coordinateuncertanty,$georeferencedby,$georeferencedate,$georeferencesource,$typestatus, $basionym,
   $publication,$page,$datepublished,$isfragment,$habitat,$phenology,$verbatimelevation,$minelevation,$maxelevation,
   $identifiedby,$dateidentified,$specimenremarks,$specimendescription,$itemdescription,$container,$collectingtrip,$utmzone,$utmeasting,$utmnorthing,
   $project, $storagelocation, $storage, $namedplace,
   $exsiccati,$fascicle,$exsiccatinumber, $host, $substrate, $typeconfidence, $determinertext;

   $fail = false;
   $feedback = "";

   if ($truncation) {
     $fail = true;
     $feedback = "Data truncation: $truncated";
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

   // zero pad barcode up to 8 digits if needed
   $barcode = str_pad($barcode,8,"0",STR_PAD_LEFT);
   // Test for validly formed barcode 
   if (!preg_match("/^[0-9]{8}$/",$barcode)) {
      $fail = true;
      $feedback .= "Barcode [$barcode] is invalid.  Must be zero padded with exactly 8 digits: ";
   }

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

                                  $sql = "update collectingevent set stationfieldnumber=?, startdate=?, startdateprecision=?, enddate=?, enddateprecision=?, verbatimdate=?, version=version+1 where collectingeventid = ? ";
                                  $statement1 = $connection->prepare($sql);
                                  if ($statement1) {
                                      $statement1->bind_param("ssisisi", $stationfieldnumber, $startdate, $startdateprecision, $enddate, $enddateprecision, $verbatimdate, $collectingeventid);
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
                                      $sql = "update collectionobject set collectingeventid = ? where collectionobjectid = ?";
                                      $statement = $connection->prepare($sql);
                                      if ($statement) {
                                          $statement->bind_param("ii", $newcollectingeventid,$collectionobjectid);
                                          $statement->execute();
                                          $rows = $connection->affected_rows;
                                          if ($rows==1) { $feedback = $feedback . " Relinked collectionobject. "; }
                                          $sql = "update collectingevent set stationfieldnumber=?, startdate=?, startdateprecision=?, enddate=?, enddateprecision=?, verbatimdate=?, version=version+1 where collectingeventid = ? ";
                                          $statement1 = $connection->prepare($sql);
                                          if ($statement1) {
                                              $statement1->bind_param("ssisisi", $stationfieldnumber, $startdate, $startdateprecision, $enddate, $enddateprecision, $verbatimdate, $newcollectingeventid);
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
                           }
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
                                  $sql = "update locality set localityname = ?, verbatimelevation = ?, namedplace=?, version=version+1 where localityid = ? ";
                		  $statement1 = $connection->prepare($sql);
                                  if ($statement1) {
                                      $statement1->bind_param("sssi", $specificlocality, $verbatimelevation, $namedplace, $localityid);
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
                                          $sql = "update locality set localityname = ?, verbatimelevation = ?, namedplace=?, version=version+1 where localityid = ? ";
                        		          $statement = $connection->prepare($sql);
                                          if ($statement) {
                                              $statement->bind_param("sssi", $specificlocality, $verbatimelevation, $namedplace, $newlocalityid);
                                              $statement->execute();
                                              $rows = $connection->affected_rows;
                                              if ($rows==1) { $feedback = $feedback . " Updated Locality. "; } 
                                              if ($rows==0) { $feedback = $feedback . " Locality unchanged. "; } 
                                          } else { 
                                              $fail = true;
                                              $feedback.= "Query Error splitting/modifying locality. " . $connection->error . " ";
                                          }
                                      }
                                   } else { 
                                        $fail = true;
                                        $feedback.= "Query Error splitting locality. " . $connection->error . " ";
                                   }
                               }
                           } // end localityid 
                           if ($collectionobjectid!=null) { 
                              if ($habitat!=null && $habitat!="") { 
                                  $sql = "update collectionobject set text1 = ? where collectionobjectid = ? ";
                		  $statement = $connection->prepare($sql);
                                  if ($statement) {
                                      $statement->bind_param("si", $habitat, $collectionobjectid);
                                      $statement->execute();
                                      $rows = $connection->affected_rows;
                                      if ($rows==1) { $feedback = $feedback . " Updated CollObject. "; } 
                                      if ($rows==0) { $feedback = $feedback . " CollObject unchanged. "; } 
                                  } else { 
                                      $fail = true;
                                      $feedback.= "Query Error modifying locality. " . $connection->error . " ";
                                  }
                              } 
                           }
                       } else {
                          $fail = true;
                          $feedback.= "Query Error " . $connection->error . " " .  $sql;
                       }
                   } elseif ($statement->num_rows==0) {
                       // check for preparation barcode.
                       $fail = true;
                       $feedback.= "Barcode on preparation not yet supported. ";

                   } else {
                      $fail = true;
                      $feedback.= "Query error. Returned other than one row [" . $statement->num_rows . "] on check for barcode: " . $barcode;
                   }
               } else {
                  $fail = true;
                  $feedback.= "Query error 503." . $connection->error . " " . $sql;
               } 


            } else { 
               // create new specimen record
 
            } 
         } // exists

   } // not fail

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
   $connection->close();

   return $feedback;

}  // end ingest


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
