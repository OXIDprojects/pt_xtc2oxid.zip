<?php
//Configuration file for osc2oxid.php script
//edit this first
//read usage notes in osc2oxid.php file


//the path of fully installed OXID eShop
$sOxidConfigDir = "h:/apache group/apache/htdocs/seifenparadies/oxid_test/";

//Do we import from OS commerce clone XTCommerce?
//In this case available extended information is imported
$blIsXtc = true;

//installed OS Commerce DB name. (assuming the db is on the same server as OXID db)
$sOcmDb = "beckpc_games";

//picture import
//OSCommerce image dir:
$sOscImageDir = "H:/Apache Group/Apache/htdocs/seifenparadies/xtcommerce_beckpc/images/";

//Avenger
//Translation table of xtc (and family!) payment-types to OXID payment types
//'xxComerce-payment-type-name'=>'OXID-payment-type-name'
$payment_types=array(
  'banktransfer'=>'oxiddebitnote',
  'cash'=>'oxcash',
  'cc'=>'oxidcreditcard',
  'cod'=>'oxidcashondel',
  'eustandardtransfer'=>'oxidinvoice',
  'invoice'=>'oxidinvoice',
  'ipayment'=>'oxidcreditcard',
  'moneyorder'=>'oxidinvoice',
  'paypal'=>'oxpaypal',
  'uos_kreditkarte_modul'=>'oxidcreditcard',
  'uos_lastschrift_at_modul'=>'oxiddebitnote',
  'uos_lastschrift_de_modul'=>'oxiddebitnote',
  'uos_vorkasse_modul'=>'oxidpayadvance',
);  
//Avenger

//that's it!
//now run the import script.