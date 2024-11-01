<?php
/*
  Plugin Name: HieCOR WooCommerce Plugin
  Plugin URI: http://www.hiecor.com/
  Description: Extends WooCommerce by Adding the CorCRM Gateway.
  Version: 1.2.6
  WC tested up to: 3.8.1
  Author: HieCOR
  Author URI: http://www.hiecor.com/
 */


// If we made it this far, then include our Gateway Class
include_once( 'Corcrm_Utility.php' );
global $crmUtility;
$crmUtility = new Corcrm_Utility();

// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action('plugins_loaded', 'corcrm_payment_init', 0);

function corcrm_payment_init() {
    // If the parent WC_Payment_Gateway class doesn't exist
    // it means WooCommerce is not installed on the site
    // so do nothing
    if (!class_exists('WC_Payment_Gateway'))
        return;

    // If we made it this far, then include our Gateway Class
    include_once( 'woocommerce-corcrm-payment.php' );

    // Now that we have successfully included our class,
    // Lets add it too WooCommerce
    add_filter('woocommerce_payment_gateways', 'corcrm_payment_gateway');

    function corcrm_payment_gateway($methods) {
        $methods[] = 'Corcrm_Payment';
        return $methods;
    }

}

// Add custom action links
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'corcrm_payment_action_links');

function corcrm_payment_action_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=corcrm_payment') . '">' . __('Settings', 'corcrm_payment') . '</a>',
    );

    // Merge our new link with the default ones
    return array_merge($plugin_links, $links);
}

// run the install scripts upon plugin activation
register_activation_hook(__FILE__, 'corcrm_plugin_install_script');

function corcrm_plugin_install_script() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'posts';
    $db_name = $wpdb->dbname;
    // create the corcrm_product_id colmn in posts table
    $sql = "SELECT COLUMN_NAME 
                FROM information_schema.COLUMNS 
                WHERE 
                    TABLE_SCHEMA = '" . $db_name . "' 
                AND TABLE_NAME = '" . $table_name . "' 
                AND COLUMN_NAME = 'corcrm_product_id'";

    if ($wpdb->get_var($sql) != 'corcrm_product_id') {


        $sql = "CREATE TABLE $table_name (
                            corcrm_product_id INT(11) NULL DEFAULT 0,
                       );";

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta($sql);
    }
}

if (isset($_POST['action']) && $_POST['action'] == 'editpost' && $_POST['post_type'] == 'shop_coupon') {
    add_action('save_post', 'coupon_to_corcrm', 10, 3);
}

function coupon_to_corcrm($post_id, $post, $update) {
    global $crmUtility;
    // If this isn't a 'product' post, don't go further
    if ($post->post_type == 'shop_coupon' && $post->post_status == 'publish') {
        $crmUtility->push_coupon_to_corcrm($post_id, $post, $update, $_POST);
    }
}

if (isset($_POST['action']) && $_POST['action'] == 'editpost') {
    add_action('save_post', 'save_to_corcrm', 10, 3);
}

function save_to_corcrm($post_id, $post, $update) {
    global $crmUtility;
    // If this isn't a 'product' post, don't go further
    if ($post->post_type == 'product' && ($post->post_status == 'publish' || $post->post_status == 'private')) {
        $crmUtility->push_to_corcrm($post_id);
    }
}

// add the action 
add_action('woocommerce_add_to_cart', 'action_woocommerce_add_to_cart', 10, 3);

// define the woocommerce_add_to_cart callback 
function action_woocommerce_add_to_cart($array, $int1, $int2) {
    global $crmUtility;
    $variation_id = 0;
    $product_id = 0;
    if (isset($_POST['product_id'])) {
        $product_id = $_POST['product_id'];
    }

    if (isset($_POST['add-to-cart'])) {
        $product_id = $_POST['add-to-cart'];
    }

    if (isset($_POST['variation_id']) && $_POST['variation_id'] > 0) {
        $variation_id = $_POST['variation_id'];
    }
    
    if ($variation_id > 0 || $product_id > 0) {
        $crmUtility->check_corcrm_existance($product_id, $variation_id);
    }
}

add_action('admin_enqueue_scripts', 'product_type_enqueue');

function product_type_enqueue($hook) {
    wp_enqueue_script('my_custom_script', plugin_dir_url(__FILE__) . 'js/type.js');
}

