<?php
/**
 * Plugin Name: Fixit
 * Plugin URI:  https://github.com/tamm/fixit
 * Description: WordPress migration toolkit. Prepare migrations before moving, emergency-fix broken moves, or audit stale URLs left behind by past migrations.
 * Version:     2.0.0
 * Author:      Tamm Sjödin
 * Author URI:  https://github.com/tamm
 * License:     MIT
 * License URI: https://opensource.org/licenses/MIT
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Text Domain: fixit
 */

defined( 'ABSPATH' ) || exit;

define( 'FIXIT_VERSION', '2.0.0' );
define( 'FIXIT_FILE', __FILE__ );
define( 'FIXIT_DIR', plugin_dir_path( __FILE__ ) );

/* =========================================================================
   MIGRATION INTERCEPT — runs before WordPress tries to redirect anywhere
   ========================================================================= */

add_action( 'plugins_loaded', 'fixit_migration_intercept', 0 );

function fixit_migration_intercept() {
	if ( ! isset( $_GET['fixit_migrate'] ) ) {
		return;
	}

	$token = get_option( 'fixit_migration_token' );
	if ( ! $token || ! hash_equals( $token, sanitize_text_field( $_GET['fixit_migrate'] ) ) ) {
		fixit_standalone_page( 'Fixit — Invalid Token', '<p>The migration token is invalid or has expired.</p><p>If you prepared this migration, go back to the <strong>old</strong> site\'s wp-admin &rarr; Tools &rarr; Fixit to find the correct URL.</p>' );
		exit;
	}

	$migration = get_option( 'fixit_migration_data' );
	if ( ! $migration || ! is_array( $migration ) ) {
		fixit_standalone_page( 'Fixit — No Migration Data', '<p>No pending migration was found. It may have already been completed.</p>' );
		exit;
	}

	$old_url = $migration['source_url'];

	if ( ! empty( $migration['target_url'] ) ) {
		$new_url = $migration['target_url'];
	} else {
		$new_url = fixit_detect_current_url();
	}

	$results = fixit_search_replace( $old_url, $new_url );

	delete_option( 'fixit_migration_token' );
	delete_option( 'fixit_migration_data' );
	set_transient( 'fixit_migration_complete', true, 3600 );

	$admin_url = esc_url( $new_url . '/wp-admin/' );

	ob_start();
	?>
	<h2>Migration complete</h2>
	<table style="margin:1.5em 0;border-collapse:collapse">
		<tr><td style="padding:4px 12px 4px 0;color:#666">Replaced</td><td><code><?php echo esc_html( $old_url ); ?></code></td></tr>
		<tr><td style="padding:4px 12px 4px 0;color:#666">With</td><td><code><?php echo esc_html( $new_url ); ?></code></td></tr>
	</table>
	<table style="margin:1.5em 0;border-collapse:collapse">
		<tr><td style="padding:4px 12px 4px 0;color:#666">Tables scanned</td><td><strong><?php echo (int) $results['tables']; ?></strong></td></tr>
		<tr><td style="padding:4px 12px 4px 0;color:#666">Columns scanned</td><td><strong><?php echo (int) $results['columns']; ?></strong></td></tr>
		<tr><td style="padding:4px 12px 4px 0;color:#666">Rows updated</td><td><strong><?php echo (int) $results['rows_updated']; ?></strong></td></tr>
		<tr><td style="padding:4px 12px 4px 0;color:#666">Serialized fields fixed</td><td><strong><?php echo (int) $results['serialized_rows']; ?></strong></td></tr>
		<tr><td style="padding:4px 12px 4px 0;color:#666">Serialized occurrences</td><td><strong><?php echo (int) $results['serialized_occurrences']; ?></strong></td></tr>
	</table>
	<?php if ( ! empty( $results['errors'] ) ) : ?>
		<div style="background:#fef0f0;border-left:4px solid #d63638;padding:10px 14px;margin:1em 0">
			<strong>Errors:</strong>
			<ul style="margin:0.5em 0 0 1.2em"><?php foreach ( $results['errors'] as $e ) { echo '<li>' . esc_html( $e ) . '</li>'; } ?></ul>
		</div>
	<?php endif; ?>
	<p style="margin-top:2em">
		<a href="<?php echo $admin_url; ?>" style="display:inline-block;padding:10px 24px;background:#2271b1;color:#fff;text-decoration:none;border-radius:4px;font-weight:600">Go to Dashboard &rarr;</a>
	</p>
	<p style="color:#666;font-size:0.9em">You can safely remove Fixit from Plugins once you've confirmed everything works.</p>
	<?php
	fixit_standalone_page( 'Fixit — Migration Complete', ob_get_clean() );
	exit;
}

