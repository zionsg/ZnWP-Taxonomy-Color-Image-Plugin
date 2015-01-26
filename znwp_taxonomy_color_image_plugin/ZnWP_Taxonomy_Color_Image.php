<?php
/**
 * Plugin class
 *
 * This plugin is designed to allow multiple 3rd party plugins to use it to add color and image fields
 * (henceforth referred collectively as "custom fields") to taxonomies.
 *
 * Info on action hook for setup:
 *     @hook   string   znwp_taxonomy_color_image_run
 *     @param  callback No additional arguments for callback needed
 *     @return array    See $config_defaults and demo plugin for examples
 *
 * @package ZnWP Taxonomy Color Image Plugin
 * @author  Zion Ng <zion@intzone.com>
 * @link    https://github.com/zionsg/ZnWP-Taxonomy-Color-Image-Plugin for canonical source repository
 */

class ZnWP_Taxonomy_Color_Image
{
    /**
     * Constants
     */
    const ACTION_HOOK = 'znwp_taxonomy_color_image_run';
    const CONTEXT     = 'znwp-taxonomy-color-image';

    /**
     * Size for taxonomy term image
     *
     * @var array width, height
     */
    protected $image_size = array(75, 75);

    /**
     * Style for taxonomy term image
     *
     * @var string
     */
    protected $image_style = 'display:block; max-height:75px; max-width:75px; padding:5px 0;';

    /**
     * Custom field defaults
     *
     * @var array
     */
    protected $custom_field_defaults = array(
        'background_color' => '',
        'color' => '',
        'image_id' => '',
    );

    /**
     * Config defaults for each 3rd party plugin
     *
     * @var array
     */
    protected $config_defaults = array(
        'plugin_name' => '',    // name of plugin using hook - use plugin_basename(__FILE__)
        'taxonomy' => array(),  // names of taxonomies to add custom fields to
    );

    /**
     * Final config comprising config for each 3rd party plugin that has called the action hook
     *
     * Indexed by plugin name.
     *
     * @var array
     */
    protected $config_by_plugin = array();

    /**
     * All the taxonomies registered by 3rd party plugins via this plugin
     *
     * Indexed by taxonomy name.
     *
     * @var array
     */
    protected $taxonomies = array();

    /**
     * Post types that use each taxonomy
     *
     * Indexed by taxonomy name.
     *
     * @var array
     */
    protected $post_types_by_taxonomy = array();

