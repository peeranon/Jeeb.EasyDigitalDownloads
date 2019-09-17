<?php
/**
 * Plugin Name:     Easy Digital Downloads - Jeeb Payment Gateway
 * Plugin URI:      https://github.com/Jeebio/Jeeb.EasyDigitalDownloads
 * Description:     The first Iranian platform for accepting and processing cryptocurrencies payments.
 * Version:         3.0
 * Author:          Jeeb
 * Author URI:      https://jeeb.io
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function EDD_Jeeb_checkCurl()
{
    return function_exists('curl_version');
}

// Add custom javascript
add_action('admin_enqueue_scripts', 'admin_scripts', 999);

function admin_scripts()
{
    if (is_admin()) {
        wp_enqueue_style('jeeb_admin_style', plugins_url('admin.css', __FILE__));
        wp_enqueue_script('jeeb_admin_script', plugins_url('admin.js', __FILE__), array('jquery'), '1.0', true);
    }
}

function jeeb_payment_gateway_plugin_setup()
{
    global $wpdb;
    $errors = array();

    if (!EDD_Jeeb_checkCurl()) {
        $errors[] = 'cUrl needs to be installed/enabled for Jeeb plugin for Easy Digital Downloads to function';
    }

    if (empty($errors) == false) {
        $plugins_url = admin_url('plugins.php');
        wp_die($errors[0] . '<br><a href="' . $plugins_url . '">Return to plugins screen</a>');
    }
}

register_activation_hook(__FILE__, 'jeeb_payment_gateway_plugin_setup');

if (!class_exists('EDD_Jeeb_Payment_Gateway')) {
    class EDD_Jeeb_Payment_Gateway
    {

        const PLUGIN_NAME = 'easydigitaldownloads';
        const PLUGIN_VERSION = '3.0';
        const BASE_URL = "https://core.jeeb.io/api/";

        private static $instance;
        /**
         * Get active instance
         *
         * @since       1.0.0
         * @access      public
         * @static
         * @return      object self::$instance
         */
        public static function get_instance()
        {
            if (!self::$instance) {
                self::$instance = new EDD_Jeeb_Payment_Gateway();
            }

            return self::$instance;
        }

        /**
         * Class constructor
         *
         * @since       1.0.0
         * @access      public
         * @return      void
         */
        public function __construct()
        {
            // Plugin dir
            define('EDD_JEEB_DIR', plugin_dir_path(__FILE__));
            // Plugin URL
            define('EDD_JEEB_URL', plugin_dir_url(__FILE__));
            $this->init();
        }

        /**
         * Run action and filter hooks
         *
         * @since       1.0.0
         * @access      private
         * @return      void
         */
        private function init()
        {
            // Make sure EDD is active
            if (!class_exists('Easy_Digital_Downloads')) {
                return;
            }

            global $edd_options;

            // Add the gateway
            add_filter('edd_payment_gateways', array($this, 'register_gateway'));
            // Remove CC form
            add_action('edd_jeeb_cc_form', '__return_false');
            // Adding Jeeb to Payment Tab
            add_filter('edd_settings_sections_gateways', array($this, 'jeeb_checkout_edd_register_gateway_section'));
            // Adding custom Icon
            add_filter('edd_accepted_payment_icons', array($this, 'jeeb_edd_payment_icon'));
            // Register settings
            add_filter('edd_settings_gateways', array($this, 'settings'), 1);

            // Process payment
            add_action('edd_gateway_jeeb', array($this, 'process_payment'));
            add_action('init', array($this, 'edd_listen_for_jeeb_ipn'));
            add_action('edd_verify_jeeb_ipn', array($this, 'process_webhook'));
            add_action('edd_process_jeeb_payment', array($this, 'process_jeeb_payment'), 10, 2);

        }

        public function jeeb_checkout_edd_register_gateway_section($gateway_sections)
        {
            $gateway_sections['jeeb_checkout_edd'] = 'Jeeb';
            return $gateway_sections;
        }

        public function jeeb_edd_payment_icon($icons)
        {
            global $edd_options;
            if (isset($edd_options["edd_jeeb_btnurl"]) === false) {
                $url = "https://jeeb.io/cdn/en/blue-white-jeeb.svg";
            } else {
                $url = $edd_options["edd_jeeb_btnurl"];
            }

            $url = str_replace('svg', 'png', $url);

            $icons[$url] = 'Jeeb';
            return $icons;
        }

        /**
         * Add settings
         *
         * @since       1.0.0
         * @access      public
         * @param       array $settings The existing plugin settings
         * @return      array
         */
        public function settings($gateway_settings)
        {
            global $edd_options;
            $url = trailingslashit(home_url());

            $jeeb_edd_settings = array(
                array(
                    'id' => 'edd_jeeb_settings',
                    'name' => '<h3><span><img class="jeeb-logo" src="https://jeeb.io/cdn/en/trans-blue-jeeb.svg"></img</span> Settings</h3>',
                    'desc' => '<p>The first Iranian platform for accepting and processing cryptocurrencies payments.</p>',
                    'type' => 'header',
                ),

                array(
                    'id' => 'edd_jeeb_signature',
                    'name' => 'Signature',
                    'desc' => '<br/>The signature provided by Jeeb for you merchant.',
                    'type' => 'text',
                ),

                array(
                    'id' => 'edd_jeeb_basecoin',
                    'name' => 'Base Currency',
                    'desc' => 'The base currency of your website.',
                    'type' => 'select',
                    'options' => array(
                        'btc' => 'BTC (Bitcoin)',
                        'usd' => 'USD (US Dollar)',
                        'eur' => 'EUR (Euro)',
                        'irr' => 'IRR (Iranian Rial)',
                        'toman' => 'TOMAN (Iranian Toman)',
                    ),
                ),

                array(
                    'id' => 'edd_jeeb_btc',
                    'name' => 'Payable Currencies',
                    'desc' => 'BTC (Bitcoin)',
                    'type' => 'checkbox',
                    'default' => 'yes',
                ),

                array(
                    'id' => 'edd_jeeb_ltc',
                    'desc' => 'LTC (Litecoin)',
                    'type' => 'checkbox',
                    'default' => 'yes',
                ),

                array(
                    'id' => 'edd_jeeb_bch',
                    'desc' => 'BCH (Bitcoin Cash)',
                    'type' => 'checkbox',
                    'default' => 'no',
                ),

                array(
                    'id' => 'edd_jeeb_eth',
                    'desc' => 'ETH (Ethereum)',
                    'type' => 'checkbox',
                    'default' => 'no',
                ),

                array(
                    'id' => 'edd_jeeb_xrp',
                    'desc' => 'XRP (Ripple)',
                    'type' => 'checkbox',
                    'default' => 'no',
                ),

                array(
                    'id' => 'edd_jeeb_xmr',
                    'desc' => 'XMR (Monero)',
                    'type' => 'checkbox',
                    'default' => 'no',
                ),

                array(
                    'id' => 'edd_jeeb_tbtc',
                    'desc' => 'TBTC (Bitcoin TESTNET)',
                    'type' => 'checkbox',
                    'default' => 'no',
                ),

                array(
                    'id' => 'edd_jeeb_tltc',
                    'desc' => 'TLTC (Litecoin TESTNET)',
                    'type' => 'checkbox',
                    'default' => 'no',
                ),

                array(
                    'id' => 'edd_jeeb_lang',
                    'name' => 'Language',
                    'desc' => '<br/>The language of the payment area.',
                    'type' => 'select',
                    'options' => array(
                        'none' => 'Auto',
                        'en' => 'English',
                        'fa' => 'Persian',
                    ),
                ),

                array(
                    'id' => 'edd_jeeb_allow_refund',
                    'name' => 'Allow Refund',
                    'desc' => 'Allows payments to be refunded.',
                    'type' => 'checkbox',
                    'default' => 'no',
                ),

                array(
                    'id' => 'edd_jeeb_test',
                    'name' => 'Allow TestNets',
                    'desc' => 'Allows testnets such as TBTC to get processed.',
                    'type' => 'checkbox',
                    'default' => 'no',
                ),

                array(
                    'id' => 'edd_jeeb_expiration',
                    'name' => 'Expiration Time',
                    'desc' => '<br/>Expands default payments expiration time. It should be between 15 to 2880 (mins).',
                    'type' => 'text',
                ),

                array(
                    'id' => 'edd_jeeb_callback_url',
                    'name' => 'Return Url',
                    'desc' => '<br/>Enter the URL to which you want the user to return after the payment.',
                    'type' => 'text',
                    'default' => $url,
                ),

                array(
                    'id' => 'edd_jeeb_btnlang',
                    'name' => 'Checkout Button Language',
                    'desc' => '<br/>Jeeb\'s checkout button preferred language.',
                    'type' => 'select',
                    'options' => array(
                        'en' => 'English',
                        'fa' => 'Persian',
                    ),
                ),

                array(
                    'id' => 'edd_jeeb_btntheme',
                    'name' => 'Checkout Button Theme',
                    'desc' => '<br/>Jeeb\'s checkout button preferred theme.',
                    'type' => 'select',
                    'options' => array(
                        'blue' => 'Blue',
                        'transparent' => 'Transparent',
                    ),
                ),

                array(
                    'id' => 'edd_jeeb_btnurl',
                    'name' => 'Checkout Button',
                    'type' => 'text',
                ),
            );
            $jeeb_edd_settings = apply_filters('edd_bp_checkout_settings', $jeeb_edd_settings);
            $gateway_settings['jeeb_checkout_edd'] = $jeeb_edd_settings;
            return $gateway_settings;
        }

        /**
         * Register our new gateway
         *
         * @since       1.0.0
         * @access      public
         * @param       array $gateways The current gateway list
         * @return      array $gateways The updated gateway list
         */
        public function register_gateway($gateways)
        {
            $gateways['jeeb'] = array(
                'admin_label' => 'Jeeb',
                'checkout_label' => 'Jeeb - Pay securely with bitcoins through Jeeb Payment Gateway.',
            );
            return $gateways;
        }
        /**
         * Process payment submission
         *
         * @since       1.0.0
         * @access      public
         * @global      array $edd_options
         * @param       array $purchase_data The data for a specific purchase
         * @return      void
         */
        public function process_payment($purchase_data)
        {
            global $edd_options;
            // Collect payment data
            $payment_data = array(
                'price' => $purchase_data['price'],
                'date' => $purchase_data['date'],
                'user_email' => $purchase_data['user_email'],
                'purchase_key' => $purchase_data['purchase_key'],
                'currency' => edd_get_currency(),
                'downloads' => $purchase_data['downloads'],
                'user_info' => $purchase_data['user_info'],
                'cart_details' => $purchase_data['cart_details'],
                'gateway' => 'jeeb',
                'status' => 'pending',
            );
            // Record the pending payment
            $payment = edd_insert_payment($payment_data);
            // Were there any errors?
            if (!$payment) {
                // Record the error
                edd_record_gateway_error('Payment Error', sprintf('Payment creation failed before sending buyer to Jeeb. Payment data: %s', json_encode($payment_data)), $payment);
                edd_send_back_to_checkout('?payment-mode=' . $purchase_data['post_data']['edd-gateway']);
            } else {
                do_action('edd_process_jeeb_payment', $purchase_data, $payment);
            }
        }

        public function process_jeeb_payment($purchase_data, $payment)
        {
            global $edd_options;
            $signature = $edd_options['edd_jeeb_signature'];
            $webhook_url = trailingslashit(home_url()) . '?edd-listener=JEEBIPN';
            $callback_url = $edd_options['edd_jeeb_callback_url'];
            $order_total = round($purchase_data['price'] - $purchase_data['tax'], 8);
            $base_currency = $edd_options['edd_jeeb_basecoin'];
            $target_cur = "";

            if (isset($edd_options['edd_jeeb_expiration']) === false ||
                is_numeric($edd_options['edd_jeeb_expiration']) === false ||
                $edd_options['edd_jeeb_expiration'] < 15 ||
                $edd_options['edd_jeeb_expiration'] > 2880) {
                $edd_options['edd_jeeb_expiration'] = 15;
            }

            $params = array(
                'btc',
                'ltc',
                'bch',
                'eth',
                'xrp',
                'xmr',
                'tbtc',
                'tltc',
            );

            foreach ($params as $p) {
                isset($edd_options["edd_jeeb_" . $p]) ? $target_cur .= $p . "/" : $target_cur .= "";
            }

            if ($base_currency == 'toman') {
                $base_currency = 'irr';
                $order_total *= 10;
            }

            $amount = $this->convert_base_to_bitcoin($base_currency, $order_total);

            $data = array(
                "orderNo" => $payment,
                "value" => $amount,
                "coins" => $target_cur,
                "webhookUrl" => $webhook_url,
                "callbackUrl" => $callback_url,
                "expiration" => $edd_options['edd_jeeb_expiration'],
                "allowReject" => $edd_options['edd_jeeb_allow_refund'] == '1' ? true : false,
                "allowTestNet" => $edd_options['edd_jeeb_test'] == '1' ? true : false,
                "language" => $edd_options['edd_jeeb_lang'] == 'none' ? null : $edd_options['edd_jeeb_lang'],
            );

            $token = $this->create_payment($signature, $data);

            // edd_empty_cart(); TODO: fix this issue

            $this->redirect_payment($token);
        }

        /**
         * Listens for a Jeeb IPN requests and then sends to the processing function
         *
         * @since       1.0.0
         * @access      public
         * @global      array $edd_options
         * @return      void
         */
        public function edd_listen_for_jeeb_ipn()
        {
            global $edd_options;
            if (isset($_GET['edd-listener']) && $_GET['edd-listener'] == 'JEEBIPN') {
                do_action('edd_verify_jeeb_ipn');
            }
        }
        /**
         * Process Jeeb IPN
         *
         * @since       1.0.0
         * @access      public
         * @global      array $edd_options
         * @return void
         */
        public function process_webhook()
        {
            global $edd_options;
            $postdata = file_get_contents("php://input");
            $json = json_decode($postdata, true);
            $payment_id = $json['orderNo'];
            $signature = $edd_options['edd_jeeb_signature'];
            if ($json['signature'] == $signature) {
                if ($json['stateId'] == 2) {
                    $int = edd_insert_payment_note($payment_id, 'Jeeb: Pending transaction.');
                    $result = edd_update_payment_status($payment_id, 'pending');
                } else if ($json['stateId'] == 3) {
                    $int = edd_insert_payment_note($payment_id, 'Jeeb: Pending confirmation.');
                    $result = edd_update_payment_status($payment_id, 'pending');
                    edd_set_payment_transaction_id( $payment_id, $json['referenceNo'] );
                } else if ($json['stateId'] == 4) {
                    $data = array(
                        "token" => $json["token"],
                    );

                    $is_confirmed = $this->confirm_payment($signature, $data);

                    if ($is_confirmed) {
                        $int = edd_insert_payment_note($payment_id, 'Jeeb: Merchant confirmation obtained. Payment is completed.');
                        $result = edd_update_payment_status($payment_id, 'complete');
                    } else {
                        $int = edd_insert_payment_note($payment_id, 'Jeeb: Double spending avoided.');
                        $result = edd_update_payment_status($payment_id, 'failed');
                    }
                } else if ($json['stateId'] == 5) {
                    $int = edd_insert_payment_note($payment_id, 'Jeeb: Payment is expired or canceled.');
                    $result = edd_update_payment_status($payment_id, 'failed');
                } else if ($json['stateId'] == 6) {
                    $int = edd_insert_payment_note($payment_id, 'Jeeb: Partial-paid payment occurred, transaction was refunded automatically.');
                    $result = edd_update_payment_status($payment_id, 'refunded');
                } else if ($json['stateId'] == 7) {
                    $int = edd_insert_payment_note($payment_id, 'Jeeb: Overpaid payment occurred, transaction was refunded automatically.');
                    $result = edd_update_payment_status($payment_id, 'refunded');
                } else {
                    $result = edd_insert_payment_note($payment_id, 'Jeeb: Unknown state received. Please report this incident.');
                }
                header("HTTP/1.1 200 OK");
            }
            header("HTTP/1.0 404 Not Found");
        }

        private function convert_base_to_bitcoin($base_currency, $amount)
        {
            $ch = curl_init(self::BASE_URL . 'currency?value=' . $amount . '&base=' . $base_currency . '&target=btc');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'User-Agent:' . self::PLUGIN_NAME . '/' . self::PLUGIN_VERSION)
            );
            $result = curl_exec($ch);
            $data = json_decode($result, true);
            // Return the equivalent bitcoin value acquired from Jeeb server.
            return (float) $data["result"];
        }

        private function create_payment($signature, $options = array())
        {
            $post = json_encode($options);
            $ch = curl_init(self::BASE_URL . 'payments/' . $signature . '/issue/');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type:application/json',
                'User-Agent:' . self::PLUGIN_NAME . '/' . self::PLUGIN_VERSION,
            ));
            $result = curl_exec($ch);
            $data = json_decode($result, true);
            return $data['result']['token'];
        }

        private function confirm_payment($signature, $options = array())
        {
            $post = json_encode($options);
            $ch = curl_init(self::BASE_URL . 'payments/' . $signature . '/confirm/');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type:application/json',
                'User-Agent:' . self::PLUGIN_NAME . '/' . self::PLUGIN_VERSION,
            ));
            $result = curl_exec($ch);
            $data = json_decode($result, true);
            return (bool) $data['result']['isConfirmed'];
        }

        private function redirect_payment($token)
        {
            $redirect_url = self::BASE_URL . "payments/invoice?token=" . $token;
            header('Location: ' . $redirect_url);
        }
    }
}
function edd_jeeb_gateway_load()
{
    $edd_jeeb = new EDD_Jeeb_Payment_Gateway();
}
add_action('plugins_loaded', 'edd_jeeb_gateway_load');
