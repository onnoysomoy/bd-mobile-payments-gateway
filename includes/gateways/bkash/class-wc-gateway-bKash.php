<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * bKash Payment Gateway
 *
 * Provides a bKash Payment Gateway, mainly for BD Shops who are accepting bKash.
 *
 * @class 		WC_Gateway_bKash
 * @extends		WC_Payment_Gateway
 * @version		2.1.0
 * @package		WooCommerce/Classes/Payment
 * @author 		Jabed Shoeb
 */
class WC_Gateway_bkash extends WC_Payment_Gateway {

    /**
     * Constructor for the gateway.
     */
    public function __construct() {
		$this->id                 = 'bkash';
		$this->icon               = apply_filters('woocommerce_dbblmb_icon', plugins_url( '../../../images/bKash.png', __FILE__ ));
		$this->has_fields         = false;
		$this->method_title       = __( 'Bkash', 'woocommerce' );
		$this->method_description = __( 'Allows payments by bKash. Mobile Banking, more commonly known as mobile banking transfer.', 'woocommerce' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

        // Define user set variables
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions', $this->description );

		// bKash account fields shown on the thanks page and in emails
		$this->account_details = get_option( 'woocommerce_bkash_accounts',
			array(
				array(
					'account_type'   => $this->get_option( 'account_type' ),
					'account_number' => $this->get_option( 'account_number' )
				)
			)
		);

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'save_account_details' ) );
    	add_action( 'woocommerce_thankyou_bkash', array( $this, 'thankyou_page' ) );

