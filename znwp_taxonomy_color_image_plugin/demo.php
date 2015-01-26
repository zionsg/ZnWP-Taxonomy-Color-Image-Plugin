<?php
/*
Plugin Name: Demo plugin for ZnWP Taxonomy Color and Image Plugin
Plugin URI:  https://github.com/zionsg/ZnWP-Taxonomy-Color-Image-Plugin
Description: This will add color and image fields to the Categories taxonomy. When adding or editing a Post, a metabox will show all the terms in Categories together with their colors and images.
Author:      Zion Ng
Author URI:  http://intzone.com/
Version:     1.0.0
*/

add_action('znwp_taxonomy_color_image_run', function () {
    return array(
        'plugin_name' => plugin_basename(__FILE__),
        'taxonomy' => array('category'),
    );
});
