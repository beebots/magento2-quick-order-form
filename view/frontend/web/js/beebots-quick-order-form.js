define([
    'jquery',
    'uiElement',
    'selectize',
    'ko',
    'mage/translate',
    'mage/mage',
    'Magento_Ui/js/lib/validation/validator',
    'validation',
], function($, Component, selectize, ko, $t) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'BeeBots_QuickOrderForm/quick-order-form',
        },
        post_url: '/quick-order/cart/adder',
        redirect_url: null,
        product_data: null,
        form_key: '',
        form_id: 'beebots-quick-order-form',
        requestSent: false,
        fields: ko.observableArray([]),
        quote_id: '',

        initialize: function() {
            this._super();
            this.initializeValidator();
            this.observe(['requestSent']);
        },

        onItemSelectorChange: function(event){
            let $element = $(event.target);
            let $qty = $element.closest('.new-item-row').find('.js-input-qty-selector');
            $qty.focus();
        },

        onAddItemSubmit: function (data, event) {
            const $element = $(event.target);
            const $parent = $element.closest('.new-item-row');
            const $qtyInput = $parent.find('input.js-input-qty-selector');
            const $selectInput = $parent.find('select.js-order-item-selector');

            if (! this.isValidQty($qtyInput)) {
                return;
            }

            let $selectizeInput = $parent.find('.selectize-input input');

            if (! this.isValidId($selectInput, $selectizeInput)) {
                return;
            }


            data.fields.push({
                id: $selectInput.val(),
                qty: $qtyInput.val(),
            });

            let selectized = $selectInput[0].selectize;
            selectized.removeItem($selectInput.val());
            selectized.refreshItems();
            selectized.refreshOptions();
            $qtyInput.val('');
        },

        isValidQty: function($element) {
            if (! $element.val() > 0) {
                $element.attr('isvalid', false);
                $element[0].setCustomValidity("Quantity must be greater than 0");
                $element[0].reportValidity();
                return false;
            }

            return true;
        },

        isValidId: function($element, $select) {
            if (! $element.val() > 0) {
                $select.attr('isvalid', false);
                $select[0].setCustomValidity("Please select an item");
                $select[0].reportValidity();
                return false;
            }

            return true;
        },

        removeItem: function (row) {
            this.fields.remove(row);
        },

        addBlankRow: function () {
            this.fields.push({
                id: null,
                qty: null,
            })
        },

        addItem: function(id, qty) {
            this.fields.push({
                id: id,
                qty: qty,
            })
        },

        initializeItemSelectorElement: function (element) {

            let $element = $(element);

            $element.selectize({
                searchField: 'searchField',
                selectOnTab: false,
                options: this.product_data,
                placeholder: 'Select a SKU',
                allowEmptyOption: true,
                valueField: 'id',
                render: {
                    item: this.buildProductSearchItem.bind(this),
                    option: this.buildProductSearchItem.bind(this)
                },
                maxItems: 1,
                closeAfterSelect: true,
                copyClassesToDropdown: false
            });

            if ($element.attr('data-product-id') && $element.attr('data-product-id').length > 0) {
                let $select = $element[0].selectize;
                $select.setValue($element.attr('data-product-id'), true);
            }

            $element.change(function(event){
                this.onItemSelectorChange(event);
            }.bind(this));

        },

        buildProductSearchItem: function(item, escape){
            return '<div>' +
                (item.sku ? '<span class="sku">' + escape(item.sku) + ':</span> ' : '') +
                (item.name ? '<span class="name">' + escape(item.name) + '</span>' : '') +
                (item.tierPrice ? '<span class="price">' + escape(this.formatPriceForDisplay(item.tierPrice)) + '</span>' : '') +
                '</div>';
        },

        formatPriceForDisplay: function(price){
            let formatter = new Intl.NumberFormat('en-US', {
                style: 'currency',
                currency: 'USD',
            });

            return formatter.format(price);
        },

        onFormSubmit: function() {
            let $form = $('#'+this.form_id);
            this.requestSent(true);
            $.ajax({
                type: 'POST',
                dataType: 'json',
                url: this.post_url,
                data: $form.serialize(),
                success: this.onAjaxSuccess.bind(this),
                error: function(response) {
                    console.log(response);

                },

            });
        },

        onAjaxSuccess: function() {
            window.location = this.redirect_url ?? self.location;
        },

        onAjaxError: function(response) {
            console.log(response);
        },

        initializeValidator: function () {
            // validate quantity field
            $.validator.addMethod('validate-quantity', function (qtyVal, element, options) {
                if (qtyVal) {
                    if (!$.validator.methods['validate-greater-than-zero'].call(this, qtyVal, element, options)) {
                        // must be greater than 0
                        $(element).data('error', $t('Quantity must be an integer greater than 0'));
                        return false;
                    }
                }

                return true;

            }, function (params, element) {
                return $(element).data('error');
            });

        },

        afterAdd: function () {
            let form = $('#'+this.form_id);
            form.mage('validation', {});
        }

    });
});

