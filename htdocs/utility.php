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

$display = '';
$display = substr(preg_replace('/[^a-z]/','',$_GET['display']),0,20);
if ($display=='') {
   $display = substr(preg_replace('/[^a-z]/','',$_POST['display']),0,20);
}
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
         $error = "Login to HUH Specify Utilities";
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
$apage->setTitle("HUH Specify Utilities");
$apage->setErrorMessage($error);
$apage->setTargetPage("utility.php");

echo $apage->getHeader($user);

switch ($display) {

   case 'mainform':
      form();
      break;
//   case 'mergepreps':
//      mergeform();
//      break;
   case 'logout':
      $user->logout();
   case "logindialog":
   default:
      // $email = $username; // causing errors, doesn't seem to be used
      $username=null;
      if (@$_GET['email']!="") {
         $username = substr(preg_replace("/[^0-9A-Za-z\.\@\-\_]/","",$_GET['email']),0,255);
      }
      $title = "Specify Login";
      echo '<div dojoType="dijit.layout.ContentPane" region="center" >';
      echo "
               <form method=POST action='utility.php' name=loginform id=loginform>
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

// NOTE: Page header isn't used, you  want Page->getHeader
function pageheader($error="") {
   global $user;
   echo "<!DOCTYPE html>\n";
   echo "<html>\n";
   echo "<head>\n";
   echo "<title>HUH Specify Utilities</title>\n";
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
      echo $user->getUserHtml("utility.php");
   }
   echo '</div>';
}

function form() {

   echo '
           <script type="text/javascript">
            dojo.addOnLoad( function() {
                var form = dojo.byId("mergeForm");

                dojo.connect(form, "onsubmit", function(event) {
                     dojo.stopEvent(event);

                     var xhrArgs = {
                        form: dojo.byId("mergeForm"),
                        handleAs: "text",
                        load: function(data) {
                              dojo.byId("checkresult").innerHTML = data;
                              dojo.byId("feedback").innerHTML = "";
                        },
                        error: function(error) {
                           dojo.byId("feedback").innerHTML = error;
                        }
                    }
                    dojo.byId("feedback").innerHTML = "Checking..."
                    dojo.byId("mergeresult").innerHTML = "";
                    dojo.byId("checkresult").innerHTML = "";

                    var deferred = dojo.xhrGet(xhrArgs);
              });
            });

            dojo.addOnLoad( function() {
                var form = dojo.byId("doMergeForm");

                dojo.connect(form, "onsubmit", function(event) {
                     dojo.stopEvent(event);

                     var xhrArgs = {
                        form: dojo.byId("doMergeForm"),
                        handleAs: "text",
                        load: function(data) {
                              dojo.byId("mergeresult").innerHTML = data;
                              dojo.byId("feedback").innerHTML = "";
                        },
                        error: function(error) {
                           dojo.byId("feedback").innerHTML = error;
                        }
                    }
                    dojo.byId("feedback").innerHTML = "Merging..."
                    dojo.byId("mergeresult").innerHTML = "";

                    var deferred = dojo.xhrGet(xhrArgs);
              });
            });

            dojo.addOnLoad( function() {
                var form = dojo.byId("structureForm");

                dojo.connect(form, "onsubmit", function(event) {
                     dojo.stopEvent(event);

                     var xhrArgs = {
                        form: dojo.byId("structureForm"),
                        handleAs: "text",
                        load: function(data) {
                              dojo.byId("structureresult").innerHTML = data;
                              dojo.byId("feedback").innerHTML = "";
                        },
                        error: function(error) {
                           dojo.byId("feedback").innerHTML = error;
                        }
                    }
                    dojo.byId("feedback").innerHTML = "Searching..."
                    dojo.byId("structureresult").innerHTML = "";

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



   echo '<div dojoType="dijit.layout.AccordionContainer" region="center" layoutPriority="1">';
   //
   echo '<div dojoType="dijit.layout.ContentPane" title="Merge Preparations">';
   echo "<form action='ajax_handler.php' method='GET' id='mergeForm' >\n";
   echo "<input type=hidden name='druid_action' value='prepmergeprocessorpre'>";
   echo "<table>\n";
   staticvalue("Instructions:","Enter the barcodes of two items that are on the same sheet, but which have different preparation records in Specify.");
   field ("targetbarcode","First Item Barcode",'','true','[0-9]{1,8}');   // not zero padded when coming off barcode scanner.
   field ("movebarcode","Second Item Barcode",'','true','[0-9]{1,8}');   // not zero padded when coming off barcode scanner.
   echo "<tr><td></td><td>";
   echo "<button type='submit' dojoType='dijit.form.Button' id='submitButton'>Pre-Merge Check</button>";
   echo "</td></tr>";
   echo "</table>\n";
   echo "</form>\n";
   echo "<div id='checkresult'></div>";
   echo "<div id='mergeresult'></div>";
   echo '</div>'; // content pane
   //
   $barcode = preg_replace("/[^0-9]/","",$_GET['barcode']);
   echo '<div dojoType="dijit.layout.ContentPane" title="Show Relationships" selected="true" >';
   echo "<form action='ajax_handler.php' method='GET' id='structureForm' >\n";
   echo "<input type=hidden name='druid_action' value='showstructure'>";
   echo "<table>\n";
   staticvalue("Instructions:","Enter a barcode to see how it is related to other data.");
   field ("barcode","Item Barcode",$barcode,'true','[0-9]{1,8}');   // not zero padded when coming off barcode scanner.
   echo "<tr><td></td><td>";
   echo "<button type='submit' dojoType='dijit.form.Button' id='submitButton'>Search</button>";
   echo "</td></tr>";
   echo "</table>\n";
   echo "</form>\n";
   echo "<div id='structureresult'></div>";
   echo '</div>'; // content pane
   //
   echo '</div>'; // accordion container


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

function staticvalue($label,$default) {
	$returnvalue = "<tr><td><label>$label</label></td><td>$default</td></tr>";
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
