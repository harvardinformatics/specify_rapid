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
			"http://localhost:8080/js/demo-extra.js" 
			// TODO: point at ajax-handling js.
		],
	
		// optional:
		//width: window.innerWidth * 2/3,
		//height: 800,
	
		q_names: {
			// TODO: expand this, when we have the data to back it.
			// taxon: "my_name",
			geography: "specificlocality",
			// collector: "collectors",
			number: "my_number",
			date: "datecollected"
			// Just for exsiccate:
			// source: "?",
			// sequence: "?"
		},
		q_ids: {
			collector: "collectors"
		},
		a_names: {
			// recordNumber: "?",
			// countryCode: "?",
			// country: "?",
			// verbatimElevation: "?",
			// verbatimDepth: "?",
			// recordedBy: "collectors",
			recordedBy_id: "collectors",
			verbatimLocality: "specificlocality",
			decimalLatitude: "verbatimlat",
			decimalLongitude: "verbatimlong",
			eventDate: "datecollected",
			// kingdom: "?",
			// phylum: "?",
			// class: "?",
			// order: "?",
			// family: "?",
			// genus: "?",
			// subgenus: "?",
			// specificEpithet: "?",
			// infraspecificEpithet: "?",
			// taxonRank: "?",
			// scientificNameAuthorship: "?",
			// scientificName: "?",
			// scientificName_id: "?",
		},
		a_ids: {
			recordedBy: "collectors"
		}
	});
}
