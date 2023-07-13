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
    jQuery('.wpu-poll-main__wrapper[data-nb-answers] .wpu-poll-main__answers').on('change', 'input[type="checkbox"]', function() {
        var $this = jQuery(this),
            $wrapper = $this.closest('.wpu-poll-main__wrapper'),
            _maxNb = parseInt($wrapper.attr('data-nb-answers'), 10);
        var _checkedBoxes = $wrapper.find('.wpu-poll-main__answers input[type="checkbox"]:checked').length;

        /* Prevent selecting too many answers */
        if (_checkedBoxes > _maxNb) {
            $this.prop('checked', false);
        }

        /* Visual indicator */
        $wrapper.attr('data-max-answers-locked', (_checkedBoxes >= _maxNb) ? 1 : 0);
    });

    jQuery('.wpu-poll-main__wrapper .wpu-poll-main__answers').on('change', 'input[type="checkbox"], input[type="checkbox"]', function() {
        var $this = jQuery(this);
        $this.closest('.wpu-poll-main__answer').attr('data-checked', $this.prop('checked') ? '1' : '0');
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
            $answers = $main.find('.wpu-poll-main__answers');

        /* Extract poll id */
        var _poll_id = $main.attr('data-poll-id');

        /* Check value */
        var $checkboxes = $answers.find('input[name="answers"]:checked');
        if (!$checkboxes.length) {
            return false;
        }

        var _values = [];
        $checkboxes.each(function() {
            _values.push(jQuery(this).val());
        });

        var _hasRequiredDetails = $main.attr('data-has-required-details');
        if (_hasRequiredDetails == '1') {
            if (!$user_name || !$user_email || !$user_name.val() || !$user_email.val()) {
                return false;
            }
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

        /* Send action */
        jQuery.post(
            wpu_polls_settings.ajaxurl, _data,
            function(response) {
                /* Failure : clean up everything */
                if (response.hasOwnProperty('success') && !response.success) {
                    update_all_polls();
                    $button.prop('disabled', false);
                    $main.find('input:checked').prop('checked', false);
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
            _nb_votes_available,
            $tmp_item,
            _percent;

        /* Default content */
        $answers.find('[data-results-id]').each(function(i, el) {
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
        $item.find('.percent').text(_percent + '%');
        $item.find('.count').text(_count_str);
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
