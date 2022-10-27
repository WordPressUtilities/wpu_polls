document.addEventListener('DOMContentLoaded', function() {
    /* Hide metabox layout */
    jQuery('#wpu-polls-box-id').each(function(){
        jQuery(this).removeClass('postbox');
        jQuery(this).find('.postbox-header').remove();
    });

    jQuery('.wpu-polls-answers').sortable();

});
