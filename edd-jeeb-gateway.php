<?php

// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;
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
            // Register settings
            add_filter( 'edd_settings_gateways', array( $this, 'settings' ), 1 );
            // Add the gateway
            add_filter( 'edd_payment_gateways', array( $this, 'register_gateway' ) );
            // Remove CC form
            add_action( 'edd_jeeb_cc_form', '__return_false' );
            // Process payment
            add_action( 'edd_gateway_jeeb', array( $this, 'process_payment' ) );
            add_action( 'init', array( $this, 'edd_listen_for_jeeb_ipn' ) );
            add_action( 'edd_verify_jeeb_ipn', array( $this, 'edd_process_jeeb_ipn' ) );
            // Display errors
            add_action( 'edd_after_cc_fields', array( $this, 'errors_div' ), 999 );
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
        /**
         * Add settings
         *
         * @since       1.0.0
         * @access      public
         * @param       array $settings The existing plugin settings
         * @return      array
         */
        public function settings( $settings ) {
            $url = trailingslashit( home_url() );
            $jeeb_settings = array(
                array(
                    'id'    => 'edd_jeeb_settings',
                    'name'  => '<strong>' . __( 'Jeeb Settings', 'edd-jeeb' ) . '</strong>',
                    'desc'  => __( 'Configure your Jeeb settings', 'edd-jeeb' ),
                    'type'  => 'header'
                ),
                array(
                    'id'    => 'edd_jeeb_signature',
                    'name'  => __( 'Signature', 'edd-jeeb' ),
                    'desc'  => __( 'Enter your Jeeb signature', 'edd-jeeb' ),
                    'type'  => 'text'
                ),
                array(
                    'id'    => 'edd_jeeb_test',
                    'name'  => __( 'Test Jeeb', 'edd-jeeb' ),
                    'desc'  => __( 'Plaese check this box for debbuging and Testing purposes', 'edd-jeeb' ),
                    'type'  => 'checkbox',
                    'default' => 'no'
                ),
                array(
                    'id'    => 'edd_jeeb_basecoin',
                    'name'  => __( 'Base Currency', 'edd-jeeb' ),
                    'desc'  => __( 'Select your base currency', 'edd-jeeb' ),
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
                    'name'  => __( 'Target Currency', 'edd-jeeb' ),
                    'desc'  => __( 'BTC  Select the target currency(Multiselect)', 'edd-jeeb' ),
                    'type'  => 'checkbox',
                    'default' => 'no'
                ),
                array(
                    'id'    => 'edd_jeeb_rth',
                    'desc'  => __( 'ETH', 'edd-jeeb' ),
                    'type'  => 'checkbox',
                    'default' => 'no'
                ),
                array(
                    'id'    => 'edd_jeeb_ltc',
                    'desc'  => __( 'LTC', 'edd-jeeb' ),
                    'type'  => 'checkbox',
                    'default' => 'no'
                ),
                array(
                    'id'    => 'edd_jeeb_xrp',
                    'desc'  => __( 'XRP', 'edd-jeeb' ),
                    'type'  => 'checkbox',
                    'default' => 'no'
                ),
                array(
                    'id'    => 'edd_jeeb_xmr',
                    'desc'  => __( 'XMR', 'edd-jeeb' ),
                    'type'  => 'checkbox',
                    'default' => 'no'
                ),
                array(
                    'id'    => 'edd_jeeb_bch',
                    'desc'  => __( 'BCH', 'edd-jeeb' ),
                    'type'  => 'checkbox',
                    'default' => 'no'
                ),
                array(
                    'id'    => 'edd_jeeb_test-btc',
                    'desc'  => __( 'TEST-BTC', 'edd-jeeb' ),
                    'type'  => 'checkbox',
                    'default' => 'no'
                ),
                array(
                    'id'    => 'edd_jeeb_lang',
                    'name'  => __( 'Language', 'edd-jeeb' ),
                    'desc'  => __( 'Select the language of payment page', 'edd-jeeb' ),
                    'type'  => 'select',
                    'options' => array(
                      'none' => 'Auto-Select',
                      'en'   => 'English',
                      'fa'   =>'Persian'
                     ),
                ),
                array(
                    'id'    => 'edd_jeeb_callback_url',
                    'name'  => __( 'Return Url', 'edd-jeeb' ),
                    'desc'  => __( 'Enter the URL to which you want the user to return after the payment', 'edd-jeeb' ),
                    'type'  => 'text',
                    'std'   => $url,
                    'faux'  => true
                )
            );
            return array_merge( $settings, $jeeb_settings );
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
                'checkout_label'    => __( 'Jeeb Payments - Pay with Bitcoin, Litecoin, or other cryptocurrencies', 'edd-jeeb-gateway' )
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
                edd_record_gateway_error( __( 'Payment Error', 'edd-jeeb' ), sprintf( __( 'Payment creation failed before sending buyer to Jeeb. Payment data: %s', 'edd-jeeb' ), json_encode( $payment_data ) ), $payment );
                edd_send_back_to_checkout( '?payment-mode=' . $purchase_data['post_data']['edd-gateway'] );
            } else {
                $ipn_url     = trailingslashit( home_url() ).'?edd-listener=JEEBIPN';
                $success_url = $edd_options['edd_jeeb_callback_url'];
                $target_cur  = "";
                $order_total = round( $purchase_data['price'] - $purchase_data['tax'], 2 );
                $baseCur     = $edd_options['edd_jeeb_basecoin'];
                $baseUri     = "https://core.jeeb.io/api/";
                $signature   = $edd_options['edd_jeeb_signature'];
                $params = array(
                                'btc',
                                'xrp',
                                'xmr',
                                'ltc',
                                'bch',
                                'eth',
                                'test-btc'
                               );
                foreach ($params as $p) {
                  // error_log($p." = ". $edd_options["edd_jeeb_".$p]);
                  $edd_options["edd_jeeb_".$p] == 1 ? $target_cur .= $p . "/" : $target_cur .="" ;
                }
                if($baseCur=='toman'){
                  $baseCur='irr';
                  $order_total *= 10;
                }
                $amount = convertBaseToTarget($baseUri, $order_total, $signature, $baseCur);
                // Setup Jeeb arguments
                $data = array(
                  "orderNo"      => $payment,
                  "value"        => (float) $amount,
                  "coins"        => $target_cur,
                  "webhookUrl"   => $ipn_url,
                  "callBackUrl"  => $success_url,
                  "allowReject"  => $edd_options['edd_jeeb_test'] == '1' ? false : true,
                  "allowTestNet" => $edd_options['edd_jeeb_test'] == '1' ? true : false,
                  "language"     => $edd_options['edd_jeeb_lang'] == 'none' ? NULL : $edd_options['edd_jeeb_lang']
                );
                $data_string = json_encode($data);
            }
            error_log("target".$target_cur." Total =".$order_total."Requesting with Params => " . json_encode($data));
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
            $postdata = file_get_contents("php://input");
            $json = json_decode($postdata, true);
            $payment_id     = $json['orderNo'];
            if($json['signature'] == $edd_options['edd_jeeb_signature']){
              error_log("Entered into Notification");
              error_log("Response =>". var_export($json, TRUE));
            // Call Jeeb
            $network_uri = "https://core.jeeb.io/api/";
            if ( $json['stateId']== 2 ) {
              $status_text = "created";
              $int     = edd_insert_payment_note( $payment_id, sprintf( __( 'Invoice %s, Awaiting payment', 'edd-jeeb' ), $status_text) );
              $result1 = edd_update_payment_status( $payment_id, 'pending' );
            }
            else if ( $json['stateId']== 3 ) {
              $status_text = "paid";
              $int     = edd_insert_payment_note( $payment_id, sprintf( __( 'Invoice %s, Awaiting confirmation', 'edd-jeeb' ), $status_text) );
              $result1 = edd_update_payment_status( $payment_id, 'pending' );
              // Get rid of cart contents
              edd_empty_cart();
            }
            else if ( $json['stateId']== 4 ) {
              $data = array(
                "token" => $json["token"]
              );
              $data_string = json_encode($data);
              $api_key = $json["signature"];
              $url = $network_uri.'payments/' . $api_key . '/confirm';
              error_log("Signature:".$api_key." Base-Url:".$network_uri." Url:".$url);
              $ch = curl_init($url);
              curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
              curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
              curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
              curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                  'Content-Type: application/json',
                  'Content-Length: ' . strlen($data_string))
              );
              $result = curl_exec($ch);
              $data = json_decode( $result , true);
              error_log("data = ".var_export($data, TRUE));
              if($data['result']['isConfirmed']){
                error_log('Payment confirmed by jeeb');
                $status_text = "confirmed";
                $int     = edd_insert_payment_note( $payment_id, sprintf( __( 'Payment was successfully %s', 'edd-jeeb' ) , $status_text ) );
                $result1 = edd_update_payment_status( $payment_id, 'publish' );
              }
              else {
                error_log('Payment rejected by jeeb');
              }
            }
            else if ( $json['stateId']== 5 ) {
              $status_text = "expired";
              $int     = edd_insert_payment_note( $payment_id, sprintf( __( 'Jeeb Invoice %s', 'edd-jeeb' ), $status_text ));
              $result1 = edd_update_payment_status( $payment_id, 'failed' );
            }
            else if ( $json['stateId']== 6 ) {
              $status_text = "overpaid";
              $int     = edd_insert_payment_note( $payment_id, sprintf( __( 'Jeeb Invoice %s', 'edd-jeeb' ), $status_text ));
              $result1 = edd_update_payment_status( $payment_id, 'failed' );
            }
            else if ( $json['stateId']== 7 ) {
              $status_text = "partially paid";
              $int     = edd_insert_payment_note( $payment_id, sprintf( __( 'Jeeb Invoice %s', 'edd-jeeb' ), $status_text ));
              $result1 = edd_update_payment_status( $payment_id, 'failed' );
            }
            else{
              error_log('Cannot read state id sent by Jeeb');
            }
          }
            die( __('IPN Processed OK', 'edd-jeeb' ) );
        }
    }
    function convertBaseToTarget($url, $amount, $signature, $baseCur) {
        error_log("Entered into Convert Base To Target");
        error_log($url.'currency?'.$signature.'&value='.$amount.'&base='.$baseCur.'&target=btc');
        $ch = curl_init($url.'currency?'.$signature.'&value='.$amount.'&base='.$baseCur.'&target=btc');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
          'Content-Type: application/json')
      );
      $result = curl_exec($ch);
      $data = json_decode( $result , true);
      error_log('Response =>'. var_export($data, TRUE));
      // Return the equivalent bitcoin value acquired from Jeeb server.
      return (float) $data["result"];
      }
      function createInvoice($url, $amount, $options = array(), $signature) {
          error_log("Entered into Create Invoice");
          $post = json_encode($options);
          $ch = curl_init($url.'payments/' . $signature . '/issue/');
          curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
          curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch, CURLOPT_HTTPHEADER, array(
              'Content-Type: application/json',
              'Content-Length: ' . strlen($post))
          );
          $result = curl_exec($ch);
          $data = json_decode( $result , true);
          error_log('Response =>'. var_export($data, TRUE));
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