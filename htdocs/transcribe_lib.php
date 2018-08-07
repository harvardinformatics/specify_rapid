<?php

class TPage extends Page { 

   function __construct() { 
      parent::__construct();
      $this->targetPage = "transcribe.php";
   }

   public function getHeader($user) {
      $error = $this->errormessage;
      $returnvalue = "<!DOCTYPE html>\n";
      $returnvalue .= "<html>\n";
      $returnvalue .= "<head>\n";
      $returnvalue .= "<meta charset='utf-8'/>\n";
      $returnvalue .= "<title>".$this->title."</title>\n";
      $returnvalue .= $this->getJQueryPageHead();
      $returnvalue .= $this->getHeadStyle();
      $returnvalue .= "</head>\n";
      $returnvalue .= "<body>\n";
      $returnvalue .= '<header class="hfbox">';
      if ($error!="") {
      	$returnvalue .= "<h2>$error</h2>";
      }
      if ($user!=null) { 
         if ($user->getAuthenticationState()==true) {
      	     $returnvalue .= $user->getUserHtml($this->targetPage);
             $returnvalue .= "
<script>

   const channel = new BroadcastChannel('imageclicks');
   const pingchannel = new BroadcastChannel('ping');

   function dosetup(barcode) { 
   
      window.open('displayimage.php?mode=image&startbarcode='+barcode ,'_blank','modal=yes');

      window.location.href = 'transcribe.php?display=mainform&barcode='+barcode;

   }
   function doclear() { 
      channel.postMessage('close');
      window.location.href = 'transcribe.php?display=setup';

   }
   function ping() { 
      pingchannel.postMessage('ping');
   }

   function goNext() { 
      alert(\"TODO: Go next, load next image.\");
   }

   pingchannel.onmessage = function(e) { 
      console.log(e);
      if (e.data=='ping') { 
         pingchannel.postMessage('pong');
      }
      if (e.data=='pong') { 
         alert('pong');
      }
   } 

</script>
              ";
         }
      }
      $returnvalue .= '</header>';
      $returnvalue .= '<div class="hfbox" style="padding: 0em;" >';
      return $returnvalue;
   }

   public function getFooter() {
   global $targethostdb;
   $returnvalue = '
	</div><!-- flex-main -->
        <div class="hfbox"  id="feedback">Status</div>
	<footer class="hfbox">Database: ' . $targethostdb . '</footer>
	';
   $returnvalue .= "</body>\n";
   $returnvalue .= "</html>";
   return $returnvalue;
   }

   public function getJQueryPageHead() { 
     $returnvalue = "
       <script src='/jquery/jquery-3.2.1.min.js'></script>
       <script src='/jquery/zoom-master/jquery.zoom.js'></script>
       <script src='/jquery/jquery-ui-1.12.1.custom/jquery-ui.min.js'></script>
       <link rel='stylesheet' type='text/css' href='/jquery/jquery-ui-1.12.1.custom/jquery-ui.theme.min.css' />
       <script>
         $( ':submit' ).button({ classes: { 'ui-button': 'highlight' } });
       </script>
     ";
     return $returnvalue;
   }
   public function getHeadStyle() { 
     $returnvalue = "
       <style>
html, body { 
   height: 100%;
}
body { 
   display: flex;
   flex-direction: column;
}
.flex-main { 
    display: flex; 
    flex-direction: row;
    align-items: stretch;
    flex-grow: 10;
}
.hfbox  {
    padding: 0.5em;
    border: 1px solid grey;
    border-radius: 3px;
}
.flexbox  {
    padding: 0.5em;
}
       </style>
     ";
     return $returnvalue;
   }

}

?>
