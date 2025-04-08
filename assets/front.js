document.addEventListener('DOMContentLoaded', function() {
    'use strict';

    /* Update all polls : counter & vote status */
    function update_all_polls() {
        jQuery('[data-poll-id]').each(function() {
            var $wrapper = jQuery(this),
                _poll_id = $wrapper.attr('data-poll-id');

            /* Check vote status */
            check_wrapper_vote($wrapper, _poll_id);

            /* Update vote counter */
            jQuery.ajax({
                dataType: "json",
                cache: false,
                url: wpu_polls_settings.cacheurl + _poll_id + '.json',
                success: function(response) {
                    wpu_poll_build_results($wrapper, response);
                }
            });
        });
    }

    update_all_polls();
    setInterval(function() {
        update_all_polls();
    }, 10000);

    /* Limit to checkboxes */
    jQuery('.wpu-poll-main__wrapper[data-nb-answers] .wpu-poll-main__answers').on('change', 'input[type="checkbox"], input[type="radio"]', function() {
        var $this = jQuery(this),
            $wrapper = $this.closest('.wpu-poll-main__wrapper'),
            _minAnswers = parseInt($wrapper.attr('data-min-answers'), 10),
            _maxNb = parseInt($wrapper.attr('data-nb-answers'), 10);
        var _checkedBoxes = $wrapper.find('.wpu-poll-main__answers').find('input[type="checkbox"]:checked,input[type="radio"]:checked').length,
            _hasCheckboxes = $wrapper.find('.wpu-poll-main__answers').find('input[type="checkbox"]').length;

        /* Prevent selecting too many answers */
        if (_checkedBoxes > _maxNb) {
            $this.prop('checked', false);
        }

        if (_minAnswers > 0 && _checkedBoxes < _minAnswers) {
            $wrapper.attr('data-min-answers-locked', 1);
        } else {
            $wrapper.attr('data-min-answers-locked', 0);
        }

        /* Visual indicator */
        if (_hasCheckboxes) {
            $wrapper.attr('data-max-answers-locked', (_checkedBoxes >= _maxNb) ? 1 : 0);
        }
    });



    function update_checked_state($wrapper) {
        $wrapper.find('.wpu-poll-main__answers input[type="checkbox"], .wpu-poll-main__answers input[type="radio"]').each(function() {
            var $this = jQuery(this);
            $this.closest('.wpu-poll-main__answer').attr('data-checked', $this.prop('checked') ? '1' : '0');
        });
    }

    jQuery('.wpu-poll-main__wrapper').each(function() {
        var $wrapper = jQuery(this),
            $details_area = $wrapper.find('.wpu-polls-require-details-area');

        /* Set visual indicator for checked state */
        update_checked_state($wrapper);
        $wrapper.on('change', 'input[type="checkbox"], input[type="radio"]', function() {
            update_checked_state($wrapper);
        });

        // Check validity of detail fields
        if ($details_area.length) {
            return;
        }
        var $fields = $details_area.find('input, textarea, select');

        // Track if all fields are valid
        var checkDetailsFieldsValidity = function() {
            var allFieldsValid = true;

            $fields.each(function() {
                var $field = jQuery(this);
                var field = $field.get(0);

                /* Check if field has a required attribute and is empty */
                if ($field.attr('required') && !$field.val().trim()) {
                    allFieldsValid = false;
                }
                /* Check validity using the browser's validation API */
                if (field && typeof field.checkValidity === 'function' && !field.checkValidity()) {
                    allFieldsValid = false;
                }
            });

            // Update form state
            $wrapper.attr('data-required-details-valid', allFieldsValid ? '1' : '0');
        };

        // Initial validation check
        checkDetailsFieldsValidity();

        // Recheck on input change
        $fields.on('input change', checkDetailsFieldsValidity);

    });

    function check_required_details($container) {
        var $fields = $container.find('input, textarea, select');

        var isFormValid = true;

        $fields.each(function() {
            var $field = jQuery(this);
            var field = $field.get(0);

            /* Check if field is required and empty */
            if ($field.attr('required') && !$field.val().trim()) {
                isFormValid = false;
            }

            /* Check validity using the browser's validation API */
            if (field && typeof field.checkValidity === 'function' && !field.checkValidity()) {
                isFormValid = false;
            }
        });

        $container.attr('data-form-valid', isFormValid ? '1' : '0');
        return isFormValid;
    }

    jQuery('.wpu-poll-main__wrapper').each(function(i, $el) {
        /* Initial check */
        check_required_details(jQuery($el));

        /* Watch */
        jQuery($el).on('change', 'input, textarea, select', function() {
            check_required_details(jQuery($el));
        });
    });

    /* Vote */
    jQuery('.wpu-poll-main__submit button').on('click', function(e) {
        e.preventDefault();

        /* Items */
        var $button = jQuery(this),
            $wrapper = $button.closest('.wpu-poll-main__submit'),
            $main = $wrapper.closest('.wpu-poll-main__wrapper'),
            $user_name = $main.find('[name="user_name"]'),
            $user_email = $main.find('[name="user_email"]'),
            $user_gdpr = $main.find('[name="user_gdpr"]'),
            $user_comment = $main.find('[name="user_comment"]'),
            $answers = $main.find('.wpu-poll-main__answers'),
            _minAnswers = parseInt($main.attr('data-min-answers'), 10);

        /* Extract poll id */
        var _poll_id = $main.attr('data-poll-id');

        /* Check value */
        var $checkboxes = $answers.find('input[name="answers"]:checked');
        if (!$checkboxes.length) {
            return false;
        }

        if (_minAnswers > 0 && $checkboxes.length < _minAnswers) {
            return false;
        }

        var _values = [];
        $checkboxes.each(function() {
            _values.push(jQuery(this).val());
        });

        if ($main.attr('data-has-required-details') == '1' && !check_required_details($main)) {
            return false;
        }

        /* Loader */
        $main.addClass('is-loading');
        $button.prop('disabled', 1);

        var _data = {
            'action': 'wpu_polls_answer',
            'poll_id': _poll_id,
            'answers': _values
        };

        if ($user_name) {
            _data.user_name = $user_name.val();
        }
        if ($user_email) {
            _data.user_email = $user_email.val();
        }
        if ($user_gdpr) {
            _data.user_gdpr = $user_gdpr.val();
        }
        if ($user_comment) {
            _data.user_comment = $user_comment.val();
        }

        /* Send action */
        jQuery.post(
            wpu_polls_settings.ajaxurl, _data,
            function(response) {
                /* Failure : clean up everything */
                if (response.hasOwnProperty('success') && !response.success) {
                    update_all_polls();
                    if (response.data.hasOwnProperty('stop_form') && response.data.hasOwnProperty('stop_form')) {
                        var $message = $main.find('.wpu-poll-main__message');
                        $main.attr('data-prevent-form', '1');
                        $message.html(response.data.error_message);
                    } else {
                        $button.prop('disabled', false);
                        $main.find('input:checked').prop('checked', false);
                    }

                    /* Mark as voted */
                    if (response.data.hasOwnProperty('mark_as_voted') && response.data.mark_as_voted) {
                        localStorage.setItem('wpu_polls_' + _poll_id, '1');
                    }

                    $main.removeClass('is-loading');
                    return false;
                }

                /* Store vote status */
                localStorage.setItem('wpu_polls_' + _poll_id, '1');

                /* Update status */
                check_wrapper_vote($main, _poll_id);
                wpu_poll_build_results($main, response);
                $main.removeClass('is-loading');

            }
        );

    });

    function wpu_poll_build_results($wrapper, response) {
        var $answers = $wrapper.find('.wpu-poll-results'),
            $questions = $wrapper.find('.wpu-poll-main__answers'),
            _max_nb = parseInt($wrapper.attr('data-nb-votes-max'), 10),
            _sort_results = parseInt($wrapper.attr('data-sort-results'), 10),
            $list = $wrapper.find('.wpu-poll-results__list'),
            $items = $answers.find('[data-results-id]'),
            _nb_votes_available,
            $tmp_item,
            _percent;

        /* If update time is available */
        if (response.update_time) {
            var tmp_update_time = parseInt($wrapper.attr('data-update-time'), 10),
                response_update_time = parseInt(response.update_time, 10);
            /* Stop refresh if data is the same */
            if (tmp_update_time == response_update_time) {
                return;
            }
            $wrapper.attr('data-update-time', response_update_time);
        }

        /* Closed */
        if (response.is_closed) {
            $wrapper.attr('data-is-closed', 1);
            $wrapper.find('.wpu-poll-main').remove();
        }

        /* Default content */
        $items.each(function(i, el) {
            wpu_poll_build_results_item(jQuery(el), 0, 0);
        });

        for (var answer_id in response.results) {
            /* Answers */
            $tmp_item = $answers.find('[data-results-id="' + answer_id + '"]');
            if ($tmp_item.length) {
                _percent = Math.round(response.results[answer_id] / response.nb_votes * 100);
                wpu_poll_build_results_item($tmp_item, response.results[answer_id], _percent);
            }
            /* Questions */
            $tmp_item = $questions.find('[data-results-id="' + answer_id + '"]');
            if (_max_nb < 99 && $tmp_item.length && response.results[answer_id]) {
                _nb_votes_available = Math.max(0, _max_nb - response.results[answer_id]);
                $tmp_item.find('.nbvotesmax_value').text(_nb_votes_available);
                if (_nb_votes_available < 1) {
                    $tmp_item.attr('data-disabled', 1);
                    $tmp_item.find('input[type="checkbox"]').prop('disabled', 1);
                }
            }
        }

        if (_sort_results == '1') {
            $items.sort(wpu_poll_sort_by_count);
        } else {
            $items.sort(function() {
                return 0.5 - Math.random();
            });
        }

        $items.each(function(i, el) {
            el.setAttribute('data-i', i + 1);
            $list.append(jQuery(el));
        });

    }

    function wpu_poll_sort_by_count(a, b) {
        var countA = parseInt(a.getAttribute('data-count'));
        var countB = parseInt(b.getAttribute('data-count'));
        return countB - countA;
    }

    function wpu_poll_build_results_item($item, _nb_results, _percent) {
        var _count_str = wpu_polls_settings.str_0_vote;
        if (_nb_results > 0) {
            _count_str = wpu_polls_settings.str_one_vote;
        }
        if (_nb_results > 1) {
            _count_str = wpu_polls_settings.str_n_votes.replace('%d', _nb_results);
        }
        $item.attr('data-count', _nb_results);
        $item.find('.percent,[data-wpupolls-value="percent"]').text(_percent + '%');
        $item.find('.count,[data-wpupolls-value="count"]').text(_count_str);
        $item.find('.bar-count').css('width', _percent + '%');
    }

    function check_wrapper_vote($wrapper, _poll_id) {
        var _vote = localStorage.getItem('wpu_polls_' + _poll_id);
        if (_vote == '1') {
            $wrapper.attr('data-has-voted', 1);
            $wrapper.find('.wpu-poll-main').remove();
        }
    }
});
