<?php

session_start();

include_once("connection_library.php");
include_once("class_lib.php");
include_once("transcribe_lib.php");

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
               $display = "mainform";
            } else {
               $display = $tdisplay;
            }
            $user->setTicket($_SERVER['REMOTE_ADDR']);
         } else {
            $error = "Incorrect email address or password.";
         }
      } else {
         $error .= "Login to HUH Specify Data Quick Entry Form";
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

# Show the page with the selected display mode.

$apage = new TPage();
$apage->setTitle("HUH Data Quick Entry Form");
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
               <form method=POST action='quick.php' name=loginform id=loginform>
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
      break;
}

echo $apage->getFooter($user);


// ********************************************************************************
// ****  Supporting functions  ****************************************************
// ********************************************************************************

function form() {
   global $user;

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


   if (strlen($targetbarcode==0)) {
       $enabled = 'true';
   } else {
       $enabled = 'false';
   }
   fieldEnabalable("barcode","Barcode",$targetbarcode,'required','[0-9]{1,8}','','Barcode must be a 1-8 digit number.',$enabled);   // not zero padded when coming off barcode scanner.
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

     @selectAcronym("herbariumacronym",$herbarium);
     @selectTaxon("filedundername","Filed Under",$filedundername,$filedundernameid,'true','true');
     @selectTaxon ("currentname","Current Name",$currentname,$currentnameid,'true','true');
     @selectQualifier("currentqualifier","ID Qualifier",$currentqualifier);
     @selectCollectorsID("identifiedby","Identified By",$identifiedby,$identifiedbyid,'false','false');
     @field ("dateidentified","Date Identified",$dateidentified,'false','([0-9]{4}(-[0-9]{2}){0,2}){1}(/([0-9]{4}(-[0-9]{2}){0,2}){1}){0,1}','','Use of an ISO format is required: yyyy, yyyy-mm, yyyy-mm-dd, or yyyy-mm-dd/yyyy-mm-dd');

     @selectCollectorsID("collectors","Collectors",$collectors,$collectoragentid,'true','false');
     @field ("etal","Et al.",$etal,'false');
     @selectCollectingTripID("collectingtrip","Collecting Trip",$collectingtrip,$collectingtripid,'false');
     @field ("stationfieldnumber","Collector Number",$stationfieldnumber,'false');
     @field ("datecollected","Date Collected",$datecollected,'false','([0-9]{4}(-[0-9]{2}){0,2}){1}(/([0-9]{4}(-[0-9]{2}){0,2}){1}){0,1}','','Use of an ISO format is required: yyyy, yyyy-mm, yyyy-mm-dd, or yyyy-mm-dd/yyyy-mm-dd','true');
     @field ("verbatimdate","Verbatim Date",$verbatimdate,'false');

     @selectContainerID("container","Container",$container,$containerid);
     @selectHigherGeography ("geographyfilter","Geography Within",$geographyfilter,$geographyfilterid,'','','false','true');
     @selectHigherGeographyFiltered ("highergeography","Higher Geography",$geography,$geographyid,'','','true');

     @field ("specificlocality","Verbatim locality",$specificLocality,'true');
     @field ("habitat","Habitat",$habitat);
     @field ("frequency", "Frequency", $frequency);
     @field ("verbatimelevation","Verbatim Elevation",$verbatimElevation,'false');

     @field ("provenance","Provenance",$provenance,'false');
     @field ("specimendescription","Description",$specimendescription,'false');
     @field ("specimenremarks","Remarks",$specimenremarks,'false');
     @selectProject("defaultproject","Project",$defaultproject);

   echo "<tr><td colspan=2>";
   echo "<input type='button' value='Save' id='saveButton' class='carryforward ui-button'> ";
   echo "</td></tr>";

   echo "<script>

          /* Set the value of a field if the field is empty or if the field is not a carryforward field.
           * @param field the id of the input for which to set the value
           * @param value the new value to set (unless the field is a carryforward with an existing value).
           */
          function setLoadedValue(field,value) {
              //if (!field=='datecollected') {
               if ($('select[name='+field+']').length) {
                   $('#'+field).css({'color':'black'});
               } else {
                   $('#'+field).css({'background-color':'#FFFFFF'});
               }
              //}
              if($('#'+field).val()=='') {
                 // if field is empty, populate from provided value.
                 $('#'+field).val(value);
              } else {
                 // field contains a value
                 //if (!field=='datecollected') {
                    // set color to indicate changed data
                  if ($('.carryforward[id][name='+field+']').length && $('#'+field).val()!=value) {
                     // if carryforward field and values are different, set background color
                     if ($('select[name='+field+']').length) {
                        $('#'+field).css({'color':'darksalmon'});
                     } else {
                       $('#'+field).css({'background-color':'#FFFAA2'});
                     }
                  }
                 //}
                 // update value from provided value.
                 // if (!$('.carryforward[id][name='+field+']').length) {
                 //     // if field contains a value only populate if not a carryforward field (carryforward trumps lookup).
                 //     $('#'+field).val(value);
                 // }
                 $('#'+field).val(value);
              }
          }

          function loadFormData(data) {
              var barcodeval = data.barcode;
              if (data.barcode==null || data.barcode=='NOTFOUND') {
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
                  setLoadedValue('defaultproject',data.project);
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
                  setLoadedValue('specificlocality',data.specificlocality);
                  setLoadedValue('habitat',data.habitat);
                  setLoadedValue('frequency',data.frequency);
                  setLoadedValue('highergeography',data.geography);
                  setLoadedValue('verbatimelevation',data.verbatimelevation);
                  setLoadedValue('geographyid',data.geographyid);
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
            $("#extrafieldsdiv").accordion( { heightStyle: "content" } ) ;
            $("#geofieldsdiv").accordion( { heightStyle: "content" } ) ;
          });
   </script>';

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

   $bak_basewidth = $GLOBALS['BASEWIDTH'];
   $GLOBALS['BASEWIDTH'] = 11;

   echo '<div id="geofieldsdiv" style="margin-top: 5px;">';
   echo '<h3 style="display: none; margin-top: 1px; margin-bottom: 0px;">Geodata fields</h3>';
   echo '<div>';
   echo '<table>';
   field ("verbatimlat","Verb. Lat.",$verbatimlat);
   field ("verbatimlong","Verb. Long.",$verbatimlong);
   field ("decimallat","Dec. Lat.",$decimallat,'false','\-?[0-9]{1,2}(\.{1}[0-9]*)?');
   field ("decimallong","Dec. Long.",$decimallong,'false','\-?[0-1]?[0-9]{1,2}(\.{1}[0-9]*)?');
   field ("georeferencesource",'Method',$georeferencesource,'false');
   //field ("datum","Datum",$datum); // almost never encountered on a label
   field ("coordinateuncertainty","Uncertainty",$coordinateuncertainty,'false','[0-9]*');
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
               minLength: 4,
               source: function( request, response ) {
                  $.ajax( {
                    url: "ajax_handler.php",
                    dataType: "json",
                    data: {
                       druid_action: "geoidgeojson",
                       term: request.term,
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
               minLength: 4,
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
   echo "<select id=\"$field\" name=\"$field\" >
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
         $(function() {
            $( "#'.$field.'" ).autocomplete({
               minLength: 4,
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
               minLength: 5,
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
        <input id='$field' value='$default' $req style='width: ".$GLOBALS['BASEWIDTH']."em; ' class='carryforward' >
     </div>
  </td></tr>
    ";
    echo $returnvalue;
}


?>
