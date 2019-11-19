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
define('AUTH_KEY',         'I2JSuu)(NiGI>|M7exfC#]5g_:*hIJWIS(i{j3idv~1O0zw$G(KK+1jEpA5ZOi ^');
define('SECURE_AUTH_KEY',  '}$KF`#k=y~I|8;=@fT^&aDf<X`pbR*-gGY).I!o0/u_[,<2JBY{{yOo[dB5|O.xn');
define('LOGGED_IN_KEY',    '+01M}Lq:5`D9l!$ZiCFM0R!]@9f8B80hL,Tfosb6&WH6qv}C# NRzQS{~-iJ,&&&');
define('NONCE_KEY',        'C([h]V<5 Y`_#=LD9vEl,&8+8Avyhd]#z|$<l-/8qcUJbXr- ,+mqj((.KOJ,5fD');
define('AUTH_SALT',        '0)uO2FAS.ypHi_/v9ln2?eUpxnx;4+g|Dt~eFJ887mW?PfZb497|li8?r-=3|+c3');
define('SECURE_AUTH_SALT', '@sz{y}^Zy)-@eXV7^H.?0Y;|}}[lNG9NgMg`_<c5FHvZs{vUmkA{q#3W8)/Bj`T$');
define('LOGGED_IN_SALT',   'C7hW3<<&Fy{: rTeW1j+TEd/u=&c-s5A%L>WsfvT%Fn<wC]vC$:/B~*1fk93-LW~');
define('NONCE_SALT',       '7KB`Ko6w OL$U}Xr~keIvl6vqy4?D1;<$}5UBIh-avD{FLLsVCzR+spA%%OjjlP:');

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
