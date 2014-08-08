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
	'scientificName',
	'typeStatus'
];

fp.autosuggest_ajax = function(field_name,value,callback_name) {
	var ajax_url = 'http://watson.huh.harvard.edu/rapid/ajax_handler_jsonp.php'
		+ '?name='+encodeURIComponent(field_name)
		+ '&value='+encodeURIComponent(value)
		+ '&callback='+encodeURIComponent(callback_name);
	$.getScript(ajax_url);
}