    /**
     * Plugin initialization
     *
     * This will loop thru each 3rd party plugin which has called the action hook instead of
     * a one-time do_action().
     *
     * @return void
     */
    public function init()
    {
        global $wp_filter;

        // Customize media uploader
        $this->customize_media_uploader();

        // Format for $actions:
        // array(<priority> => array(<hash for plugin 1> => array('function' => <callback>, 'accepted_args' => <num>)))
        $actions = isset($wp_filter[self::ACTION_HOOK]) ? $wp_filter[self::ACTION_HOOK] : array();

        $me = $this; // for passing to callbacks via use()
        foreach ($actions as $priority => $action) {
            foreach ($action as $hash => $info) {
                $callback = $info['function'];
                $config = $this->check_config($callback());
                if (false === $config) {
                    continue;
                }

                $plugin_name = $config['plugin_name'];
                $this->config_by_plugin[$config['plugin_name']] = $config;

                // Loop thru taxonomies
                // Extra layer of lambda functions used in order to pass in plugin name to callbacks
                foreach ($this->get_taxonomies($plugin_name) as $taxonomy) {
                    // Remember taxonomies
                    $this->taxonomies[$taxonomy] = $taxonomy; // indexed to prevent duplicates

                    // Add fields for taxonomies
                    add_action(
                        "{$taxonomy}_add_form_fields",
                        function ($term) use ($me, $taxonomy) {
                            return $me->custom_fields($term, $taxonomy);
                        }
                    );
                    add_action(
                        "{$taxonomy}_edit_form_fields",
                        function ($term) use ($me, $taxonomy) {
                            return $me->custom_fields($term, $taxonomy);
                        }
                    );
                    add_action(
                        "created_{$taxonomy}",
                        function ($term_id) use ($me, $taxonomy) {
                            return $me->save_custom_fields($term_id, $taxonomy);
                        }
                    );
                    add_action(
                        "edited_{$taxonomy}",
                        function ($term_id) use ($me, $taxonomy) {
                            return $me->save_custom_fields($term_id, $taxonomy);
                        }
                    );
                    add_filter(
                        "manage_edit-{$taxonomy}_columns",
                        function ($columns) use ($me) {
                            return $me->columns($columns);
                        }
                    );
                    add_filter(
                        "manage_{$taxonomy}_custom_column",
                        function ($out, $column_name, $term_id) use ($me, $taxonomy) {
                            return $me->custom_column($taxonomy, $out, $column_name, $term_id);
                        },
                        10,
                        3
                    );
                    add_action(
                        'admin_menu',
                        function () use ($me, $taxonomy) {
                            return $me->meta_box($taxonomy);
                        }
                    );

                    // For saving data in metabox when adding or editing post
                    foreach ($this->get_post_types_for_taxonomy($taxonomy) as $post_type) {
                        add_action(
                            "save_post_{$post_type}",
                            function ($post_id) use ($me, $taxonomy) {
                                return $me->save_post_taxonomy_terms($taxonomy, $post_id);
                            }
                        );
                    }
                } // end taxonomies
            } // end action
        } // end actions
    } // end function init

    /**
     * Callback to get custom fields for a taxonomy term
     *
     * @param  object $term     Term object
     * @param  string $taxonomy Taxonomy name
     * @return void
     */
    public function custom_fields($term, $taxonomy)
    {
        echo $this->helper_custom_fields($term, $taxonomy, array(
            'background_color' => array(
                'label' => 'Background color',
                'hint'  => 'Use color picker if available or type in hexadecimal code, eg. #ff0000.',
                'type'  => 'color',
            ),
            'color' => array(
                'label' => 'Foreground color',
                'hint'  => 'Use color picker if available or type in hexadecimal code, eg. #ff0000.',
                'type'  => 'color',
            ),
            'image_id' => array(
                'label' => 'Image',
                'hint'  => 'Associate an image with this taxonomy term',
                'type'  => 'image',
            ),
        ));
    }

    /**
     * Callback to save custom fields for a taxonomy term
     *
     * @param  int    $term_id  Term ID
     * @param  string $taxonomy Taxonomy name
     * @return void
     */
    public function save_custom_fields($term_id, $taxonomy)
    {
        return $this->helper_save_custom_fields($term_id, $taxonomy);
    }

    /**
     * Callback to get custom admin columns for taxonomy term
     *
     * @param  array  $columns Column information
     * @return array
     */
    public function columns($columns)
    {
        return array(
            'name'  => __('Name'),
            'color' => __('Color'),
            'image' => __('Image'),
            // 'header_icon' => '',
            // 'description' => __('Description'),
            'slug'  => __('Slug'),
            'posts' => __('Posts')
        );
    }

    /**
     * Callback to handle custom admin column for taxonomy term
     *
     * @param  string $taxonomy Taxonomy name
     * @param  mixed  $out
     * @param  string $column_name
     * @param  int    $term_id
     * @param  string $plugin_name Name of 3rd party plugin
     * @return void
     */
    public function custom_column($taxonomy, $out, $column_name, $term_id)
    {
        $term_meta = array_merge($this->custom_field_defaults, get_option("{$taxonomy}_term_{$term_id}", array()));

        if ('color' == $column_name) {
            printf(
                '<span style="background-color:%s; color:%s">&nbsp;Text&nbsp;</span>',
                $term_meta['background_color'],
                $term_meta['color']
            );
        } elseif ('image' == $column_name) {
            printf(
                '<img src="%s" style="%s" />',
                $this->get_taxonomy_term_image_url($term_meta),
                $this->image_style
            );
        }
    }

