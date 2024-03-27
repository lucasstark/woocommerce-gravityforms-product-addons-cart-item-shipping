<?php

/*
 * Plugin Name:  WooCommerce Gravity Forms Product Add-Ons Cart Item Shipping
 * Plugin URI: https://github.com/lucasstark/woocommerce-gravityforms-product-addons-cart-item-shipping
 * Description: This plugin will allow you to use gravity form's fields to set a cart item's shipping details.  Requires the Gravity Forms Product Addons plugin.
 * Version: 1.0.0
 * Author: Lucas Stark
 * Author URI: https://www.elementstark.com/
 * Requires at least: 5.0
 * Tested up to: 6.0

 * Copyright: © 2009-2022 Lucas Stark.
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html

 * WC requires at least: 6.6
 * WC tested up to: 6.7
 */

class ES_GFPA_CartItemShipping_Main {
	private static $instance = null;

	public static function register() {
		if ( self::$instance == null ) {
			self::$instance = new ES_GFPA_CartItemShipping_Main();
		}
	}

	public static $scripts_version = '1.0.1';

	public function __construct() {
		require 'ES_GFPA_CartItemShipping.php';

		add_action( 'admin_enqueue_scripts', array( $this, 'on_admin_enqueue_scripts' ), 100 );

		add_action( 'wp_ajax_wc_gravityforms_get_shipping_fields', array( $this, 'get_fields' ) );
		add_action( 'wp_ajax_wc_gravityforms_get_form', array( $this, 'get_form' ) );
		add_action( 'wp_ajax_wc_gravityforms_save_shipping_mapping', array( $this, 'save_shipping_mapping' ) );

		add_filter( 'woocommerce_gravityforms_before_save_metadata', [ $this, 'on_before_save_metadata' ] );
		add_action( 'woocommerce_gforms_after_field_groups', [ $this, 'render_field_group' ], 10, 2 );

	}

	public static function is_shipping_class_enabled( $product_id, string $slug ) {
		$gravity_form_data = wc_gfpa()->get_gravity_form_data( $product_id );
		if ( isset( $gravity_form_data['cart_shipping_mappings'][ $slug ] ) ) {
			return true;
		}

		return false;
	}

	public function on_admin_enqueue_scripts() {
		wp_enqueue_style( 'gform_admin' );
		wp_enqueue_style( 'esgfpa_shipping', self::plugin_url() . '/assets/styles/admin.css', [ 'gform_admin' ], self::$scripts_version );
		wp_enqueue_script( 'esgfpa_shipping', self::plugin_url() . '/assets/js/admin.js', [
			'jquery',
			'gform_form_admin'
		], self::$scripts_version, true );
	}

	public function on_before_save_metadata( $gravity_form_data ) {

		if ( isset( $_POST['cart_shipping_class_field'] ) ) {
			$gravity_form_data['cart_shipping_class_field'] = $_POST['cart_shipping_class_field'];
		}

		if ( isset( $_POST['enable_cart_shipping_management'] ) ) {
			$gravity_form_data['enable_cart_shipping_management'] = $_POST['enable_cart_shipping_management'];
		}

		if ( isset( $_POST['enable_cart_shipping_class_display'] ) ) {
			$gravity_form_data['enable_cart_shipping_class_display'] = $_POST['enable_cart_shipping_class_display'];
		}

		return $gravity_form_data;
	}

	public function render_field_group( $gravity_form_data, $product_id ) {
		$gravity_form_data = $gravity_form_data; // make it available for the view.
		$product           = wc_get_product( $product_id );
		include 'shipping-options-meta-box.php';
	}


	/** Ajax Handling */
	public function get_form() {
		check_ajax_referer( 'wc_gravityforms_get_products', 'wc_gravityforms_security' );

		$form_id = $_POST['form_id'] ?? 0;
		if ( empty( $form_id ) ) {
			wp_send_json_error( array(
				'status'  => 'error',
				'message' => __( 'No Form ID', 'wc_gf_addons' ),
			) );
			die();
		}
		$form_meta = RGFormsModel::get_form_meta( $form_id );

		$conditional_logic_fields = array();
		$field_settings           = array();
		foreach ( GF_Fields::get_all() as $gf_field ) {
			$settings_arr = $gf_field->get_form_editor_field_settings();
			if ( ! is_array( $settings_arr ) || empty( $settings_arr ) ) {
				continue;
			}

			$settings                          = join( ', .', $settings_arr );
			$settings                          = '.' . $settings;
			$field_settings[ $gf_field->type ] = $settings;

			if ( $gf_field->is_conditional_logic_supported() ) {
				$conditional_logic_fields[] = $gf_field->type;
			}
		}

		$form_meta['conditionalLogicFields'] = $conditional_logic_fields;
		$form_meta['fieldSettings']          = $field_settings;

		$product_id = $_POST['product_id'] ?? 0;
		if ( $product_id ) {
			$gravity_form_data             = wc_gfpa()->get_gravity_form_data( $product_id );

			if ( empty( $gravity_form_data ) ) {
				$gravity_form_data = [];
			}

			if ( ! isset( $gravity_form_data['cart_shipping_mappings'] ) ) {
				$gravity_form_data['cart_shipping_mappings'] = [];
			}

			$form_meta['shippingMappings'] = $gravity_form_data['cart_shipping_mappings'] ?? false;

			$shipping_labels = wp_list_pluck( ES_GFPA_CartItemShipping_Main::get_shipping_classes(), 'name', 'slug' );
			if ( is_array( $form_meta['shippingMappings'] ) ) {
				foreach ( $form_meta['shippingMappings'] as $key => &$mapping ) {
					$mapping['name'] = $shipping_labels[ $key ] ?? 'Unknown';
				}
			}
		}

		wp_send_json( $form_meta );
		die();
	}

