document.addEventListener('DOMContentLoaded', function(e) {
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

    /* Vote */
    jQuery('.wpu-poll-main__submit button').on('click', function(e) {
        e.preventDefault();

        /* Items */
        var $button = jQuery(this),
            $wrapper = $button.closest('.wpu-poll-main__submit'),
            $main = $wrapper.closest('.wpu-poll-main__wrapper'),
            $answers = $main.find('.wpu-poll-main__answers');

        /* Extract poll id */
        var _poll_id = $main.attr('data-poll-id');

        /* Check value */
        var $value = $answers.find('input[type="radio"]:checked');
        if (!$value.length) {
            return false;
        }

        /* Loader */
        $main.addClass('is-loading');
        $button.prop('disabled', 1);

        /* Store vote status */
        localStorage.setItem('wpu_polls_' + _poll_id, '1');

        /* Send action */
        jQuery.post(
            wpu_polls_settings.ajaxurl, {
                'action': 'wpu_polls_answer',
                'poll_id': _poll_id,
                'answer': $value.val()
            },
            function(response) {
                check_wrapper_vote($main, _poll_id);
                $main.removeClass('is-loading');
                wpu_poll_build_results($main, response);
            }
        );
    });

    function wpu_poll_build_results($wrapper, response) {
        var $answers = $wrapper.find('.wpu-poll-results'),
            $tmp_item,
            _percent;
        for (var answer_id in response.results) {
            $tmp_item = $answers.find('[data-results-id="' + answer_id + '"]');
            if (!$tmp_item.length) {
                continue;
            }
            _percent = Math.round(response.results[answer_id] / response.nb_votes * 100);
            $tmp_item.find('.percent').text(_percent + '%');
            $tmp_item.find('.count').text(response.results[answer_id]);
            $tmp_item.find('.bar-count').css('width', _percent + '%');
        }
    }

    function check_wrapper_vote($wrapper, _poll_id) {
        var _vote = localStorage.getItem('wpu_polls_' + _poll_id);
        if (_vote == '1') {
            $wrapper.attr('data-has-voted', 1);
            $wrapper.find('.wpu-poll-main').remove();
        }
    }
});
