<?php
/*
Plugin Name: WPU Polls
Plugin URI: https://github.com/WordPressUtilities/wpu_polls
Update URI: https://github.com/WordPressUtilities/wpu_polls
Description: WPU Polls handle simple polls
Version: 0.16.0
Author: Darklg
Author URI: https://darklg.me/
Text Domain: wpu_polls
Domain Path: /lang
Requires at least: 6.2
Requires PHP: 8.0
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

class WPUPolls {
    public $messages;
    public $plugin_description;
    public $baseadmindatas;
    public $basefields;
    public $settings_details;
    public $settings;
    private $plugin_version = '0.16.0';
    private $plugin_settings = array(
        'id' => 'wpu_polls',
        'name' => 'WPU Polls'
    );
    private $nb_max = 999999999999;
    private $settings_obj;

    public function __construct() {
        add_filter('plugins_loaded', array(&$this, 'plugins_loaded'));

        # Post type
        add_action('init', array(&$this, 'register_post_type'));
        add_filter('manage_polls_posts_columns', array(&$this, 'manage_polls_posts_columns'));
        add_action('manage_polls_posts_custom_column', array(&$this, 'manage_polls_posts_custom_column'), 10, 2);

        # Assets
        add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts'));
        add_action('wp_enqueue_scripts', array(&$this, 'wp_enqueue_scripts'));

        /* Admin */
        add_action('add_meta_boxes', function () {
            add_meta_box('wpu-polls-box-id', 'Poll box', array(&$this, 'edit_page_poll'), 'polls');
        });
        add_action('save_post', array(&$this, 'save_poll'));
        add_action('current_screen', array(&$this, 'before_admin_edit'));
        add_action('show_user_profile', array(&$this, 'edit_user_box'));
        add_action('edit_user_profile', array(&$this, 'edit_user_box'));

        /* Shortcode */
        add_shortcode('wpu_polls', array(&$this, 'shortcode'));

        /* Action front */
        add_action('wp_ajax_nopriv_wpu_polls_answer', array(&$this, 'ajax_action'));
        add_action('wp_ajax_wpu_polls_answer', array(&$this, 'ajax_action'));

        /* After settings */
        add_action('wpubasesettings_before_wrap_endsettings_page_wpu_polls', array(&$this, 'wpubasesettings_before_wrap_endsettings_page_wpu_polls'));
    }

    public function plugins_loaded() {
        # TRANSLATION
        $lang_dir = dirname(plugin_basename(__FILE__)) . '/lang/';
        if (!load_plugin_textdomain('wpu_polls', false, $lang_dir)) {
            load_muplugin_textdomain('wpu_polls', $lang_dir);
        }
        $this->plugin_description = __('WPU Polls handle simple polls', 'wpu_polls');
        # CUSTOM TABLE
        include dirname(__FILE__) . '/inc/WPUBaseAdminDatas/WPUBaseAdminDatas.php';
        $this->baseadmindatas = new \wpu_polls\WPUBaseAdminDatas();
        $this->baseadmindatas->init(array(
            'handle_database' => false,
            'plugin_id' => $this->plugin_settings['id'],
            'table_name' => 'wpu_polls',
            'table_fields' => array(
                'post_id' => array(
                    'public_name' => 'Post ID',
                    'type' => 'number'
                ),
                'user_id' => array(
                    'public_name' => 'Post ID',
                    'type' => 'number'
                ),
                'answer_id' => array(
                    'public_name' => 'Answer ID',
                    'type' => 'sql',
                    'sql' => 'varchar(100) DEFAULT NULL'
                ),
                'user_email' => array(
                    'public_name' => 'User email'
                ),
                'user_name' => array(
                    'public_name' => 'User name'
                ),
                'gdpr' => array(
                    'public_name' => 'GDPR',
                    'type' => 'number'
                )
            )
        ));
        # SETTINGS
        $this->settings_details = array(
            # Admin page
            'create_page' => true,
            'plugin_basename' => plugin_basename(__FILE__),
            # Default
            'plugin_name' => $this->plugin_settings['name'],
            'plugin_id' => $this->plugin_settings['id'],
            'option_id' => $this->plugin_settings['id'] . '_options',
            'sections' => array(
                'default' => array(
                    'name' => __('Settings', 'wpu_polls')
                )
            )
        );
        $this->settings = array(
            'public' => array(
                'label' => __('Public post type', 'wpu_polls'),
                'type' => 'radio',
                'datas' => array(
                    __('No', 'wpu_polls'),
                    __('Yes', 'wpu_polls')
                )
            )
        );
        include dirname(__FILE__) . '/inc/WPUBaseSettings/WPUBaseSettings.php';
        $this->settings_obj = new \wpu_polls\WPUBaseSettings($this->settings_details, $this->settings);

        if (is_admin()) {
            include dirname(__FILE__) . '/inc/WPUBaseMessages/WPUBaseMessages.php';
            $this->messages = new \wpu_polls\WPUBaseMessages($this->plugin_settings['id']);
        }

        $select_values = array();
        for ($i = 1; $i < 99; $i++) {
            $select_values[$i] = $i;
        }
        $select_values[$this->nb_max] = __('Multiple', 'wpu_polls');

        include dirname(__FILE__) . '/inc/WPUBaseFields/WPUBaseFields.php';
        $fields = array(
            'wpu_polls__nbanswers' => array(
                'type' => 'select',
                'default_value' => $this->nb_max,
                'group' => 'wpu_polls__settings',
                'label' => __('User can choose this number of answers :', 'wpu_polls'),
                'data' => $select_values
            ),
            'wpu_polls__nbvotesmax' => array(
                'type' => 'select',
                'default_value' => $this->nb_max,
                'group' => 'wpu_polls__settings',
                'label' => __('Maximum number of votes per response :', 'wpu_polls'),
                'data' => $select_values
            ),
            'wpu_polls__requiredetails' => array(
                'type' => 'checkbox',
                'group' => 'wpu_polls__settings',
                'label' => __('Require user name and email to vote (use account details if loggedin)', 'wpu_polls')
            ),
            'wpu_polls__gdprcheckbox' => array(
                'toggle-display' => array(
                    'wpu_polls__requiredetails' => 'checked'
                ),
                'type' => 'checkbox',
                'group' => 'wpu_polls__settings',
                'label' => __('Display a GDPR checkbox', 'wpu_polls')
            ),
            'wpu_polls__displaymessage' => array(
                'type' => 'checkbox',
                'group' => 'wpu_polls__settings',
                'label' => __('Display a message after vote instead of the results', 'wpu_polls')
            ),
            'wpu_polls__displaymessage__content' => array(
                'toggle-display' => array(
                    'wpu_polls__displaymessage' => 'checked'
                ),
                'group' => 'wpu_polls__settings',
                'help' => __('Displayed only if previous checkbox is checked.', 'wpu_polls'),
                'label' => __('Custom text message after vote', 'wpu_polls')
            ),
            /* Poll */
            'wpu_polls__question' => array(
                'group' => 'wpu_polls__poll',
                'label' => __('Question', 'wpu_polls'),
                'required' => true
            )
        );
        $field_groups = array(
            'wpu_polls__settings' => array(
                'label' => __('Settings', 'wpu_polls'),
                'post_type' => 'polls'
            ),
            'wpu_polls__poll' => array(
                'label' => __('Poll', 'wpu_polls'),
                'post_type' => 'polls'
            )
        );
        $this->basefields = new \wpu_polls\WPUBaseFields($fields, $field_groups);
    }

    public function admin_enqueue_scripts() {
        /* Back Script */
        wp_register_script('wpu_polls_back_script', plugins_url('assets/back.js', __FILE__), array('jquery', 'jquery-ui-sortable'), $this->plugin_version, true);
        wp_localize_script('wpu_polls_back_script', 'wpu_polls_settings_back', array(
            'confirm_vote_deletion' => __('Do you really want to delete this vote ?', 'wpu_polls'),
            'error_need_content' => __('You can’t have only empty choices', 'wpu_polls'),
            'error_need_all_images' => __('Images should be on every choice, or none of them.', 'wpu_polls'),
            'error_need_all_text' => __('Text should be on every choice, or none of them.', 'wpu_polls'),
            'error_need_two_choices' => __('You need at least two choices.', 'wpu_polls')
        ));
        wp_enqueue_script('wpu_polls_back_script');
        /* Back Style */
        wp_register_style('wpu_polls_back_style', plugins_url('assets/back.css', __FILE__), array(), $this->plugin_version);
        wp_enqueue_style('wpu_polls_back_style');
    }

    public function wp_enqueue_scripts() {
        /* Front Script with localization / variables */
        wp_register_script('wpu_polls_front_script', plugins_url('assets/front.js', __FILE__), array('jquery'), $this->plugin_version, true);
        wp_localize_script('wpu_polls_front_script', 'wpu_polls_settings', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'str_0_vote' => __('No vote', 'wpu_polls'),
            'str_one_vote' => __('1 vote', 'wpu_polls'),
            'str_n_votes' => __('%d votes', 'wpu_polls'),
            'cacheurl' => $this->get_cache_path_dir('baseurl')
        ));
        wp_enqueue_script('wpu_polls_front_script');
        /* Front Style */
        wp_register_style('wpu_polls_front_style', plugins_url('assets/front.css', __FILE__), array(), $this->plugin_version);
        wp_enqueue_style('wpu_polls_front_style');
    }

    public function register_post_type() {
        $labels = array(
            'name' => __('Polls', 'wpu_polls'),
            'singular_name' => __('Poll', 'wpu_polls'),
            'menu_name' => __('Polls', 'wpu_polls'),
            'name_admin_bar' => __('Poll', 'wpu_polls'),
            'add_new' => __('Add New', 'wpu_polls'),
            'add_new_item' => __('Add New Poll', 'wpu_polls'),
            'new_item' => __('New Poll', 'wpu_polls'),
            'edit_item' => __('Edit Poll', 'wpu_polls'),
            'view_item' => __('View Poll', 'wpu_polls'),
            'all_items' => __('All Polls', 'wpu_polls'),
            'search_items' => __('Search Polls', 'wpu_polls'),
            'parent_item_colon' => __('Parent Polls:', 'wpu_polls'),
            'not_found' => __('No polls found.', 'wpu_polls'),
            'not_found_in_trash' => __('No polls found in Trash.', 'wpu_polls'),
            'archives' => _x('Poll archives', 'The post type archive label used in nav menus. Default “Post Archives”. Added in 4.4', 'wpu_polls'),
            'insert_into_item' => _x('Insert into poll', 'Overrides the “Insert into post”/”Insert into page” phrase (used when inserting media into a post). Added in 4.4', 'wpu_polls'),
            'uploaded_to_this_item' => _x('Uploaded to this poll', 'Overrides the “Uploaded to this post”/”Uploaded to this page” phrase (used when viewing media attached to a post). Added in 4.4', 'wpu_polls'),
            'filter_items_list' => _x('Filter polls list', 'Screen reader text for the filter links heading on the post type listing screen. Default “Filter posts list”/”Filter pages list”. Added in 4.4', 'wpu_polls'),
            'items_list_navigation' => _x('Polls list navigation', 'Screen reader text for the pagination heading on the post type listing screen. Default “Posts list navigation”/”Pages list navigation”. Added in 4.4', 'wpu_polls'),
            'items_list' => _x('Polls list', 'Screen reader text for the items list heading on the post type listing screen. Default “Posts list”/”Pages list”. Added in 4.4', 'wpu_polls')
        );
        $args = array(
            'labels' => $labels,
            'public' => false,
            'show_in_nav_menus' => true,
            'show_ui' => true,
            'label' => __('Polls', 'wpu_polls'),
            'menu_icon' => 'dashicons-format-status',
            'supports' => array('title', 'author')
        );

        $settings = $this->settings_obj->get_settings();
        if (isset($settings['public']) && $settings['public'] == '1') {
            $args['public'] = true;
        }

        register_post_type('polls', $args);
    }

    function manage_polls_posts_columns($columns) {
        $new_columns = array();
        foreach ($columns as $k => $col) {
            $new_columns[$k] = $col;
            if ($k == 'title') {
                $new_columns['poll_question'] = 'Question';
            }
        }
        return $new_columns;
    }

    function manage_polls_posts_custom_column($column_key, $post_id) {
        if ($column_key == 'poll_question') {
            echo get_post_meta($post_id, 'wpu_polls__question', 1);
        }
    }

    function wpubasesettings_before_wrap_endsettings_page_wpu_polls() {
        $settings = $this->settings_obj->get_settings();
        if (!is_array($settings) || !isset($settings['public'])) {
            return;
        }
        $opt_id = 'wpu_polls__cached_options_public';
        $opt_val = get_option($opt_id);
        if ($opt_val !== $settings['public']) {
            flush_rewrite_rules();
            update_option($opt_id, $settings['public'], false);
        }
    }

    /* ----------------------------------------------------------
      Edit & Save post
    ---------------------------------------------------------- */

    /* Before view
    -------------------------- */

    function before_admin_edit() {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }
        if ($screen->base != 'post' || $screen->post_type != 'polls') {
            return;
        }
        if (isset($_GET['wpu_polls_export_csv']) && isset($_GET['post']) && is_numeric($_GET['post'])) {
            $votes = $this->get_votes_for_poll($_GET['post']);
            $short_results = $this->get_results_for_poll($_GET['post']);
            $this->baseadmindatas->export_array_to_csv($short_results, 'polls-' . $_GET['post']);
        }
        if (isset($_GET['wpu_polls_delete_vote']) && isset($_GET['post']) && is_numeric($_GET['post']) && is_numeric($_GET['wpu_polls_delete_vote'])) {
            global $wpdb;
            $wpdb->delete($this->baseadmindatas->tablename,
                array(
                    'post_id' => $_GET['post'],
                    'id' => $_GET['wpu_polls_delete_vote']
                )
            );
            $this->get_votes_for_poll($_GET['post'], true);
            $this->messages->set_message('wpu_polls_delete_vote_success', __('The vote has been successfully deleted.', 'wpu_polls'), 'updated');
            wp_redirect(admin_url('post.php?post=' . $_GET['post'] . '&action=edit'));
            die;
        }
    }

    /* Edit poll
    -------------------------- */

    public function edit_page_poll($post) {

        $answers = $this->get_post_answers($post->ID);
        echo '<div class="wpu-poll-details-inner">';
        echo '<h3>' . __('Poll', 'wpu_polls') . '</h3>';

        /* Answers */
        echo '<table class="widefat striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th colspan="2">' . __('Image', 'wpu_polls') . '</th>';
        echo '<th colspan="2">' . __('Answer', 'wpu_polls') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody class="wpu-polls-answers" id="wpu-polls-answers">';
        foreach ($answers as $answer) {
            echo $this->get_template_answer($answer);
        }
        echo '</tbody></table>';

        /* New line */
        echo '<p style="text-align:right"><button class="button button-primary button-large" type="button" id="wpu-polls-answer-add-line">' . __('Add an answer', 'wpu_polls') . '</button></p>';

        $answers = $this->get_post_answers(get_the_ID());
        $results = $this->get_votes_for_poll(get_the_ID());
        $answers_display = array();

        if (is_array($results['results']) && $results['nb_votes']) {

            foreach ($answers as $answer) {
                $nb_votes = 0;
                $nb_votes_str = __('No vote', 'wpu_polls');
                if (isset($results['results'][$answer['uniqid']])) {
                    $nb_votes_str = __('1 vote', 'wpu_polls');
                    $nb_votes = $results['results'][$answer['uniqid']];
                    if ($nb_votes > 1) {
                        $nb_votes_str = sprintf(__('%d votes', 'wpu_polls'), $nb_votes);
                    }
                }
                $answers_display[] = array(
                    'uniqid' => $answer['uniqid'],
                    'answer' => $answer['answer'],
                    'votes' => $nb_votes,
                    'votes_str' => $nb_votes_str,
                    'percent' => ($nb_votes / $results['nb_votes'] * 100) . '%.'
                );

            }
        }

        $requiredetails = get_post_meta($post->ID, 'wpu_polls__requiredetails', 1);

        if (!$requiredetails) {
            usort($answers_display, function ($a, $b) {
                return $b['votes'] - $a['votes'];
            });
        }

        if (!empty($answers_display)) {
            if ($requiredetails) {

                $short_results = $this->get_results_for_poll($post->ID);
                echo '<h3>' . __('Votes', 'wpu_polls') . '</h3>';
                echo '<table class="widefat striped" id="wpu-polls-table-votes">';
                echo '<thead>';
                echo '<th>' . __('Answer', 'wpu_polls') . '</th>';
                echo '<th>' . __('Name', 'wpu_polls') . '</th>';
                echo '<th>' . __('Email', 'wpu_polls') . '</th>';
                echo '<th>' . __('User', 'wpu_polls') . '</th>';
                echo '<th>' . __('GDPR', 'wpu_polls') . '</th>';
                echo '<th></th>';
                echo '</thead>';
                foreach ($answers_display as $answer) {
                    $html_answer = '';
                    foreach ($short_results as $result) {
                        if ($result['answer_id'] != $answer['uniqid']) {
                            continue;
                        }
                        $delete_url = admin_url('post.php?post=' . get_the_ID() . '&action=edit&wpu_polls_delete_vote=' . $result['id']);
                        $user_id = '';
                        if (is_numeric($result['user_id']) && $result['user_id']) {
                            $user_id = '<a href="' . admin_url('user-edit.php?user_id=' . $result['user_id']) . '">' . sprintf(__('#%s', 'wpu_polls'), $result['user_id']) . '</a>';
                        }
                        $html_answer .= '<tr>';
                        $html_answer .= '<td>' . $answer['answer'] . '</td>';
                        $html_answer .= '<td>' . $result['user_name'] . '</td>';
                        $html_answer .= '<td>' . $result['user_email'] . '</td>';
                        $html_answer .= '<td>' . $user_id . '</td>';
                        $html_answer .= '<td>' . ($result['gdpr'] ? '&checkmark;' : '') . '</td>';
                        $html_answer .= '<td><button class="delete-vote-button" type="button" data-delete-button-url="' . esc_url($delete_url) . '">&times;</button></td>';
                        $html_answer .= '</tr>';
                    }
                    if ($html_answer) {
                        echo '<tbody>' . $html_answer . '</tbody>';
                    }
                }
                echo '</table>';
                $export_url = admin_url('post.php?post=' . get_the_ID() . '&action=edit&wpu_polls_export_csv=1');
                echo '<hr /><div><a href="' . $export_url . '">' . __('Export results', 'wpu_polls') . '</a></div>';

            } else {
                echo '<h3>' . __('Results', 'wpu_polls') . '</h3>';
                echo '<table contenteditable class="widefat striped">';
                echo '<thead>';
                echo '<th>' . __('Answer', 'wpu_polls') . '</th>';
                echo '<th>' . __('Votes', 'wpu_polls') . '</th>';
                echo '<th>' . __('Percent', 'wpu_polls') . '</th>';
                echo '</thead>';
                echo '<tbody>';
                foreach ($answers_display as $answer) {
                    echo '<tr>';
                    echo '<td>' . $answer['answer'] . '</td>';
                    echo '<td>' . $answer['votes_str'] . '</td>';
                    echo '<td>' . $answer['percent'] . '</td>';
                    echo '</tr>';
                }
                echo '</tbody>';
                echo '</table>';
            }

        }

        /* Hidden fields */
        wp_nonce_field('wpu_polls_post_form', 'wpu_polls_post_form_nonce');
        echo '</div>';
        echo '<script type="text/template" id="wpu-polls-answer-template">' . $this->get_template_answer() . '</script>';

    }

    /* Answer template */

    private function get_template_answer($vars = array()) {
        $template = '';
        $template .= '<tr class="answer-line">';
        $template .= '<td style="width:1em"><span class="dashicons dashicons-menu-alt2"></span></td>';
        $template .= '<td style="width:150px;">';
        $template .= '<div class="answer-line__image" data-has-image="##image##">';
        $template .= '<input class="input-image" name="wpu_polls_answer_image[]" type="hidden" value="##image##" />';
        $template .= '<div class="preview-image">##imagepreview##</div>';
        $template .= '<button type="button" class="add-image">' . __('Add an image', 'wpu_polls') . '</button>';
        $template .= '<button type="button" class="remove-image">' . __('Remove this image', 'wpu_polls') . '</button>';
        $template .= '</div>';
        $template .= '</td>';
        $template .= '<td><input class="answer-line__uniqid" name="wpu_polls_uniqid[]" type="hidden" value="##uniqid##" /><input class="answer-text" name="wpu_polls_answer[]" type="text" value="##answer##" /></td>';
        $template .= '<td style="width:1em;"><button class="delete-line" title="' . esc_attr(__('Delete this reply', 'wpu_polls')) . '">&times;</button></td>';
        $template .= '</tr>';
        if (!is_array($vars)) {
            $vars = array();
        }
        if (!isset($vars['image']) || !$vars['image']) {
            $vars['image'] = 0;
        }
        foreach ($vars as $key => $var) {
            $template = str_replace('##' . $key . '##', $var, $template);
        }

        return $template;
    }

    /* Getter */
    private function get_post_answers($post_id, $args = array()) {
        $answers = get_post_meta($post_id, 'wpu_polls__answers', 1);
        if (!is_array($answers)) {
            $answers = array();
        }
        if (!is_array($args)) {
            $args = array();
        }
        if (!isset($args['image_size'])) {
            $args['image_size'] = 'thumbnail';
        }
        foreach ($answers as $k => $answer) {
            $answers[$k]['imagepreview'] = '';
            if ($answer['image']) {
                $img = wp_get_attachment_image($answer['image'], $args['image_size']);
                if ($img) {
                    $answers[$k]['imagepreview'] = $img;
                }
            }
        }
        return $answers;
    }

    function get_poll_nbvotesmax($poll_id) {
        /* Get number of votes */
        $nbvotesmax = get_post_meta($poll_id, 'wpu_polls__nbvotesmax', 1);
        if (!$nbvotesmax) {
            $nbvotesmax = $this->nb_max;
        }
        return intval($nbvotesmax, 10);
    }

    /* Save poll
    -------------------------- */

    public function save_poll($post_id) {

        /* Only once */
        if (defined('WPU_POLLS__SAVE_POST')) {
            return;
        }
        define('WPU_POLLS__SAVE_POST', 1);

        if (!current_user_can('edit_post', $post_id)) {
            return false;
        }

        /* Empty or invalid post response */
        $post_keys = array(
            'wpu_polls_uniqid',
            'wpu_polls_answer',
            'wpu_polls_answer_image'
        );
        if (empty($_POST)) {
            return;
        }
        foreach ($post_keys as $post_key) {
            if (!isset($_POST[$post_key]) || !is_array($_POST[$post_key])) {
                return;
            }
        }

        /* Invalid rights */
        if (!isset($_POST['wpu_polls_post_form_nonce']) || !wp_verify_nonce($_POST['wpu_polls_post_form_nonce'], 'wpu_polls_post_form')) {
            wp_nonce_ays('');
        }

        /* Save answers */
        $answers = array();
        foreach ($_POST['wpu_polls_answer'] as $i => $answer) {
            $answer = esc_html($answer);
            if (!isset($_POST['wpu_polls_uniqid'][$i])) {
                return;
            }
            if (!isset($_POST['wpu_polls_answer_image'][$i])) {
                return;
            }
            $uniqid = $_POST['wpu_polls_uniqid'][$i];
            if (!$uniqid) {
                $uniqid = md5(microtime());
            }
            $image = $_POST['wpu_polls_answer_image'][$i];
            if (!is_numeric($image)) {
                $image = 0;
            }

            $answers[] = array(
                'answer' => trim(esc_html($answer)),
                'uniqid' => trim(esc_html($uniqid)),
                'image' => $image
            );
        }
        update_post_meta($post_id, 'wpu_polls__answers', $answers);
        wp_update_post(array(
            'ID' => $post_id,
            'post_content' => '[wpu_polls id="' . $post_id . '"]'
        ));

    }

    /* ----------------------------------------------------------
      Vote
    ---------------------------------------------------------- */

    /* Add vote */

    private function add_votes($poll_id, $answers_ids, $post = array()) {
        if (!is_numeric($poll_id) || !is_array($answers_ids)) {
            wp_send_json_error(array('stop_form' => true, 'mark_as_voted' => false, 'error_message' => __('Invalid poll', 'wpu_polls')));
        }

        $answers_ids = array_unique($answers_ids);

        $answers = get_post_meta($poll_id, 'wpu_polls__answers', 1);

        /* If more than a response : check if poll is multi answers */
        $nbanswers = get_post_meta($poll_id, 'wpu_polls__nbanswers', 1);
        if (count($answers_ids) > $nbanswers || count($answers) < count($answers_ids)) {
            wp_send_json_error(array('stop_form' => true, 'mark_as_voted' => false, 'error_message' => __('Too much answers', 'wpu_polls')));
        }

        /* Get number of votes */
        $nbvotesmax = $this->get_poll_nbvotesmax($poll_id);

        /* Retrieve votes */
        $votes = $this->get_votes_for_poll($poll_id);

        /* Required */
        $requiredetails = get_post_meta($poll_id, 'wpu_polls__requiredetails', 1);
        $post['user_id'] = 0;
        if ($requiredetails == '1') {
            /* If user is logged-in : force default values */
            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                $user = get_userdata($user_id);
                $post['user_name'] = $user->display_name;
                $post['user_email'] = $user->user_email;
                $post['user_id'] = $user_id;
            }
            if (!isset($post['user_name']) || empty($post['user_name'])) {
                wp_send_json_error(array('stop_form' => true, 'mark_as_voted' => false, 'error_message' => __('Form is invalid', 'wpu_polls')));
            }
            if (!isset($post['user_email']) || empty($post['user_email']) || !is_email($post['user_email'])) {
                wp_send_json_error(array('stop_form' => true, 'mark_as_voted' => false, 'error_message' => __('Form is invalid', 'wpu_polls')));
            }
        }

        if (isset($post['user_email'])) {
            global $wpdb;

            /* Check email */
            $has_voted = $wpdb->get_row($wpdb->prepare("SELECT user_email FROM " . $this->baseadmindatas->tablename . " WHERE user_email=%s AND post_id=%d", $post['user_email'], $poll_id));
            if (is_object($has_voted) && isset($has_voted->user_email)) {
                wp_send_json_error(array('stop_form' => true, 'mark_as_voted' => true, 'error_message' => __('You have already sent a reply', 'wpu_polls')));
            }

            /* Check user login */
            if (is_user_logged_in()) {
                $has_voted = $wpdb->get_row($wpdb->prepare("SELECT user_id FROM " . $this->baseadmindatas->tablename . " WHERE user_id=%s AND post_id=%d", get_current_user_id(), $poll_id));
                if (is_object($has_voted) && isset($has_voted->user_id)) {
                    wp_send_json_error(array('stop_form' => true, 'mark_as_voted' => true, 'error_message' => __('You have already sent a reply', 'wpu_polls')));
                }
            }
        }

        /* Check that every answers exists */
        $accepted_answers = array();
        foreach ($answers as $answer) {
            $accepted_answers[] = $answer['uniqid'];
        }

        foreach ($answers_ids as $answer_id) {
            /* Answer does not exists */
            if (!in_array($answer_id, $accepted_answers)) {
                wp_send_json_error(array('stop_form' => true, 'mark_as_voted' => false, 'error_message' => __('Invalid answer', 'wpu_polls')));
            }

            if (isset($votes['results'], $votes['results'][$answer_id]) && $votes['results'][$answer_id] >= $nbvotesmax) {
                wp_send_json_error(array('stop_form' => true, 'mark_as_voted' => false, 'error_message' => __('Max number of answers has been reached', 'wpu_polls')));
            }
        }

        /* Answer */
        foreach ($answers_ids as $answer_id) {
            $answer_data = array(
                'post_id' => $poll_id,
                'answer_id' => esc_html($answer_id)
            );
            if ($requiredetails == '1') {
                $answer_data['user_email'] = $post['user_email'];
                $answer_data['user_id'] = $post['user_id'];
                $answer_data['user_name'] = $post['user_name'];
                $answer_data['gdpr'] = isset($post['user_gdpr']) ? 1 : 0;
            }
            $this->baseadmindatas->create_line($answer_data);
        }
        return true;
    }

    function get_results_for_poll($poll_id) {
        global $wpdb;
        $q = "SELECT * FROM " . $this->baseadmindatas->tablename . " WHERE post_id=%s";
        $prepared_query = $wpdb->prepare($q, $poll_id);
        $short_results = $wpdb->get_results($prepared_query, ARRAY_A);
        $answers = $this->get_post_answers($poll_id);

        $data = array();
        foreach ($answers as $answer) {
            foreach ($short_results as $result) {
                if ($answer['uniqid'] != $result['answer_id']) {
                    continue;
                }
                $data_item = $result;
                $data_item['answer_name'] = $answer['answer'];
                $data[] = $data_item;
            }
        }
        return $data;
    }

    /* Get votes */
    private function get_votes_for_poll($poll_id, $cache_results = false) {
        if (!is_numeric($poll_id)) {
            return;
        }
        global $wpdb;
        $q = "SELECT answer_id , count(*) as results FROM " . $this->baseadmindatas->tablename . " WHERE post_id=%s GROUP BY answer_id";
        $results = $wpdb->get_results($wpdb->prepare($q, $poll_id), ARRAY_A);
        $new_results = array();
        $nb_votes = 0;
        foreach ($results as $res) {
            $nb = intval($res['results'], 10);
            $nb_votes += $nb;
            $new_results[$res['answer_id']] = $nb;
        }

        $nbvotesmax = $this->get_poll_nbvotesmax($poll_id);

        $data = array(
            'nbvotesmax' => $nbvotesmax,
            'poll_id' => $poll_id,
            'nb_votes' => $nb_votes,
            'results' => $new_results
        );

        if ($cache_results) {
            $this->cache_results($data);
        }

        return $data;

    }

    private function get_cache_path_dir($type = 'basedir') {
        $upload_dir = wp_upload_dir();
        $sep = DIRECTORY_SEPARATOR;
        return $upload_dir[$type] . $sep . 'wpu_polls' . $sep;
    }

    /* Cache */
    private function cache_results($data) {
        if (!is_array($data)) {
            return;
        }
        $cache_dir = $this->get_cache_path_dir();
        if (!is_dir($cache_dir)) {
            mkdir($cache_dir);
        }
        $cache_file = $cache_dir . $data['poll_id'] . '.json';
        return file_put_contents($cache_file, json_encode($data));
    }

    /* ----------------------------------------------------------
      AJAX Event
    ---------------------------------------------------------- */

    public function ajax_action() {
        if (!isset($_POST['poll_id'], $_POST['answers'])) {
            wp_send_json_error();
        }
        if (!$this->add_votes($_POST['poll_id'], $_POST['answers'], $_POST)) {
            wp_send_json_error();
        }
        wp_send_json($this->get_votes_for_poll($_POST['poll_id'], 1));
    }

    /* ----------------------------------------------------------
      User
    ---------------------------------------------------------- */

    function edit_user_box($profile_user) {
        if (!is_object($profile_user)) {
            return;
        }
        global $wpdb;
        $q = "SELECT * FROM " . $this->baseadmindatas->tablename . " WHERE user_id=%s";
        $prepared_query = $wpdb->prepare($q, $profile_user->ID);
        $short_results = $wpdb->get_results($prepared_query, ARRAY_A);

        if (empty($short_results)) {
            return;
        }

        echo '<div class="postbox">';
        echo '<div class="postbox-header"><h2>' . __('Votes', 'wpu_polls') . '</h2></div>';
        echo '<div class="inside">';
        echo '<ul>';
        foreach ($short_results as $res) {
            echo '<li>#' . $res['post_id'] . ' - <a href="' . admin_url('post.php?post=' . $res['post_id'] . '&action=edit') . '">' . get_the_title($res['post_id']) . '</a></li>';
        }
        echo '</ul>';
        echo '</div>';
        echo '</div>';
    }

    /* ----------------------------------------------------------
      Shortcode
    ---------------------------------------------------------- */

    public function shortcode($atts = array()) {
        if (!is_array($atts) || !isset($atts['id']) || !$atts['id'] || !is_numeric($atts['id'])) {
            return '';
        }
        return $this->get_vote_content($atts['id']);
    }

    private function get_vote_content($poll_id) {
        $nbanswers = get_post_meta($poll_id, 'wpu_polls__nbanswers', 1);
        if (!$nbanswers) {
            $nbanswers = 1;
        }

        $requiredetails = get_post_meta($poll_id, 'wpu_polls__requiredetails', 1);
        $displaymessage = get_post_meta($poll_id, 'wpu_polls__displaymessage', 1);
        $gdprcheckbox = get_post_meta($poll_id, 'wpu_polls__gdprcheckbox', 1);

        $nbvotesmax = $this->get_poll_nbvotesmax($poll_id);

        $question = get_post_meta($poll_id, 'wpu_polls__question', 1);
        $answers = $this->get_post_answers($poll_id, array(
            'image_size' => 'medium'
        ));
        if (!$question || !$answers) {
            return '';
        }

        $results = $this->get_votes_for_poll($poll_id, 1);

        $id_prefix = 'answer__' . $poll_id . '__';

        $html_main = '';
        $html_results = '';
        $has_answer_image = false;
        foreach ($answers as $answer) {
            $answer_id = $id_prefix . $answer['uniqid'];
            if ($answer['imagepreview']) {
                $has_answer_image = true;
            }

            /* Main */
            $html_main .= '<li class="wpu-poll-main__answer" data-results-id="' . esc_attr($answer['uniqid']) . '">';
            $html_main .= $this->get_vote_content__item_main($poll_id, $answer_id, $answer, $nbanswers == 1 ? 'radio' : 'checkbox');
            $html_main .= '</li>';

            /* Results */
            $html_results .= '<li class="wpu-poll-results__answer" data-results-id="' . esc_attr($answer['uniqid']) . '">';
            $html_results .= $this->get_vote_content__item_results($answer_id, $answer);
            $html_results .= '</li>';
        }

        $has_voted = 0;
        if (is_user_logged_in() && $requiredetails) {
            global $wpdb;
            $usr = get_user_by('ID', get_current_user_id());
            $has_voted_test = $wpdb->get_row($wpdb->prepare("SELECT user_email FROM " . $this->baseadmindatas->tablename . " WHERE (user_email=%s OR user_id=%d) AND post_id=%d", $usr->user_email, get_current_user_id(), $poll_id));
            if (is_object($has_voted_test) && $has_voted_test->user_email) {
                $has_voted = 1;
            }
        }

        /* Wrapper start */
        $html = '<div class="wpu-poll-main__wrapper" ' . ($nbanswers > 1 ? ' data-nb-answers="' . $nbanswers . '"' : '') . ' data-has-image="' . ($has_answer_image ? '1' : '0') . '" data-has-required-details="' . ($requiredetails ? '1' : '0') . '" data-nb-votes-max="' . $nbvotesmax . '" data-has-voted="' . $has_voted . '" data-poll-id="' . $poll_id . '">';

        /* Questions */
        $html .= '<h3 class="wpu-poll-main__question">' . $question . '</h3>';

        /* Answers */
        $html .= '<div class="wpu-poll-main">';
        $html .= '<ul data-has-image="' . ($has_answer_image ? '1' : '0') . '" class="wpu-poll-main__answers">';
        $html .= $html_main;
        $html .= '</ul>';

        if ($requiredetails == '1') {
            $user_name = '';
            $user_email = '';
            $readonly = '';
            $label_extra = '<em>*</em>';
            if (is_user_logged_in()) {
                $user = get_userdata(get_current_user_id());
                $user_name = $user->display_name;
                $user_email = $user->user_email;
                $label_extra = '';
                $readonly = 'readonly';
            }
            $html .= '<div class="wpu-polls-require-details-area">';
            $html .= '<p><label for="' . $id_prefix . '_name">' . __('Name', 'wpu_polls') . $label_extra . '</label><input required ' . $readonly . ' value="' . esc_attr($user_name) . '" id="' . $id_prefix . '_name" name="user_name" type="text" /></p>';
            $html .= '<p><label for="' . $id_prefix . '_email">' . __('Email', 'wpu_polls') . $label_extra . '</label><input required ' . $readonly . ' value="' . esc_attr($user_email) . '" id="' . $id_prefix . '_email" name="user_email" type="email" /></p>';
            if ($gdprcheckbox) {
                $gdpr_message = apply_filters('wpu_polls__gdprcheckbox__text', sprintf(__('I agree to be contacted by phone or email by %s or its partners.', 'wpu_polls'), get_option('blogname')));
                $html .= '<p>';
                $html .= '<input value="1" id="' . $id_prefix . '_gdpr" name="user_gdpr" type="checkbox" />';
                $html .= '<label for="' . $id_prefix . '_gdpr">' . $gdpr_message . '</label>';
                $html .= '</p>';
            }
            $html .= '</div>';
        }

        $html .= '<p class="wpu-poll-main__submit"><button type="button"><span>' . __('Submit', 'wpu_polls') . '</span></button></p>';
        $html .= '</div>';

        /* Results */
        if ($displaymessage) {
            $displaymessage__content = get_post_meta($poll_id, 'wpu_polls__displaymessage__content', 1);
            if (!$displaymessage__content) {
                $displaymessage__content = apply_filters('wpu_polls__success_message', __('Thank you for your vote !', 'wpu_polls'));
            }
            $html .= '<div data-nosnippet class="wpu-poll-success-message">';
            $html .= '<p>' . $displaymessage__content . '</p>';
            $html .= '</div>';
        } else {
            $html .= '<div data-nosnippet class="wpu-poll-results">';
            $html .= '<ul data-has-image="' . ($has_answer_image ? '1' : '0') . '">';
            $html .= $html_results;
            $html .= '</ul>';
            $html .= '</div>';
        }

        /* Message */
        $html .= '<div class="wpu-poll-main__message"></div>';

        /* Wrapper end */
        $html .= '</div>';

        return $html;

    }

    function get_vote_content__item_main($poll_id, $answer_id, $answer, $type = 'checkbox') {
        $nbvotesmax = $this->get_poll_nbvotesmax($poll_id);
        $html_main = '';
        if ($answer['imagepreview']) {
            $html_main .= '<label class="part-image" for="' . $answer_id . '">' . $answer['imagepreview'] . '</label>';
        }
        $html_main .= '<div class="answer__inner">';
        $html_main .= '<span class="part-answer"><input id="' . esc_attr($answer_id) . '" type="' . $type . '" name="answers" value="' . esc_attr($answer['uniqid']) . '" /><label for="' . $answer_id . '">' . $answer['answer'] . '</label></span>';
        if ($nbvotesmax < 99) {
            $html_main .= '<span>(' . __('Available: ', 'wpu_polls') . '<span class="nbvotesmax_value">0</span>)</span>';
        }
        $html_main .= '</div>';
        return apply_filters('wpu_polls__get_vote_content__item_main__html', $html_main, $answer_id, $answer);
    }

    function get_vote_content__item_results($answer_id, $answer) {
        $html_results = $answer['imagepreview'];
        $html_results .= '<div class="answer__inner">';
        $html_results .= '<span class="part-answer"><span class="answer-text">' . $answer['answer'] . '</span><span class="count"></span><span class="percent"></span></span>';
        $html_results .= '<span class="part-background"><span class="background"></span><span class="bar-count"></span></span>';
        $html_results .= '</div>';

        return apply_filters('wpu_polls__get_vote_content__item_results__html', $html_results, $answer_id, $answer);
    }

}

$WPUPolls = new WPUPolls();
