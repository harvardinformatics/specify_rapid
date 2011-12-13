Requires: 

(1) Queries

create table huh_webuser (username varchar(255) not null primary key, sessionsecret varchar(255));

create index agentvartype on agentvariant(vartype);

(2) connection_library.php off the web tree in phpincludes

(3) /usr/bin/java -jar /var/www/phpincludes/Encryption.jar