    /**
     * Callback to handle metaboxes for taxonomy when adding/editing post types that use it
     *
     * @param  string $taxonomy Taxonomy name
     * @return void
     */
    public function meta_box($taxonomy)
    {
        $me = $this;

        $taxonomy_object = get_taxonomy($taxonomy);
        $taxonomy_label = $taxonomy_object->label;
        $terms = $this->get_taxonomy_terms($taxonomy);

        foreach ($this->get_post_types_for_taxonomy($taxonomy) as $post_type) {
            // Remove side metaboxes
            remove_meta_box("tagsdiv-{$taxonomy}", $post_type, 'side');
            remove_meta_box("{$taxonomy}div", $post_type, 'side');

            add_meta_box(
                "{$taxonomy}div",
                $taxonomy_label,
                function () use ($me, $taxonomy, $terms) {
                    // Pass in null for post ID so as to use the current post ID at the time the metabox is shown
                    echo $me->generate_form_html($taxonomy, null, $terms);
                },
                $post_type,
                'normal',
                'default'
            );
        }
    }

    /**
     * Generate form HTML for all taxonomy terms
     *
     * Method is set as public to allow for use in frontend pages.
     *
     * @param  string $taxonomy Taxonomy name
     * @param  int    $post_id  Optional post ID. This param is needed as the form may not
     *                          be for the current post, eg. on frontend pages
     * @param  array  $terms    Optional taxonomy terms which can be passed in if pre-computed to
     *                          avoid repeated queries
     * @return string
     */
    public function generate_form_html($taxonomy, $post_id = null, $terms = null)
    {
        global $post;

        $post_id = (null === $post_id) ? $post->ID : $post_id;
        if (null === $terms) {
            $terms = $this->get_taxonomy_terms($taxonomy);
        }

        // Currently selected terms in post
        $curr_terms = $this->get_post_taxonomy_terms($post_id, $taxonomy);

        $html = '';
        $cols = 3;
        $col_width = (100 / 3) . '%';
        $col_cnt = 0;

        $html .= '<table width="100%" border="0">';
        foreach ($terms as $term) {
            $term_meta = array_merge($this->custom_field_defaults, $term->term_meta);

            $html .= ((0 == $col_cnt % $cols) ? '<tr>' : '');
            $html .= sprintf(
                  '<td valign="top" width="%1$s"><input id="%2$s" name="%2$s[]" type="checkbox" '
                . 'value="%3$d" %4$s style="width:auto;" />'
                . '<span style="background-color:%5$s; color:%6$s">&nbsp;%7$s&nbsp;</span>'
                . '<img src="%8$s" style="%9$s" />'
                . '</td>',
                $col_width,
                $taxonomy,
                $term->term_id,
                isset($curr_terms[$term->term_id]) ? 'checked="checked"' : '',
                $term_meta['background_color'],
                $term_meta['color'],
                $term->name,
                $this->get_taxonomy_term_image_url($term_meta),
                $this->image_style
            );

            $html .= ((($cols - 1) == $col_cnt % $cols) ? '</tr>' : '');
            $col_cnt++;
        }
        $html .= '</table>';

        return $html;
    }

    /**
     * Callback for saving all the selected taxonomy terms when a post is saved
     *
     * update_post_meta() is not used as that is for custom fields in a post, which is 1-to-1.
     * wp_set_object_terms() links multiple taxonomy terms to a post, which is many-to-1.
     *
     * @param  string $taxonomy Taxonomy name
     * @param  int    $post_id  The ID of the post
     * @return void
     */
    public function save_post_taxonomy_terms($taxonomy, $post_id)
    {
        // Update the post terms
        if (isset($_POST[$taxonomy])) {
            // Must cast all the term_id values to int else they will be misinterpreted as slugs
            wp_set_object_terms($post_id, array_map('intval', $_POST[$taxonomy]), $taxonomy, false); // overwrite
        }
    }

