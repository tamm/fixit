<?php
/**
 * Fixit — Uninstall handler.
 * Removes plugin options from the database when deleted via wp-admin.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'fixit_migration_token' );
delete_option( 'fixit_migration_data' );
delete_transient( 'fixit_migration_complete' );
delete_transient( 'fixit_last_replace' );
