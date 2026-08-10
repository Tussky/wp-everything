/*
 *	Register jQuery object that places caret at the end of a contenteditable element, after value
 */
jQuery.fn.AS_putCursorAtEnd = function() {
	var el = jQuery( this );

	el.focus();

	var range = document.createRange();

	range.selectNodeContents( el[0] );
	range.collapse( false );

	var sel = window.getSelection();

	sel.removeAllRanges();
	sel.addRange( range );
};


/*
 *	Register function to set cookie. Reserved for admin-search
 */
function AS_set_cookie( name, value ) {
	let date = new Date();

	date.setTime( date.getTime() + 31536000000 );

	const expires = 'expires=' + date.toUTCString();

	document.cookie = 'as_' + name + '=' + value + '; ' + expires + '; path=/';
}


/*
 *	Register function to get cookie. Reserved for admin-search
 */
function AS_get_cookie( name ) {
	name = 'as_' + name;

	const cookies = `; ${document.cookie}`;
	const parts = cookies.split(`; ${name}=`);

	if ( parts.length === 2 ) {
		return parts.pop().split( ';' ).shift();
	}
}


function AS_clear_searches( nonce ) {
	if ( confirm( admin_search.strings.confirm_clear_searches ) ) {
		jQuery( '#admin-search-clear-searches-button' ).remove();

		jQuery.get( admin_search.ajax_url, {
			action : 'admin_search_clear_searches_ajax',
			_ajax_nonce : nonce
		}, function( response ) {
			if ( response.error ) {
				console.log( response.message );
			}
		} );
	}
}


var admin_search_open = false,
	admin_search_input_focussed = false,
	admin_search_has_results = false,
	admin_search_current_query = '',
	admin_search_preview_open = false,
	admin_search_scrolling = false,
	admin_search_transition_speed = 300,
	admin_search_x = AS_get_cookie( 'x' ),
	admin_search_y = AS_get_cookie( 'y' ),
	admin_search_has_suggestion = false,
	admin_search_suggestion = '';


/*
 *	Register function to submit admin-search form
 */
