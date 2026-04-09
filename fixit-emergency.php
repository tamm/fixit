<?php
/**
 * Fixit Emergency — Standalone WordPress migration rescue script.
 *
 * Usage:
 *   1. Copy this file into your WordPress root (next to wp-config.php).
 *   2. Visit https://your-domain.com/fixit-emergency.php
 *   3. Verify the detected URLs, click "Fix It".
 *   4. Delete this file when done.
 *
 * This file uses WordPress's SHORTINIT mode to get database access ($wpdb)
 * without booting themes, plugins, or the rest of WordPress.
 *
 * @version 2.0.0
 * @author  Tamm Sjödin
 * @license MIT
 */

// --- Self-delete handler (runs before anything else) -----------------------
if ( isset( $_POST['fixit_emergency_delete'] ) ) {
	$nonce_file = __DIR__ . '/.fixit-nonce';
	$stored     = file_exists( $nonce_file ) ? trim( file_get_contents( $nonce_file ) ) : '';
	if ( $stored && hash_equals( $stored, $_POST['fixit_emergency_delete'] ) ) {
		@unlink( $nonce_file );
		@unlink( __FILE__ );
		header( 'Content-Type: text/html; charset=utf-8' );
		echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Fixit — Deleted</title></head>';
		echo '<body style="font-family:sans-serif;text-align:center;padding:60px">';
		echo '<h2>Fixit emergency file deleted.</h2>';
		echo '<p><a href="/">Go to your site &rarr;</a></p>';
		echo '</body></html>';
		exit;
	}
}

// --- Bootstrap WordPress (minimal) ----------------------------------------
define( 'ABSPATH', __DIR__ . '/' );
define( 'SHORTINIT', true );

$wp_load = ABSPATH . 'wp-load.php';
if ( ! file_exists( $wp_load ) ) {
	fixit_emergency_page( 'Error', '<p class="error">Cannot find <code>wp-load.php</code>. Make sure this file is in the WordPress root directory (next to wp-config.php).</p>' );
	exit;
}

// Suppress warnings from wp-settings loading (some hosts trigger notices).
$error_level = error_reporting();
error_reporting( E_ERROR | E_PARSE );
require_once $wp_load;
error_reporting( $error_level );

// At this point we have $wpdb and core functions like is_serialized().
global $wpdb, $table_prefix;

if ( ! $wpdb || ! $wpdb->ready ) {
	fixit_emergency_page( 'Database Error', '<p class="error">Could not connect to the database. Check the credentials in <code>wp-config.php</code>.</p>' );
	exit;
}

// --- Generate / verify nonce -----------------------------------------------
$nonce_file = __DIR__ . '/.fixit-nonce';
if ( ! file_exists( $nonce_file ) ) {
	$nonce = bin2hex( random_bytes( 16 ) );
	file_put_contents( $nonce_file, $nonce );
} else {
	$nonce = trim( file_get_contents( $nonce_file ) );
}

// --- Detect URLs -----------------------------------------------------------
$options_table = $table_prefix . 'options';
$old_url = $wpdb->get_var( "SELECT option_value FROM `{$options_table}` WHERE option_name = 'siteurl'" );

$is_ssl = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] )
           || ( ! empty( $_SERVER['SERVER_PORT'] ) && 443 == $_SERVER['SERVER_PORT'] );
$scheme  = $is_ssl ? 'https' : 'http';
$host    = $_SERVER['HTTP_HOST'];
$script  = $_SERVER['SCRIPT_NAME'];
$dir     = substr( $script, 0, strrpos( $script, '/' ) );
$new_url = $scheme . '://' . $host . ( $dir ? $dir : '' );

// --- Handle replacement ----------------------------------------------------
$results  = null;
$error    = '';

if ( isset( $_POST['fixit_emergency_run'] ) ) {
	if ( ! hash_equals( $nonce, $_POST['fixit_emergency_nonce'] ?? '' ) ) {
		$error = 'Invalid security token. Please reload and try again.';
	} else {
		$search  = trim( $_POST['search'] ?? '' );
		$replace = trim( $_POST['replace'] ?? '' );

		if ( ! $search || ! $replace ) {
			$error = 'Both search and replace fields are required.';
		} elseif ( $search === $replace ) {
			$error = 'Search and replace values are identical.';
		} else {
			$results = fixit_emergency_replace( $wpdb, $search, $replace );
		}
	}
}

// --- Render page -----------------------------------------------------------
ob_start();

if ( $error ) {
	echo '<p class="error">' . htmlspecialchars( $error ) . '</p>';
}

