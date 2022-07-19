<?php

/**
 * Class ES_GFPA_CartItemShipping
 *
 * Allows for a product to use a set of Gravity Forms fields to set the cart item's weight
 *
 */
class ES_GFPA_CartItemShipping {

	private static $instance;

	public static function register() {
		if ( self::$instance == null ) {
			self::$instance = new ES_GFPA_CartItemShipping();
		}
	}

	protected $form_id;
	protected $fields;
	protected $display;

	protected function __construct() {


		//Add these filter after the Gravity Forms Product Addons, which is priority 10.
		add_filter( 'woocommerce_add_cart_item', array( $this, 'add_cart_item' ), 11, 1 );

		add_filter( 'woocommerce_get_cart_item_from_session', array(
			$this,
			'get_cart_item_from_session'
		), 11, 2 );

		add_filter( 'woocommerce_get_item_data', [ $this, 'display_cart_item_shipping_class' ], 11, 2 );

		add_action( 'woocommerce_before_calculate_totals', [ $this, 'set_custom_cart_item_shipping_class' ], 25, 1 );

		// helper to show the shipping class id
		add_filter( 'woocommerce_shipping_classes_columns', [ $this, 'add_shipping_class_column' ] );
		add_action( 'woocommerce_shipping_classes_column_id', [ $this, 'populate_shipping_class_column' ] );
	}

	public function add_shipping_class_column( $shipping_class_columns ) {
		$shipping_class_columns = array_slice( $shipping_class_columns, 0, 2 ) + array( 'id' => 'ID' ) + array_slice( $shipping_class_columns, 2, 3 );

		return $shipping_class_columns;
	}


	public function populate_shipping_class_column() {
		echo '{{ data.term_id }}';
	}

	public function add_cart_item( $cart_item ) {

		//Adjust weight if required based on the gravity form data
		if ( isset( $cart_item['_gravity_form_lead'] ) && isset( $cart_item['_gravity_form_data'] ) ) {
			$the_product_id = $cart_item['data']->get_id();
			if ( $cart_item['data']->is_type( 'variation' ) ) {
				$the_product_id = $cart_item['data']->get_parent_id();
			}

			$product           = wc_get_product( $the_product_id );
			$gravity_form_data = wc_gfpa()->get_gravity_form_data( $product->get_id() );
			$gravity_form_lead = $cart_item['_gravity_form_lead'];

			if ( ! isset( $gravity_form_data['enable_cart_shipping_management'] ) || $gravity_form_data['enable_cart_shipping_management'] != 'yes' ) {

				if ( $product->get_shipping_class_id() ) {
					$cart_item['data']->set_shipping_class_id( $product->get_shipping_class_id() );
				}

				return $cart_item;
			}

			//Store the original weight
			$cart_item['shipping_class']['default'] = $product->get_shipping_class_id();

			$form_meta = RGFormsModel::get_form_meta( $gravity_form_data['id'] );

			//Something wrong with the form, just return the cart item.
			if ( empty( $form_meta ) ) {
				return $cart_item;
			}

			$new_shipping_class_id = $this->calculate_shipping_class( $gravity_form_data, $gravity_form_lead );

			// Set the new calculated shipping class id
			if ( $new_shipping_class_id !== false ) {
				$cart_item['shipping_class']['new'] = $new_shipping_class_id;
				$cart_item['data']->set_shipping_class_id( $new_shipping_class_id );
			}
		}

		return $cart_item;
	}

	/**
	 * When the item is being restored from the session, call the add_cart_item function to re-calculate the cart item price.
	 *
	 * @param $cart_item
	 * @param $values
	 *
	 * @return mixed
	 */
	public function get_cart_item_from_session( $cart_item, $values ) {

		if ( isset( $cart_item['_gravity_form_lead'] ) && isset( $cart_item['_gravity_form_data'] ) ) {
			return $this->add_cart_item( $cart_item );
		} else {
			return $cart_item;
		}

	}

	public function display_cart_item_shipping_class( $item_data, $cart_item ) {
		$the_product_id = $cart_item['data']->get_id();
		if ( $cart_item['data']->is_type( 'variation' ) ) {
			$the_product_id = $cart_item['data']->get_parent_id();
		}
		$gravity_form_data = wc_gfpa()->get_gravity_form_data( $the_product_id );
		$show              = $gravity_form_data['enable_cart_shipping_class_display'] ?? 'no';
		if ( $show == 'yes' && isset( $cart_item['shipping_class'] ) ) {
			// Display original shipping class name
			if ( isset( $cart_item['shipping_class']['default'] ) &&  $cart_item['shipping_class']['default'] != 0 ) {
				$default_shipping_class = ES_GFPA_CartItemShipping_Main::get_shipping_class_by_id( $cart_item['shipping_class']['default'] ?? false );
				$item_data[]            = array(
					'key'   => __( 'Shipping Class (default)', 'woocommerce' ),
					'value' => $default_shipping_class ? $default_shipping_class->name : $cart_item['shipping_class']['default']
				);
			}

			// Display new shipping class name
			if ( isset( $cart_item['shipping_class']['new'] ) ) {
				$new_shipping_class = ES_GFPA_CartItemShipping_Main::get_shipping_class_by_id( $cart_item['shipping_class']['new'] ?? false );
				$item_data[]        = array(
					'key'   => __( 'Shipping Class (new)', 'woocommerce' ),
					'value' => $new_shipping_class ? $new_shipping_class->name : $cart_item['shipping_class']['new']
				);
			}
		}

		return $item_data;
	}

