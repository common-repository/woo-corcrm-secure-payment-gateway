<?php

class Corcrm_Utility {

    /**
     * The plugin ID. Used for option names.
     * @var string
     */
    public $plugin_id = 'woocommerce_';

    /**
     * Method ID.
     * @var string
     */
    public $id = 'corcrm_payment';

    /**
     * Setting values.
     * @var array
     */
    public $settings = array();

    public function __construct() {

        $this->init_settings();
        // Turn these settings into variables we can use
        foreach ($this->settings as $setting_key => $value) {
            $this->$setting_key = $value;
        }
    }

    public function init_settings() {

        // Load form_field settings
        $this->settings = get_option($this->plugin_id . $this->id . '_settings', null);

        if (!$this->settings || !is_array($this->settings)) {

            $this->settings = array();

            // If there are no settings defined, load defaults
            if ($form_fields = $this->get_form_fields()) {

                foreach ($form_fields as $k => $v) {
                    $this->settings[$k] = isset($v['default']) ? $v['default'] : '';
                }
            }
        }

        if ($this->settings && is_array($this->settings)) {
            $this->settings = array_map(array($this, 'format_settings'), $this->settings);
            $this->enabled = isset($this->settings['enabled']) && $this->settings['enabled'] == 'yes' ? 'yes' : 'no';
        }
    }

    public function get_settings($key) {
        if ($this->settings[$key]) {
            return $this->settings[$key];
        }
    }

