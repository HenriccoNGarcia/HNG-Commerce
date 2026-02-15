/**
 * HNG Commerce Conversion Tracking Script
 * 
 * Tracks user interactions for conversion analytics
 */

(function() {
    'use strict';
    
    // Get session ID from cookie or create new one
    var sessionId = getCookie('hng_session') || generateSessionId();
    setCookie('hng_session', sessionId, 30); // 30 min expiry
    
    // Page view tracking
    if (typeof hngTrackingData !== 'undefined') {
        trackEvent('page_view', {
            page_id: hngTrackingData.pageId,
            page_url: window.location.href,
            referrer: document.referrer,
            template_id: hngTrackingData.templateId || null,
            template_name: hngTrackingData.templateName || null
        });
    }
    
    // Track product views
    if (typeof hngTrackingData !== 'undefined' && hngTrackingData.productId) {
        trackEvent('product_view', {
            product_id: hngTrackingData.productId,
            page_url: window.location.href
        });
    }
    
    // Track add to cart clicks
    jQuery(document).on('click', '.hng-add-to-cart, .add-to-cart', function() {
        var productId = jQuery(this).data('product-id');
        var quantity = jQuery(this).closest('form').find('[name="quantity"]').val() || 1;
        
        if (productId) {
            trackEvent('add_to_cart', {
                product_id: productId,
                quantity: quantity
            });
        }
    });
    
    // Track checkout start
    if (typeof hngTrackingData !== 'undefined' && hngTrackingData.isCheckout) {
        trackEvent('checkout_start', {
            page_id: hngTrackingData.pageId,
            page_url: window.location.href,
            template_id: hngTrackingData.templateId || null
        });
    }
    
    // Helper functions
    function trackEvent(eventType, data) {
        data = data || {};
        data.event_type = eventType;
        data.session_id = sessionId;
        
        jQuery.post(hngTrackingData.ajaxUrl, {
            action: 'hng_track_event',
            nonce: hngTrackingData.nonce,
            event_data: data
        });
    }
    
    function generateSessionId() {
        return 'hng_' + Math.random().toString(36).substr(2, 9) + Date.now().toString(36);
    }
    
    function getCookie(name) {
        var value = "; " + document.cookie;
        var parts = value.split("; " + name + "=");
        if (parts.length === 2) return parts.pop().split(";").shift();
    }
    
    function setCookie(name, value, minutes) {
        var expires = "";
        if (minutes) {
            var date = new Date();
            date.setTime(date.getTime() + (minutes * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "") + expires + "; path=/";
    }
})();