	public function set_custom_cart_item_shipping_class( $cart ) {

		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			$the_product_id = $cart_item['data']->get_id();
			if ( $cart_item['data']->is_type( 'variation' ) ) {
				$the_product_id = $cart_item['data']->get_parent_id();
			}

			$gravity_form_data = wc_gfpa()->get_gravity_form_data( $the_product_id );

			if ( isset( $gravity_form_data['enable_cart_shipping_management'] ) && $gravity_form_data['enable_cart_shipping_management'] == 'yes' ) {
				if ( isset( $cart_item['shipping_class']['new'] ) ) {
					$cart_item['data']->set_shipping_class_id( $cart_item['shipping_class']['new'] );
				}
			} else {
				if ( isset( $cart_item['shipping_class']['default'] ) ) {
					$cart_item['data']->set_shipping_class_id( $cart_item['shipping_class']['default'] );
				}
			}
		}
	}

	private function calculate_shipping_class( $gravity_form_data, $gravity_form_lead ) {

		$form_meta = RGFormsModel::get_form_meta( $gravity_form_data['id'] );

		//Something wrong with the form, just return the cart item.
		if ( empty( $form_meta ) ) {
			return false;
		}

		$new_shipping_class_id   = false;
		$shipping_class_field_id = $gravity_form_data['cart_shipping_class_field'] ?? false;

		if ( isset( $gravity_form_data['cart_shipping_mappings'] ) ) {
			$shipping_classes = ES_GFPA_CartItemShipping_Main::get_shipping_classes();
			$shipping_classes = wp_list_pluck($shipping_classes, 'term_id', 'slug');
			foreach ( $gravity_form_data['cart_shipping_mappings'] as $key => $mapping ) {
				if ( $mapping['conditionalLogic']['enabled'] !== 'yes' ) {
					continue;
				}
				if ( $this->evaluate_conditional_logic( $form_meta, $mapping['conditionalLogic'], [], $gravity_form_lead ) ) {
					$new_shipping_class_id = $shipping_classes[$key] ?? false;
				}
			}
		} else if ( $shipping_class_field_id && isset( $gravity_form_lead[ $shipping_class_field_id ] ) ) {
			$field = GFAPI::get_field( $gravity_form_data['id'], $shipping_class_field_id );
			$value = RGFormsModel::get_lead_field_value( $gravity_form_lead, $field );

			// use lead field display so that values are properly gathered from product and product option fields.
			$display_value         = GFCommon::get_lead_field_display( $field, $value, $gravity_form_lead["currency"] ?? false );
			$new_shipping_class_id = $this->map_value_to_shipping_class( $display_value );
		}


		return $new_shipping_class_id;
	}

	private function map_value_to_shipping_class( $value ) {
		return apply_filters( 'wc_gfpa_map_value_to_shipping_class', $value );
	}


	private function evaluate_conditional_logic( $form, $logic, $field_values, $entry = null ) {

		if ( empty( $logic ) ) {
			return true;
		}

		$match_count = 0;
		foreach ( $logic['rules'] as $rule ) {
			try {
				/**
				 * Filter the conditional logic rule before it is evaluated.
				 *
				 * @param array $rule The conditional logic rule about to be evaluated.
				 * @param array $form The current form meta.
				 * @param array $logic All details required to evaluate an objects conditional logic.
				 * @param array $field_values The default field values for this form.
				 * @param array $entry The current entry object (if available).
				 *
				 * @since 2.4.22
				 *
				 */
				$rule = apply_filters( 'gform_rule_pre_evaluation', $rule, $form, $logic, $field_values, $entry );
			} catch ( Error $e ) {
				GFCommon::log_error( __METHOD__ . '(): Error from function hooked to gform_rule_pre_evaluation. ' . $e->getMessage() );
			}
			$source_field = RGFormsModel::get_field( $form, $rule['fieldId'] );
			$source_value = empty( $entry ) ? RGFormsModel::get_field_value( $source_field, $field_values ) : RGFormsModel::get_lead_field_value( $entry, $source_field );

			/**
			 * Filter the source value of a conditional logic rule before it is compared with the target value.
			 *
			 * @param int|string $source_value The value of the rule's configured field ID, entry meta, or custom property.
			 * @param array $rule The conditional logic rule that is being evaluated.
			 * @param array $form The current form meta.
			 * @param array $logic All details required to evaluate an objects conditional logic.
			 * @param array $entry The current entry object (if available).
			 *
			 * @since 2.6.2
			 *
			 */
			$source_value = apply_filters( 'gform_rule_source_value', $source_value, $rule, $form, $logic, $entry );

			$is_value_match = RGFormsModel::is_value_match( $source_value, $rule['value'], $rule['operator'], $source_field, $rule, $form );

			if ( $is_value_match ) {
				$match_count ++;
			}
		}

		$do_action = ( $logic['logicType'] == 'all' && $match_count == sizeof( $logic['rules'] ) ) || ( $logic['logicType'] == 'any' && $match_count > 0 );
		$is_hidden = ( $do_action && $logic['actionType'] == 'hide' ) || ( ! $do_action && $logic['actionType'] == 'show' );

		return ! $is_hidden;
	}

}

ES_GFPA_CartItemShipping::register();
