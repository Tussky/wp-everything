<?php
/*
 *	Format date strings for WP_Query argument
 */
function admin_search_convert_date_str(  $str = ''  ) {

	$r = array();
	$days = array(
		__( 'Monday', 'admin-search' ),
		__( 'Tuesday', 'admin-search' ),
		__( 'Wednesday', 'admin-search' ),
		__( 'Thursday', 'admin-search' ),
		__( 'Friday', 'admin-search' ),
		__( 'Saturday', 'admin-search' ),
		__( 'Sunday', 'admin-search' )
	);
	$months = array(
		__( 'January', 'admin-search' ),
		__( 'February', 'admin-search' ),
		__( 'March', 'admin-search' ),
		__( 'April', 'admin-search' ),
		__( 'May', 'admin-search' ),
		__( 'June', 'admin-search' ),
		__( 'July', 'admin-search' ),
		__( 'August', 'admin-search' ),
		__( 'September', 'admin-search' ),
		__( 'October', 'admin-search' ),
		__( 'November', 'admin-search' ),
		__( 'December', 'admin-search' )
	);

	switch ( $str ) {
		// 4 digit year
		case is_numeric( $str ) && strlen( $str ) == 4 :
			$date = strtotime( $str . '-01-01' );
			$r[ 'year' ] = gmdate( 'Y', $date );
			break;

		// Year ago string
		case in_array( $str, array(
			__( 'last year', 'admin-search' ),
			__( 'a year ago', 'admin-search' ),
			__( 'one year ago', 'admin-search' )
		) ) :
			$date = strtotime( 'last year' );
			$r[ 'year' ] = gmdate( 'Y', $date );
			break;

		// Month ago string
		case in_array( $str, array(
			__( 'last month', 'admin-search' ),
			__( 'a month ago', 'admin-search' ),
			__( 'one month ago', 'admin-search' )
		) ) :
			$date = strtotime( 'last month' );
			$r[ 'year' ] = gmdate( 'Y', $date );
			$r[ 'month' ] = gmdate( 'm', $date );
			break;

		// Day as string
		case strtolower( gmdate( 'l' ) ) == $str :
			$date = strtotime( 'NOW' );
			$r[ 'year' ] = gmdate( 'Y', $date );
			$r[ 'month' ] = gmdate( 'm', $date );
			$r[ 'day' ] = gmdate( 'd', $date );
			break;

		// Last day as string
		case in_array( ucfirst( $str ), $days ) :
			$date = strtotime( 'last ' . $str );
			$r[ 'year' ] = gmdate( 'Y', $date );
			$r[ 'month' ] = gmdate( 'm', $date );
			$r[ 'day' ] = gmdate( 'd', $date );
			break;

		// Month as string
		case in_array( ucfirst( $str ), $months ) :
			if ( gmdate( 'm', strtotime( $str . ' ' . gmdate( 'Y' ) ) ) > gmdate( 'm' ) ) {
				$date = strtotime( $str . ' ' . gmdate( 'Y', strtotime( '-1 year' ) ) );
			} else {
				$date = strtotime( $str . ' ' . gmdate( 'Y' ) );
			}

			$r[ 'year' ] = gmdate( 'Y', $date );
			$r[ 'month' ] = gmdate( 'm', $date );
			break;

		// Today / now
		case in_array( $str, array(
			__( 'today', 'admin-search' ),
			__( 'now', 'admin-search' )
		) ) :
			$date = strtotime( 'today' );
			$r[ 'year' ] = gmdate( 'Y', $date );
			$r[ 'month' ] = gmdate( 'm', $date );
			$r[ 'day' ] = gmdate( 'd', $date );
			break;

		// Yesterday
		case $str === __( 'yesterday', 'admin-search' ) :
			$date = strtotime( 'yesterday' );
			$r[ 'year' ] = gmdate( 'Y', $date );
			$r[ 'month' ] = gmdate( 'm', $date );
			$r[ 'day' ] = gmdate( 'd', $date );
			break;

		// This week (resolves to Monday of the current week)
		case $str === __( 'this week', 'admin-search' ) :
			$date = strtotime( 'monday this week' );
			$r[ 'year' ] = gmdate( 'Y', $date );
			$r[ 'month' ] = gmdate( 'm', $date );
			$r[ 'day' ] = gmdate( 'd', $date );
			break;

		// Last week
		case in_array( $str, array(
			__( 'last week', 'admin-search' ),
			__( 'a week ago', 'admin-search' ),
			__( 'one week ago', 'admin-search' )
		) ) :
			$date = strtotime( 'monday last week' );
			$r[ 'year' ] = gmdate( 'Y', $date );
			$r[ 'month' ] = gmdate( 'm', $date );
			$r[ 'day' ] = gmdate( 'd', $date );
			break;

		// This month
		case in_array( $str, array(
			__( 'this month', 'admin-search' ),
			__( 'current month', 'admin-search' )
		) ) :
			$r[ 'year' ] = gmdate( 'Y' );
			$r[ 'month' ] = gmdate( 'm' );
			break;

		// Next month
		case $str === __( 'next month', 'admin-search' ) :
			$date = strtotime( 'first day of next month' );
			$r[ 'year' ] = gmdate( 'Y', $date );
			$r[ 'month' ] = gmdate( 'm', $date );
			break;

		// This year
		case in_array( $str, array(
			__( 'this year', 'admin-search' ),
			__( 'current year', 'admin-search' )
		) ) :
			$r[ 'year' ] = gmdate( 'Y' );
			break;

		// Next year
		case $str === __( 'next year', 'admin-search' ) :
			$r[ 'year' ] = gmdate( 'Y', strtotime( '+1 year' ) );
			break;

		// Any other native format
		default :
			$date = strtotime( $str );
			if ( false === $date ) {
				// Unrecognised date string — return empty array so callers can detect the failure
				return $r;
			}
			$r[ 'year' ] = gmdate( 'Y', $date );
			$r[ 'month' ] = gmdate( 'm', $date );
			$r[ 'day' ] = gmdate( 'd', $date );
	}

	return $r;

}


/*
 *	Detect a post type prefix at the start of a search query (e.g. "products since 2023")
 *	and return the matched post type slug and the remainder of the query. Returns NULL if
 *	no recognised post type is found at the start of the query.
 */
function admin_search_detect_post_type_prefix( $q, $available_sources ) {

	foreach ( get_post_types( array(), 'objects' ) as $post_type ) {

		// Only consider post types that are actually in the configured sources
		if ( ! in_array( $post_type -> name, $available_sources ) ) {
			continue;
		}

		// Build a list of identifiers to check: slug, plural label, singular label
		$identifiers = array_unique( array_filter( array(
			mb_strtolower( $post_type -> name ),
			mb_strtolower( $post_type -> label ),
			mb_strtolower( $post_type -> labels -> singular_name ?? '' ),
		) ) );

		foreach ( $identifiers as $identifier ) {
			if ( empty( $identifier ) ) {
				continue;
			}

			// Check if query starts with this identifier followed by a space
			if ( mb_strpos( $q, $identifier . ' ' ) === 0 ) {
				$remainder = trim( mb_substr( $q, mb_strlen( $identifier ) ) );

				// Only apply if the remainder is a usable query (2+ chars)
				if ( mb_strlen( $remainder ) >= 2 ) {
					return array(
						'post_type' => $post_type -> name,
						'q'         => $remainder,
					);
				}
			}
		}
	}

	return NULL;

}


/*
 *	Parse a date expression string (e.g. "since 2023", "between 2020 and 2022",
 *	"before last year") and return an array with three keys:
 *		date       – array from admin_search_convert_date_str() for the start/only date
 *		date_end   – array for the end date (two-date ranges only), else NULL
 *		date_range – 'on' | 'before' | 'after' | 'between'
 *
 *	Returns NULL when the string does not match any known date expression pattern.
 */