    /**
     * Get taxonomy terms linked to a post
     *
     * Each term will have a 'term_meta' property that contains all metadata.
     * Terms will be indexed by term_id to facilitate searching.
     *
     * @param  int    $post_id  The ID of the post
     * @param  string $taxonomy Taxonomy name
     * @return array
     */
    public function get_post_taxonomy_terms($post_id, $taxonomy)
    {
        $terms = wp_get_object_terms($post_id, $taxonomy);

        if ($terms instanceof WP_Error) {
            return array();
        }

        $results = array();
        foreach ($terms as $term) {
            $term->term_meta = array_merge(
                $this->custom_field_defaults,
                get_option("{$taxonomy}_term_{$term_id}", array())
            );
            $results[$term->term_id] = $term;
        }

        return $results;
    }

    /**
     * Fetch all terms for a taxonomy including the term metadata
     *
     * Each term will have a 'term_meta' property that contains all metadata.
     * Terms will be indexed by term_id to facilitate searching.
     *
     * @param  string $taxonomy Taxonomy name
     * @return object[]
     */
    public function get_taxonomy_terms($taxonomy)
    {
        // hide_empty=false will return all terms including those with no posts assigned to them
        $terms = get_terms($taxonomy, array('hide_empty' => false));
        $results = array();

        foreach ($terms as $key => $term) {
            $term->term_meta = array_merge(
                $this->custom_field_defaults,
                get_option("{$taxonomy}_term_{$term_id}", array())
            );
            $results[$term->term_id] = $term;
        }

        return $results;
    }

    /**
     * Fetch a term for a taxonomy including the term metadata
     *
     * The term will have a 'term_meta' property that contains all metadata.
     *
     * @param  int    $term_id
     * @param  string $taxonomy Taxonomy name
     * @return null|object
     */
    public function get_taxonomy_term($term_id, $taxonomy)
    {
        $term = get_term($term_id, $taxonomy);

        if (null === $term || $term instanceof WP_Error) {
            return null;
        }

        $term->term_meta = array_merge(
            $this->custom_field_defaults,
            get_option("{$taxonomy}_term_{$term_id}", array())
        );

        return $term;
    }

    /**
     * Get image url for taxonomy term
     *
     * @param  array $term_meta
     * @return string
     */
    public function get_taxonomy_term_image_url($term_meta)
    {
        $image_url = '';
        if (isset($term_meta['image_id'])) {
            $image_src = wp_get_attachment_image_src($term_meta['image_id'], $this->image_size);
            $image_url = $image_src[0];
        }

        return $image_url;
    }

    /**
     * Customize media uploader for uploading images for taxonomy terms
     *
     * @return void
     */
    protected function customize_media_uploader()
    {
        $plugin_context = self::CONTEXT;
        add_filter('media_upload_tabs', function ($default_tabs) use ($plugin_context) {
            $context = isset($_GET['context']) ? $_GET['context'] : '';
            if ($context == $plugin_context) {
                unset($default_tabs['type_url']);
                unset($default_tabs['gallery']);
            }

            return $default_tabs;
        }, 10, 1);

        add_filter('attachment_fields_to_edit', function ($form_fields, $post) use ($plugin_context) {
            $context = isset($_GET['context']) ? $_GET['context'] : '';
            if ($context == $plugin_context) {
                $send = "<input type='submit' class='button' name='send[{$post->ID}]' value='Use Image' />";
                $form_fields['buttons'] = array(
                    'tr' => "\t\t<tr class='submit'><td></td><td class='savesend'>{$send}</td></tr>\n"
                );
                $form_fields['context'] = array('input' => 'hidden', 'value' => 'znwp-taxonomy-color-image');
            }

            return $form_fields;
        }, 20, 2);

        add_filter('media_send_to_editor', function ($html, $send_id, $attachment) use ($plugin_context) {
            $context = isset($attachment['context']) ? $attachment['context'] : '';
            if ($context == $plugin_context) {
                echo "
                    <script>
                      /* <![CDATA[ */
                      var win = window.dialogArguments || opener || parent || top;
                      win.jQuery('#term_meta_image_id').val('{$send_id}');
                      win.jQuery('#term_meta_image_id_src').attr('src', '{$attachment['url']}');
                      win.jQuery('#term_meta_image_id_remove').show();
                      win.jQuery('#TB_overlay').hide();
                      win.jQuery('#TB_window').hide();
                      /* ]]> */
                    </script>
                ";
                exit; // do not allow any other scripts to run
            }
        }, 10, 3);
    }

