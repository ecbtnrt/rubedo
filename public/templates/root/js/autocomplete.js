$(function(){
	$('.typeahead').typeahead({
	    source: function (query, process) {
			var request = jQuery.ajax({
				url : '/blocks/search/xhr-get-suggests',
				type : "POST",
				data : {
					'query' : query,
					'current-page':jQuery('body').attr('data-current-page'),
					'searchParams':jQuery('#searchpage').attr('data-searchparams')
				},
				dataType : "json"
			});
			request.done(function(data) {
				return process(data.terms);
			});
			
	    },
	    items:10,
	    minLength:3
	})
});