function admin_search_parse_date_expression( $expr ) {

	$date       = NULL;
	$date_end   = NULL;
	$date_range = 'on';
	$column     = 'post_date'; // overridden to 'post_modified' for updated/edited queries

	// Detect and strip a "modified / updated / edited" prefix so that date expressions
	// like "updated between 2020 and 2022" or "edited in the last 7 days" target the
	// post_modified column instead of post_date.  Longer prefixes are checked first.
	foreach ( array(
		__( 'last updated', 'admin-search' ),
		__( 'last edited',  'admin-search' ),
		__( 'last modified','admin-search' ),
		__( 'updated',      'admin-search' ),
		__( 'edited',       'admin-search' ),
		__( 'modified',     'admin-search' ),
	) as $pfx ) {
		if ( preg_match( '/^' . preg_quote( $pfx, '/' ) . '\s+(.+)$/i', $expr, $m ) ) {
			$column = 'post_modified';
			$expr   = trim( $m[1] );
			break;
		}
	}

	// Relative time windows: "in the last N days/weeks/months/years",
	// "last N days", "past N weeks", "past month", etc.
	// These always produce an 'after' range ("show everything since N units ago").

	// Translatable time-unit forms mapped to English equivalents for PHP date arithmetic.
	// Translators provide the singular and plural of each unit in their language; the
	// English key ('day', 'week', 'month', 'year') is used only for strtotime().
	$_time_unit_map = array(
		/* translators: singular and plural of the time unit "day" used in relative-date search queries */
		'day'   => array( __( 'day',   'admin-search' ), __( 'days',   'admin-search' ) ),
		/* translators: singular and plural of "week" */
		'week'  => array( __( 'week',  'admin-search' ), __( 'weeks',  'admin-search' ) ),
		/* translators: singular and plural of "month" */
		'month' => array( __( 'month', 'admin-search' ), __( 'months', 'admin-search' ) ),
		/* translators: singular and plural of "year" */
		'year'  => array( __( 'year',  'admin-search' ), __( 'years',  'admin-search' ) ),
	);

	// Build a regex alternation from all translated unit forms (longest-first to avoid sub-matches)
	$_unit_re_parts = array();
	foreach ( $_time_unit_map as $_en_unit => $_forms ) {
		foreach ( $_forms as $_form ) {
			$_unit_re_parts[] = preg_quote( mb_strtolower( $_form ), '/' );
		}
	}
	usort( $_unit_re_parts, function ( $a, $b ) { return strlen( $b ) - strlen( $a ); } );
	$_unit_re = implode( '|', array_unique( $_unit_re_parts ) );

	// Build a regex alternation from translated relative-window prefix phrases
	$_rel_prefixes = array_unique( array(
		/* translators: "in the last" as in "in the last 3 days" */
		__( 'in the last', 'admin-search' ),
		/* translators: "last" as in "last 3 days" */
		__( 'last',        'admin-search' ),
		/* translators: "past" as in "past 3 days" */
		__( 'past',        'admin-search' ),
	) );
	usort( $_rel_prefixes, function ( $a, $b ) { return strlen( $b ) - strlen( $a ); } );
	$_pfx_re = implode( '|', array_map( function ( $p ) { return preg_quote( $p, '/' ); }, $_rel_prefixes ) );

	$rel_n    = NULL;
	$rel_unit = NULL; // English unit name for strtotime()

	// "in the last 3 days", "last 2 weeks", "past 6 months"
	if ( preg_match( '/^(?:' . $_pfx_re . ')\s+(\d+)\s+(' . $_unit_re . ')$/i', $expr, $m ) ) {
		$rel_n       = (int) $m[1];
		$_matched    = mb_strtolower( $m[2] );
		foreach ( $_time_unit_map as $_en_unit => $_forms ) {
			if ( in_array( $_matched, array_map( 'mb_strtolower', $_forms ) ) ) {
				$rel_unit = $_en_unit;
				break;
			}
		}

	// "last week", "past month" — N=1 implied
	} elseif ( preg_match( '/^(?:' . $_pfx_re . ')\s+(' . $_unit_re . ')$/i', $expr, $m ) ) {
		$rel_n       = 1;
		$_matched    = mb_strtolower( $m[1] );
		foreach ( $_time_unit_map as $_en_unit => $_forms ) {
			if ( in_array( $_matched, array_map( 'mb_strtolower', $_forms ) ) ) {
				$rel_unit = $_en_unit;
				break;
			}
		}
	}

	if ( $rel_n !== NULL && $rel_unit !== NULL ) {
		// Use the locale-independent PHP relative format "-N unit" so that strtotime()
		// always receives an English instruction regardless of WordPress locale.
		$d = admin_search_convert_date_str( '-' . $rel_n . ' ' . $rel_unit );
		if ( ! empty( $d ) ) {
			$date       = $d;
			$date_range = 'after';
		}
	}

	// Two-date range patterns checked first so "from X to Y" is not mis-parsed
	// by the single-date "from X" pattern below.
	if ( $date !== NULL ) {
		// Already resolved by the relative-window block above — skip the remaining patterns.
		goto date_expr_done;
	}
	foreach ( array(
		/* translators: %date1%/%date2%: any date format */
		__( 'between %date1% and %date2%', 'admin-search' ),
		/* translators: %date1%/%date2%: any date format */
		__( 'from %date1% to %date2%', 'admin-search' ),
		/* translators: %date1%/%date2%: any date format */
		__( 'from %date1% until %date2%', 'admin-search' ),
		/* translators: %date1%/%date2%: any date format */
		__( '%date1% to %date2%', 'admin-search' ),
	) as $between_q ) {
		$pattern = '/^' . str_replace( array( '%date1%', '%date2%' ), array( '(.+?)', '(.+)' ), $between_q ) . '$/';
		if ( preg_match( $pattern, $expr, $match ) ) {
			$d1 = admin_search_convert_date_str( trim( $match[1] ) );
			$d2 = admin_search_convert_date_str( trim( $match[2] ) );
			if ( ! empty( $d1 ) && ! empty( $d2 ) ) {
				$date       = $d1;
				$date_end   = $d2;
				$date_range = 'between';
				break;
			}
		}
	}

	// Dates before a given date
	if ( $date === NULL ) {
		foreach ( array(
			/* translators: %date%: any date format */
			__( 'posted before %date%', 'admin-search' ),
			/* translators: %date%: any date format */
			__( 'published before %date%', 'admin-search' ),
			/* translators: %date%: any date format */
			__( 'before %date%', 'admin-search' ),
			/* translators: %date%: any date format */
			__( 'until %date%', 'admin-search' ),
			/* translators: %date%: any date format */
			__( 'up to %date%', 'admin-search' ),
		) as $date_q ) {
			if ( preg_match( '/^' . str_replace( '%date%', '(.+)', $date_q ) . '$/', $expr, $match ) ) {
				$date_candidate = admin_search_convert_date_str( trim( $match[1] ) );
				if ( ! empty( $date_candidate ) ) {
					$date       = $date_candidate;
					$date_range = 'before';
					break;
				}
			}
		}
	}

	// Dates after a given date
	if ( $date === NULL ) {
		foreach ( array(
			/* translators: %date%: any date format */
			__( 'posted after %date%', 'admin-search' ),
			/* translators: %date%: any date format */
			__( 'published after %date%', 'admin-search' ),
			/* translators: %date%: any date format */
			__( 'since %date%', 'admin-search' ),
			/* translators: %date%: any date format */
			__( 'after %date%', 'admin-search' ),
			/* translators: %date%: any date format */
			__( 'from %date%', 'admin-search' ),
		) as $date_q ) {
			if ( preg_match( '/^' . str_replace( '%date%', '(.+)', $date_q ) . '$/', $expr, $match ) ) {
				$date_candidate = admin_search_convert_date_str( trim( $match[1] ) );
				if ( ! empty( $date_candidate ) ) {
					$date       = $date_candidate;
					$date_range = 'after';
					break;
				}
			}
		}
	}

	// Exact date / month / year
	if ( $date === NULL ) {
		foreach ( array(
			/* translators: %date%: any date format */
			__( 'posted on %date%', 'admin-search' ),
			/* translators: %date%: any date format */
			__( 'posted in %date%', 'admin-search' ),
			/* translators: %date%: any date format */
			__( 'posted %date%', 'admin-search' ),
			/* translators: %date%: any date format */
			__( 'published on %date%', 'admin-search' ),
			/* translators: %date%: any date format */
			__( 'published in %date%', 'admin-search' ),
			/* translators: %date%: any date format */
			__( 'published %date%', 'admin-search' ),
		) as $date_q ) {
			if ( preg_match( '/^' . str_replace( '%date%', '(.+)', $date_q ) . '$/', $expr, $match ) ) {
				$date_candidate = admin_search_convert_date_str( trim( $match[1] ) );
				if ( ! empty( $date_candidate ) ) {
					$date = $date_candidate;
					break;
				}
			}
		}
	}

	date_expr_done:

	if ( $date === NULL ) {
		return NULL;
	}

	return array(
		'date'       => $date,
		'date_end'   => $date_end,
		'date_range' => $date_range,
		'column'     => $column,
	);

}


/*
 *	Parse a query string for natural language taxonomy constraints such as
 *	"in review category", "tagged sci-fi", "categorized as tutorial", or compound
 *	expressions like "in review category and tagged sci-fi".
 *
 *	Returns an array with:
 *		tax_queries – list of ['taxonomy' => 'slug', 'term' => 'term name']
 *		remainder   – query string with all matched taxonomy clauses stripped out
 *
 *	Returns NULL when no taxonomy patterns are detected.
 */