function admin_search_submit_form() {
	// Ignore if the admin-search modal isn't open
	if ( ! admin_search_open ) {
		return false;
	}

	var q = jQuery( '#admin-search-input-field-value' ).text();


	// If the search term is not a string or is too short, clear the search if it has results or ignore the submission
	if ( ! ( q && typeof q == 'string' && q.length > 1 ) ) {
		admin_search_clear( false );

		return false;
	}


	// Ignore if the search term is the active search term
	if ( q == admin_search_current_query ) {
		return false;
	}


	// Set initial states
	admin_search_has_results = false;
	admin_search_current_query = q;

	jQuery( '#admin-search-modal' ).addClass( 'admin-search-results-loading' );

	admin_search_close_preview();


	// Perform HTTP GET request to get results
	jQuery.get( admin_search.ajax_url, {
		action : 'admin_search_ajax',
		_ajax_nonce : admin_search.nonce,
		q : q
	}, function( response ) {
		// If there's an error, add it to console and look no further
		if ( response.error ) {
			console.log( response );

			return false;
		}

		// Clear any existing results and scroll to the top
		jQuery( '#admin-search-results' ).empty().scrollTop();


		// If include_admin_pages is set to true, loop through matching admin pages and add them to the results
		if ( admin_search.include_admin_pages ) {
			jQuery.each( admin_search.menu, function( ) {
				if ( this.name.includes( q.toLowerCase() ) ) {
					if ( response.results.length < 1 ) {
						response.results = new Object();
					}

					if ( typeof response.results.admin == 'undefined' ) {
						response.results.admin = {
							post_type : {
								name : 'admin',
								label : admin_search.admin_pages_label
							},
							posts : []
						};
					}

					response.results.admin.posts.push( {
						id : '',
						edit_post_link : this.edit_post_link,
						title :	this.title,
						preview : {
							title : this.title,
							content : null
						},
						date : null,
						status : this.status
					} );

					response.total_results++;
				}
			} );
		}


		// If there are results, build result groups and add them to results
		var admin_search_result_group_template = wp.template( 'admin-search-result-group-template' ),
			admin_search_result_template = wp.template( 'admin-search-result-template' ),
			admin_search_media_result_template = wp.template( 'admin-search-media-result-template' ),
			admin_search_result_group_pagination_template = wp.template( 'admin-search-results-group-pagination-template' );


		// If there are no results, show message and move on
		if ( response.total_results == 0 ) {
			admin_search_has_results = false;

			jQuery( '#admin-search-modal' ).addClass( 'admin-search-no-results' );
			jQuery( '<div>', {
				id : 'admin-search-results-message'
			} ).append( jQuery( '<div>', {
				id : 'admin-search-results-message-content'
			} ).text( response.message ) ).appendTo( '#admin-search-results' );

			setTimeout( function() {
				jQuery( '#admin-search-results-message' ).css( 'opacity', 1 );
			}, admin_search_transition_speed );
		} else {
			admin_search_has_results = true;

			let first_result = true;

			// Loop through each result and add it to its group
			jQuery.each( response.results, function() {
				let results = jQuery( '<div>' );

				if ( typeof this.post_type == 'object' && this.post_type.name == 'attachment' ) {
					jQuery.each( this.posts, function() {
						jQuery( admin_search_media_result_template( this ) ).appendTo( results );
					} );
				} else {
					jQuery.each( this.posts, function() {
						this.target = '_self';


						// If the result link it external, make sure it opens in a new tab
						if ( jQuery( '<a>', {
							href : this.edit_post_link
						} ).get( 0 ).hostname != location.hostname ) {
							this.target = '_blank';
						}

						let result = jQuery( admin_search_result_template( this ) );


						// If it's the first result, set focus state
						if ( first_result && this.edit_post_link ) {
							result.addClass( 'admin-search-result-focussed' );

							first_result = false;
						}

						result.appendTo( results );
					});


					// If there is more than one page of results, add pagination
					if ( this.next_page ) {
						jQuery( admin_search_result_group_pagination_template( this ) ).appendTo( results );
					}
				}


				// Build the results element and add it to admin-search
				jQuery( admin_search_result_group_template( {
					post_type_name : this.post_type.name,
					post_type_title : this.post_type.label,
					post_type_shortcut : this.post_type.search_url,
					results : results.html()
				} ) ).appendTo( '#admin-search-results' );
			});


			// There are results so clear the no results state
			jQuery( '#admin-search-modal' ).removeClass( 'admin-search-no-results' );
		}

		jQuery( '#admin-search-modal' ).addClass( 'admin-search-results-open' );

		setTimeout( function() {
			jQuery( '#admin-search-modal' ).removeClass( 'admin-search-results-loading' );
		}, admin_search_transition_speed );
	} );

	return true;
}


/*
 *	Register function to clear but not remove admin-search, effectively resetting the form
 */
function admin_search_clear( clear_field ) {
	// Clear and reset the state of admin-search modal
	// If clear_field is undefined, define it as true
	if ( typeof clear_field == 'undefined' ) {
		clear_field = true;
	}

	// If clear_field is set to true, clear the field value
	if ( clear_field ) {
		jQuery( '#admin-search-input-field-value' ).text( '' );
		jQuery( '#admin-search-modal' ).removeClass( 'admin-search-has-value' );
	}

	// Move the focus to the field
	jQuery( '#admin-search-input-field-value' ).focus();

	// If there are results, empty it in the following order
	if ( admin_search_has_results ) {
		jQuery( '#admin-search-modal' ).removeClass( 'admin-search-results-open' );

		// Wait until CSS transition is finished then empty results and remove preview
		setTimeout( function() {
			jQuery( '#admin-search-results' ).empty();

			admin_search_close_preview();
		}, admin_search_transition_speed );

	// If there aren't results, empty it in the following order
	} else {
		jQuery( '#admin-search-results-message' ).css( 'opacity', 0 );

		// Wait until CSS transition is finished then clear no results message
		setTimeout( function() {
			jQuery( '#admin-search-modal' ).removeClass( 'admin-search-results-open' );

			setTimeout( function() {
				jQuery( '#admin-search-results' ).empty();
			}, admin_search_transition_speed );
		}, admin_search_transition_speed );
	}

	// Empty active query
	admin_search_current_query = '';

	return true;
}


