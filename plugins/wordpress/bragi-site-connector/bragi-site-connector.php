<?php
/**
 * Plugin Name: BRAGI Site Connector
 * Description: Conector seguro entre WordPress y la app BRAGI para analítica SEO y acciones técnicas autorizadas.
 * Version: 0.1.0
 * Requires at least: 6.2
 * Requires PHP: 7.4
 * Author: MLCDESIGN
 * License: GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'BRAGI_SITE_CONNECTOR_VERSION', '0.1.0' );
define( 'BRAGI_SITE_CONNECTOR_FILE', __FILE__ );

require_once __DIR__ . '/includes/class-bragi-site-connector.php';

register_activation_hook( __FILE__, array( 'Bragi_Site_Connector', 'activate' ) );
( new Bragi_Site_Connector() )->register();
