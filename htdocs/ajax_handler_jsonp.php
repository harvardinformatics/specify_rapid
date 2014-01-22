<?php

include_once('connection_library_ro.php');
if (!function_exists('specify_connect')) {
   die('specify_connect not defined.');
}
@$connection = specify_connect();

if (!isset($_GET['callback']) || !preg_match('/^[a-z][\w.]*$/',$_GET['callback'])) {
  die('valid callback param is required.');
}

if (!isset($_GET['genus_species'])) {
  die('genus_species param is required.');
}

$stub = $_GET['genus_species'];
$matches = array();
while ($stub && count($matches)<2) {
  // TODO: possibility that stripping one character expands the result set hugely and we lose the one of interest.
  // ... but I want to be sure this is the right track before spending time on that case.
  // For instance, maybe we want to stem the genus and species separately?
  $matches = get_matches($stub);
  $stub = substr($stub,0,-1);
}

$json = json_encode($matches);

echo $_GET['callback'] . '(' . $json . ');';

function get_matches($genus_species) {
  $matches = array();
  $genus_species_search = $genus_species . '%';
  global $connection;
  $stmt = $connection->prepare("
    SELECT DISTINCT taxonid, concat(FullName, ' ',ifnull(Author,''))
    FROM taxon
    WHERE FullName like ?
    ORDER BY FullName
    LIMIT 50");
  $stmt->bind_param("s",$genus_species_search);
  $stmt->execute();
  $stmt->bind_result($id, $val);
  $stmt->store_result();
  while ($stmt->fetch()) {
    $matches[] = array($id,$val);
  }
  return $matches;
}

