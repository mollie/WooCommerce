const allProducts = {
    simple: {
        "title": "Beanie",
        'link': '/product/beanie/',
        'sku': 'woo-beanie',
        'sale_price': '18,00 €',
        'regular_price': '20,00 €',
        'price': '18,00 €',
        'virtual': 'no',
        'downloadable': 'no',
    },
    virtual: {
        "title": "Album",
        'link': '/product/album/',
        'sku': 'woo-album',
        'sale_price': '15,00 €',
        'regular_price': '20,00 €',
        'price': '15,00 €',
        'virtual': 'yes',
        'downloadable': 'yes',
    },
    variation: {
        "title": "V-Neck T-Shirt",
        'link': "/product/v-neck-t-shirt/",
        'sku': "woo-vneck-tee",
        'sale_price': '',
        'regular_price': '',
        'price': '',
        'virtual': 'no',
        'downloadable': 'no',
    }, variationRed: {
        "title": "V-Neck T-Shirt - Red",
        'link': '/product/v-neck-t-shirt/?attribute_pa_color=red',
        'sku': "woo-vneck-tee-red",
        'sale_price': '',
        'regular_price': '20,00 €',
        'price': '20,00 €',
        'virtual': 'no',
        'downloadable': 'no',
    },
    variationGreen: {
        "title": "V-Neck T-Shirt - Green",
        'link': '/product/v-neck-t-shirt/?attribute_pa_color=green',
        'sku': "woo-vneck-tee-green",
        'sale_price': '',
        'regular_price': '20,00 €',
        'price': '20,00 €',
        'virtual': 'no',
        'downloadable': 'no',
    }
};


module.exports = {allProducts};
