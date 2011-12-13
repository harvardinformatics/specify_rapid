<?php
//==============================================================================
//===   REPOSITORY.php                         
//===   Autogenerated by Druid from MySQL db Build:6
//==============================================================================

include_once("druid_interfaces.php");

class huh_REPOSITORY implements  model , loadableModel, saveableModel, tableSchema 
{
   // These constants hold the sizes the fields in this table in the database.
   const ID_SIZE              = 20; //BIGINT
   const NAME_SIZE            = 64; //64
   const URL_PREFIX_SIZE      = 120; //120
   const DESCRIPTION_SIZE     = 300; //300
    // These constants hold the field names of the table in the database. 
   const ID                = 'ID';
   const NAME              = 'NAME';
   const URL_PREFIX        = 'URL_PREFIX';
   const DESCRIPTION       = 'DESCRIPTION';

   //---------------------------------------------------------------------------

   // interface tableSchema implementation
   // schemaPK returns array of primary key field names
   public function schemaPK() {
       return $this->primaryKeyArray;
   } 
   // schemaHaveDistinct returns array of field names for which selectDistinct{fieldname} methods are available.
   public function schemaHaveDistinct() {
       return $this->selectDistinctFieldsArray;
   } 
   // schemaFields returns array of all field names
   public function schemaFields() { 
       return $this->allFieldsArray;
   } 
/*  Example sanitized retrieval of variable matching object variables from $_GET 
/*  Customize these to limit each variable to narrowest possible set of known good values. 

  $ID = substr(preg_replace('/[^A-Za-z0-9\.\.\ \[NULL\]]/','',$_GET['ID']), 0, 20);
  $NAME = substr(preg_replace('/[^A-Za-z0-9\.\.\ \[NULL\]]/','',$_GET['NAME']), 0, 64);
  $URL_PREFIX = substr(preg_replace('/[^A-Za-z0-9\.\.\ \[NULL\]]/','',$_GET['URL_PREFIX']), 0, 120);
  $DESCRIPTION = substr(preg_replace('/[^A-Za-z0-9\.\.\ \[NULL\]]/','',$_GET['DESCRIPTION']), 0, 300);
*/

   //---------------------------------------------------------------------------

   private $ID; // PK BIGINT 
   private $NAME; // VARCHAR(64) 
   private $URL_PREFIX; // VARCHAR(120) 
   private $DESCRIPTION; // VARCHAR(300) 
   private $dirty;
   private $loaded;
   private $error;
   const FIELDLIST = ' ID, NAME, URL_PREFIX, DESCRIPTION, ';
   const PKFIELDLIST = ' ID, ';
   const NUMBER_OF_PRIMARY_KEYS = 1;
   private $primaryKeyArray = array( 1 => 'ID'  ) ;
   private $allFieldsArray = array( 0 => 'ID' , 1 => 'NAME' , 2 => 'URL_PREFIX' , 3 => 'DESCRIPTION'  ) ;
   private $selectDistinctFieldsArray = array(  ) ;

   //---------------------------------------------------------------------------

   // constructor 
   function huh_REPOSITORY(){
       $this->ID = NULL;
       $this->NAME = '';
       $this->URL_PREFIX = '';
       $this->DESCRIPTION = '';
       $this->dirty = false;
       $this->loaded = false;
       $this->error = '';
   }

   private function l_addslashes($value) {
      $retval = $value;
      if (!get_magic_quotes_gpc()) {
          $retval = addslashes($value);
      }
      return $retval;
   }
   private function l_stripslashes($value) {
      $retval = $value;
      if (!get_magic_quotes_gpc()) {
          $retval = stripslashes($value);
      }
      return $retval;
   }
   public function isDirty() {
       return $this->dirty;
   }
   public function isLoaded() {
       return $this->loaded;
   }
   public function errorMessage() {
       return $this->error;
   }

   //---------------------------------------------------------------------------

