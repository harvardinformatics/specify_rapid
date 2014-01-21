<?php
$_GET['allow_anyway']=1;
if (!isset($_GET['callback']) || !preg_match('/^[a-z][\w.]*$/',$_GET['callback'])) {
	echo 'valid callback param is required.';
} else {
	echo $_GET['callback'].'(';
	include('ajax_handler.php');
	echo ');';
}