/*
 * Added By Tarun on 21-01-2016
 * This will display CorCRM product id in woocommerce product general data tab
 */

add_action('woocommerce_product_options_general_product_data', 'corcrm_add_custom_general_fields');

function corcrm_add_custom_general_fields() {
    global $post;
    $corcrm_product_id = (!empty($post->corcrm_product_id)) ? $post->corcrm_product_id : '';

    woocommerce_wp_text_input(
            array(
                'id' => 'corcrm_prod_id',
                'name' => 'corcrm_prod[]',
                'label' => __('CorCRM Product Id', 'woocommerce'),
                'placeholder' => '',
                'value' => $corcrm_product_id,
                'type' => 'text',
                'custom_attributes' => array()
            )
    );

    // Product type( straight/subscription ) select  box for simple products
    $type_field = array(
        'id' => 'corcrm_custom_product_type',
        'label' => __('CorCRM Product type', 'textdomain'),
        'options' => array(
            'straight' => __('Straight', 'straight'),
            'subscription' => __('Subscription', 'subscription')
        )
    );
    woocommerce_wp_select($type_field);

    // Subscription Duration Select field for simple products
    $subscription_duration = array(
        'id' => 'corcrm_custom_subscription_time',
        'label' => __('CRM Subscription Time', 'textdomain'),
        'options' => array(
            '' => __('Select', ''),
            '1' => __('1 Day', '1'),
            '7' => __('1 Week', '7'),
            '14' => __('2 Week', '14'),
            '1m' => __('1 Month', '1m'),
            '6m' => __('6 Month', '6m'),
            '1y' => __('1 Year', '1y')
        )
    );
    woocommerce_wp_select($subscription_duration);
}

// Saving prodct type and subscription duration in meta
add_action('woocommerce_process_product_meta', 'save_custom_field');

function save_custom_field($post_id) {

    $corcrm_custom_product_type = isset($_POST['corcrm_custom_product_type']) ? $_POST['corcrm_custom_product_type'] : '';
    $corcrm_custom_subscription_time = isset($_POST['corcrm_custom_subscription_time']) ? $_POST['corcrm_custom_subscription_time'] : '';
    $product = wc_get_product($post_id);
    $product->update_meta_data('corcrm_custom_product_type', $corcrm_custom_product_type);
    $product->update_meta_data('corcrm_custom_subscription_time', $corcrm_custom_subscription_time);
    $product->save();
}

// Show product type on frontend for simple product
add_action('woocommerce_product_meta_end', 'showing_custom_fields_fronted');

function showing_custom_fields_fronted() {
    global $post;

    $subsctiption_time = get_post_meta($post->ID, 'corcrm_custom_subscription_time', true);
    $corcrm_custom_product_type = get_post_meta($post->ID, 'corcrm_custom_product_type', true);
    if (trim($corcrm_custom_product_type) == 'subscription') {
        echo '<div class="crm_product_type" style="text-transform:uppercase;"> Product Type : ' . $corcrm_custom_product_type . '</div>';
        switch ($subsctiption_time) {
            case '1':
                $time = '1 Day';
                break;
            case '7':
                $time = '1 Week';
                break;
            case '14':
                $time = '2 Week';
                break;
            case '1m':
                $time = '1 Month';
                break;
            case '6m':
                $time = '6 Month';
                break;
            case '1y':
                $time = '1 Year';
                break;
            default:
                $time = '';
                break;
        }
        if (!empty($subsctiption_time)) {
            echo '<div class="subsctiption_time"> Subscription Time : ' . $time . '</div>';
        }
    } else if (!empty($corcrm_custom_product_type)) {
        echo '<div class="crm_product_type"> Product Type : ' . ucfirst($corcrm_custom_product_type) . '</div>';
    }
}

// Show product type on frontend for variation product
add_filter('woocommerce_available_variation', 'load_variation_settings_fields');

function load_variation_settings_fields($variations) {

    // duplicate the line for each field
    $variations['corcrm_custom_product_type'] = get_post_meta($variations['variation_id'], '_corcrm_custom_product_type', true);
    $variations['corcrm_custom_subscription_time'] = get_post_meta($variations['variation_id'], '_corcrm_custom_subscription_time', true);
    return $variations;
}

