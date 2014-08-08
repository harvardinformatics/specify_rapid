dojo.require("dojo.io.script");
dojo.io.script.get({url: "http://140.247.98.183:8086/js/fp-data-entry-plugin.js"});

function on_grab_lichen_click() {
	fp_data_entry_plugin({
		iframe_parent_id: "fp-data-entry-plugin-goes-here",
		url: "http://140.247.98.183:8086", // Change this to point to the real server.
		css_urls: [
			"css/data-entry.css", // default css is provided on server.
		],
		js_urls: [
			"http://watson.huh.harvard.edu/rapid/dataentry_extra.js" 
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
		url: "http://fp3.acis.ufl.edu:8888", // Change this to point to the real server.
		css_urls: [
			"css/data-entry.css", // default css is provided on server.
		],
		js_urls: [
			// TODO: Same controlled vocab as for lichens?
			// "http://localhost/rapid/dataentry_extra.js" 
		],
	
		// optional:
		width: window.innerWidth * 1/3, // TODO: better logic for this.
		//height: 800,
		
		// Which fields will be used to query the widget?
		// None of these are required, but at least one should be present.
		// Your field names should be unique. 
		q_names: {
			collector: "collector",
			collectionNumber: "collectionNumber"
		},
		
		// As changes are made in the widget, which fields in the parent are changed?
		// Typically, these are input name attributes, but if you need something more flexible,
		// a function can be given in selector_function.
		selectors: {
			"genus":"genus",
			"specificEpithet":"specificEpithet",
			"infraspecificRank":"infraspecificRank",
			"infraspecificEpithet":"infraspecificEpithet",
			"scientificNameAuthorship":"scientificNameAuthorship",
			"identificationQualifier":"identificationQualifier",
			"collector":"collector",
			"collectionNumber":"collectionNumber",
			"verbatimCollectionDate":"verbatimCollectionDate"
		},
		selector_function: function(name) {
			var els = document.getElementsByName(name);
			if (els.length > 1) {
				throw Error('Name "'+name+'" is not unique in this document.');
			};
			return els[0];
		},

		// Whether confirmation is explicitly required, or if data can automatically
		// be copied over into the host interface.
		require_confirmation: false,

		// Arbitrary JavaScript to be run against the target page.
		// This can be useful when the target application needs to be
		// prompted to create all the form fields we want to fill in.
		pre_js: "" 
	});
}
