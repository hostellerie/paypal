(function ($) {
    'use strict';

    function config() {
        return window.PayPalCartConfig || {};
    }

    function cartState() {
        var value = $('#jcart-is-checkout').val();
        return value === 'true' ? 'true' : 'false';
    }

    function refreshCart(data) {
        var $cart = $('#jcart');
        if ($cart.length) {
            $cart.html(data);
            $('.jcart-hide').remove();
        updateCartBlockVisibility();
        }
    }

    function updateCartBlockVisibility() {
        var $outer = $('#paypal-cart-dynamic-block');
        var $content = $('#paypal-cart-block');

        if (!$outer.length || !$content.length) {
            return;
        }

        var isEmpty = $content.find('.paypal-cart-summary__item').length === 0;
        $outer.toggleClass('paypal-cart-dynamic-block--empty', isEmpty);
    }

    function refreshCartBlock(data) {
        var $block = $('#paypal-cart-block');
        if ($block.length) {
            $block.html(data);
            $('.jcart-hide').remove();
            updateCartBlockVisibility();
        }
    }

    function refreshBlock(cfg) {
        $.get(cfg.relayBlockUrl, function (data) {
            refreshCartBlock(data);
        });
    }

    function postCart(payload) {
        var cfg = config();

        $.post(cfg.relayUrl, payload, function (data) {
            refreshCart(data);
            refreshBlock(cfg);
        });
    }

    $(function () {
        var cfg = config();

        if (!cfg.relayUrl || !cfg.relayBlockUrl) {
            return;
        }

        $('.jcart-hide').remove();

        $(document).on('submit', 'form.jcart', function (event) {
            event.preventDefault();

            var $form = $(this);
            var attributeNames = [];
            var attributeIds = [];
            var itemRef = $form.find('input[name="' + cfg.itemRef + '"]').val() || '';

            $form.find('.attributes_select option:selected').each(function () {
                attributeNames.push($(this).text());
                attributeIds.push($(this).val());
                itemRef += $(this).attr('ref') || '';
            });

            var itemName = $form.find('input[name="' + cfg.itemName + '"]').val() || '';
            var itemId = $form.find('input[name="' + cfg.itemId + '"]').val() || '';

            if (attributeIds.length) {
                itemName += ' ' + attributeNames.join(', ') + ' (' + itemRef + ')';
                itemId += '|' + attributeIds.join(',');
            }

            var payload = {};
            payload[cfg.itemId] = itemId;
            payload[cfg.itemPrice] = $form.find('input[name="' + cfg.itemPrice + '"]').val() || '';
            payload[cfg.itemName] = itemName;
            payload[cfg.itemQty] = $form.find('input[name="' + cfg.itemQty + '"]').val() || '1';
            payload[cfg.itemAdd] = $form.find('input[name="' + cfg.itemAdd + '"]').val() || '1';
            payload[cfg.itemWeight] = $form.find('input[name="' + cfg.itemWeight + '"]').val() || '';

            postCart(payload);
        });

        $(document).on('click', '#jcart a.jcart-remove', function (event) {
            event.preventDefault();

            var href = $(this).attr('href') || '';
            var match = href.match(/[?&]jcart_remove=([^&]+)/);
            if (!match) {
                return;
            }

            var payload = {
                jcart_remove: decodeURIComponent(match[1]),
                jcart_is_checkout: cartState()
            };

            $.get(cfg.relayUrl, payload, function (data) {
                refreshCart(data);
                refreshBlock(cfg);
            });
        });

        var updateTimer = null;

        $(document).on('keyup', '#jcart input[type="text"]', function () {
            var $input = $(this);
            var id = $input.attr('id') || '';
            var parts = id.split('-');
            var updateId = parts.length > 3 ? parts[3] : '';
            var updateQty = $input.val();

            if (!updateId || updateQty === '') {
                return;
            }

            window.clearTimeout(updateTimer);
            updateTimer = window.setTimeout(function () {
                postCart({
                    item_id: updateId,
                    item_qty: updateQty,
                    jcart_update_item: cfg.updateButton,
                    jcart_is_checkout: cartState()
                });
            }, 500);
        });

        $(document).on('keydown', '#jcart', function (event) {
            if (event.which === 13 && $(event.target).is('input[type="text"]')) {
                event.preventDefault();
            }
        });
    });
})(jQuery);
