#!/bin/bash

#activate tax setting
wp option set woocommerce_calc_taxes "yes"
#create tax
wp wc tax create --rate=10 --name="Standard tax" --priority=1 --compound=0 --shipping=1 --user="admin"
wp option set woocommerce_tax_display_shop "incl"
#euro
wp option set woocommerce_currency "EUR"

#store address
wp option set woocommerce_store_address "Calle Drutal"
wp option set woocommerce_store_address_2 ""
wp option set woocommerce_store_city "Valencia"
wp option set woocommerce_default_country "DE:DE-BE"
