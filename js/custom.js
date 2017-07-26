function removeEmptyKeys(){

	var links = jQuery.map( jQuery('.files_links').val().split('\n') , jQuery.trim );

	arr = links.filter(function(n){ return n != '' }); 

	return arr;

}

jQuery(document).on( 'click' , '.save_files' , function(){

	/*
	** If empty show error message. 
	*/

	if( jQuery.isEmptyObject( removeEmptyKeys() ) == true ){
		alert( 'Add direct links fields cannot be empty' );
		return;
	}

	jQuery('.upload_info').show();
	jQuery( '.upload_info tr:not("[class=table_head]")' ).remove();
	jQuery('.save_files').prop( 'disabled' , true );

	/*
	* Scroll to #upload_information
	*/
	jQuery('html, body').animate({
	    scrollTop: jQuery("#upload_information").offset().top
	}, 'fast' );

	var links = removeEmptyKeys();
	var i = 0;

	asyncAjaxCall( links , i  );

});

function asyncAjaxCall( links , i  ){

	jQuery.ajax({
		url : emuAjax,
		type : 'post',
		//async: false,
		dataType : 'json',
		data : {
			action : 'save_external_files',
			link : links[i]
		},
		beforeSend : function(){
			jQuery('.upload_info').append('<tr><td>' + ( i + 1 ) + '</td>' + 
				'<td><a target="blank" href="' + links[i] + '">' + links[i] + '</a></td>' +
				'<td class="size_processing_' + i + '"></td>' + 
				'<td class="actions_processing_' + i + '"></td>' + 
				'<td>' +
				'<img class="link_processing link_processing_' + i + '" src="' + 'https://mpphscl.in/Content/images/windows8.gif' + '"/>' +
			'</td></tr>');
		},
		success : function( data ){

			var imgHide = '.link_processing_' + i;
			var size = '.size_processing_' + i;
			var actions = '.actions_processing_' + i;

			jQuery( imgHide ).hide();
			jQuery( size ).text( (data.file_size).trim() );
			jQuery( actions ).html( (data.actions).trim() );

			var msg = '<span>' + (data.message).trim() + '</span>';
			jQuery( msg ).insertAfter( imgHide );

			if( i < ( links.length - 1 ) ){
				asyncAjaxCall( links , ++i  );
			} else {
				jQuery('.save_files').prop( 'disabled' , false );
			}

		}
	});

}

jQuery( document ).on( 'click' , '.emu_tabs a' , function(){

	jQuery('.emu_tabs a').removeClass( 'active' );
	jQuery( this ).addClass( 'active' );

	jQuery( '.emu_wrapper table' ).hide();
	var active_tab = jQuery( this ).attr( 'for' );
	jQuery( '.emu_wrapper table#' + active_tab ).show();

});

function checkURL(url) {
	var filterURl = url.split('?')[0];
    return( filterURl.match(/\.(jpeg|jpg|bmp|gif|png)$/) != null );
}

jQuery( document ).on( 'click' , '.save_to_post' , function(){

	var url = jQuery('.featured_image_url').val();

	if( url == '' ){
		alert( 'Image field cannot be empty.' );
		return;
	}

	if( checkURL(url) != true ){
		alert( 'Only jpeg , jpg , bmp , gif & png files allowed.' );
		return;
	}

	if( jQuery('.post_upload').val() == '' ){
		alert( 'Please select one post/page.' );
		return;
	}

	jQuery.ajax({
		url : emuAjax,
		type : 'post',
		dataType : 'json',
		data : {
			action : 'emu_save_to_post',
			link : url,
			post_id : jQuery('.post_upload').val()
		},
		beforeSend : function(){
			jQuery( '.save_to_post' ).val( 'Saving ...' ).prop( 'disabled' , true );
			jQuery('.error_row').hide();
			jQuery('.success_row').hide();
		},
		success : function( data ){
			jQuery( '.save_to_post' ).val( 'Save' ).prop( 'disabled' , false );;

			if( data.result == 'success' ){
				jQuery('.success_row').show();
			} else if( data.result == 'file_not_found' || data.result == 'error' ){
				jQuery('.save_to_post_error').text( data.message );
				jQuery('.error_row').show();
			}
		}
	});

});

jQuery( document ).on( 'click' , '.emu_settings_btn' , function(){

	var i = 0;
	var arrayExt = [];

	jQuery( '.emu_options tr' ).each( function(){

		jQuery(this).find( 'td label input[name="allow_files_extensions"]' ).each( function(){

			if( jQuery(this).is(':checked') ){
				arrayExt[i++] = jQuery(this).val().trim();
			}

		});

	});

	jQuery.ajax({
		url : emuAjax,
		type : 'post',
		dataType : 'json',
		data : {
			action : 'save_emu_settings',
			allow_files : arrayExt,
			timeout : jQuery( '.download_timeout' ).val()
		},
		beforeSend : function(){
			jQuery('.emu_settings_btn').val( 'Saving ...' );
		},
		success : function( data ){
			jQuery('.emu_settings_btn').val( 'Save' );
			location.reload();
		}

	});

});