<?php

session_start();

include_once("connection_library.php");
include_once("class_lib.php");

define("ENABLE_DUPLICATE_FINDING",TRUE);
define("BASEWIDTH",21);  // em for width of form fields

// Header lines specifying format for spreadsheet uploads.
$expectedheader = "herbarium,barcode,Collector(s),et al,field number,verbatim date,ISO date collected,identification,authorship,qualifier,identified by,date identified,country,primary division,secondary division,specific locality,prep method,format,verbatim lat,verbatim long,decimal lat,decimal long,datum,coordinate uncertainty,georeferenced by,georeference date,georeferencesource,utm zone,utm easting,utm northing,type status,basionym,basionymauthorship,publication,page,year published,is fragment,habitat,phenology,verbatim elevation,min elevation,max elevation,specimen remarks,container";
$expectedheaderquoted = '"herbarium","barcode","Collector(s)","et al","field number","verbatim date","ISO date collected","identification","authorship","qualifier","identified by","date identified","country","primary division","secondary division","specific locality","prep method","format","verbatim lat","verbatim long","decimal lat","decimal long","datum","coordinate uncertainty","georeferenced by","georeference date","georeferencesource","utm zone","utm easting","utm northing","type status","basionym","basionymauthorship","publication","page","year published","is fragment","habitat","phenology","verbatim elevation","min elevation","max elevation","specimen remarks","container"';

$error = "";
$targethostdb = "";
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

$require_authentication = true;
$authenticated = false;
$user = new User();
$username = "";

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

$apage = new Page();
$apage->setTitle("HUH Rapid Data Entry Form");
$apage->setErrorMessage($error);

echo $apage->getHeader($user);