// // Show subscription duration for variation product 
add_filter('woocommerce_available_variation', function ($value, $object = null, $variation = null) {
            if (!empty($value['corcrm_custom_product_type'])) {
                $value['price_html'] .= '<span class="crm_product_type"> Product Type : ' . ucfirst($value['corcrm_custom_product_type']) . '</span>';
            }
            if (trim($value['corcrm_custom_product_type']) == 'subscription') {
                switch ($value['corcrm_custom_subscription_time']) {
                    case '1':
                        $time = '1 Day';
                        break;
                    case '7':
                        $time = '1 Week';
                        break;
                    case '14':
                        $time = '2 Week';
                        break;
                    case '1m':
                        $time = '1 Month';
                        break;
                    case '6m':
                        $time = '6 Month';
                        break;
                    case '1y':
                        $time = '1 Year';
                        break;
                    default:
                        $time = '';
                        break;
                }
                if (!empty($time)) {
                    $value['price_html'] .= '<br><span class="crm_product_subscrption"> Subscription Time : ' . $time . '</span>';
                }
            }
            return $value;
        }, 10, 3);



// Add Variation Settings
add_action('woocommerce_product_after_variable_attributes', 'corcrm_variation_settings_fields', 10, 3);
// Save Variation Settings
add_action('woocommerce_save_product_variation', 'corcrm_save_variation_settings_fields', 10, 2);

function corcrm_variation_settings_fields($loop, $variation_data, $variation) {

    woocommerce_wp_text_input(
            array(
                'id' => 'corcrm_prod',
                'name' => 'corcrm_prod[]',
                'label' => __('Corcrm Product Id', 'woocommerce'),
                'placeholder' => 'corcrm product id',
                'desc_tip' => 'true',
                'description' => __('This is corcrm product id.', 'woocommerce'),
                'value' => $variation->corcrm_product_id,
                'custom_attributes' => array()
            )
    );

    // For product type straight or subsscription

    $options = array(
        'straight' => __('Straight', 'straight'),
        'subscription' => __('Subscription', 'subscription')
    );
    $type = get_post_meta($variation->ID, '_corcrm_custom_product_type', true);
    if ($type == "subscription") {
        $options = array('subscription' => __('Subscription', 'subscription'));
    }
    echo '<div class="variation-custom-fields">';
    // Product Type select field for variation product

    woocommerce_wp_select(array(
        'id' => 'corcrm_custom_product_type_' . $variation->ID,
        'class' => 'crm_select_class',
        'label' => __('CorCRM Product type', 'woocommerce'),
        'value' => get_post_meta($variation->ID, '_corcrm_custom_product_type', true),
        'options' => $options,
        'custom_attributes' => array(),
    ));
    echo '</div>';

    if (get_post_meta($variation->ID, '_corcrm_custom_product_type', true) == 'straight') {
        $display = 'style="display:none;"';
    } elseif (get_post_meta($variation->ID, '_corcrm_custom_product_type', true) == 'subscription') {
        $display = 'style="display:block;"';
    } else {
        $display = 'style="display:none;"';
    }

    echo '<div class="variation-custom-fields-times" ' . $display . '>';

    // Subscription Duration select field for variation product
    woocommerce_wp_select(array(
        'id' => 'corcrm_custom_subscription_time_' . $variation->ID,
        'label' => __('CRM Subscription Time', 'woocommerce'),
        'value' => get_post_meta($variation->ID, '_corcrm_custom_subscription_time', true),
        'options' => array(
            '' => __('Select', ''),
            '1' => __('1 Day', '1'),
            '7' => __('1 Week', '7'),
            '14' => __('2 Week', '14'),
            '1m' => __('1 Month', '1m'),
            '6m' => __('6 Month', '6m'),
            '1y' => __('1 Year', '1y')
        )
    ));
    echo '</div>';
}

/**
 * Save new fields for variations
 *
 */
function corcrm_save_variation_settings_fields($post_id) {

    global $wpdb;

    if (isset($_POST['corcrm_prod']) && count($_POST['corcrm_prod']) > 0) {
        if (isset($_POST['variable_post_id']) && count($_POST['variable_post_id']) > 0) {
            $_POST['variable_post_id'] = array_values($_POST['variable_post_id']);
            foreach ($_POST['variable_post_id'] as $key => $value) {
                $corId = $_POST['corcrm_prod'][$key];
                $wooVarId = $value;
                if ($corId > 0) {

                    $upd = $wpdb->update($wpdb->prefix . 'posts', array('corcrm_product_id' => $corId), array('id' => $wooVarId));
                }
            }
        }
    }

    // Save variation settings for product type straight or subscription
    // Save product type - straight/subscription
    $select = $_POST["corcrm_custom_product_type_$post_id"];
    if (!empty($select)) {
        update_post_meta($post_id, '_corcrm_custom_product_type', esc_attr($select));
    }

    // Save subsscription duration
    $select = $_POST["corcrm_custom_subscription_time_$post_id"];
    if (!empty($select)) {
        update_post_meta($post_id, '_corcrm_custom_subscription_time', esc_attr($select));
    }
}

