(function ($) {

    $(document).ready(function () {

        $('#gravityform-id').change(function () {
            getWeightFields($(this).val());
        });

        $('#enable_cart_shipping_management').on('change', function(e) {
            getWeightFields($('#gravityform-id').val(), $(this).val());
        });

    });

    let $xhr = null;

    function getWeightFields($form_id, type) {

        if (type == 'no') {
            $('#gforms_shipping_field_section').html('');
            return;
        }

        if ($xhr) {
            $xhr.abort();
        }

        const data = {
            action: 'wc_gravityforms_get_shipping_fields',
            wc_gravityforms_security: wc_gf_addons.nonce,
            form_id: $form_id,
            product_id: wc_gf_addons.product_id
        };

        $('#gforms_shipping_field_group').block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });


        $xhr = $.post(ajaxurl, data, function (response) {

            $('#gforms_shipping_field_group').unblock();

            $('#gforms_shipping_field_section').show();
            $('#gforms_shipping_field_section').html(response.data.markup);
        });

    }
})(jQuery);

