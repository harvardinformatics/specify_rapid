create table TR_BATCH (
   -- filesystem based batches of images for transcription
   tr_batch_id bigint not null auto_increment primary key,  -- surrogate numeric primary key
   path varchar(255) not null,  -- IMAGE_LOCAL_FILE.path to batch (length limited by unique index)
   image_batch_id bigint, -- foreign key to IMAGE_BATCH
   completed_date date default null -- set date when batch is completed
);

create unique index IDXUBATCHPATH on TR_BATCH(path);

create table TR_BATCH_IMAGE (
  -- crossref table linking IMAGE_OBJECTs to TR_BATCHes
  tr_batch_id bigint not null,
  image_object_id bigint not null,
  barcode char(10),
  position int not null,

  UNIQUE INDEX(tr_batch_id,position),
  CONSTRAINT FOREIGN KEY (tr_batch_id) REFERENCES TR_BATCH(tr_batch_id),
  CONSTRAINT FOREIGN KEY (image_object_id) REFERENCES IMAGE_OBJECT(id)
);

CREATE INDEX IDXTRBATCHID ON TR_BATCH_IMAGE(tr_batch_id);
CREATE INDEX IDXTRIMAGEOBJECTID ON TR_BATCH_IMAGE(image_object_id);
CREATE INDEX IDXTRBARCODE ON TR_BATCH_IMAGE(barcode);

create table TR_USER_BATCH (
   -- pending, in progress, and completed batches for users.
   tr_user_batch_id bigint not null auto_increment primary key, -- surrogate numeric primary key
   tr_batch_id bigint not null,
   username varchar(64) not null,-- specifyuser.name, huh_webuser.username
   position int not null default 1, -- last file transcribed in batch

   KEY IDXUSERBATCHBATCHID (tr_batch_id),
   CONSTRAINT FOREIGN KEY (tr_batch_id) REFERENCES TR_BATCH(tr_batch_id),
   CONSTRAINT FOREIGN KEY (username) REFERENCES specifyuser(name)

);

create table TR_ACTION_LOG (
   -- track transcription activity
   tr_action_log_id bigint not null auto_increment primary key, -- surrogate numeric primary key
   username varchar(64) not null,-- specifyuser.name, huh_webuser.username
   action_time timestamp default CURRENT_TIMESTAMP not null,  -- timestamp for the action
   action varchar(50) not null, -- specific action carried out
   details text -- remarks about the action
);
