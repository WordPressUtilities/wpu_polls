/* Hide metabox layout */
document.addEventListener('DOMContentLoaded', function() {
    jQuery('#wpu-polls-box-id').each(function(){
        jQuery(this).removeClass('postbox');
        jQuery(this).find('.postbox-header').remove();
    });
});
