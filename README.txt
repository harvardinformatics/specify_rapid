Requires: 

(1) Queries

create table huh_webuser (username varchar(255) not null primary key, sessionsecret varchar(255));

create index agentvartype on agentvariant(vartype);

(2) connection_library.php off the web tree in phpincludes.  An example connection_library.php file
is included.

(3) /usr/bin/java -jar /var/www/phpincludes/Encryption.jar  With path to java and path to Encryption.jar
configured within connection_library.php.

(4) MySQL mode should not include ONLY_FULL_GROUP_BY, which is included by default since MySQL 5.7.5

To add a field (relational fields need all of these; text fields, some):

class_lib.php
-------------
* create custom object overriding druid default to include lookup methods referenced in ajax_handler
widget lookup processing, e.g. class huh_storage_custom
* declare global var for form input value in ingestCollectionObject
* assign nulls for empty string form input values
* add debug info, e.g. $df.="project=[$project]"
* create and call sql for inserts, e.g. "insert into preparation..."; take care to do inserts in proper
relational order

ajax_handler.php
----------------
* cleaning of input strings - filtering out chars by regexp: see case 'rapidaddprocessor'
* truncating, assigning new form values to variables: e.g. if ($fascicle!=$_GET['fascicle'])
* process lookup widget for relational fields, e.g. adding case 'returndistinctjsonfoo'

rapid.php
---------
* create html for form input field, e.g. selectStorageID("storage", "Subcollection")
* define functions referenced in html creation, e.g. function selectStorageID...