    public function get_form_fields() {
        // Build the administration fields for this specific Gateway
        return array(
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

    /**
     * Decode values for settings.
     *
     * @param mixed $value
     * @return array
     */
    public function format_settings($value) {
        return is_array($value) ? $value : $value;
    }

    public static function logger($msg) {
        $log = new WC_Logger();
        $log->add('api', $msg);
    }

    public function getProduct($post_id) {
        $_pf = new WC_Product_Factory();
        return $_pf->get_product($post_id);
    }

    public function get_productImage($post_id) {
        $product = wc_get_product($post_id);
        $img = $product->get_image('shop_thumbnail', '', false); // accepts 2 arguments ( size, attr 
        return $img;
    }

    public static function cleanDescription($description) {
        $str1 = strip_tags($description);
        $str = html_entity_decode($description, ENT_QUOTES, 'UTF-8');
        $xml = new DOMDocument();
        $internalErrors = libxml_use_internal_errors(true);
        $xml->loadHTML($str);
        libxml_use_internal_errors($internalErrors);
        $xpath = new DOMXpath($xml);
        $elements = $xpath->query("//body//text()[not(ancestor::script)][not(ancestor::style)]");

        $text = "";
        for ($i = 0; $i < $elements->length; $i++) {
            $text .= trim(strip_tags($elements->item($i)->nodeValue));
            $text = str_replace(chr(194), "", trim($text));
        }
        $description = strip_tags($text);
        $description = preg_replace('/[[:^print:]]/', '', $description);
        return str_replace(array("&nbsp;", "\'", "\"", "&quot;"), "", $description);
    }

    public static function dump($data) {
        echo "<pre>";
        print_r($data);
        echo "</pre>";
    }

    public function get_post($post_id) {
        return get_post($post_id);
    }

    function recursive_categories() {
        
    }

    public function push_to_corcrm($post_id) {

        global $wpdb, $product;
        $table_name = $wpdb->prefix . 'posts';
        $product = $this->getProduct($post_id);

        $prod_terms = get_the_terms($post_id, 'product_cat');

        $parentId = 0;
        $category = array();
        if (!empty($prod_terms)) {
            foreach ($prod_terms as $cat) {
                $category[] = $cat->name;
            }
        }
        $mergeCategories = implode(' / ', $category);

        $product_image = $this->get_productImage($post_id);

        $attachment_ids[0] = get_post_thumbnail_id($post_id);
        $attachment = wp_get_attachment_image_src($attachment_ids[0], 'full');

        $prod = array();
        $image_link = array();
        if (!empty($product_image)) {
            $image_link[] = $attachment[0];
        }

        $attachment_ids = $product->get_gallery_image_ids();

        foreach ($attachment_ids as $attachment_id) {
            $image_link[] = wp_get_attachment_url($attachment_id);
        }

        if ($product->is_type('variable')) {
            $variations = $product->get_available_variations();
        }

        $prod['post_id'] = $product->get_id();
        $prod['corcrm_product_id'] = $this->get_post($post_id)->corcrm_product_id;
        if (isset($_POST['corcrm_prod']) && is_array($_POST['corcrm_prod'])) {
            foreach ($_POST['corcrm_prod'] as $key => $id) {
                if ($id > 0) {
                    $prod['corcrm_product_id'] = $id;
                }
            }
        }
        $prod['title'] = $product->get_title();
        $prod['name'] = "";
        $prod['description'] = (!empty($this->get_post($post_id)->post_content)) ? self::cleanDescription($this->get_post($post_id)->post_content) : "";
        $sku = $product->get_sku();
        $prod['code'] = (!empty($sku)) ? $product->get_sku() : $product->get_title();
        $weight = $product->get_weight();
        $prod['weight'] = (!empty($weight)) ? $weight : 0.00;
        if ($product->is_type('variable')) {
            $prod['price'] = $variations[0]['display_price'];
        } else {
            $salePrice = $product->get_sale_price();
            $regularPrice = $product->get_regular_price();
            $prod['price'] = (!empty($salePrice)) ? $salePrice : ((!empty($regularPrice)) ? $regularPrice : 0.00);
        }

        $qty = get_post_meta($product->get_id(), '_stock', true);
        $prod['stock'] = $qty;
        if ($product->managing_stock()) {
            $prod['unlimited_stock'] = 'no';
        } else {
            $prod['unlimited_stock'] = 'yes';
            $prod['stock'] = 0;
        }

        //get cost
        $cost = get_post_meta($product->get_id(), '_wc_cog_cost', true);
        $prod['raw_product_cost'] = $cost;

        //get product code - UPC
        $ex_prod_id = get_post_meta($product->get_id(), 'hwp_product_gtin', true);
        $prod['external_id'] = $ex_prod_id;
        $prod['external_id_type'] = 'upc';


        if ($product->has_attributes()) {
            $count = 0;
            foreach ($product->get_attributes() as $attr => $attrdata) {
                $optionName = ucwords(str_replace('pa_', '', $attr));
                $attrValues = explode(',', $product->get_attribute($attr));
                sort($attrValues);
                foreach ($attrValues as $v) {
                    $v1 = explode('|', $v);
                    foreach ($v1 as $v) {
                        $attributes[] = array($count, $v, '', $optionName);
                        $count++;
                    }
                }
            }

            $prod['attributes'] = serialize($attributes);
        } else {
            $prod['attributes'] = '';
        }

        $description = $prod['description'];
        $image_link = array_reverse($image_link);
        $prod['images'] = serialize(array_unique($image_link));
        $pTitle = $product->get_title();
        $longDesc = $description;
        $shortDesc = $description;
        //array(Product Title, Long Description, Short Description, Description, Language)
        $description_raw = serialize(array(array($pTitle, $longDesc, $shortDesc, $description, "en")));
        $prod['description_raw'] = $description_raw;
        $prod['product_category'] = $mergeCategories;

        $product_id = $prod['post_id']; // the ID of the product to check

        $_product = $product;

        if ($_product->is_type('variable')) {

            $args = array(
                'post_type' => 'product_variation',
                'post_status' => array('private', 'publish'),
                'numberposts' => -1,
                'orderby' => 'menu_order',
                'order' => 'asc',
                'post_parent' => $post_id
            );

            $variations = get_posts($args);
            $availVars = $_product->get_available_variations();
            $prodSku = $_product->get_sku();
            $varPrice = '';
            $productDataArray = array();

            foreach ($variations as $key => $variation) {

                $variable_product1 = new WC_Product_Variation($variation->ID);
                $regular_price = $variable_product1->get_regular_price();
                $sales_price = $variable_product1->get_sale_price();
                $qty = $variable_product1->get_stock_quantity();
                $prod = array();
                // Check if stock managed or not
                if ($variable_product1->managing_stock()) {
                    $prod['unlimited_stock'] = 'no';
                } else {
                    $prod['unlimited_stock'] = 'yes';
                    $qty = 0;
                }
                if (!empty($sales_price)) {
                    $varPrice = $sales_price;
                } else {
                    $varPrice = $regular_price;
                }

                $var_image_link = array();
                $var_image_link[] = $availVars[$key]['image']['thumb_src'];
                $image = serialize($var_image_link);

                $product1 = new WC_Product_Variation($variation->ID);
                $prod['post_id'] = $variation->ID;

                $variation = wc_get_product($prod['post_id']);

                $variation_attributes = $variation->get_variation_attributes();

                $i = 0;
                $attributes = array();
                foreach ($variation_attributes as $attr => $attrdata) {
                    $optionName = str_replace('attribute_', '', $attr);
                    $optionName = ucwords(str_replace('pa_', '', $optionName));
                    $attributes[] = array($i, $attrdata, '', $optionName);
                    $i++;
                }
                $name = "";
                foreach ($variation_attributes as $key => $va) {
                    $attrKey = str_replace('attribute_', '', $key);
                    $meta = get_post_meta($prod['post_id'], $key, true);
                    $term = get_term_by('slug', $meta, $attrKey);
                    if (isset($term->name) && !empty($term->name)) {
                        $name .= $term->name . "/";
                    } else {
                        $name .= $va . "/";
                    }
                }
                $crm_id = get_post($prod['post_id']);
                $prod_name = rtrim($name, "/");

                $description = (!empty($this->get_post($post_id)->post_content)) ? self::cleanDescription($this->get_post($post_id)->post_content) : "";

                $pTitle = $product->get_title() . '-' . $prod_name;
                $longDesc = $prod_name;
                $shortDesc = $description;
                //array(Product Title, Long Description, Short Description, Description, Language)
                $description_raw = serialize(array(array($pTitle, $longDesc, $shortDesc, $description, "en")));

                $prod['corcrm_product_id'] = $crm_id->corcrm_product_id;
                $prod['title'] = $product->get_title() . "-" . $prod_name;
                $prod['description'] = $description;
                $prod['description_raw'] = $description_raw;
                $varSku = $product1->get_sku();
                $sku = (!empty($varSku)) ? $varSku : $prodSku;
                $prod['code'] = (!empty($sku)) ? $sku : $product->get_title();
                $vWeight = $variation->get_weight();
                $weight = (!empty($vWeight)) ? $vWeight : $product->get_weight();
                $prod['weight'] = (!empty($weight)) ? $weight : 0.00;
                $prod['price'] = $varPrice;
                $prod['stock'] = $qty;
                $prod['images'] = $image;
                $prod['product_category'] = $mergeCategories;
                $prod['attributes'] = serialize($attributes);

                // Get Cost of Good
                $cost = get_post_meta($prod['post_id'], '_wc_cog_cost', true);
                $prod['raw_product_cost'] = $cost;

                //get product code
                $ex_prod_id = get_post_meta($prod['post_id'], 'hwp_var_gtin', true);
                $prod['external_id'] = $ex_prod_id;
                $prod['external_id_type'] = 'upc';

                if ($crm_id->corcrm_product_id > 0) {
                    $productDataArray[] = $this->add_subscription_data($prod);
                } else {
                    $this->add_to_corcrm($prod);
                }
            }

            if (!empty($productDataArray) && count($productDataArray) > 0) {
                $this->bulk_update_product($productDataArray);
            }
        } else {
            if ($prod['corcrm_product_id'] > 0) {
                $this->update_to_corcrm($prod);
            } else {
                $this->add_to_corcrm($prod);
            }
        }

        return;
    }

    private function bulk_update_product($productDataArray) {
        try {
            $client = new SoapClient($this->wsdl_url, array('trace' => 1, 'encoding' => 'ISO-8859-1'));
            $accountInfo = array('user_name' => $this->user_name, 'auth_key' => htmlspecialchars_decode($this->auth_key), 'digest' => "", "nonce" => "");

            $data = array(
                'accountInfo' => $accountInfo,
                'productDataArray' => $productDataArray
            );

            $api_response_pro = $client->__soapCall('update_bulk_product', $data);
            $response['response'] = (get_object_vars($api_response_pro));
            if ($response['response']['status'] == "success") {
                
            } else {
                Corcrm_Utility::logger(print_r($response['response'], true));
            }
        } catch (Exception $ex) {
            
        }
    }

    public function to_add($data) {

        global $wpdb;
        $client = new SoapClient($this->wsdl_url, array('trace' => 1, 'encoding' => 'ISO-8859-1'));
        try {
            $api_response_pro = $client->__soapCall('create_product', $data);
            $response['response'] = (get_object_vars($api_response_pro['response']));
            if ($response['response']['status'] == "success") {
                //update corcrm_product_id

                $upd = $wpdb->update($wpdb->prefix . 'posts', array('corcrm_product_id' => $api_response_pro['product_id']), array('id' => $data['external_prod_id']), $format = null, $where_format = null);

                if (!$upd) {
                    Corcrm_Utility::logger("Unable to update CorCRM productID Error:" . $wpdb->last_error);
                }
                Corcrm_Utility::logger("CorCRM productID:" . $api_response_pro['product_id']);
            } else {
                Corcrm_Utility::logger(print_r($response['response'], true));
            }
        } catch (Exception $ex) {
            throw new SoapFault("SoapClient", $client->__getLastResponse());
        }
    }

    private function add_to_corcrm($prod) {
        global $wpdb;
        try {
            $client = new SoapClient($this->wsdl_url, array('trace' => 1, 'encoding' => 'ISO-8859-1'));

            $accountInfo = array('user_name' => $this->user_name, 'auth_key' => htmlspecialchars_decode($this->auth_key), 'digest' => "", "nonce" => "");
            $data = array('accountInfo' => $accountInfo,
                'title' => $prod['title'],
                'description' => $prod['description'],
                "parent_id" => rand(0, 99999),
                "access_group" => ""
            );

            try {

                $api_response = $client->__soapCall('create_product_folder', $data);
            } catch (Exception $e) {
                throw new SoapFault("SoapClient", $client->__getLastResponse());
            }

            $response['response'] = (get_object_vars($api_response['response']));

            if ($response['response']['status'] == "success") {

                $product_id = $prod['post_id']; // the ID of the product to check
                $_product = wc_get_product($product_id);

                // send product type information
                $custom_product_type = get_post_meta($product_id, 'corcrm_custom_product_type', true);
                $custom_product_type_v = get_post_meta($product_id, '_corcrm_custom_product_type', true);
                $subscription_time = get_post_meta($product_id, 'corcrm_custom_subscription_time', true);
                $subscription_time_v = get_post_meta($product_id, '_corcrm_custom_subscription_time', true);
                $sub_time = $subscription_time ? $subscription_time : $subscription_time_v;

                switch ($sub_time) {
                    case '1':
                        $time = '1 Day';
                        $cycle = 'days';
                        $sub_days_between = "1";
                        break;
                    case '7':
                        $time = '1 Week';
                        $cycle = 'days';
                        $sub_days_between = "7";
                        break;
                    case '14':
                        $time = '2 Week';
                        $cycle = 'days';
                        $sub_days_between = "14";
                        break;
                    case '1m':
                        $time = '1 Month';
                        $cycle = 'months';
                        $sub_days_between = "1";
                        break;
                    case '6m':
                        $time = '6 Month';
                        $cycle = 'months';
                        $sub_days_between = "6";
                        break;
                    case '1y':
                        $time = '1 Year';
                        $cycle = 'months';
                        $sub_days_between = "12";
                        break;
                    default:
                        $time = '';
                        $cycle = '';
                        break;
                }

                if (($custom_product_type == 'subscription') || ($custom_product_type_v == 'subscription')) {

                    $data = array('accountInfo' => $accountInfo,
                        'folder_id' => $api_response['folder_id'],
                        'code' => $prod['code'],
                        "price" => $prod['price'],
                        'description_raw' => $prod['description_raw'],
                        "weight" => $prod['weight'],
                        "attributes" => $prod['attributes'],
                        "stock" => $prod['stock'],
                        "subscription" => 'Yes',
                        "product_type" => $custom_product_type ? $custom_product_type : $custom_product_type_v,
                        "subscription_cycle" => $cycle,
                        "subscription_data" => serialize(array(array($sub_days_between, $prod['price'], '0'))),
                        "external_prod_id" => $product_id,
                        "external_prod_source" => "woocommerce",
                        "image" => $prod['images'],
                        "additional_info" => '',
                        "include_required_modifiers" => '',
                        "required_modifiers" => '',
                        "optional_modifiers" => '',
                        "product_category" => $prod['product_category'],
                        "unlimited_stock" => $prod['unlimited_stock'],
                        "raw_product_cost" => $prod['raw_product_cost'],
                        "external_id" => $prod['external_id'],
                        "external_id_type" => $prod['external_id_type']
                    );
                } else {
                    $data = array('accountInfo' => $accountInfo,
                        'folder_id' => $api_response['folder_id'],
                        'code' => $prod['code'],
                        "price" => $prod['price'],
                        'description_raw' => $prod['description_raw'],
                        "weight" => $prod['weight'],
                        "attributes" => $prod['attributes'],
                        "stock" => $prod['stock'],
                        "subscription" => 'No',
                        "product_type" => 'straight',
                        "subscription_cycle" => 'months',
                        "subscription_data" => 'a:0:{}',
                        "external_prod_id" => $product_id,
                        "external_prod_source" => "woocommerce",
                        "image" => $prod['images'],
                        "additional_info" => '',
                        "include_required_modifiers" => '',
                        "required_modifiers" => '',
                        "optional_modifiers" => '',
                        "product_category" => $prod['product_category'],
                        "unlimited_stock" => $prod['unlimited_stock'],
                        "raw_product_cost" => $prod['raw_product_cost'],
                        "external_id" => $prod['external_id'],
                        "external_id_type" => $prod['external_id_type']
                    );
                }

                $this->to_add($data);
            } else {
                Corcrm_Utility::logger(print_r($api_response, true));
            }
        } catch (SoapFault $sp) {
            Corcrm_Utility::logger($sp->getMessage());
        } catch (Exception $ex) {
            Corcrm_Utility::logger($ex->getMessage());
        }
    }

    private function add_subscription_data($prod) {

        $accountInfo = array('user_name' => $this->user_name, 'auth_key' => htmlspecialchars_decode($this->auth_key), 'digest' => "", "nonce" => "");
        $product_id = $prod['post_id'];
        $_product = wc_get_product($product_id);
        $corProdId = (!empty($prod['corcrm_product_id']->corcrm_product_id)) ? $prod['corcrm_product_id']->corcrm_product_id : $prod['corcrm_product_id'];
        $custom_product_type = get_post_meta($product_id, 'corcrm_custom_product_type', true);

        $custom_product_type_v = get_post_meta($product_id, '_corcrm_custom_product_type', true);
        $subscription_time = get_post_meta($product_id, 'corcrm_custom_subscription_time', true);
        $subscription_time_v = get_post_meta($product_id, '_corcrm_custom_subscription_time', true);
        $sub_time = $subscription_time ? $subscription_time : $subscription_time_v;

        switch ($sub_time) {
            case '1':
                $time = '1 Day';
                $cycle = 'days';
                $sub_days_between = "1";
                break;
            case '7':
                $time = '1 Week';
                $cycle = 'days';
                $sub_days_between = "7";
                break;
            case '14':
                $time = '2 Week';
                $cycle = 'days';
                $sub_days_between = "14";
                break;
            case '1m':
                $time = '1 Month';
                $cycle = 'months';
                $sub_days_between = "1";
                break;
            case '6m':
                $time = '6 Month';
                $cycle = 'months';
                $sub_days_between = "6";
                break;
            case '1y':
                $time = '1 Year';
                $cycle = 'months';
                $sub_days_between = "12";
                break;
            default:
                $time = '';
                $cycle = '';
                break;
        }

        if (($custom_product_type == 'subscription') || ($custom_product_type_v == 'subscription')) {

            $data = array(
                'accountInfo' => $accountInfo,
                'product_id' => $corProdId,
                'folder_id' => 0,
                'code' => $prod['code'],
                "price" => $prod['price'],
                'description_raw' => $prod['description_raw'],
                "weight" => $prod['weight'],
                "attributes" => $prod['attributes'],
                "stock" => $prod['stock'],
                "subscription" => 'Yes',
                "product_type" => $custom_product_type ? $custom_product_type : $custom_product_type_v,
                "subscription_cycle" => $cycle,
                "subscription_data" => serialize(array(array($sub_days_between, $prod['price'], '0'))),
                "external_prod_id" => $prod['post_id'],
                "external_prod_source" => "woocommerce",
                "image" => $prod['images'],
                "product_category" => $prod['product_category'],
                "unlimited_stock" => $prod['unlimited_stock'],
                "raw_product_cost" => $prod['raw_product_cost'],
                "external_id" => $prod['external_id'],
                "external_id_type" => $prod['external_id_type']
            );
        } else {
            $data = array(
                'accountInfo' => $accountInfo,
                'product_id' => $corProdId,
                'folder_id' => 0,
                'code' => $prod['code'],
                "price" => $prod['price'],
                'description_raw' => $prod['description_raw'],
                "weight" => $prod['weight'],
                "attributes" => $prod['attributes'],
                "stock" => $prod['stock'],
                "subscription" => 'No',
                "product_type" => 'straight',
                "subscription_cycle" => 'months',
                "subscription_data" => 'a:0:{}',
                "external_prod_id" => $prod['post_id'],
                "external_prod_source" => "woocommerce",
                "image" => $prod['images'],
                "product_category" => $prod['product_category'],
                "unlimited_stock" => $prod['unlimited_stock'],
                "raw_product_cost" => $prod['raw_product_cost'],
                "external_id" => $prod['external_id'],
                "external_id_type" => $prod['external_id_type']
            );
        }

        return $data;
    }

    private function update_to_corcrm($prod) {

        if (empty($prod['corcrm_product_id'])) {
            self::logger('CorCRM productID is empty:' . print_r($prod, true));
            return;
        }

        try {

            $client = new SoapClient($this->wsdl_url, array('trace' => 1, 'encoding' => 'ISO-8859-1'));

            $product_id = $prod['post_id']; // the ID of the product to check
            $_product = wc_get_product($product_id);
            $corProdId = (!empty($prod['corcrm_product_id']->corcrm_product_id)) ? $prod['corcrm_product_id']->corcrm_product_id : $prod['corcrm_product_id'];

            // Send product type information to hiecor during product update operation
            $data = $this->add_subscription_data($prod);

            try {

                $api_response_pro = $client->__soapCall('update_product', $data);
                $response['response'] = (get_object_vars($api_response_pro));
                if ($response['response']['status'] == "success") {
                    
                } else {
                    Corcrm_Utility::logger(print_r($response['response'], true));
                }
            } catch (Exception $ex) {
                throw new SoapFault("SoapClient", $client->__getLastResponse());
            }
        } catch (SoapFault $sp) {
            Corcrm_Utility::logger($sp->getMessage());
        } catch (Exception $ex) {
            Corcrm_Utility::logger($ex->getMessage());
        }
    }

    public function push_coupon_to_corcrm($post_id, $post, $update, $post_data) {
        global $wpdb;
        $coupon = $this->getCoupon($post, $post_data);
        $coupon['post_id'] = $post_id;
        $coupon['corcrm_product_id'] = $post->corcrm_product_id;

        try {

            $client = new SoapClient($this->wsdl_url, array('trace' => 1, 'encoding' => 'ISO-8859-1'));

            $accountInfo = array('user_name' => $this->user_name, 'auth_key' => htmlspecialchars_decode($this->auth_key), 'digest' => "", "nonce" => "");
            $get_coupon_data = array('accountInfo' => $accountInfo,
                "coupon_id" => $coupon['corcrm_product_id'],
            );
            if ($coupon['corcrm_product_id'] > 0) {
                try {
                    $get_coupon_response = $client->__soapCall('get_coupon', $get_coupon_data);
                    $coupon_response['response'] = (get_object_vars($get_coupon_response['response']));
                    if ($coupon_response['response']['status'] == "success") {
                        $coupon['coupon_id'] = $get_coupon_response['coupon_id'];
                        $this->update_coupon_to_corcrm($coupon, $accountInfo);
                    } else {
                        $this->insert_coupon_to_corcrm($coupon, $accountInfo);
                    }
                } catch (Exception $ex) {
                    throw new SoapFault("SoapClient", $client->__getLastResponse());
                }
            } else {
                $this->insert_coupon_to_corcrm($coupon, $accountInfo);
            }
        } catch (SoapFault $sp) {
            Corcrm_Utility::logger($sp->getMessage());
        } catch (Exception $ex) {
            Corcrm_Utility::logger($ex->getMessage());
        }
    }

    public function getCoupon($post, $data) {
        global $wpdb, $product;
       
        $table_name = $wpdb->prefix . 'posts';

        if ($data['discount_type'] == "fixed_cart" || $data['discount_type'] == "fixed_product") {
            $data['discount_type'] = "Flat";
        } else {
            $data['discount_type'] = "Percent";
        }
        $meta_data_of_coupon = get_post_meta($post->ID);
        foreach ($meta_data_of_coupon as $k => $v) {
            $meta_data_of_coupon[$k] = array_shift($v);
        }
        $max_spend = $meta_data_of_coupon['maximum_amount'];
        if (!empty($max_spend)) {
            $data['total_amount'] = $max_spend;
        } else {
            $data['total_amount'] = 0;
        }

        $d_start = new DateTime($post->post_date);
        $date_start = $d_start->format('Y-m-d') . " " . "00:00:00";
        $c_date = new DateTime($post->post_date);
        $created_date = $c_date->format('Y-m-d') . " " . "00:00:00";
        $u_date = new DateTime($post->post_modified);
        $updated_date = $u_date->format('Y-m-d') . " " . "00:00:00";

        $coupon = array(
            'coupon_code' => $post->post_title,
            'discount_type' => $data['discount_type'],
            'discount_amount' => $data['coupon_amount'],
            "active" => 'yes',
            'coupon_name' => $post->post_title,
            "total_amount" => $data['total_amount'],
            'free_shipping' => 'No',
            'date_start' => $date_start,
            'date_end' => $data['expiry_date'],
            'uses_per_coupon' => $data['usage_limit'],
            'uses_per_customer' => $data['usage_limit_per_user'],
            'products' => '',
            'date_created' => $created_date,
            'date_updated' => $updated_date
        );
        if (isset($data['free_shipping'])) {
            $coupon['free_shipping'] = $data['free_shipping'];
        }
        $corcrm_products = array();
        $corcrmid = "";
        $prod = $data['product_ids'];
        foreach ($prod as $key => $value) {
            $product = $this->getProduct($value);
            
            if ($product->is_type('simple')) {
               $id = get_post($value);
               $corcrmid .= $id->corcrm_product_id . ",";
            } else if ($product->is_type('variable')) {
               $available_variations = $product->get_available_variations();
               
                foreach ($available_variations as $key => $value) {
                    $variation_id = get_post($value['variation_id']);
                    $corcrmid .= $variation_id->corcrm_product_id . ",";
                }
            }else{
                $crmid = $wpdb->get_var("SELECT `corcrm_product_id` FROM $table_name WHERE ID = $value");
                $corcrmid .= $crmid . ",";
            }
        }
        $corcrm_id = rtrim($corcrmid, ",");
        $coupon['products'] = $corcrm_id;
        return $coupon;
    }

    private function insert_coupon_to_corcrm($coupon, $accountInfo) {
        global $wpdb;
        $client = new SoapClient($this->wsdl_url, array('trace' => 1, 'encoding' => 'ISO-8859-1'));
        $data = array('accountInfo' => $accountInfo,
            "coupon_code" => $coupon['coupon_code'],
            "discount_type" => $coupon['discount_type'],
            "discount_amount" => $coupon['discount_amount'],
            "active" => $coupon['active'],
            "coupon_name" => $coupon['coupon_name'],
            "total_amount" => $coupon['total_amount'],
            "free_shipping" => $coupon['free_shipping'],
            "date_start" => $coupon['date_start'],
            "date_end" => $coupon['date_end'],
            "uses_per_coupon" => $coupon['uses_per_coupon'],
            "uses_per_customer" => $coupon['uses_per_customer'],
            "products" => $coupon['products'],
            "date_created" => $coupon['date_created'],
            "date_updated" => $coupon['date_updated'],
        );

        try {
            $api_response_pro = $client->__soapCall('create_coupon', $data);
            $response['response'] = (get_object_vars($api_response_pro['response']));
            if ($response['response']['status'] == "success") {
                //update corcrm_product_id

                $upd = $wpdb->update($wpdb->prefix . 'posts', array('corcrm_product_id' => $api_response_pro['coupon_id']), array('id' => $coupon['post_id']), $format = null, $where_format = null);
                if (!$upd) {
                    Corcrm_Utility::logger("Unable to update CorCRM Coupon ID Error:" . $wpdb->last_error);
                }
                Corcrm_Utility::logger("CorCRM couponId:" . $api_response_pro[' coupon_id']);
            } else {
                
            }
        } catch (Exception $ex) {
            throw new SoapFault("SoapClient", $client->__getLastResponse());
        }
    }

    private function update_coupon_to_corcrm($coupon, $accountInfo) {
        $client = new SoapClient($this->wsdl_url, array('trace' => 1, 'encoding' => 'ISO-8859-1'));
        $data = array('accountInfo' => $accountInfo,
            "coupon_id" => $coupon['coupon_id'],
            "coupon_code" => $coupon['coupon_code'],
            "discount_type" => $coupon['discount_type'],
            "discount_amount" => $coupon['discount_amount'],
            "active" => $coupon['active'],
            "coupon_name" => $coupon['coupon_name'],
            "total_amount" => $coupon['total_amount'],
            "free_shipping" => $coupon['free_shipping'],
            "date_start" => $coupon['date_start'],
            "date_end" => $coupon['date_end'],
            "uses_per_coupon" => $coupon['uses_per_coupon'],
            "uses_per_customer" => $coupon['uses_per_customer'],
            "products" => $coupon['products'],
            "date_updated" => $coupon['date_updated']
        );
        try {
            $api_response_pro = $client->__soapCall('update_coupon', $data);
            $response['response'] = (get_object_vars($api_response_pro['response']));
            if ($response['response']['status'] == "success") {
                
            } else {
                
            }
        } catch (Exception $ex) {
            throw new SoapFault("SoapClient", $client->__getLastResponse());
        }
    }

    public function check_corcrm_existance($product_id, $variation_id) {
        global $wpdb;
        $postTable = $wpdb->prefix . 'posts';
        $wc_post_id = $product_id;
        $client = new SoapClient($this->wsdl_url, array('trace' => 1));
        $accountInfo = array('user_name' => $this->user_name, 'auth_key' => htmlspecialchars_decode($this->auth_key), 'digest' => "", "nonce" => "");

        $hiecorProdId = $wpdb->get_var("SELECT `corcrm_product_id` FROM $postTable WHERE ID = $product_id");
        if ($variation_id > 0) {
            $hiecorProdId = $wpdb->get_var("SELECT `corcrm_product_id` FROM $postTable WHERE ID = $variation_id");
            $wc_post_id = $variation_id;
        }

        $data = array(
            'accountInfo' => $accountInfo,
            'product_id' => $hiecorProdId,
            'external_id' => ''
        );
        try {
            $api_response = $client->__soapCall('get_product', $data);
        } catch (Exception $ex) {
            throw new SoapFault('SoapClient', $client->__getLastResponse());
        }

        $response['response'] = (get_object_vars($api_response['response']));
        if ($response['response']['status'] == "failure") {

            $upd = $wpdb->update($postTable, array('corcrm_product_id' => 0), array('id' => $wc_post_id), $format = null, $where_format = null);
            if (!$upd) {
                Corcrm_Utility::logger("Unable to update CorCRM productID - " . $product_id . " Error:" . $wpdb->last_error);
            }

            $_pf = new WC_Product_Factory();
            $product = $_pf->get_product($wc_post_id);
            wp_update_post(array('ID' => $wc_post_id, 'post_title' => $product->get_title()));

            $this->push_to_corcrm($product_id);
        }
    }

}