	public function get_fields() {
		check_ajax_referer( 'wc_gravityforms_get_products', 'wc_gravityforms_security' );

		$form_id = $_POST['form_id'] ?? 0;
		if ( empty( $form_id ) ) {
			wp_send_json_error( array(
				'status'  => 'error',
				'message' => __( 'No Form ID', 'wc_gf_addons' ),
			) );
			die();
		}

		$product_id     = $_POST['product_id'] ?? 0;
		$selected_field = '';
		if ( $product_id ) {
			$gravity_form_data = wc_gfpa()->get_gravity_form_data( $product_id );
			if ( $gravity_form_data && isset( $gravity_form_data['enable_cart_shipping_management'] ) ) {
				if ( isset( $gravity_form_data['cart_shipping_class_field'] ) ) {
					$selected_field = $gravity_form_data['cart_shipping_class_field'];
				}
			}
		}

		$markup = ES_GFPA_CartItemShipping_Main::get_field_markup( $form_id, $selected_field, $gravity_form_data['enable_cart_shipping_class_display'] ?? 'no' );

		$response = array(
			'status'  => 'success',
			'message' => '',
			'markup'  => $markup
		);

		wp_send_json_success( $response );
		die();
	}

	public function save_shipping_mapping() {
		check_ajax_referer( 'wc_gravityforms_get_products', 'wc_gravityforms_security' );

		$form_id = $_POST['form_id'] ?? 0;
		if ( empty( $form_id ) ) {
			wp_send_json_error( array(
				'status'  => 'error',
				'message' => __( 'No Form ID', 'wc_gf_addons' ),
			) );
			die();
		}

		$product_id  = $_POST['product_id'] ?? 0;

		if ( empty( $product_id ) ) {
			wp_send_json_error( array(
				'status'  => 'error',
				'message' => __( 'No Product ID', 'wc_gf_addons' ),
			) );
			die();
		}

		$object_type = $_POST['objectType'];

		if ( empty( $object_type ) ) {
			wp_send_json_error( array(
				'status'  => 'error',
				'message' => __( 'No Object Type', 'wc_gf_addons' ),
			) );
			die();
		}

		if ( $product_id ) {
			$product           = wc_get_product( $product_id );
			$gravity_form_data = wc_gfpa()->get_gravity_form_data( $product_id );

			if ( empty( $gravity_form_data ) ) {
				$gravity_form_data = [
					'id' => $form_id,
					'enable_cart_shipping_management' => $_POST['enable_cart_shipping_management'] ?? 'no',
					'enable_cart_shipping_class_display' => $_POST['enable_cart_shipping_class_display'] ?? 'no',
					'cart_shipping_mappings' => [],
				];
			}

			if ( ! isset( $gravity_form_data['cart_shipping_mappings'] ) ) {
				$gravity_form_data['cart_shipping_mappings'] = [];
			}

			$gravity_form_data['cart_shipping_mappings'][ $object_type ] = $_POST['data'];
			$product->update_meta_data( '_gravity_form_data', $gravity_form_data );
			$product->save_meta_data();
		}

		wp_send_json_success( 'OK' );
	}

	/** Helper functions ***************************************************** */

	/**
	 * Get the plugin url.
	 *
	 * @access public
	 * @return string
	 */
	public static function plugin_url() {
		return plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) );
	}


	public static function get_field_markup( $form_id, $selected_field = '', $show_shipping_class = 'no' ) {
		$form   = GFAPI::get_form( $form_id );
		$fields = GFAPI::get_fields_by_type( $form, array( 'option', 'choice', 'singleproduct', 'hidden' ), false );

		if ( $fields ) {

			ob_start();

			woocommerce_wp_select( array(
				'id'          => 'enable_cart_shipping_class_display',
				'label'       => __( 'Show Shipping Classes?', 'wc_gf_addons' ),
				'value'       => $show_shipping_class,
				'options'     => array(
					'no'  => __( 'No', 'wc_gf_addons' ),
					'yes' => __( 'Yes', 'wc_gf_addons' )
				),
				'description' => __( 'Choose to show the the cart item\'s shipping classes in the cart.  Useful for debugging purposes.', 'wc_gf_addons' )
			) );

			$markup = ob_get_clean();
		} else {
			$markup = '<p class="form-field">' . __( 'No suitable fields found.', 'wc_gf_addons' ) . '</p>';
		}

		return $markup;
	}

	public static function get_shipping_class_by_id( $shipping_class_id ) {
		$shipping_class_term = get_term( $shipping_class_id, 'product_shipping_class' );

		if ( ! is_wp_error( $shipping_class_term ) && is_a( $shipping_class_term, 'WP_Term' ) ) {
			return $shipping_class_term;
		} else {
			return false;
		}
	}

	public static function get_shipping_classes() {
		$shipping_classes = get_terms( [
			'taxonomy'   => 'product_shipping_class',
			'hide_empty' => false,
			'orderby'    => 'name'
		] );

		return $shipping_classes;

	}
}

ES_GFPA_CartItemShipping_Main::register();
