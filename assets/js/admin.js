$wc_gfpa_xhr = null;

function ES_GFPA_LoadForm(formId, callback) {
    jQuery('.conditional_logic_flyout').block({
        message: null,
        overlayCSS: {
            background: '#fff',
            opacity: 0.6
        }
    });

    if ($wc_gfpa_xhr) {
        $wc_gfpa_xhr.abort();
    }

    const data = {
        action: 'wc_gravityforms_get_form',
        wc_gravityforms_security: wc_gf_addons.nonce,
        form_id: formId,
        product_id: wc_gf_addons.product_id
    };

    $wc_gfpa_xhr = jQuery.post(ajaxurl, data, function (responseData) {
        jQuery('.conditional_logic_flyout').unblock();
        window.form = responseData;
        window.GetConditionalLogicFields = function () {
            return window.form.conditionalLogicFields;
        }
        if (callback !== undefined) {
            callback();
        }
    });
}


function ES_GFPA_SaveShippingMapping(objectType) {
    jQuery('.conditional_logic_flyout').block({
        message: null,
        overlayCSS: {
            background: '#fff',
            opacity: 0.6
        }
    });

    if ($wc_gfpa_xhr) {
        $wc_gfpa_xhr.abort();
    }

    const data = {
        action: 'wc_gravityforms_save_shipping_mapping',
        wc_gravityforms_security: wc_gf_addons.nonce,
        product_id: wc_gf_addons.product_id,
        objectType: objectType,
        form_id: jQuery('#gravityform-id').val(),
        enable_cart_shipping_management: jQuery('#enable_cart_shipping_management').val(),
        enable_cart_shipping_class_display: jQuery('#enable_cart_shipping_class_display').val(),
        data: ES_GFPA_GetConditionalObject(objectType)
    };

    $wc_gfpa_xhr = jQuery.post(ajaxurl, data, function (responseData) {
        jQuery('.conditional_logic_flyout').unblock();
    });
}


function ES_GFPA_GetConditionalObject(shippingClass) {
    return window.form.shippingMappings[shippingClass];
}

function ES_GFPA_GetRuleOperators( objectType, i, fieldId, selectedOperator ) {
    var str, supportedOperators, operators, selected;
    supportedOperators = {"is":"is","isnot":"isNot", ">":"greaterThan", "<":"lessThan", "contains":"contains", "starts_with":"startsWith", "ends_with":"endsWith"};
    str = "<select id='" + objectType + "_rule_operator_" + i + "' class='gfield_rule_select' onchange='ES_GFPA_SetRuleProperty(\"" + objectType + "\", " + i + ", \"operator\", jQuery(this).val());var valueSelector=\"#" + objectType + "_rule_value_" + i + "\"; jQuery(valueSelector).replaceWith(ES_GFPA_GetRuleValues(\"" + objectType + "\", " + i + ",\"" + fieldId + "\", \"\"));jQuery(valueSelector).change();'>";
    operators = IsEntryMeta(fieldId) ? GetOperatorsForMeta(supportedOperators, fieldId) : supportedOperators;

    operators = gform.applyFilters( 'gform_conditional_logic_operators', operators, objectType, fieldId );
    jQuery.each(operators,function(operator, stringKey){
        var operatorText = gf_vars[stringKey];
        if ( undefined === operatorText ) {
            // If the operator text has been filtered, it may not be in the gf_vars array.
            operatorText = stringKey;
        }
        selected = selectedOperator == operator ? "selected='selected'" : "";
        str += "<option value='" + operator + "' " + selected + ">" + operatorText + "</option>"
    });
    str +="</select>";
    return str;
}

