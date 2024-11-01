<?php

/* CorCRM Payment Gateway Class */
include_once( 'Corcrm_Utility.php' );
global $crmUtility;
$crmUtility = new Corcrm_Utility();

class Corcrm_Payment extends WC_Payment_Gateway {

    // Setup our Gateway's id, description and other values
    function __construct() {

        // The global ID for this Payment method
        $this->id = "corcrm_payment";

        // The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
        $this->method_title = __("Credit Card", 'corcrm-secure-payments');

        // The description for this Payment Gateway, shown on the actual Payment options page on the backend
        $this->method_description = __("HieCOR Secure Payment Gateway Plug-in for WooCommerce", 'corcrm-secure-payments');

        // The title to be used for the vertical tabs that can be ordered top to bottom
        $this->title = __("Credit Card", 'corcrm-secure-payments');

        // If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
        $this->icon = null;

        // Bool. Can be set to true if you want payment fields to show on the checkout 
        // if doing a direct integration, which we are doing in this case
        $this->has_fields = true;

        // Supports the default credit card form
        $this->supports = array('default_credit_card_form');

        // This basically defines your settings which are then loaded with init_settings()
        $this->init_form_fields();

        // After init_settings() is called, you can get the settings and load them into variables, e.g:
        // $this->title = $this->get_option( 'title' );
        $this->init_settings();

        // Turn these settings into variables we can use
        foreach ($this->settings as $setting_key => $value) {
            $this->$setting_key = $value;
        }

        // Lets check for SSL
        add_action('admin_notices', array($this, 'do_ssl_check'));

        // Save settings
        if (is_admin()) {
            // Versions over 2.0
            // Save our administration options. Since we are not going to be doing anything special
            // we have not defined 'process_admin_options' in this class so the method in the parent
            // class will be used instead
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        }
    }

// End __construct()
    // Build the administration fields for this specific Gateway
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable / Disable', 'corcrm-secure-payments'),
                'label' => __('Enable this payment gateway', 'corcrm-secure-payments'),
                'type' => 'checkbox',
                'default' => 'no',
            ),
            'title' => array(
                'title' => __('Title', 'corcrm-secure-payments'),
                'type' => 'text',
                'desc_tip' => __('Payment title the customer will see during the checkout process.', 'corcrm-secure-payments'),
                'default' => __('Credit Card', 'corcrm-secure-payments'),
            ),
            'description' => array(
                'title' => __('Description', 'corcrm-secure-payments'),
                'type' => 'textarea',
                'desc_tip' => __('Payment description the customer will see during the checkout process.', 'corcrm-secure-payments'),
                'default' => __('Pay securely using your credit card.', 'corcrm-secure-payments'),
                'css' => 'max-width:350px;'
            ),
            'wsdl_url' => array(
                'title' => __('WSDL Url', 'corcrm-secure-payments'),
                'type' => 'text',
                'desc_tip' => __('This is the CRM API WSDL url provided by HieCOR.', 'corcrm-secure-payments'),
            ),
            'auth_key' => array(
                'title' => __('Authorization Key', 'corcrm-secure-payments'),
                'type' => 'text',
                'desc_tip' => __('This is the Authorization Key provided by HieCOR.', 'corcrm-secure-payments'),
            ),
            'user_name' => array(
                'title' => __('User Name', 'corcrm-secure-payments'),
                'type' => 'text',
                'desc_tip' => __('This is the API User Name provided by HieCOR.', 'corcrm-secure-payments'),
                'default' => '',
            ),
            array(
                'title' => __('General options', 'corcrm-secure-payments'),
                'type' => 'title',
                'desc' => '',
                'id' => 'cor_general_options'
            ),
            'order_status' => array(
                'title' => __('Order Status', 'corcrm-secure-payments'),
                'type' => 'select',
                'default' => 'complete',
                'options' => array(
                    'complete' => __('Complete', 'woocommerce'),
                    'processing' => __('Processing', 'woocommerce'),
                ),
                'class' => 'wc-enhanced-select',
                'desc_tip' => __('Set default order status', 'corcrm-secure-payments'),
            ),
            'visit_plugin' => array(
                'title' => __('Visit', 'corcrm-secure-payments'),
                'label' => __('Include visit tracking code.', 'corcrm-secure-payments'),
                'type' => 'checkbox',
                'default' => 'no',
            )
        );
    }

    // Submit payment and handle response
    public function process_payment($order_id) {
        global $woocommerce;
        global $crmUtility;
        global $wpdb;
        $delivery_date = '';
        $tbl_name = $wpdb->prefix . 'posts';

        try {
            // Get this Order's information so that we know
            // who to charge and how much
            $customer_order = new WC_Order($order_id);

            $shipping_method = @array_shift($customer_order->get_shipping_methods());
            $shipping_method_name = $shipping_method['name'];
            $customer_note = $customer_order->customer_note;
            $delivery_data_meta = $customer_order->get_meta_data();
            if (!empty($delivery_data_meta) && is_array($delivery_data_meta)) {
                foreach ($delivery_data_meta as $key => $value) {
                    if (strpos($value->key, 'Date')) {
                        $delivery_date = $value->value;
                    }
                }
            }

            // This is where the fun stuff begins
            $payload = array(
                // Order total
                "order_total" => $customer_order->order_total,
                // Credit Card Information
                "card_num" => str_replace(array(' ', '-'), '', sanitize_text_field($_POST['corcrm_payment-card-number'])),
                "card_code" => ( isset($_POST['corcrm_payment-card-cvc']) ) ? sanitize_text_field($_POST['corcrm_payment-card-cvc']) : '',
                "exp_date" => sanitize_text_field($_POST['corcrm_payment-card-expiry']),
                "invoice_num" => str_replace("#", "", $customer_order->get_order_number()),
                // Billing Information
                "first_name" => $customer_order->billing_first_name,
                "last_name" => $customer_order->billing_last_name,
                "bill_address_1" => $customer_order->billing_address_1,
                "bill_address_2" => $customer_order->billing_address_2,
                "city" => $customer_order->billing_city,
                "state" => $customer_order->billing_state,
                "zip" => $customer_order->billing_postcode,
                "country" => $customer_order->billing_country,
                "phone" => $customer_order->billing_phone,
                "email" => $customer_order->billing_email,
                "bill_company" => $customer_order->billing_company,
                // Shipping Information
                "ship_first_name" => $customer_order->shipping_first_name,
                "ship_last_name" => $customer_order->shipping_last_name,
                "ship_company" => $customer_order->shipping_company,
                "ship_address_1" => $customer_order->shipping_address_1,
                "ship_address_2" => $customer_order->shipping_address_2,
                "ship_city" => $customer_order->shipping_city,
                "ship_country" => $customer_order->shipping_country,
                "ship_state" => $customer_order->shipping_state,
                "ship_zip" => $customer_order->shipping_postcode,
                // Some Customer Information
                "cust_id" => $customer_order->user_id,
                "customer_ip" => $_SERVER['REMOTE_ADDR'],
            );
            $items = $customer_order->get_items();
            $products = array();
            $attributes = array();
            $price = '';
            $prodId = '';
			
            $extra_data=array();
            $tax_detail=array();
            foreach( $customer_order->get_items( 'tax' ) as $item_id => $item_tax ){

              $tax_data = $item_tax->get_data();
              $rate_code = $item_tax->get_rate_id();

              $tax_detail = $wpdb->get_results("SELECT tax_rate_country, tax_rate_state , tax_rate ,
               tax_rate_name ,tax_rate_class FROM ".$wpdb->prefix.woocommerce_tax_rates." WHERE tax_rate_id = $rate_code");

            }
            
            if(isset($tax_detail[0]->tax_rate_state) && isset($tax_detail[0]->tax_rate)){
                $extra_data['tax_details'] = array(
                    'region_code'=> $tax_detail[0]->tax_rate_state,
                    'tax_value'=>$tax_detail[0]->tax_rate
                );
            }
         
          
            foreach ($items as $key => $item) {

                $p = $crmUtility->getProduct($item['product_id']);
                $prodId = $p->post->corcrm_product_id;

                // get custom options of product, will set in product comment.
                $item_meta_data = $item->get_meta_data();

                if (!empty($item_meta_data)) {
					
                    foreach ($item_meta_data as $key => $meta) {

                        if (!in_array($meta->key, array('_wc_cog_item_cost', '_wc_cog_item_total_cost'))) {

                            $fieldName = $meta->key;
                            $metaValue = $meta->value;

                            if (strpos($meta->key, 'pa_') >= 0) {
                                $fieldName = str_replace('pa_', '', $meta->key);
                            }

                            if (strpos($fieldName, '_')) {
                                $fieldName = str_replace("_", " ", $fieldName);
                            }

                            if (strpos($metaValue, ',')) {
                                $metaValue = str_replace(",", " ", $metaValue);
                            }

                            $fieldKey = ucwords($fieldName);

                            $attributes[] = $fieldKey . "|" . $metaValue;
                        }
                    }
                }

                if (isset($item['variation_id']) && $item['variation_id'] > 0) {
                    $attr = $p->get_variation_attributes();

                    $varId = $item['variation_id'];
                    $prodId = $wpdb->get_var("SELECT `corcrm_product_id` FROM $tbl_name WHERE ID=$varId");
                }

                $price = $item['line_subtotal'] / $item['qty'];

                if (empty($prodId)) {

                    $id = $item['product_id'];
                    $_pf = new WC_Product_Factory();
                    $product = $_pf->get_product($id);
                    if ($product->product_type == "variable") {
                        $id = $item['variation_id'];
                        $crmUtility->push_to_corcrm($product->post->ID);
                        $prodId = $wpdb->get_var("SELECT `corcrm_product_id` FROM $tbl_name WHERE ID=$id");
                    } else {
                        $crmUtility->push_to_corcrm($product->post->ID);
                        $prodId = $wpdb->get_var("SELECT `corcrm_product_id` FROM $tbl_name WHERE ID=$id");
                    }
                }

                $pro = array(
                    'product_id' => $prodId,
                    'qty' => $item['qty'],
                    'man_price' => $price,
                    'is_dynamic' => ''
                );

                if (!empty($attributes)) {

                    $attributes = implode(",", $attributes);
                    $pro['attributes'] = $attributes;
                }
                $attributes = array();

                $pro['required_modifiers'] = !empty($item['Sandwich options']) ? $item['Sandwich options'] : '';
                $pro['optional_modifiers'] = !empty($item['Custom Made For You']) ? $item['Custom Made For You'] : '';
                $pro['product_comment'] = '';
                /* for modifers ends here */
                $products[] = $pro;
            }

            $client = new SoapClient($this->wsdl_url, array('trace' => 1));
            $accountInfo = array('user_name' => $this->user_name, 'auth_key' => htmlspecialchars_decode($this->auth_key), 'digest' => "", "nonce" => "");

            $userId = 0;
            $data = array('accountInfo' => $accountInfo, 'userID' => $userId, 'username' => '', 'email' => $payload['email']);

            try {
                $api_response = $client->__soapCall('get_user', $data);
            } catch (Exception $ex) {
                
                throw new SoapFault('SoapClient', $client->__getLastResponse());
            }

            $response['response'] = (get_object_vars($api_response['response']));
            if ($response['response']['status'] == "failure") {
                $data2 = array(
                    'accountInfo' => $accountInfo,
                    'username' => '',
                    'password' => '',
                    'first_name' => $payload['first_name'],
                    'last_name' => $payload['last_name'],
                    'company' => '',
                    'email' => $payload['email'],
                    'admin_notes' => '',
                    'overwrite' => 0
                );
                try {
                    $response = $client->__soapCall('create_user', $data2);
                    $response['response'] = (get_object_vars($response['response']));
                    if ($response['response']['status'] == "failure") {
                        self::logger('CorCRM Create User ERROR: ' . print_r($response, true));
                        return array(
                            'result' => 'failure',
                        );
                    } else {
                        $userId = $response['userID'];
                    }
                } catch (Exception $ex) {
                    throw new SoapFault('SoapClient', $client->__getLastResponse());
                }
            } else {
                $userId = $api_response['userID'];
            }
            $process_fee = 0;
            if ($woocommerce->session->fee_total > 0) {
                $process_fee = $woocommerce->session->fee_total;
            }
            $shipping = ($process_fee + $customer_order->get_total_shipping());

            $tax = $customer_order->get_total_tax();
            $coupon = '';
            //get coupons used. returns the array of coupons. but our crm api supports just coupon. so pass first one

            if (!empty($customer_order->get_used_coupons())) {
                $coupon = $customer_order->get_used_coupons();
                $coupon = $coupon[0];
            }

            $cardExp = explode('/', $payload['exp_date']);
            if (strlen(trim($cardExp[1])) == 2) {
                $cardExp[1] = '20' . trim($cardExp[1]);
            }

            $data = array('accountInfo' => $accountInfo,
                'userID' => $userId,
                'bill_profile_id' => '',
                'bill_first_name' => $payload['first_name'],
                'bill_last_name' => $payload['last_name'],
                'bill_address_1' => $payload['bill_address_1'],
                'bill_address_2' => $payload['bill_address_2'],
                'bill_city' => $payload['city'],
                'bill_region' => $payload['state'],
                'bill_country' => $payload['country'],
                'bill_postal_code' => $payload['zip'],
                'bill_email' => $payload['email'],
                'bill_phone' => $payload['phone'],
                'ship_first_name' => $payload['ship_first_name'],
                'ship_last_name' => $payload['ship_last_name'],
                'ship_address_1' => $payload['ship_address_1'],
                'ship_address_2' => $payload['ship_address_2'],
                'ship_city' => $payload['ship_city'],
                'ship_region' => $payload['ship_state'],
                'ship_country' => $payload['ship_country'],
                'ship_postal_code' => $payload['ship_zip'],
                'products' => $products,
                'coupon' => $coupon,
                'shipping' => $shipping,
                'tax' => $tax,
                'comments' => $customer_note,
                'cc_account' => $payload['card_num'],
                'cc_exp_mo' => trim($cardExp[0]),
                'cc_exp_yr' => trim($cardExp[1]),
                'cc_cvv' => $payload['card_code'],
                'merchant_id' => '',
                'campaign_code' => '',
                'sub_id' => '',
                'source' => 'WooCommerce',
                'shipping_method' => $shipping_method_name,
                'crm_partner_order_id' => $order_id,
                'discount' => '',
                'delivery_date' => $delivery_date,
                'company'=>'',
            );
            if(!empty($extra_data)){
                $data['extra_data']=serialize($extra_data);
            }
            
            try {
                $api_response = $client->__soapCall('create_order', $data);
            } catch (Exception $ex) {
                WC()->session->__unset( 'order_awaiting_payment' );
                throw new SoapFault('SoapClient', $client->__getLastResponse());
            }


            $response['response'] = (get_object_vars($api_response['response']));
            
            if ($response['response']['status'] == "success") {
                // Payment has been successful
                $customer_order->add_order_note(__('CorCRM payment completed.', 'corcrm-secure-payments'));

                // Mark order as Paid
                $customer_order->payment_complete();

                // Empty the cart (Very important step)
                $woocommerce->cart->empty_cart();
                
                // Added corcrm id into the session 
                WC()->session->set('corcrm_order_id', $api_response['order_id']);

                // Redirect to thank you page
                return array(
                    'result' => 'success',
                    'redirect' => $this->get_return_url($customer_order),
                );
            } else {
                // Transaction was not succesful - Resetting Order Id
                WC()->session->__unset( 'order_awaiting_payment' );
                // Add notice to the cart
                wc_add_notice($response['response']['description'], 'error');
                // Add note to the order for your reference
                $customer_order->add_order_note('Error: ' . $response['response']['description']);

                self::logger('CorCRM Payment ERROR: ' . $response['response']['description']);
            }
        } catch (SoapFault $sp) {
            
            wc_add_notice("Due to some technical issue we are unable to process your Order #{$order_id} . Please contact site admin.", 'error');
            $customer_order->add_order_note("Order #{$order_id} failed to process. Due to Hiecor API failure.");
            Corcrm_Utility::logger($sp->getMessage());
            
        } catch (Exception $ex) {
            
            wc_add_notice("Due to some technical issue we are unable to process your Order #{$order_id} . Please contact site admin.", 'error');
            $customer_order->add_order_note("Order #{$order_id} failed to process. Due to Hiecor API failure.");
            Corcrm_Utility::logger($ex->getMessage());
            
        }
    }

    // Validate fields
    public function validate_fields() {
        $msg='';
        $error=0;
        if( empty( $_POST[ 'corcrm_payment-card-number' ]) ) {
            $msg.=  'Credit card';
            $error=1;
        }
        if( empty( $_POST[ 'corcrm_payment-card-expiry' ]) ) {
            $msg.=  ',Expiry month, Expiry year';
            $error=1;
        }
        if( empty( $_POST[ 'corcrm_payment-card-cvc' ]) ) {
            $msg.=  ',CVC';
            $error=1;
        }
        if($error){
           $msg.=' fields are required.';
           wc_add_notice(  trim($msg,','), 'error' );
                return false;
        }
        return true;
    }

    // Check if we are forcing SSL on checkout pages
    // Custom function not required by the Gateway
    public function do_ssl_check() {
        if ($this->enabled == "yes") {
            if (get_option('woocommerce_force_ssl_checkout') == "no") {
                echo "<div class=\"error\"><p>" . sprintf(__("<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>"), $this->method_title, admin_url('admin.php?page=wc-settings&tab=checkout')) . "</p></div>";
            }
        }
    }

    public static function logger($msg) {
        $log = new WC_Logger();
        $log->add('api', $msg);
    }

}

// End of Corcrm_Payment