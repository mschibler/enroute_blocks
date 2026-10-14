<?php
/**
 * Plugin Name: Enroute Blocks
 * Description: Gutenberg blocks for the Enroute front page grid.
 * Version: 1.2.0
 * Author: Enroute
 * Text Domain: enroute_blocks
 */
if ( ! defined( 'ABSPATH' ) ) exit;

define( 'ENROUTE_BLOCKS_PATH', plugin_dir_path( __FILE__ ) );
define( 'ENROUTE_BLOCKS_URL',  plugin_dir_url( __FILE__ ) );
define( 'ENROUTE_BLOCKS_VER',  '1.2.0' );

require_once ENROUTE_BLOCKS_PATH . 'includes/blocks.php';
require_once ENROUTE_BLOCKS_PATH . 'includes/render.php';
