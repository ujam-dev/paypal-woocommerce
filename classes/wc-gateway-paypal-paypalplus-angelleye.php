<?php

class WC_Gateway_PayPal_Plus_AngellEYE extends WC_Payment_Gateway {

    public function __construct() {
        // Necessary Properties

        $this->id = 'paypal_plus';
        $this->icon = apply_filters('woocommerce_paypal_plus_icon', '');
        $this->has_fields = true;
        $this->home_url = is_ssl() ? home_url('/', 'https') : home_url('/'); //set the urls (cancel or return) based on SSL
        $this->testurl = 'https://api.sandbox.paypal.com';
        $this->liveurl = 'https://api.paypal.com';
        $this->relay_response_url = add_query_arg('wc-api', 'WC_Gateway_PayPal_Plus_AngellEYE', $this->home_url);
        $this->method_title = __('PayPal Plus', 'paypal-for-woocommerce');
        $this->secure_token_id = '';
        $this->securetoken = '';
        $this->supports = array(
            'products',
            'refunds'
        );
        // Load the form fields.
        $this->init_form_fields();
        // Load the settings.
        $this->init_settings();
        // Define user set variables
        $this->title = $this->settings['title'];
        $this->description = $this->settings['description'];
        $this->testmode = $this->settings['testmode'];
        $this->rest_client_id = $this->settings['rest_client_id'];
        $this->rest_secret_id = $this->settings['rest_secret_id'];
        $this->debug = $this->settings['debug'];
        $this->invoice_prefix = $this->settings['invoice_prefix'];
        $this->send_items = 'yes';
        $this->billing_address = isset($this->settings['billing_address']) ? $this->settings['billing_address'] : 'no';
        // Determine the user and host address
        $this->hostaddr = $this->testmode == 'yes' ? $this->testurl : $this->liveurl;
        // Enable Logs if user configures to debug
        if ($this->debug == 'yes')
            $this->log = new WC_Logger();
        // Hooks
        add_action('admin_notices', array($this, 'checks')); //checks for availability of the plugin
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_receipt_paypal_plus', array($this, 'receipt_page')); // Payment form hook
        // Set enable property if the Paypal Adavnced supported for the user country


        if (!$this->is_available())
            $this->enabled = false;
    }

    /**
     * Check if required fields for configuring the gateway are filled up by the administrator
     * @access public
     * @return void
     * */
    public function checks() {
        if ($this->enabled == 'no') {
            return;
        }
        // Check required fields
        if (!$this->rest_client_id) {
            echo '<div class="error"><p>' . sprintf(__('Paypal Plus error: Please enter your Rest API Cient ID <a href="%s">here</a>', 'paypal-for-woocommerce'), admin_url('admin.php?page=wc-settings&tab=checkout&section=' . strtolower('WC_Gateway_PayPal_Plus_AngellEYE'))) . '</p></div>';
        } elseif (!$this->rest_secret_id) {
            echo '<div class="error"><p>' . sprintf(__('Paypal Plus error: Please enter your Rest API Cient Secret ID <a href="%s">here</a>', 'paypal-for-woocommerce'), admin_url('admin.php?page=wc-settings&tab=checkout&section=' . strtolower('WC_Gateway_PayPal_Plus_AngellEYE'))) . '</p></div>';
        }
        return;
    }

    /**
     * redirect_to - redirects to the url based on layout type
     *
     * @access public
     * @return javascript code to redirect the parent to a page
     */
    public function redirect_to($redirect_url) {
        // Clean
        @ob_clean();
        echo "<script>window.parent.location.href='" . $redirect_url . "';</script>";
        exit;
    }