function ES_GFPA_GetRuleFields(objectType, ruleIndex, selectedFieldId) {

    let str = "<select id='" + objectType + "_rule_field_" + ruleIndex + "' class='gfield_rule_select' onchange='jQuery(\"#" + objectType + "_rule_operator_" + ruleIndex + "\").replaceWith(ES_GFPA_GetRuleOperators(\"" + objectType + "\", " + ruleIndex + ", jQuery(this).val()));jQuery(\"#" + objectType + "_rule_value_" + ruleIndex + "\").replaceWith(ES_GFPA_GetRuleValues(\"" + objectType + "\", " + ruleIndex + ", jQuery(this).val())); ES_GFPA_SetRule(\"" + objectType + "\", " + ruleIndex + "); '>";
    let options = [];

    for (var i = 0; i < form.fields.length; i++) {

        var field = form.fields[i];

        if (ES_GFPA_IsConditionalLogicField(field)) {

            // @todo: the inputType check will likely go away once we've figured out how we're going to manage inputs moving forward
            if (field.inputs && jQuery.inArray(GetInputType(field), ['checkbox', 'email', 'consent']) == -1) {
                for (let j = 0; j < field.inputs.length; j++) {
                    let input = field.inputs[j];
                    if (!input.isHidden) {
                        options.push({
                            label: GetLabel(field, input.id),
                            value: input.id
                        });
                    }
                }
            } else {
                options.push({
                    label: GetLabel(field),
                    value: field.id
                });
            }

        }
    }

    str += GetRuleFieldsOptions(options, selectedFieldId == 0 ? options[0].value : selectedFieldId);
    str += "</select>";
    return str;
}

function ES_GFPA_IsConditionalLogicField(field) {
    const inputType = field.inputType ? field.inputType : field.type;
    const supported_fields = window.form.conditionalLogicFields;

    const index = jQuery.inArray(inputType, supported_fields);
    const isConditionalLogicField = index >= 0 ? true : false;
    return isConditionalLogicField;
}

