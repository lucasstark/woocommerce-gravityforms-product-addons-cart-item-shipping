<div class="wc-product-data-metabox-group-field">
    <div class="wc-product-data-metabox-group-field-title">
        <a href="javascript:;"><?php _e( 'Shipping Options', 'wc_gf_addons' ); ?></a>
    </div>

    <div id="gforms_shipping_field_group" class="wc-product-data-metabox-group-field-content"
         style="display:none;">

        <div class="gforms-panel options_group" <?php echo empty( $gravity_form_data['id'] ) ? "style='display:none;'" : ''; ?>>
            <div class="wc-product-data-metabox-option-group-label">
				<?php _e( 'Weight Calculation Field Options', 'wc_gf_addons' ); ?>
                <p style="font-weight: normal;">
					<?php _e( 'Options for setting a field to use as a cart items weight', 'wc_gf_addons' ); ?>
                </p>
            </div>

			<?php
			woocommerce_wp_select( array(
				'id'          => 'enable_cart_shipping_management',
				'label'       => __( 'Control Shipping?', 'wc_gf_addons' ),
				'value'       => $gravity_form_data['enable_cart_shipping_management'] ?? 'no',
				'options'     => array(
					'no'  => __( 'No', 'wc_gf_addons' ),
					'yes' => __( 'Yes', 'wc_gf_addons' )
				),
				'description' => __( 'Choose to control the cart item\'s shipping class.', 'wc_gf_addons' )
			) );
			?>

            <div id="gforms_shipping_field_section">
				<?php if ( isset( $gravity_form_data['enable_cart_shipping_management'] ) && $gravity_form_data['enable_cart_shipping_management'] == 'yes' ): ?>
					<?php echo ES_GFPA_CartItemShipping_Main::get_field_markup( $gravity_form_data['id'], $gravity_form_data['cart_shipping_class_field'] ?? '', $gravity_form_data['enable_cart_shipping_class_display'] ?? 'no' ); ?>
				<?php endif; ?>

				<?php
				$shipping_classes = ES_GFPA_CartItemShipping_Main::get_shipping_classes();
				if ( $shipping_classes ) :
					foreach ( $shipping_classes as $shipping_class ) :
						?>
                        <p><a title="Configure <?php echo $shipping_class->name; ?> Mappings"
                              href="#TB_inline?&width=500&height=800&inlineId=gforms_shipping_map_builder_container"
                              onclick="ES_GRPA_CreateMapLogic('<?php echo $shipping_class->slug; ?>', '<?php echo $shipping_class->name; ?>');"
                              class="thickbox button">Configure <?php echo $shipping_class->name; ?> Mappings</a></p>
					<?php endforeach; ?>
				<?php endif; ?>

                <div id="gforms_shipping_map_builder_container" style="display:none;">
                    <div id="gforms_shipping_map_builder">
                        <div class="conditional_logic_flyout">
                            <div class="conditional_logic_flyout__body panel-block-tabs__body--settings gform-initialized">
                                <div class="conditional_logic_flyout__toggle">
                                    <span class="conditional_logic_flyout__toggle_label">
                                        Enable Mapping
                                    </span>
                                    <div class="conditional_logic_flyout__toggle_input gform-field__toggle">
                                        <span class="gform-settings-input__container">
                                            <input type="checkbox" class="gform-field__toggle-input" data-js-conditonal-toggle="" id="shipping_logic_enabled_field">
                                            <label class="gform-field__toggle-container" for="shipping_logic_enabled_field">
                                                <span class="gform-field__toggle-switch-text screen-reader-text">Enabled</span>
                                                <span class="gform-field__toggle-switch"></span>
                                            </label>
                                        </span>
                                    </div>
                                </div>
                                <div class="conditional_logic_flyout__main">
                                    <fieldset class="conditional-flyout__main-fields active">
                                        <div id="shipping_mapping_logic_container"></div>
                                    </fieldset>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        </div>
    </div>
</div>