   public function keyValueSet($fieldname,$value) {
       $returnvalue = false;
       if ($this->hasField($fieldname)) { 
          try {
             if ($fieldname=='ID') { $returnvalue = $this->setID($value); } 
             if ($fieldname=='NAME') { $returnvalue = $this->setNAME($value); } 
             if ($fieldname=='URL_PREFIX') { $returnvalue = $this->setURL_PREFIX($value); } 
             if ($fieldname=='DESCRIPTION') { $returnvalue = $this->setDESCRIPTION($value); } 
             $returnvalue = true;
          }
          catch (exception $e) { ;
              $returnvalue = false;
              throw new Exception('Field Set Error'.$e->getMessage()); 
          }
       } else { 
          throw new Exception('No Such field'); 
       }  
       return $returnvalue;
   }
   public function keyGet($fieldname) {
       $returnvalue = null;
       if ($this->hasField($fieldname)) { 
          try {
             if ($fieldname=='ID') { $returnvalue = $this->getID(); } 
             if ($fieldname=='NAME') { $returnvalue = $this->getNAME(); } 
             if ($fieldname=='URL_PREFIX') { $returnvalue = $this->getURL_PREFIX(); } 
             if ($fieldname=='DESCRIPTION') { $returnvalue = $this->getDESCRIPTION(); } 
          }
          catch (exception $e) { ;
              $returnvalue = null;
          }
       }
       return $returnvalue;
   }
/*ID*/
   public function getID() {
       if ($this->ID==null) { 
          return null;
       } else { ;
          return trim($this->l_stripslashes($this->ID));
       }
   }
   public function setID($ID) {
       if (strlen($ID) > huh_REPOSITORY::ID_SIZE) { 
           throw new Exception('Value exceeds field length.');
       } 
       $this->ID = $this->l_addslashes($ID);
       $this->dirty = true;
   }
/*NAME*/
   public function getNAME() {
       if ($this->NAME==null) { 
          return null;
       } else { ;
          return trim($this->l_stripslashes($this->NAME));
       }
   }
   public function setNAME($NAME) {
       if (strlen($NAME) > huh_REPOSITORY::NAME_SIZE) { 
           throw new Exception('Value exceeds field length.');
       } 
       $this->NAME = $this->l_addslashes($NAME);
       $this->dirty = true;
   }
/*URL_PREFIX*/
   public function getURL_PREFIX() {
       if ($this->URL_PREFIX==null) { 
          return null;
       } else { ;
          return trim($this->l_stripslashes($this->URL_PREFIX));
       }
   }
   public function setURL_PREFIX($URL_PREFIX) {
       if (strlen($URL_PREFIX) > huh_REPOSITORY::URL_PREFIX_SIZE) { 
           throw new Exception('Value exceeds field length.');
       } 
       $this->URL_PREFIX = $this->l_addslashes($URL_PREFIX);
       $this->dirty = true;
   }
/*DESCRIPTION*/
   public function getDESCRIPTION() {
       if ($this->DESCRIPTION==null) { 
          return null;
       } else { ;
          return trim($this->l_stripslashes($this->DESCRIPTION));
       }
   }
   public function setDESCRIPTION($DESCRIPTION) {
       if (strlen($DESCRIPTION) > huh_REPOSITORY::DESCRIPTION_SIZE) { 
           throw new Exception('Value exceeds field length.');
       } 
       $this->DESCRIPTION = $this->l_addslashes($DESCRIPTION);
       $this->dirty = true;
   }
   public function PK() { // get value of primary key 
        $returnvalue = '';
        $returnvalue .= $this->getID();
        return $returnvalue;
   }
   public function PKArray() { // get name and value of primary key fields 
        $returnvalue = array();
        $returnvalue['ID'] = $this->getID();
        return $returnvalue;
   }
   public function NumberOfPrimaryKeyFields() { // returns the number of primary key fields defined for this table 
        return 1;
   }

   // Constants holding the mysqli field type character (s,i,d) for each field
  const C_IDMYSQLI_TYPE = 'i';
  const C_NAMEMYSQLI_TYPE = 's';
  const C_URL_PREFIXMYSQLI_TYPE = 's';
  const C_DESCRIPTIONMYSQLI_TYPE = 's';

   // function to obtain the mysqli field type character from a fieldname
   public function MySQLiFieldType($aFieldname) { 
      $retval = '';
      if ($aFieldname=='ID') { $retval = self::C_IDMYSQLI_TYPE; }
      if ($aFieldname=='NAME') { $retval = self::C_NAMEMYSQLI_TYPE; }
      if ($aFieldname=='URL_PREFIX') { $retval = self::C_URL_PREFIXMYSQLI_TYPE; }
      if ($aFieldname=='DESCRIPTION') { $retval = self::C_DESCRIPTIONMYSQLI_TYPE; }
      return $retval;
   }

