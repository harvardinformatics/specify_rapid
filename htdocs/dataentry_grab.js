dojo.require("dojo.io.script");

function dataentry_grab() {
  dojo.io.script.get({'url':'http://localhost:8080/STATE'});
  // TODO: make callback param explicit.
}

function dataentry_callback(data) {
  var collectors_dojo = dijit.byId('collectors');
  var collectors_el = dojo.query("input[name=collectors]")[0];
  var collectors_val = data['recordedBy'];
  var collectors_id = data['recordedBy_id'];

  var name_dojo = dijit.byId('filedundername');
  var name_el = dojo.query("input[name=filedundername]")[0];
  var name_val = data['specificEpithet'];
  var name_id = data['specificEpithet_id'] || data['scientificName_id'];

  var number_el = dojo.query("input[name=fieldnumber]")[0];
  var number_val = data['recordNumber'];

  var locality_el = dojo.query("input[name=specificlocality]")[0];
  var locality_val = data['verbatimLocality'];

  var lat_el = dojo.query("input[name=verbatimlat]")[0];
  var lat_val = data['decimalLatitude'];

  var long_el = dojo.query("input[name=verbatimlong]")[0];
  var long_val = data['decimalLongitude'];

  var date_el = dojo.query("input[name=datecollected]")[0];
  var date_val = data['eventDate'].replace(/T.*/,'');
  var date_hint_el = dojo.query("input[name=datecollected] + span")[0]; // TODO: seems fragile?

  var elevation_el = dojo.query("input[name=verbatimelevation]")[0];
  var elevation_val = data['verbatimElevation'];;

  // ... now set the fields:

  dojo.attr(collectors_dojo,'value',collectors_val);
  collectors_dojo.textbox.value = collectors_val;
  collectors_el.value = collectors_id;

  dojo.attr(name_dojo,'value',name_val);
  name_dojo.textbox.value = name_val;
  name_el.value = name_id;

  number_el.value = number_val;

  locality_el.value = locality_val;

  lat_el.value = lat_val;

  long_el.value = long_val;

  date_el.value = date_val;
  dojo.attr(date_hint_el,'style','display:none')

  elevation_el.value = elevation_val;
}
