import Plugin from 'src/plugin-system/plugin.class';
import { COOKIE_CONFIGURATION_UPDATE } from 'src/plugin/cookie/cookie-configuration.plugin';
import CookieStorageHelper from 'src/helper/storage/cookie-storage.helper';
import DeviceDetection from 'src/helper/device-detection.helper';


export default class EdronePlugin extends Plugin {

    static options = {
        'buyButtonSelector': '.btn-buy',
        'productCardSelector': '.product-box',
        'listingBreadcrumbSelector': '.breadcrumb',
        'productPageSelector': '.is-ctl-product',
        'imageWrapperSelector': '.product-image-wrapper',
        'productLinkSelector': '.product-name',
        'productNameSelector': 'input[name="product-name"]',
        'productIdSelector': 'input[name="product-id"]',
        'productNumberSelector': 'meta[itemprop="mpn"]'
    };

    /**
     *  Plugin initialization
     *
     * @public
     */
    init() {
        this.cookieEnabledName = 'edrone-crm-enabled';

        this.handleCookieChangeEvent();

        if (!CookieStorageHelper.getItem(this.cookieEnabledName)) {
            return;
        }

        this.startEdroneCrm();

    }

    startEdroneCrm() {
        window.edroneCrmCallback();
        this._registerEvents();
    }

    handleCookieChangeEvent() {
        document.$emitter.subscribe(COOKIE_CONFIGURATION_UPDATE, this.handleCookies.bind(this));
    }

    handleCookies(cookieUpdateEvent) {
        const updatedCookies = cookieUpdateEvent.detail;

        if (!updatedCookies.hasOwnProperty(this.cookieEnabledName)) {
            return;
        }

        if (updatedCookies[this.cookieEnabledName]) {
            this.startEdroneCrm();
        }
    }

    /**
     * Register all needed events
     *
     * @private
     */
    _registerEvents() {
        const eventType = (DeviceDetection.isTouchDevice()) ? 'touchend' : 'click';

        [...document.querySelectorAll(this.options.buyButtonSelector)]
            .forEach(el => el.addEventListener(eventType, this._onAddToCard.bind(this)));
    }

    /**
     * event on AddToCard
     *
     * @private
     */
    _onAddToCard(e) {
        if (!document.querySelector(this.options.productPageSelector)) {
            this._prepareEdrone(e);
        }

        window._edrone.action_type = 'add_to_cart'
        window._edrone.init()
    }

    /**
     * function prepare edrone data for add to card event
     *
     * @private
     */
    _prepareEdrone(e) {
        const productBox = e.target.closest(this.options.productCardSelector);

        window._edrone.product_skus = productBox.querySelector(this.options.productNumberSelector).content;
        window._edrone.product_ids = productBox.querySelector(this.options.productIdSelector).value;
        window._edrone.product_titles = productBox.querySelector(this.options.productNameSelector).value;
        window._edrone.product_images = productBox.querySelector(this.options.imageWrapperSelector + ' img').src;
        window._edrone.product_urls = productBox.querySelector(this.options.productLinkSelector).href;
        window._edrone.product_availability = '1';
        // _edrone.product_category_ids = '{{ page.product.extensions.edroneProductCategory.productCategoryIds |url_encode }}';
        window._edrone.product_category_names =  document.querySelector(this.options.listingBreadcrumbSelector).innerText.replace('\n','~');
    }
}