   // Function load() can take either the value of the primary key which uniquely identifies a particular row
   // or an array of array('primarykeyfieldname'=>'value') in the case of a single field primary key
   // or an array of fieldname value pairs in the case of multiple field primary key.
   public function load($pk) {
        // ******* Note: $connection must be a mysqli object.
        global $connection;
        $returnvalue = false;
        try {
             if (is_array($pk)) { 
                 $this->setID($pk[ID]);
             } else { ;
                 $this->setID($pk);
             };
        } 
        catch (Exception $e) { 
             throw new Exception($e->getMessage());
        }
        if($this->ID != NULL) {
           $sql = 'SELECT ID, NAME, URL_PREFIX, DESCRIPTION FROM REPOSITORY WHERE ID = '.$this->ID ;

           $preparesql = 'SELECT ID, NAME, URL_PREFIX, DESCRIPTION FROM REPOSITORY WHERE ID = ? ';

           if ($statement = $connection->prepare($preparesql)) { 
              $statement->bind_param("i", $this->ID);
              $statement->execute();
              $statement->bind_result($this->ID, $this->NAME, $this->URL_PREFIX, $this->DESCRIPTION);
              $statement->fetch();
              $statement->close();
           }

            $this->loaded = true;
            $this->dirty = false;
        } else { 
        }
        return $returnvalue;
    }
   //---------------------------------------------------------------------------

   // Function save() will either save the current record or insert a new record.
   // Inserts new record if the primary key field in this table is null 
   // for this instance of this object.
   // Otherwise updates the record identified by the primary key value.
   public function save() {
        // ******* Note: $connection must be a mysqli object.
        global $connection;
        $returnvalue = false;
        // Test to see if this is an insert or update.
        if ($this->ID!= NULL) {
            $sql  = 'UPDATE  REPOSITORY SET ';
            $isInsert = false;
            $sql .=  "NAME = ? ";
            $sql .=  ", URL_PREFIX = ? ";
            $sql .=  ", DESCRIPTION = ? ";

            $sql .= "  WHERE ID = ? ";
        } else {
            $sql  = 'INSERT INTO REPOSITORY ';
            $isInsert = true;
if ($this->PK==NULL) { throw new Exception('Can\'t insert record with null primary key for this table'); }
            $sql .= '( ID ,  NAME ,  URL_PREFIX ,  DESCRIPTION ) VALUES (';
            $sql .=  "  ? ";
            $sql .=  " ,  ? ";
            $sql .=  " ,  ? ";
            $sql .=  " ,  ? ";
            $sql .= ')';

        }
        if ($statement = $connection->prepare($sql)) { 
           if ($this->ID!= NULL ) {
              $statement->bind_param("isssi", $this->ID , $this->NAME , $this->URL_PREFIX , $this->DESCRIPTION , $this->ID );
           } else { 
              $statement->bind_param("isss", $this->ID , $this->NAME , $this->URL_PREFIX , $this->DESCRIPTION );
           } 
           $statement->execute();
           if ($statement->num_rows()!=1) {
               $this->error = $statement->error; 
           }
           $statement->close();
        } else { 
            $this->error = mysqli_error($connection); 
        }
        if ($this->error=='') { 
            $returnvalue = true;
        };

        $this->loaded = true;
        return $returnvalue;
    }
   //---------------------------------------------------------------------------

   public function delete() {
        // ******* Note: $connection must be a mysqli object.
        global $connection;
        $returnvalue = false;
        if($this->ID != NULL) {
           $sql = 'SELECT ID, NAME, URL_PREFIX, DESCRIPTION FROM REPOSITORY WHERE ID = "'.$this->ID.'"  ' ;

           $preparedsql = 'SELECT  FROM REPOSITORY WHERE  and ID = ?  ' ;
        if ($statement = $connection->prepare($preparedsql)) { 
           $statement->bind_param("isss", $this->ID, $this->NAME, $this->URL_PREFIX, $this->DESCRIPTION);
           $statement->execute();
           $statement->store_result();
           if ($statement->num_rows()==1) {
                $sql = 'DELETE FROM REPOSITORY WHERE  and ID = ?  ';
                if ($stmt_delete = $connection->prepare($sql)) { 
                   $stmt_delete->bind_param("isss", $this->ID, $this->NAME, $this->URL_PREFIX, $this->DESCRIPTION);
                   if ($stmnt_delete->execute()) { 
                       $returnvalue = true;
                   } else {
                       $this->error = mysqli_error($connection); 
                   }
                   $stmt_delete->close();
                }
           } else { 
               $this->error = mysqli_error($connection); 
           }
           $tatement->close();
        } else { 
            $this->error = mysqli_error($connection); 
        }

            $this->loaded = true;
            // record was deleted, so set PK to null
            $this->ID = NULL; 
        } else { 
           throw new Exception('Unable to identify which record to delete, primary key is not set');
        }
        return $returnvalue;
    }
   //---------------------------------------------------------------------------

