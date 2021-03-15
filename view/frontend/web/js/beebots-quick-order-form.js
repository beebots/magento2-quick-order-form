define([
    'jquery',
    'uiElement',
    'selectize',
    'ko',
    'mage/translate',
    'Magento_Ui/js/model/messageList',
    'Magento_Customer/js/customer-data',
    'mage/mage',
    'Magento_Ui/js/lib/validation/validator',
    'validation',
], function($, Component, selectize, ko, $t, messageList, customerData) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'BeeBots_QuickOrderForm/quick-order-form',
        },
        post_url: '/quick-order/cart/add',
        redirect_url: null,
        product_data: null,
        form_key: '',
        form_id: 'beebots-quick-order-form',
        requestSent: false,
        fields: ko.observableArray([]),
        button_text: 'Checkout',
        new_item_row_selector: '.new-item-row',
        has_focus: true,
        new_item_event: new CustomEvent('newQuickOrderItemAddedEvent', {
            bubbles: true
        }),

        initialize: function() {
            this._super();
            this.observe(['requestSent']);
        },

        onItemSelectorChange: function(event){
            let $element = $(event.target);
            this.addItem(this, $element);
        },

        onAddItemSubmit: function(data, event) {
            this.addItem(data, $(event.target));
        },

        addItem: function (data, $element) {
            const $parent = $element.closest(this.new_item_row_selector);
            const $qtyInput = $parent.find('input.js-input-qty-selector');
            const $selectInput = $parent.find('select.js-order-item-selector');
            const selectedItemId = $selectInput.val();
            const qtyValue = $qtyInput.val();

            if (! this.isValidQty($qtyInput)) {
                return;
            }

            let $selectizeInput = $parent.find('.selectize-input input');

            if (! this.isValidId($selectInput, $selectizeInput)) {
                return;
            }

            let selectized = $selectInput[0].selectize;
            selectized.removeItem($selectInput.val(), true);
            selectized.refreshItems();
            selectized.refreshOptions(false);
            selectized.$control_input.css({position: 'unset', left: 'unset', opacity: 1});
            this.resetValidation($selectizeInput);
            $qtyInput.val('1');

            data.fields.push({
                id: selectedItemId,
                qty: qtyValue,
                isFocused: true,
            });

            $parent[0].dispatchEvent(this.new_item_event);

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

        removeItem: function (data) {
            this.fields.remove(data);
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

            let $parent = $element.closest(this.new_item_row_selector);
            let $selectizeInput = $parent.find('.selectize-input input');

            $selectizeInput.on('propertychange input', function(event) {
                this.clearValidation(this, event);
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

            if (this.fields().length === 0) {
                return;
            }

            let $form = $('#'+this.form_id);
            this.requestSent(true);
            $.ajax({
                type: 'POST',
                dataType: 'json',
                url: this.post_url,
                data: $form.serialize(),
                success: this.onAjaxSuccess.bind(this),
                error: function(response) {
                    this.onAjaxError(response);
                }.bind(this),
            });
        },

        onAjaxSuccess: function() {
            window.location = this.redirect_url ?? self.location;
            this.addMessage('Items added to cart.', 'success');
        },

        onAjaxError: function(response) {
            this.addMessage('Items could not be added cart.', 'error');
        },

        addMessage: function(message, type) {
            let customerMessages = customerData.get('messages')() || {},
                messages = customerMessages.messages || [];

            messages.push({
                text: message,
                type: type
            });

            customerMessages.messages = messages;

            customerData.set('messages', customerMessages);
        },

        getProductById: function(productId){
            return this.product_data.find(function(item){
                return item.id === productId;
            });
        },

        clearValidation: function(data, event) {
            let $element = $(event.target);
            this.resetValidation($element);
        },

        resetValidation: function($element) {
            $element.removeAttr('isvalid');
            $element[0].setCustomValidity('');
        },
    });
});