/*
 *	Register function to close result preview overlay
 */
function admin_search_close_preview() {
	// Ignore if the preview isn't open
	if ( ! admin_search_preview_open ) {
		return;
	}

	// Set initial state of the preview overlay
	jQuery( '#admin-search-result-preview-close button' ).css( 'transform', 'scale(0)' );
	jQuery( '#admin-search-result-preview-content' ).css( {
		transform : 'translateX(100%)',
		boxShadow : 'none'
	} );

	// Wait until CSS transition is finished then hide overlay and empty iframe and link
	setTimeout( function() {
		jQuery( '#admin-search-result-preview' ).hide();
		jQuery( '#admin-search-result-preview iframe' ).attr( 'src', 'about:blank' );
		jQuery( '#admin-search-result-preview a' ).attr( 'href', '#' );

		// Set global state to closed
		admin_search_preview_open = false;
	}, admin_search_transition_speed );

	return true;
}


/*
 *	Register function to toggle the admin-search
 */
function admin_search_modal( args ) {
	// Ignore if no arguments are supplied
	if ( typeof args !== 'object' ) {
		args = {};
	}


	// Check if open, then close, if closed, then open 
	if ( args.toggle && admin_search_open ) {
		args.close = true;
	}


	// Remove admin-search modal and reset page body state
	if ( args.close ) {
		jQuery( '#admin-search-modal' ).remove();
		jQuery( 'body' ).css( { overflow : '' } );
		jQuery( '#wp-admin-bar-admin-search-toggle' ).removeClass( 'hover' );


		// Set global state to closed and clear active search query
		admin_search_open = false;
		admin_search_current_query = '';


	// If admin-search is open, then set value and submit
	} else if ( args.value ) {
		if ( admin_search_open ) {
			jQuery( '#admin-search-input-field-value' ).text( args.value );
			admin_search_submit_form();
		}


	// If admin-search is closed, open it
	} else if ( ! admin_search_open ) {
		// If WP is not available, game over
		if ( ! ( wp && typeof wp.template == 'function' ) ) {
			return;
		}


		// Fetch the admin-search modal template, build it and append it to the DOM
		var admin_search_modal_template = wp.template( 'admin-search-modal-template' ),
			admin_search_modal = jQuery( admin_search_modal_template( {
				value : args.value
			} ) );

		admin_search_modal.appendTo( 'body' )


		// Set page body and initial admin-search state
		jQuery( 'body' ).css( { overflow : 'hidden' } );
		jQuery( '#admin-search-input-field-value' ).focus();


		// If value arg is supplied, submit the form
		if ( args.value ) {
			admin_search_submit_form();
		}


		// Set admin-search position based on last position, if it exists, and make it draggable
		var position = {
			left : ( jQuery( window ).width() - admin_search_modal.find( '#admin-search-container' ).width() ) / 2,
			top : jQuery( window ).height() / 4
		};

		if ( admin_search_x ) {
			position.left = admin_search_x / 100 * jQuery( window ).width();
		}

		if ( admin_search_y ) {
			position.top = admin_search_y / 100 * jQuery( window ).height();
		}

		admin_search_modal.find( '#admin-search-container' ).css( position );

		
		admin_search_modal.find( '#admin-search-container' ).draggable( {
			containment : 'parent',
			scroll : false,
			stop : function() {
				//When dragged, save the position in cookies
				admin_search_x = ( jQuery( this ).offset().left - jQuery( '#admin-search-modal' ).offset().left ) / jQuery( window ).width() * 100;
				admin_search_y = ( jQuery( this ).offset().top - jQuery( '#admin-search-modal' ).offset().top ) / jQuery( window ).height() * 100;

				AS_set_cookie( 'x', admin_search_x );
				AS_set_cookie( 'y', admin_search_y );
			}
		} );

		// Show state in #wpadminbar on small screens
		if ( jQuery( window ).width() <= 782 ) {
			jQuery( '#wp-admin-bar-admin-search-toggle' ).addClass( 'hover' );
		}

		// Set global state to open
		setTimeout( function() {
			admin_search_open = true;
		}, 1 );
	}

	return true;
}


