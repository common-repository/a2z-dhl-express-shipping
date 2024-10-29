=== Automated DHL Express live/manual shipping rates, labels and pickup – HPOS supported ===
Contributors: aarsiv
Tags: DHL, DHL Express, automated, shipping rates, shipping label,  return label, DHL Woocommerce
Requires at least: 4.0.1
Tested up to: 6.7
Requires PHP: 5.6
Stable tag: 5.5.3
License: GPLv3 or later License
URI: http://www.gnu.org/licenses/gpl-3.0.html

(Fully automated) Real-time rates, shipping label, return label, pickup, invoice, multi vendor,etc. supports all countries. 

== Description ==

[DHL Express shipping](https://wordpress.org/plugins/a2z-dhl-express-shipping/) plugin integrates seamlessly with [DHL Express](https://www.dhl.com/en.html) for real-time DHL Express online quotes based on the postal codes of origin and destination,shipping rates, label printing, automatic tracking number email generation, shipping rate previews on product pages, and much more.

Annoyed of clicking button to create shipping label and generating it here is a hassle free solution, [Shipi](https://myshipi.com) is the tool with fully automated will reduce your cost and will save your time. 

Further, We will track your shipments and will update the order status automatically. Our plugin used the latest version of DHL Express XML Version 10.

For documentation vist [this link](https://app.myshipi.com/docs/dhlintegration.html)
= FRONT OFFICE (CHECKOUT PAGE): =

To fetch real-time rates on the checkout page, we will send product information and location to DHL.

We are providing the following domestic & international shipping carriers of DHL:
 * Domestic Express
 * Worldwide Express
 * DHL Express
 * DHL Economy

and more 14 Services.

You can get the DHL provided discounts.
By using hooks and filters you can make currency conversions, product skipping package, definition customization and supports the insurance.

= BACK OFFICE (SHIPPING ): =

[DHL Express shipping](https://wordpress.org/plugins/a2z-dhl-express-shipping/) plugin is deeply integrated with shipi. So the shipping labels will be generated automatically. You can get the shipping label through email or from the order page.

 This plugin also supported the manual shipments option. By using this you can create the shipments directly from the order page. Shipi will keep track of the orders and update the order state to complete.

[How to configure?](https://myshipi.com/how-to-configure-dhl-express-shipping/)
[Code Snippet collection](https://myshipi.com/dhl-express-woocommerce-snippets/)

= Your customer will appreciate : =

* The Product is delivered very quickly. The reason is, there this no delay between the order and shipping label action.
* Access to the many services of DHL for domestic & international shipping.
* Good impression of the shop.


= Shipi Action Sample =
[youtube https://www.youtube.com/watch?v=TZei_H5NkyU]


= Informations for Configure plugin =

> If you have already a DHL Express Account, please contact your DHL account manager to get your credentials.
> If you are not registered yet, please contact our customer service.
> Functions of the module are available only after receiving your API’s credentials.
> Please note also that phone number registration for the customer on the address webform should be mandatory.
> Create account in shipi.

Plugin Tags: <blockquote>Best DHL express plugin, DHL, DHL Express, dhlexpress,DHL Express shipping, DHL Woocommerce, dhl express for woocommerce, official dhl express, dhl express plugin, dhl plugin, create shipment, dhl shipping, dhl shipping rates</blockquote>


= About DHL =

DHL Express is a division of the German logistics company Deutsche Post DHL providing international courier, parcel, and express mail services. Deutsche Post DHL is the world's largest logistics company operating around the world, particularly in sea and air mail

= About  Shipi =

"Shipi is a shipping platform for automation". Shipi is an automation tool that let's you print shipping labels,track orders,audit shipments etc. Both shipments are handled with a strong integration of e-Commerce.

= What basic features Shipi have with DHL Express? =

> Get Live Shipping rates and account rates using plugins which are integrated with Shipi.
> Create Shipments and generate label through Shipi.
> You can Create Pick up request and label through Shipi.
> You can create Return label through Shipi.
> You can Track your Shipments.
> Automatically update order status.
> Audit Shipments.
> All Shipping services available.
> Supports both Domestic and International Shipments.

= Why Shipi? =
If you are tired by the process of creating and printing a shipping label, here is a simple solution for you. Shipi is a fully automated solution that lowers your costs and saves your time.

> 1. When an order is placed, the shipping label, invoice, packing slip gets automatically created and it will be mailed to your email, and at the same time, the label is updated on your site.
> 2. It tracks the order information and automatically changes the state from shipped to a completed state when an order status is completed.
> 3. Website Speed -> Shipping servers are returning a big amount of Base64 encoded data via API. If you are using any plugin, this encodes formatted data that will be getting saved in your DB. And DB size will get increased, Maintenance is needed to your Database in the future. Here we are handling that on the Shipi side, only storing a short URL of the shipping label in the table.
> 4. We are doing shipment audits, If a package goes wrong, any damage happens, the system will automatically capture the data & email you. You can get a refund from them.
> 5. Mainly, When your customer enters the address, they may enter the address1 line very big. But shipping companies will support 44 characters to 60 characters. Here some shipping plugins will truncate the characters and create shipping labels that cause bad delivery. In Shipi, This part is manually handled. If this kind of error comes while creating a shipping label, our team will manually enter that data from outside & create a label correctly.


== Screenshots ==
1. DHL Account integration settings.
2. Shipper address configuration.
3. Packing algorithm configurations.
4. Shipping rates configuration & shipping services list.
5. Shipping label, tracking, pickup configuration.
6. Order page where you can easily get labels.
7. Cart Page -  Shipping rates working.
8. Checkout - Order placed with the DHL Express carrier.
9. Create shiping label screen in edit order page. This is for manual usage.
10. Shipping label management section - Shipi.
11. Check tracking informations - Shipi.
12. Check tracking informations - In the site my account section.
13. Shipping label & Management information.


== Changelog ==
=5.5.3
	> New Wordpress version tested
=5.5.2
	> Bug fix on order rate
=5.5.1
	> Bug fix
=5.5.0
	> Translation API Updated
=5.4.0
	> New DHL API Updates

=5.3.0
	> Performace Inprovements

=5.2.7
*Release Date - 30 March 2024*
	> Performace Inprovements

=5.2.7
*Release Date - 13 March 2024*
	> Minor improvements

=5.2.6
*Release Date - 07 March 2024*
	> Bug fix on single quote

=5.2.5
*Release Date - 29 Feb 2024*
	> Bug fix on state length

=5.2.4
*Release Date - 12 Jan 2024*
	> Bug fix on manual order creation

=5.2.3
*Release Date - 08 Jan 2024*
	> Minor improvements

=5.2.2
*Release Date - 05 Jan 2024*
	> Added some error handlings

=5.2.1
*Release Date - 19 Dec 2023*
	> Minor bug fix

=5.2.0
*Release Date - 08 Dec 2023*
	> Added custom shipment description support through filter.

=5.1.1
*Release Date - 22 Nov 2023*
	> Minor improvements

=5.1.0
*Release Date - 02 Nov 2023*
	> Added field to saved inbound commodity code

=5.0.2
*Release Date - 31 Oct 2023*
	> Skip Sku filter added

=5.0.1
*Release Date - 24 Oct 2023*
	> Minor bug fix

=5.0.0
*Release Date - 20 Oct 2023*
	> MY DHL API support