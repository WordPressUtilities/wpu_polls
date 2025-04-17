<?php
defined('ABSPATH') || die;
$short_results = $this->get_results_for_poll($post->ID);
$has_too_much_votes = (count($short_results) > 100);
$wpu_polls_load_results = isset($_GET['wpu_polls_load_results']) ? (int) $_GET['wpu_polls_load_results'] : 0;
$export_url = admin_url('post.php?post=' . get_the_ID() . '&action=edit&wpu_polls_export_csv=1');
$export_html_str = '<hr /><div><a href="' . $export_url . '">' . __('Export results', 'wpu_polls') . '</a></div>';

echo '<h3>' . __('Votes', 'wpu_polls') . '</h3>';
if ($has_too_much_votes && !$wpu_polls_load_results) {
    $edit_post_link = add_query_arg('wpu_polls_load_results', 1, get_edit_post_link($post->ID)) . '#wpu-polls-results-details';
    echo wpautop(sprintf(__('Please <a href="%s">click here</a> to load the detailed votes.', 'wpu_polls'), $edit_post_link));
    echo $export_html_str;
    return;
}

echo '<details ' . ($wpu_polls_load_results ? 'open' : '') . ' id="wpu-polls-results-details" class="wpu-polls-results-details">';
if ($total_votes) {
    echo '<p>' . sprintf(__('Total number of votes: <b>%s</b>', 'wpu_polls'), $total_votes) . '</p>';
}
echo '<table class="widefat striped" id="wpu-polls-table-votes">';
echo '<thead>';
echo '<th>' . __('Answer', 'wpu_polls') . '</th>';
echo '<th>' . __('Name', 'wpu_polls') . '</th>';
echo '<th>' . __('Email', 'wpu_polls') . '</th>';
echo '<th>' . __('User', 'wpu_polls') . '</th>';
echo '<th>' . __('GDPR', 'wpu_polls') . '</th>';
echo '<th>' . __('Language', 'wpu_polls') . '</th>';
echo '<th></th>';
echo '</thead>';

/** Add a nonce field for security */
echo '<input type="hidden" name="wpu_polls_nonce" value="' . esc_attr(wp_create_nonce('wpu_polls_nonce_action')) . '" />';


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
        $html_answer .= '<td>' . esc_html($result['lang']) . '</td>';
        $html_answer .= '<td><button class="delete-vote-button" type="button" data-delete-button-url="' . esc_url($delete_url) . '">&times;</button></td>';
        $html_answer .= '</tr>';
    }
    if ($html_answer) {
        echo '<tbody>' . $html_answer . '</tbody>';
    }
}
echo '</table>';
echo '</details>';
echo $export_html_str;
