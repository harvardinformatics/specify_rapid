dojo.require("dojo.io.script");
dojo.io.script.get({url: "http://localhost:8080/js/fp-data-entry-plugin.js"});

function on_grab_click() {
	fp_data_entry_plugin({
		iframe_parent_id: "fp-data-entry-plugin-goes-here",
		url: "http://localhost:8080", // Change this to point to the real server.
		css_urls: [
			"css/data-entry.css", // default css is provided on server.
		],
		js_urls: [
			"http://localhost/rapid/dataentry_extra.js" 
			// TODO
		],
	
		// optional:
		width: window.innerWidth * 1/3,
		//height: 800,
		
		// To hide input fields, find and remove them in your extra JS.
		outputs_to_hide: ["recordNumber","verbatimDepth",
		                  "exsiccateTitle","exsiccateNumber",
		                  "kingdom", "phylum", "class", "order"],

		q_names: {
			exsiccate_number: "exsiccatinumber"
		},
		q_ids: {
			exsiccate_title: "exsiccati"
		},
		a_names: {
			// recordNumber: "?",
			// countryCode: "?",
			// country: "?",
			// verbatimElevation: "?",
			// verbatimDepth: "?",
			recordedBy_id: "collectors",
			verbatimLocality: "specificlocality",
			decimalLatitude: "verbatimlat",
			decimalLongitude: "verbatimlong",
			eventDate: "datecollected",
			habitat: "habitat",
			scientificName_id: "filedundername"
		},
		a_ids: {
			scientificName: "filedundername",
			recordedBy: "collectors"
		}
	});
}
