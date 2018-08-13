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

function imageDataForBarcode($barcode) {
   global $connection;
   $result = new ImageReturn();
   $sql = " select image_set_id, concat(url_prefix,uri) as url, pixel_height, pixel_width 
            from IMAGE_OBJECT io left join REPOSITORY on io.repository_id = REPOSITORY.id 
            left join IMAGE_SET_collectionobject isco on io.image_set_id = isco.imagesetid
            left join fragment f on f.collectionobjectid = isco.collectionobjectid
            where identifier = ? and object_type_id = 4 and hidden_flag = 0 and active_flag = 1
            limit 1 ";
   if ($statement = $connection->prepare($sql)) {
       $statement->bind_param("s", $barcode);
       $statement->execute();
       $statement->bind_result($image_set_id, $url, $pixel_height, $pixel_width);
       if ($statement->fetch()) {
         $result->image_set_id = $image_set_id;
         $result->url = $url;
         if ($pixel_height==0||$pixel_height==null) {
            $size = getImageSize($url);
            $pixel_width = $size[0];
            $pixel_height = $size[1];
         }
         $result->pixel_height = $pixel_height;
         $result->pixel_width = $pixel_width;
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

   $(window).on('load', function(){
       $('#cover').fadeOut(100);
   })
</script>
<style>
#cover {
    background: url('ajaxloader.gif') no-repeat scroll center center #FFF;
    position: absolute;
    height: 100%;
    width: 100%;
}

</style>
</head>
<body>
<div id="cover"></div>
<?php

switch ($mode) { 
    case 'image': 
       $startbarcode = '';
       @$startbarcode = substr(preg_replace('/[^0-9]/','',$_GET['startbarcode']),0,20);
       image($startbarcode);
       break;
    case 'imagefile': 
       $path= '';
       @$path = urldecode($_GET['path']);
       $filename= '';
       @$filename = urldecode($_GET['filename']);
       imagefile($path,$filename);
       break;
    default: 
       error();
} 

function error() { 
    echo "<strong>Window is not initialized properly.</strong>";

} 

function image($barcode) { 
    echo "$barcode <div id='info'>imageclicks</div>";
    // channel.postMessage( { x:xpos, y:ypos, h:height, w:width, oh:origheight, ow:origwidth, id:imagesetid } )
    echo "
        <script>
           channel.onmessage = function(e) {
               console.log(e);
               if (e.data=='close') { 
                  window.close();
               } else {
                  if (e.data.action=='load') { 
                     setupCanvas(e.data.uri,e.data.origheight,ed.data.origwidth);
                  } else {  
                     document.getElementById('info').innerHTML = 'Click on: ' + e.data.x + ':' + e.data.y;
                     doZoom(e.data.x,e.data.y,e.data.h,e.data.w,e.data.oh,e.data.ow);
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
       $media = imageDataForBarcode($barcode);
       $mediauri = $media->url;
       $mediaid = $media->image_set_id;
       $h = $media->pixel_height;
       $w = $media->pixel_width;
echo "[$barcode][$mediauri][$h]";
   }
   echo '<canvas id="viewport" style="border: 1px solid white; width: 1200px; height: 1000px; " ></canvas>';
   echo "<script>
     var canvas = document.getElementById('viewport');
     canvas.width  = 1200;
     canvas.height = 1000;
     context = canvas.getContext('2d');

     setupCanvas('$mediauri',$h,$w);

     function setupCanvas(uri,h,w) {
         base_image = new Image();
         base_image.src = uri;
         base_image.onload = function() { 
             context.drawImage(base_image, 1, 1,w,h,1,1,800,1200);
         }
     }

     function doZoom(x,y,h,w,oh,ow) {
         xnew =  (ow/w) * x;
         ynew =  (oh/h) * y;
         xnew = xnew - 600;  if (xnew < 1) { xnew = 1; } 
         ynew = ynew - 400;  if (ynew < 1) { ynew = 1; } 
         context.clearRect( 0, 0, context.canvas.width, context.canvas.height);
         context.drawImage(base_image,xnew,ynew,1500,1200,1,1,1200,1000);
     }

   </script>";
}   

/** Given a path and a filename, load an image and echo html and javascript to display the image with zoom on click via channel.
 *  
 *  @param path the path relative to BASE_IMAGE_PATH and BASE_IMAGE_URI
 *  @param filename the filename below the path.
 */
function imagefile($path,$filename) { 
    echo "$filename <div id='info'>imageclicks</div>";
    // channel.postMessage( { x:xpos, y:ypos, h:height, w:width, oh:origheight, ow:origwidth, id:imagesetid } )
    echo "
        <script>
           channel.onmessage = function(e) {
               console.log(e);
               if (e.data=='close') { 
                  window.close();
               } else {
                  if (e.data.action=='load') { 
                     setupCanvas(e.data.uri,e.data.origheight,ed.data.origwidth);
                  } else {  
                     document.getElementById('info').innerHTML = 'Click on: ' + e.data.x + ':' + e.data.y;
                     doZoom(e.data.x,e.data.y,e.data.h,e.data.w,e.data.oh,e.data.ow);
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
   $mediauri = BASE_IMAGE_URI.$path."/".$filename;
   
   if ($filename!="") { 
       // TODO: Lookup values from IMAGE_LOCAL_FILE
       //$media = imageDataForBarcode($barcode);
       //$mediauri = $media->url;
       //$mediaid = $media->image_set_id;
       //$h = $media->pixel_height;
       //$w = $media->pixel_width;
       $h = 5616;
       $w = 3744;
echo @"[$barcode][$mediauri][$h]";
   }
   echo '<canvas id="viewport" style="border: 1px solid white; width: 1200px; height: 1000px; " ></canvas>';
   echo "<script>
     var canvas = document.getElementById('viewport');
     canvas.width  = 1200;
     canvas.height = 1000;
     context = canvas.getContext('2d');

     setupCanvas('$mediauri',$h,$w);

     function setupCanvas(uri,h,w) {
         base_image = new Image();
         base_image.src = uri;
         base_image.onload = function() { 
             context.drawImage(base_image, 1, 1,w,h,1,1,800,1200);
         }
     }

     function doZoom(x,y,h,w,oh,ow) {
         xnew =  (ow/w) * x;
         ynew =  (oh/h) * y;
         xnew = xnew - 600;  if (xnew < 1) { xnew = 1; } 
         ynew = ynew - 400;  if (ynew < 1) { ynew = 1; } 
         context.clearRect( 0, 0, context.canvas.width, context.canvas.height);
         context.drawImage(base_image,xnew,ynew,1500,1200,1,1,1200,1000);
     }

   </script>";
}   

?>


</body>
</html>