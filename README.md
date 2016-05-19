###############################################
Ecommerce Countries
###############################################

This is a module that can be used when you have complex dealings with several countries (e.g. only sell some products to some countries) for your e-commerce shop.


Developers
-----------------------------------------------
Nicolaas Francken [at] sunnysideup.co.nz


Requirements
-----------------------------------------------
see composer.json

Project Home
-----------------------------------------------
See http://code.google.com/p/silverstripe-ecommerce

Demo
-----------------------------------------------
See http://www.silverstripe-ecommerce.com

Installation Instructions
-----------------------------------------------
1. Find out how to add modules to SS and add module as per usual.
2. copy configurations from this module's \_config.php file
into mysite/\_config.php file and edit settings as required.
NB. the idea is not to edit the module at all, but instead customise
it from your mysite folder, so that you can upgrade the module without redoing the settings.

If you just want one or two things from this module
then of course you are free to copy them to your
mysite folder and delete the rest of this module.


# NOTES
things to change for country:

a. availability:
    see: 
b. price (amount + currency)
c. payment gateway
d. taxes
e. delivery

What can the distributor edit:
1. price
2. what is available
3. taxes

WW = world-wide
a
Each visitor is a from a country
firstly, we need to work out the country used:


AVAILABILITY: sales to country are available
PRICE: current country price, WW backup price
GATEWAY: distributor gateway, WW gateway
TAXES: country tax tax, WW tax
DELIVERY: country delivery, WW delivery


BY COUNTRY:
========================================
- price
- currency
- tax
- delivery
- distributor

BY DISTRIBUTOR
========================================
- gateway
- availability

WW BACKUP based on:
========================================
AVAILABILITY: based backup country
PRICE: based backup country
CURRENCY: based backup country
GATEWAY: based backup distributor
TAX: specific tax setting for not-listed country
DELIVERY: specific delivery setting for not-listed country
DISTRIBUTOR: back-up distributor