   public function count() {
        // ******* Note: $connection must be a mysqli object.
        global $connection;
        $returnvalue = false;
        $sql = 'SELECT count(*)  FROM REPOSITORY';
        if ($result = $connection->query($sql)) { 
           if ($result->num_rows()==1) {
             $row = $result->fetch_row();
             if ($row) {
                $returnvalue = $row[0];
             }
           }
        } else { 
           $this->error = mysqli_error($connection); 
        }
        mysqli_free_result($result);

        $this->loaded = true;
        return $returnvalue;
    }
   //---------------------------------------------------------------------------

   public function loadArrayKeyValueSearch($searchTermArray) {
       // ******* Note: $connection must be a mysqli object.
       global $connection;
       $returnvalue = array();
       $and = '';
       $wherebit = 'WHERE ';
       foreach($searchTermArray as $fieldname => $searchTerm) {
           if ($this->hasField($fieldname)) { 
               $operator = '='; 
               // change to a like search if a wildcard character is present
               if (!(strpos($searchTerm,'%')===false)) { $operator = 'like'; }
               if (!(strpos($searchTerm,'_')===false)) { $operator = 'like'; }
               if ($searchTerm=='[NULL]') { 
                   $wherebit .= "$and ($fieldname is null or $fieldname='') "; 
               } else { 
                   $wherebit .= "$and $fieldname $operator ? ";
                   $types = $types . $this->MySQLiFieldType($fieldname);
               } 
               $and = ' and ';
           }
       }
       $sql = "SELECT ID FROM REPOSITORY $wherebit";
       if ($wherebit=='') { 
             $this->error = 'Error: No search terms provided';
       } else {
          $statement = $connection->prepare($sql);
          $vars = Array();
          $vars[] = $types;
          $i = 0;
          foreach ($searchTermArray as $value) { 
               $varname = 'bind'.$i;  // create a variable name
               $$varname = $value;    // using that variable name store the value 
               $vars[] = &$$varname;  // add a reference to the variable to the array
               $i++;
           }
           //$vars[] contains $types followed by references to variables holding each value in $searchTermArray.
          call_user_func_array(array($statement,'bind_param'),$vars);
          //$statement->bind_param($types,$names);
          $statement->execute();
          $statement->bind_result($id);
          $ids = array();
          while ($statement->fetch()) {
              $ids[] = $id;
          } // double loop to allow all data to be retrieved before preparing a new statement. 
          $statement->close();
          for ($i=0;$i<count($ids);$i++) {
              $obj = new huh_REPOSITORY();
              $obj->load($ids[$i]);
              $returnvalue[] = $obj;
              $result=true;
          }
          if ($result===false) { $this->error = mysqli_error($connection); }
       }
       return $returnvalue;
   }	

   //---------------------------------------------------------------------------

// TODO: *************** link to related tables 

   //---------------------------------------------------------------------------

   // Returns an array of primary key values (id) and concatenated values of all other fields (fields)
   // wrapped as $values in: '{ "identifier":"id", "items": [ '.$values.' ] }';
   // when druid_handler.php is called with druid_action=returnfkjson
   // Used with dojo dijit.form.FilteringSelect to submit surrogate numeric key values from a text picklist 
   public function keySelectAllConcatJSON($orderby='ASC') {
        // ******* Note: $connection must be a mysqli object.
        global $connection;
       $returnvalue = '';
       $order = '';
       if ($orderby=='ASC') { $order = 'ASC'; } else { $order = 'DESC'; } 
       $sql = "SELECT ID, concat(IFNULL(NAME,'') || ' ' || IFNULL(URL_PREFIX,'') || ' ' || IFNULL(DESCRIPTION,'')) FROM REPOSITORY order by ID $order ";
       $comma = '';
       if ($result = $connection->query($sql)) { 
          while ($row = $result->fetch_row()) {
             if ($row) {
                $pkval = trim($row[0]);
                $fval = trim($row[1]);
                if ($pkval!='') { 
                    $pkval = str_replace('"','&quot;',$pkval);
                    $fval = str_replace('"','&quot;',$fval);
                    $returnvalue .= $comma . ' { "id":"'.$pkval.'", "fields": "'.$fval.'" } ';
                    $comma = ', ';
                }
             }
          }
       } else { 
          $this->error = mysqli_error(); 
       }
       $result->close();
       return $returnvalue;
   }
   //---------------------------------------------------------------------------

