<?php

$sql = array(
    'scientificName' => "
      SELECT DISTINCT taxonid, concat(FullName, ' ',ifnull(Author,''))
      FROM taxon
      WHERE FullName like ?
      ORDER BY FullName
      LIMIT 50",
    'recordedBy' => "
      SELECT DISTINCT agentid, name
      FROM agentvariant
      WHERE name like ?
        AND vartype = 4 -- What is this about?
      LIMIT 50",
    'typeStatus' => "
      SELECT DISTINCT value, title
      FROM picklistitem
      WHERE picklistid = 56
        AND value like ?
      ORDER BY Title ASC
      LIMIT 50");

//
// Validate
//

include_once('connection_library_ro.php');
if (!function_exists('specify_connect')) {
   die('specify_connect not defined.');
}
@$connection = specify_connect();

if (!isset($_GET['callback']) || !preg_match('/^[a-z][\w.]*$/',$_GET['callback'])) {
  die('valid "callback" param is required.');
}

if (!isset($_GET['name']) || !in_array($_GET['name'],array_keys($sql))) {
  die('valid "name" param is required.');
}

if (!isset($_GET['value'])) {
  die('"value" param is required.');
}

//
// Get the data
//

$type = $_GET['name'];
$stub = $_GET['value'];
$matches = array();
while ($stub && count($matches)<2) {
  // TODO: possibility that stripping one character expands the result set hugely and we lose the one of interest.
  // ... but I want to be sure this is the right track before spending time on that case.
  // For instance, maybe we want to stem the genus and species separately?
  $matches = get_matches($stub,$type);
  if (count($matches)==1) {
    $best = $matches[0];
  }
  $stub = substr($stub,0,-1);
}

if (isset($best) && count($matches)>1) {
  // Put the best one first:
  foreach ($matches as $i=>$match) {
    if ($best==$match) {
      unset($matches[$i]);
    }
  }
  array_unshift($matches,$best);
}

//
// Send it out
//

$json = json_encode(array(
  'name' => $_GET['name'],
  'list' => $matches
));

echo $_GET['callback'] . '(' . $json . ');';

//
// Functions
//

function get_matches($stub,$type) {
  global $sql;
  $matches = array();
  $stub_search = $stub . '%';
  global $connection;

  $stmt = $connection->prepare($sql[$type]);
  $stmt->bind_param("s",$stub_search);
  $stmt->execute();
  $stmt->bind_result($id, $val);
  $stmt->store_result();
  while ($stmt->fetch()) {
    $matches[] = array(strval($id),$val);
  }
  return $matches;
}

