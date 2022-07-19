# woocommerce-gravityforms-product-addons-cart-item-shipping


This plugin will allow you to use gravity form's fields to set a cart item's shipping details.

## Install
To install, download the zip from this repository and upload to your site.  Active the plugin to begin configuring your mapping between Gravity Forms information and your shipping classes.  

## Requirements
To use the mapping features, you must have previously setup WooCommerce shipping information on your site.  To make the extension more useful it's helpful if you have 
multiple shipping classes that your products can be grouped into.  

With your WooCommerce shipping information configured, you can then map the shipping classes to a users purchases.

##Mapping Shipping Classes

With the plugin active and your WooCommerce shipping information configured, edit a WooCommerce where you will have a form attached. 
You will see a new tab in the Product Meta Data section under Gravity forms called "Shipping Options". 

Edit Product -> Product Information -> Gravity Forms Tab -> Shipping Information.  

On the Shipping Information subsection, you can enable mapping in general.  Set this value to Yes. 
Each of your shipping classes will be listed, and initially will all be disabled.  Click on the shipping class name, or the configure link to 
enable and create your mappings.   

The mappings are similar to how Gravity Forms conditional logic works.  You can set various conditions which must be met.  
When those conditions are met the shipping class will be applied.  
