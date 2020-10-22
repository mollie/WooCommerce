<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://codex.wordpress.org/Editing_wp-config.php
 *
 * @package WordPress
 */

// ** MySQL settings ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress' );

/** MySQL database username */
define( 'DB_USER', 'wordpress' );

/** MySQL database password */
define( 'DB_PASSWORD', 'wordpress' );

/** MySQL hostname */
define( 'DB_HOST', 'mariadb' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',          '38Nm.#mRI7AT%&-jr7;Nf3T(KsT1Xf~DQ|,yZg@w~EIzb>S=08qLkkYX ^B|^Ya]' );
define( 'SECURE_AUTH_KEY',   'PwW9)[}GE&4y@R5jA9R+]@E`kr_FE!q#Ei[*&dq`ep.`FtyjLkCY33[0!Y7^/c R' );
define( 'LOGGED_IN_KEY',     '(Gd|PdHIAVyMdjy`|Dxc94S.HOw4@o,>So!p#Cq088@}qV5HSlDbuH]LN0RYn{K2' );
define( 'NONCE_KEY',         '^&k8,k`qu}zYxR3 gJSOB !C__GV p<PxWCH=CL7l$jeO^p7]<[:)lXaMNEh15_]' );
define( 'AUTH_SALT',         '?P?$,8mBd%]A?+D;p#9L[x1wQv8n-|b-]LK1*7Of,L9eZ;pOnX&P+Dmga@88/tt-' );
define( 'SECURE_AUTH_SALT',  ',gA?ufV&Wg[lQ&rd3AA*0yCPsiwQF!76*5m?! <(wNCIK$~536D2.0,)IHg$t>Oq' );
define( 'LOGGED_IN_SALT',    '@vMgz y$=l:h7-q=F8W+QK@])6K)^UC%9EVv=vQx1o9wJN7a.1mxOETbjBGREu-P' );
define( 'NONCE_SALT',        'z8F;H[kXRz @s)lMq%5FhXa&B-7:kCSz[a=uYn@M[eWA>;W@xvObg]58vWcR%00%' );
define( 'WP_CACHE_KEY_SALT', 'DXe{Yg22$0u H=9bF`KIQC$q!e^]IYK&&[MV&G|);aK0N3R@&r~(uea&q4Vg?`pP' );

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';




/* That's all, stop editing! Happy blogging. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) )
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
