document.addEventListener('DOMContentLoaded', function() {
    'use strict';
    /* Hide metabox layout */
    jQuery('#wpu-polls-box-id').each(function() {
        jQuery(this).removeClass('postbox');
        jQuery(this).find('.postbox-header').remove();
    });

    var $answers = jQuery('.wpu-polls-answers'),
        $button_add = jQuery('#wpu-polls-answer-add-line');

    /* Allow sortable answers */
    $answers.sortable();

    /* Add a line function */
    function add_line() {
        var _uniqid = 't' + Math.random().toString(12).slice(2, 12) + Date.now();
        var _tpl_content_line = jQuery('#wpu-polls-answer-template').html().replace('##uniqid##', _uniqid);
        /* Remove template vars */
        _tpl_content_line = _tpl_content_line.replace(/##[a-z]+##/g, '');
        /* Insert line */
        var $new_line = jQuery(_tpl_content_line);
        $answers.append($new_line);
        $new_line.find('input.answer-text').focus();
    }

    /* Add a line : button click */
    $button_add.on('click', function(e) {
        e.preventDefault();
        add_line();
    });

    /* Add a line : press enter */
    $answers.on('keydown', function(e) {
        if (e.keyCode == 13) {
            e.preventDefault();
            add_line();
        }
    });

    /* Remove a line */
    $answers.on('click', '.delete-line', function(e) {
        e.preventDefault();
        jQuery(this).closest('.answer-line').remove();
    });

    /* Default Answer */
    if (!$answers.children().length) {
        add_line();
    }
});
