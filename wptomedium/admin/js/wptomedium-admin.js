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

		$link.text( wptomediumData.translating ).addClass( 'disabled' );

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
				$link.text( wptomediumData.translate ).removeClass( 'disabled' );
			}
		} )
		.fail( function() {
			alert( wptomediumData.requestFailed );
			$link.text( wptomediumData.translate ).removeClass( 'disabled' );
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

		$btn.prop( 'disabled', true ).text( wptomediumData.translating );

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
				$btn.prop( 'disabled', false ).text( wptomediumData.retranslate );
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
			showToast( wptomediumData.htmlCopied );
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
					showToast( wptomediumData.markdownCopied );
				} );
			} else {
				alert( response.data );
			}
		} );
	} );

	// Validate API Key.
	$( document ).on( 'click', '.wptomedium-validate-key', function() {
		var $btn    = $( this );
		var $result = $( '.wptomedium-validate-result' );
		var apiKey  = $( 'input[name="wptomedium_api_key"]' ).val();

		$btn.prop( 'disabled', true ).text( wptomediumData.validating );
		$result.hide();

		$.post( wptomediumData.ajaxUrl, {
			action:  'wptomedium_validate_key',
			nonce:   wptomediumData.nonce,
			api_key: apiKey,
		} )
		.done( function( response ) {
			if ( response.success ) {
				$result.text( response.data ).css( 'color', '#00a32a' ).fadeIn();
			} else {
				$result.text( response.data ).css( 'color', '#d63638' ).fadeIn();
			}
		} )
		.fail( function() {
			$result.text( wptomediumData.requestFailed ).css( 'color', '#d63638' ).fadeIn();
		} )
		.always( function() {
			$btn.prop( 'disabled', false ).text( wptomediumData.validateKey );
		} );
	} );

} )( jQuery );