function ES_GFPA_GetRuleValues(objectType, ruleIndex, selectedFieldId, selectedValue, inputName) {

    if (!inputName) {
        inputName = false;
    }

    const dropdownId = inputName == false ? objectType + '_rule_value_' + ruleIndex : inputName;

    if (selectedFieldId == 0) {
        selectedFieldId = ES_GFPA_GetFirstRuleField();
    }

    if (selectedFieldId == 0) {
        return "";
    }

    const field = GetFieldById(selectedFieldId);
    const isEntryMeta = IsEntryMeta(selectedFieldId);
    const obj = ES_GFPA_GetConditionalObject(objectType);
    const rule = obj["conditionalLogic"]["rules"][ruleIndex];
    const operator = rule.operator;
    let str = "";

    if (field && field["type"] == "post_category" && field["displayAllCategories"]) {

        const dropdown = jQuery('#' + dropdownId + ".gfield_category_dropdown");

        //don't load category drop down if it already exists (to avoid unnecessary ajax requests)
        if (dropdown.length > 0) {

            let options = dropdown.html();
            options = options.replace(/ selected="selected"/g, '');
            options = options.replace("value=\"" + selectedValue + "\"", "value=\"" + selectedValue + "\" selected=\"selected\"");
            str = "<select id='" + dropdownId + "' class='gfield_rule_select es_wcgfpa_gfield_rule_value_dropdown gfield_category_dropdown'>" + options + "</select>";
        } else {
            let placeholderName = inputName == false ? "gfield_ajax_placeholder_" + ruleIndex : inputName + "_placeholder";

            //loading categories via AJAX
            jQuery.post(ajaxurl, {
                    action: "gf_get_post_categories",
                    objectType: objectType,
                    ruleIndex: ruleIndex,
                    inputName: inputName,
                    selectedValue: selectedValue
                },
                function (dropdown_string) {
                    if (dropdown_string) {
                        jQuery('#' + placeholderName).replaceWith(dropdown_string.trim());

                        ES_GFPA_SetRuleProperty(objectType, ruleIndex, "value", jQuery("#" + dropdownId).val());
                    }
                }
            );

            //will be replaced by real drop down during the ajax callback
            str = "<select id='" + placeholderName + "' class='gfield_rule_select'><option>" + gf_vars["loading"] + "</option></select>";
        }
    } else if (field && field.choices && jQuery.inArray(operator, ["is", "isnot"]) > -1) {
        let emptyChoice;
        let ruleChoices;

        if (GetInputType(field) === 'multiselect') {
            emptyChoice = gf_vars.emptyChoice;
        } else if (field.placeholder) {
            emptyChoice = field.placeholder;
        }

        ruleChoices = emptyChoice ? [{
            text: emptyChoice,
            value: ''
        }].concat(field.choices) : field.choices;
        str = ES_GFPA_GetRuleValuesDropDown(ruleChoices, objectType, ruleIndex, selectedValue, inputName);
    } else if (IsAddressSelect(selectedFieldId, field)) {

        //loading categories via AJAX
        jQuery.post(ajaxurl, {
            action: 'gf_get_address_rule_values_select',
            address_type: field.addressType ? field.addressType : gf_vars.defaultAddressType,
            value: selectedValue,
            id: dropdownId,
            form_id: field.formId
        }, function (selectMarkup) {
            if (selectMarkup) {
                $select = jQuery(selectMarkup.trim());
                $placeholder = jQuery('#' + dropdownId);
                $placeholder.replaceWith($select);
                ES_GFPA_SetRuleProperty(objectType, ruleIndex, 'value', $select.val());
            }
        });

        // will be replaced by real drop down during the ajax callback
        str = "<select id='" + dropdownId + "' class='gfield_rule_select'><option>" + gf_vars['loading'] + "</option></select>";

    } else if (isEntryMeta && entry_meta && entry_meta[selectedFieldId] && entry_meta[selectedFieldId].filter && typeof entry_meta[selectedFieldId].filter.choices != 'undefined') {
        str = ES_GFPA_GetRuleValuesDropDown(entry_meta[selectedFieldId].filter.choices, objectType, ruleIndex, selectedValue, inputName);
    } else {
        selectedValue = selectedValue ? selectedValue.replace(/'/g, "&#039;") : "";

        //create a text field for fields that don't have choices (i.e text, textarea, number, email, etc...)
        str = "<input type='text' placeholder='" + gf_vars["enterValue"] + "' class='gfield_rule_select gfield_rule_input' id='" + dropdownId + "' name='" + dropdownId + "' value='" + selectedValue.replace(/'/g, "&#039;") + "' onchange='ES_GFPA_SetRuleProperty(\"" + objectType + "\", " + ruleIndex + ", \"value\", jQuery(this).val());' onkeyup='ES_GFPA_SetRuleProperty(\"" + objectType + "\", " + ruleIndex + ", \"value\", jQuery(this).val());'>";
    }

    str = gform.applyFilters('gform_conditional_logic_values_input', str, objectType, ruleIndex, selectedFieldId, selectedValue)

    return str;
}

function ES_GFPA_GetRuleValuesDropDown(choices, objectType, ruleIndex, selectedValue, inputName) {

    var dropdown_id = inputName == false ? objectType + '_rule_value_' + ruleIndex : inputName;

    //create a drop down for fields that have choices (i.e. drop down, radio, checkboxes, etc...)
    var str = "<select class='gfield_rule_select es_wcgfpa_gfield_rule_value_dropdown' id='" + dropdown_id + "' name='" + dropdown_id + "'>";

    var isAnySelected = false;
    for (var i = 0; i < choices.length; i++) {
        var choiceValue = typeof choices[i].value == "undefined" || choices[i].value == null ? choices[i].text + '' : choices[i].value + '';
        var isSelected = choiceValue == selectedValue;
        var selected = isSelected ? "selected='selected'" : "";
        if (isSelected) {
            isAnySelected = true;
        }
        choiceValue = choiceValue.replace(/'/g, "&#039;");
        let choiceText = ((jQuery('<div>' + choices[i].text + '</div>').text()) === '' ? choiceValue : choices[i].text).trim();
        str += "<option value='" + choiceValue.replace(/'/g, "&#039;") + "' " + selected + ">" + choiceText + "</option>";
    }

    if (!isAnySelected && selectedValue && selectedValue != "") {
        str += "<option value='" + selectedValue.replace(/'/g, "&#039;") + "' selected='selected'>" + selectedValue + "</option>";
    }

    str += "</select>";

    return str;

}

function ES_GFPA_GetFirstRuleField() {
    for (var i = 0; i < form.fields.length; i++) {
        if (ES_GFPA_IsConditionalLogicField(form.fields[i])) {
            return form.fields[i].id;
        }
    }

    return 0;
}

function ES_GFPA_InsertRule(objectType, ruleIndex) {
    let obj = ES_GFPA_GetConditionalObject(objectType);
    obj.conditionalLogic.rules.splice(ruleIndex, 0, new ES_GFPA_MappingRule());
    ES_GRPA_CreateMapLogic(objectType);
    ES_GFPA_SetRule(objectType, ruleIndex);
}

function ES_GFPA_DeleteRule(objectType, ruleIndex) {
    const obj = ES_GFPA_GetConditionalObject(objectType);
    obj.conditionalLogic.rules.splice(ruleIndex, 1);
    ES_GRPA_CreateMapLogic(objectType);
}

function ES_GFPA_SetRule(objectType, ruleIndex) {
    ES_GFPA_SetRuleProperty(objectType, ruleIndex, "fieldId", jQuery("#" + objectType + "_rule_field_" + ruleIndex).val());
    ES_GFPA_SetRuleProperty(objectType, ruleIndex, "operator", jQuery("#" + objectType + "_rule_operator_" + ruleIndex).val());
    ES_GFPA_SetRuleProperty(objectType, ruleIndex, "value", jQuery("#" + objectType + "_rule_value_" + ruleIndex).val());
}

function ES_GFPA_SetRuleProperty(objectType, ruleIndex, name, value) {
    const obj = ES_GFPA_GetConditionalObject(objectType);
    if (!obj.conditionalLogic.rules) {
        return;
    }
    obj.conditionalLogic.rules[ruleIndex][name] = value;
}

function ES_GFPA_SetConditionalProperty(objectType, name, value) {
    var obj = ES_GFPA_GetConditionalObject(objectType);
    obj.conditionalLogic[name] = value;
}

function ES_GFPA_SetRuleValueDropDown(element) {
    //parsing ID to get objectType and ruleIndex
    var ary = element.attr("id").split('_rule_value_');

    if (ary.length < 2) {
        return;
    }

    var objectType = ary[0];
    var ruleIndex = ary[1];

    ES_GFPA_SetRuleProperty(objectType, ruleIndex, "value", element.val());
}

function ES_GFPA_MappingLogic() {
    this.actionType = "show"; //show or hide
    this.logicType = "all"; //any or all
    this.rules = [new ES_GFPA_MappingRule()];
}

function ES_GFPA_MappingRule() {
    this.fieldId = 0;
    this.operator = "is"; //is or isnot
    this.value = "";
}

function ES_GRPA_CreateMapLogic(shippingClass, shippingLabel = '') {
    console.log(shippingLabel);
    if (!window.form) {
        const callback = function () {
            ES_GRPA_CreateMapLogic(shippingClass, shippingLabel);
        }
        ES_GFPA_LoadForm(jQuery('#gravityform-id').val(), callback);
        return false;
    }

    if (!window.form.shippingMappings) {
        window.form.shippingMappings = {};
        window.form.shippingMappings[shippingClass] = {
            conditionalLogic: new ES_GFPA_MappingLogic()
        };
    }

    if (!window.form.shippingMappings[shippingClass]) {
        window.form.shippingMappings[shippingClass] = {
            conditionalLogic: new ES_GFPA_MappingLogic()
        };
    }

    const obj = window.form.shippingMappings[shippingClass];

    const objText = 'When';
    const hideSelected = obj.conditionalLogic.actionType == "hide" ? "selected='selected'" : "";
    const showSelected = obj.conditionalLogic.actionType == "show" ? "selected='selected'" : "";
    const allSelected = obj.conditionalLogic.logicType == "all" ? "selected='selected'" : "";
    const anySelected = obj.conditionalLogic.logicType == "any" ? "selected='selected'" : "";

    const showText = 'Set Shipping Class'
    const hideText = gf_vars.hide;


    const descPieces = {};
    descPieces.title = "<h3>" + shippingLabel + "</h3>";
    descPieces.actionType = "<select class=\"gfield_rule_select\" id='" + shippingClass + "_action_type' onchange='ES_GFPA_SetConditionalProperty(\"" + shippingClass + "\", \"actionType\", jQuery(this).val());'><option value='show' " + showSelected + ">" + showText + "</option></select>";
    descPieces.objectDescription = objText;
    descPieces.logicType = "<select class=\"gfield_rule_select\" id='" + shippingClass + "_logic_type' onchange='ES_GFPA_SetConditionalProperty(\"" + shippingClass + "\", \"logicType\", jQuery(this).val());'><option value='all' " + allSelected + ">" + gf_vars.all + "</option><option value='any' " + anySelected + ">" + gf_vars.any + "</option></select>";
    descPieces.ofTheFollowingMatch = gf_vars.ofTheFollowingMatch;
    descPieces.shippingClass = "<input type=\"hidden\" id=\"gf_shippingClass\" value=\"" + shippingClass + "\" />";

    var descPiecesArr = makeArray(descPieces);
    var str = '<div class="conditional_logic_flyout__action">' + descPiecesArr.join(' ') + '</div>';
    str = gform.applyFilters('gform_conditional_logic_description', str, descPieces, shippingClass, obj);

    str += '<div class="conditional_logic_flyout__logic">';
    var i, rule;
    for (i = 0; i < obj.conditionalLogic.rules.length; i++) {
        rule = obj.conditionalLogic.rules[i];
        str += "<div width='100%' class='gf_conditional_logic_rules_container conditional_logic_flyout__rule'>";
        str += ES_GFPA_GetRuleFields(shippingClass, i, rule.fieldId);
        str += ES_GFPA_GetRuleOperators(shippingClass, i, rule.fieldId, rule.operator);
        str += ES_GFPA_GetRuleValues(shippingClass, i, rule.fieldId, rule.value);

        str += '<div class="conditional_logic_flyout__rule-controls">';
        str += "<button " +
            "type='button' " +
            "class='add_field_choice gform-st-icon gform-st-icon--circle-plus' " +
            "title='add another rule' " +
            "onclick=\"ES_GFPA_InsertRule('" + shippingClass + "', " + (i + 1) + ");\" " +
            "onkeypress=\"ES_GFPA_InsertRule('" + shippingClass + "', " + (i + 1) + ");\"" +
            "></button>";
        if (obj.conditionalLogic.rules.length > 1) {
            str += "<button " +
                "type='button' " +
                "class='delete_field_choice gform-st-icon gform-st-icon--circle-minus' " +
                "title='remove this rule' " +
                "onclick=\"ES_GFPA_DeleteRule('" + shippingClass + "', " + i + ");\" " +
                "onkeypress=\"ES_GFPA_DeleteRule('" + shippingClass + "', " + i + ");\"" +
                "></button></li>";
        }
        str += '</div>';
        str += "</div>";
    }
    str += "<div class=conditional_logic_flyout__rule-save>";
    str += "<input id='save_shipping_mapping' type=\"button\" class=\"button primary large\" onclick=\"ES_GFPA_SaveShippingMapping('" + shippingClass + "');\" value=\"Save\" />";
    str += "</div>";
    str += '</div>';

    jQuery('#shipping_logic_enabled_field').on('change', function () {
        if (jQuery(this).prop('checked')) {
            jQuery('.conditional_logic_flyout__main').show();
            ES_GFPA_SetConditionalProperty(jQuery(this).data('objectType'), 'enabled', 'yes');
        } else {
            jQuery('.conditional_logic_flyout__main').hide();
            ES_GFPA_SetConditionalProperty(jQuery(this).data('objectType'), 'enabled', 'no');
            ES_GFPA_SaveShippingMapping(jQuery(this).data('objectType'));
        }
    });

    jQuery("#" + 'shipping' + "_mapping_logic_container").html(str);
    jQuery('#shipping_logic_enabled_field').data('objectType', shippingClass);
    if (obj.conditionalLogic.enabled === 'yes') {
        jQuery('.conditional_logic_flyout__main').show();
        jQuery('#shipping_logic_enabled_field').prop('checked', 'checked');
    } else {
        jQuery('.conditional_logic_flyout__main').hide();
        jQuery('#shipping_logic_enabled_field').removeAttr('checked');
    }

    ES_GFPA_SetRule(shippingClass, 0);
}

(function ($) {

    $(document).ready(function () {

        $(document).on('change', '.es_wcgfpa_gfield_rule_value_dropdown', function () {
            ES_GFPA_SetRuleValueDropDown($(this));
        });

        if ($('#gravityform-id').val()) {
            //ES_GFPA_LoadForm($('#gravityform-id').val());
        }
    });

    let $xhr = null;

    $('#gravityform-id').on('change', function () {
        //ES_GFPA_LoadForm($(this).val());
    });

    jQuery('body').on('thickbox:removed', function () {
        ES_GFPA_SaveShippingMapping(jQuery('#gf_shippingClass').val());
    });


})(jQuery);