function admin_search_parse_taxonomy_expression( $q ) {

	$tax_queries = array();
	$working     = $q;

	// Translatable tag templates — %term% marks where the tag name appears.
	$_tag_templates = array(
		/* translators: %term%: tag name */
		__( 'tagged with %term%', 'admin-search' ),
		/* translators: %term%: tag name */
		__( 'with tags %term%', 'admin-search' ),
		/* translators: %term%: tag name */
		__( 'with tag %term%', 'admin-search' ),
		/* translators: %term%: tag name */
		__( 'tagged %term%', 'admin-search' ),
	);

	// Translatable category templates.  Where %term% comes before the keyword
	// (e.g. "in the %term% category") the natural end of the template already
	// terminates the capture, so no $stop lookahead is needed there.
	$_cat_templates = array(
		/* translators: %term%: category name, placed before the word "category" */
		__( 'in the %term% category', 'admin-search' ),
		/* translators: %term%: category name, placed before the word "category" */
		__( 'in %term% category', 'admin-search' ),
		/* translators: %term%: category name, placed after the word "category" */
		__( 'in category %term%', 'admin-search' ),
		/* translators: %term%: category name */
		__( 'categorized as %term%', 'admin-search' ),
		/* translators: %term%: category name */
		__( 'categorised as %term%', 'admin-search' ),
		/* translators: %term%: category name */
		__( 'categorized under %term%', 'admin-search' ),
		/* translators: %term%: category name */
		__( 'categorised under %term%', 'admin-search' ),
	);

	// Translated "and" / "or" connectors for $stop and $strip.
	$_and_word = __( 'and', 'admin-search' );
	$_or_word  = __( 'or',  'admin-search' );
	$_and_re   = preg_quote( $_and_word, '/' );
	$_or_re    = preg_quote( $_or_word,  '/' );

	// Build $stop lookahead from the first word of every taxonomy template so that
	// non-greedy term captures stop before the next compound taxonomy clause.
	$_stop_words = array();
	foreach ( array_merge( $_tag_templates, $_cat_templates ) as $_tpl ) {
		$_tpl_lc = mb_strtolower( $_tpl );
		// Take the first word of the (translated) template, strip any %term% chars
		if ( preg_match( '/^(\S+)/', str_replace( '%term%', '', $_tpl_lc ), $_fw ) && strlen( $_fw[1] ) >= 2 ) {
			$_stop_words[] = preg_quote( trim( $_fw[1] ), '/' );
		}
	}
	$_stop_words = array_unique( $_stop_words );
	$stop = '(?=\s+' . $_and_re . '\s+(?:' . implode( '|', $_stop_words ) . ')|$)';

	// Helper: strip a matched fragment and clean up orphaned conjunctions / whitespace.
	$strip = function ( $str, $fragment ) use ( $_and_re, $_or_re ) {
		$str = trim( str_replace( $fragment, ' ', $str ) );
		$str = preg_replace( '/^\s*(?:' . $_and_re . '|' . $_or_re . ')\s+/i', '', $str );
		$str = preg_replace( '/\s+(?:' . $_and_re . '|' . $_or_re . ')\s*$/i', '', $str );
		return trim( preg_replace( '/\s{2,}/', ' ', $str ) );
	};

	// Helper: split a captured term string into individual terms.
	// Supports comma-separated lists and "and"-joined lists (translated), including
	// the Oxford-comma form "comedy, romance, and sci-fi".
	// e.g.  "comedy and romance"          → ["comedy", "romance"]
	//       "comedy, romance and sci-fi"  → ["comedy", "romance", "sci-fi"]
	//       "comedy"                       → ["comedy"]
	$split_terms = function ( $term_str ) use ( $_and_re ) {
		$parts = preg_split(
			'/\s*,\s*(?:' . $_and_re . '\s+)?|\s+' . $_and_re . '\s+/i',
			$term_str
		);
		return array_values( array_filter( array_map( 'trim', $parts ) ) );
	};

	// Helper: build a regex pattern from a taxonomy template.
	// When %term% is at the very end of the template the $stop lookahead is appended
	// so that the non-greedy capture terminates before the next compound clause.
	// When %term% appears mid-template (before another word) the surrounding text
	// naturally terminates the capture, so no lookahead is needed.
	$make_tax_pattern = function ( $tpl, $stop, $anchor_start = false ) {
		$term_is_last = substr( $tpl, -7 ) === '%term%';
		$term_regex   = $term_is_last ? '(.+?)' . $stop : '(.+?)';
		$pat = str_replace( '%term%', $term_regex, preg_quote( $tpl, '/' ) );
		return '/' . ( $anchor_start ? '^' : '' ) . $pat . '/i';
	};

	// --- post_tag patterns ---
	// First pass: anchored to start of string (fast path for simple tag queries).
	$_tag_found = false;
	foreach ( $_tag_templates as $_tag_tpl ) {
		if ( preg_match( $make_tax_pattern( $_tag_tpl, $stop, true ), $working, $m ) ) {
			$term = trim( $m[1] );
			if ( $term ) {
				// Split "comedy and romance" / "comedy, romance and sci-fi" into individual terms.
				foreach ( $split_terms( $term ) as $_t ) {
					$tax_queries[] = array( 'taxonomy' => 'post_tag', 'term' => $_t );
				}
				$working = $strip( $working, $m[0] );
			}
			$_tag_found = true;
			break;
		}
	}
	// Second pass: match anywhere in the string — handles compound queries such as
	// "in review category and tagged sci-fi" where the tag clause is not first.
	if ( ! $_tag_found ) {
		foreach ( $_tag_templates as $_tag_tpl ) {
			if ( preg_match( $make_tax_pattern( $_tag_tpl, $stop, false ), $working, $m ) ) {
				$term = trim( $m[1] );
				if ( $term ) {
					foreach ( $split_terms( $term ) as $_t ) {
						$tax_queries[] = array( 'taxonomy' => 'post_tag', 'term' => $_t );
					}
					$working = $strip( $working, $m[0] );
				}
				break;
			}
		}
	}

	// --- category patterns ---
	// Match anywhere in the string (category clause may appear anywhere in query).
	foreach ( $_cat_templates as $_cat_tpl ) {
		if ( preg_match( $make_tax_pattern( $_cat_tpl, $stop, false ), $working, $m ) ) {
			$term = trim( $m[1] );
			if ( $term ) {
				foreach ( $split_terms( $term ) as $_t ) {
					$tax_queries[] = array( 'taxonomy' => 'category', 'term' => $_t );
				}
				$working = $strip( $working, $m[0] );
			}
			break;
		}
	}

	// --- dynamic taxonomy patterns: match "in [term] [taxonomy label]" for custom taxonomies ---
	foreach ( get_taxonomies( array(), 'objects' ) as $tax ) {
		// Skip non-public taxonomies and the built-ins already handled above
		if ( ( ! $tax->public ) && ( ! isset( $tax->show_ui ) || ! $tax->show_ui ) ) {
			continue;
		}
		if ( in_array( $tax->name, array( 'category', 'post_tag' ) ) ) {
			continue;
		}

		$labels = array_unique( array_filter( array(
			mb_strtolower( $tax->label ),
			isset( $tax->labels->singular_name ) ? mb_strtolower( $tax->labels->singular_name ) : '',
		) ) );

		foreach ( $labels as $lbl ) {
			if ( strlen( $lbl ) < 2 ) {
				continue;
			}
			$lbl_re = preg_quote( $lbl, '/' );
			if ( preg_match( '/\bin\s+(?:the\s+)?(.+?)\s+' . $lbl_re . '\b/i', $working, $m ) ) {
				$term = trim( $m[1] );
				if ( $term ) {
					foreach ( $split_terms( $term ) as $_t ) {
						$tax_queries[] = array( 'taxonomy' => $tax->name, 'term' => $_t );
					}
					$working = $strip( $working, $m[0] );
					break 2; // one custom taxonomy match per call
				}
			}
		}
	}

	if ( empty( $tax_queries ) ) {
		return NULL;
	}

	return array(
		'tax_queries' => $tax_queries,
		'remainder'   => $working,
	);

}


/*
 *	Parse a query string for natural language meta field expressions such as:
 *		"posts with no value in price"
 *		"products where rating > 4"
 *		"articles where status is not empty"
 *		"events where date is between 2024-01-01 and 2024-12-31"
 *
 *	Returns an array with:
 *		meta_query – a WP_Query-compatible meta_query clause (array)
 *		remainder  – query string with the matched expression removed
 *
 *	Returns NULL when no meta expression is detected.
 *
 *	Meta field names are validated against the WordPress meta key format
 *	([a-zA-Z0-9_-]) to prevent false positives on normal keyword queries.
 */
