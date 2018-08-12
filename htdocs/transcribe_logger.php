<?php
session_start();

$debug = false;
if ($debug) {
   echo  "[". $_SESSION["user_ticket"] . "][". $_SESSION["session_id"] ."]";
	// See PHP documentation.
	mysqli_report(MYSQLI_REPORT_ALL ^ MYSQLI_REPORT_STRICT);
} else { 
	mysqli_report(MYSQLI_REPORT_OFF);
}

include_once('class_lib.php');  // contains declaration of User() class

class Result {
  public $success;
  public $html;
  public $errors;
}


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

$retval = new Result();
$retval->html = "";
$retval->success = FALSE;
if ($connection && $authenticated) {
    # find the action
    @$test = substr(preg_replace('/[^a-z]/','',$_POST['test']),0,10);
    @$action = substr(preg_replace('/[^a-z]/','',$_POST['action']),0,10);
    $postjson = json_encode($_POST);
    $username = $_SESSION['username'];
    if (strlen($action)==0) { 
       $retval->errors .= "Logging Error: No action provided. ";
    } else {       
       # log the action
       $sql = "insert into TR_ACTION_LOG (username, action, details) values (?, ?, ?)";
       $stmt = $connection->prepare($sql);
       $stmt->bind_param("sss",$username,$action,$postjson);
       if ($stmt->execute()) {
           $retval->success = TRUE;
       } else {
           $retval->errors .= "Logging Error: " . $stmt->error . "  ";
       }
       $stmt->close();
    } 
} else { 
    $retval->errors .= "Logging Error: unable to connect. ";
}
 
if ($retval->success) {
   $retval->html = "<strong>Success.</strong> Logged $action";
} else {
   $retval->html = "<strong>Failed.</strong> ";
   $retval->html .= $retval->errors;
}

echo $retval->html;

?>