if ( $results ) {
	// Show results
	?>
	<h2>Done</h2>
	<table class="info">
		<tr><td>Replaced</td><td><code><?php echo htmlspecialchars( $_POST['search'] ); ?></code></td></tr>
		<tr><td>With</td><td><code><?php echo htmlspecialchars( $_POST['replace'] ); ?></code></td></tr>
	</table>
	<table class="info">
		<tr><td>Tables scanned</td><td><strong><?php echo $results['tables']; ?></strong></td></tr>
		<tr><td>Columns scanned</td><td><strong><?php echo $results['columns']; ?></strong></td></tr>
		<tr><td>Rows updated</td><td><strong><?php echo $results['rows_updated']; ?></strong></td></tr>
		<tr><td>Serialized fields fixed</td><td><strong><?php echo $results['serialized_rows']; ?></strong></td></tr>
		<tr><td>Serialized occurrences</td><td><strong><?php echo $results['serialized_occurrences']; ?></strong></td></tr>
	</table>
	<?php if ( ! empty( $results['errors'] ) ) : ?>
		<div class="warning">
			<strong>Warnings:</strong>
			<ul><?php foreach ( $results['errors'] as $e ) { echo '<li>' . htmlspecialchars( $e ) . '</li>'; } ?></ul>
		</div>
	<?php endif; ?>
	<p style="margin-top:1.5em"><a href="/" class="btn">&larr; Go to your site</a></p>

	<hr style="margin:2em 0;border:none;border-top:1px solid #ddd">
	<h3>Clean up</h3>
	<p>You should delete this file now. It provides direct database access and should not remain on a live server.</p>
	<form method="post" style="margin-top:0.5em">
		<input type="hidden" name="fixit_emergency_delete" value="<?php echo htmlspecialchars( $nonce ); ?>">
		<button type="submit" class="btn btn-danger" onclick="return confirm('Delete fixit-emergency.php from the server?')">Delete this file</button>
	</form>
	<?php
} else {
	// Show form
	?>
	<h2>Fix it</h2>
	<form method="post" id="fixit-form">
		<input type="hidden" name="fixit_emergency_run" value="1">
		<input type="hidden" name="fixit_emergency_nonce" value="<?php echo htmlspecialchars( $nonce ); ?>">

		<div class="field">
			<label for="search">Search (old URL)</label>
			<input type="text" id="search" name="search"
			       value="<?php echo htmlspecialchars( $old_url ); ?>">
		</div>
		<div class="field">
			<label for="replace">Replace (new URL)</label>
			<input type="text" id="replace" name="replace"
			       value="<?php echo htmlspecialchars( $new_url ); ?>">
		</div>

		<button type="submit" class="btn btn-primary" onclick="return confirm('Replace all occurrences in the database?')">Fix It &rarr;</button>
	</form>

	<hr style="margin:2em 0;border:none;border-top:1px solid #ddd">
	<h3>Clean up without fixing</h3>
	<p>If you don't need this anymore:</p>
	<form method="post" style="margin-top:0.5em">
		<input type="hidden" name="fixit_emergency_delete" value="<?php echo htmlspecialchars( $nonce ); ?>">
		<button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete fixit-emergency.php?')">Delete this file</button>
	</form>
	<?php
}

fixit_emergency_page( 'Fixit Emergency', ob_get_clean() );
exit;


/* =========================================================================
   ENGINE — search & replace (uses $wpdb from SHORTINIT)
   ========================================================================= */

function fixit_emergency_replace( $wpdb, $search, $replace ) {
	$results = array(
		'tables'                 => 0,
		'columns'                => 0,
		'rows_updated'           => 0,
		'serialized_rows'        => 0,
		'serialized_occurrences' => 0,
		'errors'                 => array(),
	);

	$tables = $wpdb->get_col( 'SHOW TABLES' );
	$results['tables'] = count( $tables );

	foreach ( $tables as $table ) {
		$columns = $wpdb->get_results( "SHOW COLUMNS FROM `{$table}` WHERE Type LIKE '%char%' OR Type LIKE '%text%' OR Type LIKE '%blob%'" );

		foreach ( $columns as $col ) {
			$col_name = $col->Field;
			$results['columns']++;

			// Plain replacement for non-serialized data.
			$wpdb->query( $wpdb->prepare(
				"UPDATE `{$table}` SET `{$col_name}` = REPLACE(`{$col_name}`, %s, %s) WHERE `{$col_name}` NOT LIKE '_:%%' AND `{$col_name}` LIKE %s",
				$search,
				$replace,
				'%' . $wpdb->esc_like( $search ) . '%'
			) );
			$results['rows_updated'] += max( 0, (int) $wpdb->rows_affected );

			// Serialized data.
			$serial_rows = $wpdb->get_col( $wpdb->prepare(
				"SELECT `{$col_name}` FROM `{$table}` WHERE `{$col_name}` LIKE '_:%%' AND `{$col_name}` LIKE %s",
				'%' . $wpdb->esc_like( $search ) . '%'
			) );

			foreach ( $serial_rows as $value ) {
				if ( ! is_serialized( $value ) ) {
					continue;
				}

				$results['serialized_rows']++;
				$unserialized = @unserialize( $value );

				if ( false === $unserialized && 'b:0;' !== $value ) {
					$results['errors'][] = "Could not unserialize a value in {$table}.{$col_name}";
					continue;
				}

				$count = 0;
				$unserialized = fixit_emergency_recursive_replace( $search, $replace, $unserialized, $count );
				$results['serialized_occurrences'] += $count;

				$new_value = serialize( $unserialized );

				$wpdb->query( $wpdb->prepare(
					"UPDATE `{$table}` SET `{$col_name}` = %s WHERE `{$col_name}` = %s",
					$new_value,
					$value
				) );
				$results['rows_updated'] += max( 0, (int) $wpdb->rows_affected );
			}
		}
	}

	return $results;
}

