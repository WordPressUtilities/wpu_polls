<?php
/*
Plugin Name: WPU Polls
Plugin URI: https://github.com/WordPressUtilities/wpu_polls
Update URI: https://github.com/WordPressUtilities/wpu_polls
Description: WPU Polls handle simple polls
Version: 0.0.3
Author: darklg
Author URI: https://darklg.me/
License: MIT License
License URI: https://opensource.org/licenses/MIT
*/

class WPUPolls {
    private $plugin_version = '0.0.3';
    private $plugin_settings = array(
        'id' => 'wpu_polls',
        'name' => 'WPU Polls'
    );
    private $settings_obj;

    public function __construct() {
        add_filter('plugins_loaded', array(&$this, 'plugins_loaded'));
        add_action('init', array(&$this, 'register_post_type'));
        # Back Assets
        add_action('admin_enqueue_scripts', array(&$this, 'admin_enqueue_scripts'));
        # Front Assets
        add_action('wp_enqueue_scripts', array(&$this, 'wp_enqueue_scripts'));

        /* Admin */
        add_action('add_meta_boxes', function () {
            add_meta_box('wpu-polls-box-id', 'az', array(&$this, 'edit_page_poll'), 'polls');
        });
        add_action('save_post', array(&$this, 'save_poll'));

    }

    public function plugins_loaded() {
        # TRANSLATION
        load_plugin_textdomain('wpu_polls', false, dirname(plugin_basename(__FILE__)) . '/lang/');
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
                'answer_id' => array(
                    'public_name' => 'Answer ID',
                    'type' => 'sql',
                    'sql' => 'varchar(100) DEFAULT NULL'
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
                'import' => array(
                    'name' => __('Import Settings', 'wpu_polls')
                )
            )
        );
        $this->settings = array(
            'value' => array(
                'label' => __('My Value', 'wpu_polls'),
                'help' => __('A little help.', 'wpu_polls'),
                'type' => 'textarea'
            )
        );
        include dirname(__FILE__) . '/inc/WPUBaseSettings/WPUBaseSettings.php';
        $this->settings_obj = new \wpu_polls\WPUBaseSettings($this->settings_details, $this->settings);
    }

    public function admin_enqueue_scripts() {
        /* Back Script */
        wp_register_script('wpu_polls_back_script', plugins_url('assets/back.js', __FILE__), array('jquery', 'jquery-ui-sortable'), $this->plugin_version, true);
        wp_enqueue_script('wpu_polls_back_script');
        /* Back Style */
        wp_register_style('wpu_polls_back_style', plugins_url('assets/back.css', __FILE__), array(), $this->plugin_version);
        wp_enqueue_style('wpu_polls_back_style');
    }

    public function wp_enqueue_scripts() {
        /* Front Script with localization / variables */
        wp_register_script('wpu_polls_front_script', plugins_url('assets/front.js', __FILE__), array(), $this->plugin_version, true);
        wp_localize_script('wpu_polls_front_script', 'wpu_polls_settings', array(
            'my_key' => 'my_value'
        ));
        wp_enqueue_script('wpu_polls_front_script');
        /* Front Style */
        wp_register_style('wpu_polls_front_style', plugins_url('assets/front.css', __FILE__), array(), $this->plugin_version);
        wp_enqueue_style('wpu_polls_front_style');
    }

    public function register_post_type() {
        register_post_type('polls', array(
            'public' => true,
            'label' => __('Polls', 'wpu_polls'),
            'menu_icon' => 'dashicons-format-status',
            'supports' => array('title', 'author')
        ));
    }

    /* ----------------------------------------------------------
      Edit & Save post
    ---------------------------------------------------------- */

    /* Edit poll
    -------------------------- */

    function edit_page_poll($post) {
        $question = get_post_meta($post->ID, 'wpu_polls__question', 1);
        $answers = $this->get_post_answers($post->ID);

        /* Question */
        echo '<p><label for="wpu-polls-question">' . __('Question:', 'wpu_polls') . '</label><br /><input type="text" name="wpu_polls_question" id="wpu-polls-question" value="' . esc_attr($question) . '" /></p>';

        /* Answers */
        echo '<table class="widefat striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th colspan="2">' . __('Answer', 'wpu_polls') . '</th>';
        echo '<th colspan="2">' . __('Image', 'wpu_polls') . '</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody class="wpu-polls-answers" id="wpu-polls-answers">';
        foreach ($answers as $answer) {
            echo $this->get_template_answer($answer);
        }
        echo '</tbody></table>';

        /* Add a line */
        echo '<p><button type="button" id="wpu-polls-answer-add-line">' . __('Add a line', 'wpu_polls') . '</button></p>';

        /* Hidden fields */
        wp_nonce_field('wpu_polls_post_form', 'wpu_polls_post_form_nonce');
        echo '<script type="text/template" id="wpu-polls-answer-template">' . $this->get_template_answer() . '</script>';
    }

    /* Answer template */

    function get_template_answer($vars = array()) {
        $template = '';
        $template .= '<tr class="answer-line">';
        $template .= '<td><span class="dashicons dashicons-menu-alt2"></span></td>';
        $template .= '<td><input name="wpu_polls_uniqid[]" type="hidden" value="##uniqid##" /><input class="answer-text" name="wpu_polls_answer[]" type="text" value="##answer##" /></td>';
        $template .= '<td><input name="wpu_polls_answer_image[]" type="number" value="##image##" /></td>';
        $template .= '<td><button class="delete-line" title="' . esc_attr(__('Delete this line', 'wpu_polls')) . '">&times;</button></td>';
        $template .= '</tr>';
        foreach ($vars as $key => $var) {
            $template = str_replace('##' . $key . '##', $var, $template);
        }

        return $template;
    }

    /* Getter */
    function get_post_answers($post_id) {
        $answers = get_post_meta($post_id, 'wpu_polls__answers', 1);
        if (!is_array($answers)) {
            $answers = array();
        }
        return $answers;
    }

    /* Save poll
    -------------------------- */

    function save_poll($post_id) {
        $post_keys = array(
            'wpu_polls_uniqid',
            'wpu_polls_answer',
            'wpu_polls_answer_image'
        );

        /* Empty or invalid post response */
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
            $image = $_POST['wpu_polls_image'][$i];
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

        /* Save question */
        if (isset($_POST['wpu_polls_question'])) {
            update_post_meta($post_id, 'wpu_polls__question', esc_html($_POST['wpu_polls_question']));
        }

    }

}

$WPUPolls = new WPUPolls();