jQuery( document ).ready( function( $ ) {
	var typingtimer;
	var autocompletetimer;


	// When clicking to close admin-search, ignore clicks within the modal
	$( document ).on( 'click', '#admin-search-modal', function( event ) {
		event.stopPropagation();
	} );


	// If clicking on anything other than a toggle (or the modal itself), close the admin-search modal
	$( document ).on( 'click', function( event ) {
		if ( ! $( event.target ).is( '#wp-admin-bar-admin-search-toggle, #wp-admin-bar-admin-search-toggle *' ) ) {
			if ( admin_search_open ) {
				admin_search_modal( {
					close : true
				} );
			}
		}
	} );


	// Close admin-search when clicking on the background layer
	$( document ).on( 'click', '#admin-search-modal-background', function( ) {
		admin_search_modal( {
			close : true
		} );
	} );


	// Set focus state when hovering over results with links
	$( document ).on( 'mouseenter', '.admin-search-result-has-link', function( ) {
		$( '.admin-search-result' ).removeClass( 'admin-search-result-focussed' );
		$( this ).addClass( 'admin-search-result-focussed' );
	} );


	// Listen for certain keypresses
	$( document ).on( 'keydown', function( event ) {
		var focusonindex, focuson;

		/* Disabling this for now as it conflicts with WordPress default functionality
		// If Ctrl + S or Cmd + S is pressed, toggle admin-search
		if ( ( event.ctrlKey || event.metaKey ) && event.keyCode === 83 ) {
			event.preventDefault();

			admin_search_modal( {
				toggle : true
			} );


		// If Esc is pressed...
		} else*/ if ( event.keyCode === 27 && admin_search_open ) {
			// ...close result preview overlay if it's open
			if ( admin_search_preview_open ) {
				admin_search_close_preview();


			// ...clear the admin-search if it's open and has value
			} else if ( admin_search_current_query != '' ) {
				admin_search_clear();


			// ...close admin-search if it's empty
			} else {
				admin_search_modal( {
					close : true
				} );
			}

			return;


		// If display_on_keypress is set to true and a keypress occurs outside an input or editable element, open admin-search
		} else if ( admin_search.display_on_keypress && ! admin_search_open && ! $( 'input:not([type="checkbox"]), input:not([type="radio"]), input:not([type="range"]), textarea, select' ).is( ':focus' ) && ! document.activeElement.isContentEditable ) {
			// Make sure it's an alphanumeric key
			if ( ( ( event.keyCode >= 65 && event.keyCode <= 90 ) || ( event.keyCode >= 48 && event.keyCode <= 57 ) ) && ! event.ctrlKey && ! event.metaKey ) {
				admin_search_modal();
			}


		// If down key is pressed alone and admin-search is open but the preview isn't, move focus to next result
		} else if ( event.keyCode === 40 && ! event.metaKey && ! event.ctrlKey && ! event.shiftKey && ! event.altKey && admin_search_open && ! admin_search_input_focussed && ! admin_search_preview_open ) {
			event.preventDefault();

			focusonindex = $( '.admin-search-result-focussed' ).index( '.admin-search-result-has-link' ) + 1;
			focuson = $( $( '.admin-search-result-has-link' ).get( focusonindex ) );

			// If result is last, then focus on the admin-search input
			if ( focusonindex > -1 && focuson.length ) {
				$( '.admin-search-result-has-link' ).removeClass( 'admin-search-result-focussed' );
				focuson.addClass( 'admin-search-result-focussed' );
				focuson.find( 'a' ).focus();
			} else {
				$( '#admin-search-input-field-value' ).AS_putCursorAtEnd();
			}


		// If up key is pressed alone and admin-search is open but the preview isn't, move focus to previous result
		} else if ( event.keyCode === 38 && ! event.metaKey && ! event.ctrlKey && ! event.shiftKey && ! event.altKey && admin_search_open && ! admin_search_input_focussed && ! admin_search_preview_open ) {
			event.preventDefault();

			focusonindex = $( '.admin-search-result-focussed' ).index( '.admin-search-result-has-link' ) - 1;
			focuson = $( $( '.admin-search-result-has-link' ).get( focusonindex ) );

			// If result is first, then focus on the admin-search input
			if ( focusonindex > -1 && focuson.length ) {
				$( '.admin-search-result-has-link' ).removeClass( 'admin-search-result-focussed' );
				focuson.addClass( 'admin-search-result-focussed' );
				focuson.find( 'a' ).focus();
			} else {
				$( '#admin-search-input-field-value' ).AS_putCursorAtEnd();
			}


		// If enter key is pressed and input is in focus, don't create a new line, perform a search!
		} else if ( event.keyCode === 13 && admin_search_open && admin_search_input_focussed ) {
			event.preventDefault();

			admin_search_submit_form();


		// If enter key is pressed and result is in focus, open the result
		} else if ( event.keyCode === 13 && admin_search_open && ! admin_search_input_focussed && $( '.admin-search-result-focussed' ).length ) {
			event.preventDefault();

			window.location.href = $( '.admin-search-result-focussed a' ).attr( 'href' );


		// If right arrow key is pressed and there is a suggestion, apply it
		} else if ( event.keyCode === 39 && admin_search_open && admin_search_input_focussed && admin_search_has_suggestion ) {
			$( '#admin-search-input-field-value' ).text( admin_search_suggestion ).AS_putCursorAtEnd();


		// If right arrow key is pressed and admin-search is open but the preview isn't, open the preview of the focussed result
		} else if ( event.keyCode === 39 && admin_search_open && ! admin_search_input_focussed && ! admin_search_preview_open ) {
			$( '.admin-search-result-focussed .admin-search-result-preview-toggle' ).trigger( 'click' );


		// If tab is pressed and focus is moved to the anchor in a result, set the result status to focussed
		} else if ( event.keyCode === 9 && admin_search_open ) {
			setTimeout( function () {
				if ( $( '.admin-search-result-has-link a' ).is( ':focus' ) ) {
					focuson = $( '.admin-search-result-has-link a:focus' ).parent();

					$( '.admin-search-result-has-link' ).removeClass( 'admin-search-result-focussed' );
					focuson.addClass( 'admin-search-result-focussed' );
				}
			}, 1 );
		}
	});


	$( document ).on( 'mouseenter', '#admin-search-input-field-value', function( ) {
		$( '#admin-search-container' ).draggable( 'disable' );
	} );


	$( document ).on( 'mouseleave', '#admin-search-input-field-value', function( ) {
		$( '#admin-search-container' ).draggable( 'enable' );
	} );


	$( document ).on( 'click', '#admin-search-input-field', function( event ) {
		if ( ! $( event.target ).is( '#admin-search-input-field-value' ) ) {
			$( '#admin-search-input-field-value' ).AS_putCursorAtEnd();
		}
	} );


	$( document ).on( 'focus', '#admin-search-input-field-value', function( ) {
		admin_search_input_focussed = true;

		$( '#admin-search-modal' ).addClass( 'admin-search-input-is-focussed' );

		if ( admin_search_has_suggestion ) {
			$( '#admin-search-input-field-autocomplete-suggestion' ).css( 'opacity', 1 );
		}
	} );


	$( document ).on( 'blur', '#admin-search-input-field-value', function( ) {
		admin_search_input_focussed = false;

		clearInterval( autocompletetimer );

		$( '#admin-search-modal' ).removeClass( 'admin-search-input-is-focussed' );
		$( '#admin-search-input-field-autocomplete-suggestion' ).css( 'opacity', 0 );
	} );


	$( document ).on( 'keydown keyup', '#admin-search-input-field-value', function( ) {
		$( '#admin-search-modal' ).addClass( 'admin-search-has-value' );

		if ( $( '#admin-search-input-field-value' ).text() == '' ) {
			admin_search_clear();
		}
	} );

	$( document ).on( 'keydown', '#admin-search-input-field-value', function( ) {
		if ( admin_search.show_suggestions ) {
			autocompletetimer = setInterval( function() {
				admin_search_has_suggestion = false;

				var value = $( '#admin-search-input-field-value' ).text();
				var trimmed_suggestion = '';

				if ( value ) {
					if ( value.length > 3 ) {
						$.each( admin_search.top_searches, function() {
							if ( this.startsWith( value.toLowerCase().trim() ) ) {
								admin_search_suggestion = this;
								trimmed_suggestion = admin_search_suggestion.slice( value.length )

								if ( trimmed_suggestion.trim() ) {
									admin_search_has_suggestion = true;
								}

								return false;
							}
						} );
					}
				}

				if ( admin_search_has_suggestion ) {
					$( '#admin-search-input-field-autocomplete-suggestion' ).text( trimmed_suggestion );
					$( '#admin-search-input-field-autocomplete' ).css( 'opacity', 1 );
				} else {
					$( '#admin-search-input-field-autocomplete' ).css( 'opacity', 0 );
					$( '#admin-search-input-field-autocomplete-suggestion' ).text( '' );
				}
			}, 0 );
		}
	} );


	// Wait for keyup to determine if admin-search has a value and if it has no value, clear the results. Whenever keyup, restart the typing timer (which delays submission until typing is complete)
	$( document ).on( 'keyup', '#admin-search-input-field-value', function( ) {
		clearInterval( autocompletetimer );

		if ( admin_search.autosearch && admin_search_open ) {
			var value = $( this ).text();

			if ( value ) {
				clearTimeout( typingtimer );

				typingtimer = setTimeout( admin_search_submit_form, 750 );
			} else {
				admin_search_clear();
			}
		}
	});


	// Whenever keydown, end the typing timer
	$( document ).on( 'keydown', '#admin-search-input-field-value', function( ) {
		if ( $( this ).text() ) {
			clearTimeout( typingtimer );
		}
	});


	// Clear results when the clear button is clicked
	$( document ).on( 'click', '#admin-search-clear-button button', admin_search_clear );


	// When paginating, submit the form and add the results to the group
	$( document ).on( 'submit', '.admin-search-result-group-pagination-form', function( event ) {
		event.preventDefault();

		var q = $( '#admin-search-input-field-value' ).text(),
			source = $( this ).find( '[name="source"]' ).val(),
			paged = $( this ).find( '[name="page"]' ).val();

		var results = $( '.admin-search-result-group.admin-search-' + source + '-post-type' ),
			pagination = results.find( '.admin-search-result-group-pagination' );

		pagination.addClass( 'admin-search-results-loading' );
		pagination.find( 'button[type="submit"]' ).prop( 'disabled', true );


        // Perform HTTP GET request to get paginated results
		$.get( admin_search.ajax_url, {
			action : 'admin_search_ajax',
			_ajax_nonce : admin_search.nonce,
			q : q,
			source : source,
			paged : paged
		}, function( response ) {
            // If there's an error, add it to console and look no further
			if ( response.error ) {
				console.log( response );

				return false;
			}


			// Remove current pagination action
			pagination.remove();

			var admin_search_result_template = wp.template( 'admin-search-result-template' ),
				admin_search_result_group_pagination_template = wp.template( 'admin-search-results-group-pagination-template' );


			// Loop through response and add them to results
			$.each( response.results, function() {
				$.each( this.posts, function() {
					this.target = '_self';

					// If the result link is external, make sure it opens in a new tab
					if ( jQuery( '<a>', {
						href : this.edit_post_link
					} ).get( 0 ).hostname != location.hostname ) {
						this.target = '_blank';
					}

					$( admin_search_result_template( this ) ).appendTo( results );
				} );

				// If there is another page of results, add new pagination
				if ( this.next_page ) {
					$( admin_search_result_group_pagination_template( this ) ).appendTo( results );
				}
			} );
		} );
	} );


	// When clicking to close the preview overlay, ignore clicks within the preview content
	$( document ).on( 'click', '#admin-search-result-preview-content', function( event ) {
		event.stopPropagation();
	} );


	// When clicking outside the preview overlay but within the admin-search, or the close button, close the preview
	$( document ).on( 'click', '#admin-search-result-preview, #admin-search-result-preview-close button', admin_search_close_preview );


	// Show preview when clicking the preview button in a result
	$( document ).on( 'click', '.admin-search-result-preview-toggle', function( event ) {
		var result = $( this ).parents( '.admin-search-result' );


		// If admin-search is open, ignore this function
		if ( admin_search_preview_open ) {
			return false;
		}


		// If result doesn't have a preview link, ignore this function
		if ( ! result.data( 'preview' ) ) {
			return false;
		}


		// If the window is small, ignore this function
		if ( $( window ).width() <= 783 ) {
			return false;
		}


		// Set the globel preview state to open
		admin_search_preview_open = true;

		$( '#admin-search-result-preview iframe' ).attr( 'src', result.data( 'preview' ) );
		$( '#admin-search-result-preview a' ).attr( 'href', result.data( 'edit' ) );
		$( '#admin-search-result-preview' ).addClass( 'admin-search-result-preview-loading' ).show();


		// Wait until DOM is inserted then set initial state
		setTimeout( function() {
			$( '#admin-search-result-preview-content' ).css( {
				transform : 'translateX(0)',
				boxShadow : '-16px 0 16px rgb(0 0 0 / 32%)'
			} );


			// Wait until CSS transition is complete then display close button
			setTimeout( function() {
				$( '#admin-search-result-preview-close button' ).css( 'transform', 'scale(1)' );
			}, admin_search_transition_speed );
		}, 1 );
	} );


	// Check if preview is loaded then display it
	window.addEventListener( 'message', ( event ) => {
		if ( event.origin !== admin_search.site_url ) {
			return;
		}

		if ( event.data.display && admin_search_preview_open ) {
			$( '#admin-search-result-preview' ).removeClass( 'admin-search-result-preview-loading' );
		}
	}, false );


	// When an anchor with #admin-search at the beginning is clicked, show the admin-menu with the value in the attribute
	$( document ).on( 'click', 'a[href^="#admin-search="]', function( event ) {
		event.preventDefault();

		admin_search_modal( {
			value : decodeURI( $( this ).attr( 'href' ).replace( '#admin-search=', '' ) )
		} );
	} );


	// Don't paste with style
	$( document ).on( 'paste', '#admin-search-input-field-value', function( event ) {
		event.preventDefault();

		const text = ( event.originalEvent.clipboardData || window.clipboardData ).getData( 'text' );

		document.execCommand( 'insertText', false, text.trim() );
	} );
});