add_action('woocommerce_process_product_meta_simple', 'woo_add_custom_general_fields_save');

function woo_add_custom_general_fields_save($post_id) {

    global $wpdb;
    if (isset($_POST['corcrm_prod']) && is_array($_POST['corcrm_prod'])) {
        foreach ($_POST['corcrm_prod'] as $key => $corid) {
            if ($corid > 0) {
                $upd = $wpdb->update($wpdb->prefix . 'posts', array('corcrm_product_id' => $corid), array('id' => $post_id), $format = null, $where_format = null);
            }
        }
    }
}

// 01-APR-2016 By Tarun
add_filter('woocommerce_credit_card_form_fields', 'corcrm_custom_wc_checkout_fields');

function corcrm_custom_wc_checkout_fields($fields) {
    $fields['card-expiry-field'] = '<p class="form-row form-row-first">
				<label for="corcrm_payment-card-expiry">' . __('Expiry (MM/YYYY)', 'woocommerce') . ' <span class="required">*</span></label>
				<input id="corcrm_payment-card-expiry" class="input-text wc-credit-card-form-card-expiry" type="text" autocomplete="off" placeholder="' . esc_attr__('MM / YYYY', 'woocommerce') . '" name="corcrm_payment-card-expiry" />
			</p>';
    return $fields;
}

/*
 * To set the order status to complete after successfull payment
 */
$orderStaus = $crmUtility->get_settings('order_status');
if ($orderStaus == "complete") {
    add_filter('woocommerce_payment_complete_order_status', 'corcrm_change_status_function');
}

function corcrm_change_status_function() {
    return 'completed';
}

// define the woocommerce_api_create_product callback 
function corcrm_woocommerce_api_create_product($id, $data) {

    global $wpdb;

    $corcrm_prod_id = $data['corcrm_product_id'];

    $upd = $wpdb->update($wpdb->prefix . 'posts', array('corcrm_product_id' => $corcrm_prod_id), array('id' => $id), $format = null, $where_format = null);
    if (!$upd) {
        $crmUtility::logger("Unable to update CorCRM productID Error:" . $wpdb->last_error);
    }
}

// add the action 
add_action('woocommerce_api_create_product', 'corcrm_woocommerce_api_create_product', 10, 2);

// Allow wp-admin to be loaded in Iframe by removing the option header
remove_action('admin_init', 'send_frame_options_header', 10);
remove_action('login_init', 'send_frame_options_header', 10);

function corcrm_auto_login() {

    if (isset($_GET['t'])) {

        preg_match_all('/(\w+)=([^&]+)/', $_SERVER["QUERY_STRING"], $pairs);
        $_GET = array_combine($pairs[1], $pairs[2]);

        //Get and set any values already sent
        $user_extra = ( isset($_POST['user_extra']) ) ? $_POST['user_extra'] : '';

        $text = base64_decode(urldecode($_GET['t']));
        //$t=mcrypt_decrypt(MCRYPT_RIJNDAEL_128, 'p2w-wordpress', $text, MCRYPT_MODE_ECB, 'keee');
        $credentials = explode('@p2w@', $text);
        $username = trim($credentials[0]);
        $password = trim($credentials[1]);
        ?>

        <script type="text/javascript">
            window.onload = function() {
                document.getElementById("user_login").value = "<?php echo $username ?>";
                document.getElementById("user_pass").value = "<?php echo $password ?>";
                document.getElementById("loginform").submit();
            }

        </script>
        <?php
    }
}

add_action('login_form', 'corcrm_auto_login');

//Insert corcrm visit plugin code to site footer

add_action('wp_footer', 'corcrm_insert_visit_code');