function admin_search_parse_meta_expression( $q ) {

	// Only alphanumeric, underscore, and hyphen are valid WordPress meta key characters.
	$is_valid_key = function ( $key ) {
		return (bool) preg_match( '/^[a-zA-Z0-9_\-]+$/', trim( $key ) );
	};

	// ── Helpers: build WP_Query meta_query clause arrays ─────────────────────

	// "no value" — meta row absent OR value is an empty string
	$mq_empty = function ( $key ) {
		return array(
			'relation' => 'OR',
			array( 'key' => $key, 'compare' => 'NOT EXISTS' ),
			array( 'key' => $key, 'value'   => '', 'compare' => '=' ),
		);
	};

	// "has a value" — meta row exists and value is not an empty string
	$mq_not_empty = function ( $key ) {
		return array(
			'relation' => 'AND',
			array( 'key' => $key, 'compare' => 'EXISTS' ),
			array( 'key' => $key, 'value'   => '', 'compare' => '!=' ),
		);
	};

	// "is set" — meta row exists (value may be empty)
	$mq_exists = function ( $key ) {
		return array( array( 'key' => $key, 'compare' => 'EXISTS' ) );
	};

	// "is not set" — meta row absent entirely
	$mq_not_exists = function ( $key ) {
		return array( array( 'key' => $key, 'compare' => 'NOT EXISTS' ) );
	};


	// ── Stage 1: existence / emptiness patterns ───────────────────────────────
	//
	// Templates use %field% as the placeholder for the meta key name.
	// Two sub-groups:
	//   a) Field-at-end — %field% is the last token; the pre-expression text
	//      (the keyword part of the query) is captured in match[1].
	//   b) Field-in-middle — %field% is followed by a qualifying phrase; the
	//      strict character class [a-zA-Z0-9_\-]+ is used so that only valid
	//      WordPress meta key names match (no $is_valid_key() call needed).

	// Stage 1a — field at end of expression
	$_meta_end_templates = array(
		/* translators: %field%: WordPress meta field name */
		array( __( 'with no value in %field%',     'admin-search' ), 'empty'     ),
		/* translators: %field%: WordPress meta field name */
		array( __( 'without a value in %field%',   'admin-search' ), 'empty'     ),
		/* translators: %field%: WordPress meta field name */
		array( __( 'without any value in %field%', 'admin-search' ), 'empty'     ),
		/* translators: %field%: WordPress meta field name */
		array( __( 'with a value in %field%',      'admin-search' ), 'not_empty' ),
		/* translators: %field%: WordPress meta field name */
		array( __( 'with any value in %field%',    'admin-search' ), 'not_empty' ),
	);

	// Stage 1b — field in middle (followed by a qualifier phrase)
	$_meta_mid_templates = array(
		/* translators: %field%: WordPress meta field name */
		array( __( 'where %field% is empty',        'admin-search' ), 'empty'     ),
		/* translators: %field%: WordPress meta field name */
		array( __( 'where %field% has no value',    'admin-search' ), 'empty'     ),
		/* translators: %field%: WordPress meta field name */
		array( __( 'where %field% is not empty',    'admin-search' ), 'not_empty' ),
		/* translators: %field%: WordPress meta field name */
		array( __( 'where %field% is set',          'admin-search' ), 'not_empty' ),
		/* translators: %field%: WordPress meta field name */
		array( __( 'where %field% is present',      'admin-search' ), 'not_empty' ),
		/* translators: %field%: WordPress meta field name */
		array( __( 'where %field% is filled',       'admin-search' ), 'not_empty' ),
		/* translators: %field%: WordPress meta field name */
		array( __( 'where %field% has a value',     'admin-search' ), 'not_empty' ),
		/* translators: %field%: WordPress meta field name */
		array( __( 'where %field% has any value',   'admin-search' ), 'not_empty' ),
		/* translators: %field%: WordPress meta field name */
		array( __( 'where %field% has some value',  'admin-search' ), 'not_empty' ),
		/* translators: %field%: WordPress meta field name */
		array( __( 'where %field% is not set',      'admin-search' ), 'not_exists' ),
		/* translators: %field%: WordPress meta field name */
		array( __( 'where %field% is missing',      'admin-search' ), 'not_exists' ),
		/* translators: %field%: WordPress meta field name */
		array( __( 'where %field% does not exist',  'admin-search' ), 'not_exists' ),
		/* translators: %field%: WordPress meta field name. Note: the apostrophe in "doesn't" must be preserved in translations. */
		array( __( "where %field% doesn't exist",   'admin-search' ), 'not_exists' ),
		/* translators: %field%: WordPress meta field name */
		array( __( 'missing %field%',               'admin-search' ), 'not_exists' ),
	);

	// Shared helper: resolve type → clause array, then build the return value.
	$_make_meta_result = function ( $type, $key, $remainder ) use ( $mq_empty, $mq_not_empty, $mq_exists, $mq_not_exists ) {
		switch ( $type ) {
			case 'empty':      $clause = $mq_empty( $key );      break;
			case 'not_empty':  $clause = $mq_not_empty( $key );  break;
			case 'exists':     $clause = $mq_exists( $key );     break;
			case 'not_exists': $clause = $mq_not_exists( $key ); break;
			default: return NULL;
		}
		return array( 'meta_query' => $clause, 'remainder' => trim( $remainder ) );
	};

	// Stage 1a: field at end — (.+) captures the meta key name, $is_valid_key guards it.
	foreach ( $_meta_end_templates as $mep ) {
		$tpl  = $mep[0];
		$type = $mep[1];
		// Note: preg_quote() does not escape %, so str_replace on %field% is safe.
		$pat  = '/^(.*?)\s*' . str_replace( '%field%', '(.+)', preg_quote( $tpl, '/' ) ) . '$/i';
		if ( ! preg_match( $pat, $q, $m ) ) {
			continue;
		}
		$key = trim( $m[2] );
		if ( ! $is_valid_key( $key ) ) {
			continue;
		}
		$result = $_make_meta_result( $type, $key, $m[1] );
		if ( $result !== NULL ) {
			return $result;
		}
	}

	// Stage 1b: field in middle — strict character class doubles as key validation.
	foreach ( $_meta_mid_templates as $mmp ) {
		$tpl  = $mmp[0];
		$type = $mmp[1];
		$pat  = '/^(.*?)\s*' . str_replace( '%field%', '([a-zA-Z0-9_\\-]+)', preg_quote( $tpl, '/' ) ) . '$/i';
		if ( ! preg_match( $pat, $q, $m ) ) {
			continue;
		}
		$key    = trim( $m[2] );
		$result = $_make_meta_result( $type, $key, $m[1] );
		if ( $result !== NULL ) {
			return $result;
		}
	}


	// ── Stage 2: value comparison patterns, anchored by a translatable "where" keyword ───
	// Requiring this anchor prevents false positives on normal keyword searches.
	// Query form: "[keyword] where [field] [operator] [value]"
	/* translators: "where" as used in a search filter, e.g. "posts where price > 100" */
	$_where_word = __( 'where', 'admin-search' );
	if ( ! preg_match( '/^(.*?)\s*\b' . preg_quote( $_where_word, '/' ) . '\b\s+(.+)$/i', $q, $where_m ) ) {
		return NULL;
	}

	$before_where = trim( $where_m[1] );
	$clause_str   = trim( $where_m[2] );

	// Note on localisation: the comparison operators below (contains, greater than,
	// at least, between … and …, etc.) are matched by hardcoded English regex
	// alternations.  Fully localising them would require each operator phrase to be
	// expressed as a separate __() string, which would produce a very long list and
	// complicate the regex assembly significantly.  This is left as a known limitation;
	// translations can override the entire function via remove_filter / add_filter on
	// 'admin_search_posts_query' if needed.

	// Patterns ordered most-specific first (longer / more-distinctive operators first).
	// Format: [ regex, compare, type_or_null ]
	//   'between' is special-cased because it has three capture groups.
	$cmp_patterns = array(
		// BETWEEN: "price between 10 and 50"
		array(
			'/^(.+?)\s+(?:is\s+)?between\s+(.+?)\s+and\s+(.+)$/i',
			'BETWEEN', 'NUMERIC',
		),
		// >= / at least
		array(
			'/^(.+?)\s+(?:is\s+)?(?:>=|at\s+least|no\s+less\s+than|minimum(?:\s+of)?)\s+(.+)$/i',
			'>=', 'NUMERIC',
		),
		// <= / at most
		array(
			'/^(.+?)\s+(?:is\s+)?(?:<=|at\s+most|no\s+more\s+than|maximum(?:\s+of)?)\s+(.+)$/i',
			'<=', 'NUMERIC',
		),
		// > / greater than
		array(
			'/^(.+?)\s+(?:is\s+)?(?:>|greater\s+than|more\s+than|higher\s+than|above)\s+(.+)$/i',
			'>', 'NUMERIC',
		),
		// < / less than
		array(
			'/^(.+?)\s+(?:is\s+)?(?:<|less\s+than|lower\s+than|fewer\s+than|below)\s+(.+)$/i',
			'<', 'NUMERIC',
		),
		// != / not equal / is not
		array(
			'/^(.+?)\s+(?:is\s+not|!=|does\s+not\s+equal|doesn\'?t\s+equal)\s+(.+)$/i',
			'!=', NULL,
		),
		// NOT LIKE / does not contain
		array(
			'/^(.+?)\s+(?:does\s+not\s+contain|doesn\'?t\s+contain|does\s+not\s+include|doesn\'?t\s+include)\s+(.+)$/i',
			'NOT LIKE', NULL,
		),
		// LIKE / contains
		array(
			'/^(.+?)\s+(?:contains?|includes?)\s+(.+)$/i',
			'LIKE', NULL,
		),
		// = / equals / is [exactly]
		array(
			'/^(.+?)\s+(?:=|equals?\s+|is\s+(?:exactly\s+)?)(.+)$/i',
			'=', NULL,
		),
	);

	foreach ( $cmp_patterns as $cp ) {
		$pattern = $cp[0];
		$compare = $cp[1];
		$type    = $cp[2];

		if ( ! preg_match( $pattern, $clause_str, $cmp_m ) ) {
			continue;
		}

		$key = trim( $cmp_m[1] );
		if ( ! $is_valid_key( $key ) ) {
			continue;
		}

		if ( $compare === 'BETWEEN' ) {
			$val1 = trim( $cmp_m[2] );
			$val2 = trim( $cmp_m[3] );
			if ( $val1 === '' || $val2 === '' ) {
				continue;
			}
			return array(
				'meta_query' => array(
					array(
						'key'     => $key,
						'value'   => array( $val1, $val2 ),
						'compare' => 'BETWEEN',
						'type'    => 'NUMERIC',
					),
				),
				'remainder' => $before_where,
			);
		}

		$val = trim( $cmp_m[2] );
		if ( $val === '' && $compare !== '=' && $compare !== '!=' ) {
			continue;
		}

		$clause_args = array(
			'key'     => $key,
			'value'   => $val,
			'compare' => $compare,
		);
		if ( $type !== NULL ) {
			$clause_args['type'] = $type;
		}

		return array(
			'meta_query' => array( $clause_args ),
			'remainder'  => $before_where,
		);
	}

	return NULL;

}


/*
 *	Add a nice title to external website names
 */
function admin_search_get_website_title( $host ) {

	// Some common websites
	$website_titles = array(
		'bing.com' => 'Bing',
		'gettyimages.com' => 'Getty Images',
		'google.ca' => 'Google',
		'google.ch' => 'Google',
		'google.co.nz' => 'Google',
		'google.co.uk' => 'Google',
		'google.com' => 'Google',
		'google.com.au' => 'Google',
		'google.in' => 'Google',
		'istockphoto.com' => 'iStock',
		'shutterstock.com' => 'Shutterstock',
		'stackoverflow.com' => 'Stack Overflow',
		'unsplash.com' => 'Unsplash',
		'wikipedia.org' => 'Wikipedia',
		'en.wikipedia.org' => 'Wikipedia',
		'wordpress.org' => 'WordPress',
		'wordpress.stackexchange.com' => 'WordPress Development Stack Exchange',
		'wpbeginner.com' => 'WPBeginner',
		'yahoo.com' => 'Yahoo'
	);

	// Apply any filters to the array
	$website_titles = apply_filters( 'admin_search_website_titles', $website_titles );

	// Check if array is valid and host exists, then return matching label
	if ( is_array( $website_titles ) && isset( $website_titles[ $host ] ) ) {
		return $website_titles[ $host ];
	}

	// Otherwise, return the host name as is
	return $host;

}


/*
 *	Prepare JSON for AJAX call
 */
function admin_search_ajax_return(  $message = '', $error = true, $results = array(), $total_results = 0  ) {

	wp_send_json( array(
		'error'			=>	$error,
		'message'		=>	$message,
		'results'		=>	$results,
		'total_results'	=>	$total_results
	) );

}


/*
 *	Prepare query for LIKE statement
 */
function admin_search_prepare_query(  $table, $field, $s  ) {

	global $wpdb;

	return $wpdb -> prepare( "{$table}.{$field} LIKE %s", '%' . $wpdb -> esc_like( $s ) . '%' );

}


/*
 *	Add 'as_s' item to WP_Query and handle it
 */
