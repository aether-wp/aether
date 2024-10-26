jQuery(document).ready(function($) {
    $('.enable-checkbox, .disable-checkbox').on('change', function() {
        var plugin = $(this).data('plugin');
        var env = $(this).data('env');
        var isEnable = $(this).hasClass('enable-checkbox');
        
        // Find the corresponding checkbox
        var otherCheckbox = isEnable ? 
            $('.disable-checkbox[data-plugin="' + plugin + '"][data-env="' + env + '"]') :
            $('.enable-checkbox[data-plugin="' + plugin + '"][data-env="' + env + '"]');
        
        // If this checkbox is checked, uncheck the other one
        if ($(this).is(':checked')) {
            otherCheckbox.prop('checked', false);
        }
    });
});