function fixit_emergency_recursive_replace( $search, $replace, $data, &$count = 0 ) {
	if ( is_array( $data ) ) {
		foreach ( $data as $key => $value ) {
			$data[ $key ] = fixit_emergency_recursive_replace( $search, $replace, $value, $count );
		}
	} elseif ( is_object( $data ) ) {
		foreach ( get_object_vars( $data ) as $key => $value ) {
			$data->$key = fixit_emergency_recursive_replace( $search, $replace, $value, $count );
		}
	} elseif ( is_string( $data ) ) {
		$count += substr_count( $data, $search );
		$data = str_replace( $search, $replace, $data );
	}
	return $data;
}


/* =========================================================================
   PAGE RENDERER
   ========================================================================= */

function fixit_emergency_page( $title, $body ) {
	header( 'Content-Type: text/html; charset=utf-8' );
	// Discourage caching and indexing.
	header( 'Cache-Control: no-store, no-cache' );
	header( 'X-Robots-Tag: noindex, nofollow' );
	?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title><?php echo htmlspecialchars( $title ); ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen,Ubuntu,sans-serif;
     background:#f0f0f1;color:#1d2327;line-height:1.6;padding:40px 20px}
.wrap{max-width:540px;margin:0 auto;background:#fff;border-radius:8px;
      box-shadow:0 1px 3px rgba(0,0,0,.08);padding:32px 40px}
.brand{color:#2271b1;font-size:13px;letter-spacing:1px;text-transform:uppercase;margin-bottom:4px}
.brand small{color:#999;letter-spacing:0;text-transform:none}
h1{font-size:18px;margin-bottom:16px;font-weight:600}
h2{font-size:17px;margin:0.8em 0 0.5em}
h3{font-size:14px;margin:1em 0 0.4em}
p{margin:0.5em 0}
code{background:#f0f0f1;padding:2px 6px;border-radius:3px;font-size:0.9em;word-break:break-all}
.field{margin-bottom:1em}
.field label{display:block;font-size:0.85em;color:#666;margin-bottom:4px;font-weight:600}
.field input[type=text]{width:100%;font-size:15px;padding:8px 10px;border:1px solid #c3c4c7;
     border-radius:4px;font-family:monospace}
.field input[type=text]:focus{border-color:#2271b1;outline:2px solid #2271b1;outline-offset:-1px}
.btn{display:inline-block;padding:10px 24px;border-radius:4px;font-size:14px;font-weight:600;
     cursor:pointer;border:none;text-decoration:none;color:#fff;font-family:inherit}
.btn-primary{background:#2271b1}.btn-primary:hover{background:#135e96}
.btn-danger{background:#b32d2e}.btn-danger:hover{background:#8c2324}
.btn-sm{padding:6px 16px;font-size:13px}
.error{color:#b32d2e;background:#fef0f0;border-left:4px solid #d63638;padding:8px 12px;margin:0.8em 0}
.warning{background:#fef8ee;border-left:4px solid #dba617;padding:8px 12px;margin:1em 0}
.warning ul{margin:0.4em 0 0 1.2em}
table.info{margin:1em 0;border-collapse:collapse;width:100%}
table.info td{padding:4px 12px 4px 0;border-bottom:1px solid #f0f0f1}
table.info td:first-child{color:#666;white-space:nowrap}
hr{margin:2em 0;border:none;border-top:1px solid #eee}
</style>
</head>
<body>
<div class="wrap">
	<div class="brand">Fixit Emergency <small>v<?php echo '2.0.0'; ?></small></div>
	<?php echo $body; ?>
</div>
</body>
</html>
	<?php
}