   public function keySelectDistinctJSON($field,$orderby='ASC') {
       // ******* Note: $connection must be a mysqli object.
       global $connection;
       $returnvalue = '';
       if ($this->hasField($field)) { 
          $order = '';
          $fieldesc = mysql_escape_string($field);
          if ($orderby=='ASC') { $order = 'ASC'; } else { $order = 'DESC'; } 
          $preparemysql = "SELECT DISTINCT $fieldesc FROM REPOSITORY order by $fieldesc $order ";
          $comma = '';
         if ($stmt = $connection->prepare($preparemysql)) { 
             $stmt->execute();
             $stmt->bind_result($val);
             while ($stmt->fetch()) {
                $val = trim($val);                if ($val!='') { 
                    $val = str_replace('"','&quot;',$val);
                    $returnvalue .= $comma . ' { "'.$field.'":"'.$val.'" } ';
                    $comma = ', ';
                 }
             }
             $stmt->close();
         }
       }
       return $returnvalue;
   }
   //---------------------------------------------------------------------------


   //---------------------------------------------------------------------------

   // Each field with an index has a load array method generated for it.

   //---------------------------------------------------------------------------

   // Each fulltext index has a load array method generated for it.

   //---------------------------------------------------------------------------

   // Each field with an index has a select distinct method generated for it.

   public function keySelectDistinct($fieldname,$startline,$link,$endline,$includecount=false,$orderbycount=false) {
       $returnvalue = '';
       switch ($fieldname) { 
       }
       return $returnvalue;
    }

   //---------------------------------------------------------------------------

   public function hasField($fieldname) {
       $returnvalue = false;
       if (trim($fieldname)!='' && trim($fieldname)!=',') {
            if (strpos(self::FIELDLIST," $fieldname, ")!==false) { 
               $returnvalue = true;
            }
       }
       return $returnvalue;
    }
   //---------------------------------------------------------------------------

}


