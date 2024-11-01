<?php

// Add UPC field for simple product


if (!in_array('woo-add-gtin/woocommerce-gtin.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('woocommerce_product_options_inventory_product_data', 'hiecor_add_upc_in_inventory_tab');
}

function hiecor_add_upc_in_inventory_tab() {
    global $post;
    $upc = get_post_meta($post->ID, 'hwp_product_gtin', true);

    woocommerce_wp_text_input(
            array(
                'id' => '_hiecor_upc',
                'name' => '_hiecor_upc',
                'label' => __('UPC', 'woocommerce'),
                'placeholder' => '',
                'value' => $upc,
                'type' => 'text',
                'custom_attributes' => array()
            )
    );
}

// Add Cost field for simple product

if (!in_array('woocommerce-cost-of-goods/woocommerce-cost-of-goods.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('woocommerce_product_options_general_product_data', 'hiecor_add_cost_field');
}

//add_action('woocommerce_product_options_general_product_data', 'hiecor_add_cost_field');

function hiecor_add_cost_field() {
    global $post;
    $cost = get_post_meta($post->ID, '_wc_cog_cost', true);

    woocommerce_wp_text_input(
            array(
                'id' => '_hiecor_cost',
                'name' => '_hiecor_cost',
                'label' => __('Cost of Good', 'woocommerce'),
                'placeholder' => '',
                'value' => $cost,
                'type' => 'text',
                'custom_attributes' => array()
            )
    );
}

// Save UPC and Cost for simple products

add_action('woocommerce_process_product_meta_simple', 'save_cost_and_upc');

function save_cost_and_upc($post_id) {

    global $wpdb;
    if (isset($_POST['_hiecor_cost']) && !empty($_POST['_hiecor_cost'])) {
        update_post_meta($post_id, '_wc_cog_cost', $_POST['_hiecor_cost']);
    }
    if (isset($_POST['_hiecor_upc']) && !empty($_POST['_hiecor_upc'])) {
        update_post_meta($post_id, 'hwp_product_gtin', $_POST['_hiecor_upc']);
    }
}

// Add Cost and UPC fields for variation products

add_action('woocommerce_product_after_variable_attributes', 'hiecor_add_upc_fields_for_variations', 10, 3);

function hiecor_add_upc_fields_for_variations($loop, $variation_data, $variation) {

    if (!in_array('woocommerce-cost-of-goods/woocommerce-cost-of-goods.php', apply_filters('active_plugins', get_option('active_plugins')))) {

        $cost = get_post_meta($variation->ID, '_wc_cog_cost', true);

        woocommerce_wp_text_input(
                array(
                    'id' => '_hiecor_cost',
                    'name' => '_hiecor_cost[]',
                    'label' => __('Cost of Good', 'woocommerce'),
                    'placeholder' => '',
                    'desc_tip' => 'true',
                    'description' => __('Product Cost', 'woocommerce'),
                    'value' => $cost,
                    'custom_attributes' => array()
                )
        );
    }

    if (!in_array('woo-add-gtin/woocommerce-gtin.php', apply_filters('active_plugins', get_option('active_plugins')))) {

        $upc = get_post_meta($variation->ID, 'hwp_var_gtin', true);

        woocommerce_wp_text_input(array(
            'id' => '_hiecor_upc',
            'name' => '_hiecor_upc[]',
            'label' => __('UPC', 'woocommerce'),
            'placeholder' => '',
            'desc_tip' => 'true',
            'description' => __('UPC code', 'woocommerce'),
            'value' => $upc,
            'custom_attributes' => array()
                )
        );
    }
}

add_action('woocommerce_save_product_variation', 'hiecor_save_upc_cost_for_variation', 10, 2);

function hiecor_save_upc_cost_for_variation($post_id) {

    global $wpdb;

    if (isset($_POST['_hiecor_cost']) && count($_POST['_hiecor_cost']) > 0) {
        if (isset($_POST['variable_post_id']) && count($_POST['variable_post_id']) > 0) {
            $_POST['variable_post_id'] = array_values($_POST['variable_post_id']);
            foreach ($_POST['variable_post_id'] as $key => $variation_id) {
                $_hiecor_cost = $_POST['_hiecor_cost'][$key];
                if ($_hiecor_cost > 0) {
                    update_post_meta($variation_id, '_wc_cog_cost', $_hiecor_cost);
                }
            }
        }
    }

    if (isset($_POST['_hiecor_upc']) && count($_POST['_hiecor_upc']) > 0) {
        if (isset($_POST['variable_post_id']) && count($_POST['variable_post_id']) > 0) {
            $_POST['variable_post_id'] = array_values($_POST['variable_post_id']);
            foreach ($_POST['variable_post_id'] as $key => $variation_id) {
                $_hiecor_upc = $_POST['_hiecor_upc'][$key];
                if (!empty($_hiecor_upc)) {
                    update_post_meta($variation_id, 'hwp_var_gtin', $_hiecor_upc);
                }
            }
        }
    }
}

?>