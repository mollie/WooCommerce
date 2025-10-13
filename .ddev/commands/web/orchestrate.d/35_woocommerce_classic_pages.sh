#!/bin/bash

 wp wc tool run install_pages --user="admin"
 wp post create --post_content='<!-- wp:woocommerce/classic-shortcode {"shortcode":"checkout"} /-->' --post_title='Classic Checkout' --post_type=page --post_status=publish --menu_order=21
 wp post create --post_content='<!-- wp:woocommerce/classic-shortcode /-->' --post_title='Classic Cart' --post_type=page --post_status=publish --menu_order=20