switch ($display) {

   case 'mainform':
      form();
      break;
   case 'upload':
      uploadspreadsheet();
      break;
   case 'spreadsheet':
      spreadsheet();
      break;
   case 'process':
      processspreadsheet();
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
               <input type=hidden name=display value='mainform'>
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

echo $apage->getFooter();
//pagefooter();

// ********************************************************************************
// ****  Supporting functions  ****************************************************
// ********************************************************************************

function inProfile($profile,$field, $location) {
   global $profiles;

   if ($profiles ==null) {
      $profiles = array();
      // load data from profiles.conf, blank lines and lines starting with # are ignored.
      $fh = fopen('profiles.conf','r');
      while ($line = fgets($fh)) {
        if ( (strlen($line)>0)  && (substr($line,0,1)<>"#") ) {
           $bits = explode(",",$line);
           if (count($bits)==3) {
              $key = $bits[0].$bits[1];
              $profiles[$key] = trim($bits[2]);
           }
        }
      }
      fclose($fh);
      reset($profiles);
   }

   $key = $profile . $field;

   //profile,field,location
   if (array_key_exists($key,$profiles)) {
      // a location is provided in the profile for the field
      $val = $profiles[$key];
      if ($val==$location) {
         $result = true;
      } else {
         $result = false;
      }
   } else {
       // the combination of profile and field are not present in the selected profile.
       if ($location=="main") {
          $result = true;
       } else {
          $result = false;
       }
   }
   return $result;
}


// NOTE: Page header isn't used, you  want Page->getHeader
function pageheader($error="") {
   global $user;
   echo "<!DOCTYPE html>\n";
   echo "<html>\n";
   echo "<head>\n";
   echo "<title>HUH Rapid Data Entry Form</title>\n";
   echo '<script src="/dojo/1.9.2/dojo/dojo.js" djConfig="parseOnLoad: true">
        </script>
        <style type="text/css">
            @import "/dojo/1.9.2/dojox/grid/enhanced/resources/claro/EnhancedGrid.css";
            @import "/dojo/1.9.2/dojox/grid/enhanced/resources/claro/Common.css";
            @import "/dojo/1.9.2/dijit/themes/claro/claro.css";
        </style>
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
             dojo.require("dojox.grid.EnhancedGrid");
             dojo.require("dojox.grid.enhanced.plugins.NestedSorting");
             dojo.require("dojox.data.CsvStore");
             dojo.require("dojo.parser");
             dojo.require("dojo.dom");

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

   @$profile = substr(preg_replace("/[^0-9A-Za-z]/","",$_GET['profile']),0,255);
   if ($profile == "") { $profile = "default"; }
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

   			dojo.addOnLoad(function() {
   				var topOfForm = dojo.byId("collectors");
   				var rapidForm = dojo.byId("rapidForm");
   				if (rapidForm && topOfForm) {
	   				dojo.on(rapidForm, "keyup", function(evt) {
						if (evt.shiftKey || evt.ctrlKey) {
   							var charOrCode = evt.charCode || evt.keyCode;
   							switch(charOrCode) {
   							case dojo.keys.UP_ARROW:
   								dijit.focus(topOfForm);
   								break;
	   						}
   						}
   					});
   				}
   			});

        function setNamesToFiledUnder() {
          var fun = dijit.byId("filedundername");
          var curr = dijit.byId("currentdetermination");
          var label = dijit.byId("label_name");

          if (curr.value == null || curr.value == "") {
            //curr.store.fetch({query:{name:fun._lastQuery}});
            curr.store._items=fun.store._items;
            curr.store._itemsByIdentity=fun.store._itemsByIdentity;
            curr.set("value", fun.get("value"));
          }

          if (label.value == null || label.value == "") {
            //label.store.fetch({query:{name:fun._lastQuery}});
            label.store._items=fun.store._items;
            label.store._itemsByIdentity=fun.store._itemsByIdentity;
            label.set("value", fun.get("value"));
          }
        }

        </script>
   ';

   @$cleardefaultgeography = substr(preg_replace('/[^01]/','',$_GET['cleardefaultgeography']),0,2);
   if ($cleardefaultgeography==1) {
       $defaultcountry = "";
       $defaultprimary = "";
   } else {
        @$defaultcountry = substr(preg_replace('/[^A-Za-z[:alpha:] ]/','',$_GET['defaultcountry']),0,255);
        @$defaultprimary = substr(preg_replace('/[^A-Za-z[:alpha:] ]/','',$_GET['defaultprimary']),0,255);

       // Sensible default values for original California project, no longer sensible.
       // if ($defaultcountry=='') {
       //     $defaultcountry = "United States of America";
       //     if ($defaultprimary=='') {
       //         $defaultprimary = "California";
       //     }
       // }
   }
   @$defaultherbarium = substr(preg_replace('/[^A-Z]/','',$_GET['defaultherbarium']),0,5);
   if ($defaultherbarium=='') { $defaultherbarium = "GH"; }
   @$defaultformat = substr(preg_replace('/[^A-Za-z ]/','',$_GET['defaultformat']),0,255);
   if ($defaultformat=='') { $defaultformat = "Sheet"; }
   @$defaultprepmethod = substr(preg_replace('/[^A-Za-z ]/','',$_GET['defaultprepmethod']),0,255);
   if ($defaultprepmethod=='') { $defaultprepmethod = "Pressed"; }
   @$defaultproject = substr(preg_replace('/[^0-9A-Za-z\. \-]/','',$_GET['defaultproject']),0,255);

   echo "<form action='ajax_handler.php' method='GET' id='rapidForm' >\n";
   //echo "<div dojoType='dijit.form.Form' id='rapidForm' jsId='rapidForm' encType='multipart/form-data' action='ajax_handler.php' method='GET'>";
   echo "<input type=hidden name='druid_action' value='rapidaddprocessor'>";

   echo '<div dojoType="dijit.layout.AccordionContainer" region="left" layoutPriority="1">';
   echo '<div dojoType="dijit.layout.ContentPane" title="Required">';
   echo "<table>\n";
   field ("barcode","Barcode",'','true','[0-9]{1,8}');   // not zero padded when coming off barcode scanner.
   selectAcronym("herbariumacronym",$defaultherbarium);
   selectCurrentID("filedundername","Filed under name",'true');   // filed under
   if(inProfile($profile, 'fiidentificationqualifier','main')) { fieldselectpicklist("fiidentificationqualifier",'Id qualifier','','false','fiidqualifierpl',26); }
   if(inProfile($profile, 'fiidentifiedby','main')) { selectCollectorsID("fiidentifiedby","Identified by"); } // for current id
   if(inProfile($profile, 'fideterminertext','main')) {field ("fideterminertext", "Ident by (text)"); }
   if(inProfile($profile, 'fidateidentified','main')) {field ("fidateidentified","Date identified",'','false','[0-9-]+','2010-03-18'); }  // for current id
   if(inProfile($profile, 'fiannotationtext','main')) {field ("fiannotationtext", "Annotation text"); }

   if(inProfile($profile, 'provenance','main')) { field ("provenance", "Provenance"); }
   if(inProfile($profile, 'container','main')) { selectContainerID ("container","Container"); }
   if(inProfile($profile, 'collectingtrip','main')) { selectCollectingTripID("collectingtrip","Collecting Trip"); }
   if(inProfile($profile, 'highergeography','main')) { staticvalue("Country limit",$defaultcountry); }
   if(inProfile($profile, 'highergeography','main')) { staticvalue("State limit",$defaultprimary); }
   if(inProfile($profile, 'highergeography','main')) { selectHigherGeography ("highergeography","Higher Geography",$defaultcountry,$defaultprimary); } // higher picklist limited by country/primary
   if(inProfile($profile, 'specificlocality','main')) { field ("specificlocality","Verbatim locality",'','true'); }
   if(inProfile($profile, 'host','main')) { field ("host","Host"); }
   if(inProfile($profile, 'substrate','main')) { field ("substrate", "Substrate"); }
   if(inProfile($profile, 'collectors','main')) { selectCollectorsID('collectors','Collector(s)','true'); }
   if(inProfile($profile, 'etal','main')) {field ("etal","et al."); }
   if(inProfile($profile, 'fieldnumber','main')) {field ("fieldnumber","Collector number"); }
   if(inProfile($profile, 'datecollected','main')) { field ("datecollected","Date collected",'','false','[0-9-/]+','2010-03-18'); }  // ISO, see https://code.google.com/p/applecore/wiki/CollectionDatea
   if(inProfile($profile, 'verbatimdate','main')) { field ("verbatimdate","Verb. date coll."); }

   if(inProfile($profile, 'currentdetermination','main')) { selectCurrentID("currentdetermination","Current Id");  }  // current id
   if(inProfile($profile, 'identificationqualifier','main')) { fieldselectpicklist("identificationqualifier",'Id qualifier','','false','idqualifierpl',26); }
   if(inProfile($profile, 'identifiedby','main')) { selectCollectorsID("identifiedby","Identified by"); } // for current id
   if(inProfile($profile, 'determinertext','main')) {field ("determinertext", "Ident by (text)"); }
   if(inProfile($profile, 'dateidentified','main')) {field ("dateidentified","Date identified",'','false','[0-9-]+','2010-03-18'); }  // for current id
   if(inProfile($profile, 'annotationtext','main')) {field ("annotationtext", "Annotation text"); }

   if(inProfile($profile, 'label_name','main')) { selectCurrentID("label_name","Label Id");  }  // current id
   if(inProfile($profile, 'label_identificationqualifier','main')) { fieldselectpicklist("label_identificationqualifier",'Id qualifier (label)','','false','label_idqualifierpl',26); }
   if(inProfile($profile, 'label_identifiedby','main')) { selectCollectorsID("label_identifiedby","Identified by"); } // for current id
   if(inProfile($profile, 'label_determinertext','main')) {field ("label_determinertext", "Ident by (text)"); }
   if(inProfile($profile, 'label_dateidentified','main')) {field ("label_dateidentified","Date identified",'','false','[0-9-]+','2010-03-18'); }  // for current id
   if(inProfile($profile, 'label_annotationtext','main')) {field ("label_annotationtext", "Annotation text"); }

   fieldselectpicklist("prepmethod",'Preparation method',$defaultprepmethod,'true','prepmethodpl',55);
   preptypeselect("format","Format",$defaultformat,'true','formatStore','huh_preptype','Name');
   selectProject("project","Project",$defaultproject);

   echo "<tr><td></td><td>";
   //echo "<input type=submit value='Add'>";
   echo "<button type='submit' dojoType='dijit.form.Button' id='submitButton'>Add</button>";
   if (ENABLE_DUPLICATE_FINDING===TRUE) {
       echo "<button type='button' dojoType='dijit.form.Button' id='grabLichenButton'
                     onclick='on_grab_lichen_click(); return false;'>Grab Lichen</button>";
       echo "<button type='button' dojoType='dijit.form.Button' id='grabNevpButton'
                     onclick='on_grab_nevp_click(); return false;'>Grab NEVP</button>";
   }
   echo "</td></tr>";
   echo "</table>\n";
   echo '</div>';
   echo '</div>';
   echo '<div dojoType="dijit.layout.AccordionContainer" region="center" >';

   echo '<div dojoType="dijit.layout.AccordionPane" title="Georeference">';
   echo "<table>\n";
   field ("verbatimlat","verbatim latitude  &deg; ' \"");
   field ("verbatimlong","verbatim longitude &deg; ' \"");
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
   echo "<table><tr><td valign='top'><table>\n";

   if(inProfile($profile, 'fiidentificationqualifier','additional')) { fieldselectpicklist("fiidentificationqualifier",'Id qualifier (filed)','','false','fiidqualifierpl',26); }
   field ("specimenremarks","Specimen remarks");
   field ("specimendescription","Specimen (collection object) description");
   field ("itemdescription", "Item description");
   field ("verbatimelevation","Verbatim elevation");
   //field ("minelevation","Min. elevation meters",'','false','[0-9]*');
   //field ("maxelevation","Max. elevation meters",'','false','[0-9]*');

   field ("habitat","Habitat");  // https://code.google.com/p/applecore/wiki/Habitat
   fieldselectpicklist("phenology",'Reproductive condition','','false','repcondpl',54);  // https://code.google.com/p/applecore/wiki/Phenology
   staticvalue("See also:","<a href='https://code.google.com/p/applecore/wiki/Habitat'>Habitat</a>&nbsp;<a href='https://code.google.com/p/applecore/wiki/Phenology'>ReproductiveCondition</a>");

   if(inProfile($profile, 'host','additional')) { field ("host","Host"); }
   if(inProfile($profile, 'substrate','additional')) { field ("substrate", "Substrate"); }
   if(inProfile($profile, 'provenance','additional')) { field ("provenance", "Provenance"); }
   if(inProfile($profile, 'container','additional')) { selectContainerID ("container","Container"); }
   if(inProfile($profile, 'collectingtrip','additional')) { selectCollectingTripID("collectingtrip","Collecting Trip"); }

   echo "</table></td>\n";
   echo "<td valign=\"top\" style=\"padding: 25px\"><table>\n";

   selectRefWorkID("exsiccati","Exsiccati");
   field ("exsiccatinumber","Number");
   field ("fascicle","Fascicle");
   echo "<BR>";
   if(inProfile($profile, 'highergeography','additional')) { staticvalue("Country limit",$defaultcountry); }
   if(inProfile($profile, 'highergeography','additional')) { staticvalue("State limit",$defaultprimary); }
   if(inProfile($profile, 'highergeography','additional')) { selectHigherGeography ("highergeography","Higher Geography",$defaultcountry,$defaultprimary); } // higher picklist limited by country/primary
   if(inProfile($profile, 'collectors','additional')) { selectCollectorsID('collectors','Collector(s)','true'); }
   if(inProfile($profile, 'etal','additional')) {field ("etal","et al."); }
   if(inProfile($profile, 'fieldnumber','additional')) {field ("fieldnumber","Collector number"); }

   echo "</table></td></tr>\n";
   echo "<tr><td valign='center'><table>\n";

   field ("accessionnumber", "Accession number");
   selectStorageID("storage","Subcollection");
   field ("storagelocation","Storage Location");
   if(inProfile($profile, 'specificlocality','additional')) { field ("specificlocality","Verbatim locality",'','true'); }
   if(inProfile($profile, 'datecollected','additional')) { field ("datecollected","Date collected",'','false','[0-9-/]+','2010-03-18'); }
   if(inProfile($profile, 'verbatimdate','additional')) { field ("verbatimdate","Verb. date coll."); }

   if(inProfile($profile, 'currentdetermination','additional')) { selectCurrentID("currentdetermination","Current Id");  }  // current id
   if(inProfile($profile, 'identificationqualifier','additional')) { fieldselectpicklist("identificationqualifier",'Id qualifier (curr.)','','false','idqualifierpl',26); }
   if(inProfile($profile, 'identifiedby','additional')) { selectCollectorsID("identifiedby","Identified by"); } // for current id
   if(inProfile($profile, 'determinertext','additional')) {field ("determinertext", "Identified by (text)"); }
   if(inProfile($profile, 'dateidentified','additional')) {field ("dateidentified","Date identified",'','false','[0-9-]+','2010-03-18'); }  // for current id


   echo "</table></td>\n";
   echo "<td valign='top'><table>\n";

   staticvalue("","Type Specimen Information");
   fieldselectpicklist("typestatus",'Type status','','false','typestatuspl',56);
   fieldselectpicklist("typeconfidence",'Confidence','','false','typeconfidencepl',47);
   selectCurrentID("basionym","Basionym");
   selectRefWorkID("publication","Publication");
   field ("page","Vol: Page");
   field ("datepublished","Year published");
   selectYesNo ("isfragment","Is fragment");

   echo "</table></td></tr>\n";

   if (ENABLE_DUPLICATE_FINDING===TRUE) {
      echo "<tr><td><div id='fp-data-entry-plugin-goes-here'></div></td><td></td></tr>";
   }
   echo "</table>\n";
   echo '</div>'; // accordion pane

   echo '<div dojoType="dijit.layout.AccordionPane" selected="true" title="Entering Data">';
   echo "<table>\n";
   staticvalue("Required:","Fill in all fields under Required if there is data to put in them.  You cannot leave Herbarium acronym, Barcode, Filed under Name, Higher Geography, Verbatim Locality, Preparation Method, or Format Blank.");
   staticvalue("Picklists:","For people and taxa: type part of the value and wait for the picklist to popup.  Use * as a wildcard, Collector='J*Mack' finds 'J. A. Macklin'.  For qualifiers, format, etc, you can just pull down the list.");
   staticvalue("Dates:","Must be in the form 2011-02-25 (except for verbatim dates). 2011-05 and 2011 are allowed to express just month or year.");
   staticvalue("DateCollected:","Also allows ranges with start and end dates separated by a slash: e.g. 2006/2008,  1886-11/1887-02, 1902-03-16/1902-03-18, 1912-03/1922 ");
   staticvalue("Barcode:","Required. Up to 8 digits, can be zero padded, e.g. 00154335.");
   staticvalue("FiledUnderName:","Required.The taxon name on the folder the specimen is in.");
   staticvalue("CurrentID:","The most recent identification on the sheet, leave blank if the same as Filed Under Name.  Identified by and date identified pertain to the Current Id.");
   staticvalue("Collector(s)","The names of people (collectors, determiners, georeferencers) must be present as agents with variant names of type Label/Collector name in Specify before they can be entered here.");
   staticvalue("Country/State:","Change the default settings for the higher geography filter on the Set Default Values, note that this resets all form entries to blank values.");
   staticvalue("Clear Button:","The button <button id='buttonResetDummy' dojoType='dijit.form.Button' data-dojo-type='dijit/form/Button' type='button' data-dojo-props=\"iconClass:'dijitIconClear'\" ></button> will clear the field next to the button.  You must use this button to change the field to an empty value, simply erasing the current value will still retain and submit that value.");
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
   staticvalue("","You can leave one of these filters blank.  Select \"yes\" on clear both to clear both filters.");
   staticvalue("","If you set a country and a state, the state will be used as the filter.");
   geographyselect("defaultcountry","Country limit",$defaultcountry,'false','country');
   geographyselect("defaultprimary","State/province limit",$defaultprimary,'false','primary');
   selectYesNo ("cleardefaultgeography","Clear both Country and State/Province limit");
   selectAcronym("defaultherbarium",$defaultherbarium);
   fieldselectpicklist("defaultprepmethod",'Preparation method','Pressed','true','prepmethodpl',55);
   preptypeselect("defaultformat","Format",'Sheet','true','formatStore','huh_preptype','Name');
   selectProject ("defaultproject","Project",$defaultproject);
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
   echo "<input name=$name value='$default' dojoType='dijit.form.ValidationTextBox' required='$required' $regex $placeholder  style='width: ".BASEWIDTH."em; ' >";
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
	searchAttr='label' value='$default' ></td></tr>";
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
   echo "<select name=\"$field\" dojoType=\"dijit.form.Select\">
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
   if ($field=="filedundername") {
      $onchangeval = "onchange='setNamesToFiledUnder();'";
   }
   $returnvalue = "<tr><td><div dojoType='custom.ComboBoxReadStore' jsId='taxonStore$field'
	 url='ajax_handler.php?druid_action=returndistinctjsonidnamelimited&table=huh_taxon&field=FullName'> </div>";
   $width = BASEWIDTH - 3;
   $returnvalue .= "<label for=\"$field\">$label</label></td><td>
	<input type=text name=$field id=$field dojoType='dijit.form.FilteringSelect'
	store='taxonStore$field' required='$required' searchDelay='900' hasDownArrow='false'
    style='width: ".$width."em; border-color: blue; '
	searchAttr='name' value='' $onchangeval>
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
	store='taxonStore$field' required='$required' searchDelay='900' hasDownArrow='false'
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
	store='agentStore$field' required='$required' searchDelay='900' hasDownArrow='false'
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
	store='agentStore$field' required='$required' searchDelay='900' hasDownArrow='false' style='border-color: blue;'
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
	store='agentStore$field' required='$required' searchDelay='900' hasDownArrow='false' style='border-color: blue;'
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
	store='agentStore$field' required='$required' searchDelay='900' hasDownArrow='false' style='border-color: blue;'
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
	store='collectingTripStore$field' required='$required' searchDelay='900' hasDownArrow='false' style='border-color: blue;'
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

/**
 *  Step one of a batch upload.  Describe the upload format and allow upload of a csv file.
 */
function uploadspreadsheet() {
   global $expectedheaderquoted;
   echo '
<div dojoType="dijit.layout.ContentPane" region="center" layoutPriority="3" splitter="false">
<p>Upload a csv file for bulkloading specimen minimal specimen records</p>
<form enctype="multipart/form-data" action="rapid.php" method="POST">
    <input type="hidden" name="display" value="spreadsheet" />
    <input type="hidden" name="MAX_FILE_SIZE" value="30000" />
    <input name="uploadfile" type="file" />
    <input type="submit" value="Upload" />
</form>
<p>File must be a CSV file, using comma as a separator, with a header exactly matching the following specification:</p>
<p>'.$expectedheaderquoted.'</p>
<p>Quotation marks should be used to enclose each field, and quotation marks within fields should be escaped with a backslash e.g. <strong>"field with a \" quote","next field"</strong><p>
<p>Download a spreadsheet <a href="entry_spreadsheet.xls">template</a>.  Works best with OpenOffice/LibreOffice using SaveAs, file type Text CSV, Edit filter settings, character set unicode, field delimiter: comma, text delimiter ".</p>
<p>Certain fields are required, certain fields must exactly match existing values in the database.  See the second sheet in the template for instructions.</p>
</div>
   ';

}

/**
 * Step three of a batch upload.  Validate the spreadsheet, lookup values for each line, ingest into database,
 * and report on results.
 */
function processspreadsheet() {
global $debug, $expectedheader, $expectedheaderquoted,
   $collectors,$etal,$fieldnumber,$verbatimdate,$datecollected,$herbariumacronym,$barcode,
   $filedundername,$fiidentificationqualifier,$currentdetermination,$identificationqualifier,$highergeography,
   $specificlocality,$prepmethod,$format,$verbatimlat,$verbatimlong,$decimallat,$decimallong,$datum,
   $coordinateuncertanty,$georeferencedby,$georeferencedate,$georeferencesource,$typestatus, $basionym,
   $publication,$page,$datepublished,$isfragment,$habitat,$phenology,$verbatimelevation,$minelevation,$maxelevation,
   $identifiedby,$dateidentified,$specimenremarks,$container,$utmzone,$utmeasting,$utmnorthing;


$filename = substr(preg_replace('/[.]{2}/','',$_POST['filename']),0,255);

//TODO: Embed processing within spreadsheet().

ini_set("auto_detect_line_endings", true);
$handle = fopen("uploads/$filename", "r");
$report = fopen("uploads/results_$filename", "w");
$successcount = 0;
$failurecount = 0;
if ($handle !== FALSE) {
    $header = fgets($handle);
    fputs($report,"result,".$header);
    $recognized = false;
    if (trim($header)==trim($expectedheader)) {
       $recognized = true;
       $enclosed = '"';
       $delimiter = ",";
    }
    if (trim($header)==trim($expectedheaderquoted)) {
       $recognized = true;
       $enclosed = '"';
       $delimiter = ",";
    }
    if (!$recognized) {
      $results = "<p><strong>Format not recognized.</strong>  Must be comma separated values with exact match on expected header line.</p>";
      $results .= "<table><tr><td>Expected header</td><td>$expectedheaderquoted</td></tr>";
      $results .= "<tr><td>Found header</td><td>$header</td></tr></table>";
    } else {
       $row = 1;
       while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
          $message = "";
          $num = count($data);
          if ($num==44) {
             // get field values from spreadsheet
             $herbariumacronym=$data[0];
             $barcode=$data[1];
             $collectors=$data[2];
             $etal=$data[3];
             $fieldnumber=$data[4];
             $verbatimdate=$data[5];
             $datecollected=$data[6];
             $filedundername=$data[7];
             $filedunderauthorship=$data[8];
             $fiidentificationqualifier=$data[9];
             $identifiedby=$data[10];
             $dateidentified=$data[11];
             $country=$data[12];
             $state=$data[13];
             $county=$data[14];
             $specificlocality=$data[15];
             $prepmethod=$data[16];
             $format=$data[17];
             $verbatimlat=$data[18];
             $verbatimlong=$data[19];
             $decimallat=$data[20];
             $decimallong=$data[21];
             $datum=$data[22];
             $coordinateuncertanty=$data[23];
             $georeferencedby=$data[24];
             $georeferencedate=$data[25];
             $georeferencesource=$data[26];
             $utmzone=$data[27];
             $utmeasting=$data[28];
             $utmnorthing=$data[29];
             $typestatus=$data[30];
             $basionym=$data[31];
             $basionymauthorship=$data[32];
             $publication=$data[33];
             $page=$data[34];
             $datepublished=$data[35];
             $isfragment=$data[36];
             $habitat=$data[37];
             $phenology=$data[38];
             $verbatimelevation=$data[39];
             $minelevation=$data[40];
             $maxelevation=$data[41];
             $specimenremarks=$data[42];
             $container=$data[43];
             // do database lookups
             $lookupok = true;
             $agentvariant = new huh_agentvariant();
             // collectors
             $termsarray = array();
             $termsarray['Name']=$collectors;
             $termsarray['VarType']='4';
             $matches = $agentvariant->loadArrayKeyValueSearch($termsarray);
             if (count($matches)==1) {
                 $collectors = $matches[0]->getAgentID();
             } else {
               $message .= "No match on Collector(s) [$collectors]. ";
               $lookupok = false;
             }
             // identified by
             if ($identifiedby!='') {
                $termsarray = array();
                $termsarray['Name']=$identifiedby;
                $termsarray['VarType']='4';
                $matches = $agentvariant->loadArrayKeyValueSearch($termsarray);
                if (count($matches)==1) {
                    $identifiedby = $matches[0]->getAgentID();
                } else {
                  $message .= "No match on Identified By [$identifiedby]. ";
                  $lookupok = false;
                }
             }
             // georeferenced by
             if ($georeferencedby!='') {
                $termsarray = array();
                $termsarray['Name']=$georeferencedby;
                $termsarray['VarType']='4';
                $matches = $agentvariant->loadArrayKeyValueSearch($termsarray);
                if (count($matches)==1) {
                    $georeferencedby = $matches[0]->getAgentID();
                } else {
                  $message .= "No match on Georeferenced By [$georeferencedby]. ";
                  $lookupok = false;
                }
             }
             $taxon = new huh_taxon();
             // identification
             $termsarray = array();
             $termsarray['FullName']=$filedundername;
             $termsarray['Author']=$filedunderauthorship;
             $matches = $taxon->loadArrayKeyValueSearch($termsarray);
             if (count($matches)==1) {
                $filedundername = $matches[0]->getTaxonID();
             } else {
                $message .= "No match on Identification [$filedundername][$filedunderauthorship]. ";
                $lookupok = false;
             }

             // geography
             $highergeography='';
             $geography = new huh_geography();
             $termsarray = array();
             $termsarray['FullName']="$county ($state)";
             $matches = $geography->loadArrayKeyValueSearch($termsarray);
             if (count($matches)==1) {
                $highergeography = $matches[0]->getGeographyID();
             } else {
                // TODO lookup parentages
                $termsarray['FullName']="$state";
                $matches = $geography->loadArrayKeyValueSearch($termsarray);
                if (count($matches)==1) {
                   $highergeography = $matches[0]->getGeograhyID();
                } else {
                   $message .= "No match on Geography [$county ($state)][$country][$state][$county]. ";
                   $lookupok = false;
                }
             }

             // basionym
             if ($basionym!='') {
                $termsarray = array();
                $termsarray['FullName']=$basionym;
                $termsarray['Author']=$basionymauthorship;
                $matches = $taxon->loadArrayKeyValueSearch($termsarray);
                if (count($matches)==1) {
                    $basionym = $matches[0]->getTaxonID();
                } else {
                  $message .= "No match on Basionym [$basionym][$basionymauthorship]. ";
                  $lookupok = false;
                }
             }
             // TODO publication
             $pub = new huh_referencework();
             if ($publication!='') {
                $termsarray = array();
                $termsarray['title']=$publication;
                $matches = $pub->loadArrayKeyValueSearch($termsarray);
                if (count($matches)==1) {
                    $publication = $matches[0]->getReferenceworkID();
                } else {
                  $message .= "No match on Publication [$publication]. ";
                  $lookupok = false;
                }
             }
             // TODO container
             $cont = new huh_container();
             if ($container!='') {
                $termsarray = array();
                $termsarray['Name']=$container;
                $matches = $cont->loadArrayKeyValueSearch($termsarray);
                if (count($matches)==1) {
                    $Container = $matches[0]->getContainerID();
                } else {
                  $message .= "No match on Container [$container]. ";
                  $lookupok = false;
                }
             }

             $oktoingest = true;
             // Test for success in all pk lookups
             // collectors, highergeography, filedundername are required and numeric only.
             $collectors = preg_replace("/[^0-9]/","",$collectors);
             $highergeography = preg_replace("/[^0-9]/","",$highergeography);
             $filedundername = preg_replace("/[^0-9]/","",$filedundername);
             if (!$lookupok) {
                $oktoingest = false;
             }
             // Test for required fields.
             if ($highergeography=='' || $herbariumacronym=='' || $filedundername=='' || $prepmethod=='' || $format=='' || $specificlocality=='' || $barcode=='' || $collectors=='' ) {
                $oktoingest = false;
                $message .= "Missing required field";
             }
             // ingest data
                $results .= "<strong>$herbariumacronym-$barcode</strong> ";
             if ($oktoingest) {
                $debug = false;
                $message .= ingestCollectionObject();
                $results .= $message;
                if (substr(strip_tags($message),0,2)=="OK") {
                    $successcount++;
                } else {
                    $failurecount++;
                }
             } else {
                $failurecount++;
                $results .= $message."<BR>";
             }
             $row = $enclosed.strip_tags($message).$enclosed.$delimiter.toCsvLine($data,$delimiter,$enclosed);
          } else {
             // row has an error, perhaps delimiter in text.
             $row = $enclosed."Bad line".$enclosed.$delimiter.toCsvLine($data,$delimiter,$enclosed);
             $results .= "Bad Line<BR>";
             $failurecount++;
          }
          fputs($report,$row."\n");
       }
    }
    fclose($handle);
    fclose($report);
} else {
  $results .= "Error: Can't read uploaded file.";
}

if ($successcount>0) {
   $resultsummary .= "Successfully added $successcount rows. ";
}
if ($failurecount>0) {
   $resultsummary .= "<strong>Failure adding $failurecount rows.<strong>";
}

   echo '
<div dojoType="dijit.layout.ContentPane" region="center" layoutPriority="3" splitter="false">
  <p>Processed '.$filename.'</p>
  <p>'.$resultsummary.'</p>
  <p><a href="uploads/results_'.$filename.'">Download Processing Report Spreadsheet</a></p>
  <div>'.$results.'</div><BR>
  <!-- div dojoType="dijit.ProgressBar" style="width:80%;" jsId="jsProgress" id="ProcessingProgress" maximum="100" >
  </div -->
</div>
   ';

}

/**
 *  Given an array, produce a line for a csv file.
 */
function toCsvLine($data,$delimiter,$enclosed) {
   $result = "";
   $comma = "";
   for ($i=0; $i<count($data);$i++) {
      $result .= $comma.$enclosed.$data[$i].$enclosed;
      $comma = $delimiter;
   }
   return $result;
}

/**
 * Step two of batch upload process.  Check that the uploaded file has the expected format and
 * display its contents if it does.
 */
function spreadsheet() {
global $expectedheader, $expectedheaderquoted;

$filename = "";

$uploaddir = "/var/www/htdocs/huh_rapid/uploads/";
$uploadfile = $uploaddir . basename($_FILES['uploadfile']['name']);
if (move_uploaded_file($_FILES['uploadfile']['tmp_name'], $uploadfile)) {
    $filename = "uploads/".basename($uploadfile);
    $uploadresult = "File successfully uploaded: " . basename($uploadfile);
} else {
    $uploadresult = "Upload unsuccessful";
}

ini_set("auto_detect_line_endings", true);
$handle = fopen("$filename", "r");
if ($handle !== FALSE) {
    $header = fgets($handle);
    $recognized = false;
    if (trim($header)==trim($expectedheader)) {
       $recognized = true;
       $enclosed = '';
    }
    if (trim($header)==trim($expectedheaderquoted)) {
       $recognized = true;
       $enclosed = '"';
    }
    if (!$recognized) {
      $uploadresult .= "<p><strong>Format not recognized.</strong>  Must be comma separated values with exact match on expected header line.</p>";
      $uploadresult .= "<table><tr><td>Expected header</td><td>$expectedheaderquoted</td></tr>";
      $uploadresult .= "<tr><td>Found header</td><td>$header</td></tr></table>";
    }
}

echo '
<script type="text/javascript">
    dojo.addOnLoad(function() {
        dojo.require("dojox.data.CsvStore");
        dojo.require("dojo.parser")
        dojo.require("dojox.grid.EnhancedGrid");
        dojo.require("dojox.grid.enhanced.plugins.NestedSorting");

        var csvstore = new dojox.data.CsvStore({
            url: \''.$filename.'\'
        });

        var grid = new dojox.grid.EnhancedGrid({
            store: csvstore,
            structure: [
                {name:"Herbarium", field:"herbarium"},
                {name:"Barcode", field:"barcode"},
                {name:"Collector(s)", field:"Collector(s)"},
                {name:"et al.", field:"et al"},
                {name:"Field number", field:"field number"},
                {name:"Verbatim date", field:"verbatim date"},
                {name:"ISO date collected", field:"ISO date collected"},
                {name:"Identification", field:"identification"},
                {name:"Authorship", field:"authorship"},
                {name:"Qualifier", field:"qualifier"},
                {name:"Identified by", field:"identified by"},
                {name:"Date identified", field:"date identified"},
                {name:"Country", field:"country"},
                {name:"Primary division", field:"primary division"},
                {name:"Secondary division", field:"secondary division"},
                {name:"Specific locality", field:"specific locality"},
                {name:"Prep method", field:"prep method"},
                {name:"Format", field:"format"},
                {name:"Verbatim lat", field:"verbatim lat"},
                {name:"Verbatim long", field:"verbatim long"},
                {name:"Decimal lat", field:"decimal lat"},
                {name:"Decimal long", field:"decimal long"},
                {name:"Datum", field:"datum"},
                {name:"Coordinate uncertainty", field:"coordinate uncertainty"},
                {name:"Georeferenced by", field:"georeferenced by"},
                {name:"Georeference date", field:"georeference date"},
                {name:"Georeference source", field:"georeferencesource"},
                {name:"utm zone", field:"utm zone"},
                {name:"utm easting", field:"utm easting"},
                {name:"utm northing", field:"utm northing"},
                {name:"type status", field:"type status"},
                {name:"Basionym", field:"basionym"},
                {name:"Basionym authorship", field:"basionynauthorship"},
                {name:"publication", field:"publication"},
                {name:"page", field:"page"},
                {name:"year published", field:"year published"},
                {name:"is fragment", field:"is fragment"},
                {name:"habitat", field:"habitat"},
                {name:"phenology", field:"phenology"},
                {name:"verbatim elevation", field:"verbatim elevation"},
                {name:"min elevation", field:"min elevation"},
                {name:"max elevation", field:"max elevation"},
                {name:"specimen remarks", field:"specimen remarks"},
                {name:"container", field:"container"}
            ],
            clientSort: true,
            rowSelector: "20px",
            plugins: {
                nestedSorting: true
            }
        },
        document.createElement("div"));
        dojo.byId("loadedGrid").appendChild(grid.domNode);
        grid.startup();
    });
</script>';

echo '
<div dojoType="dijit.layout.ContentPane" region="center" layoutPriority="3" splitter="false">';
  echo "<p>$uploadresult</p>";
  if ($recognized) {
  echo'
  <div dojoType="dijit.form.Form" action="rapid.php" method="POST" >
    <input type="hidden" name="display" value="process" />
    <input type="hidden" name="filename" value="'.basename($uploadfile).'" />
    <button dojoType="dijit.form.Button" type="submit" name="submitButton" value="Submit">Load these records</button>
  </div>';
  }
  echo'
  <div id="loadedGrid" style="width: 100%; height: 80%;">
  </div>
</div>
';

}


?>