/* =========================================================================
   ADMIN NOTICE after migration
   ========================================================================= */

add_action( 'admin_notices', 'fixit_admin_notices' );

function fixit_admin_notices() {
	if ( get_transient( 'fixit_migration_complete' ) ) {
		delete_transient( 'fixit_migration_complete' );
		echo '<div class="notice notice-success is-dismissible"><p><strong>Fixit:</strong> Migration completed successfully. You can <a href="' . esc_url( admin_url( 'plugins.php' ) ) . '">deactivate and remove Fixit</a> if you no longer need it.</p></div>';
	}
}

/* =========================================================================
   ADMIN MENU
   ========================================================================= */

add_action( 'admin_menu', 'fixit_admin_menu' );

function fixit_admin_menu() {
	add_management_page(
		'Fixit — WordPress Migration',
		'Fixit',
		'manage_options',
		'fixit',
		'fixit_admin_page'
	);
}

/* =========================================================================
   ADMIN FORM HANDLERS
   ========================================================================= */

add_action( 'admin_init', 'fixit_handle_actions' );

function fixit_handle_actions() {
	if ( ! isset( $_POST['fixit_action'] ) || ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$action = sanitize_text_field( $_POST['fixit_action'] );

	// --- Prepare migration ---
	if ( $action === 'prepare_migration' ) {
		check_admin_referer( 'fixit_prepare_migration' );

		$target = isset( $_POST['fixit_target_url'] ) ? esc_url_raw( trim( $_POST['fixit_target_url'] ) ) : '';
		$target = untrailingslashit( $target );

		$token  = bin2hex( random_bytes( 16 ) );
		$source = untrailingslashit( site_url() );

		update_option( 'fixit_migration_token', $token, false );
		update_option( 'fixit_migration_data', array(
			'source_url' => $source,
			'target_url' => $target,
			'created'    => current_time( 'mysql' ),
		), false );

		wp_safe_redirect( admin_url( 'tools.php?page=fixit&tab=migrate&prepared=1' ) );
		exit;
	}

	// --- Cancel migration ---
	if ( $action === 'cancel_migration' ) {
		check_admin_referer( 'fixit_cancel_migration' );
		delete_option( 'fixit_migration_token' );
		delete_option( 'fixit_migration_data' );

		wp_safe_redirect( admin_url( 'tools.php?page=fixit&tab=migrate&cancelled=1' ) );
		exit;
	}

	// --- Quick replace (URL audit) ---
	if ( $action === 'quick_replace' ) {
		check_admin_referer( 'fixit_quick_replace' );

		$search  = isset( $_POST['fixit_search'] ) ? sanitize_text_field( wp_unslash( $_POST['fixit_search'] ) ) : '';
		$replace = isset( $_POST['fixit_replace'] ) ? sanitize_text_field( wp_unslash( $_POST['fixit_replace'] ) ) : '';

		if ( $search && $replace && $search !== $replace ) {
			$results = fixit_search_replace( $search, $replace );
			set_transient( 'fixit_last_replace', $results, 300 );
		}

		wp_safe_redirect( admin_url( 'tools.php?page=fixit&tab=audit&replaced=1' ) );
		exit;
	}
}

/* =========================================================================
   ADMIN PAGE RENDERER
   ========================================================================= */

function fixit_admin_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'migrate';
	$tabs = array(
		'migrate'   => 'Prepare Migration',
		'audit'     => 'URL Audit',
		'emergency' => 'Emergency',
	);
	?>
	<div class="wrap">
		<h1>Fixit <small style="font-weight:normal;color:#999">v<?php echo esc_html( FIXIT_VERSION ); ?></small></h1>

		<nav class="nav-tab-wrapper">
			<?php foreach ( $tabs as $slug => $label ) : ?>
				<a href="<?php echo esc_url( admin_url( 'tools.php?page=fixit&tab=' . $slug ) ); ?>"
				   class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>">
					<?php echo esc_html( $label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<div style="margin-top:20px">
			<?php
			switch ( $tab ) {
				case 'migrate':
					fixit_tab_migrate();
					break;
				case 'audit':
					fixit_tab_audit();
					break;
				case 'emergency':
					fixit_tab_emergency();
					break;
			}
			?>
		</div>
	</div>
	<?php
}

/* ----- Tab: Prepare Migration ------------------------------------------ */

function fixit_tab_migrate() {
	$migration = get_option( 'fixit_migration_data' );
	$token     = get_option( 'fixit_migration_token' );

	if ( isset( $_GET['cancelled'] ) ) {
		echo '<div class="notice notice-info inline"><p>Migration cancelled.</p></div>';
	}

	if ( $migration && $token ) {
		// --- Migration is prepared, show status ---
		$source = esc_html( $migration['source_url'] );
		$target = ! empty( $migration['target_url'] ) ? $migration['target_url'] : false;
		$created = esc_html( $migration['created'] );

		if ( $target ) {
			$magic_url = esc_url( $target . '/?fixit_migrate=' . $token );
		}

		if ( isset( $_GET['prepared'] ) ) {
			echo '<div class="notice notice-success inline"><p>Migration prepared.</p></div>';
		}
		?>
		<div class="card" style="max-width:700px">
			<h2 style="margin-top:0">Migration Ready</h2>
			<p>Prepared on <?php echo $created; ?></p>

			<table class="form-table" role="presentation">
				<tr>
					<th>Source</th>
					<td><code><?php echo $source; ?></code></td>
				</tr>
				<tr>
					<th>Target</th>
					<td>
						<?php if ( $target ) : ?>
							<code><?php echo esc_html( $target ); ?></code>
						<?php else : ?>
							<em>Auto-detect (will use the domain you visit the migration URL from)</em>
						<?php endif; ?>
					</td>
				</tr>
			</table>

			<h3>Your migration URL</h3>
			<?php if ( $target ) : ?>
				<p>After copying your WordPress files and database to the new location, visit:</p>
				<input type="text" value="<?php echo $magic_url; ?>" readonly
				       onclick="this.select()" style="width:100%;font-size:14px;padding:8px;font-family:monospace">
			<?php else : ?>
				<p>After copying your WordPress files and database, visit this URL on the <strong>new</strong> domain:</p>
				<input type="text" value="/?fixit_migrate=<?php echo esc_attr( $token ); ?>" readonly
				       onclick="this.select()" style="width:100%;font-size:14px;padding:8px;font-family:monospace">
				<p class="description">Prepend your new domain, e.g. <code>https://newdomain.com/?fixit_migrate=<?php echo esc_html( $token ); ?></code></p>
			<?php endif; ?>

			<h3 style="margin-top:2em">What happens next</h3>
			<ol>
				<li>Copy your WordPress files and database to the new server / domain.</li>
				<li>Visit the migration URL above on the new domain.</li>
				<li>Fixit will replace <code><?php echo $source; ?></code> with the new URL everywhere in the database.</li>
				<li>Your site works on the new domain. Done.</li>
			</ol>
		</div>

		<form method="post" style="margin-top:16px">
			<?php wp_nonce_field( 'fixit_cancel_migration' ); ?>
			<input type="hidden" name="fixit_action" value="cancel_migration">
			<button type="submit" class="button" onclick="return confirm('Cancel the prepared migration?')">Cancel Migration</button>
		</form>
		<?php
	} else {
		// --- No migration pending, show setup form ---
		$current_url = untrailingslashit( site_url() );
		?>
		<div class="card" style="max-width:700px">
			<h2 style="margin-top:0">Prepare a Migration</h2>
			<p>Set up your migration <strong>before</strong> you move. Fixit will store the current URL and generate
			   a one-click migration link for your new location.</p>

			<form method="post">
				<?php wp_nonce_field( 'fixit_prepare_migration' ); ?>
				<input type="hidden" name="fixit_action" value="prepare_migration">

				<table class="form-table" role="presentation">
					<tr>
						<th><label>Current Site URL</label></th>
						<td><code><?php echo esc_html( $current_url ); ?></code></td>
					</tr>
					<tr>
						<th><label for="fixit_target_url">Target URL</label></th>
						<td>
							<input type="url" id="fixit_target_url" name="fixit_target_url"
							       placeholder="https://newdomain.com" class="regular-text" value="">
							<p class="description">
								Leave blank to auto-detect from wherever you open the migration link.
								<br>Include the full URL with scheme, e.g. <code>https://newdomain.com</code> or <code>https://example.com/blog</code>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" class="button button-primary">Prepare Migration</button>
				</p>
			</form>
		</div>
		<?php
	}
}

/* ----- Tab: URL Audit -------------------------------------------------- */

function fixit_tab_audit() {
	$current_url = untrailingslashit( site_url() );
	$last_replace = get_transient( 'fixit_last_replace' );

	if ( isset( $_GET['replaced'] ) && $last_replace ) {
		delete_transient( 'fixit_last_replace' );
		?>
		<div class="notice notice-success inline">
			<p><strong>Replacement complete.</strong>
				<?php echo (int) $last_replace['rows_updated']; ?> rows updated,
				<?php echo (int) $last_replace['serialized_rows']; ?> serialized fields fixed.</p>
		</div>
		<?php
	}
	?>
	<div class="card" style="max-width:700px">
		<h2 style="margin-top:0">URL Audit</h2>
		<p>Scan <strong>every table</strong> in your database for URLs — WordPress core, plugin tables,
		   custom tables, everything. Finds remnants of old domains, partial migrations, or stale URLs
		   hiding in serialized data.</p>

		<p><button type="button" class="button button-primary" id="fixit-scan-btn">Scan Database</button></p>

		<div id="fixit-scan-results" style="display:none">
			<h3>Domains found</h3>
			<table class="widefat striped" id="fixit-domains-table">
				<thead>
					<tr>
						<th style="width:50%">Domain</th>
						<th>Occurrences</th>
						<th>Status</th>
					</tr>
				</thead>
				<tbody></tbody>
			</table>
		</div>
	</div>

	<div class="card" style="max-width:700px;margin-top:20px">
		<h2 style="margin-top:0">Search &amp; Replace</h2>
		<p>Replace a specific URL across the entire database (serialization-aware).</p>
		<form method="post">
			<?php wp_nonce_field( 'fixit_quick_replace' ); ?>
			<input type="hidden" name="fixit_action" value="quick_replace">
			<table class="form-table" role="presentation">
				<tr>
					<th><label for="fixit_search">Search for</label></th>
					<td><input type="text" id="fixit_search" name="fixit_search" class="regular-text" placeholder="https://old-domain.com" required></td>
				</tr>
				<tr>
					<th><label for="fixit_replace">Replace with</label></th>
					<td>
						<input type="text" id="fixit_replace" name="fixit_replace" class="regular-text"
						       value="<?php echo esc_attr( $current_url ); ?>" required>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" class="button button-primary"
				        onclick="return confirm('This will search and replace across your entire database. Proceed?')">
					Replace
				</button>
			</p>
		</form>
	</div>

	<script>
	(function(){
		var btn = document.getElementById('fixit-scan-btn');
		var results = document.getElementById('fixit-scan-results');
		var tbody = document.querySelector('#fixit-domains-table tbody');
		var currentUrl = <?php echo wp_json_encode( $current_url ); ?>;

		btn.addEventListener('click', function(){
			btn.disabled = true;
			btn.textContent = 'Scanning\u2026';
			tbody.innerHTML = '';
			results.style.display = 'none';

			var xhr = new XMLHttpRequest();
			xhr.open('POST', ajaxurl);
			xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
			xhr.onload = function(){
				btn.disabled = false;
				btn.textContent = 'Scan Database';
				try {
					var resp = JSON.parse(xhr.responseText);
					if (resp.success && resp.data.domains) {
						var domains = resp.data.domains;
						if (domains.length === 0) {
							tbody.innerHTML = '<tr><td colspan="3">No URLs found.</td></tr>';
						} else {
							domains.sort(function(a,b){ return b.count - a.count; });
							domains.forEach(function(d){
								var isCurrent = (d.url === currentUrl);
								var tableList = d.tables ? d.tables.join(', ') : '';
								var tr = document.createElement('tr');
								tr.innerHTML =
									'<td><code>' + escHtml(d.url) + '</code>' +
									(tableList ? '<br><small style="color:#999">in: ' + escHtml(tableList) + '</small>' : '') +
									'</td>' +
									'<td>' + d.count + '</td>' +
									'<td>' + (isCurrent
										? '<span style="color:#00a32a">&#10003; Current site</span>'
										: '<span style="color:#b32d2e">Foreign</span>') + '</td>';
								tbody.appendChild(tr);
							});
						}
						results.style.display = '';
					}
				} catch(e) {
					alert('Scan failed. Check the browser console.');
					console.error(e, xhr.responseText);
				}
			};
			xhr.send('action=fixit_scan_urls&_wpnonce=' + <?php echo wp_json_encode( wp_create_nonce( 'fixit_scan_urls' ) ); ?>);
		});

		function escHtml(s){
			var d = document.createElement('div');
			d.appendChild(document.createTextNode(s));
			return d.innerHTML;
		}
	})();
	</script>
	<?php
}

/* ----- Tab: Emergency -------------------------------------------------- */

function fixit_tab_emergency() {
	$emergency_file = FIXIT_DIR . 'fixit-emergency.php';
	$exists = file_exists( $emergency_file );
	?>
	<div class="card" style="max-width:700px">
		<h2 style="margin-top:0">Emergency Standalone Fix</h2>
		<p>If your WordPress site is broken after a move and you <strong>can't access wp-admin</strong>,
		   use the standalone emergency script.</p>

		<h3>How to use</h3>
		<ol>
			<li>Connect to your server via FTP or SSH.</li>
			<li>
				Copy <code>fixit-emergency.php</code> from the plugin directory to your <strong>WordPress root</strong>
				(the same directory as <code>wp-config.php</code>).
				<?php if ( $exists ) : ?>
					<br><code style="font-size:0.85em"><?php echo esc_html( $emergency_file ); ?></code>
				<?php else : ?>
					<br><span style="color:#b32d2e">File not found in plugin directory.</span>
				<?php endif; ?>
			</li>
			<li>Visit <code>https://your-domain.com/fixit-emergency.php</code> in your browser.</li>
			<li>Verify the detected old and new URLs, then click <strong>Fix It</strong>.</li>
			<li>After fixing, click <strong>Delete this file</strong> to clean up.</li>
		</ol>

		<div style="background:#fef8ee;border-left:4px solid #dba617;padding:10px 14px;margin:1em 0">
			<strong>Security note:</strong> The emergency file gives direct database access.
			Always delete it immediately after use.
		</div>
	</div>
	<?php
}

/* =========================================================================
   AJAX: Scan URLs
   ========================================================================= */

add_action( 'wp_ajax_fixit_scan_urls', 'fixit_ajax_scan_urls' );

function fixit_ajax_scan_urls() {
	check_ajax_referer( 'fixit_scan_urls' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( 'Unauthorized' );
	}

	global $wpdb;

	$domains    = array();
	$tables_hit = array();

	// Walk EVERY table in the database — not just core WP tables.
	// Plugin tables (WooCommerce, ACF, etc.), custom tables, everything.
	$tables = $wpdb->get_col( 'SHOW TABLES' );

	foreach ( $tables as $table ) {
		// Get all text-type columns in this table.
		$columns = $wpdb->get_results(
			"SHOW COLUMNS FROM `{$table}` WHERE Type LIKE '%char%' OR Type LIKE '%text%' OR Type LIKE '%blob%'"
		);

		if ( ! $columns ) {
			continue;
		}

		foreach ( $columns as $col ) {
			$col_name = $col->Field;

			$rows = $wpdb->get_col(
				"SELECT `{$col_name}` FROM `{$table}` WHERE `{$col_name}` LIKE '%http://%' OR `{$col_name}` LIKE '%https://%'"
			);

			foreach ( $rows as $value ) {
				// Handle serialized data — peek inside it for URLs too.
				if ( is_serialized( $value ) ) {
					$unserialized = @unserialize( $value );
					if ( false !== $unserialized || 'b:0;' === $value ) {
						$value = fixit_flatten_to_string( $unserialized );
					}
				}

				if ( preg_match_all( '#https?://[a-zA-Z0-9._-]+(?:\:[0-9]+)?#', $value, $matches ) ) {
					foreach ( $matches[0] as $url ) {
						$url = rtrim( strtolower( $url ), '/' );
						if ( ! isset( $domains[ $url ] ) ) {
							$domains[ $url ] = array( 'count' => 0, 'tables' => array() );
						}
						$domains[ $url ]['count']++;
						$domains[ $url ]['tables'][ $table ] = true;
					}
				}
			}
		}
	}

	// Format for response — include which tables each domain was found in.
	$result = array();
	foreach ( $domains as $url => $info ) {
		$result[] = array(
			'url'    => $url,
			'count'  => $info['count'],
			'tables' => array_keys( $info['tables'] ),
		);
	}

	wp_send_json_success( array( 'domains' => $result ) );
}

/* =========================================================================
   DATABASE ENGINE — serialization-aware search & replace
   ========================================================================= */

function fixit_search_replace( $search, $replace ) {
	global $wpdb;

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

			// Serialized data: must unserialize, replace, reserialize to preserve string lengths.
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
				$unserialized = fixit_recursive_replace( $search, $replace, $unserialized, $count );
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

function fixit_recursive_replace( $search, $replace, $data, &$count = 0 ) {
	if ( is_array( $data ) ) {
		foreach ( $data as $key => $value ) {
			$data[ $key ] = fixit_recursive_replace( $search, $replace, $value, $count );
		}
	} elseif ( is_object( $data ) ) {
		foreach ( get_object_vars( $data ) as $key => $value ) {
			$data->$key = fixit_recursive_replace( $search, $replace, $value, $count );
		}
	} elseif ( is_string( $data ) ) {
		$count += substr_count( $data, $search );
		$data = str_replace( $search, $replace, $data );
	}
	return $data;
}

/* =========================================================================
   UTILITIES
   ========================================================================= */

/**
 * Recursively extract all string values from a nested array/object
 * into a single string for URL scanning.
 */
function fixit_flatten_to_string( $data ) {
	$parts = array();
	if ( is_array( $data ) ) {
		foreach ( $data as $value ) {
			$parts[] = fixit_flatten_to_string( $value );
		}
	} elseif ( is_object( $data ) ) {
		foreach ( get_object_vars( $data ) as $value ) {
			$parts[] = fixit_flatten_to_string( $value );
		}
	} elseif ( is_string( $data ) ) {
		return $data;
	}
	return implode( ' ', $parts );
}

function fixit_detect_current_url() {
	$is_ssl = ( ! empty( $_SERVER['HTTPS'] ) && 'off' !== $_SERVER['HTTPS'] )
	           || ( ! empty( $_SERVER['SERVER_PORT'] ) && 443 == $_SERVER['SERVER_PORT'] );
	$scheme = $is_ssl ? 'https' : 'http';
	$host   = sanitize_text_field( $_SERVER['HTTP_HOST'] );

	// Determine the subdirectory WordPress lives in.
	$path = '';
	$doc_root = ! empty( $_SERVER['DOCUMENT_ROOT'] ) ? realpath( $_SERVER['DOCUMENT_ROOT'] ) : false;
	$wp_root  = realpath( ABSPATH );

	if ( $doc_root && $wp_root && 0 === strpos( $wp_root, $doc_root ) ) {
		$path = substr( $wp_root, strlen( $doc_root ) );
	}

	return $scheme . '://' . $host . rtrim( $path, '/' );
}

/**
 * Render a self-contained HTML page (used by migration intercept,
 * which runs before themes are available).
 */
function fixit_standalone_page( $title, $body ) {
	header( 'Content-Type: text/html; charset=utf-8' );
	?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?php echo esc_html( $title ); ?></title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Oxygen,Ubuntu,sans-serif;
     background:#f0f0f1;color:#1d2327;line-height:1.6;padding:40px 20px}
.wrap{max-width:640px;margin:0 auto;background:#fff;border-radius:8px;
      box-shadow:0 1px 3px rgba(0,0,0,.08);padding:32px 40px}
h1{font-size:22px;margin-bottom:4px}
h2{font-size:18px;margin:1.2em 0 0.5em}
h3{font-size:15px;margin:1.2em 0 0.5em}
code{background:#f0f0f1;padding:2px 6px;border-radius:3px;font-size:0.9em}
p{margin:0.6em 0}
.brand{color:#2271b1;font-size:13px;letter-spacing:1px;text-transform:uppercase;margin-bottom:12px}
</style>
</head>
<body>
<div class="wrap">
	<div class="brand">Fixit</div>
	<h1><?php echo $title; ?></h1>
	<?php echo $body; ?>
</div>
</body>
</html>
	<?php
}