    function get_approvalurl($order_id) {
        $const_client_id = $this->settings['rest_client_id'];
        $const_secret_id = $this->settings['rest_secret_id'];

        define('CLIENT_ID', $const_client_id); //your PayPal client ID
        define('CLIENT_SECRET', $const_secret_id); //PayPal Secret

        define('CANCEL_URL', site_url()); //cancel URL
        define('PP_CURRENCY', 'EUR'); //Currency code

        include_once __DIR__ . "/vendor/autoload.php"; //include PayPal SDK
        if (!defined("PP_CONFIG_PATH")) {
            define("PP_CONFIG_PATH", __DIR__);
        }
        include_once(__DIR__ . '/functions.inc.php'); //our PayPal functions

        if (!session_id()) {
            session_start();
        }
        $order = new WC_Order($order_id);

        //calculate total amount of all quantity. 

        $redirect_url = $this->get_return_url($order);
        try { // try a payment request
            //if payment method is paypal
            //set array of items you are selling, single or multiple
            $item_array = array();
            $item_array_details = array();
            $final_items = array();
            $order_discount_array = array();




            $get_order_details = $this->get_order_details($order);


            foreach ($get_order_details['Payments'][0]['order_items'] as $key => $value) {

                $item_array = array(
                    'name' => $value['name'],
                    'quantity' => $value['qty'],
                    'price' => $value['amt'],
                    'sku' => isset($value['sku']) ? $value['sku'] : '1',
                    'currency' => $get_order_details['Payments'][0]['currencycode']
                );

                array_push($item_array_details, $item_array);
                $final_items = $item_array_details;
            }


            $review_order_page_url = get_permalink(wc_get_page_id('review_order'));
            if (!$review_order_page_url) {
                $this->add_log(__('Review Order Page not found, re-create it. ', 'paypal-for-woocommerce'));
                include_once( WC()->plugin_path() . '/includes/admin/wc-admin-functions.php' );
                $page_id = wc_create_page(esc_sql(_x('review-order', 'page_slug', 'woocommerce')), 'woocommerce_review_order_page_id', __('Checkout &rarr; Review Order', 'paypal-for-woocommerce'), '[woocommerce_review_order]', wc_get_page_id('checkout'));
                $review_order_page_url = get_permalink($page_id);
            }
            $returnURL1 = (add_query_arg('pp_action', $order_id, $review_order_page_url));

            $totalamt = $get_order_details['Payments'][0]['amt'];
            $_SESSION["get_order_details"] = $get_order_details;

            $result = create_paypal_payment($totalamt, PP_CURRENCY, '', $final_items, $returnURL1, CANCEL_URL);

            //if payment method was PayPal, we need to redirect user to PayPal approval URL
            if ($result->state == "created" && $result->payer->payment_method == "paypal") {
                $_SESSION["payment_id"] = $result->id; //set payment id for later use, we need this to execute payment

                return $result->links[1]->href;
            }
        } catch (PPConnectionException $ex) {

            wc_add_notice(__('Error:', 'paypal-for-woocommerce') . ' "' . $ex->getData() . '"', 'error');
        } catch (Exception $ex) {
            wc_add_notice(__('Error:', 'paypal-for-woocommerce') . ' "' . $ex->getMessage() . '"', 'error');
        }
    }

