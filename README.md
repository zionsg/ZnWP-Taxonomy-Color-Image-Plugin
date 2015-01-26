##ZnWP Taxonomy Color and Image Plugin

This WordPress plugin allows multiple 3rd party plugins to easily add color and image fields to taxonomies via an action hook.

Collections can be color coded and entities can be grouped under multiple collections.

Demo plugin included.

### Installation
Steps
  - Click the "Download Zip" button on the righthand side of this GitHub page
  - Uncompress the zip file on your desktop
  - Copy the `znwp_taxonomy_color_image_plugin` folder to your WordPress plugins folder
    OR compress that folder and upload via the WordPress admin interface
  - Activate the plugin

### Usage
This plugin only has 1 action hook - `znwp_taxonomy_color_image_run` which takes in a config array. It is designed to be used by multiple 3rd party plugins on the same WordPress installation to add color and image fields to taxonomies.

After installation, you will see 2 plugins installed - the plugin itself and a demo plugin. Activate the demo plugin to see it in action.

View the source code of the [Demo Plugin](https://raw.githubusercontent.com/zionsg/ZnWP-Taxonomy-Color-Image-Plugin/master/znwp_taxonomy_color_image_plugin/demo.php) to see how to use the action hook.
