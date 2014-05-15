dojo.require("dojo.io.script");
dojo.io.script.get({url: "http://localhost:8080/js/fp-data-entry-plugin.js"});

function on_grab_lichen_click() {
	fp_data_entry_plugin({
		iframe_parent_id: "fp-data-entry-plugin-goes-here",
		url: "http://localhost:8080", // Change this to point to the real server.
		css_urls: [
			"css/data-entry.css", // default css is provided on server.
		],
		js_urls: [
			"http://localhost/rapid/dataentry_extra.js" 
		],
	
		// optional:
		width: window.innerWidth * 1/3, // TODO: better logic for this.
		//height: 800,
		
		// To hide input fields, find and remove them in your extra JS.
		outputs_to_hide: ["exsiccateTitle","exsiccateNumber","countryCode",
		                  "kingdom", "phylum", "class", "order","scientificNameAuthorship"],

		q_names: {
			exsiccate_number: "exsiccatinumber"
		},
		q_ids: {
			exsiccate_title: "exsiccati"
		},
		a_names: {
			verbatimLocality: "specificlocality",
			decimalLatitude: "verbatimlat",
			decimalLongitude: "verbatimlong",
			eventDate: "datecollected",
			habitat: "habitat",
			recordNumber: "fieldnumber",
			minimumElevationInMeters: "minelevation",
			maximumElevationInMeters: "maxelevation",

			recordedBy_id: "collectors",
			scientificName_id: "filedundername",
			typeStatus_id: "typestatus"
		},
		a_ids: {
			recordedBy: "collectors",
			scientificName: "filedundername",
			typeStatus: "typestatus"
		}
	});
}

function on_grab_nevp_click() {
	// TODO: Make this hit NEVP: right now it still hits the exsic server.
	fp_data_entry_plugin({
		iframe_parent_id: "fp-data-entry-plugin-goes-here",
		url: "http://localhost:8080", // Change this to point to the real server.
		css_urls: [
			"css/data-entry.css", // default css is provided on server.
		],
		js_urls: [
			"http://localhost/rapid/dataentry_extra.js" 
		],
	
		// optional:
		width: window.innerWidth * 1/3, // TODO: better logic for this.
		//height: 800,
		
		// To hide input fields, find and remove them in your extra JS.
		outputs_to_hide: ["exsiccateTitle","exsiccateNumber","countryCode",
		                  "kingdom", "phylum", "class", "order","scientificNameAuthorship"],

		q_names: {
			exsiccate_number: "exsiccatinumber"
		},
		q_ids: {
			exsiccate_title: "exsiccati"
		},
		a_names: {
			verbatimLocality: "specificlocality",
			decimalLatitude: "verbatimlat",
			decimalLongitude: "verbatimlong",
			eventDate: "datecollected",
			habitat: "habitat",
			recordNumber: "fieldnumber",
			minimumElevationInMeters: "minelevation",
			maximumElevationInMeters: "maxelevation",

			recordedBy_id: "collectors",
			scientificName_id: "filedundername",
			typeStatus_id: "typestatus"
		},
		a_ids: {
			recordedBy: "collectors",
			scientificName: "filedundername",
			typeStatus: "typestatus"
		}
	});
}
