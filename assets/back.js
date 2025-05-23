document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    /* Only on a polls edit page */
    var $form = jQuery('#post'),
        $body = jQuery('body');
    if (!$form.length || !$body.hasClass('post-type-polls')) {
        return;
    }

    /* Delete vote */
    jQuery('.delete-vote-button').on('click', function(e) {
        e.preventDefault();
        var $btn = jQuery(this);
        if (confirm(wpu_polls_settings_back.confirm_vote_deletion)) {
            window.location.href = $btn.attr('data-delete-button-url') + '&wpu_polls_nonce=' + jQuery('[name="wpu_polls_nonce"]').val();
        }
    });

    /* Handle minmax answers */
    jQuery('#wpubasefields_wpu_polls__nbanswers').on('change', function() {
        var $this = jQuery(this),
            $min = jQuery('#wpubasefields_wpu_polls__nbanswers_min');
        if ($this.val() < $min.val()) {
            $this.val($min.val());
            alert(wpu_polls_settings_back.max_vote_invalid);
        }
    });
    jQuery('#wpubasefields_wpu_polls__nbanswers_min').on('change', function() {
        var $this = jQuery(this),
            $max = jQuery('#wpubasefields_wpu_polls__nbanswers');
        if ($this.val() > $max.val()) {
            $this.val($max.val());
            alert(wpu_polls_settings_back.min_vote_invalid);
        }
    });

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
        _tpl_content_line = _tpl_content_line.replace(/##image##/g, '0');
        _tpl_content_line = _tpl_content_line.replace(/##[a-z_]+##/g, '');
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
        if (e.keyCode != 13) {
            return;
        }

        if (!jQuery(e.target).closest('.answer-line').is(':last-child')) {
            return;
        }

        e.preventDefault();
        add_line();
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

    /* Admin image */
    var frames = {};

    // Add image link
    jQuery('body').on('click', '.answer-line__image .add-image', function(e) {
        e.preventDefault();
        var $items = img_preview_get_items(jQuery(this));

        if (frames[$items.uniqid]) {
            frames[$items.uniqid].open();
            return;
        }

        frames[$items.uniqid] = wp.media({
            multiple: false
        });

        var frame = frames[$items.uniqid];
        frame.on('select', function() {
            var attachment = frame.state().get('selection').first().toJSON();
            $items.$wrapper.attr('data-has-image', attachment.id);
            $items.$preview.html('<img src="' + attachment.url + '" alt="" style="max-width:100%;"/>');
            $items.$input.val(attachment.id);
        });

        frame.open();
    });

    // Delete image link
    jQuery('body').on('click', '.answer-line__image .remove-image', function(e) {
        e.preventDefault();
        var $items = img_preview_get_items(jQuery(this));
        $items.$wrapper.attr('data-has-image', '0');
        $items.$preview.html('');
        $items.$input.val('0');
    });

    function img_preview_get_items($start) {
        var $items = {};
        $items.uniqid = $start.closest('.answer-line').find('.answer-line__uniqid').val();
        $items.$wrapper = $start.closest('.answer-line__image');
        $items.$preview = $items.$wrapper.find('.preview-image');
        $items.$input = $items.$wrapper.find('.input-image');
        return $items;
    }

    /* Avoid some errors on submit */
    $form.on('submit', function(e) {
        var _answers = $form.find('.wpu-polls-answers .answer-line');
        if (_answers.length < 2) {
            e.preventDefault();
            alert(wpu_polls_settings_back.error_need_two_choices);
        }

        /* Images */
        var _nb_images = 0;
        _answers.find('.input-image').each(function(i, $item) {
            if ($item.value && $item.value != '0') {
                _nb_images++;
            }
        });
        if (_nb_images > 0 && _nb_images != _answers.length) {
            e.preventDefault();
            alert(wpu_polls_settings_back.error_need_all_images);
        }
        /* Text */
        var _nb_text = 0;
        _answers.find('.answer-text').each(function(i, $item) {
            if ($item.value) {
                _nb_text++;
            }
        });
        if (_nb_text > 0 && _nb_text != _answers.length) {
            e.preventDefault();
            alert(wpu_polls_settings_back.error_need_all_text);
        }
        /* Content */
        if (_nb_text == 0 && _nb_images == 0) {
            e.preventDefault();
            alert(wpu_polls_settings_back.error_need_content);
        }
    });

});
