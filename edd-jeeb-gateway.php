<?php

/**
 * Plugin Name:     Easy Digital Downloads - Jeeb Payment Gateway
 * Plugin URI:      https://github.com/Jeebio/Jeeb.EasyDigitalDownloads
 * Description:     The first Iranian platform for accepting and processing cryptocurrencies payments.
 * Version:         4.0
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
        const PLUGIN_VERSION = '4.0';
        const BASE_URL = "https://core.jeeb.io/api/v3/";

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
         * Get all available currencies in Jeeb gateway
         *
         * @since       3.4.0
         * @access      private
         * @return      array
         */
        private function jeeb_available_currencies_list()
        {
            $currencies = [
                "IRT" => "IRT (Toman)",
                "IRR" => "IRR (Rial)",
                "BTC" => "BTC (Bitcoin)",
                "USD" => "USD (US Dollar)",
                "USDT" => "USDT (TetherUS)",
                "EUR" => "EUR (Euro)",
                "GBP" => "GBP (Pound)",
                "CAD" => "CAD (CA Dollar)",
                "AUD" => "AUD (AU Dollar)",
                "JPY" => "JPY (Yen)",
                "CNY" => "CNY (Yuan)",
                "AED" => "AED (Dirham)",
                "TRY" => "TRY (Lira)",
            ];

            return $currencies;
        }

        /**
         * Get all available coins in Jeeb gateway
         *
         * @since       3.4.0
         * @access      private
         * @return      array
         */
        private function jeeb_available_coins_list()
        {
            $currencies = [
                "BTC" => "BTC (Bitcoin)",
                "ETH" => "ETH (Ethereum)",
                "DOGE" => "DOGE (Dogecoin)",
                "LTC" => "LTC (Litecoin)",
                "USDT" => "USDT (TetherUS)",
                "BNB" => "BNB (BNB)",
                "USDC" => "USDC (USD Coin)",
                "ZRX" => "ZRX (0x)",
                "LINK" => "LINK (ChainLink)",
                "PAX" => "PAX (Paxos Standard)",
                "DAI" => "DAI (Dai)",
                "TBTC" => "TBTC (Bitcoin Testnet)",
                "TETH" => "TETH (Ethereum Testnet)",
            ];

            return $currencies;
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
                    'id' => 'edd_jeeb_apiKey',
                    'name' => 'API Key',
                    'desc' => '<br/>The API key provided by Jeeb for you merchant.',
                    'type' => 'text',
                ),

                array(
                    'id' => 'edd_jeeb_baseCurrency',
                    'name' => 'Base Currency',
                    'desc' => 'The base currency of your website.',
                    'type' => 'select',
                    'options' => $this->jeeb_available_currencies_list(),
                ),
            );

            $available_coins = $this->jeeb_available_coins_list();

            $first_item = true;
            foreach ($available_coins as $key => $title) {
                $jeeb_edd_settings[] = array(
                    'id' => 'edd_jeeb_' . $key,
                    'name' => $first_item ? 'Payable Currencies' : '',
                    'desc' => $title,
                    'type' => 'checkbox',
                    'default' => 'yes',
                );

                if ($first_item) {
                    $first_item = false;
                }
            }

            $jeeb_edd_settings = array_merge($jeeb_edd_settings, array(
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
                    'id' => 'edd_jeeb_testnets',
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

                array(
                    'id'   => 'edd_jeeb_debugging_header',
                    'name' => '<strong>Debugging</strong>',
                    'type' => 'header',
                ),

                array(
                    'id' => 'edd_jeeb_webhookDebugUrl',
                    'name' => 'Webhook.site URL',
                    'desc' => '<br/>With <a href="https://webhook.site">Webhook.site</a>, you instantly get a unique, random URL that you can use to test and debug Webhooks and HTTP requests',
                    'type' => 'text',
                ),
            ));
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

        public function process_jeeb_payment($purchase_data, $payment_id)
        {
            global $edd_options;

            $api_key = $edd_options['edd_jeeb_apiKey'];

            $hash_key = md5($api_key . $payment_id);
            $webhook_url = trailingslashit(home_url()) . '?edd-listener=JEEBIPN&hashKey=' . $hash_key;

            $callback_url = get_permalink(edd_get_option('success_page', false));
            if (isset($edd_options['edd_jeeb_callback_url'])) {
                $callback_url =  $edd_options['edd_jeeb_callback_url'];
            }

            $order_total = round($purchase_data['price'] - $purchase_data['tax'], 8);
            $base_currency = $edd_options['edd_jeeb_baseCurrency'];

            $expiration = 15;
            if (
                isset($edd_options['edd_jeeb_expiration'])  &&
                is_numeric($edd_options['edd_jeeb_expiration']) &&
                $edd_options['edd_jeeb_expiration'] >= 15 ||
                $edd_options['edd_jeeb_expiration'] <= 2880
            ) {
                $expiration = $edd_options['edd_jeeb_expiration'];
            }

            $coins = array_keys($this->jeeb_available_coins_list());

            $payable_coins = [];
            foreach ($coins as $coin) {
                if (isset($edd_options["edd_jeeb_" . $coin])) {
                    $payable_coins[] = $coin;
                }
            }
            $payable_coins = implode('/', $payable_coins);
            if (empty($payable_coins)) {
                $payable_coins = null;
            }

            $allowReject = isset($edd_options['edd_jeeb_allow_refund']) &&  $edd_options['edd_jeeb_allow_refund'] == '1';
            $allowTestNets = isset($edd_options['edd_jeeb_testnets']) &&  $edd_options['edd_jeeb_testnets'] == '1';

            $data = array(
                "orderNo" => $payment_id,
                "baseAmount" => $order_total,
                "baseCurrencyId" => $base_currency,
                "payableCoins" => $payable_coins,
                "webhookUrl" => $webhook_url,
                "callbackUrl" => $callback_url,
                "expiration" => $expiration,
                "allowReject" => $allowReject,
                "allowTestNets" => $allowTestNets,
                "language" => $edd_options['edd_jeeb_lang'] == 'none' ? null : $edd_options['edd_jeeb_lang'],
            );

            $token = $this->create_payment($api_key, $data);

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

            $this->notify_log($json);

            $payment_id = $json['orderNo'];

            $api_key = $edd_options['edd_jeeb_apiKey'];

            if ($this->validate_hashkey($_GET['hashKey'], $api_key, $payment_id)) {

                $this->notify_log('HashKey:' . $_GET['hashKey'] . ' is valid');

                switch ($json['state']) {
                    case 'PendingTransaction':
                        edd_insert_payment_note($payment_id, 'Jeeb: Pending transaction.');
                        edd_update_payment_status($payment_id, 'pending');
                        break;

                    case 'PendingConfirmation':
                        edd_insert_payment_note($payment_id, 'Jeeb: Pending confirmation.');
                        if ($json['refund'] == true) {
                            edd_insert_payment_note($payment_id, 'Jeeb: Payment will be rejected.');
                        }
                        edd_update_payment_status($payment_id, 'pending');
                        edd_set_payment_transaction_id($payment_id, $json['referenceNo']);
                        break;

                    case 'Completed':
                        $is_confirmed = $this->confirm_payment($json['token'], $api_key);

                        if ($is_confirmed) {
                            edd_insert_payment_note($payment_id, 'Jeeb: Payment is confirmed.');
                            edd_update_payment_status($payment_id, 'complete');
                        } else {
                            edd_insert_payment_note($payment_id, 'Jeeb: Double spending avoided.');
                        }
                        break;

                    case 'Rejected':
                        edd_insert_payment_note($payment_id, 'Jeeb: Payment is rejected.');
                        edd_update_payment_status($payment_id, 'refunded');
                        break;

                    case 'Expired':
                        edd_insert_payment_note($payment_id, 'Jeeb: Payment is expired or canceled.');
                        edd_update_payment_status($payment_id, 'failed');

                        $this->notify_log('Payment is expired or canceled');
                        break;

                    default:
                        edd_insert_payment_note($payment_id, 'Jeeb: Unknown state received. Please report this incident.');
                        break;
                }

                header("HTTP/1.1 200 OK");
            }
            header("HTTP/1.0 404 Not Found");
        }

        private function create_payment($api_key, $options = array())
        {
            $post = json_encode($options);
            $ch = curl_init(self::BASE_URL . 'payments/issue/');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type:application/json',
                'X-API-Key: ' . $api_key,
                'User-Agent:' . self::PLUGIN_NAME . '/' . self::PLUGIN_VERSION,
            ));
            $result = curl_exec($ch);
            $data = json_decode($result, true);
            return $data['result']['token'];
        }

        private function confirm_payment($token, $api_key)
        {
            $post = json_encode(array('token' => $token));

            $ch = curl_init(self::BASE_URL . 'payments/seal/');
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                'Content-Type:application/json',
                'X-API-Key: ' . $api_key,
                'User-Agent:' . self::PLUGIN_NAME . '/' . self::PLUGIN_VERSION,
            ));
            $result = curl_exec($ch);
            $data = json_decode($result, true);
            return (bool) $data['succeed'];
        }

        private function redirect_payment($token)
        {
            $redirect_url = self::BASE_URL . "payments/invoice?token=" . $token;
            echo "<script type='text/javascript'>document.location.href='{$redirect_url}';</script>";
        }

        /**
         * Check if hashKey parameter in webhook request is valid
         *
         * @since       3.4.0
         * @access      private
         * @return      bool
         */
        private function validate_hashkey($hash_key, $api_key, $payment_id)
        {
            return md5($api_key . $payment_id) === $hash_key;
        }


        /**
         * Push message to webhook.site endpoint
         *
         * @since       3.4.0
         * @access      private
         * @param       $message
         * @return      void
         */
        private function notify_log($message)
        {
            global $edd_options;

            if (isset($edd_options['edd_jeeb_webhookDebugUrl'])) {
                $post = json_encode($message);
                $ch = curl_init($edd_options['edd_jeeb_webhookDebugUrl']);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                ));

                curl_exec($ch);
            }
        }
    }
}
function edd_jeeb_gateway_load()
{
    $edd_jeeb = new EDD_Jeeb_Payment_Gateway();
}
add_action('plugins_loaded', 'edd_jeeb_gateway_load');
