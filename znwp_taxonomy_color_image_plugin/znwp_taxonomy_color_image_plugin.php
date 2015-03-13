<?php
/*
Plugin Name: ZnWP Taxonomy Color and Image Plugin
Plugin URI:  https://github.com/zionsg/ZnWP-Taxonomy-Color-Image-Plugin
Description: This plugin allows multiple 3rd party plugins to easily add color, image and order fields to taxonomies. Demo plugin included.
Author:      Zion Ng
Author URI:  http://intzone.com/
Version:     1.0.0
*/

require_once 'ZnWP_Taxonomy_Color_Image.php'; // PSR-1 states files should not declare classes AND execute code

// init must be run after theme setup to allow functions.php in theme to add action hook
$znwp_taxonomy_color_image_plugin = new ZnWP_Taxonomy_Color_Image();
add_action('after_setup_theme', array($znwp_taxonomy_color_image_plugin, 'init'));