// Write your own views by extending this class.
// Place your extended views in a separate file and you
// can use Druid to regenerate the REPOSITORY.php file to reflect changes
// in the underlying database without overwriting your custom views of the data.
// 
class huh_REPOSITORYView implements viewer
{
   var $model = null;
   public function setModel($aModel) { 
       $this->model = $aModel;
   }
   // @param $includeRelated default true shows rows from other tables through foreign key relationships.
   // @param $editLinkURL default '' allows adding a link to show this record in an editing form.
   public function getDetailsView($includeRelated=true, $editLinkURL='') {
       $returnvalue = '<ul>';
       $editLinkURL=trim($editLinkURL);
       $model = $this->model;
       $primarykeys = $model->schemaPK();
       if ($editLinkURL!='') { 
          if (!preg_match('/\&$/',$editLinkURL)) { $editLinkURL .= '&'; } 
          $nullpk = false; 
          foreach ($primarykeys as $primarykey) { 
              // Add fieldname=value pairs for primary key(s) to editLinkURL. 
              $editLinkURL .= urlencode($primarykey) . '=' . urlencode($model->keyGet($primarykey));
              if ($model->keyGet($primarykey)=='') { $nullpk = true; } 
          }
          if (!$nullpk) { $returnvalue .= "<li>huh_REPOSITORY <a href='$editLinkURL'>Edit</a></li>\n";  } 
       }
       $returnvalue .= "<li>".huh_REPOSITORY::ID.": ".$model->getID()."</li>\n";
       $returnvalue .= "<li>".huh_REPOSITORY::NAME.": ".$model->getNAME()."</li>\n";
       $returnvalue .= "<li>".huh_REPOSITORY::URL_PREFIX.": ".$model->getURL_PREFIX()."</li>\n";
       $returnvalue .= "<li>".huh_REPOSITORY::DESCRIPTION.": ".$model->getDESCRIPTION()."</li>\n";
       if ($includeRelated) { 
           // note that $includeRelated is provided as false in calls out to
           // related tables to prevent infinite loops between related objects.

        }
       $returnvalue .= '</ul>';
       return  $returnvalue;
   }
   public function getJSON() {
       $returnvalue = '{ ';
       $model = $this->model;
       $returnvalue .= '"'.huh_REPOSITORY::ID.': "'.$model->getID().'",';
       $returnvalue .= '"'.huh_REPOSITORY::NAME.': "'.$model->getNAME().'",';
       $returnvalue .= '"'.huh_REPOSITORY::URL_PREFIX.': "'.$model->getURL_PREFIX().'",';
       $returnvalue .= '"'.huh_REPOSITORY::DESCRIPTION.': "'.$model->getDESCRIPTION().'" }';
       $returnvalue .= '</ul>';
       return  $returnvalue;
   }
   public function getTableRowView() {
       $returnvalue = '<tr>';
       $model = $this->model;
       $returnvalue .= "<td>".$model->getID()."</td>\n";
       $returnvalue .= "<td>".$model->getNAME()."</td>\n";
       $returnvalue .= "<td>".$model->getURL_PREFIX()."</td>\n";
       $returnvalue .= "<td>".$model->getDESCRIPTION()."</td>\n";
       $returnvalue .= '</tr>';
       return  $returnvalue;
   }
   public function getHeaderRow() {
       $returnvalue = '<tr>';
       $returnvalue .= "<th>".huh_REPOSITORY::ID."</th>\n";
       $returnvalue .= "<th>".huh_REPOSITORY::NAME."</th>\n";
       $returnvalue .= "<th>".huh_REPOSITORY::URL_PREFIX."</th>\n";
       $returnvalue .= "<th>".huh_REPOSITORY::DESCRIPTION."</th>\n";
       $returnvalue .= '</tr>';
       return  $returnvalue;
   }
   public function getEditFormDojoView($includeRelated=true) {
       $model = $this->model;
       if ($model->PK()=='') { $addform=true; } else { $addform=false; } 
       $returnvalue = '';
       $id = trim($model->PK());
       $feedback = "status$id";
       $formname = "editform$id";
       $script = '
  var saveprocessor'.$id.' = {
        form: dojo.byId("'.$formname.'"),
        url: "ajax_handler.php",
        handleAs: "text",
        load: function(data){
                dojo.byId("'.$feedback.'").innerHTML = data;
        },
        error: function(data){
                dojo.byId("'.$feedback.'").innerHTML = "Error: " + data;
                console.debug("Error: ", data);
        },
        timeout: 8000
  };
  function save'.$id.'() {
     dojo.byId("'.$feedback.'").innerHTML = "Saving...";
     dojo.xhrGet(saveprocessor'.$id.');
  };
       ';
       $returnvalue .= "<form id='$formname' name='$formname' dojoType='dijit.form.Form'>";
       $returnvalue .= '<input type=hidden name=druid_action id=druid_action value=save>';
       $returnvalue .= '<input type=hidden name=druid_table id=druid_action value="huh_REPOSITORY">';
       $returnvalue .= "<div id='div_$formnane' >";
       if($addform) { 
          $returnvalue .= "Add a new ID"; 
       } else { 
         $returnvalue .= "<div><label for=".huh_REPOSITORY::ID.">ID</label><input  dojoType='dijit.form.ValidationTextBox'  name=".huh_REPOSITORY::ID." id=".huh_REPOSITORY::ID." value='".$model->getID()."'  style=' width:".huh_REPOSITORY::ID_SIZE ."em;  '  maxlength='".huh_REPOSITORY::ID_SIZE ."' ></div>\n";
       }  
       $returnvalue .= "<div><label for=".huh_REPOSITORY::NAME.">NAME</label><textarea  dojoType='dijit.form.Textarea'  style=' width:51em; border:1px solid grey; '  name=".huh_REPOSITORY::NAME." id=".huh_REPOSITORY::NAME." >".$model->getNAME()."</textarea></div>\n";
       $returnvalue .= "<div><label for=".huh_REPOSITORY::URL_PREFIX.">URL_PREFIX</label><textarea  dojoType='dijit.form.Textarea'  style=' width:51em; border:1px solid grey; '  name=".huh_REPOSITORY::URL_PREFIX." id=".huh_REPOSITORY::URL_PREFIX." >".$model->getURL_PREFIX()."</textarea></div>\n";
       $returnvalue .= "<div><label for=".huh_REPOSITORY::DESCRIPTION.">DESCRIPTION</label><textarea  dojoType='dijit.form.Textarea'  style=' width:51em; border:1px solid grey; '  name=".huh_REPOSITORY::DESCRIPTION." id=".huh_REPOSITORY::DESCRIPTION." >".$model->getDESCRIPTION()."</textarea></div>\n";
       if ($includeRelated) { 
           // note that $includeRelated is provided as false in calls out to
           // related tables to prevent infinite loops between related objects.
        }
       $returnvalue .= "<li><input name=save id=save type=button value='Save' onclick='$script  save$id();'><div id='$feedback'></div></li>";
       $returnvalue .= '</div>';
       $returnvalue .= '</form>';
       if(!$addform) { 
          // show delete button if editing an existing record 
       $id = trim($model->PK());
       $feedback = "deletestatus$id";
       $formname = "deleteform$id";
       $script = '
  var deleteprocessor'.$id.' = {
        form: dojo.byId("'.$formname.'"),
        url: "ajax_handler.php",
        handleAs: "text",
        load: function(data){
                dojo.byId("'.$feedback.'").innerHTML = data;
        },
        error: function(data){
                dojo.byId("'.$feedback.'").innerHTML = "Error: " + data;
                console.debug("Error: ", data);
        },
        timeout: 8000
  };
  function deleterecord'.$id.'() {
     dojo.byId("'.$feedback.'").innerHTML = "Deleting...";
     dojo.xhrGet(deleteprocessor'.$id.');
  };
       ';
       $returnvalue .= "<form id='$formname' name='$formname' dojoType='dijit.form.Form'>";
       $returnvalue .= '<input type=hidden name=druid_action id=druid_action value=delete>';
       $returnvalue .= '<input type=hidden name=druid_table id=druid_table value="huh_REPOSITORY">';
       $returnvalue .= '<input type=hidden name=ID id=druid_table value="'.$id.'">';
       $returnvalue .= '<ul>';
       $returnvalue .= "<li><input name=delete id=save type=button value='Delete' onclick='$script  deleterecord$id();'><div id='$feedback'></div></li>";
       $returnvalue .= '</ul>';
       $returnvalue .= '</form>';
       } 
       return  $returnvalue;
   }
   public function getEditFormView($includeRelated=true) {
       $model = $this->model;
       $returnvalue = '<form method=get action=druid_handler.php>';
       $returnvalue .= '<input type=hidden name=druid_action id=druid_action value=save>';
       $returnvalue .= '<input type=hidden name=druid_table id=druid_action value="huh_REPOSITORY">';
       $returnvalue .= '<ul>';
       $returnvalue .= "<li>ID<input type=text name=".huh_REPOSITORY::ID." id=".huh_REPOSITORY::ID." value='".$model->getID()."'  size='".huh_REPOSITORY::ID_SIZE ."'  maxlength='".huh_REPOSITORY::ID_SIZE ."' ></li>\n";
       $returnvalue .= "<li>NAME<input type=text name=".huh_REPOSITORY::NAME." id=".huh_REPOSITORY::NAME." value='".$model->getNAME()."'  size='51'  maxlength='".huh_REPOSITORY::NAME_SIZE ."' ></li>\n";
       $returnvalue .= "<li>URL_PREFIX<input type=text name=".huh_REPOSITORY::URL_PREFIX." id=".huh_REPOSITORY::URL_PREFIX." value='".$model->getURL_PREFIX()."'  size='51'  maxlength='".huh_REPOSITORY::URL_PREFIX_SIZE ."' ></li>\n";
       $returnvalue .= "<li>DESCRIPTION<input type=text name=".huh_REPOSITORY::DESCRIPTION." id=".huh_REPOSITORY::DESCRIPTION." value='".$model->getDESCRIPTION()."'  size='51'  maxlength='".huh_REPOSITORY::DESCRIPTION_SIZE ."' ></li>\n";
       if ($includeRelated) { 
           // note that $includeRelated is provided as false in calls out to
           // related tables to prevent infinite loops between related objects.
        }
       $returnvalue .= '<li><input type=submit value="Save"></li>';
       $returnvalue .= '</ul>';
       $returnvalue .= '</form>';
       return  $returnvalue;
   }
}

//==============================================================================
?>
