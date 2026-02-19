( function( $ ) {
	'use strict';

	/**
	 * Show toast notification.
	 *
	 * @param {string} message Toast message text.
	 */
	function showToast( message ) {
		var $toast = $( '.wptomedium-toast' );
		$toast.text( message ).fadeIn( 200 );
		setTimeout( function() {
			$toast.fadeOut( 200 );
		}, 2000 );
	}

	// Translate button on articles list.
	$( document ).on( 'click', '.wptomedium-translate', function( e ) {
		e.preventDefault();

		var $link  = $( this );
		var postId = $link.data( 'post-id' );

		$link.text( 'Translating...' ).addClass( 'disabled' );

		$.post( wptomediumData.ajaxUrl, {
			action:  'wptomedium_translate',
			nonce:   wptomediumData.nonce,
			post_id: postId,
		} )
		.done( function( response ) {
			if ( response.success ) {
				window.location.href = response.data.review_url;
			} else {
				alert( response.data );
				$link.text( 'Translate' ).removeClass( 'disabled' );
			}
		} )
		.fail( function() {
			alert( 'Translation request failed.' );
			$link.text( 'Translate' ).removeClass( 'disabled' );
		} );
	} );

	// Save translation.
	$( document ).on( 'click', '.wptomedium-save', function() {
		var postId  = $( this ).data( 'post-id' );
		var title   = $( '#wptomedium-translated-title' ).val();
		var content = '';

		// TinyMCE Content holen.
		if ( typeof tinyMCE !== 'undefined' && tinyMCE.get( 'wptomedium_translation_editor' ) ) {
			content = tinyMCE.get( 'wptomedium_translation_editor' ).getContent();
		} else {
			content = $( '#wptomedium_translation_editor' ).val();
		}

		$.post( wptomediumData.ajaxUrl, {
			action:  'wptomedium_save',
			nonce:   wptomediumData.nonce,
			post_id: postId,
			title:   title,
			content: content,
		} )
		.done( function( response ) {
			if ( response.success ) {
				showToast( response.data );
			} else {
				alert( response.data );
			}
		} );
	} );

	// Retranslate.
	$( document ).on( 'click', '.wptomedium-retranslate', function() {
		var $btn   = $( this );
		var postId = $btn.data( 'post-id' );

		$btn.prop( 'disabled', true ).text( 'Translating...' );

		$.post( wptomediumData.ajaxUrl, {
			action:  'wptomedium_translate',
			nonce:   wptomediumData.nonce,
			post_id: postId,
		} )
		.done( function( response ) {
			if ( response.success ) {
				window.location.reload();
			} else {
				alert( response.data );
				$btn.prop( 'disabled', false ).text( 'Retranslate' );
			}
		} );
	} );

	// Copy as HTML.
	$( document ).on( 'click', '.wptomedium-copy-html', function() {
		var content = '';

		if ( typeof tinyMCE !== 'undefined' && tinyMCE.get( 'wptomedium_translation_editor' ) ) {
			content = tinyMCE.get( 'wptomedium_translation_editor' ).getContent();
		} else {
			content = $( '#wptomedium_translation_editor' ).val();
		}

		navigator.clipboard.writeText( content ).then( function() {
			showToast( 'HTML copied!' );
		} );
	} );

	// Copy as Markdown.
	$( document ).on( 'click', '.wptomedium-copy-markdown', function() {
		var postId = $( this ).data( 'post-id' );

		$.post( wptomediumData.ajaxUrl, {
			action:  'wptomedium_copy_markdown',
			nonce:   wptomediumData.nonce,
			post_id: postId,
		} )
		.done( function( response ) {
			if ( response.success ) {
				navigator.clipboard.writeText( response.data.markdown ).then( function() {
					showToast( 'Markdown copied!' );
				} );
			} else {
				alert( response.data );
			}
		} );
	} );

} )( jQuery );
