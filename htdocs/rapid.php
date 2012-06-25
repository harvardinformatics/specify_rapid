<?php

session_start();

include_once("connection_library.php");
include_once("class_lib.php");

$error = "";
$targethostdb = "";
if (!function_exists('specify_connect')) {
   $error = 'Error: Database connection function not defined.';
}
@$connection = specify_connect();
if (!$connection) {
   $error =  'Error: No database connection. '. $targethostdb;
}


$display = substr(preg_replace('/[^a-z]/','',$_GET['display']),0,20);
$tdisplay = $display;
$showDefault = true;

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
         $error = "Login to HUH Specify Rapid Data Capture Form";
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

$page = new Page();
$page->setTitle("HUH Rapid Data Entry Form");
$page->setErrorMessage($error);

echo $page->getHeader($user);

//pageheader($error);

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
      echo '<div dojoType="dijit.layout.ContentPane" region="center" >';
      echo "
               <form method=POST action='rapid.php' name=loginform id=loginform>
               Specify username:<input type=textbox name=username value='$username'>
               Password:<input type=password name=password>
               <input type=hidden name=action value='getpassword'>
               <input type=hidden name=display value='$display'>
               <input type=submit value='Login'>
               </form>";
      echo "<script type='text/javascript'>
               function get_password(){
                  dojo.byId('pwresult').innerHTML = 'Checking'
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

echo $page->getFooter();
//pagefooter();

// ********************************************************************************
// ****  Supporting functions  ****************************************************
// ********************************************************************************

function pageheader($error="") {
   global $user;
   echo "<!DOCTYPE html>\n";
   echo "<html>\n";
   echo "<head>\n";
   echo "<title>HUH Rapid Data Entry Form</title>\n";
   echo '<script src="/dojo/1.6.1/dojo/dojo.js" djConfig="parseOnLoad: true">
        </script>
        <link rel="stylesheet" type="text/css" href="/dojo/1.6.1/dijit/themes/claro/claro.css" />
        <script type="text/javascript">
             dojo.require("dijit.layout.AccordionContainer");
             dojo.require("dojo.data.ItemFileReadStore");
             dojo.require("dijit.form.Form");
             dojo.require("dijit.form.FilteringSelect");
             dojo.require("dijit.form.Select");
             dojo.require("dijit.form.Button");
             dojo.require("dijit.form.ValidationTextBox");
             dojo.require("dijit.layout.ContentPane");
             dojo.require("dijit.layout.BorderContainer");
             dojo.require("dijit.form.TextBox");
             dojo.require("dijit.Dialog");
             dojo.require("custom.ComboBoxReadStore");
             dojo.require("custom.LoadingMsgFilteringSelect");
        </script>        
        <style type="text/css">
            html, body { width: 100%; height: 100%; margin: 0; overflow:hidden; }
            #borderContainer { width: 100%; height: 100%; }
        </style>';
   echo "</head>\n";
   echo "<body class=' claro '>\n";
   echo '<div dojoType="dijit.layout.BorderContainer" design="sidebar" gutters="true" liveSplitters="true" id="borderContainer">';
   echo '<div dojoType="dijit.layout.ContentPane" region="top" layoutPriority="2" splitter="false">';
   if ($error!="") {
      echo "<h2>$error</h2>";
   }
   if ($user->getAuthenticationState()==true) {
      echo $user->getUserHtml("rapid.php");
   }
   echo '</div>';
}

function form() {

   echo '
           <script type="text/javascript">
            dojo.addOnLoad( function() {
                var form = dojo.byId("rapidForm");

                dojo.connect(form, "onsubmit", function(event) {
                     dojo.stopEvent(event);
 
                     var xhrArgs = {
                        form: dojo.byId("rapidForm"),
                        handleAs: "text",
                        load: function(data) {
                              dojo.byId("feedback").innerHTML = data;
                        },
                        error: function(error) {
                           dojo.byId("feedback").innerHTML = error;
                        }
                    }
                    dojo.byId("feedback").innerHTML = "Adding..."
                    
                    var deferred = dojo.xhrGet(xhrArgs);
              });
           });

            dojo.addOnLoad(function() {
                var defDialog = dijit.byId("defaultsDialog");
                dojo.connect(dijit.byId("buttonDefaults"), "onClick", defDialog, "show");
                dojo.connect(dijit.byId("buttonCancel"), "onClick", defDialog, "hide");
            });           
        </script>
   ';
   
   $defaultcountry = substr(preg_replace('/[^A-Za-z[:alpha:] ]/','',$_GET['defaultcountry']),0,255);
   $defaultprimary = substr(preg_replace('/[^A-Za-z[:alpha:] ]/','',$_GET['defaultprimary']),0,255);
   
   if ($defaultcountry=='') { 
       $defaultcountry = "United States of America";
       if ($defaultprimary=='') { 
           $defaultprimary = "California";
       }
   }
   
   echo "<form action='ajax_handler.php' method='GET' id='rapidForm' >\n";
   //echo "<div dojoType='dijit.form.Form' id='rapidForm' jsId='rapidForm' encType='multipart/form-data' action='ajax_handler.php' method='GET'>";
   echo "<input type=hidden name='druid_action' value='rapidaddprocessor'>";
   
   echo '<div dojoType="dijit.layout.AccordionContainer" region="left" layoutPriority="1">';
   echo '<div dojoType="dijit.layout.ContentPane" title="Required">';
   echo "<table>\n";
   selectCollectorsID('collectors','Collector(s)','true');
   field ("etal","et al.");
   field ("fieldnumber","Collector number");
   field ("verbatimdate","Verb. date coll.");
   field ("datecollected","Date collected",'','false','[0-9-/]+','2010-03-18');  // ISO, see https://code.google.com/p/applecore/wiki/CollectionDate
   selectAcronym();
   field ("barcode","Barcode",'','true','[0-9]{1,8}');   // not zero padded when coming off barcode scanner.
   selectCurrentID("filedundername","Filed under name",'true');   // filed under
   fieldselectpicklist("fiidentificationqualifier",'Id qualifier','','false','fiidqualifierpl',26);
   selectCurrentID("currentdetermination","Current Id");   // current id
   fieldselectpicklist("identificationqualifier",'Id qualifier','','false','idqualifierpl',26);
   selectCollectorsID("identifiedby","Identified by"); // for current id 
   field ("dateidentified","Date identified",'','false','[0-9-]+','2010-03-18');  // for current id
   staticvalue("Country limit",$defaultcountry);
   staticvalue("State limit",$defaultprimary);
   selectHigherGeography ("highergeography","Higher Geography",$defaultcountry,$defaultprimary); // higher picklist limited by country/primary
   field ("specificlocality","Verbatim locality",'','true');
   fieldselectpicklist("prepmethod",'Preparation method','Pressed','true','prepmethodpl',55);
   preptypeselect("format","Format",'Sheet','true','formatStore','huh_preptype','Name');
   echo "<tr><td></td><td>";
   //echo "<input type=submit value='Add'>";
   echo "<button type='submit' dojoType='dijit.form.Button' id='submitButton'>Add</button>";
   echo "</td></tr>";
   echo "</table>\n";
   echo '</div>';
   echo '</div>';
   
   echo '<div dojoType="dijit.layout.AccordionContainer" region="center" >';
   
   echo '<div dojoType="dijit.layout.AccordionPane" title="Georeference">';
   echo "<table>\n";
   field ("verbatimlat","verbatim latitude  ° ' \"");
   field ("verbatimlong","verbatim longitude ° ' \"");
   field ("decimallat","decimal latitude",'','false','\-?[0-9]{1,2}(\.{1}[0-9]*)?');
   field ("decimallong","decimal longitude",'','false','\-?[0-1]?[0-9]{1,2}(\.{1}[0-9]*)?');
   field ("datum","datum");
   field ("coordinateuncertanty","uncertainty radius meters",'','false','[0-9]*');
   selectCollectorsID("georeferencedby","Georeferenced by");
   field ("georeferencedate","georeference date (9999-99-99)",'','false','[0-9]{4}-[0-9]{2}-[0-9]{2}','2010-03-18');
   fieldselectpicklist("georeferencesource",'Lat/Long method','','false','georefsourcepl',31);
   staticvalue("Note:","If you fill in any fields above you should fill in all of them."); 
   utm();
   staticvalue("See:","<a href='http://www.gbif.org/orc/?doc_id=1288'>GBIF guide to best practices in Georeferencing</a>"); 
   echo "</table>\n";
   echo '</div>';
   
   echo '<div dojoType="dijit.layout.AccordionPane" selected="true" title="Additional Fields">';
   echo "<table>\n";
   fieldselectpicklist("typestatus",'Type status','','false','typestatuspl',56);
   selectCurrentID("basionym","Basionym");
   selectRefWorkID("publication","Publication");
   field ("page","Page");
   field ("datepublished","Year published");
   selectYesNo ("isfragment","Is fragment");
   field ("habitat","Habitat");  // https://code.google.com/p/applecore/wiki/Habitat
   fieldselectpicklist("phenology",'Reproductive condition','','false','repcondpl',54);  // https://code.google.com/p/applecore/wiki/Phenology
   field ("verbatimelevation","Verbatim elevation");
   field ("minelevation","Minimum elevation meters",'','false','[0-9]*');
   field ("maxelevation","Maximum elevation meters",'','false','[0-9]*');
   selectContainerID ("container","Container");  
   field ("specimenremarks","Specimen remarks");
   echo "</table>\n";
   echo '</div>'; // accordion pane
   
   echo '<div dojoType="dijit.layout.AccordionPane" selected="true" title="Entering Data">';
   echo "<table>\n";
   staticvalue("Required:","Fill in all fields under Required if there is data to put in them.  You cannot leave Herbarium acronym, Barcode, Filed under Name, Higher Geography, Specific Locality, Preparation Method, or Format Blank."); 
   staticvalue("Picklists:","For people and taxa: type part of the value and wait for the picklist to popup.  Use * as a wildcard, Collector='J*Mack' finds 'J. A. Macklin'.  For qualifiers, format, etc, you can just pull down the list.");  
   staticvalue("Dates:","Must be in the form 2011-02-25 (except for verbatim dates). 2011-05 and 2011 are allowed to express just month or year."); 
   staticvalue("DateCollected:","Also allows ranges with start and end dates separated by a slash: e.g. 2006/2008,  1886-11/1887-02, 1902-03-16/1902-03-18, 1912-03/1922 "); 
   staticvalue("Barcode:","Required. Up to 8 digits, can be zero padded, e.g. 00154335."); 
   staticvalue("FiledUnderName:","Required.The taxon name on the folder the specimen is in."); 
   staticvalue("CurrentID:","The most recent identification on the sheet, leave blank if the same as Filed Under Name.  Identified by and date identified pertain to the Current Id."); 
   staticvalue("Collector(s)","The names of people (collectors, determiners, georeferencers) must be present as agents with variant names of type Label/Collector name in Specify before they can be entered here."); 
   staticvalue("Country/State:","Change the default settings for the higher geography filter on the Set Default Values, note that this resets all form entries to blank values."); 
   staticvalue("See:","<a href='http://watson.huh.harvard.edu/wiki/index.php/Minimal_Data_Capture'>Minimal Data Capture Wiki Page</a>"); 
   staticvalue("See:","<a href='http://watson.huh.harvard.edu/wiki/index.php/Field_Definitions'>Field Definitions</a>"); 
   staticvalue("See:","<a href='https://code.google.com/p/applecore/wiki/CodesAndNumbers'>AppleCore guidance document</a>"); 
   echo "</table>\n";
   echo '<button id="buttonDefaults" dojoType="dijit.form.Button" type="button">Set Default Values</button>'; 
   echo '</div>'; // accordion pane
   
   
   echo '</div>'; // accordion container
   
   echo "</form>\n";
   
   echo '<div dojoType="dijit.Dialog" id="defaultsDialog" title="Set Default Values">';
   echo "<form action='rapid.php' method='GET'>";
   echo "<table>\n";
   staticvalue("","Pressing the Set default button below sets the values for the filter that is placed on Higher Geography.");  
   staticvalue("","You can leave one or both of these filters blank.");  
   staticvalue("","If you set a country and a state, the state will be used as the filter.");  
   geographyselect("defaultcountry","Country limit",$defaultcountry,'false','country');
   geographyselect("defaultprimary","State/province limit",$defaultprimary,'false','primary');   
   staticvalue("Note:","Clears and resets all form values.  You will lose all unsaved data.");
   echo "<tr><td></td><td>";
   echo "<input type=submit value='Set default values'>\n";
   echo '<button id="buttonCancel" dojoType="dijit.form.Button" type="button">Cancel</button>'; 
   echo "</td></tr>";
   echo "</table>\n";
   echo "</form>\n";
   echo '</div>'; // dialog
   
}

function field($name, $label, $default="", $required='false', $regex='', $placeholder='') {
   echo "<tr><td>\n";
   echo "<label for='$name'>$label</label>";
   echo "</td><td>\n";
   if ($regex!='') {
      $regex = "regExp='$regex'";
   }
   if ($placeholder!='') {
      $placeholder = "placeHolder='$placeholder'";
   }
   echo "<input name=$name value='$default' dojoType='dijit.form.ValidationTextBox' required='$required' $regex $placeholder >";
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
	$returnvalue = "<tr><td><div dojoType='dojo.data.ItemFileReadStore' jsId='store$name$rank'
	 url='ajax_handler.php?druid_action=return".$rank."json'> </div>";
	$returnvalue .= "<label for=\"$name\">$label</label></td><td>
	<input type=text name=$name id=$name dojoType='dijit.form.FilteringSelect' 
	store='store$name$rank' 
	searchAttr='name' value='$default' ></td></tr>";
	echo $returnvalue;
}

function selectHigherGeography($field,$label, $defaultcountry='', $defaultprimary='') { 
	$returnvalue = "<tr><td><div dojoType='dojo.data.ItemFileReadStore' jsId='geoStore$field'
	 url='ajax_handler.php?druid_action=returndistinctgeography&country=$defaultcountry&primary=$defaultprimary'> </div>";
	$returnvalue .= "<label for=\"$field\">$label</label></td><td>
	<input type=text name=$field id=$field dojoType='dijit.form.FilteringSelect' 
	store='geoStore$field' 
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

function selectAcronym() {
   echo "<tr><td>\n";
   echo "<label for='herbariumacronym'>Herbarium acronym</label>";
   echo "</td><td>\n";
   echo '<select name="herbariumacronym" dojoType="dijit.form.Select">
	<option value="GH" selected>GH</option>
	<option value="A">A</option>
	<option value="FH">FH</option>
	<option value="AMES">AMES</option>
	<option value="ECON">ECON</option>
	<option value="NEBC">NEBC</option>
	</select>';
   echo "</td></tr>\n";
}

function selectCurrentID($field,$label,$required='false') {
   $returnvalue = "<tr><td><div dojoType='custom.ComboBoxReadStore' jsId='taxonStore$field'
	 url='ajax_handler.php?druid_action=returndistinctjsonidnamelimited&table=huh_taxon&field=FullName'> </div>";
   $returnvalue .= "<label for=\"$field\">$label</label></td><td>
	<input type=text name=$field id=$field dojoType='dijit.form.FilteringSelect' 
	store='taxonStore$field' required='$required' searchDelay='300' hasDownArrow='false' style='border-color: blue;'
	searchAttr='name' value='' ></td></tr>";
   echo $returnvalue;
}

function selectCollectorsID($field,$label,$required='false') {
   $returnvalue = "<tr><td><div dojoType='custom.ComboBoxReadStore' jsId='agentStore$field'
	 url='ajax_handler.php?druid_action=returndistinctjsoncollector' > </div>";
   $returnvalue .= "<label for=\"$field\">$label</label></td><td>
	<input type='text' name=$field id=$field dojoType='dijit.form.FilteringSelect' 
	store='agentStore$field' required='$required' searchDelay='300' hasDownArrow='false' style='border-color: blue;'
	searchAttr='name' value='' ></td></tr>";
   echo $returnvalue;
}


function selectRefWorkID($field,$label,$required='false') {
   $returnvalue = "<tr><td><div dojoType='custom.ComboBoxReadStore' jsId='agentStore$field'
	 url='ajax_handler.php?druid_action=returndistinctjsontitle' > </div>";
   $returnvalue .= "<label for=\"$field\">$label</label></td><td>
	<input type='text' name=$field id=$field dojoType='dijit.form.FilteringSelect' 
	store='agentStore$field' required='$required' searchDelay='300' hasDownArrow='false' style='border-color: blue;' 
	searchAttr='name' value='' ></td></tr>";
   echo $returnvalue;
}

function selectContainerID($field,$label,$required='false') {
	$returnvalue = "<tr><td><div dojoType='custom.ComboBoxReadStore' jsId='agentStore$field'
	 url='ajax_handler.php?druid_action=returndistinctjsoncontainer' > </div>";
	$returnvalue .= "<label for=\"$field\">$label</label></td><td>
	<input type='text' name=$field id=$field dojoType='dijit.form.FilteringSelect' 
	store='agentStore$field' required='$required' searchDelay='300' hasDownArrow='false' style='border-color: blue;'
	searchAttr='name' value='' ></td></tr>";
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
