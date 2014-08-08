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

include_once('connection_library.php');
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

if (!defined("json_encode")) { 

/**
 * Converts an associative array of arbitrary depth and dimension into JSON representation.
 *
 * NOTE: If you pass in a mixed associative and vector array, it will prefix each numerical
 * key with "key_". For example array("foo", "bar" => "baz") will be translated into
 * {"key_0": "foo", "bar": "baz"} but array("foo", "bar") would be translated into [ "foo", "bar" ].
 *
 * @param $array The array to convert.
 * @return mixed The resulting JSON string, or false if the argument was not an array.
 * @author Andy Rusterholz
 */
function json_encode( $array ){

    if( !is_array( $array ) ){
        return false;
    }

    $associative = count( array_diff( array_keys($array), array_keys( array_keys( $array )) ));
    if( $associative ){


        $construct = array();
        foreach( $array as $key => $value ){

            // We first copy each key/value pair into a staging array,
            // formatting each key and value properly as we go.

            // Format the key:
            if( is_numeric($key) ){
                $key = "key_$key";
            }
            $key = '"'.addslashes($key).'"';

            // Format the value:
            if( is_array( $value )){
                $value = json_encode( $value );
            } else if( !is_numeric( $value ) || is_string( $value ) ){
                $value = '"'.addslashes($value).'"';
            }

            // Add to staging array:
            $construct[] = "$key: $value";
        }

        // Then we collapse the staging array into the JSON form:
        $result = "{ " . implode( ", ", $construct ) . " }";

    } else { // If the array is a vector (not associative):

        $construct = array();
        foreach( $array as $value ){

            // Format the value:
            if( is_array( $value )){
                $value = json_encode( $value );
            } else if( !is_numeric( $value ) || is_string( $value ) ){
                $value = '"'.addslashes($value).'"';
            }

            // Add to staging array:
            $construct[] = $value;
        }

        // Then we collapse the staging array into the JSON form:
        $result = "[ " . implode( ", ", $construct ) . " ]";
    }

    return $result;
} 

}

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