    function get_order_details($order) {
        /*
         * Display message to user if session has expired.
         */
        if (sizeof(WC()->cart->get_cart()) == 0) {
            $ms = sprintf(__('Sorry, your session has expired. <a href=%s>Return to homepage &rarr;</a>', 'paypal-for-woocommerce'), '"' . home_url() . '"');
            $set_ec_message = apply_filters('angelleye_set_ec_message', $ms);
            wc_add_notice($set_ec_message, "error");
        }

        /*
         * Check if the PayPal class has already been established.
         */
        if (!class_exists('Angelleye_PayPal')) {
            require_once( 'lib/angelleye/paypal-php-library/includes/paypal.class.php' );
        }

        // Basic array of survey choices.  Nothing but the values should go in here.
        $SurveyChoices = array('Choice 1', 'Choice2', 'Choice3', 'etc');

        /*
         * Get tax amount.
         */
        if (get_option('woocommerce_prices_include_tax') == 'yes') {
            $shipping = WC()->cart->shipping_total + WC()->cart->shipping_tax_total;
            $tax = '0.00';
        } else {
            $shipping = WC()->cart->shipping_total;
            $tax = WC()->cart->get_taxes_total();
        }

        if ('yes' === get_option('woocommerce_calc_taxes') && 'yes' === get_option('woocommerce_prices_include_tax')) {
            $tax = wc_round_tax_total(WC()->cart->tax_total + WC()->cart->shipping_tax_total);
        }

        $Payments = array();
        $Payment = array(
            'amt' => number_format(WC()->cart->total, 2, '.', ''), // Required.  The total cost of the transaction to the customer.  If shipping cost and tax charges are known, include them in this value.  If not, this value should be the current sub-total of the order.
            'currencycode' => get_woocommerce_currency(), // A three-character currency code.  Default is USD.
            'shippingamt' => number_format($shipping, 2, '.', ''), // Total shipping costs for this order.  If you specify SHIPPINGAMT you mut also specify a value for ITEMAMT.
            'shippingdiscamt' => '', // Shipping discount for this order, specified as a negative number.
            'insuranceamt' => '', // Total shipping insurance costs for this order.
            'insuranceoptionoffered' => '', // If true, the insurance drop-down on the PayPal review page displays the string 'Yes' and the insurance amount.  If true, the total shipping insurance for this order must be a positive number.
            'handlingamt' => '', // Total handling costs for this order.  If you specify HANDLINGAMT you mut also specify a value for ITEMAMT.
            'taxamt' => number_format($tax, 2, '.', ''), // Required if you specify itemized L_TAXAMT fields.  Sum of all tax items in this order.
            'desc' => '', // Description of items on the order.  127 char max.
            'custom' => '', // Free-form field for your own use.  256 char max.
            'invnum' => '', // Your own invoice or tracking number.  127 char max.
            'notifyurl' => '', // URL for receiving Instant Payment Notifications
            'shiptoname' => '', // Required if shipping is included.  Person's name associated with this address.  32 char max.
            'shiptostreet' => '', // Required if shipping is included.  First street address.  100 char max.
            'shiptostreet2' => '', // Second street address.  100 char max.
            'shiptocity' => '', // Required if shipping is included.  Name of city.  40 char max.
            'shiptostate' => '', // Required if shipping is included.  Name of state or province.  40 char max.
            'shiptozip' => '', // Required if shipping is included.  Postal code of shipping address.  20 char max.
            'shiptocountrycode' => '', // Required if shipping is included.  Country code of shipping address.  2 char max.
            'shiptophonenum' => '', // Phone number for shipping address.  20 char max.
            'notetext' => '', // Note to the merchant.  255 char max.
            'allowedpaymentmethod' => '', // The payment method type.  Specify the value InstantPaymentOnly.
            //    'paymentaction' => $this->payment_action == 'Authorization' ? 'Authorization' : 'Sale', // How you want to obtain the payment.  When implementing parallel payments, this field is required and must be set to Order.
            'paymentrequestid' => '', // A unique identifier of the specific payment request, which is required for parallel payments.
            'sellerpaypalaccountid' => ''   // A unique identifier for the merchant.  For parallel payments, this field is required and must contain the Payer ID or the email address of the merchant.
        );

        /**
         * If checkout like regular payment
         */
        if (!empty($posted) && WC()->cart->needs_shipping()) {
            $SECFields['addroverride'] = 1;
            if (@$posted['ship_to_different_address']) {
                $Payment['shiptoname'] = $posted['shipping_first_name'] . ' ' . $posted['shipping_last_name'];
                $Payment['shiptostreet'] = $posted['shipping_address_1'];
                $Payment['shiptostreet2'] = @$posted['shipping_address_2'];
                $Payment['shiptocity'] = @$posted['shipping_city'];
                $Payment['shiptostate'] = @$posted['shipping_state'];
                $Payment['shiptozip'] = @$posted['shipping_postcode'];
                $Payment['shiptocountrycode'] = @$posted['shipping_country'];
                $Payment['shiptophonenum'] = @$posted['shipping_phone'];
            } else {
                $Payment['shiptoname'] = $posted['billing_first_name'] . ' ' . $posted['billing_last_name'];
                $Payment['shiptostreet'] = $posted['billing_address_1'];
                $Payment['shiptostreet2'] = @$posted['billing_address_2'];
                $Payment['shiptocity'] = @$posted['billing_city'];
                $Payment['shiptostate'] = @$posted['billing_state'];
                $Payment['shiptozip'] = @$posted['billing_postcode'];
                $Payment['shiptocountrycode'] = @$posted['billing_country'];
                $Payment['shiptophonenum'] = @$posted['billing_phone'];
            }
        }

        $PaymentOrderItems = array();
        $ctr = $total_items = $total_discount = $total_tax = $order_total = 0;
        foreach (WC()->cart->get_cart() as $cart_item_key => $values) {
            /*
             * Get product data from WooCommerce
             */
            $_product = $values['data'];
            $qty = absint($values['quantity']);
            $sku = $_product->get_sku();
            $values['name'] = html_entity_decode($_product->get_title(), ENT_NOQUOTES, 'UTF-8');

            /*
             * Append variation data to name.
             */
            if ($_product->product_type == 'variation') {

                $meta = WC()->cart->get_item_data($values, true);

                if (empty($sku)) {
                    $sku = $_product->parent->get_sku();
                }

                if (!empty($meta)) {
                    $values['name'] .= " - " . str_replace(", \n", " - ", $meta);
                }
            }

            $quantity = absint($values['quantity']);
            $Item = array(
                'name' => $values['name'], // Item name. 127 char max.
                'desc' => '', // Item description. 127 char max.
                'amt' => round($values['line_subtotal'] / $quantity, 2), // Cost of item.
                'number' => $sku, // Item number.  127 char max.
                'qty' => $quantity, // Item qty on order.  Any positive integer.
                'taxamt' => '', // Item sales tax
                'itemurl' => '', // URL for the item.
                'itemcategory' => '', // One of the following values:  Digital, Physical
                'itemweightvalue' => '', // The weight value of the item.
                'itemweightunit' => '', // The weight unit of the item.
                'itemheightvalue' => '', // The height value of the item.
                'itemheightunit' => '', // The height unit of the item.
                'itemwidthvalue' => '', // The width value of the item.
                'itemwidthunit' => '', // The width unit of the item.
                'itemlengthvalue' => '', // The length value of the item.
                'itemlengthunit' => '', // The length unit of the item.
                'ebayitemnumber' => '', // Auction item number.
                'ebayitemauctiontxnid' => '', // Auction transaction ID number.
                'ebayitemorderid' => '', // Auction order ID number.
                'ebayitemcartid' => ''      // The unique identifier provided by eBay for this order from the buyer. These parameters must be ordered sequentially beginning with 0 (for example L_EBAYITEMCARTID0, L_EBAYITEMCARTID1). Character length: 255 single-byte characters
            );
            array_push($PaymentOrderItems, $Item);

            $total_items += round($values['line_subtotal'] / $quantity, 2) * $quantity;
            $ctr++;
        }

        /**
         * Add custom Woo cart fees as line items
         */
        foreach (WC()->cart->get_fees() as $fee) {
            $Item = array(
                'name' => $fee->name, // Item name. 127 char max.
                'desc' => '', // Item description. 127 char max.
                'amt' => number_format($fee->amount, 2, '.', ''), // Cost of item.
                'number' => $fee->id, // Item number. 127 char max.
                'qty' => 1, // Item qty on order. Any positive integer.
                'taxamt' => '', // Item sales tax
                'itemurl' => '', // URL for the item.
                'itemcategory' => '', // One of the following values: Digital, Physical
                'itemweightvalue' => '', // The weight value of the item.
                'itemweightunit' => '', // The weight unit of the item.
                'itemheightvalue' => '', // The height value of the item.
                'itemheightunit' => '', // The height unit of the item.
                'itemwidthvalue' => '', // The width value of the item.
                'itemwidthunit' => '', // The width unit of the item.
                'itemlengthvalue' => '', // The length value of the item.
                'itemlengthunit' => '', // The length unit of the item.
                'ebayitemnumber' => '', // Auction item number.
                'ebayitemauctiontxnid' => '', // Auction transaction ID number.
                'ebayitemorderid' => '', // Auction order ID number.
                'ebayitemcartid' => '' // The unique identifier provided by eBay for this order from the buyer. These parameters must be ordered sequentially beginning with 0 (for example L_EBAYITEMCARTID0, L_EBAYITEMCARTID1). Character length: 255 single-byte characters
            );
            array_push($PaymentOrderItems, $Item);

            $total_items += $fee->amount * $Item['qty'];
            $ctr++;
        }

        /*
         * Get discount(s)
         */
        if (WC()->cart->get_cart_discount_total() > 0) {
            foreach (WC()->cart->get_coupons('cart') as $code => $coupon) {
                $Item = array(
                    'name' => 'Cart Discount',
                    'number' => $code,
                    'qty' => '1',
                    'amt' => '-' . number_format(WC()->cart->coupon_discount_amounts[$code], 2, '.', '')
                );
                array_push($PaymentOrderItems, $Item);
                $total_discount += number_format(WC()->cart->coupon_discount_amounts[$code], 2, '.', '');
            }
        }

        if (!$this->is_wc_version_greater_2_3()) {
            if (WC()->cart->get_order_discount_total() > 0) {
                foreach (WC()->cart->get_coupons('order') as $code => $coupon) {
                    $Item = array(
                        'name' => 'Order Discount',
                        'number' => $code,
                        'qty' => '1',
                        'amt' => '-' . number_format(WC()->cart->coupon_discount_amounts[$code], 2, '.', '')
                    );
                    array_push($PaymentOrderItems, $Item);
                    $total_discount += number_format(WC()->cart->coupon_discount_amounts[$code], 2, '.', '');
                }
            }
        }


        if (isset($total_discount)) {
            $total_discount = round($total_discount, 2);
        }

        if ($this->send_items) {
            /*
             * Now that all the order items are gathered, including discounts,
             * we'll push them back into the Payment.
             */
            $Payment['order_items'] = $PaymentOrderItems;

            /*
             * Now that we've looped and calculated item totals
             * we can fill in the ITEMAMT
             */
            $Payment['itemamt'] = number_format($total_items - $total_discount, 2, '.', '');
        } else {
            $Payment['order_items'] = array();

            /*
             * Now that we've looped and calculated item totals
             * we can fill in the ITEMAMT
             */
            $Payment['itemamt'] = number_format($total_items - $total_discount, 2, '.', '');
        }

        /*
         * Then we load the payment into the $Payments array
         */
        array_push($Payments, $Payment);

        $BuyerDetails = array(
            'buyerid' => '', // The unique identifier provided by eBay for this buyer.  The value may or may not be the same as the username.  In the case of eBay, it is different.  Char max 255.
            'buyerusername' => '', // The username of the marketplace site.
            'buyerregistrationdate' => '' // The registration of the buyer with the marketplace.
        );

        // For shipping options we create an array of all shipping choices similar to how order items works.
        $ShippingOptions = array();
        $Option = array(
            'l_shippingoptionisdefault' => '', // Shipping option.  Required if specifying the Callback URL.  true or false.  Must be only 1 default!
            'l_shippingoptionname' => '', // Shipping option name.  Required if specifying the Callback URL.  50 character max.
            'l_shippingoptionlabel' => '', // Shipping option label.  Required if specifying the Callback URL.  50 character max.
            'l_shippingoptionamount' => ''      // Shipping option amount.  Required if specifying the Callback URL.
        );
        array_push($ShippingOptions, $Option);

        $BillingAgreements = array();
        $Item = array(
            'l_billingtype' => '', // Required.  Type of billing agreement.  For recurring payments it must be RecurringPayments.  You can specify up to ten billing agreements.  For reference transactions, this field must be either:  MerchantInitiatedBilling, or MerchantInitiatedBillingSingleSource
            'l_billingagreementdescription' => '', // Required for recurring payments.  Description of goods or services associated with the billing agreement.
            'l_paymenttype' => '', // Specifies the type of PayPal payment you require for the billing agreement.  Any or IntantOnly
            'l_billingagreementcustom' => ''     // Custom annotation field for your own use.  256 char max.
        );

        array_push($BillingAgreements, $Item);

        $PayPalRequestData = array(
            'SurveyChoices' => $SurveyChoices,
            'Payments' => $Payments,
                //'BuyerDetails' => $BuyerDetails,
                //'ShippingOptions' => $ShippingOptions,
                //'BillingAgreements' => $BillingAgreements
        );

        // Rounding amendment

        if (trim(number_format(WC()->cart->total, 2, '.', '')) !== trim(number_format($total_items - $total_discount + $tax + $shipping, 2, '.', ''))) {
            $diffrence_amount = $this->get_diffrent(WC()->cart->total, $total_items - $total_discount + $tax + $shipping);
            if ($shipping > 0) {
                $PayPalRequestData['Payments'][0]['shippingamt'] = round($shipping + $diffrence_amount, 2);
            } elseif ($tax > 0) {
                $PayPalRequestData['Payments'][0]['taxamt'] = round($tax + $diffrence_amount, 2);
            } else {
                $PayPalRequestData['Payments'][0]['itemamt'] = round($PayPalRequestData['Payments'][0]['itemamt'] + $diffrence_amount, 2);
            }
        }

        return $PayPalRequestData;
    }

