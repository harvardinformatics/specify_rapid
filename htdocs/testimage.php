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

function targetlist() {
   global $connection;
   $result = "";
   $sql = "select distinct f.text1, f.identifier from locality l left join collectingevent ce on l.localityid = ce.localityid left join collectionobject co on ce.collectingeventid = co.collectingeventid left join fragment f on co.collectionobjectid = f.collectionobjectid left join IMAGE_SET_collectionobject isco on co.collectionobjectid = isco.collectionobjectid  where localityname = '[data not captured]' and isco.collectionobjectid is not null limit ? ";
   $limit = 10;
   if ($statement = $connection->prepare($sql)) {
       $statement->bind_param("i", $limit);
       $statement->execute();
       $statement->bind_result($acronym, $barcode);
       while ($statement->fetch()) {
         $result .= "<a href='transcribe.php?barcode=$barcode'>$acronym $barcode</a>&nbsp; ";
       }
       $statement->close();
   }
   return $result;
}

function target() {
   global $connection;
   $result = "";
   $sql = "select distinct f.text1, f.identifier from locality l left join collectingevent ce on l.localityid = ce.localityid left join collectionobject co on ce.collectingeventid = co.collectingeventid left join fragment f on co.collectionobjectid = f.collectionobjectid left join IMAGE_SET_collectionobject isco on co.collectionobjectid = isco.collectionobjectid  where localityname = '[data not captured]' and isco.collectionobjectid is not null limit ? ";
   $limit = 1;
   if ($statement = $connection->prepare($sql)) {
       $statement->bind_param("i", $limit);
       $statement->execute();
       $statement->bind_result($acronym, $barcode);
       while ($statement->fetch()) {

         $mediauri = imageForBarcode($barcode);
         $mediauri = 'http://nrs.harvard.edu/urn-3:FMUS.HUH:s16-47087-301139-3';
         $height =  4897;
         $width  =  3420;
         $s = $height / 200;
         $h = round($height/$s);
         $w = round($width/$s);
         $result .= "<a onclick='alert(\"$barcode\"); channel.postMessage(\"$barcode\"); '>$acronym $barcode</a>&nbsp; ";
         $result .= "<img id='image_div' onclick=' getClick(event);' src='$mediauri' width='$w' height='$h'></div>";
       }
       $statement->close();
   }
   return $result;
}

function imageForBarcode($barcode) {
   global $connection;
   $result = "";
   $sql = " select concat(url_prefix,uri) 
            from IMAGE_OBJECT io left join REPOSITORY on io.repository_id = REPOSITORY.id 
            left join IMAGE_SET_collectionobject isco on io.image_set_id = isco.imagesetid
            left join fragment f on f.collectionobjectid = isco.collectionobjectid
            where identifier = ? and object_type_id = 4 and hidden_flag = 0 and active_flag = 1
            limit 1 ";
   if ($statement = $connection->prepare($sql)) {
       $statement->bind_param("s", $barcode);
       $statement->execute();
       $statement->bind_result($url);
       if ($statement->fetch()) {
         $result .= $url;
       }
       $statement->close();
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

   function dosetup() { 
   
      window.open("testimage.php?mode=image","_blank","modal=yes");

      window.location.href = "testimage.php?mode=form";

   }
   function doclear() { 
      channel.postMessage("Closed");
      window.location.href = "testimage.php";

   }
</script>
</head>
<body>
<?php

switch ($mode) { 
    case 'form':
       form();
       break;
    case 'image': 
       image();
       break;
    default: 
       setup();
} 

function setup() { 
    echo "<button type='button' onclick='dosetup();'>Start</button>";

} 

function form() { 
    echo "
        <script>
           channel.onmessage = function (e) { console.log(e); }

           function getClick(event){
               xpos = event.offsetX?(event.offsetX):event.pageX-document.getElementById('image_div').offsetLeft;
               ypos = event.offsetY?(event.offsetY):event.pageY-document.getElementById('image_div').offsetTop;
               channel.postMessage( { x:xpos, y:ypos } )
           }

        </script>
    ";
    echo target();
    echo "<button type='button' onclick='doclear();'>Restart</button>";

}

function image() { 
    echo "<div id='info'>imageclicks</div>";
    echo "
        <script>
           channel.onmessage = function(e) {
               console.log(e);
               document.getElementById('info').innerHTML = 'Click on: ' + e.data.x + ':' + e.data.y;
               doZoom(e.data.x,e.data.y);
           };
        </script>
    ";
   $mediauri = 'http://nrs.harvard.edu/urn-3:FMUS.HUH:s16-47087-301139-3';
   //echo '<canvas id="viewport" style="border: 1px solid white; width: 1000px; height: 800px; " ></canvas>';
   echo '<canvas id="viewport" style="border: 1px solid white; width: 1200px; height: 1000px; " ></canvas>';
   //echo '<div id="testimage"><img src="'.$mediauri.'" width="500"></div>';
   echo "<script>
     var canvas = document.getElementById('viewport');
     canvas.width  = 1200;
     canvas.height = 1000;
     context = canvas.getContext('2d');

     setupCanvas();

     function setupCanvas() {
         base_image = new Image();
         base_image.src = '$mediauri';
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