    /**
     * Check config
     *
     * @param  array      $config Config from 3rd party plugin as per $config_defaults
     * @return array|bool Sanitized config. FALSE returned if config is faulty
     */
    protected function check_config($config)
    {
        $config = array_merge(
            $this->config_defaults,
            $config
        );
        if ($config == $this->config_defaults) {
            return false;
        }

        // Ensure taxonomy is array
        if (is_string($config['taxonomy'])) {
            $config['taxonomy'] = array($config['taxonomy']);
        }

        return $config;
    }

    /**
     * Get all taxonomies registered by 3rd party plugins via this plugin
     *
     * @param  string $plugin_name If plugin name is passed in, only those taxonomies
     *                             registered for that plugin are returned.
     * @return array
     */
    protected function get_taxonomies($plugin_name = null)
    {
        if (null === $plugin_name) {
            return $this->taxonomies;
        }

        return isset($this->config_by_plugin[$plugin_name]['taxonomy'])
             ? $this->config_by_plugin[$plugin_name]['taxonomy']
             : array();
    }

    /**
     * Get all post types that use a specific taxonomy
     *
     * @param  string $taxonomy
     * @return array
     */
    protected function get_post_types_for_taxonomy($taxonomy)
    {
        if (!isset($this->post_types_by_taxonomy[$taxonomy])) {
            $taxonomy_object = get_taxonomy($taxonomy);
            $this->post_types_by_taxonomy[$taxonomy] = empty($taxonomy_object->object_type)
                                                     ? array()
                                                     : $taxonomy_object->object_type;
        }

        return $this->post_types_by_taxonomy[$taxonomy];
    }