    /**
     * Check if this gateway is enabled and available in the user's country
     * @access public
     * @return boolean
     */
    public function is_available() {
        //if enabled checkbox is checked
        if ($this->enabled == 'yes')
            return true;
        return false;
    }

    /**
     * Admin Panel Options
     * - Settings
     *
     * @access public
     * @return void
     */
    public function admin_options() {
        ?>
        <h3><?php _e('PayPal Plus', 'paypal-for-woocommerce'); ?></h3>
        <p><?php _e('PayPal Payments Plus uses an iframe to seamlessly integrate PayPal hosted pages into the checkout process.', 'paypal-for-woocommerce'); ?></p>
        <table class="form-table">
        <?php
        //if user's currency is USD
        if (!in_array(get_woocommerce_currency(), array('EUR', 'CAD'))) {
            ?>
                <div class="inline error"><p><strong><?php _e('Gateway Disabled', 'paypal-for-woocommerce'); ?></strong>: <?php _e('PayPal does not support your store currency.', 'paypal-for-woocommerce'); ?></p></div>
            <?php
            return;
        } else {
            // Generate the HTML For the settings form.
            $this->generate_settings_html();
        }
        ?>
        </table><!--/.form-table-->
            <?php
        }

// End admin_options()
        /**
         * Initialise Gateway Settings Form Fields
         *
         * @access public
         * @return void
         */

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'paypal-for-woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable PayPal Plus', 'paypal-for-woocommerce'),
                    'default' => 'yes'
                ),
                'title' => array(
                    'title' => __('Title', 'paypal-for-woocommerce'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'paypal-for-woocommerce'),
                    'default' => __('PayPal Plus', 'paypal-for-woocommerce')
                ),
                'description' => array(
                    'title' => __('Description', 'paypal-for-woocommerce'),
                    'type' => 'text',
                    'description' => __('This controls the description which the user sees during checkout.', 'paypal-for-woocommerce'),
                    'default' => __('PayPal Plus description', 'paypal-for-woocommerce')
                ),
                'rest_client_id' => array(
                    'title' => __('Client ID', 'paypal-for-woocommerce'),
                    'type' => 'password',
                    'description' => 'Enter your PayPal Rest API Client ID',
                    'default' => ''
                ),
                'rest_secret_id' => array(
                    'title' => __('Secret ID', 'paypal-for-woocommerce'),
                    'type' => 'password',
                    'description' => __('Enter your PayPal Rest API Secret ID.', 'paypal-for-woocommerce'),
                    'default' => ''
                ),
                'testmode' => array(
                    'title' => __('PayPal sandbox', 'paypal-for-woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable PayPal sandbox', 'paypal-for-woocommerce'),
                    'default' => 'yes',
                    'description' => sprintf(__('PayPal sandbox can be used to test payments. Sign up for a developer account <a href="%s">here</a>', 'paypal-for-woocommerce'), 'https://developer.paypal.com/'),
                ),
                'billing_address' => array(
                    'title' => __('Billing Address', 'paypal-for-woocommerce'),
                    'label' => __('Set billing address in WooCommerce using the address returned by PayPal.', 'paypal-for-woocommerce'),
                    'description' => __('PayPal only returns a shipping address back to the website.  Enable this option if you would like to use this address for both billing and shipping in WooCommerce.'),
                    'type' => 'checkbox',
                    'default' => 'no'
                ),
                'invoice_prefix' => array(
                    'title' => __('Invoice Prefix', 'paypal-for-woocommerce'),
                    'type' => 'text',
                    'description' => __('Please enter a prefix for your invoice numbers. If you use your PayPal account for multiple stores ensure this prefix is unique as PayPal will not allow orders with the same invoice number.', 'woocommerce'),
                    'default' => 'WC-PPADV',
                    'desc_tip' => true,
                ),
                'debug' => array(
                    'title' => __('Debug Log', 'paypal-for-woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Enable logging', 'paypal-for-woocommerce'),
                    'default' => 'no',
                    'description' => __('Log PayPal events, such as Secured Token requests, inside <code>woocommerce/logs/paypal_plus.txt</code>', 'paypal-for-woocommerce'),
                )
            );
        }

