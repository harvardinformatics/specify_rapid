function dataentry_grab() {
  var collectors_dojo = dijit.byId('collectors');
  var collectors_val = 'TODO: collector';

  var name_dojo = dijit.byId('filedundername');
  var name_val = 'TODO: scientific name';

  var number_el = dojo.query("input[name=fieldnumber]")[0];
  var number_val = 'TODO: number';

  var locality_el = dojo.query("input[name=specificlocality]")[0];
  var locality_val = 'TODO: locality';

  var lat_el = dojo.query("input[name=verbatimlat]")[0];
  var lat_val = 'TODO: latitude';

  var long_el = dojo.query("input[name=verbatimlong]")[0];
  var long_val = 'TODO: longitude';

  var date_el = dojo.query("input[name=datecollected]")[0];
  var date_val = 'TODO: date';
  var date_hint_el = dojo.query("input[name=datecollected] + span")[0]; // TODO: seems fragile?

  var elevation_el = dojo.query("input[name=verbatimelevation]")[0];
  var elevation_val = 'TODO: elevation';


// done	RECORD_NUMBER(DwcTerm.RECORD_NUMBER), //
// done	RECORDED_BY(DwcTerm.RECORDED_BY), //
// done	VERBATIM_LOCALITY(DwcTerm.VERBATIM_LOCALITY), //
// ?	COUNTRY(DwcTerm.COUNTRY_CODE, DwcTerm.COUNTRY), //
// ?	LATITUDE_LONGITUDE(DwcTerm.DECIMAL_LATITUDE, DwcTerm.DECIMAL_LONGITUDE), //
// done	EVENT_DATE(DwcTerm.EVENT_DATE), //
// done	ELEVATION_DEPTH(DwcTerm.VERBATIM_ELEVATION, DwcTerm.VERBATIM_DEPTH), //
//	KINGDOM(DwcTerm.KINGDOM), //
//	PHYLUM(DwcTerm.PHYLUM), //
//	CLASS(DwcTerm.CLASS), //
//	ORDER(DwcTerm.ORDER), //
//	FAMILY(DwcTerm.FAMILY), //
//	SCIENTIFIC_NAME( //
// done?		DwcTerm.GENUS, //
//			DwcTerm.SUBGENUS, //
//			DwcTerm.SPECIFIC_EPITHET, //
//			DwcTerm.INFRASPECIFIC_EPITHET, //
//			DwcTerm.TAXON_RANK, //
//			DwcTerm.SCIENTIFIC_NAME_AUTHORSHIP),
//	
//	SOURCE(DwcTerm.SOURCE), //
//	SEQUENCE(DwcTerm.SEQUENCE);




  dojo.attr(collectors_dojo,'value',collectors_val);
  collectors_dojo.textbox.value = collectors_val;

  dojo.attr(name_dojo,'value',name_val);
  name_dojo.textbox.value = name_val;

  number_el.value = number_val;

  locality_el.value = locality_val;

  lat_el.value = lat_val;

  long_el.value = long_val;

  date_el.value = date_val;
  dojo.attr(date_hint_el,'style','display:none')

  elevation_el.value = elevation_val;
}
