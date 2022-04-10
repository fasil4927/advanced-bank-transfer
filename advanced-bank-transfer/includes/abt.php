<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

/**
 * Custom Payment Gateway.
 *
 * Provides a Custom Payment Gateway, to modify BACS formatting.
 */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    class ABT_WC_Gateway extends WC_Payment_Gateway {

        public $domain;
		public $locale;

		/**
		 * Constructor for the gateway.
		 */
		public function __construct() {

			$this->id                 = 'abt';
			$this->icon               = apply_filters('woocommerce_bacs_icon', '');
			$this->has_fields         = false;
			$this->method_title       = __( 'Advanced Bank Transfer', 'advanced-bank-transfer' );
			$this->method_description = __( 'Allows payments by ABT, more commonly known as direct bank/wire transfer.', 'advanced-bank-transfer' );

			// Load the settings.
			$this->init_form_fields();
			$this->init_settings();

			// Define user set variables
			$this->title        = $this->get_option( 'title' );
			$this->description  = $this->get_option( 'description' );
			$this->instructions = $this->get_option( 'instructions', $this->description );

			// BACS account fields shown on the thanks page and in emails
			$this->account_details = get_option( 'abt_woocommerce_bacs_accounts',
				array(
					array(
						'account_name'   => $this->get_option( 'account_name' ),
						'account_number' => $this->get_option( 'account_number' ),
						'sort_code'      => $this->get_option( 'sort_code' ),
						'bank_name'      => $this->get_option( 'bank_name' ),
						'iban'           => $this->get_option( 'iban' ),
						'bic'            => $this->get_option( 'bic' )
					)
				)
			);

			// Actions
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_account_details' ) );
			add_action( 'woocommerce_thankyou_direct_deposit', array( $this, 'thankyou_page' ) );

			// Customer Emails
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
		}

		/**
		 * Initialise Gateway Settings Form Fields.
		 */
		public function init_form_fields() {

			$this->form_fields = array(
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'advanced-bank-transfer' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Bank Transfer', 'advanced-bank-transfer' ),
					'default' => 'no'
				),
				'title' => array(
					'title'       => __( 'Title', 'advanced-bank-transfer' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'advanced-bank-transfer' ),
					'default'     => __( 'Direct Bank Transfer', 'advanced-bank-transfer' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __( 'Description', 'advanced-bank-transfer' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'advanced-bank-transfer' ),
					'default'     => __( 'Make your payment directly into our bank account. Please use your Order ID as the payment reference. Your order won\'t be shipped until the funds have cleared in our account.', 'advanced-bank-transfer' ),
					'desc_tip'    => true,
				),
				'instructions' => array(
					'title'       => __( 'Instructions', 'advanced-bank-transfer' ),
					'type'        => 'textarea',
					'description' => __( 'Instructions that will be added to the thank you page and emails.', 'advanced-bank-transfer' ),
					'default'     => '',
					'desc_tip'    => true,
				),
				'account_details' => array(
					'type'        => 'account_details'
				),
			);

		}

		/**
		 * Generate account details html.
		 *
		 * @return string
		 */
		public function generate_account_details_html() {

			ob_start();

			$country 	= WC()->countries->get_base_country();
			$locale		= $this->get_country_locale();

			// Get sortcode label in the $locale array and use appropriate one
			$sortcode = isset( $locale[ $country ]['sortcode']['label'] ) ? $locale[ $country ]['sortcode']['label'] : __( 'Sort Code', 'advanced-bank-transfer' );

			?>
			<tr valign="top">
				<th scope="row" class="titledesc"><?php _e( 'Account Details', 'advanced-bank-transfer' ); ?>:</th>
				<td class="forminp" id="bacs_accounts">
					<table class="widefat wc_input_table sortable" cellspacing="0">
						<thead>
							<tr>
								<th class="sort">&nbsp;</th>
								<th><?php _e( 'Account Name', 'advanced-bank-transfer' ); ?></th>
								<th><?php _e( 'Account Number', 'advanced-bank-transfer' ); ?></th>
								<th><?php _e( 'Bank Name', 'advanced-bank-transfer' ); ?></th>
								<th><?php echo $sortcode; ?></th>
								<th><?php _e( 'IBAN', 'advanced-bank-transfer' ); ?></th>
								<th><?php _e( 'BIC / Swift', 'advanced-bank-transfer' ); ?></th>
							</tr>
						</thead>
						<tbody class="accounts">
							<?php
							$i = -1;
							if ( $this->account_details ) {
								foreach ( $this->account_details as $account ) {
									$i++;

									echo '<tr class="account">
										<td class="sort"></td>
										<td><input type="text" value="' . esc_attr( wp_unslash( $account['account_name'] ) ) . '" name="bacs_account_name[' . $i . ']" /></td>
										<td><input type="text" value="' . esc_attr( $account['account_number'] ) . '" name="bacs_account_number[' . $i . ']" /></td>
										<td><input type="text" value="' . esc_attr( wp_unslash( $account['bank_name'] ) ) . '" name="bacs_bank_name[' . $i . ']" /></td>
										<td><input type="text" value="' . esc_attr( $account['sort_code'] ) . '" name="bacs_sort_code[' . $i . ']" /></td>
										<td><input type="text" value="' . esc_attr( $account['iban'] ) . '" name="bacs_iban[' . $i . ']" /></td>
										<td><input type="text" value="' . esc_attr( $account['bic'] ) . '" name="bacs_bic[' . $i . ']" /></td>
									</tr>';
								}
							}
							?>
						</tbody>
						<tfoot>
							<tr>
								<th colspan="7"><a href="#" class="add button"><?php _e( '+ Add Account', 'advanced-bank-transfer' ); ?></a> <a href="#" class="remove_rows button"><?php _e( 'Remove selected account(s)', 'advanced-bank-transfer' ); ?></a></th>
							</tr>
						</tfoot>
					</table>
					<script type="text/javascript">
						jQuery(function() {
							jQuery('#bacs_accounts').on( 'click', 'a.add', function(){

								var size = jQuery('#bacs_accounts').find('tbody .account').length;

								jQuery('<tr class="account">\
										<td class="sort"></td>\
										<td><input type="text" name="bacs_account_name[' + size + ']" /></td>\
										<td><input type="text" name="bacs_account_number[' + size + ']" /></td>\
										<td><input type="text" name="bacs_bank_name[' + size + ']" /></td>\
										<td><input type="text" name="bacs_sort_code[' + size + ']" /></td>\
										<td><input type="text" name="bacs_iban[' + size + ']" /></td>\
										<td><input type="text" name="bacs_bic[' + size + ']" /></td>\
									</tr>').appendTo('#bacs_accounts table tbody');

								return false;
							});
						});
					</script>
				</td>
			</tr>
			<?php
			return ob_get_clean();

		}

		/**
		 * Save account details table.
		 */
		public function save_account_details() {

			$accounts = array();

			if ( isset( $_POST['bacs_account_name'] ) ) {

				$account_names   = array_map( 'wc_clean', $_POST['bacs_account_name'] );
				$account_numbers = array_map( 'wc_clean', $_POST['bacs_account_number'] );
				$bank_names      = array_map( 'wc_clean', $_POST['bacs_bank_name'] );
				$sort_codes      = array_map( 'wc_clean', $_POST['bacs_sort_code'] );
				$ibans           = array_map( 'wc_clean', $_POST['bacs_iban'] );
				$bics            = array_map( 'wc_clean', $_POST['bacs_bic'] );

				foreach ( $account_names as $i => $name ) {
					if ( ! isset( $account_names[ $i ] ) ) {
						continue;
					}

					$accounts[] = array(
						'account_name'   => $account_names[ $i ],
						'account_number' => $account_numbers[ $i ],
						'bank_name'      => $bank_names[ $i ],
						'sort_code'      => $sort_codes[ $i ],
						'iban'           => $ibans[ $i ],
						'bic'            => $bics[ $i ]
					);
				}
			}

			update_option( 'abt_woocommerce_bacs_accounts', $accounts );

		}

		/**
		 * Output for the order received page.
		 *
		 * @param int $order_id
		 */
		public function thankyou_page( $order_id ) {

			if ( $this->instructions ) {
				echo wpautop( wptexturize( wp_kses_post( $this->instructions ) ) );
			}
			$this->bank_details( $order_id );

		}

		/**
		 * Add content to the WC emails.
		 *
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

			if ( ! $sent_to_admin && 'direct_deposit' === $order->payment_method && $order->has_status( 'on-hold' ) ) {
				if ( $this->instructions ) {
					echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
				}
				$this->bank_details( $order->id );
			}

		}

		/**
		 * Get bank details and place into a list format.
		 *
		 * @param int $order_id
		 */
		private function bank_details( $order_id = '' ) {

			if ( empty( $this->account_details ) ) {
				return;
			}

			// Get order and store in $order
			$order 		= wc_get_order( $order_id );

			// Get the order country and country $locale
			$country 	= $order->billing_country;
			$locale		= $this->get_country_locale();

			// Get sortcode label in the $locale array and use appropriate one
			$sortcode = isset( $locale[ $country ]['sortcode']['label'] ) ? $locale[ $country ]['sortcode']['label'] : __( 'Sort Code', 'advanced-bank-transfer' );

			$bacs_accounts = apply_filters( 'abt_woocommerce_bacs_accounts', $this->account_details );

			if ( ! empty( $bacs_accounts ) ) {
				echo '<h2 class="wc-bacs-bank-details-heading">' . __( 'Our Bank Details', 'advanced-bank-transfer' ) . '</h2>' . PHP_EOL;

				foreach ( $bacs_accounts as $bacs_account ) {

					$bacs_account = (object) $bacs_account;

					//if ( $bacs_account->account_name || $bacs_account->bank_name ) {
					//	echo '<h3>' . wp_unslash( implode( ' - ', array_filter( array( $bacs_account->account_name, $bacs_account->bank_name ) ) ) ) . '</h3>' . PHP_EOL;
					//}

					echo '<ul class="wc-bacs-bank-details order_details bacs_details">' . PHP_EOL;

					// PAYMENT DETAILS RIGHT HERE!!!

					// BACS account fields shown on the thanks page and in emails
					$account_fields = apply_filters( 'woocommerce_bacs_account_fields', array(
						'bank_name'=> array(
							'label' => __( 'Bank Name', 'advanced-bank-transfer' ),
							'value' => $bacs_account->bank_name
						),
						'sort_code'     => array(
							'label' => $sortcode,
							'value' => $bacs_account->sort_code
						),						
						'account_name'=> array(
							'label' => __( 'Account Name', 'advanced-bank-transfer' ),
							'value' => $bacs_account->account_name
						),
						'account_number'=> array(
							'label' => __( 'Account Number', 'advanced-bank-transfer' ),
							'value' => $bacs_account->account_number
						),
						'iban'          => array(
							'label' => __( 'IBAN', 'advanced-bank-transfer' ),
							'value' => $bacs_account->iban
						),
						'bic'           => array(
							'label' => __( 'BIC', 'advanced-bank-transfer' ),
							'value' => $bacs_account->bic
						)
					), $order_id );

					foreach ( $account_fields as $field_key => $field ) {
						if ( ! empty( $field['value'] ) ) {
							echo '<li class="' . esc_attr( $field_key ) . '">' . esc_attr( $field['label'] ) . ': <strong>' . wptexturize( $field['value'] ) . '</strong></li>' . PHP_EOL;
						}
					}

					echo '</ul>';
				}
			}

		}

		/**
		 * Process the payment and return the result.
		 *
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {

			$order = wc_get_order( $order_id );

			// Mark as on-hold (we're awaiting the payment)
			$order->update_status( 'on-hold', __( 'Awaiting ABT payment', 'advanced-bank-transfer' ) );
                        
                        $post_data = (array) wp_unslash( wc_clean ( $_POST ));
                        
                        if(isset($post_data['abt_bpr']) && !empty($post_data['abt_bpr'])){
                            $bank_payment_receipt = $post_data['abt_bpr'];
                            $order->update_meta_data( '_bank_payment_receipt', $bank_payment_receipt );
                        }

			// Reduce stock levels
			$order->reduce_order_stock();

			// Remove cart
			WC()->cart->empty_cart();

			// Return thankyou redirect
			return array(
				'result'    => 'success',
				'redirect'  => $this->get_return_url( $order )
			);

		}

		/**
		 * Get country locale if localized.
		 *
		 * @return array
		 */
		public function get_country_locale() {

			if ( empty( $this->locale ) ) {

				// Locale information to be used - only those that are not 'Sort Code'
				$this->locale = apply_filters( 'abt_woocommerce_get_bacs_locale', array(
					'AU' => array(
						'sortcode'	=> array(
							'label'		=> __( 'BSB', 'advanced-bank-transfer' ),
						),
					),
					'CA' => array(
						'sortcode'	=> array(
							'label'		=> __( 'Bank Transit Number', 'advanced-bank-transfer' ),
						),
					),
					'IN' => array(
						'sortcode'	=> array(
							'label'		=> __( 'IFSC', 'advanced-bank-transfer' ),
						),
					),
					'IT' => array(
						'sortcode'	=> array(
							'label'		=> __( 'Branch Sort', 'advanced-bank-transfer' ),
						),
					),
					'NZ' => array(
						'sortcode'	=> array(
							'label'		=> __( 'Bank Code', 'advanced-bank-transfer' ),
						),
					),
					'SE' => array(
						'sortcode'	=> array(
							'label'		=> __( 'Bank Code', 'advanced-bank-transfer' ),
						),
					),
					'US' => array(
						'sortcode'	=> array(
							'label'		=> __( 'Routing Number', 'advanced-bank-transfer' ),
						),
					),
					'ZA' => array(
						'sortcode'	=> array(
							'label'		=> __( 'Branch Code', 'advanced-bank-transfer' ),
						),
					),
				) );

			}

			return $this->locale;

		}
                
            public function payment_fields() {
		$description = $this->get_description();
		if ( $description ) {
			echo wpautop( wptexturize( $description ) );
		}
                echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-bpr-form" class="bank-payment-receipt-form wc-payment-form" style="background:transparent;">';

                // Add this action hook if you want your custom payment gateway to support it
                do_action( 'abt_payment_receipt_form_start', $this->id );

                echo '<div class="form-row form-row-wide"><label>Bank Payment Receipt <span class="required">*</span></label>
                        <input id="abt_bpr_file" name="abt_bpr_file" type="file">
                        <input id="abt_bpr" name="abt_bpr" type="hidden">
                        </div>                        
                        <div class="clear"></div>';

                do_action( 'abt_payment_receipt_form_end', $this->id );

                echo '<div class="clear"></div></fieldset>';
                
                
            }

	}

	add_filter( 'woocommerce_payment_gateways', 'add_direct_deposit' );
	function add_direct_deposit( $methods ) {
	    $methods[] = 'ABT_WC_Gateway';
	    return $methods;
	}
}