$('body,head').error(function(event) {
	console.log('Error!',event);
});


/*
 * Delete input fields you don't want.
 */

$('#input input').not('[name^="exsiccate_"]').parent().remove();

/*
 * Customize this to add selectors which pull from a local controlled vocabulary.
 */

fp.suggestion_fields = [
	'recordedBy',
	'scientificName'
];

fp.autosuggest_ajax = function(field_name,value,callback_name) {
	/*
	 * To create your own autosuggest, you probably want to
	 *   -- copy this JS to your own server, 
	 *   -- delete everything below that's not commented, 
	 *   -- uncomment the lines at the end of this block,
	 *   -- change the url to match your institution, 
	 *   -- and on your server implement a JSONP service that responds with something like:
	 * 
	 * 	<callback_name>({
	 * 		name: '<field_name>',
	 * 		list: [
	 * 			['<id>', '<value>'],
	 * 			...
	 * 		]
	 * 	});
	 * 
	 * The first result in the list should be the best match, because it will be automatically selected.
	 * 
	 * If you already have a JSONP service, but it returns results in a different format,
	 * rather than adding new code on the server, you might define a little transformation function
	 * here, and designate it as the callback, instead of the callback_name parameter.
	 * 

	var ajax_url = 'http://example.edu/collections-management/autosuggest-jsonp'
		+ '?name='+encodeURIComponent(field_name)
		+ '&value='+encodeURIComponent(value)
		+ '&callback='+encodeURIComponent(callback_name);
	
	$.getScript(ajax_url);

	*/
	
	// What follows just constructs a mock ajax response for demos.
	
	// TODO: use value;
	
	var fake_collectors = [
	    // These occur in the sample data:
		['1', 'Ahlner, Sten'],
		['2', 'Magnus E. Fries'],
		['1', 'S. Ahlner'],
		['1', 'Sten Ahlner'],
		['3', 'S.W. Sundell'],
		// Add something for every letter of the alphabet, to better demonstrate the autosuggest:
		['4', 'Allen, A.'],
		['5', 'Brown, B.'],
		['6', 'Clark, C.'],
		['7', 'Davis, D.'],
		['8', 'Evans, E.'],
		['9', 'Flores, F.'],
		['10', 'Garcia, G.'],
		['11', 'Hernandez, H.'],
		['12', 'Ingram, I.'],
		['13', 'Johnson, J.'],
		['14', 'King, K.'],
		['15', 'Lopez, L.'],
		['16', 'Miller, M.'],
		['17', 'Nelson, N.'],
		['18', 'Ortiz, O.'],
		['19', 'Perez, P.'],
		['20', 'Quinn, Q.'],
		['21', 'Rodriguez, R.'],
		['22', 'Smith, S.'],
		['23', 'Taylor, T.'],
		['24', 'Underwood, U.'],
		['25', 'Vasquez, V.'],
		['26', 'Williams, W.'],
		['27', 'Young, Y.'],
		['28', 'Zimmerman, Z.']
	];

	var fake_species = [
		['2', 'Chaenotheca trichialis (Ach.) Th. Fr.'],
		['3', 'Chrysothrix candelaris (L.) J. R. Laundon'],
		['4', 'Diplotomma alboatrum'],
		['5', 'Evernia prunastri (L.) Ach.'],
		['6', 'Haematomma ochroleucum'],
		['7', 'Lecanora circumborealis'],
		['8', 'Lecanora populicola'],
		['9', 'Lecanora populicola (DC.) Duby'],
		['10', 'Lobaria scrobiculata (Scop.) P. Gaertn.'],
		['11', 'Pertusaria albescens'],
		['12', 'Pertusaria lactea'],
		['13', 'Pleurosticta acetabulum (Neck.) Elix & Lumbsch'],
		['14', 'Ramalina baltica Lettau'],
		['15', 'Ramalina fastigiata (Pers.) Ach.'],
		['16', 'Ramalina fraxinea'],
		['17', 'Sclerophora nivea (Hoffm.) Tibell']
	];
	
	eval(callback_name).call(null,{
		name: field_name,
		list: $.grep(
			field_name == 'recordedBy' ? fake_collectors : fake_species,
			function(pair) {
				return pair[1].substr(0,1) == value.substr(0,1).toUpperCase();
			}
		)
	});
	
	
}