// End init_form_fields()
        /**
         * There are no payment fields for paypal, but we want to show the description if set.
         *
         * @access public
         * @return void
         * */

        public function payment_fields() {
            if ($this->description)
                echo wpautop(wptexturize($this->description));
        }

        /**
         * Process the payment
         *
         * @access public
         * @return void
         * */
        public function process_payment($order_id) {
            //create the order object
            $order = new WC_Order($order_id);

            //use try/catch blocks to handle exceptions while processing the payment
            try {



                if (isset($_POST['createaccount'])) {
                    $this->customer_id = apply_filters('woocommerce_checkout_customer_id', get_current_user_id());
                    if (empty($_POST['username'])) {
                        //  wc_add_notice(__('Username is required', 'paypal-for-woocommerce'), 'error');
                    } elseif (username_exists($_POST['username'])) {
                        //   wc_add_notice(__('This username is already registered.', 'paypal-for-woocommerce'), 'error');
                    } elseif (empty($_POST['email'])) {
                        //  wc_add_notice(__('Please provide a valid email address.', 'paypal-for-woocommerce'), 'error');
                    } elseif (empty($_POST['password']) || empty($_POST['repassword'])) {
                        //  wc_add_notice(__('Password is required.', 'paypal-for-woocommerce'), 'error');
                    } elseif ($_POST['password'] != $_POST['repassword']) {
                        //  wc_add_notice(__('Passwords do not match.', 'paypal-for-woocommerce'), 'error');
                    } elseif (get_user_by('email', $_POST['email']) != false) {
                        //   wc_add_notice(__('This email address is already registered.', 'paypal-for-woocommerce'), 'error');
                    } else {

                        $username = !empty($_POST['username']) ? $_POST['username'] : '';
                        $password = !empty($_POST['password']) ? $_POST['password'] : '';
                        $email = $_POST['email'];
                        $this->customer_id = $user->ID;

                        // As we are now logged in, checkout will need to refresh to show logged in data
                        WC()->session->set('reload_checkout', true);

                        // Also, recalculate cart totals to reveal any role-based discounts that were unavailable before registering
                        WC()->cart->calculate_totals();

                        require_once("lib/NameParser.php");
                        $parser = new FullNameParser();
                        $split_name = $parser->split_full_name($result['SHIPTONAME']);
                        $shipping_first_name = $split_name['fname'];
                        $shipping_last_name = $split_name['lname'];
                        $full_name = $split_name['fullname'];

                        // Add customer info from other billing fields
                        //  if (isset($result)) {
                        update_user_meta($this->customer_id, 'first_name', isset($result['FIRSTNAME']) ? $result['FIRSTNAME'] : '');
                        update_user_meta($this->customer_id, 'last_name', isset($result['LASTNAME']) ? $result['LASTNAME'] : '');
                        update_user_meta($this->customer_id, 'shipping_first_name', $shipping_first_name);
                        update_user_meta($this->customer_id, 'shipping_last_name', $shipping_last_name);
                        update_user_meta($this->customer_id, 'shipping_company', isset($result['BUSINESS']) ? $result['BUSINESS'] : '' );
                        update_user_meta($this->customer_id, 'shipping_address_1', isset($result['SHIPTOSTREET']) ? $result['SHIPTOSTREET'] : '');
                        update_user_meta($this->customer_id, 'shipping_address_2', isset($result['SHIPTOSTREET2']) ? $result['SHIPTOSTREET2'] : '');
                        update_user_meta($this->customer_id, 'shipping_city', isset($result['SHIPTOCITY']) ? $result['SHIPTOCITY'] : '' );
                        update_user_meta($this->customer_id, 'shipping_postcode', isset($result['SHIPTOZIP']) ? $result['SHIPTOZIP'] : '');
                        update_user_meta($this->customer_id, 'shipping_country', isset($result['SHIPTOCOUNTRYCODE']) ? $result['SHIPTOCOUNTRYCODE'] : '');
                        update_user_meta($this->customer_id, 'shipping_state', isset($result['SHIPTOSTATE']) ? $result['SHIPTOSTATE'] : '' );
                        $user_submit_form = maybe_unserialize(WC()->session->checkout_form);
                        if ((isset($user_submit_form) && !empty($user_submit_form) && is_array($user_submit_form))) {
                            update_user_meta($this->customer_id, 'billing_first_name', isset($user_submit_form['billing_first_name']) ? $user_submit_form['billing_first_name'] : $result['FIRSTNAME']);
                            update_user_meta($this->customer_id, 'billing_last_name', isset($user_submit_form['billing_last_name']) ? $user_submit_form['billing_last_name'] : $result['LASTNAME']);
                            update_user_meta($this->customer_id, 'billing_address_1', isset($user_submit_form['billing_address_1']) ? $user_submit_form['billing_address_1'] : $result['SHIPTOSTREET']);
                            update_user_meta($this->customer_id, 'billing_address_2', isset($user_submit_form['billing_address_2']) ? $user_submit_form['billing_address_2'] : $result['SHIPTOSTREET2']);
                            update_user_meta($this->customer_id, 'billing_city', isset($user_submit_form['billing_city']) ? $user_submit_form['billing_city'] : $result['SHIPTOCITY']);
                            update_user_meta($this->customer_id, 'billing_postcode', isset($user_submit_form['billing_postcode']) ? $user_submit_form['billing_postcode'] : $result['SHIPTOZIP']);
                            update_user_meta($this->customer_id, 'billing_country', isset($user_submit_form['billing_country']) ? $user_submit_form['billing_country'] : $result['SHIPTOCOUNTRYCODE']);
                            update_user_meta($this->customer_id, 'billing_state', isset($user_submit_form['billing_state']) ? $user_submit_form['billing_state'] : $result['SHIPTOSTATE']);
                            update_user_meta($this->customer_id, 'billing_phone', isset($user_submit_form['billing_phone']) ? $user_submit_form['billing_phone'] : $result['PHONENUM']);
                            update_user_meta($this->customer_id, 'billing_email', isset($user_submit_form['billing_email']) ? $user_submit_form['billing_email'] : $result['EMAIL']);
                        } else {
                            update_user_meta($this->customer_id, 'billing_first_name', $shipping_first_name);
                            update_user_meta($this->customer_id, 'billing_last_name', $shipping_last_name);
                            update_user_meta($this->customer_id, 'billing_address_1', isset($result['SHIPTOSTREET']) ? $result['SHIPTOSTREET'] : '');
                            update_user_meta($this->customer_id, 'billing_address_2', isset($result['SHIPTOSTREET2']) ? $result['SHIPTOSTREET2'] : '');
                            update_user_meta($this->customer_id, 'billing_city', isset($result['SHIPTOCITY']) ? $result['SHIPTOCITY'] : '');
                            update_user_meta($this->customer_id, 'billing_postcode', isset($result['SHIPTOZIP']) ? $result['SHIPTOZIP'] : '');
                            update_user_meta($this->customer_id, 'billing_country', isset($result['SHIPTOCOUNTRYCODE']) ? $result['SHIPTOCOUNTRYCODE'] : '');
                            update_user_meta($this->customer_id, 'billing_state', isset($result['SHIPTOSTATE']) ? $result['SHIPTOSTATE'] : '');
                            update_user_meta($this->customer_id, 'billing_phone', isset($result['PHONENUM']) ? $result['PHONENUM'] : '');
                            update_user_meta($this->customer_id, 'billing_email', isset($result['EMAIL']) ? $result['EMAIL'] : '');
                        }
                        //}
                    }
                }


                $this->approvalurl = $this->get_approvalurl($order_id);

                //if valid securetoken
                if ($this->approvalurl != "") {
                    $user_submit_form = maybe_unserialize(WC()->session->customer);

                    //Log
                    if ($this->debug == 'yes')
                        $this->log->add('paypal_plus', sprintf(__('Secured Token generated successfully for the order %s', 'paypal-for-woocommerce'), $order->get_order_number()));



                    //redirect to pay
                    return array(
                        'result' => 'success',
                        'redirect' => $order->get_checkout_payment_url(true)
                    );
                }
            } catch (Exception $e) {

                //add error
                wc_add_notice(__('Error:', 'paypal-for-woocommerce') . ' "' . $e->getMessage() . '"', 'error');

                //Log
                if ($this->debug == 'yes')
                    $this->log->add('paypal_advanced', 'Error Occurred while processing the order ' . $order_id);
            }
            return;
        }

        /**
         * Displays IFRAME/Redirect to show the hosted page in Paypal
         *
         * @access public
         * @return void
         * */
        public function receipt_page($order_id) {
            //get the mode
            $PF_MODE = $this->settings['testmode'] == 'yes' ? 'TEST' : 'LIVE';
            //create order object
            $order = new WC_Order($order_id);
            //get the tokens
            //Log the browser and its version
            if ($this->debug == 'yes')
                $this->log->add('paypal_plus', sprintf(__('Browser Info: %s', 'paypal-for-woocommerce'), $_SERVER['HTTP_USER_AGENT']));
            //display the form in IFRAME, if it is layout C, otherwise redirect to paypal site
            //define the redirection url
            $location = $this->get_approvalurl($order_id);
            //$result = execute_payment($_SESSION["payment_id"], $_GET["PayerID"]);
            //Log
            if ($this->debug == 'yes')
                $this->log->add('paypal_plus', sprintf(__('Show payment form redirecting to ' . $location . ' for the order %s as it is not configured to use Layout C', 'paypal-for-woocommerce'), $order->get_order_number()));
            //redirect
            ?>
        <script src="https://www.paypalobjects.com/webstatic/ppplus/ppplus.min.js"type="text/javascript"></script>

        <div id="ppplus"> </div>

        <script type="application/javascript">
            var ppp = PAYPAL.apps.PPP({
            "approvalUrl": "<?php echo $location; ?>",
            "placeholder": "ppplus",
            "useraction": "commit",
            "onLoad" : "callback",
            "mode": "sandbox",
            });
        </script>

        <?php
        exit;
    }

    /**
     * Limit the length of item names
     * @param  string $item_name
     * @return string
     */
    public function paypal_plus_item_name($item_name) {
        if (strlen($item_name) > 36) {
            $item_name = substr($item_name, 0, 33) . '...';
        }
        return html_entity_decode($item_name, ENT_NOQUOTES, 'UTF-8');
    }

    /**
     * Limit the length of item desc
     * @param  string $item_desc
     * @return string
     */
    public function paypal_plus_item_desc($item_desc) {
        if (strlen($item_desc) > 127) {
            $item_desc = substr($item_desc, 0, 124) . '...';
        }
        return html_entity_decode($item_desc, ENT_NOQUOTES, 'UTF-8');
    }

    ////////////////////////////////////////////////////////////////////////////////
    function add_log($message) {
        if (empty($this->log))
            $this->log = new WC_Logger();
        $this->log->add('paypal_plus', $message);
    }

    public function is_wc_version_greater_2_3() {
        return $this->get_wc_version() && version_compare($this->get_wc_version(), '2.3', '>=');
    }

    public function get_wc_version() {
        return defined('WC_VERSION') && WC_VERSION ? WC_VERSION : null;
    }

    function get_diffrent($amout_1, $amount_2) {
        $diff_amount = $amout_1 - $amount_2;
        return $diff_amount;
    }

    function cut_off($number) {
        $parts = explode(".", $number);
        $newnumber = $parts[0] . "." . $parts[1][0] . $parts[1][1];
        return $newnumber;
    }

    public function executepay($payment_args) {

        if (isset($_SESSION['payment_args']['token']) && isset($_SESSION['payment_args']['PayerID']) && isset($_SESSION['payment_args']['paymentId'])) {
            $const_client_id = $this->settings['rest_client_id'];
            $const_secret_id = $this->settings['rest_secret_id'];

            define('CLIENT_ID', $const_client_id); //your PayPal client ID
            define('CLIENT_SECRET', $const_secret_id); //PayPal Secret


            include_once __DIR__ . "/vendor/autoload.php"; //include PayPal SDK
            if (!defined("PP_CONFIG_PATH")) {
                define("PP_CONFIG_PATH", __DIR__);
            }
            include_once(__DIR__ . '/functions.inc.php'); //our PayPal functions
            $result = execute_payment($_SESSION['payment_args']['paymentId'], $_SESSION['payment_args']['PayerID']);  //call execute payment function.

            if (isset($order_id) && !empty($order_id)) {
                $order = new WC_Order($order_id);
            }
            if ($result->state == "approved") { //if state = approved continue..
                global $wpdb;

                $my_post = array(
                    'ID' => $_GET['pp_action'],
                    'post_status' => 'wc-processing',);

// Update the post into the database
                wp_update_post($my_post);

                if ($this->billing_address == 'yes') {
                    require_once("lib/NameParser.php");
                    $parser = new FullNameParser();
                    $split_name = $parser->split_full_name($result->payer->payer_info->shipping_address->recipient_name);
                    $shipping_first_name = $split_name['fname'];
                    $shipping_last_name = $split_name['lname'];
                    $full_name = $split_name['fullname'];

                    update_post_meta($_GET['pp_action'], '_billing_first_name', $shipping_first_name);
                    update_post_meta($_GET['pp_action'], '_billing_last_name', $shipping_last_name);
                    update_post_meta($_GET['pp_action'], '_billing_full_name', $full_name);
                    //update_post_meta( $_GET['pp_action'], '_billing_company',   $this->get_session('company') );
                    update_post_meta($_GET['pp_action'], '_billing_address_1', $result->payer->payer_info->shipping_address->line1);
                    update_post_meta($_GET['pp_action'], '_billing_address_2', $result->payer->payer_info->shipping_address->line2);
                    update_post_meta($_GET['pp_action'], '_billing_city', $result->payer->payer_info->shipping_address->city);
                    update_post_meta($_GET['pp_action'], '_billing_postcode', $result->payer->payer_info->shipping_address->postal_code);
                    update_post_meta($_GET['pp_action'], '_billing_country', $result->payer->payer_info->shipping_address->country_code);
                    update_post_meta($_GET['pp_action'], '_billing_state', $result->payer->payer_info->shipping_address->state);
                }

                $key = get_post_meta($_GET['pp_action'], '_order_key', true);
                //$order->get_checkout_order_received_url();
                global $woocommerce;
                $checkout_url = $woocommerce->cart->get_checkout_url() . 'order-received/' . $_GET['pp_action'];
                $received_url = $checkout_url . "/?key=" . $key;
                //redirect to the checkout page
                $this->redirect_to($received_url);
            }
        }
    }

}