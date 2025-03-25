<?php
defined('ABSPATH') || die;
$short_results = $this->get_results_for_poll($post->ID);
echo '<h3>' . __('Votes', 'wpu_polls') . '</h3>';
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
$export_url = admin_url('post.php?post=' . get_the_ID() . '&action=edit&wpu_polls_export_csv=1');
echo '<hr /><div><a href="' . $export_url . '">' . __('Export results', 'wpu_polls') . '</a></div>';
