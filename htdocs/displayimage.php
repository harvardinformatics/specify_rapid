<?php 
session_start();

include_once("connection_library.php");
include_once("class_lib.php");
include_once("transcribe_lib.php");

@$connection = specify_connect();
if (!$connection) {
   $error =  'Error: No database connection. '. $targethostdb;
}

$mode= '';
@$mode = substr(preg_replace('/[^a-z]/','',$_GET['mode']),0,20);
$startbarcode = '';
@$startbarcode = substr(preg_replace('/[^0-9]/','',$_GET['startbarcode']),0,20);

function imageForBarcode($barcode) {
   global $connection;
   $result = "";
   $sql = " select concat(url_prefix,uri) as url
            from IMAGE_OBJECT io left join REPOSITORY on io.repository_id = REPOSITORY.id 
            left join IMAGE_SET_collectionobject isco on io.image_set_id = isco.imagesetid
            left join fragment f on f.collectionobjectid = isco.collectionobjectid
            where identifier = ? and object_type_id = 4 and hidden_flag = 0 and active_flag = 1
            limit 1 ";
   if ($statement = $connection->prepare($sql)) {
       $statement->bind_param("i", $barcode);
       $statement->execute();
       $statement->bind_result($url);
       if ($statement->fetch()) {
         $result .= $url;
       } else {
          echo $connection->error;
       }
       $statement->close();
   } else {
          echo $connection->error;
   }
   return $result;
}


?>
<!DOCTYPE html>
<html lang="en">
<meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
<html>
<head>
<script src='/jquery/jquery-3.2.1.min.js'></script>
<script src='/jquery/jquery-ui-1.12.1.custom/jquery-ui.min.js'></script>
<link rel='stylesheet' type='text/css' href='/jquery/jquery-ui-1.12.1.custom/jquery-ui.theme.min.css' />
<script>
         $( ':submit' ).button({ classes: { 'ui-button': 'highlight' } });
</script>
<script>

   const channel = new BroadcastChannel('imageclicks');
   const pingchannel = new BroadcastChannel('ping');

   function ping() { 
      pingchannel.postMessage('ping');
   }

</script>
</head>
<body>
<?php

switch ($mode) { 
    case 'image': 
       image($startbarcode);
       break;
    default: 
       error();
} 

function error() { 
    echo "<strong>Window is not initialized properly.</strong>";

} 

function image($barcode) { 
    echo "<div id='info'>imageclicks</div>";
    echo "
        <script>
           channel.onmessage = function(e) {
               console.log(e);
               if (e.data=='close') { 
                  window.close();
               } else {
                  if (e.data.action=='load') { 
                     alert(e.data)
                     setupCanvas();
                  } else {  
                     document.getElementById('info').innerHTML = 'Click on: ' + e.data.x + ':' + e.data.y;
                     doZoom(e.data.x,e.data.y);
                  }
              }
           };

           pingchannel.onmessage = function(e) { 
               console.log(e);
               if (e.data=='ping') { 
                  pingchannel.postMessage('pong');
               }
           } 
        </script>
    ";
   $mediauri = 'http://nrs.harvard.edu/urn-3:FMUS.HUH:s16-47087-301139-3';
   if ($barcode!="") { 
       $mediauri = imageForBarcode($barcode);
echo "[$barcode][$mediauri]";
   }
   echo '<canvas id="viewport" style="border: 1px solid white; width: 1200px; height: 1000px; " ></canvas>';
   echo "<script>
     var canvas = document.getElementById('viewport');
     canvas.width  = 1200;
     canvas.height = 1000;
     context = canvas.getContext('2d');

     setupCanvas('$mediauri');

     function setupCanvas(uri) {
         base_image = new Image();
         base_image.src = uri;
         base_image.onload = function() { 
             context.drawImage(base_image, 1, 1,3420,4897,1,1,800,1200);
         }
     }

     function doZoom(x,y) {
         xnew =  (3420/140) * x;
         ynew =  (4897/200) * y;
         xnew = xnew - 250;  if (xnew < 1) { xnew = 1; } 
         ynew = ynew - 250;  if (ynew < 1) { ynew = 1; } 
         context.clearRect( 0, 0, context.canvas.width, context.canvas.height);
         context.drawImage(base_image,xnew,ynew,1500,1200,1,1,1200,1000);
     }

        function oldDoZoom(x,y) { 
            var image = $('#testimage img');
            var imageWidth = image.width();
            var imageHeight = image.height();

            image.css({
                height: imageHeight * 1.2,
                width: imageWidth * 1.2,
                left: -x,
                top: -y
            });
        }

   </script>";
}   


?>


</body>
</html>
