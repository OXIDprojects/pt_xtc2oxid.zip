<?php

//(c)2009 OXID eSales AG
//This script imports OS Commerce shop catalog to OXID eShop
//What's imported:
// Manufacturers, categories, product info, products in categories, images, reviews.
// It tries to convert OSCommerce options to OXID variants.
//Script automatically sets OXID lanuage config array according to OSCommerce languages.
//support@oxid-esales.com
//
//This script also supports data import from XTCommerce (extended OSCommerce clone)
//In this case additionally product search keywords, short description, EAN, image array,
//general scale prices, crosseling, category short/long description,
//newsletter subscriber list are imported and tag cloud are generated.
//
//Set configuration params in _config.inc.php
//and run this script from command line (recommended):
//>php osc2oxid.php

/*
(c)2009 Avenger, entwicklung@powertemplate.de

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

*/

define('IS_ADMIN_FUNCTION',true);
$iStartTime = time();

//CONFIGURATION
require_once("_config.inc.php");
require_once("_functions.inc.php");

require_once dirname(__FILE__) . "/../bootstrap.php"; 

//IMPLEMENTATION
set_time_limit(0);

//language count
$iLangCount = 4;


global $sOxidConfigDir;

//init OXID framework
@include_once($sOxidConfigDir . "_version_define.php");
require_once($sOxidConfigDir. "core/oxfunctions.php");
//require_once($sOxidConfigDir. "core/adodblite/adodb.inc.php");

$myConfig = oxRegistry::getConfig();  

//default OXID shop id
$sShopId = $myConfig->getBaseShopId();
//Avenger 

if ($blIsXtc)
    $oIHandler = new XtImportHandler($sShopId);
else
    $oIHandler = new ImportHandler($sShopId);

//connect to db
//$oIHandler->mysqlConnect();
//empty tables:
$oIHandler->cleanUpBeforeImport();

printLine("<pre>");

//--- LANGUAGES ----------------------------------------------------------
printLine("SETTING LANGUAGES");
$oIHandler->setLanguages();
printLine("Done.\n");
//------------------------------------------------------------------------

//--- CUSTOMERS ------------------------------------------------------
printLine("IMPORTING CUSTOMERS");
printLine("Here we get collation errors unicode vs general_ci");
$oIHandler->importCustomers();
printLine("Done.\n");
//------------------------------------------------------------------------

exit(1);

//--- MANUFACTURERS ------------------------------------------------------
printLine("IMPORTING MANUFACTURERS");
$oIHandler->importManufacturers();
printLine("Done.\n");
//------------------------------------------------------------------------

//--- CATEGORIES ---------------------------------------------------------
printLine("IMPORTING CATEGORIES");
printLine("Get categories..");
$oIHandler->importCategories();
printLine("Rebuilding category tree..");
$oIHandler->rebuildCategoryTree();
printLine("Done.\n");
//------------------------------------------------------------------------

//--- PRODUCTS -----------------------------------------------------------
printLine("IMPORTING PRODUCTS");
printLine("Get Products..");
$oIHandler->importProducts();
printLine("Get Relations..");
$oIHandler->importProduct2Categories();
printLine("Get Reviews..");
$oIHandler->importReviews();
printLine("Handle variants(options)..");
$oIHandler->importVariants();
printLine("Extended info..");
$oIHandler->importExtended();
printLine("Done.\n");
//------------------------------------------------------------------------

//--- ORDERS ------------------------------------------------------
printLine("IMPORTING ORDERS");
$oIHandler->importOrders();
printLine("Done.\n");
//------------------------------------------------------------------------

//--- IMAGES -------------------------------------------------------------
printLine("COPYING IMAGES");
printLine("Handle manufacturer icons..");
$oIHandler->handleManufacturerImages();
printLine("Handle category icons..");
$oIHandler->handleCategoryImages();
printLine("Handle product images..");
$oIHandler->handleProductImages();
printLine("Done.\n");
//------------------------------------------------------------------------

printLine("IMPORT DONE!");

printLine("</pre>");
?>