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
               $display = "mainform";
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
   $display="mainform";
}

$apage = new TPage();
$apage->setTitle("HUH Data Transcription Form");
$apage->setErrorMessage($error);

echo $apage->getHeader($user);

switch ($display) {

   case 'mainform':
      form();
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
               <input type=hidden name=display value='mainform'>
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
      //echo '<div class="flexbox" id="pwresult">';
      //echo "</div>";
      //echo '<div class="flexbox"><div id="testimage"><img src="http://nrs.harvard.edu/urn-3:FMUS.HUH:s19-00000001-315971-2" width="200"></div><div class="flexbox"><div id="imgtarget" style="width: 500px;"></div></div></div>';
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

// ** Functions

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

   $targetbarcode = '00460286';
   @$targetbarcode = substr(preg_replace('/[^0-9]/','',$_GET['barcode']),0,8);

   $habitat = "";

   $huh_fragment = new huh_fragment();
   $matches = $huh_fragment->loadArrayByIdentifier($targetbarcode);
   $num_matches = count($matches);
   if ($num_matches==1) { 
       $match = $matches[0];
       $match->load($match->getFragmentID());
       $related = $match->loadLinkedTo();
       $rcolobj = $related['CollectionObjectID'];
       $rcolobj->load($rcolobj->getCollectionObjectID());
       $created = $rcolobj->getTimestampCreated();
       $habitat = $rcolobj->getText1();
       $related = $rcolobj->loadLinkedTo();
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
 
   echo "<div class='hfbox' style='height: 1em;'>";  
   echo targetlist();
   echo "</div>";
   echo "</div>";
   echo "<div class='flex-main hfbox' style='padding: 0em;'>";  

   echo "<form action='transcribe_handler.php' method='GET' id='transcribeForm' >\n";
   echo "<input type=hidden name='action' value='transcribe'>";
   
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
   field ("barcode","Barcode",$targetbarcode,'required','[0-9]{1,8}');   // not zero padded when coming off barcode scanner.
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
   selectAcronym("herbariumacronym",$defaultherbarium);

   @field ("specificlocality","Verbatim locality",$specificLocality,'true'); 
   @field ("stationfieldnumber","Collector Number",$stationfieldnumber,'false'); 
   @field ("datecollected","Date Collected",$datecollected,'false','[0-9-/]+','2010-03-18'); 
   @field ("verbatimdate","Verbatim Date",$verbatimdate,'false'); 
   field ("habitat","Habitat",$habitat); 
   @field ("namedplace","Named place",$namedPlace); 
   @field ("verbatimelevation","verbatimElevation",$verbatimElevation,'false'); 

   echo "<tr><td><input type='submit' value='Save' id='saveButton'></td></tr>";
   echo "<script>
         $('#transcribeForm').submit(function(event){
               $('#feedback').html( 'Submitting: ' + ($('#barcode').val()) ) ;
               $.ajax({ 
                   type: 'GET',
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

   echo "</form>\n";

   echo '<script>
         $( function(){
            $("#imagezoomdiv").accordion( { heightStyle: "content" } ) ;
          });
   </script>';
   echo '<div id="imagezoomdiv" >';
   echo '<h3>Click to zoom, then move mouse to pan, then click to hold.</h3>';
   echo '<div>';

   $mediauri = "http://nrs.harvard.edu/urn-3:FMUS.HUH:s19-00000001-315971-2";
   $mediauri = "https://s3.amazonaws.com/huhwebimages/94A28BC927D6407/type/full/460286.jpg";
   $mediauri = imageForBarcode($targetbarcode);

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
   
}

function field($name, $label, $default="", $required='false', $regex='', $placeholder='') {
   echo "<tr><td>\n";
   echo "<label for='$name'>$label</label>";
   echo "</td><td>\n";
   if ($regex!='') {
      $regex = "pattern='$regex'";
   }
   if ($placeholder!='') {
      $placeholder = "placeHolder='$placeholder'";
   }
   if ($required=='false') { 
      echo "<input id=$name name=$name value='$default' $regex $placeholder  style='width: ".BASEWIDTH."em; ' >";
   } else { 
      echo "<input id=$name name=$name value='$default' required='$required' $regex $placeholder  style='width: ".BASEWIDTH."em; ' >";
   }
   echo "</td></tr>\n";
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
	$returnvalue = "<tr><td><div dojoType='custom.ComboBoxReadStore' jsId='store$name$rank'
	 url='ajax_handler.php?druid_action=return".$rank."json'> </div>";
	$returnvalue .= "<label for=\"$name\">$label</label></td><td>
	<input type=text name=$name id=$name dojoType='dijit.form.FilteringSelect' 
	store='store$name$rank' required='$required'
	searchAttr='name' value='$default' ></td></tr>";
	echo $returnvalue;
}

function selectHigherGeography($field,$label, $defaultcountry='', $defaultprimary='') { 
	$returnvalue = "<tr><td><div dojoType='dojo.data.ItemFileReadStore' jsId='geoStore$field'
	 url='ajax_handler.php?druid_action=returndistinctgeography&country=$defaultcountry&primary=$defaultprimary'> </div>";
	$returnvalue .= "<label for=\"$field\">$label</label></td><td>
	<input type=text name=$field id=$field dojoType='dijit.form.FilteringSelect' 
	store='geoStore$field' 
    style='width: ".BASEWIDTH."em; '
	searchAttr='name' value='' ></td></tr>";
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
	echo '<select name="'.$field.'" dojoType="dijit.form.Select">
	<option value="" selected>&nbsp;&nbsp;&nbsp;</option>
	<option value="1">Yes</option>
	<option value="0">No</option>
	</select>';
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

function selectCurrentID($field,$label,$required='false') {
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

function selectCollectorsID($field,$label,$required='false') {
   $returnvalue = "<tr><td><div dojoType='custom.ComboBoxReadStore' jsId='agentStore$field'
	 url='ajax_handler.php?druid_action=returndistinctjsoncollector' > </div>";
   $width = BASEWIDTH - 3;
   $returnvalue .= "<label for=\"$field\">$label</label></td><td>
	<input type='text' name=$field id='$field' dojoType='dijit.form.FilteringSelect' 
	store='agentStore$field' required='$required' searchDelay='300' hasDownArrow='false' 
    style='width: ".$width."em; border-color: blue; '
	searchAttr='name' value='' >
    <button id='buttonReset$field' dojoType='dijit.form.Button' data-dojo-type='dijit/form/Button' type='button' 
      onclick=\"dijit.byId('$field').reset();\"  data-dojo-props=\"iconClass:'dijitIconClear'\" ></button>
    </td></tr>";
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

function selectCollectingTripID($field,$label,$required='false') {
	$returnvalue = "<tr><td><div dojoType='custom.ComboBoxReadStore' jsId='collectingTripStore$field'
	url='ajax_handler.php?druid_action=returndistinctjsoncollectingtrip' > </div>";
	$returnvalue .= "<label for=\"$field\">$label</label></td><td>
	<input type='text' name=$field id=$field dojoType='dijit.form.FilteringSelect'
	store='collectingTripStore$field' required='$required' searchDelay='300' hasDownArrow='false' style='border-color: blue;'
	searchAttr='name' value='' >
	<button id='buttonReset$field' dojoType='dijit.form.Button' data-dojo-type='dijit/form/Button' type='button' 
    onclick=\"dijit.byId('$field').reset();\"  data-dojo-props=\"iconClass:'dijitIconClear'\" ></button></td></tr>";
	echo $returnvalue;
}

function selectProject($field,$label,$default,$required='false') {
    $returnvalue = "<tr><td><div dojoType='dojo.data.ItemFileReadStore' jsId='projectStore$field'
     url='ajax_handler.php?druid_action=returndistinctjsonproject&name=*' > </div>";
    $returnvalue .= "<label for=\"$field\">$label</label></td><td>
    <input type='text' name=$field id=$field dojoType='dijit.form.FilteringSelect' 
    store='projectStore$field' required='$required' hasDownArrow='false' style='border-color: blue;'
    searchAttr='name' value='$default' >
    <button id='buttonReset$field' dojoType='dijit.form.Button' data-dojo-type='dijit/form/Button' type='button' 
    onclick=\"dijit.byId('$field').reset();\"  data-dojo-props=\"iconClass:'dijitIconClear'\" ></button></td></tr>";
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
