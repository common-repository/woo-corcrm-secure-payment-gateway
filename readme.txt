===  HieCOR WooCommerce Plugin ===
Contributors: hiecor
Tags: HieCOR Payment Gateway, HieCOR, HieCOR woocommerce addon
Requires at least: 4.7.5
Tested up to: 5.3
Stable tag: 1.2.6
License: GPLv2 or later

HieCOR Payment Gateway Plugin will synced down the products from Wordpress to HieCOR and from HieCOR to wordpress.
All the orders will also be synced down to HieCOR and payment will be done at HieCOR. If you have HieCOR subscription 
and you are running a wordpress site then this is the must use plugin.

== Description ==
HieCOR Payment Gateway Plugin will synced down the products from Wordpress to HieCOR and from HieCOR to wordpress.
All the orders will also be synced down to HieCOR and payment will be done at HieCOR. If you have HieCOR subscription 
and you are running a wordpress site then this is the must use plugin.

== Screenshots ==
1. HieCOR API setting page

== Changelog ==
=1.0.1=
* Changed Corcrm to HieCOR

==1.0.4==
* Added weight for variable products
* Fixed auto login

==1.0.5==

* Product category flow to HieCOR
* Fixed image appearance in HieCor POS for variation Products

==1.0.6==

* Fixed Hiecor Product id update in wordpress 

== 1.0.7==

* Added support for order pulling - synced the partner order id to Hiecor

==1.0.8==

* Added option to set the default order status as Complete/Processing
* Added address_2 for billing and shipping address

==1.0.9==

* Subscription products functionality added.Subscription products from woocommerce to Hiecor can be created now.

==1.1.0==

* Added 2 weeks subscription option.

==1.1.1==

* Using attribute value instead of slug for variation product title sent to Hiecor.
* Corrected Subscription spelling.

==1.1.2==

* Added product custom option support
* Fixed Jquery conflict
* Unlimited Stock Issue fixed
* Implemented Bulk Product API for Variation Updates

==1.1.3==

* Variation Product Image issue fixed
* Custom Option and Attribute issues fixed

==1.1.4==

* Deleted products from Hiecor if exist in the wordpress can be restored back to Hiecor during add-to-cart action
* Order notes (Special Instruction)are now sent to hiecor as order notes.

==1.1.5==

* UPC and Cost fields are now synced to Hiecor (UPC and Cost support has been added with in the Hiecor Payment Plugin and Our Plugin also support the use of  <a href="https://wordpress.org/plugins/woo-add-gtin/">WooCommerce UPC, EAN, and ISBN</a> plugin for UPC and <a href="https://woocommerce.com/products/woocommerce-cost-of-goods/">Cost of Goods</a> for Cost)

== 1.1.6 ==

* Sync enabled for the product set Visibility status to Private

== 1.1.7 ==

* Added Order Delivery Date and Updated to Hiecor as Order date

== 1.1.8 ==

* Added corcrm_product_id in get product woocommerce API response

== 1.1.9 ==

* Fixed comma issues in product attributes.

==1.2.0==

*Fixed duplicate order issue (Orders were duplicated in Hiecor if Place Order button reclicked in case of payment failure)

== 1.2.1==

*Fixed duplicate order issue (resetting order_id for payment failure)

== 1.2.2 ==

*Fixed get_product api failure issue

== 1.2.4 ==

*Fixed wordpress 5.1 Compatibility issue

== 1.2.5 ==

*Support for wordpress 5.3 
*Bug fix for Coupon Products
*Tax information passed to Hiecor through new SOAP param extra_data

== 1.2.6 ==
* Bug Fix Validate Payment fields
* Campaign Code fix