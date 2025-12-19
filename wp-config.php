<?php
define( 'WP_CACHE', true );

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'u460517132_C81p8' );

/** Database username */
define( 'DB_USER', 'u460517132_41IVC' );

/** Database password */
define( 'DB_PASSWORD', 'FRwg8QOuet' );

/** Database hostname */
define( 'DB_HOST', '127.0.0.1' );

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
define( 'AUTH_KEY',          'x{Q_m3z}NsGP>Y&!hws%4TBo-7juuS0Yx3knfI`X@)Ze#<,^jlbclJl[[Dt.RsQ2' );
define( 'SECURE_AUTH_KEY',   'EWs!7Q#:,$+(KTa&z#r7KTYbPc QXBOGMmzlUJuF0B>ihoft#SYkJLeUlS~@IEi|' );
define( 'LOGGED_IN_KEY',     'RHT)26p&UKv+#+S@oFGbW@Un;4&et)o[a3xiok@=AOSIy/fn0[wTJ<Db1?z{,n[7' );
define( 'NONCE_KEY',         ' 36j&=[=hH{de^BqRIsZWn&qoDBV{<KYtpNz.y.j/P4a0~>xQj,xM+uTZ6H i<_D' );
define( 'AUTH_SALT',         '83=00t]4:p/FEC,^aIk7l{B{eN>X{l?[c.ILlr7#zQOXfFOvPoLo@}^DD)/qd5(N' );
define( 'SECURE_AUTH_SALT',  'p9QP0 j@dL=T?RyE^$%hO0SW?-_bxnr6_^xh5Zp7tg4ut,Rvkj.3);H `b#.(t>[' );
define( 'LOGGED_IN_SALT',    'IVhtik+F!<w];yYxY`^Djf3Yw^N6?KL=yQ6=Lkh~w{ur[O9VYos%ie>zX_4v}xx|' );
define( 'NONCE_SALT',        '|;T@=BKnJtGRWjy}TAr} Z!`HRExu=%+eT/rz~h qZRS&IV8WPXlhxbJH;SDh4Z:' );
define( 'WP_CACHE_KEY_SALT', '}dRlkfVopQCf[J_eBr!)jC#VkN,k 0/;w:oBjU7qI.;n46JqqAc,gc((JDS~:$cg' );


/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';


/* Add any custom values between this line and the "stop editing" line. */



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
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
if ( ! defined( 'WP_DEBUG' ) ) {
	define( 'WP_DEBUG', false );
}

define( 'FS_METHOD', 'direct' );
define( 'COOKIEHASH', '579af9c84626ff54eed061fe85002dde' );
define( 'WP_AUTO_UPDATE_CORE', 'minor' );
/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
