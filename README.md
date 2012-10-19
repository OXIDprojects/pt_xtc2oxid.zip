pt_xtc2oxid.zip
===============

pt_xtc2oxid greatly enhances the osc2oxid-program for importing xtCommerce/osCommerce shop-databases into an OXID eShop.  Apart from fixing some bugs in the original version, pt_xtc2oxid also allows importing customers and orders.

Originally registered: 2010-03-18 by Avenger on former OXID projects

-----------------------------

osc2oxid/xtc2oxid 

Enhanced by avenger.

2009-09-10

The osc2oxid/xtc2oxid importers needed some enhancements, in order to import osc/xtc-data correctly.

First of all, a security issue has been fixed: you must be logged in as an administrator in order to use the program.

The ID of the active language is determined from the source database, instead of using the fixed ID "1", as the importer did (which was wrong at least for xtc, as 1 is the ID for the english language).

The tax-rates for the store-country and -zone are determined from the source database.

These are used on product-import to calculate the product's gross price from xtc's net price.

If the product uses a tax-rate different form OXID's standard tax-rate (e.g. 7%), then this tax-rate is stored with the product.

The product's "active" state is taken from the source-product's "active" information, instead of setting it always to "active".

The categorie's "hidden" state is taken from the source-categories's "active" information, instead of leaving it always "not hidden".

If a product containde more then 5 images, PHP warnings were generated

Also customer-data can now be imported.

The importer's ability to import product attributes is  v e r y  limited, as it stores the osc/xtc )attributes as OXID variants.

This means, you will can have 1(!) attribute-group in OXID, regardless of the number of attribute-groups a product has in xtc/osc.

The importer code, however, processes all option groups and options a product has in osc/xtc, storing different data for the same product redundantly, which is uselessly time consuming with a large number of attributes.

The importer code has been modified, so that only the data of the first option-group is processed.

2009-09-17

The importer has been extended to allow also order import

2009-09-18

Admin login has to be made in the shop front-end

Newsletter ""optin"-status is derive from source optin status