function corcrm_insert_visit_code() {
    $get_settings = get_option('woocommerce_corcrm_payment_settings', null);
    $get_url = parse_url($get_settings['wsdl_url']);
    if ($get_settings['visit_plugin'] == "yes") {
        ?>
        <script type="text/javascript">
            var custom1 = "", custom2 = "", custom3 = "", _cords = [], ua_agent = "CORUA-87";
            _cords.push(contentType = document.contentType);
            _cords.push(Date.now());
            _cords.push(window.location.pathname);
            _cords.push(window.location.protocol + "//" + window.location.hostname + "" + window.location.pathname);
            _cords.push(custom1);
            _cords.push(custom2);
            _cords.push(custom3);
            _cords.push(ua_agent);
            _cords.push("<?php echo $get_url['host']; ?>");

            (function() {
                var corcrm = document.createElement("script");
                corcrm.type = "text/javascript";
                corcrm.async = true;
                var file_loc = window.location.protocol;
                if (file_loc == "file:") {
                    file_loc = "https:";
                }
                corcrm.src = file_loc + "//<?php echo $get_url['host']; ?>/includes/plugins/visit/corcrm_custom.js";
                var s = document.getElementsByTagName("script")[0];
                s.parentNode.insertBefore(corcrm, s);
            })();

        </script>
        <?php
    }
}

//Add track code into session
add_action('wp_head', 'corcrm_save_track_code');

add_action('init', 'corcrm_track_session');

function corcrm_track_session(){
  if( !session_id() )
  {
    session_start();
  }
}

function corcrm_save_track_code() {
    if (!empty($_GET['track_code'])) {
        $find_track_url = $_SERVER['HTTP_HOST'] . $_SERVER['REDIRECT_URL'];
        $_SESSION['corcrm_track_code']= esc_attr($_GET['track_code']);
        $_SESSION['corcrm_page_url']= $find_track_url;
    } 
    
    add_filter('woocommerce_thankyou_order_received_text', 'corcrm_woocommerce_thankyou_order_received_text', 10, 2);
}


function corcrm_woocommerce_thankyou_order_received_text($var, $order) {
    if(!empty($_SESSION['corcrm_track_code'])){
        // make filter magic happen here... 
        $get_settings = get_option('woocommerce_corcrm_payment_settings', null);
        $get_url = parse_url($get_settings['wsdl_url']);
        $corcrm_track_code     = $_SESSION['corcrm_track_code'];
        $corcrm_page_url       = $_SESSION['corcrm_page_url'];
        $corcrm_order_id      = WC()->session->get( 'corcrm_order_id' );
        $var .= '<img src="' . esc_url($get_url['scheme'] . '://' . $get_url['host'] . '/pixel/' . $corcrm_track_code . '/' . $corcrm_page_url . '/?order_id=' .$corcrm_order_id) . '" style="display:none;" />';
        unset($_SESSION['corcrm_track_code']);
        unset($_SESSION['corcrm_page_url']);
        unset(WC()->session->corcrm_order_id);
        return $var;
    }else{
        return $var;
    }
}

function corcrm_hide_shipping($rates) {
    $free = array();
    foreach ($rates as $rate_id => $rate) {
        if ('free_shipping' === $rate->method_id) {
            $free[$rate_id] = $rate;
            break;
        }
    }
    return !empty($free) ? $free : $rates;
}

add_filter('woocommerce_package_rates', 'corcrm_hide_shipping', 100);


/*
 * Add a corcrm_product_id field to the Product API response.
 */

function prefix_wc_rest_prepare_order_object($response, $object, $request) {
    // Get the value

    global $wpdb;
    $tbl_name = $wpdb->prefix . 'posts';
    $id = $object->get_id();

    if ($object->is_type('variable') && $object->has_child()) {
        foreach ($response->data['variations'] as $key => $value) {
            $prodId = $wpdb->get_var("SELECT `corcrm_product_id` FROM $tbl_name WHERE ID = $value");
            $response->data['variation_to_corcrm_id'][$value] = $prodId;
        }
    } else {
        $prodId = $wpdb->get_var("SELECT `corcrm_product_id` FROM $tbl_name WHERE ID=$id");
        $response->data['corcrm_product_id'] = $prodId;
    }
    return $response;
}

add_filter('woocommerce_rest_prepare_product_object', 'prefix_wc_rest_prepare_order_object', 10, 3);

// UPC and Cost Field
include_once( 'admin/custom-fields.php' );

//Bulk import functionality
include_once('admin/bulkimport.php');
