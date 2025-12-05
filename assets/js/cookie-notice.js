/**
 * Nekuda MCT Cookie Notice JavaScript
 */
(function() {
    'use strict';

    var config = window.nekudaMctCookie || {
        cookieName: 'nekuda_mct_cookie_consent',
        cookieExpiry: 365
    };

    /**
     * Set a cookie
     */
    function setCookie(name, value, days) {
        var date = new Date();
        date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
        var expires = 'expires=' + date.toUTCString();
        var secure = window.location.protocol === 'https:' ? ';Secure' : '';
        document.cookie = name + '=' + value + ';' + expires + ';path=/;SameSite=Lax' + secure;
    }

    /**
     * Hide the banner
     */
    function hideBanner() {
        var banner = document.getElementById('nekuda-mct-cookie-banner');
        if (banner) {
            banner.classList.add('nekuda-mct-cookie-banner--hidden');
        }
    }

    /**
     * Handle close button click
     */
    function handleClose() {
        setCookie(config.cookieName, 'accepted', config.cookieExpiry);
        hideBanner();
    }

    /**
     * Initialize
     */
    function init() {
        var closeButton = document.getElementById('nekuda-mct-cookie-close');
        if (closeButton) {
            closeButton.addEventListener('click', handleClose);
        }
    }

    // Run on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
