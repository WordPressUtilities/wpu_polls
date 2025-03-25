<?php
defined('ABSPATH') || die;
echo '<h3>' . __('Results', 'wpu_polls') . '</h3>';
if ($total_votes) {
    echo '<p>' . sprintf(__('Total number of results: <b>%s</b>', 'wpu_polls'), $total_votes) . '</p>';
}
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
