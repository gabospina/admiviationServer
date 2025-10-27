<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'udktvymy_WPF43');

/** Database username */
define('DB_USER', 'udktvymy_WPF43');

/** Database password */
define('DB_PASSWORD', 'qP3V:l-lAiHazW8Qt');

/** Database hostname */
define('DB_HOST', 'localhost');

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY', '32108687f6818d27ea5205cb850d3d9d6e61db11cba1b32e6e0f6b4bad1fe82d');
define('SECURE_AUTH_KEY', 'f8c8635e346aef89a248ed711076a2cead348cee2fafb52cb514601c109273ef');
define('LOGGED_IN_KEY', 'bd88bdaa470c0510360539c92507f3fbe4975fa0592dbd8259e073b55f65d33e');
define('NONCE_KEY', '0a6a8cb5af5213de75349dbf08a8ac39250d8e56d80bac4c41dd426d19da291b');
define('AUTH_SALT', '41e6b8811f59236ca55fd18e323dca8cf9ccda3bf1c33b10a201c697ef42966d');
define('SECURE_AUTH_SALT', 'b34f2c4f0a2ed7e0bb43e5e8d8742bda8285203c3b2d185d4f7900471bf091e7');
define('LOGGED_IN_SALT', 'be44265fb5dccddc7ffbd0aaa600395ded3793f62d0b063372a1d908a43f5dff');
define('NONCE_SALT', '5bb325b4487558675225e1d150c9ce8569badadd5f90cf1677c24ca420996c4f');

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'U8H_';
define('WP_CRON_LOCK_TIMEOUT', 120);
define('AUTOSAVE_INTERVAL', 300);
define('WP_POST_REVISIONS', 20);
define('EMPTY_TRASH_DAYS', 7);
define('WP_AUTO_UPDATE_CORE', true);

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
