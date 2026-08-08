<?php
/**
 * MU-plugin: registers a wp-cli command 'smr-phpcs' that runs PHPCS
 * with the WordPress coding standard against a path.
 *
 * Uses the PHPCS source-tree checkout under wp-content/phpcs-vendor/phpcs-source
 * rather than the phar (the phar's stub invokes `runphpcs` on load).
 *
 * @package SiteMapRedirects
 */

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

WP_CLI::add_command(
	'smr-phpcs',
	array( 'SMR_Phpcs_Runner', 'run' ),
	array(
		'synopsis' => '[<path>] [--standard=<standard>] [--report=<report>] [--error-severity=<n>] [--warning-severity=<n>] [--ignore-annotations] [--summary] [--log=<file>]',
	)
);

/**
 * PHPCS runner: load PHPCS source via its autoloader, configure WPCS,
 * scan the target path.
 */
class SMR_Phpcs_Runner {

	/**
	 * Cached flag indicating whether PHPCS has been bootstrapped.
	 *
	 * @var bool
	 */
	private static $phpcs_loaded = false;

	/**
	 * Run PHPCS against the given path.
	 *
	 * @param array $args        Positional arguments.
	 * @param array $assoc_args  Associative arguments.
	 */
	public static function run( $args, $assoc_args ) {
		$path     = isset( $args[0] ) ? $args[0] : WP_CONTENT_DIR . '/plugins/site-map-redirects';
		$standard = isset( $assoc_args['standard'] ) ? $assoc_args['standard'] : 'WordPress-Extra,WordPress-Docs';
		$report   = isset( $assoc_args['report'] ) ? $assoc_args['report'] : 'full';
		$errsev   = isset( $assoc_args['error-severity'] ) ? (int) $assoc_args['error-severity'] : 1;
		$warnsev  = isset( $assoc_args['warning-severity'] ) ? (int) $assoc_args['warning-severity'] : 1;
		$summary_only = isset( $assoc_args['summary'] );

		if ( ! is_dir( $path ) ) {
			WP_CLI::error( "Path not found: {$path}" );
		}

		$phpcs_src = WP_CONTENT_DIR . '/phpcs-vendor/phpcs-source';
		$wpcs_path = WP_CONTENT_DIR . '/phpcs-vendor/WordPress-Coding-Standards';

		if ( ! is_dir( $phpcs_src ) ) {
			WP_CLI::error( "PHPCS source not found at {$phpcs_src}" );
		}
		if ( ! is_dir( $wpcs_path ) ) {
			WP_CLI::error( "WordPress-Coding-Standards not found at {$wpcs_path}" );
		}

		self::load_phpcs( $phpcs_src );

		// Load PHPCSUtils, the helper library WPCS sniffs depend on. Its
		// autoloader registers a SPL autoloader for the PHPCSUtils namespace.
		$phpcs_utils_autoload = WP_CONTENT_DIR . '/phpcs-vendor/phpcs-utils/phpcsutils-autoload.php';
		if ( file_exists( $phpcs_utils_autoload ) ) {
			require_once $phpcs_utils_autoload;
		}

		// Define the PHPCS runtime constants the CLI stub would normally set.
		if ( ! defined( 'PHP_CODESNIFFER_CBF' ) ) {
			define( 'PHP_CODESNIFFER_CBF', false );
		}
		if ( ! defined( 'PHP_CODESNIFFER_VERBOSITY' ) ) {
			define( 'PHP_CODESNIFFER_VERBOSITY', 0 );
		}

		// Register WPCS standards plus PHPCSUtils and PHPCSExtra as installed
		// paths. PHPCS' Standards::getInstalledStandardPaths() reads the
		// `installed_paths` config key and walks each path looking for
		// ruleset.xml files. setConfigData() also calls Autoload::addSearchPath()
		// for every standard it finds, which is required for the sniff classes
		// to be discovered.
		$phpcs_utils_dir  = WP_CONTENT_DIR . '/phpcs-vendor/phpcs-utils/PHPCSUtils';
		$phpcsextra_dir   = WP_CONTENT_DIR . '/phpcs-vendor/phpcsextra';
		$paths = array_filter( array( $wpcs_path, $phpcs_utils_dir, $phpcsextra_dir ) );
		\PHP_CodeSniffer\Config::setConfigData( 'installed_paths', implode( ',', $paths ), true );

		// Build PHPCS config via the CLI-style long arguments (which the
		// Config class accepts via __construct).
		$config_args = array(
			'--standard=' . $standard,
			'--report=' . $report,
			'--error-severity=' . $errsev,
			'--warning-severity=' . $warnsev,
			'--tab-width=4',
			'--parallel=1',
			'--no-cache',
			'--no-colors',
			'-q',
		);
		if ( ! empty( $assoc_args['ignore-annotations'] ) ) {
			$config_args[] = '--ignore-annotations';
		}

		$config = new \PHP_CodeSniffer\Config( $config_args, false );

		// Override defaults that Config::__construct doesn't expose.
		$config->basepath    = $path;
		$config->files       = self::collect_files( $path );
		$config->annotations = empty( $assoc_args['ignore-annotations'] );

		// Init runner.
		$runner = new \PHP_CodeSniffer\Runner();
		$runner->config = $config;
		$runner->init();

		// Capture both PHP error/warning output and the buffered PHPCS report
		// so we can write them to a file the host can read, even if the
		// runner truncates stdout. This makes the audit reproducible from
		// the workspace without depending on result.json timing.
		$debug_log = isset( $assoc_args['log'] ) ? (string) $assoc_args['log'] : '';
		$log_handle = $debug_log ? fopen( $debug_log, 'w' ) : false;
		$log_write  = function ( $line ) use ( &$log_handle ) {
			if ( $log_handle ) {
				fwrite( $log_handle, $line );
				fflush( $log_handle );
			}
		};

		$log_write( "SMR_PHPCS_START path=" . $path . " standard=" . $standard . "\n" );

		// Suppress the runner's normal output; we'll print our own summary.
		ob_start();
		try {
			// PHPCS Runner::run() is private; reach it via reflection so we
			// can reuse the runner we configured above (runPHPCS would build
			// its own Config from CLI args and discard ours). Then call
			// Reporter::printReports() to emit the configured report (full,
			// summary, json, etc.) into our outer buffer.
			$run_method = new \ReflectionMethod( $runner, 'run' );
			$run_method->setAccessible( true );
			$run_method->invoke( $runner );
			$runner->reporter->printReports();
		} catch ( \Throwable $e ) {
			$log_write( 'SMR_PHPCS_EXCEPTION ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n" );
			ob_end_clean();
			if ( $log_handle ) {
				fclose( $log_handle );
			}
			WP_CLI::error( 'PHPCS run failed: ' . $e->getMessage() );
		}
		$report_output = ob_get_clean();

		// Compute totals from the reporter state.
		$total_errors   = 0;
		$total_warnings = 0;
		$total_fixed    = 0;
		if ( isset( $runner->reporter ) && is_object( $runner->reporter ) ) {
			if ( isset( $runner->reporter->totalErrors ) ) {
				$total_errors   = (int) $runner->reporter->totalErrors;
			}
			if ( isset( $runner->reporter->totalWarnings ) ) {
				$total_warnings = (int) $runner->reporter->totalWarnings;
			}
			if ( isset( $runner->reporter->totalFixed ) ) {
				$total_fixed    = (int) $runner->reporter->totalFixed;
			}
		}

		if ( ! $summary_only ) {
			WP_CLI::log( $report_output );
		}

		// Write the report body to the debug log so the host can read it.
		if ( $log_handle ) {
			fwrite( $log_handle, $report_output );
		}

		// Always print machine-readable totals as the last line.
		WP_CLI::log(
			sprintf(
				'SMR_PHPCS_TOTALS errors=%d warnings=%d fixes=%d files=%d',
				$total_errors,
				$total_warnings,
				$total_fixed,
				count( $config->files )
			)
		);

		if ( $log_handle ) {
			fwrite( $log_handle, sprintf( "SMR_PHPCS_TOTALS errors=%d warnings=%d fixes=%d files=%d\n", $total_errors, $total_warnings, $total_fixed, count( $config->files ) ) );
			fclose( $log_handle );
		}

		if ( $total_errors > 0 ) {
			WP_CLI::halt( $total_errors );
		}
		WP_CLI::success( 'No PHPCS errors found.' );
	}

	/**
	 * Load PHPCS source from a directory tree.
	 *
	 * PHPCS source includes an autoload.php that registers a SPL autoloader
	 * in the PHP_CodeSniffer\Autoload class. Loading that autoloader brings
	 * every class in.
	 *
	 * @param string $phpcs_src Absolute path to PHPCS source dir.
	 */
	private static function load_phpcs( $phpcs_src ) {
		if ( self::$phpcs_loaded ) {
			return;
		}

		$autoload = $phpcs_src . '/autoload.php';
		if ( ! file_exists( $autoload ) ) {
			WP_CLI::error( "PHPCS autoload.php missing at {$autoload}" );
		}
		require_once $autoload;

		self::$phpcs_loaded = true;
	}

	/**
	 * Recursively collect .php files under a path.
	 *
	 * @param string $path Root path.
	 * @return array List of absolute file paths.
	 */
	private static function collect_files( $path ) {
		$rii = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $path, \RecursiveDirectoryIterator::SKIP_DOTS )
		);
		$files = array();
		foreach ( $rii as $file ) {
			if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
				// Skip phpcs vendor test area.
				if ( false !== strpos( $file->getPathname(), '/phpcs-vendor/' ) ) {
					continue;
				}
				$files[] = $file->getPathname();
			}
		}
		sort( $files );
		return $files;
	}
}