function admin_search_extend_query( $where, $wp_query ) {

	global $wpdb;

	// Only modify this filter if 'as_s' is used in the WP_Query call
	if ( $s = $wp_query -> get( 'as_s' ) ) {

		$default_fields = array( 'post_title', 'post_name', 'post_excerpt', 'post_content' );

		// Apply filters to search fields
		$fields = apply_filters( 'admin_search_fields', $default_fields, $wp_query -> get( 'post_type' ) );

		// Check that applied filters are valid, otherwise, use default fields
		if ( ! ( is_array( $fields ) && ! empty( $fields ) ) ) {
			$fields = $default_fields;
		}

		// Build WHERE query for post fields
		$where .= ' AND (';

		foreach ( $fields as $i => $field ) {
			if ( $i > 0 ) {
				$where .= ' OR ';
			}

			$where .= admin_search_prepare_query( $wpdb -> posts, $field, $s );
		}

		// Apply filters to meta queries
		$meta_fields = apply_filters( 'admin_search_meta_queries', array( '_wp_attachment_image_alt' ), $wp_query -> get( 'post_type' ) );

		// If meta queries have been added, build WHERE query for meta fields
		if ( is_array( $meta_fields ) && ! empty( $meta_fields ) ) {
			if ( ! empty( $fields ) ) {
				$where .= ' OR ';
			}

			foreach ( $meta_fields as $i => $meta_field ) {
				if ( $i > 0 ) {
					$where .= ' OR ';
				}

				$where .= '(' . admin_search_prepare_query( $wpdb -> postmeta, 'meta_value', $s ) . ' AND ' . $wpdb -> prepare( "{$wpdb -> postmeta}.meta_key = %s", $meta_field ) . ')';
			}
		}

		$taxonomies = apply_filters( 'admin_search_taxonomies', array(), $wp_query -> get( 'post_type' ) );

		if ( is_array( $taxonomies ) && ! empty( $taxonomies ) ) {
			foreach ( $taxonomies as $taxonomy ) {
				// Add taxonomy query
				$where .= ' OR ' . admin_search_prepare_query( $wpdb -> terms, 'name', $s ) . ' AND ' . $wpdb -> prepare( "{$wpdb -> term_taxonomy}.taxonomy = %s", $taxonomy );
			}
		}

		$where .= ')';
	}

	return $where;
}

/*
 *	Enable 'as_s' for meta queries and taxonomy queries
 */
add_filter( 'posts_where', 'admin_search_extend_query', 10, 2 );
add_filter( 'posts_join', function( string $sql, WP_Query $query ) {

	global $wpdb;

	if ( $query -> get( 'as_s' ) ) {
		// Join postmeta for meta fields
		$sql .= " LEFT JOIN {$wpdb -> postmeta} ON {$wpdb -> posts}.ID = {$wpdb -> postmeta}.post_id ";

		// Join taxonomy tables for taxonomy fields
		$sql .= " LEFT JOIN {$wpdb -> term_relationships} ON ({$wpdb -> posts}.ID = {$wpdb -> term_relationships}.object_id)
				  LEFT JOIN {$wpdb -> term_taxonomy} ON ({$wpdb -> term_relationships}.term_taxonomy_id = {$wpdb -> term_taxonomy}.term_taxonomy_id)
				  LEFT JOIN {$wpdb -> terms} ON ({$wpdb -> term_taxonomy}.term_id = {$wpdb -> terms}.term_id) ";
	}

	return $sql;

}, 10, 2 );

add_filter( 'posts_distinct', function( string $sql, WP_Query $query ) {

	if ( $query -> get( 'as_s' ) ) {
		return 'DISTINCT';
	}

	return $sql;

}, 10, 2 );


/*
 *	Validate search query and return search results with AJAX
 */
