=== HCGroup Shipping for Woocommerce ===
Contributors: hcgroup
Donate link: 
Tags: woocommerce, admin, shipping, shipping method, woocommerce extension, hcgroup
Requires at least: 4.3.0
Tested up to: 5.7.2
Stable tag: 2.2.5
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

== Description ==
HCGroup Shipping for Woocommerce is a Wordpress Plugin that integrate the hcgroup service, it will calculate the shipping cost.

This addon calls a webservice to obtain the shipping rate or price according to the parameters of the product, for this with the following URL
http://www.hcgroup.cl/ws/ecomerce.asmx/get_tarifa_despacho_JSON

The variables are
token_cliente; number to identify the registered customer
servicio; type of service delivered to the client
comuna_destino; commune where delivery will be made
peso; of the total purchase
cantidad; quantity of products
volumen; final volume of all products

send the data 
The tarifa data is received, which corresponds to the shipping price.

in post sale
When admin changes the status of orders to completed or cancelled, this addon call to webservice and insert the new state, then return number of service.
If order is cancelled, call to webservice and delete number of service, then return success.
In orders section, this addons call to webservice and consult actual state, then show result in order detail.
When HCGroup make a retirement, addons consult a webservice and get the tracking number and show this in order detail. This also appears in the customer order detail.

== Installation ==

1. From admin wordpress plugin, add new, search hcgroup shipping, download plugin.
2. Activate the plugin through the 'Plugins' screen in WordPress.

== Frequently Asked Questions ==

== Changelog ==

= 2.2.5 =
*Release Date - 26 May 2021*
* Fix update for webpay plus rest and for all, the final status for the post payment is processing

= 2.2.4 =
*Release Date - 21 May 2021*
* Fix bug converter of volume measurement per product to centimeters

= 2.2.3 =
*Release Date - 05 May 2021*
* Converter of volume measurement per product to centimeters

= 2.2.2 =
*Release Date - 09 Abr 2021*
* Fixed bugs add second address for carrier

= 2.2.1 =
*Release Date - 24 Mar 2021*
* Fixed bugs show only if carrier is hcgroup for admin

= 2.2.0 =
*Release Date - 24 Mar 2021*
* Fixed bugs on messages for admin
* Fixed now only send request for carrier hcgroup

= 2.1.0 =
*Release Date - 29 Oct 2020*
* Monitoring against webservices

= 2.0.2 =
*Release Date - 28 Sep 2020*
* Change for php 7.2

= 2.0.1 =
*Release Date - 25 Sep 2020*
* Change for php 7.3

= 2.0.0 =
*Release Date - 10 July 2020*
* Fixed bugs on update + new fuctions for orders status

= 1.0.7 =
* Fixed bugs on update , created only function

= 1.0.6 =
* Fixed bugs on address not set

= 1.0.5 =
* Fixed bugs on address and states on send url with get

= 1.0.4 =
* Change price shipping for get subtotal price

= 1.0.3 =
* Bug timeout external server and hide carrier on response -1

= 1.0.2 =
* New options, post sale service and delivery

= 1.0.1 =
* Update compability wordpress 5.3 and php 7.4

= 1.0.0 =
* Initial release.
