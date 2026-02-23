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

	/**
	 * Persist copied status for a translated post.
	 *
	 * @param {number} postId Post ID.
	 */
	function markCopiedStatus( postId ) {
		$.post( wptomediumData.ajaxUrl, {
			action:  'wptomedium_mark_copied',
			nonce:   wptomediumData.nonce,
			post_id: postId,
		} );
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
		var postId  = $( this ).data( 'post-id' );
		var content = '';

		if ( typeof tinyMCE !== 'undefined' && tinyMCE.get( 'wptomedium_translation_editor' ) ) {
			content = tinyMCE.get( 'wptomedium_translation_editor' ).getContent();
		} else {
			content = $( '#wptomedium_translation_editor' ).val();
		}

		navigator.clipboard.writeText( content ).then( function() {
			markCopiedStatus( postId );
			showToast( wptomediumData.htmlCopied );
		} ).catch( function() {
			alert( wptomediumData.requestFailed );
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
					markCopiedStatus( postId );
					showToast( wptomediumData.markdownCopied );
				} ).catch( function() {
					alert( wptomediumData.requestFailed );
				} );
			} else {
				alert( response.data );
			}
		} )
		.fail( function() {
			alert( wptomediumData.requestFailed );
		} );
	} );

	/**
	 * Update the model dropdown with new models.
	 *
	 * @param {Object} models Key-value pairs of model ID => display name.
	 */
	function updateModelDropdown( models ) {
		var $select  = $( '#wptomedium-model-select' );
		var previous = $select.val();

		$select.empty();
		$.each( models, function( id, name ) {
			$select.append(
				$( '<option>' ).val( id ).text( name )
			);
		} );

		if ( $select.find( 'option[value="' + previous + '"]' ).length ) {
			$select.val( previous );
		}
	}

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
				$result.text( response.data.message ).css( 'color', '#00a32a' ).fadeIn();
				if ( response.data.models ) {
					updateModelDropdown( response.data.models );
				}
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

	// Restore Default Prompt.
	$( document ).on( 'click', '.wptomedium-restore-prompt', function() {
		$( '#wptomedium-system-prompt' ).val( wptomediumData.defaultPrompt );
	} );

	// Refresh Models.
	$( document ).on( 'click', '.wptomedium-refresh-models', function() {
		var $btn    = $( this );
		var $result = $( '.wptomedium-refresh-result' );

		$btn.prop( 'disabled', true ).text( wptomediumData.refreshing );
		$result.hide();

		$.post( wptomediumData.ajaxUrl, {
			action: 'wptomedium_refresh_models',
			nonce:  wptomediumData.nonce,
		} )
		.done( function( response ) {
			if ( response.success ) {
				$result.text( response.data.message ).css( 'color', '#00a32a' ).fadeIn();
				if ( response.data.models ) {
					updateModelDropdown( response.data.models );
				}
			} else {
				$result.text( response.data ).css( 'color', '#d63638' ).fadeIn();
			}
		} )
		.fail( function() {
			$result.text( wptomediumData.requestFailed ).css( 'color', '#d63638' ).fadeIn();
		} )
		.always( function() {
			$btn.prop( 'disabled', false ).text( wptomediumData.refreshModels );
		} );
	} );

	/**
	 * Force Visual mode and keep side-by-side panes scroll-synchronized.
	 */
	function initReviewPageComparison() {
		var editorId = 'wptomedium_translation_editor';
		var $review  = $( '.wptomedium-review-wrap' );
		var leftPane = document.querySelector( '.wptomedium-panel-original' );
		var rightPane = document.querySelector( '.wptomedium-panel-translation' );

		if ( ! $review.length || ! leftPane || ! rightPane ) {
			return;
		}

		var isSyncing = false;

		function getEditorWrap() {
			return document.getElementById( 'wp-' + editorId + '-wrap' );
		}

		function ensureVisualMode() {
			var editorWrap = getEditorWrap();
			if ( ! editorWrap || editorWrap.classList.contains( 'tmce-active' ) ) {
				return;
			}

			if ( 'undefined' !== typeof switchEditors && switchEditors && 'function' === typeof switchEditors.go ) {
				switchEditors.go( editorId, 'tmce' );
			}
		}

		function autoResizeEditor() {
			if ( 'undefined' === typeof tinyMCE || ! tinyMCE ) {
				return;
			}

			var editor = tinyMCE.get( editorId );
			if ( ! editor || editor.isHidden() ) {
				return;
			}

			editor.execCommand( 'mceAutoResize' );
			editor.execCommand( 'mceAutoResize' );
		}

		function syncScrollPosition( source, target ) {
			var sourceMax;
			var targetMax;
			var ratio;

			if ( isSyncing || ! source || ! target ) {
				return;
			}

			sourceMax = source.scrollHeight - source.clientHeight;
			targetMax = target.scrollHeight - target.clientHeight;

			if ( sourceMax <= 0 || targetMax <= 0 ) {
				return;
			}

			ratio = source.scrollTop / sourceMax;

			isSyncing        = true;
			target.scrollTop = ratio * targetMax;
			isSyncing        = false;
		}

		function onLeftScroll() {
			if ( rightPane ) {
				syncScrollPosition( leftPane, rightPane );
			}
		}

		function onRightScroll() {
			syncScrollPosition( rightPane, leftPane );
		}

		ensureVisualMode();
		window.setTimeout( autoResizeEditor, 120 );
		window.setTimeout( ensureVisualMode, 120 );
		window.setTimeout( autoResizeEditor, 320 );
		window.setTimeout( ensureVisualMode, 600 );
		window.setTimeout( autoResizeEditor, 800 );

		leftPane.addEventListener( 'scroll', onLeftScroll );
		rightPane.addEventListener( 'scroll', onRightScroll );

		$( document ).on( 'click', '#wp-' + editorId + '-wrap .wp-switch-editor', function() {
			window.setTimeout( autoResizeEditor, 160 );
			window.setTimeout( autoResizeEditor, 400 );
		} );

		if ( 'undefined' !== typeof tinymce && tinymce ) {
			tinymce.on( 'AddEditor', function( event ) {
				if ( event.editor && editorId === event.editor.id ) {
					event.editor.on( 'init', function() {
						ensureVisualMode();
						window.setTimeout( autoResizeEditor, 120 );
					} );

					event.editor.on( 'SetContent', function() {
						window.setTimeout( autoResizeEditor, 40 );
					} );
				}
			} );
		}
	}

	initReviewPageComparison();

	} )( jQuery );
