jQuery(document).ready(function () {

    jQuery('.variation-custom-fields-times').css('display', 'none');

    jQuery('.corcrm_custom_subscription_time_field ').css('display', 'none');
    sel_val = jQuery('#corcrm_custom_product_type').find(":selected").val();

    if (sel_val == 'subscription') {
        jQuery('.corcrm_custom_subscription_time_field ').css('display', 'block');
    }
   
    jQuery('#corcrm_custom_product_type').on('change', function () {
        sel_val = jQuery(this).find(":selected").val();
        if (sel_val == 'subscription') {
            jQuery('.corcrm_custom_subscription_time_field ').css('display', 'block');
        } else {
            jQuery('.corcrm_custom_subscription_time_field ').css('display', 'none');
        }
    });

    jQuery(document).on('change', '.crm_select_class', function () {
        sel_val = jQuery(this).find(":selected").val();
        if (sel_val == 'subscription') {
            jQuery(this).parents('.variation-custom-fields').next('.variation-custom-fields-times').css('display', 'block');

        } else {
            jQuery(this).parents('.variation-custom-fields').next('.variation-custom-fields-times').css('display', 'none');

        }
    });
    
     if (jQuery("#corcrm_custom_product_type").val() == "subscription") {
        jQuery("#corcrm_custom_product_type option[value='straight']").remove();
    }

});