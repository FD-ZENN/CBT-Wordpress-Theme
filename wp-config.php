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
define( 'DB_NAME', 'wp_ujiankuu' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

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
define( 'AUTH_KEY',         'u:[gpn1B8?ZBVL}:dcycch+(p=_#g9Kfj ;%+}Yw_KwO!i6!S^!d?&4GG4O gHHU' );
define( 'SECURE_AUTH_KEY',  'npwERq)!I-@R3&L;o$G2Y{==v@? #`T,}UJx<L&7Kb7JXlqgyJBhYOYJp~Jt`{X*' );
define( 'LOGGED_IN_KEY',    'u~C9^ch$/Pr;.{DfPBv$KSyT)[QDH]vN(0_WmeGS~Gv,#ocR2+BURf?D,aJGZ2,{' );
define( 'NONCE_KEY',        'j>^4m:+9gj,Wh3*]Q[SYSe;q~BRn30jd$-?iuZ0]ut?8qi#,R[-{lE9>Zvp ;2.R' );
define( 'AUTH_SALT',        'd#L8Q3@fRB7QntGb}u`Ci;Q6MZ|[F$(tY2xF`oPJuv_2W^!E_Mph*Bqsyus#|PRB' );
define( 'SECURE_AUTH_SALT', 'NF(Vug!*QE5W<Osfk-YWZ{x`3W8luAlT(}(H6e[#lix?cek|:08Cl}T:z_v9SkV+' );
define( 'LOGGED_IN_SALT',   '=7I>GJP;]w  pYu{x}&2JOMMM1B55yi2C$b,=6AW5bUd^.+lii*AStevcTU*}|ZI' );
define( 'NONCE_SALT',       'jR/ye}EUDh<9@Y5[Hf{`p|(mnKPDY_@N:R#0NHwef3e^TTmb@`Kcc_/}8h6D8s7q' );

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
$table_prefix = 'wpx_';

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
define('WP_DEBUG', false);
define('WP_DEBUG_LOG', false);
define('SCRIPT_DEBUG', false);

/* Add any custom values between this line and the "stop editing" line. */
define('DISABLE_WP_CRON', true);
define('WP_MEMORY_LIMIT', '4096M');
define('WP_MAX_MEMORY_LIMIT', '4096M');

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
