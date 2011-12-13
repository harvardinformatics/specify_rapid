<?php 
// *******  This file won't be overwritten by druid if PHP code is regenerated.
// *******  You may customize this file for your own purposes.
?>
<?php
// Autogenerated by Druid from MySQL db Build:6
// sets up simpleTest for php unit tests on classes
// run unit tests with: php all_tests.php

// load simpletest framework and files containing classes to test
require_once('simpletest/unit_tester.php');
require_once('../class_lib.php');

// define test cases

/* class testOfUser
* Unit tests for class User.
*/
class testOfUser extends UnitTestCase {

  function testConstructor() {
     $user = new User('','');
     $this->assertNotNull($user);
     $this->assertFalse($user->getAuthenticationState(),'User authentication state must be false for newly constructed user object.');
     $this->assertIdentical($user->getUserHtml(),'');
     $this->assertIdentical($user->getFullname(),'');
  }

  function testAuthentication() {
     $invaliduser = new User('','');
     $this->assertFalse($invaliduser->authenticate(),'Invalid user authenticated.');
     $this->assertIdentical($invaliduser->getUserHtml(),'');
     $this->assertIdentical($invaliduser->getFullname(),'');
  }

  function testTicket() {
      $user = new User('','');
      $ip = '127.0.0.1';
      $ticket = $user->setTicket($ip);
      $this->assertTrue($user->validateTicket($ticket,$ip));
      $this->assertFalse($user->validateTicket('invalidticket',$ip));
      $this->assertFalse($user->validateTicket($ticket,'invalidip'));
  }
 

}
?>