    	// Customer Emails
    	add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 3 );
    }

    /**
     * Initialise Gateway Settings Form Fields
     */
    public function init_form_fields() {
    	$this->form_fields = array(
			'enabled' => array(
				'title'   => __( 'Enable/Disable', 'woocommerce' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable bKash Mobile Banking', 'woocommerce' ),
				'default' => 'yes'
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
				'default'     => __( 'bKash Mobile Banking', 'woocommerce' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
				'default'     => __( 'Make your payment directly into our bKash Mobile Banking account. Please use your Order ID and last 4 digit of bKash Mobile Banking sending # as the payment reference when sending email to us. Your order won\'t be process until the funds have cleared in our account.', 'woocommerce' ),
				'desc_tip'    => true,
			),
			'instructions' => array(
				'title'       => __( 'Instructions', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Instructions that will be added to the thank you page and emails.', 'woocommerce' ),
				'default'     => '',
				'desc_tip'    => true,
			),
			'account_details' => array(
				'type'        => 'account_details'
			),
		);
    }

    /**
     * generate_account_details_html function.
     */
    public function generate_account_details_html() {
    	ob_start();
	    ?>
	    <tr valign="top">
            <th scope="row" class="titledesc"><?php _e( 'Account Details', 'woocommerce' ); ?>:</th>
            <td class="forminp" id="bkash_accounts">
			    <table class="widefat wc_input_table sortable" cellspacing="0">
		    		<thead>
		    			<tr>
		    				<th class="sort">&nbsp;</th>
		    				<th><?php _e( 'Account Type', 'woocommerce' ); ?></th>
			            	<th><?php _e( 'Account Number', 'woocommerce' ); ?></th>
		    			</tr>
		    		</thead>
		    		<tfoot>
		    			<tr>
		    				<th colspan="7"><a href="#" class="add button"><?php _e( '+ Add Account', 'woocommerce' ); ?></a> <a href="#" class="remove_rows button"><?php _e( 'Remove selected account(s)', 'woocommerce' ); ?></a></th>
		    			</tr>
		    		</tfoot>
		    		<tbody class="accounts">
		            	<?php
		            	$i = -1;
		            	if ( $this->account_details ) {
		            		foreach ( $this->account_details as $account ) {
		                		$i++;

		                		echo '<tr class="account">
		                			<td class="sort"></td>
		                			<td><input type="text" value="' . esc_attr( $account['account_type'] ) . '" name="bkash_account_type[' . $i . ']" /></td>
		                			<td><input type="text" value="' . esc_attr( $account['account_number'] ) . '" name="bkash_account_number[' . $i . ']" /></td>
			                    </tr>';
		            		}
		            	}
		            	?>
		        	</tbody>
		        </table>
		       	<script type="text/javascript">
					jQuery(function() {
						jQuery('#bkash_accounts').on( 'click', 'a.add', function(){

							var size = jQuery('#bkash_accounts tbody .account').size();

							jQuery('<tr class="account">\
		                			<td class="sort"></td>\
		                			<td><input type="text" name="bkash_account_type[' + size + ']" /></td>\
		                			<td><input type="text" name="bkash_account_number[' + size + ']" /></td>\
			                    </tr>').appendTo('#bkash_accounts table tbody');

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
     * Save account details table
     */
    public function save_account_details() {
    	$accounts = array();

    	if ( isset( $_POST['bkash_account_type'] ) ) {

			$account_types   = array_map( 'wc_clean', $_POST['bkash_account_type'] );
			$account_numbers = array_map( 'wc_clean', $_POST['bkash_account_number'] );

			foreach ( $account_types as $i => $type ) {
				if ( ! isset( $account_types[ $i ] ) ) {
					continue;
				}

	    		$accounts[] = array(
	    			'account_type'   => $account_types[ $i ],
					'account_number' => $account_numbers[ $i ]
	    		);
	    	}
    	}

    	update_option( 'woocommerce_bkash_accounts', $accounts );
    }

    /**
     * Output for the order received page.
     */
    public function thankyou_page( $order_id ) {
		if ( $this->instructions ) {
        	echo wpautop( wptexturize( wp_kses_post( $this->instructions ) ) );
        }
        $this->bkash_details( $order_id );
    }

    /**
     * Add content to the WC emails.
     *
     * @access public
     * @param WC_Order $order
     * @param bool $sent_to_admin
     * @param bool $plain_text
     * @return void
     */
    public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

    	if ( $sent_to_admin || $order->status !== 'on-hold' || $order->payment_method !== 'bkash' ) {
    		return;
    	}

		if ( $this->instructions ) {
        	echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
        }

		$this->bkash_details( $order->id );
    }

    /**
     * Get bKash details and place into a list format
     */
    private function bkash_details( $order_id = '' ) {
    	if ( empty( $this->account_details ) ) {
    		return;
    	}

    	echo '<h2>' . __( 'Our bKash Mobile Banking Details', 'woocommerce' ) . '</h2>' . PHP_EOL;

    	$bkash_accounts = apply_filters( 'woocommerce_bkash_accounts', $this->account_details );

    	if ( ! empty( $bkash_accounts ) ) {
	    	foreach ( $bkash_accounts as $bkash_account ) {
	    		echo '<ul class="order_details bkash_details">' . PHP_EOL;

	    		$bkash_account = (object) $bkash_account;

	    		// bkash account fields shown on the thanks page and in emails
				$account_fields = apply_filters( 'woocommerce_bkash_account_fields', array(
					'account_type'=> array(
						'label' => __( 'Account Type', 'woocommerce' ),
						'value' => $bkash_account->account_type
					),
					'account_number'=> array(
						'label' => __( 'Account Number', 'woocommerce' ),
						'value' => $bkash_account->account_number
					)
				), $order_id );

				if ( $bkash_account->account_type || $bkash_account->account_number ) {
					echo '<h3>' . implode( ' - ', array_filter( array( $bkash_account->account_type, $bkash_account->account_number ) ) ) . '</h3>' . PHP_EOL;
				}

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
     * Process the payment and return the result
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment( $order_id ) {

		$order = new WC_Order( $order_id );

		// Mark as on-hold (we're awaiting the payment)
		$order->update_status( 'on-hold', __( 'Awaiting bKash payment', 'woocommerce' ) );

		// Reduce stock levels
		$order->reduce_order_stock();

		// Remove cart
		WC()->cart->empty_cart();

		// Return thankyou redirect
		return array(
			'result' 	=> 'success',
			'redirect'	=> $this->get_return_url( $order )
		);
    }
}
