<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * DBBL Mobile Banking Payment Gateway
 *
 * Provides a dbblmb Payment Gateway, mainly for BD Shops who are accepting Dutch Bangla Mobile Banking.
 *
 * @class 		WC_Gateway_dbblmb
 * @extends		WC_Payment_Gateway
 * @version		2.1.0
 * @package		WooCommerce/Classes/Payment
 * @author 		Jabed Shoeb
 */
class WC_Gateway_dbblmb extends WC_Payment_Gateway {

    /**
     * Constructor for the gateway.
     */
    public function __construct() {
		$this->id                 = 'dbblmb';
		$this->icon               = apply_filters('woocommerce_dbblmb_icon', plugins_url( '../../../images/dbblmb.png', __FILE__ ));
		$this->has_fields         = false;
		$this->method_title       = __( 'DBBLMB', 'woocommerce' );
		$this->method_description = __( 'Allows payments by Dutch Bangla Bank Ltd. Mobile Banking, more commonly known as mobile banking transfer.', 'woocommerce' );

		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();

        // Define user set variables
		$this->title        = $this->get_option( 'title' );
		$this->description  = $this->get_option( 'description' );
		$this->instructions = $this->get_option( 'instructions', $this->description );

		// BACS account fields shown on the thanks page and in emails
		$this->account_details = get_option( 'woocommerce_dbblmb_accounts',
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
    	add_action( 'woocommerce_thankyou_dbblmb', array( $this, 'thankyou_page' ) );

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
				'label'   => __( 'Enable DBBL Mobile Banking', 'woocommerce' ),
				'default' => 'yes'
			),
			'title' => array(
				'title'       => __( 'Title', 'woocommerce' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
				'default'     => __( 'DBBL Mobile Banking', 'woocommerce' ),
				'desc_tip'    => true,
			),
			'description' => array(
				'title'       => __( 'Description', 'woocommerce' ),
				'type'        => 'textarea',
				'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
				'default'     => __( 'Make your payment directly into our DBBL Mobile Banking account. Please use your Order ID and last 4 digit of DBBL Mobile Banking sending # as the payment reference when sending email to us. Your order won\'t be process until the funds have cleared in our account.', 'woocommerce' ),
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
            <td class="forminp" id="dbblmb_accounts">
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
		                			<td><input type="text" value="' . esc_attr( $account['account_type'] ) . '" name="dbblmb_account_type[' . $i . ']" /></td>
		                			<td><input type="text" value="' . esc_attr( $account['account_number'] ) . '" name="dbblmb_account_number[' . $i . ']" /></td>
			                    </tr>';
		            		}
		            	}
		            	?>
		        	</tbody>
		        </table>
		       	<script type="text/javascript">
					jQuery(function() {
						jQuery('#dbblmb_accounts').on( 'click', 'a.add', function(){

							var size = jQuery('#dbblmb_accounts tbody .account').size();

							jQuery('<tr class="account">\
		                			<td class="sort"></td>\
		                			<td><input type="text" name="dbblmb_account_type[' + size + ']" /></td>\
		                			<td><input type="text" name="dbblmb_account_number[' + size + ']" /></td>\
			                    </tr>').appendTo('#dbblmb_accounts table tbody');

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

    	if ( isset( $_POST['dbblmb_account_type'] ) ) {

			$account_types   = array_map( 'wc_clean', $_POST['dbblmb_account_type'] );
			$account_numbers = array_map( 'wc_clean', $_POST['dbblmb_account_number'] );

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

    	update_option( 'woocommerce_dbblmb_accounts', $accounts );
    }

    /**
     * Output for the order received page.
     */
    public function thankyou_page( $order_id ) {
		if ( $this->instructions ) {
        	echo wpautop( wptexturize( wp_kses_post( $this->instructions ) ) );
        }
        $this->dbblmb_details( $order_id );
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

    	if ( $sent_to_admin || $order->status !== 'on-hold' || $order->payment_method !== 'dbblmb' ) {
    		return;
    	}

		if ( $this->instructions ) {
        	echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
        }

		$this->dbblmb_details( $order->id );
    }

    /**
     * Get bKash details and place into a list format
     */
    private function dbblmb_details( $order_id = '' ) {
    	if ( empty( $this->account_details ) ) {
    		return;
    	}

    	echo '<h2>' . __( 'Our Dutch Bangla Mobile Banking Details', 'woocommerce' ) . '</h2>' . PHP_EOL;

    	$dbblmb_accounts = apply_filters( 'woocommerce_dbblmb_accounts', $this->account_details );

    	if ( ! empty( $dbblmb_accounts ) ) {
	    	foreach ( $dbblmb_accounts as $dbblmb_account ) {
	    		echo '<ul class="order_details dbblmb_details">' . PHP_EOL;

	    		$dbblmb_account = (object) $dbblmb_account;

	    		// dbblmb account fields shown on the thanks page and in emails
				$account_fields = apply_filters( 'woocommerce_dbblmb_account_fields', array(
					'account_type'=> array(
						'label' => __( 'Account Type', 'woocommerce' ),
						'value' => $dbblmb_account->account_type
					),
					'account_number'=> array(
						'label' => __( 'Account Number', 'woocommerce' ),
						'value' => $dbblmb_account->account_number
					)
				), $order_id );

				if ( $dbblmb_account->account_type || $dbblmb_account->account_number ) {
					echo '<h3>' . implode( ' - ', array_filter( array( $dbblmb_account->account_type, $dbblmb_account->account_number ) ) ) . '</h3>' . PHP_EOL;
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
