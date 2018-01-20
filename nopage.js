(function($){
	// fill in quick edit fields
	var inlineEditPost_edit = inlineEditPost.edit;
	inlineEditPost.edit = function( id ) {
		inlineEditPost_edit.apply( this, arguments );

        var post_id = typeof( id ) == 'object' ? parseInt( this.getId( id ) ) : 0;
        if(post_id <= 0)
        	return;

		var nopage_val = $('#wpedit-nopage'+post_id).val(); // get value from column
		$('#edit-' + post_id).find('[name="wpedit-nopage"]').val(nopage_val);
	};

	// submit bulk edits
	$( document ).on( 'click', '#bulk_edit', function(){
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			async: false,
			cache: false,
			data: {
				action: 'nopage_bulk_edit',
				wpedit_nopage: $('#bulk-edit [name="wpedit-nopage"]').val(), // select value
				post_ids: $('#bulk-edit #bulk-titles a').map(function(){ 	// list of post ids
					return $(this).attr('id').replace('_', ''); 
				}).toArray()
			}
		});
	});
})(jQuery);