function admin_search_ajax() {

	// Only logged in users can search
	if ( ! is_user_logged_in() ) {
		return admin_search_ajax_return( __( 'Not authorized', 'admin-search' ) );
	}

	// Only users who can edit other users' posts can search
	if ( ! current_user_can( 'edit_others_pages' ) ) {
		return admin_search_ajax_return( __( 'Not authorized', 'admin-search' ) );
	}

	// Verify nonce
	check_ajax_referer( 'admin-search' );

	// JSON headers and default values
	$results = array();


	// Perform basic checks on q
	if ( ! ( isset( $_GET[ 'q' ] ) && ! empty( trim( wp_strip_all_tags( $_GET[ 'q' ] ) ) ) ) ) {
		return admin_search_ajax_return( __( 'No search query supplied', 'admin-search' ) );
	}

	// Clean q up
	$q = trim( mb_strtolower( wp_strip_all_tags( $_GET[ 'q' ] ) ) );

	// Apply any filters to q
	$q = apply_filters( 'admin_search_query', $q );

	// Check if source is supplied, otherwise build list from preferences
	if ( isset( $_GET[ 'source' ] ) && is_string( trim( $_GET[ 'source' ] ) ) ) {
		$sources = array( sanitize_key( $_GET[ 'source' ] ) );
	} else {
		$sources = array_merge( array_keys( admin_search_setting( 'post_types', array() ) ), admin_search_setting( 'taxonomies', array() ) );

		if ( admin_search_setting( 'include_comments' ) ) {
			$sources[] = 'comment';
		}

		if ( admin_search_setting( 'include_users' ) ) {
			$sources[] = 'user';
		}

		if ( admin_search_setting( 'include_admin_pages' ) ) {
			$sources[] = 'admin';
		}

		if ( admin_search_setting( 'external_websites' ) ) {
			$sources[] = 'external_sites';
		}
	}

	// Apply any filters to sources array
	$sources = apply_filters( 'admin_search_sources', $sources, $q );

	// Make sure the filters didn't break the array
	if ( ! is_array( $sources ) ) {
		return admin_search_ajax_return( __( 'Invalid sources list', 'admin-search' ) );
	}

	// Check if the query starts with a post type name/label and, if so, scope to that type
	// and strip the prefix. This allows queries like "products since 2023" or "pages by John".
	// Only applies when no explicit source has been passed in via $_GET['source'].
	if ( ! isset( $_GET[ 'source' ] ) ) {
		$post_type_prefix = admin_search_detect_post_type_prefix( $q, $sources );
		if ( $post_type_prefix ) {
			$sources = array( $post_type_prefix[ 'post_type' ] );
			$q       = $post_type_prefix[ 'q' ];
		}
	}

	// Filter out internal / page-builder post types from $sources when the
	// 'hide_internal_post_types' setting is on.  Runs after the post-type prefix
	// detection so a query like "bricks templates since 2023" still respects the
	// hide flag.  Respects the admin_search_internal_post_types filter.
	if ( admin_search_setting( 'hide_internal_post_types' ) ) {
		$internal_types = admin_search_internal_post_types();
		if ( ! empty( $internal_types ) ) {
			$sources = array_values( array_diff( $sources, $internal_types ) );
		}
	}


	// Check if q is an ID
	$id = NULL;

	if ( preg_match( '/^#([0-9]+)$/', $q, $match ) ) {
		$id = $match[ 1 ];
	}


	// Check if q contains a status, if it does, limit it to just posts, pages, attachments and custom post types
	$status = 'any';

	/* translators: %s: post status (eg. draft, published, pending) */
	$status_pattern = '/\s' . sprintf( __( 'status:%s', 'admin-search' ), '([a-z]+)' ) . '$/';

	if ( preg_match( $status_pattern, $q, $match ) ) {
		$status = $match[ 1 ];
		$q = preg_replace( $status_pattern, '', $q );
		$sources = array_keys( admin_search_setting( 'post_types', array() ) );

		// Respect the hide_internal_post_types flag for status: scoped searches too.
		if ( admin_search_setting( 'hide_internal_post_types' ) ) {
			$internal_types = admin_search_internal_post_types();
			if ( ! empty( $internal_types ) ) {
				$sources = array_values( array_diff( $sources, $internal_types ) );
			}
		}
	}


	// Check if paged is supplied and valid, if not, set as 1
	$paged = isset( $_GET[ 'paged' ] ) && absint( $_GET[ 'paged' ] ) > 0 ? absint( $_GET[ 'paged' ] ) : 1;


	// Check if q is long enough, afterall, anything shorter than 2 characters is not a very good search
	if ( strlen( $q ) < 2 ) {
		return admin_search_ajax_return( __( 'Query too short', 'admin-search' ) );
	}


	// Apply any filters to $results
	$results = apply_filters( 'admin_search_pre_results', $results, $q );


	// Build the list of searchable post types, honouring the hide_internal_post_types
	// toggle.  When that setting is on we skip the denylisted page-builder types
	// entirely so we don't even issue WP_Query against them.
	$searchable_post_types = array_keys( admin_search_setting( 'post_types', array() ) );

	if ( admin_search_setting( 'hide_internal_post_types' ) ) {
		$internal_types = admin_search_internal_post_types();
		if ( ! empty( $internal_types ) ) {
			$searchable_post_types = array_values( array_diff( $searchable_post_types, $internal_types ) );
		}
	}

	// Cycle through all the searchable post types
	foreach ( $searchable_post_types as $post_type ) {
		// Check if the post type is in the sources array, if not, skip it
		if ( ! in_array( $post_type, $sources ) ) {
			continue;
		}

		// Setup author, date, and keyword variables
		$author      = NULL;
		$date        = NULL;
		$date_end    = NULL;
		$date_range  = 'on';
		$date_column = 'post_date'; // overridden to 'post_modified' for updated/edited queries
		$keyword     = NULL;        // set when a keyword+date combination is detected

		// All recognised author-prefix patterns (reused for both pure-author and combined detection)
		$author_patterns = array(
			__( 'posts by %author%',    'admin-search' ),
			__( 'posted by %author%',   'admin-search' ),
			__( 'authored by %author%', 'admin-search' ),
			__( 'author is %author%',   'admin-search' ),
			__( 'author %author%',      'admin-search' ),
			__( 'made by %author%',     'admin-search' ),
			__( 'created by %author%',  'admin-search' ),
		);

		// Keywords that introduce a date expression; used as a non-consuming look-ahead
		// anchor so the author capture stays non-greedy in combined queries.
		// Built from translated phrases so the combined-query detection keeps working
		// when the site is running in a non-English locale.
		$_date_anchor_phrases = array_filter( array(
			/* translators: "posted before" as prefix for a date expression */
			__( 'posted before',    'admin-search' ),
			/* translators: "posted after" as prefix for a date expression */
			__( 'posted after',     'admin-search' ),
			/* translators: "posted on" as prefix for a date expression */
			__( 'posted on',        'admin-search' ),
			/* translators: "posted in" as prefix for a date expression */
			__( 'posted in',        'admin-search' ),
			/* translators: "published before" as prefix for a date expression */
			__( 'published before', 'admin-search' ),
			/* translators: "published after" as prefix for a date expression */
			__( 'published after',  'admin-search' ),
			/* translators: "published on" as prefix for a date expression */
			__( 'published on',     'admin-search' ),
			/* translators: "published in" as prefix for a date expression */
			__( 'published in',     'admin-search' ),
			// Modified-date prefixes — must match those in admin_search_parse_date_expression()
			__( 'last updated',     'admin-search' ),
			__( 'last edited',      'admin-search' ),
			__( 'last modified',    'admin-search' ),
			__( 'updated',          'admin-search' ),
			__( 'edited',           'admin-search' ),
			__( 'modified',         'admin-search' ),
			// Relative-window prefix (must match those in admin_search_parse_date_expression())
			__( 'in the last',      'admin-search' ),
			// Raw date anchors
			__( 'before',           'admin-search' ),
			__( 'after',            'admin-search' ),
			__( 'since',            'admin-search' ),
			__( 'from',             'admin-search' ),
			__( 'until',            'admin-search' ),
			__( 'up to',            'admin-search' ),
			__( 'between',          'admin-search' ),
		) );
		// Sort longest-first so longer phrases are tried before shorter prefixes of them
		usort( $_date_anchor_phrases, function ( $a, $b ) { return strlen( $b ) - strlen( $a ); } );
		$date_anchor_re = '(?:' . implode( '|', array_map( 'preg_quote', $_date_anchor_phrases, array_fill( 0, count( $_date_anchor_phrases ), '/' ) ) ) . ')';

		// 1. Try combined author + date (e.g. "posts by John between 2020 and 2022")
		foreach ( $author_patterns as $author_q ) {
			$combined_re = '/^' . str_replace( '%author%', '(.+?)', $author_q ) . '\s+(' . $date_anchor_re . '\s*.+)$/';
			if ( preg_match( $combined_re, $q, $match ) ) {
				$author_candidate = trim( $match[1] );
				$date_result      = admin_search_parse_date_expression( trim( $match[2] ) );
				if ( $date_result !== NULL && ! empty( $author_candidate ) ) {
					$author      = $author_candidate;
					$date        = $date_result['date'];
					$date_end    = $date_result['date_end'];
					$date_range  = $date_result['date_range'];
					$date_column = $date_result['column'];
					break;
				}
			}
		}

		// 2. If no combined match, try a pure author query
		if ( $author === NULL ) {
			foreach ( $author_patterns as $author_q ) {
				if ( preg_match( '/^' . str_replace( '%author%', '(.+)', $author_q ) . '$/', $q, $match ) ) {
					$author = $match[1];
					break;
				}
			}
		}

		// 3. If no author at all, try a pure date expression
		if ( $author === NULL ) {
			$date_result = admin_search_parse_date_expression( $q );
			if ( $date_result !== NULL ) {
				$date        = $date_result['date'];
				$date_end    = $date_result['date_end'];
				$date_range  = $date_result['date_range'];
				$date_column = $date_result['column'];
			}
		}

		// 3.5. Keyword + date: a leading keyword followed by a date anchor keyword.
		//      Handles queries like "wordpress between 2020 and 2022" or
		//      "hello world since last year" where neither the full string nor the
		//      prefix matches any author pattern and the full string is not a pure date.
		if ( $author === NULL && $date === NULL ) {
			if ( preg_match( '/^(.+?)\s+(' . $date_anchor_re . '\s*.+)$/s', $q, $match ) ) {
				$kw_candidate   = trim( $match[1] );
				$date_candidate = admin_search_parse_date_expression( trim( $match[2] ) );
				if ( $date_candidate !== NULL && strlen( $kw_candidate ) >= 2 ) {
					$keyword     = $kw_candidate;
					$date        = $date_candidate['date'];
					$date_end    = $date_candidate['date_end'];
					$date_range  = $date_candidate['date_range'];
					$date_column = $date_candidate['column'];
				}
			}
		}


		// 4. Extract taxonomy constraints (combinable with author/date; e.g. "tagged sci-fi",
		//    "in review category and tagged sci-fi", "posts in review category before 2023").
		$taxonomy_result       = admin_search_parse_taxonomy_expression( $q );
		$tax_queries_for_args  = array();
		$tax_keyword_remainder = $q; // fallback; updated below when patterns are found

		if ( $taxonomy_result !== NULL ) {
			$tax_keyword_remainder = $taxonomy_result['remainder'];
			$pt_settings           = admin_search_setting( 'post_types', array() );
			$enabled_fields        = isset( $pt_settings[ $post_type ] ) ? (array) $pt_settings[ $post_type ] : NULL;

			foreach ( $taxonomy_result['tax_queries'] as $tq ) {
				// When explicit settings exist for this post type, only honour enabled taxonomies
				if ( $enabled_fields !== NULL && ! in_array( $tq['taxonomy'], $enabled_fields ) ) {
					continue;
				}

				// Try an exact term-name match first
				$term_ids = get_terms( array(
					'taxonomy'   => $tq['taxonomy'],
					'name'       => $tq['term'],
					'hide_empty' => false,
					'fields'     => 'ids',
				) );

				// Fall back to a starts-with / contains match when no exact result
				if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
					$term_ids = get_terms( array(
						'taxonomy'   => $tq['taxonomy'],
						'name__like' => $tq['term'],
						'hide_empty' => false,
						'fields'     => 'ids',
					) );
				}

				if ( ! is_wp_error( $term_ids ) && ! empty( $term_ids ) ) {
					$tax_queries_for_args[] = array(
						'taxonomy' => $tq['taxonomy'],
						'field'    => 'term_id',
						'terms'    => $term_ids,
					);
				}
			}

		}


		// 5. Extract meta field expression — handles existence checks, equality,
		//    contains, and numeric comparisons (e.g. "posts with no value in price",
		//    "products where rating > 4", "where status is not empty").
		$meta_result         = admin_search_parse_meta_expression( $q );
		$meta_query_for_args = NULL;
		$meta_expr_remainder = '';

		if ( $meta_result !== NULL ) {
			$meta_query_for_args = $meta_result['meta_query'];
			$meta_expr_remainder = trim( $meta_result['remainder'] );
		}


		// Setup `WP_Post` arguments
		$posts_args = array(
			'post_type'           => $post_type,
			'post_status'         => $status,
			'posts_per_page'      => admin_search_setting( 'result_count' ),
			'paged'               => $paged,
			'ignore_sticky_posts' => true,
		);

		// Attach taxonomy filter when one or more terms were resolved
		if ( ! empty( $tax_queries_for_args ) ) {
			$posts_args['tax_query'] = array_merge(
				array( 'relation' => 'AND' ),
				$tax_queries_for_args
			);
		}

		// Attach meta expression filter when a field expression was detected
		if ( $meta_query_for_args !== NULL ) {
			$posts_args['meta_query'] = $meta_query_for_args;
		}

		// Author constraint — applied independently so it can combine with a date constraint
		if ( $author !== NULL ) {
			// Resolve "me" / "myself" / "i" to the currently logged-in user's ID
			if ( in_array( $author, array(
				__( 'me',     'admin-search' ),
				__( 'myself', 'admin-search' ),
				__( 'i',      'admin-search' ),
			) ) ) {
				$author = get_current_user_id();
			}

			if ( is_numeric( $author ) ) {
				$posts_args['author'] = $author;
			} else {
				$posts_args['author_name'] = $author;
			}
		}

		// Date constraint — applied independently so it can combine with an author or keyword constraint.
		// $date_column is 'post_date' for publish-date queries and 'post_modified' for
		// updated/edited queries; it is passed through to WP_Date_Query via the 'column' key.
		if ( $date !== NULL ) {
			switch ( $date_range ) {
				case 'on' :
					// $date is already an array of year/month/day from admin_search_convert_date_str()
					$dq = $date;
					if ( $date_column !== 'post_date' ) {
						$dq['column'] = $date_column;
					}
					$posts_args['date_query'] = $dq;
					break;
				case 'before' :
					$posts_args['date_query'] = array_filter( array(
						'before'    => $date,
						'inclusive' => true,
						'column'    => $date_column !== 'post_date' ? $date_column : NULL,
					) );
					break;
				case 'after' :
					$posts_args['date_query'] = array_filter( array(
						'after'     => $date,
						'inclusive' => true,
						'column'    => $date_column !== 'post_date' ? $date_column : NULL,
					) );
					break;
				case 'between' :
					$posts_args['date_query'] = array_filter( array(
						'after'     => $date,
						'before'    => $date_end,
						'inclusive' => true,
						'column'    => $date_column !== 'post_date' ? $date_column : NULL,
					) );
					break;
			}

			// Keyword+date combination (step 3.5) — also run a full-text search on the keyword part
			if ( $keyword !== NULL ) {
				$posts_args['as_s'] = $keyword;
			}
		}

		// If neither author nor date matched, fall back to an ID look-up or a keyword search.
		// When a taxonomy or meta filter is active only the remaining text (after stripping the
		// matched clause) is used as a keyword — an empty remainder means the filter alone
		// constrains the results and no further keyword search is needed.
		if ( $author === NULL && $date === NULL ) {
			if ( $id ) {
				$posts_args['p'] = $id;
			} elseif ( ! empty( $tax_queries_for_args ) ) {
				// Taxonomy constraint active; keyword-search the remainder (if any)
				if ( strlen( $tax_keyword_remainder ) >= 2 ) {
					$posts_args['as_s'] = $tax_keyword_remainder;
				}
			} elseif ( $meta_query_for_args !== NULL ) {
				// Meta expression active; keyword-search the remainder (if any).
				// "tutorials where price > 100" → as_s = "tutorials", meta: price > 100.
				// "where price > 100" → meta only, no keyword search.
				if ( strlen( $meta_expr_remainder ) >= 2 ) {
					$posts_args['as_s'] = $meta_expr_remainder;
				}
			} else {
				$posts_args['as_s'] = $q;
			}
		}

		// Apply any filters to the `WP_Query` arguments
		$posts_args = apply_filters( 'admin_search_posts_query', $posts_args, $q );
		$posts_args = apply_filters( 'admin_search_' . $post_type . '_query', $posts_args, $q );

		// Perform `WP_Query`
		$posts = new WP_Query( $posts_args );

		// Check if there are any posts that match all that criteria
		if ( $posts -> have_posts() ) {
			// There are!
			while ( $posts -> have_posts() ) {
				$posts -> the_post();

				// If the source information hasn't already been defined, define it. This has to be done within the loop, so if it is defined, just ignore it. Gross
				if ( ! isset( $results[ $post_type ] ) ) {
					// Get the post type fields to add to the source information
					$post_type_object = get_post_type_object( get_post_type( get_the_id() ) );

					// Apply a friendly label override for known page-builder / internal post types
					// so that, when an admin has opted out of hiding them, results still read clearly.
					$label_overrides = admin_search_post_type_label_overrides();
					$display_label   = isset( $label_overrides[ $post_type ] )
						? $label_overrides[ $post_type ]
						: $post_type_object->label;

					$results[ $post_type ][ 'post_type' ] = array(
						'name' => $post_type,
						'label' => $display_label,
						'search_url' => add_query_arg( array(
							'post_type' => $post_type,
							's' => $q
						), get_admin_url( '', 'edit.php' ) )
					);

					// The attachment post type has a different search URL
					if ( $post_type == 'attachment' ) {
						$results[ $post_type ][ 'post_type' ][ 'search_url' ] = add_query_arg( array(
							'search' => $q
						), get_admin_url( '', 'upload.php' ) );
					}
				}

				$title = get_the_title();
				$content = wp_trim_words( get_the_excerpt(), 20 );

				// If the highlight query setting is enabled, replace the matching content with the highlight HTML
				if ( admin_search_setting( 'highlight_query' ) ) {
					$title = preg_replace( '/(' . addcslashes( $q, '/' ) . ')/i', '<span class="admin-search-result-title-highlight">$1</span>', $title );
					$content = preg_replace( '/(' . addcslashes( $q, '/' ) . ')/i', '<span class="admin-search-result-title-highlight">$1</span>', $content );
				}

				// Build the result array
				$result = array(
					'id'				=>	get_the_id(),
					'edit_post_link'	=>	get_edit_post_link(),
					'preview_link'		=>	null,
					'title'				=>	$title,
					'date'				=>	get_the_date(),
					'status'			=>	get_post_status() == 'publish' ? NULL : ucfirst( get_post_status() )
				);

				// Check if `results_previews` are enabled
				if ( admin_search_setting( 'result_previews' ) ) {
					$result[ 'preview_link' ] = add_query_arg( 'admin_search_preview', 'true', get_preview_post_link() );
				}

				// Special items for attachments
				if ( $post_type == 'attachment' ) {
					$result[ 'attachment' ] = wp_get_attachment_image( get_the_id(), 'medium' );
					$result[ 'mime_type' ] = get_post_mime_type();
					$result[ 'mime_icon' ] = wp_mime_type_icon( get_the_id() );
				}

				// Add the result array to $return
				$results[ $post_type ][ 'posts' ][] = $result;
			}

			// Add addition information about this source to $return
			$results[ $post_type ][ 'total_results' ] = $posts -> found_posts;
			$results[ $post_type ][ 'total_pages' ] = $posts -> max_num_pages;
			$results[ $post_type ][ 'current_page' ] = $paged;
			$results[ $post_type ][ 'next_page' ] = NULL;

			// If there is more than one page of results, add the page number
			if ( $posts -> max_num_pages > $paged ) {
				$results[ $post_type ][ 'next_page' ] = $paged + 1;
			}
		}

		wp_reset_query();
	}


	// Cycle through all the searchable taxonomies
	foreach ( admin_search_setting( 'taxonomies', array() ) as $taxonomy ) {
		// Check if this taxonomy is in the sources array, if not, skip it
		if ( ! in_array( $taxonomy, $sources ) ) {
			continue;
		}

		// Setup arguments for `WP_Term_Query`
		$terms_args = array(
			'taxonomy'		=>	$taxonomy,
			'hide_empty'	=>	false,
			'number'		=>	admin_search_setting( 'result_count' ),
			'offset'		=>	(int)( $paged - 1 ) * (int)admin_search_setting( 'result_count' )
		);

		// If q is an ID, only look for the term with that ID, otherwise, pass q to the `name__like` argument
		if ( $id ) {
			$terms_args[ 'include' ] = array( $id );
		} else {
			$terms_args[ 'name__like' ] = $q;
		}

		// Apply any filters to the `WP_Term_Query` arguments
		$terms_args = apply_filters( 'admin_search_terms_query', $terms_args, $q );
		$terms_args = apply_filters( 'admin_search_' . $taxonomy . '_query', $terms_args, $q );

		// Perform `WP_Term_Query`
		$terms = new WP_Term_Query( $terms_args );

		// Check if there are any terms matching that criteria
		if ( $terms -> get_terms() ) {
			// There are!
			foreach ( $terms -> get_terms() as $term ) {
				if ( ! isset( $results[ $taxonomy ] ) ) {
					// Get the taxonomy fields to add to the source information
					$taxonomy_object = get_taxonomy( $term -> taxonomy );

					// Set the post type for the queried taxonomy
					$taxonomy_post_type = '';

					if ( is_array( $taxonomy_object -> object_type ) ) {
						$taxonomy_post_type = $taxonomy_object -> object_type[ 0 ];
					}

					// If the source information hasn't already been defined, define it. This has to be done within the loop, so if it is defined, just ignore it. Gross
					$results[ $taxonomy ][ 'post_type' ] = array(
						'name'	=> $taxonomy,
						'label'	=> $taxonomy_object -> label,
						'search_url' => add_query_arg( array(
							'taxonomy' => $taxonomy,
							'post_type' => $taxonomy_post_type,
							's' => $q
						), get_admin_url( '', 'edit-tags.php' ) )
					);
				}

				$title = $term -> name;

				// If the highlight query setting is enabled, replace the matching content with the highlight HTML
				if ( admin_search_setting( 'highlight_query' ) ) {
					$title = preg_replace( '/(' . addcslashes( $q, '/' ) . ')/i', '<span class="admin-search-result-title-highlight">$1</span>', $title );
				}

				// Build the result array and add it to $return
				$results[ $taxonomy ][ 'posts' ][] = array(
					'id'				=>	$term -> term_id,
					'edit_post_link'	=>	get_edit_term_link( $term -> term_id, $taxonomy ),
					'title'				=>	$title,
				);
			}

			// Add addition information about this source to $return. A little more work is required because `WP_Term_Query` doesn't return total found items or number of pages
			$found_terms = wp_count_terms( $terms_args );
			$total_pages = ceil( $found_terms / admin_search_setting( 'result_count' ) );
			$results[ $taxonomy ][ 'total_results' ] = $found_terms;
			$results[ $taxonomy ][ 'total_pages' ] = $total_pages;
			$results[ $taxonomy ][ 'current_page' ] = $paged;
			$results[ $taxonomy ][ 'next_page' ] = NULL;

			// If there is more than one page of results, add the page number
			if ( $total_pages > $paged ) {
				$results[ $taxonomy ][ 'next_page' ] = $paged + 1;
			}
		}
	}

	// Check if comment is in the sources array, if not, skip it
	if ( in_array( 'comment', $sources ) ) {
		// Setup arguments for `WP_Comment_Query`
		$comments_args = array(
			'search'	=>	$q,
			'number'	=>	admin_search_setting( 'result_count' ),
			'offset'	=>	( $paged - 1 ) * admin_search_setting( 'result_count' )
		);

		// Apply any filters to the `WP_Comment_Query` arguments
		$comments_args = apply_filters( 'admin_search_comments_query', $comments_args, $q );

		// Perform `WP_Comment_Query`
		$comments = new WP_Comment_Query( $comments_args );

		// Check if there any comments matching that criteria
		if ( $comments -> comments ) {
			// There are!
			// Define source information
			$results[ 'comment' ][ 'post_type' ] = array(
				'name'	=>	'comment',
				'label'	=>	__( 'Comments', 'admin-search' ),
				'search_url' => add_query_arg( array(
					's' => $q
				), get_admin_url( '', 'edit-comments.php' ) )
			);

			foreach ( $comments -> comments as $comment ) {
				$title = get_the_title( $comment -> comment_post_ID );

				// If the highlight query setting is enabled, replace the matching content with the highlight HTML
				if ( admin_search_setting( 'highlight_query' ) ) {
					$title = preg_replace( '/(' . addcslashes( $q, '/' ) . ')/i', '<span class="admin-search-result-title-highlight">$1</span>', $title );
				}

				// Build the result array and add it to $return
				$results[ 'comment' ][ 'posts' ][] = array(
					'id'				=>	$comment -> comment_ID,
					'edit_post_link'	=>	get_edit_comment_link( $comment -> comment_ID ),

					/* translators: %author%: comment author, %title%: comment's post title */
					'title'				=>	str_replace( array(
												'%author%',
												'%title%'
											), array(
												$comment -> comment_author,
												$title
											), __( 'Comment by %author% on %title%' , 'admin-search' ) ),
					'date'				=>	gmdate( get_option( 'date_format' ), strtotime( $comment -> comment_date ) ),
					'status'			=>	$comment -> comment_approved ? 'Published' : 'Pending'
				);
			}

			// Add addition information about this source to $return
			$results[ 'comment' ][ 'total_results' ] = $comments -> found_comments;
			$results[ 'comment' ][ 'total_pages' ] = $comments -> max_num_pages;
			$results[ 'comment' ][ 'current_page' ] = $paged;
			$results[ 'comment' ][ 'next_page' ] = NULL;

			// If there is more than one page of results, add the page number
			if ( $comments -> max_num_pages > $paged ) {
				$results[ 'comment' ][ 'next_page' ] = $paged + 1;
			}
		}
	}


	// Check if user is in the sources array, if not, skip it, and if the logged in user can edit users. Don't want users having access to other user profiles!
	if ( in_array( 'user', $sources ) && current_user_can( 'edit_users' ) ) {
		// Fetch a list of all user roles
		$user_roles = new WP_Roles();
		$role_names = $user_roles -> get_names();
		$role_q = $q;

		// Add extra terms for administrator
		if ( in_array( $role_q, array( 'admin', 'admins' ) ) ) {
			$role_q = 'administrator';
		}

		// Setup arguments for `WP_User_Query`
		$user_args = array(
			'number'	=>	admin_search_setting( 'result_count' ),
			'paged'		=>	$paged
		);

		// Depluralize role names (this isn't perfect), then check if q is a role, if it is, pass it to the role argument, otherwise, pass q to the search argument and add search columns
		if ( isset( $role_names[ preg_replace( '/s$/', '', $role_q ) ] ) ) {
			$user_args[ 'role' ] = preg_replace( '/s$/', '', $role_q );
		} else {
			if ( $id ) {
				$user_args = array(
					'search'         	=>	'*' . $id . '*',
					'search_columns'	=>	array(
						'ID'
					)
				);
			} else {
				$user_args = array(
					'search'         	=>	'*' . $q . '*',
					'search_columns'	=>	array(
						'ID',
						'user_login',
						'user_nicename',
						'user_email',
						'display_name',
					)
				);
			}
		}

		// Apply any filters to `WP_User_Query` arguments
		$user_args = apply_filters( 'admin_search_users_query', $user_args, $q );

		// Perform `WP_User_Query`
		$users = new WP_User_Query( $user_args );

		// Check if there are any users matching that criteria
		if ( $users -> get_results() ) {
			// There are!

			// If q is a role, then change the search URL to the role URL
			if ( isset( $user_args[ 'role' ] ) ) {
				$results[ 'user' ][ 'post_type' ][ 'search_url' ] = add_query_arg( array(
					'role' => $role_q
				), get_admin_url( '', 'users.php' ) );
			}

			foreach ( $users -> get_results() as $user ) {
				// Check if user is hidden to Admin Search
				if ( get_the_author_meta( 'hide_in_admin_search', $user -> ID ) ) {
					continue;
				}

				$title = $user -> data -> user_login;

				// If the highlight query setting is enabled, replace the matching content with the highlight HTML
				if ( admin_search_setting( 'highlight_query' ) ) {
					$title = preg_replace( '/(' . addcslashes( $q, '/' ) . ')/i', '<span class="admin-search-result-title-highlight">$1</span>', $title );
				}

				// Build the result array and add it to $return
				$results[ 'user' ][ 'posts' ][] = array(
					'id'				=>	$user -> ID,
					'edit_post_link'	=>	get_edit_user_link( $user -> ID ),
					'title'				=>	$title,
					'date'				=>	NULL,
					'status'			=>	$role_names[ $user -> roles[ 0 ] ]
				);
			}

			// Define source information
			if ( isset( $results[ 'user' ] ) ) {
				$results[ 'user' ][ 'post_type' ] = array(
					'name'			=>	'user',
					'label'			=>	__( 'Users', 'admin-search' ),
					'search_url'	=> add_query_arg( array(
						's' => $q
					), get_admin_url( '', 'users.php' ) )
				);

				// Add addition information about this source to $return. A little more work is required because `WP_User_Query` doesn't return total pages
				$total_pages = ceil( $users -> get_total() / admin_search_setting( 'result_count' ) );
				$results[ 'user' ][ 'total_results' ] = $users -> get_total();
				$results[ 'user' ][ 'total_pages' ] = $total_pages;
				$results[ 'user' ][ 'current_page' ] = $paged;
				$results[ 'user' ][ 'next_page' ] = NULL;

				// If there is more than one page of results, add the page number
				if ( $total_pages > $paged ) {
					$results[ 'user' ][ 'next_page' ] = $paged + 1;
				}
			}
		}
	}


	// Check if admin is in the sources array, if not, skip it
	if ( in_array( 'admin', $sources ) ) {
		// Add admin page search terms
		require_once plugin_dir_path( __FILE__ ) . 'admin-results.php';

		// Cycle through the admin page search terms
		foreach ( $admin_results as $admin_result ) {
			// Check if the logged in user has the cability to access this admin page
			if ( current_user_can( $admin_result[ 'capability' ] ) ) {
				// Check if q contains the admin page term
				$contains_value = false;

				foreach ( $admin_result[ 'q' ] as $sub_q ) {
					if ( strpos( strtolower( $sub_q ), $q ) !== false ) {
						$contains_value = true; break;
					}
				}

				// It does!
				if ( $contains_value ) {
					$title = $admin_result[ 'title' ];

					// If the highlight query setting is enabled, replace the matching content with the highlight HTML
					if ( admin_search_setting( 'highlight_query' ) ) {
						$title = preg_replace( '/(' . addcslashes( $q, '/' ) . ')/i', '<span class="admin-search-result-title-highlight">$1</span>', $title );
					}

					// Build the result array and add it to $return
					$results[ 'admin' ][ 'posts' ][] = array(
						'id'				=>	'',
						'edit_post_link'	=>	$admin_result[ 'url' ],
						'title'				=>	$title,
						'status'			=>	$admin_result[ 'status' ]
					);
				}
			}
		}

		// If there are results, define source information
		if ( isset( $results[ 'admin' ] ) ) {
			$results[ 'admin' ][ 'post_type' ] = array(
				'name'	=>	'admin',
				'label'	=>	__( 'Admin Pages', 'admin-search' )
			);
		}
	}


	// Check if admin is in the sources array, if not, skip it
	if ( in_array( 'external_sites', $sources ) ) {
		// Define source information
		$results[ 'external_sites' ][ 'post_type' ] = array(
			'name'	=>	'external_sites',
			'label'	=>	__( 'Other Websites', 'admin-search' )
		);

		// Break the setting field up into an array and cycle through it
		foreach ( explode( "\n", admin_search_setting( 'external_websites' ) ) as $external_website ) {
			// Format the host name and check if a label exists for it
			$external_website_title = admin_search_get_website_title( str_replace( 'www.', '', strtolower( wp_parse_url( $external_website, PHP_URL_HOST ) ) ) );

			// Build the result array and add it to $return
			$results[ 'external_sites' ][ 'posts' ][] = array(
				'id'				=>	'',
				'edit_post_link'	=>	str_replace( '%q%', urlencode( $q ), $external_website ),

				/* translators: %s: the title of the external website, may be a domain eg. google.com */
				'title'				=>	sprintf( __( 'View results on %s', 'admin-search' ), $external_website_title ),
				'status'			=>	NULL
			);
		}
	}


	// Apply any filters to $results
	$results = apply_filters( 'admin_search_post_results', $results, $q );


	// Ensure attachments are the last results returned
	if ( isset( $results[ 'attachment' ] ) ) {
		$attachments = $results[ 'attachment' ];

		unset( $results[ 'attachment' ] );

		$results[ 'attachment' ] = $attachments;
	}


	// Calculate and return total result count
	$total_results = 0;

	foreach ( $results as $key => $value ) {
		if ( isset( $value[ 'posts' ] ) && is_array( $value[ 'posts' ] ) ) {
			$total_results += count( $value[ 'posts' ] );
		}
	}


	global $wpdb;

	$table_name = $wpdb -> prefix . 'admin_search__searches';


	if ( $total_results > 0 ) {
		// If the query is longer than 8 characters, save it to the `searches` table
		if ( strlen( $q ) > 8 ) {
			// Check if query is in the `searches` table
			if ( $row = $wpdb -> get_row( $wpdb -> prepare( "SELECT * FROM $table_name WHERE query = %s", $q ) ) ) {
				// It is, so update the number of results and increment its occurrences
				$wpdb -> update(
					$table_name,
					[
						'results' => $total_results,
						'occurrences' => (int)$row -> occurrences + 1
					],
					[
						'id' => $row -> id
					]
				);
			} else {
				// It's not, so add it
				$wpdb -> insert(
					$table_name,
					[
						'query' => $q,
						'results' => $total_results,
					]
				);
			}
		}

		// Format and return results
		return admin_search_ajax_return( __( 'Results found', 'admin-search' ), false, $results, $total_results );
	} else {
		// Check if the query is in the `searches` table
		if ( $row = $wpdb -> get_row( $wpdb -> prepare( "SELECT * FROM $table_name WHERE query = %s", $q ) ) ) {
			// It is, so remove it (we don't want queries with no results in the table. If the row exists, it's probably because it had results at one point)
			$wpdb -> delete(
				$table_name,
				[
					'id' => $row -> id
				]
			);
		}

		// Format and return results
		return admin_search_ajax_return( __( 'No results found', 'admin-search' ), false );
	}

}

