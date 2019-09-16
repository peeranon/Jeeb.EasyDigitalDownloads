<?php
/**
 * Plugin Name:     Easy Digital Downloads -Jeeb Gateway
 * Plugin URI:      https://github.com/Jeebio/Jeeb.EasyDigitalDownloads
 * Description:     Add support for Jeeb payment Gateway to Easy Digital Downloads.
 * Version:         3.0
 * Author:          Giridhar
 * Author URI:      https://github.com/gdhar67
 */

// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;


// Add custom javascript
add_action( 'admin_enqueue_scripts', 'admin_scripts', 999);

function admin_scripts()
{
  if (is_admin()) {
      wp_enqueue_style('jeeb_admin_style', plugins_url('admin.css', __FILE__));
      wp_enqueue_script('jeeb_admin_script', plugins_url('admin.js', __FILE__), array('jquery'), '1.0', true);
  }
}

if( !class_exists( 'EDD_Jeeb' ) ) {
    class EDD_Jeeb {
        private static $instance;
        /**
         * Get active instance
         *
         * @since       1.0.0
         * @access      public
         * @static
         * @return      object self::$instance
         */
        public static function get_instance() {
            if( !self::$instance )
                self::$instance = new EDD_Jeeb();
            return self::$instance;
        }
        /**
         * Class constructor
         *
         * @since       1.0.0
         * @access      public
         * @return      void
         */
        public function __construct() {
            // Plugin dir
            define( 'EDD_JEEB_DIR', plugin_dir_path( __FILE__ ) );
            // Plugin URL
            define( 'EDD_JEEB_URL', plugin_dir_url( __FILE__ ) );
            $this->init();
        }
        /**
         * Run action and filter hooks
         *
         * @since       1.0.0
         * @access      private
         * @return      void
         */
        private function init() {
            // Make sure EDD is active
            if( !class_exists( 'Easy_Digital_Downloads' ) ) return;
            global $edd_options;
            // Internationalization
            add_action( 'init', array( $this, 'textdomain' ) );
            // Add the gateway
            add_filter( 'edd_payment_gateways', array( $this, 'register_gateway' ) );
            // Remove CC form
            add_action( 'edd_jeeb_cc_form', '__return_false' );
            // Adding Jeeb to Payment Tab
            add_filter('edd_settings_sections_gateways', array( $this, 'jeeb_checkout_edd_register_gateway_section' ));
            // Adding custom Icon
            add_filter('edd_accepted_payment_icons', array( $this, 'jeeb_edd_payment_icon' ));
            // Register settings
            add_filter( 'edd_settings_gateways', array( $this, 'settings' ), 1 );

            // Process payment
            add_action( 'edd_gateway_jeeb', array( $this, 'process_payment' ) );
            add_action( 'init', array( $this, 'edd_listen_for_jeeb_ipn' ) );
            add_action( 'edd_verify_jeeb_ipn', array( $this, 'edd_process_jeeb_ipn' ) );
            add_action( 'edd_process_jeeb_payment', array( $this, 'process_jeeb_payment' ), 10, 2);

        }
        /**
         * Internationalization
         *
         * @since       1.0.0
         * @access      public
         * @static
         * @return      void
         */
        public static function textdomain() {
            // Set filter for language directory
            $lang_dir = dirname( plugin_basename( __FILE__ ) ) . '/languages/';
            $lang_dir = apply_filters( 'edd_jeeb_lang_dir', $lang_dir );
            // Load translations
            load_plugin_textdomain( 'edd-jeeb', false, $lang_dir );
        }

        public function jeeb_checkout_edd_register_gateway_section($gateway_sections)
        {
            $gateway_sections['jeeb_checkout_edd'] = 'Jeeb';
            return $gateway_sections;
        }

        public function jeeb_edd_payment_icon($icons){
          global $edd_options;
          if(isset($edd_options["edd_jeeb_btnurl"]) === false)
            $url="https://jeeb.io/cdn/en/blue-white-jeeb.svg";
          else
            $url = $edd_options["edd_jeeb_btnurl"];
          $url = set_url_scheme( $url );

          if (is_admin()) {
            $icons[$url] = 'Jeeb'; // Not working
            return $icons;
          }
        }

        /**
         * Add settings
         *
         * @since       1.0.0
         * @access      public
         * @param       array $settings The existing plugin settings
         * @return      array
         */
        public function settings( $gateway_settings ) {
            global $edd_options;
            // $url = trailingslashit( home_url() );
            $jeeb_edd_settings = array(
                array(
                    'id'    => 'edd_jeeb_settings',
                    'name'  => '<strong>'.'Jeeb Settings'.'</strong>',
                    'desc'  => 'Configure your Jeeb settings',
                    'type'  => 'header'
                ),
                array(
                    'id'    => 'edd_jeeb_signature',
                    'name'  => 'Signature',
                    'desc'  => 'Enter your Jeeb signature',
                    'type'  => 'text'
                ),
                array(
                    'id'    => 'edd_jeeb_test',
                    'name'  => 'Allow TestNets',
                    'desc'  => 'Allows testnets such as TEST-BTC to get processed.',
                    'type'  => 'checkbox',
                    'default' => 'no'
                ),
                array(
                    'id'    => 'edd_jeeb_basecoin',
                    'name'  => 'Base Currency',
                    'desc'  => 'The base currency of your website.',
                    'type'  => 'select',
                    'options' => array(
                      'btc' => 'BTC',
                      'eur' => 'EUR',
                      'irr' => 'IRR',
                      'toman'=>'TOMAN',
                      'usd' => 'USD',
                     ),
                ),

                array(
                    'id'    => 'edd_jeeb_btc',
                    'name'  => 'Payable Currency',
                    'desc'  => 'BTC     The currencies which users can use for payments.',
                    'type'  => 'checkbox',
                    'default' => 'no'
                ),
                array(
                    'id'    => 'edd_jeeb_rth',
                    'desc'  => 'ETH',
                    'type'  => 'checkbox',
                    'default' => 'no'
                ),
                array(
                    'id'    => 'edd_jeeb_ltc',
                    'desc'  => 'LTC',
                    'type'  => 'checkbox',
                    'default' => 'no'
                ),
                array(
                    'id'    => 'edd_jeeb_xrp',
                    'desc'  => 'XRP',
                    'type'  => 'checkbox',
                    'default' => 'no'
                ),
                array(
                    'id'    => 'edd_jeeb_xmr',
                    'desc'  => 'XMR',
                    'type'  => 'checkbox',
                    'default' => 'no'
                ),
                array(
                    'id'    => 'edd_jeeb_bch',
                    'desc'  => 'BCH',
                    'type'  => 'checkbox',
                    'default' => 'no'
                ),
                array(
                    'id'    => 'edd_jeeb_test-btc',
                    'desc'  => 'TEST-BTC',
                    'type'  => 'checkbox',
                    'default' => 'no'
                ),
                array(
                    'id'    => 'edd_jeeb_test-ltc',
                    'desc'  => 'TEST-LTC',
                    'type'  => 'checkbox',
                    'default' => 'no'
                ),
                array(
                    'id'    => 'edd_jeeb_allow_refund',
                    'name'  => 'Allow Refund',
                    'desc'  => 'Allows payments to be refunded.',
                    'type'  => 'checkbox',
                    'default' => 'no'
                ),
                array(
                    'id'    => 'edd_jeeb_expiration',
                    'name'  => 'Expiration Time',
                    'desc'  => 'Expands default payments expiration time. It should be between 15 to 2880 (mins).',
                    'type'  => 'text'
                ),
                array(
                    'id'    => 'edd_jeeb_lang',
                    'name'  => 'Language',
                    'desc'  => 'The language of the payment area.',
                    'type'  => 'select',
                    'options' => array(
                      'none' => 'Auto-Select',
                      'en'   => 'English',
                      'fa'   =>'Persian'
                     ),
                ),
                array(
                    'id'    => 'edd_jeeb_callback_url',
                    'name'  => 'Return Url',
                    'desc'  => 'Enter the URL to which you want the user to return after the payment',
                    'type'  => 'text',
                    // 'default'   => $url
                ),
                array(
                    'id'    => 'edd_jeeb_btnlang',
                    'name'  => 'Checkout Button Language',
                    'desc'  => 'Jeeb\'s checkout button preferred language.',
                    'type'  => 'select',
                    'options' => array(
                      'en'   => 'English',
                      'fa'   =>'Persian'
                     ),
                ),
                array(
                    'id'    => 'edd_jeeb_btntheme',
                    'name'  => 'Checkout Button Theme',
                    'desc'  => 'Jeeb\'s checkout button preferred theme.',
                    'type'  => 'select',
                    'options' => array(
                        'blue' => 'Blue',
                        'transparent' => 'Transparent'
                    ),
                ),
                array(
                  'id'   => 'edd_jeeb_btnurl',
                  'name'  => 'Checkout Button',
                  'type' => 'text'
                )
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
        public function register_gateway( $gateways ) {
            $gateways['jeeb'] = array(
                'admin_label'       => 'Jeeb Payments',
                'checkout_label'    => 'Jeeb Payments - Pay with Bitcoin, Litecoin, or other cryptocurrencies',
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
        public function process_payment( $purchase_data ) {
            global $edd_options;
            // Collect payment data
            $payment_data = array(
                'price'         => $purchase_data['price'],
                'date'          => $purchase_data['date'],
                'user_email'    => $purchase_data['user_email'],
                'purchase_key'  => $purchase_data['purchase_key'],
                'currency'      => edd_get_currency(),
                'downloads'     => $purchase_data['downloads'],
                'user_info'     => $purchase_data['user_info'],
                'cart_details'  => $purchase_data['cart_details'],
                'gateway'       => 'jeeb',
                'status'        => 'pending'
            );
            // Record the pending payment
            $payment = edd_insert_payment( $payment_data );
            // Were there any errors?
            if( !$payment ) {
                // Record the error
                edd_record_gateway_error( 'Payment Error', sprintf(  'Payment creation failed before sending buyer to Jeeb. Payment data: %s' , json_encode( $payment_data ) ), $payment );
                edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
            } else {
              do_action('edd_process_jeeb_payment', $purchase_data, $payment);
            }
        }

        public function process_jeeb_payment( $purchase_data, $payment ){
          global $edd_options;
          $ipn_url     = trailingslashit( home_url() ).'?edd-listener=JEEBIPN';
          $success_url = $edd_options['edd_jeeb_callback_url'];
          $target_cur  = "";
          $order_total = round( $purchase_data['price'] - $purchase_data['tax'], 2 );
          $baseCur     = $edd_options['edd_jeeb_basecoin'];
          $baseUri     = "https://core.jeeb.io/api/";
          $signature   = $edd_options['edd_jeeb_signature'];

          if(isset($edd_options['edd_jeeb_expiration']) === false ||
              is_numeric($edd_options['edd_jeeb_expiration']) === false ||
              $edd_options['edd_jeeb_expiration']<15||
              $edd_options['edd_jeeb_expiration']>2880){
            $edd_options['edd_jeeb_expiration']=15;
          }

          $params = array(
                          'btc',
                          'ltc',
                          'bch',
                          'eth',
                          'xrp',
                          'xmr',
                          'test-btc',
                          'test-ltc'
                         );
          foreach ($params as $p) {
            isset($edd_options["edd_jeeb_".$p]) ? $target_cur .= $p . "/" : $target_cur .="" ;
          }
          if($baseCur=='toman'){
            $baseCur='irr';
            $order_total *= 10;
          }
          $amount = convertBaseToTarget($baseUri, $order_total, $signature, $baseCur);
          // Setup Jeeb arguments
          $data = array(
            "orderNo"        => $payment,
            "value"          => (float) $amount,
            "coins"          => $target_cur,
            "webhookUrl"     => $ipn_url,
            "callbackUrl"    => $success_url,
            "expiration"     => $edd_options['edd_jeeb_expiration'],
            "allowReject"    => $edd_options['edd_jeeb_allow_refund'] == '1' ? true : false,
            "allowTestNet"   => $edd_options['edd_jeeb_test'] == '1' ? true : false,
            "language"       => $edd_options['edd_jeeb_lang'] == 'none' ? NULL : $edd_options['edd_jeeb_lang']
          );
          $data_string = json_encode($data);

          $token = createInvoice($baseUri, $amount, $data, $signature);
          // Redirect to Jeeb
          redirectPayment($baseUri, $token);

          exit;
        }

        /**
         * Listens for a Jeeb IPN requests and then sends to the processing function
         *
         * @since       1.0.0
         * @access      public
         * @global      array $edd_options
         * @return      void
         */
        public function edd_listen_for_jeeb_ipn() {
            global $edd_options;
            if ( isset( $_GET['edd-listener'] ) && $_GET['edd-listener'] == 'JEEBIPN' ) {
                do_action( 'edd_verify_jeeb_ipn' );
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
        public function edd_process_jeeb_ipn() {
            global $edd_options;
            error_log("Entered into Jeeb-Notification");
            $postdata = file_get_contents("php://input");
            $json = json_decode($postdata, true);
            $payment_id     = $json['orderNo'];
            if($json['signature'] == $edd_options['edd_jeeb_signature']){
            // Call Jeeb
            $network_uri = "https://core.jeeb.io/api/";
            if ( $json['stateId']== 2 ) {
              $status_text = "created";
              $int     = edd_insert_payment_note( $payment_id, sprintf(  'Invoice %s, Awaiting payment' , $status_text) );
              $result1 = edd_update_payment_status( $payment_id, 'pending' );
            }
            else if ( $json['stateId']== 3 ) {
              $status_text = "paid";
              $int     = edd_insert_payment_note( $payment_id, sprintf(  'Invoice %s, Awaiting confirmation. Reference Number : %s' , $status_text, $json['referenceNo']) );
              $result1 = edd_update_payment_status( $payment_id, 'pending' );
              // Get rid of cart contents
              edd_empty_cart();
            }
            else if ( $json['stateId']== 4 ) {
              $data = array(
                "token" => $json["token"]
              );
              define("PLUGIN_NAME", 'eastdigitaldownloads');
              define("PLUGIN_VERSION", '3.0');
              $data_string = json_encode($data);
              $api_key = $json["signature"];
              $url = $network_uri.'payments/' . $api_key . '/confirm';
              $ch = curl_init($url);
              curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
              curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
              curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
              curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                  'Content-Type: application/json',
                  'Content-Length: ' . strlen($data_string),
                  'User-Agent:'.$PLUGIN_NAME.'/'.$PLUGIN_VERSION)
              );
              $result = curl_exec($ch);
              $data = json_decode( $result , true);
              if($data['result']['isConfirmed']){
                $status_text = "confirmed";
                $int     = edd_insert_payment_note( $payment_id, sprintf(  'Payment was successfully %s', $status_text ) );
                $result1 = edd_update_payment_status( $payment_id, 'publish' );
              }
              else {
                error_log('Payment rejected by jeeb');
              }
            }
            else if ( $json['stateId']== 5 ) {
              $status_text = "expired";
              $int     = edd_insert_payment_note( $payment_id, sprintf(  'Jeeb Invoice %s' , $status_text ));
              $result1 = edd_update_payment_status( $payment_id, 'failed' );
            }
            else if ( $json['stateId']== 6 ) {
              $status_text = "refunded";
              $int     = edd_insert_payment_note( $payment_id, sprintf(  'Jeeb Invoice %s' , $status_text ));
              $result1 = edd_update_payment_status( $payment_id, 'refunded' );
            }
            else if ( $json['stateId']== 7 ) {
              $status_text = "refunded";
              $int     = edd_insert_payment_note( $payment_id, sprintf(  'Jeeb Invoice %s' , $status_text ));
              $result1 = edd_update_payment_status( $payment_id, 'refunded' );
            }
            else{
              error_log('Cannot read state id sent by Jeeb');
            }
          }
            die( __('IPN Processed OK', 'edd-jeeb' ) );
        }
    }
    function convertBaseToTarget($url, $amount, $signature, $baseCur) {
        error_log("Entered into Convert API");
        define("PLUGIN_NAME", 'eastdigitaldownloads');
        define("PLUGIN_VERSION", '3.0');
        $ch = curl_init($url.'currency?'.$signature.'&value='.$amount.'&base='.$baseCur.'&target=btc');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Content-Type: application/json',
          'User-Agent:'.$PLUGIN_NAME.'/'.$PLUGIN_VERSION)
      );
      $result = curl_exec($ch);
      $data = json_decode( $result , true);
      // Return the equivalent bitcoin value acquired from Jeeb server.
      return (float) $data["result"];
      }
      function createInvoice($url, $amount, $options = array(), $signature) {
          error_log("Entered into Issue API");
          define("PLUGIN_NAME", 'eastdigitaldownloads');
          define("PLUGIN_VERSION", '3.0');
          $post = json_encode($options);
          $ch = curl_init($url.'payments/' . $signature . '/issue/');
          curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
          curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch, CURLOPT_HTTPHEADER, array(
              'Content-Type:application/json',
              'User-Agent:'.$PLUGIN_NAME.'/'.$PLUGIN_VERSION
          ));
          $result = curl_exec($ch);
          $data = json_decode( $result , true);
          return $data['result']['token'];
      }
      function redirectPayment($url, $token) {
        error_log("Entered into auto submit-form");
        // Using Auto-submit form to redirect user with the token
        echo "<form id='form' method='post' action='".$url."payments/invoice'>".
                "<input type='hidden' autocomplete='off' name='token' value='".$token."'/>".
               "</form>".
               "<script type='text/javascript'>".
                    "document.getElementById('form').submit();".
               "</script>";
      }

}
function edd_jeeb_gateway_load() {
    $edd_jeeb = new EDD_Jeeb();
}
add_action( 'plugins_loaded', 'edd_jeeb_gateway_load' );