    /**
     * Helper function to generate custom form fields for taxonomy
     *
     * @param  object $term
     * @param  string $taxonomy
     * @param  array  $fields   Custom fields with labels and description, example:
     *                              array(field1 => array(
     *                                  'label' => <label>,
     *                                  'hint' => <hint>,
     *                                  'type' => <text|dropdown|multicheckbox>,
     *                                  'options' => <array of option-label pairs if type is dropdown or multicheckbox>,
     *                                  'selected_options' => <array of selected options if applicable>,
     *                                  'option_separator' => <text separating options in multicheckbox, eg. '<br>'>,
     *                              ), ...)
     * @return string
     */
    protected function helper_custom_fields($term, $taxonomy, array $fields)
    {
        // Check for existing taxonomy meta for the term being edited
        $term_id = $term->term_id; // Get the ID of the term being edited
        $term_meta = get_option("{$taxonomy}_term_{$term_id}");

        $defaults = array(
            'label' => '',
            'hint' => '',
            'type' => 'text',
            'options' => array(),
            'selected_options' => array(),
            'option_separator' => '',
        );

        $html = '';
        foreach ($fields as $field => $info) {
            extract(array_merge($defaults, $info));

            $value = isset($term_meta[$field]) ? $term_meta[$field] : '';
            if ('dropdown' == $type) {
                $element = sprintf('<select id="term_meta_%1$s" name="term_meta[%1$s]">', $field);
                foreach ($options as $option => $option_label) { // cannot use $label as it is used already
                    $element .= sprintf(
                        '<option value="%s" %s>%s</option>' . PHP_EOL,
                        $option,
                        (in_array($option, $selected_options) ? 'selected="selected"' : ''),
                        $option_label
                    );
                }
                $element .= '</select>';
            } elseif ('multicheckbox' == $type) {
                // Style for checkbox set to width:auto else will elongate when editing term
                $element = '';
                foreach ($options as $option => $option_label) {
                    $element .= sprintf( // note the [] for name
                          '<input id="term_meta_%1$s" name="term_meta[%1$s][]" type="checkbox" '
                        . ' value="%2$s" %3$s style="width:auto;" />%4$s%5$s',
                        $field,
                        $option,
                        (in_array($option, $selected_options) ? 'checked="checked"' : ''),
                        $option_label,
                        $option_separator
                    );
                }
            } elseif ('image' == $type) { // Media upload
                add_thickbox(); // enable media upload to open in pop-up window using Thickbox library in WordPress
                $image_library_url = get_upload_iframe_src('image', null, 'library');
                $image_library_url = remove_query_arg(array('TB_iframe'), $image_library_url);
                $image_library_url = add_query_arg(
                    array('context' => self::CONTEXT, 'TB_iframe' => 1),
                    $image_library_url
                );

                $image_url = '';
                if (isset($term_meta[$field])) {
                    $image_src = wp_get_attachment_image_src($term_meta[$field], $this->image_size);
                    $image_url = $image_src[0];
                }

                $script = "
                    <script>
                      jQuery('#term_meta_{$field}_button').click(function () {
                          jQuery('#TB_overlay, #TB_window').show();
                          return true;
                      });

                      jQuery('#term_meta_{$field}_remove').click(function () {
                          jQuery('#term_meta_{$field}').val('');
                          jQuery('#term_meta_{$field}_src').attr('src', '');
                          jQuery(this).hide();
                          return false;
                      });

                      jQuery('#term_meta_{$field}_remove').toggle(
                          jQuery('#term_meta_{$field}_src').attr('src') ? true : false
                      );
                    </script>
                ";

                $element = sprintf(
                      '<a id="term_meta_%2$s_button" class="button thickbox" href="%1$s">Set Image</a>'
                    . '<input id="term_meta_%2$s" name="term_meta[%2$s]" type="hidden" '
                    . ' value="%3$s" />'
                    . '<img id="term_meta_%2$s_src" src="%4$s" style="%5$s" />'
                    . '<a id="term_meta_%2$s_remove" href="#">Remove image</a>'
                    . '%6$s',
                    esc_url($image_library_url),
                    $field,
                    $value,
                    $image_url,
                    $this->image_style,
                    $script
                );
            } else {
                $element = sprintf(
                    '<input id="term_meta[%1$s]" name="term_meta[%1$s]" type="%2$s" size="40" value="%3$s" />',
                    $field,
                    $type,
                    $value
                );
            }

            $html .= sprintf('
                <tr class="form-field">
                  <th><label for="%s">%s</label></th>
                  <td>%s<p>%s</p></td>
                </tr>
                ',
                $field,
                $label,
                $element,
                $hint
            );
        }

        return $html;
    }

    /**
     * Helper function to save custom fields for taxonomy
     *
     * @param  string $term_id
     * @param  string $taxonomy Taxonomy name
     * @param  array  $data     Array containing 'term_meta' key. If not specified, POST data is used.
     * @return void
     */
    protected function helper_save_custom_fields($term_id, $taxonomy, array $data = null)
    {
        if (null === $data) {
            $data = $_POST;
        }
        if (!isset($data['term_meta'])) {
            return;
        }

        $term_meta = get_option("{$taxonomy}_term_{$term_id}");
        foreach ($data['term_meta'] as $key => $value) {
            $term_meta[$key] = $value;
        }

        update_option("{$taxonomy}_term_{$term_id}", $term_meta);
    }
}