add_action( 'wp_ajax_admin_search_ajax', 'admin_search_ajax' );




function admin_search_clear_searches_ajax() {

	// Only logged in users can clear searches
	if ( ! is_user_logged_in() ) {
		return admin_search_ajax_return( __( 'Not authorized', 'admin-search' ) );
	}

	// Only users who can edit other users' posts can clear searches
	if ( ! current_user_can( 'edit_others_pages' ) ) {
		return admin_search_ajax_return( __( 'Not authorized', 'admin-search' ) );
	}

	check_ajax_referer( 'clear-searches' );

	global $wpdb;
	
	$table  = $wpdb -> prefix . 'admin_search__searches';

	$wpdb -> query( "TRUNCATE TABLE $table" );

	return admin_search_ajax_return( __( 'OK', 'admin-search' ) );

}

add_action( 'wp_ajax_admin_search_clear_searches_ajax', 'admin_search_clear_searches_ajax' );




/*
 *	Add common plugin fields to searchable fields
 */
add_filter( 'admin_search_meta_queries', function( $fields, $post_type ) {

	// If the `product` type exists add some custom fields to the searchable fields
	if ( 'product' === $post_type ) {
		// Add the `SKU` field from Woocommerce
		$fields[] = '_sku';
	}

	return $fields;

}, 10, 2 );



