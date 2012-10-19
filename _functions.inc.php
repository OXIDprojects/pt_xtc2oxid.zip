<?php

//here you can find functions used by osc2oxid.php import script.
//do not run this script directly.

/**
 * Show base path
 *
 * @return string
 */
function getShopBasePath()
{
    global $sOxidConfigDir;

    return $sOxidConfigDir . "/";
}

/**
 * Prints out the line
 *
 * @param string $sOut
 */
function printLine($sOut)
{
    echo $sOut . "\n";
    flush();
}

/**
 * Returns language suffix;
 *
 * @param int $iLang
 */
 
 //Avenger
function getLangSuffix($iLang)
{
  global $sXtcLangId;
  
  if ($iLang==$sXtcLangId)
  {
    return "";
  }
  elseif ($iLang>$sXtcLangId)
  {
    $iLang--;
  }
  return "_".$iLang;
}

function get_table_collation($oxDB,$db_name,$table_name)
{
  $sQ="SHOW TABLE STATUS";
  if ($db_name)
  {
    $sQ.=" FROM $db_name";
  }
  $sQ.=" LIKE '$table_name'";
  $oRs = $oxDB->Execute($sQ);
  if ( $oRs !== false && $oRs->recordCount() > 0) 
  {
    $collation=$oRs->fields['Collation'];
  }
  return $collation;
}

function split_street($street,&$street_nr)
{
  if ($street)
  {
    $street_a=explode(' ',$street);
    $add_dot=sizeof($street_a)==1;
    if ($add_dot)
    {
      $street_a=explode('.',$street);
    }
    if (sizeof($street_a)>1)
    {
      $street_nr=end($street_a);
      $n_street_nr=(float)$street_nr;
      if ($n_street_nr && is_numeric($n_street_nr))
      {
        $street=rtrim(str_replace($street_nr,'',$street));
        if ($add_dot)
        {
          $street.='.';
        }
      }
    }
  }      
  return $street;
}
//Avenger

/**
 * Dumps var to file
 *
 * @param mixed $mVar var to be dumped
 */
function exportVar($mVar)
{
    ob_start();
    var_dump($mVar);
    $sDump = ob_get_contents();
    ob_end_clean();

    file_put_contents('out.txt', $sDump."\n\n", FILE_APPEND);
}

/**
 * OS Commerce to OXID import handler
 *
 */
class ImportHandler
{
    /**
     * Max language count
     *
     * @var int
     */
    protected $_iLangCount;

    /**
     * OS Commerce DB
     *
     * @var string
     */
    protected $_sOcmDb;

    /**
     * Shop id
     *
     * @var string
     */
    protected $_sShopId;

    /**
     * OS Commerce image dir
     *
     * @var string
     */
    protected $_sOscImageDir;

    /**
     * SQL snippet for OXINCL field
     *
     * @var string
     */
    protected $_sInclField = '';

    /**
     * SQL snippet for OXINCL field value
     *
     * @var string
     */
    protected $_sInclFieldVal = '';

    /**
     * Category image path
     *
     * @var string
     */
    protected $_sCategoryImagePath = '/';

    /**
     * Manufacturer image path
     *
     * @var string
     */
    protected $_sManufacturerImagePath = '/';
    
    //Avenger
    /**
     * Default language Id of xtc Shop
     *
     * @var string
     */
    protected $_sXtcLangId = '2';
    //Avenger
    
    //Avenger
    /**
     * Default language Id of xtc Shop
     *
     * @var string
     */
    protected $_sXtcTaxRates = array(0=>0,1=>19,2=>7);
    //Avenger

    /**
     * Constructs by setting shop id
     *
     * @param int $sShopId ShopId
     */
    public function __construct($sShopId)
    {
        global $iLangCount;
        global $sOcmDb;
        global $sOscImageDir;

        //Avenger
        global $sXtcLangId;
        //Avenger

        $this->_iLangCount = $iLangCount;
        $this->_sOcmDb = $sOcmDb;
        $this->_sShopId = $sShopId;
        $this->_sOscImageDir = $sOscImageDir;
        //Avenger
        $this->_sXtcLangId = $sXtcLangId;
        //Avenger

        if (oxConfig::getInstance()->getEdition() == 'EE') {
            $this->_sInclField    = ', oxshopincl';
            $this->_sInclFieldVal = ', 1';
        }
        $oxDB=oxDb::getDb(true);
        $sQ = "SELECT * FROM $sOcmDb.products limit 1";
        $oxDB->Execute($sQ);

        if (mysql_errno())
            die("FAILURE: Can't select from OSCommerce database '$sOcmDb'");
        
        //Avenger
        //Determine active language in xtc/osc
        $sQ = "SELECT configuration_value FROM $sOcmDb.configuration where configuration_key='DEFAULT_LANGUAGE'";
        $oRs=$oxDB->Execute($sQ);
        if ( $oRs !== false && $oRs->recordCount() > 0) 
        {
          $sLangCode=$oRs->fields['configuration_value'];
          if ($sLangCode)
          {
            $sQ = "SELECT languages_id FROM $sOcmDb.languages where code='$sLangCode'";
            $oRs=$oxDB->Execute($sQ);
            if ( $oRs !== false && $oRs->recordCount() > 0) 
            {
              $this->_sXtcLangId=$oRs->fields['languages_id'];
            }
          }
        }
        //Determine tax-rates in xtc/osc
        $sQ = "SELECT configuration_value FROM $sOcmDb.configuration where configuration_key='STORE_COUNTRY'";
        $oRs=$oxDB->Execute($sQ);
        if ( $oRs !== false && $oRs->recordCount() > 0) 
        {
          $country_id=$oRs->fields['configuration_value'];
          if ($country_id)
          {
            $sQ = "SELECT configuration_value FROM $sOcmDb.configuration where configuration_key='STORE_ZONE'";
            $oRs=$oxDB->Execute($sQ);
            if ( $oRs !== false && $oRs->recordCount() > 0) 
            {
              $zone_id=$oRs->fields['configuration_value'];
              $sQ="
              SELECT 
              sum(tax_rate) as tax_rate,
              tr.tax_class_id
              from 
              $sOcmDb.tax_rates tr 
              left join $sOcmDb.zones_to_geo_zones za on (tr.tax_zone_id = za.geo_zone_id) 
              left join $sOcmDb.geo_zones tz on (tz.geo_zone_id = tr.tax_zone_id) 
              where 
              (za.zone_country_id is null or za.zone_country_id = '0' or za.zone_country_id = '$country_id ') and 
              (za.zone_id is null or za.zone_id = '0' or za.zone_id = '$zone_id') #and tr.tax_class_id = '$class_id' 
              group by tr.tax_priority";            
              $oRs=$oxDB->Execute($sQ);
              if ( $oRs !== false && $oRs->recordCount() > 0) 
              {
                $this->_sXtcTaxRates=array(0=>0,2=>0);
                while (!$oRs->EOF) 
                {
                  $this->_sXtcTaxRates[$oRs->fields['tax_class_id']]=$oRs->fields['tax_rate']/100;
                  $oRs->MoveNext();
                }
              }
            }
          }
        }
        //Avenger
    }

    /**
     * Deletes all items
     *
     */
    public function cleanUpBeforeImport()
    {
        $delete_from='delete from ';
        $where_oxshopid=" where oxshopid = '".$this->_sShopId."'";
        $oxDB=oxDb::getDb(true);

        $sQ = $delete_from."oxcategories".$where_oxshopid;
        $oxDB->Execute($sQ);

        $sQ = $delete_from."oxarticles".$where_oxshopid;
        $oxDB->Execute($sQ);

        $sQ = $delete_from."oxorderarticles";
        $oxDB->Execute($sQ);

        $sQ = $delete_from."oxmanufacturers".$where_oxshopid;
        $oxDB->Execute($sQ);
    
        $sQ=$delete_from."oxuser".$where_oxshopid."' and oxid<>'oxdefaultadmin'";
        $oxDB->Execute($sQ);
        
        $sQ=$delete_from."oxnewssubscribed where oxuserid<>'oxdefaultadmin'";
        $oxDB->Execute($sQ);
        
        $sQ = $delete_from."oxorder".$where_oxshopid;
        $oxDB->Execute($sQ);

        $sQ = $delete_from."oxorderarticles where oxordershopid = '".$this->_sShopId."'";
        $oxDB->Execute($sQ);

        $sQ = $delete_from."oxartextends";
        $oxDB->Execute($sQ);

}

    /**
     * Reads OSC languages and sets them to OXID config
     *
     */
    public function setLanguages()
    {
        $sOcmDb = $this->_sOcmDb;
        $sQ = "SELECT * FROM $sOcmDb.languages";
        $rs = oxDb::getDb(true)->Execute($sQ);
        $aLanguages = array();
        $aLangParams = array();
        $i = 0;
        while ($rs && !$rs->EOF) {
            $sName = $rs->fields["name"];
            $sCode = $rs->fields["code"];
            $sSort = $rs->fields["sort_order"];
            $iId = $rs->fields["languages_id"];
            $aLanguages[$sCode] = $sName;
            $aConfLangs[$sCode] = array('active'=>1, 'sort' => $sSort, 'baseId'=>$i++,'language_id'=>$iId);
            $rs->moveNext();
        }
        oxConfig::getInstance()->saveShopConfVar('aarr', 'aLanguages', serialize($aLanguages));
        oxConfig::getInstance()->saveShopConfVar('aarr', 'aLanguageParams', serialize($aConfLangs));
    }

    /**
     * Category importer
     */
    public function importCategories()
    {
        $iLangCount = $this->_iLangCount;
        $sOcmDb = $this->_sOcmDb;
        $sShopId = $this->_sShopId;

        //insert first language categories
        //Avenger
        $oxDB=oxDb::getDb(true);
        $oRs = $oxDB->Execute("SET SESSION sql_mode=''");  //Reset strict mode
        $sQ = "REPLACE INTO oxcategories 
          (
          oxid, 
          oxshopid, 
          oxactive, 
          oxhidden, 
          oxparentid, 
          oxsort, 
          oxtitle, 
          oxthumb {$this->_sInclField})
          (
          SELECT 
          c.categories_id, 
          '$sShopId', 
          1, 
          if (categories_status=1,0,1),
          parent_id, 
          sort_order, 
          categories_name, 
          categories_image {$this->_sInclFieldVal}
          FROM 
          $sOcmDb.categories AS c, 
          $sOcmDb.categories_description AS cd
          WHERE 
          c.categories_id = cd.categories_id AND 
          language_id = $this->_sXtcLangId)";
        $oxDB->Execute($sQ);
        $error=$oxDB->ErrorMsg();
        if ('importCategories --'.$error)
        {
          printLine($error);
        }
        //replace the rest of the languages
        for ($iLang = 1; $iLang <= $iLangCount; $iLang++) 
        {
          $sLangSuffix = getLangSuffix($iLang);
          if ($sLangSuffix)
          {
            $sQ = "
            UPDATE 
            oxcategories AS c, 
            (SELECT cd.categories_id AS id, cd.categories_name AS t FROM $sOcmDb.categories_description AS cd WHERE cd.language_id = $iLang) AS src 
            SET 
            c.oxtitle$sLangSuffix = src.t, 
            c.oxactive$sLangSuffix = 1
            WHERE 
            src.id = c.oxid";
            $oxDB->Execute($sQ);
          }
        }
        //Avenger

        $sQ = "update oxcategories set oxparentid = 'oxrootid' where oxparentid = 0";
        $oxDB->Execute($sQ);

        $sQ = "update oxcategories set oxrootid = oxid where oxparentid = 'oxrootid'";
        $oxDB->Execute($sQ);
    }

    /**
     * Rebuilds category tree.
     *
     */
    public function rebuildCategoryTree()
    {
        $oCatTree = oxNew("oxcategorylist");
        $oCatTree->updateCategoryTree(false);

    }

    //Avenger
    /**
     * Customer importer
     */
    public function importCustomers()
    {
      global $blIsXtc;
      
      if ($blIsXtc)
      {
        $sImported='xt';
      }
      else
      {
        $sImported='os';
      }
      $sImported='Imported from '.$sImported.'Commerce';
      $sOcmDb = $this->_sOcmDb;
      $sShopId = $this->_sShopId;
      $oxDB=oxDb::getDb(true);
      $collation=get_table_collation($oxDB,$sOcmDb,'customers');
      if ($collation)
      {
        $collation="COLLATION $collation";
      }
      $oRs = $oxDB->Execute("SET SESSION sql_mode=''");  //Reset strict mode
      //Copy user data
      $sQ = "
      REPLACE INTO oxuser (
        oxid, 
        oxactive, 
        oxboni,
        oxrights,
        oxshopid,
        oxpassword,
        oxusername,
        oxcustnr,
        oxustid,
        oxcompany,
        oxfname,
        oxlname,
        oxstreet,
        oxcity,
        oxcountryid,
        oxzip,
        oxaddinfo,
        oxfon,
        oxprivfon,
        oxfax,
        oxsal,
        oxbirthdate,
        oxcreate,
        oxregister
        )
        
        (
        SELECT 
        c.customers_id, 
        1,
        1000,
        if (c.customers_status=0,'malladmin','user'),
        '$sShopId', 
        'password',
        c.customers_email_address,
        if (c.customers_cid,c.customers_cid,c.customers_id+20000),
        c.customers_vat_id,
        a.entry_company,
        c.customers_firstname,
        c.customers_lastname,
        a.entry_street_address,
        a.entry_city,
        coo.oxid,
        a.entry_postcode,
        '$sImported',
        c.customers_telephone,
        c.customers_telephone,
        c.customers_fax,
        if (c.customers_gender=0,'Herr','Frau'),
        c.customers_dob,
        now(),
        if (c. customers_date_added,c. customers_date_added,now())
        FROM 
        $sOcmDb.customers c,
        $sOcmDb.address_book a,
        $sOcmDb.countries cox,
        oxcountry coo
        WHERE 
        a.customers_id = c.customers_id and
        cox.countries_id=a.entry_country_id and
        coo.oxisoalpha2=cox.countries_iso_code_2 and
        c.customers_id>1
        )";
      $oRs = $oxDB->Execute($sQ);
      $error=$oxDB->ErrorMsg();
      if ($error)
      {
        printLine('importCustomers -- '.$error);
      }
      //Separate street-number and street and build text-file with user email-addresses for notification.
      $oRs = $oxDB->execute('select oxid,oxstreet,oxusername from oxuser where oxid<>"oxdefaultadmin"');
      if ( $oRs !== false && $oRs->recordCount() > 0) 
      {
        $emails=array();
        while ( !$oRs->EOF ) 
        {
          $street=$oRs->fields['oxstreet'];
          $street_a=explode(' ',$street);
          $add_dot=sizeof($street_a)==1;
          if ($add_dot)
          {
            $street_a=explode('.',$street);
          }
          if (sizeof($street_a)>1)
          {
            $street_nr=end($street_a);
            $n_street_nr=(float)$street_nr;
            if ($n_street_nr && is_numeric($n_street_nr))
            {
              $street=rtrim(str_replace($street_nr,'',$street));
              if ($add_dot)
              {
                $street.='.';
              }
              $oxid=$oRs->fields['oxid'];
              $sQ="update oxuser set oxstreet='$street', oxstreetnr='$street_nr' where oxid='$oxid'";
              $oxDB->execute($sQ);
            }
          }
          $email=trim($oRs->fields['oxusername']);
          if ($email)
          {
            $emails[]=$email;
          }
          $oRs->moveNext();
        }
        $n=sizeof($emails);
        if ($n>0)
        {
          global $sOxidConfigDir;
          
          sort($emails);
          $emails=implode("\r\n",$emails);
          $emails_file=$sOxidConfigDir.'user_email.txt';
          $fh=@fopen($emails_file,'w');
          if ($fh)
          {
            fwrite($fh,$emails);
            fclose($fh);
            
            global $myConfig;
            
            $emails_file=$myConfig->getShopUrl().basename($emails_file);
            $s='(with '.$n.' addresses) was successfully written. (<a href="'.$emails_file.'" target="_blank">Open eMail-addresses file</a>)';
          }
          else
          {
            $s='could not be written. (Check permissions!)';
          }
          $s='eMail-addresses file "'.$emails_file.'" '.$s;
          printLine($s);
        }
      }
    }
    //Avenger

    /**
     * Manufacturer importer
     */
    public function importManufacturers()
    {
        $sOcmDb = $this->_sOcmDb;
        $sShopId = $this->_sShopId;

        //copy same title to all OXID languages
        $aLangs = oxConfig::getInstance()->getConfigParam("aLanguages");
        $iLangCount = count($aLangs);
        $sTitleFields = "";
        $sTitleVals = "";
        for($i = 1; $i < $iLangCount; $i++) {
            $sTitleFields .= ", oxtitle_".$i;
            $sTitleVals .= ", manufacturers_name";
        }

        $sQ = "REPLACE INTO oxmanufacturers (oxid, oxshopid, oxactive, oxicon, oxtitle $sTitleFields {$this->_sInclField})
                            (SELECT manufacturers_id, '$sShopId', 1, manufacturers_image, manufacturers_name $sTitleVals {$this->_sInclFieldVal} FROM $sOcmDb.manufacturers)";
        $oxDB=oxDb::getDb();
        $oxDB->Execute($sQ);
        $error=$oxDB->ErrorMsg();
        if ($error)
        {
          printLine('importManufacturers -- '.$error);
        }
    }

    /**
     * Product importer
     */
    public function importProducts()
    {
        $iLangCount = $this->_iLangCount;
        $sOcmDb = $this->_sOcmDb;
        $sShopId = $this->_sShopId;

        //insert first language categories
        //W. Kaiser
        //Consider tax rates and convert price to gross price. Store non-standard tax-rate.
        $sQ = "
        REPLACE INTO oxarticles (
        oxid, 
        oxshopid, 
        oxactive, 
        oxartnum,
        oxstock,
        oxthumb,
        oxpic1,
        oxvat,
        oxprice,
        oxinsert,
        oxweight,
        oxtitle,
        oxexturl, 
        oxsearchkeys,
        oxsoldamount,
        oxean,
        oxmanufacturerid {$this->_sInclField}
        )
        (
        SELECT 
        p.products_id,
        '$sShopId',
        products_status,
        products_model,
        products_quantity,
        products_image,
        products_image,
        if (products_tax_class_id=2,{$this->_sXtcTaxRates[2]},null),
        round(if (products_tax_class_id=1,products_price*(1+{$this->_sXtcTaxRates[1]}),if (products_tax_class_id=2,products_price*(1+{$this->_sXtcTaxRates[2]}),products_price)),2),
        products_date_added,
        products_weight,
        products_name,
        products_url,
        products_keywords,
        products_ordered,
        products_ean,
        manufacturers_id {$this->_sInclFieldVal}
        FROM 
        $sOcmDb.products AS p, 
        $sOcmDb.products_description AS pd
        WHERE 
        p.products_id = pd.products_id AND 
        language_id = $this->_sXtcLangId)";
        $oxDB=oxDb::getDb();
        $oxDB->Execute($sQ);
        $error=$oxDB->ErrorMsg();
        if ($error)
        {
          printLine('importProducts 1 -- '.$error);
        }

        $sQ = "
        REPLACE INTO 
        oxartextends (oxid, oxlongdesc)
        (
        SELECT 
        products_id, 
        products_description
        FROM 
        $sOcmDb.products_description 
        WHERE 
        language_id = $this->_sXtcLangId)";
        $oxDB->Execute($sQ);
        $error=$oxDB->ErrorMsg();
        if ($error)
        {
          printLine('importProducts 2 -- '.$error);
        }
        //Avenger

        //update the rest of the languages
        for($i = 1; $i <= $iLangCount; $i++) 
        {
          if ($i<>$this->_sXtcLangId)
          {
            $iLang = $i;
            if ($i>$this->_sXtcLangId)
            {
              $iLang--;
            }
            $sLangSuffix = "_" . $iLang;
            $sQ = "
              UPDATE oxarticles AS p, (SELECT pd.products_id AS id, pd.products_name AS t FROM $sOcmDb.products_description AS pd WHERE pd.language_id = $i) AS src 
                SET p.oxtitle$sLangSuffix = src.t WHERE src.id = p.oxid";
            oxDb::getDb()->Execute($sQ);

            //dealing with long descr
            $sQ = "
            UPDATE oxartextends AS p, (SELECT pd.products_id AS id, pd.products_description AS d FROM $sOcmDb.products_description AS pd WHERE pd.language_id = $i) AS src 
              SET p.oxlongdesc$sLangSuffix = src.d WHERE src.id = p.oxid";
            $oxDB->Execute($sQ);
          }
        }
        //Avenger
        //rating import
        $sQ = "
        UPDATE oxarticles AS t1, (SELECT products_id, AVG(reviews_rating) AS rating, count(products_id) AS cnt FROM $sOcmDb.reviews GROUP BY products_id) AS src 
          SET t1.oxrating = src.rating, t1.oxratingcnt = src.cnt WHERE t1.oxid = src.products_id";
        $oxDB->Execute($sQ);

        //delete existing category assignments
        $sQ = "DELETE FROM oxobject2category WHERE oxobjectid IN (SELECT products_id FROM $sOcmDb.products)";
        $oxDB->Execute($sQ);

    }

    /**
     * Imports produc to category relations
     *
     */
    public function importProduct2Categories()
    {
        $sOcmDb = $this->_sOcmDb;
        if ($this->_sInclField)
            $sInclFieldVal = ", ".MAX_64BIT_INTEGER;
        $sQ = "INSERT INTO oxobject2category (oxid, oxobjectid, oxcatnid {$this->_sInclField})
                          (SELECT md5(concat(t.products_id, t.categories_id, RAND())), t.products_id, t.categories_id $sInclFieldVal
                                    FROM $sOcmDb.products_to_categories AS t)";
        oxDb::getDb()->Execute($sQ);
    }

    /**
     * Imports product reviews
     *
     */
    public function importReviews()
    {
        $sOcmDb = $this->_sOcmDb;

        $sQ = "REPLACE INTO oxreviews (oxid, oxactive, oxobjectid, oxtype,    oxtext,       oxcreate,     oxlang,         oxrating)
                          (SELECT t1.reviews_id, 1,  products_id, 'oxarticle',reviews_text, date_added, languages_id - 1, reviews_rating
                                    FROM $sOcmDb.reviews AS t1, $sOcmDb.reviews_description AS t2 WHERE t1.reviews_id = t2.reviews_id)";

        $oxDB=oxDb::getDb();
        $oxDB->Execute($sQ);
        $error=$oxDB->ErrorMsg();
        if ($error)
        {
          printLine('importReviews -- '.$error);
        }
    }

    /**
     * Creates OXID variants from OS Commerce option information. Does not fully handle multiple dimension variants
     *
     */
    public function importVariants()
    {
        $iLangCount = $this->_iLangCount;
        $sOcmDb = $this->_sOcmDb;
        $sShopId = $this->_sShopId;
        $oxDB=oxDb::getDb(true);  
        //remove imported variants
        $sQ = "DELETE FROM oxarticles WHERE oxparentid <> '' AND oxparentid IN (SELECT products_id FROM $sOcmDb.products)";
        $oxDB->Execute($sQ);

        //proably it would be possible to handle it over single sql, but lets do it in the loop instead of joining 3 tables

        //first selecting option names to be used in oxvarname
        $aOptNames = array();
        $sQ = "SELECT products_options_id, language_id, products_options_name FROM $sOcmDb.products_options";
        $rs = $oxDB->Execute($sQ);
        if ($rs && $rs->recordCount()>0) 
        {
          while (!$rs->EOF) 
          {
              $iLang = $rs->fields["language_id"];
              $iOptId = $rs->fields["products_options_id"];
              $aOptNames[$iOptId][$iLang] = $rs->fields["products_options_name"];
              $rs->MoveNext();
          }
        }
        //Avenger
        //first selecting option values names to be used
        $aOptValuesNames = array();
        $sQ = "SELECT products_options_values_id,language_id,products_options_values_name FROM $sOcmDb.products_options_values";
        $rs = $oxDB->Execute($sQ);
        if ($rs && $rs->recordCount()>0) 
        {
          while (!$rs->EOF) 
          {
            $iLang = $rs->fields["language_id"];
            $iOptId = $rs->fields["products_options_values_id"];
            $aOptValuesNames[$iOptId][$iLang] = $rs->fields["products_options_values_name"];
            $rs->MoveNext();
          }
        }
        //Avenger
        //now lets read all attribute values and put them as variants
        $sQ = "
        SELECT 
        * 
        FROM $sOcmDb.products_attributes 
        order by products_id,options_id,options_values_id";

        $rs = $oxDB->Execute($sQ);
        //Avenger
        $current_parent_product=0;
        $total_attributes=$rs->recordCount();
        $total_attributes_text=' von '.number_format($total_attributes,0, '', '.');
        while ($rs && $rs->recordCount()>0 && !$rs->EOF) 
        {
            $iParentProd = $rs->fields["products_id"];
            $iOption = $rs->fields["options_id"];
            $iOptValId = $rs->fields["options_values_id"];
            if ($current_parent_product<>$iParentProd)  
            {
              $current_parent_product=$iParentProd;
              $current_option=$current_option_count=0;
              //parent OXVARNAME values
              foreach ($aOptNames[$iOption] as $iLang => $sName) 
              {
                $sLangSuffix = getLangSuffix($iLang);
                if (!$sLangSuffix)
                {
                  $option_name=$sName;
                }
                $sQ1 = "UPDATE oxarticles SET oxvarname$sLangSuffix = '$sName', oxvarstock = 1, oxvarcount = oxvarcount + 1 where oxid = '$iParentProd'";
                $oxDB->Execute($sQ1);
              }
              //Get tax-rate of parent-product
              $sQ = "
              SELECT 
              products_tax_class_id 
              FROM 
              $sOcmDb.products
              WHERE 
              products_id=$iParentProd";
              $oRs=$oxDB->Execute($sQ);
              if ( $oRs !== false && $oRs->recordCount() > 0) 
              {
                $tax_class_id=$oRs->fields['products_tax_class_id'];
                $tax_rate=1+$this->_sXtcTaxRates[$tax_class_id];
              }
            }
            if ($current_option<>$iOption)
            {
              $current_option_count++;
            }
            if ($current_option_count==1)
            {
              $current_option=$iOption;
              if ($aOptValuesNames[$iOptValId])   
              {
                //create variant article
                $sProdId = oxUtilsObject::getInstance()->generateUID();
                $dPrice = $oxDB->getOne("SELECT oxprice FROM oxarticles WHERE oxid = '$iParentProd'");
                $options_values_price=$rs->fields["options_values_price"];
                if ($tax_rate)
                {
                  $options_values_price=round($options_values_price*$tax_rate,2);
                }
                if ($rs->fields["price_prefix"] == "+")
                {
                  $dPrice += $options_values_price;
                }
                else
                {
                  $dPrice -= $options_values_price;
                }
                $iStock = $this->_getOptionStock($rs);
                $dWeight = $this->_getOptionWeight($rs);

                $sQ2 = "INSERT INTO oxarticles 
                (
                oxid,
                oxshopid,
                oxparentid,
                oxactive,
                oxprice,
                oxstockflag,
                oxstock, 
                oxweight {$this->_sInclField}
                )
                VALUES (
                '$sProdId',
                '$sShopId',
                '$iParentProd',
                1,
                $dPrice,
                1,
                '$iStock',
                '$dWeight' {$this->_sInclFieldVal}
                )";
                $oxDB->Execute($sQ2);
                //OXVARSELECT VALUE
                foreach ($aOptValuesNames[$iOptValId] as $iLang => $sOptName) 
                {
                  $sLangSuffix = getLangSuffix($iLang);
                  if (!$sLangSuffix)
                  {
                    $option_value_name=$sOptName;
                  }
                  $sQ4 = "UPDATE oxarticles SET oxvarselect$sLangSuffix = '$sOptName' WHERE oxid = '$sProdId'";
                  $oxDB->Execute($sQ4);
                }
              }
            }
            $rs->moveNext();
        }
        //Avenger
    }

    /**
     * Copies manufacturer images
     *
     */
    public function handleManufacturerImages()
    {
      $sOscImageDir = $this->_sOscImageDir;
      $sQ = "SELECT oxid, oxicon FROM oxmanufacturers";
      $rs = oxDb::getDb(true)->Execute($sQ);
      while ($rs && $rs->recordCount()>0 && !$rs->EOF) 
      {
        $sImg = $rs->fields["oxicon"];
        //copy image
        $sSrcName = $sOscImageDir . $this->_sManufacturerImagePath . $sImg;
        if (file_exists($sSrcName) && !is_dir($sSrcName))
            copy($sSrcName, oxConfig::getInstance()->getAbsDynImageDir() . "/icon/". basename($sImg));
        $sImg = basename($sImg);
        $sQ1 = "UPDATE oxmanufacturers SET oxicon = '$sImg' WHERE oxid = '".$rs->fields["oxid"]."'";
        oxDb::getDb(true)->Execute($sQ1);
        $rs->moveNext();
      }
    }

    /**
     * Copy category images
     *
     */
    public function handleCategoryImages()
    {
        $sOscImageDir = $this->_sOscImageDir;
        $sOcmDb = $this->_sOcmDb;
        $image_dir=oxConfig::getInstance()->getAbsDynImageDir();
        $sQ = "SELECT oxid, oxthumb FROM oxcategories WHERE oxid IN (SELECT categories_id FROM {$sOcmDb}.categories)";
        $rs = oxDb::getDb(true)->Execute($sQ);
        while ($rs && $rs->recordCount()>0 && !$rs->EOF) 
        {
            $sImg = $rs->fields["oxthumb"];
            //copy image
            $sSrcName = $sOscImageDir . $this->_sCategoryImagePath . $sImg;
            if (file_exists($sSrcName) && !is_dir($sSrcName)) {
                copy($sSrcName, $image_dir . "/0/". basename($sImg));
            }

            $sImg = basename($sImg);
            $sQ1 = "UPDATE oxcategories SET oxthumb = '$sImg' WHERE oxid = '".$rs->fields["oxid"]."'";
            oxDb::getDb(true)->Execute($sQ1);

            $rs->moveNext();
        }
    }

    /**
     * Copy product images
     *
     */
    public function handleProductImages()
    {
        $sOscImageDir = $this->_sOscImageDir;
        $sOcmDb = $this->_sOcmDb;
        $image_dir=oxConfig::getInstance()->getAbsDynImageDir();

        $sQ = "SELECT oxid, oxthumb, oxpic1 FROM oxarticles WHERE oxid in (SELECT products_id FROM $sOcmDb.products)";
        $rs = oxDb::getDb(true)->Execute($sQ);
        while ($rs && $rs->recordCount()>0 && !$rs->EOF) {
          $sImg = $rs->fields["oxthumb"];
            //copy image
          if ($sImg) {
              $sSrcName = $sOscImageDir . "/" . $sImg;
              if (file_exists($sSrcName) && !is_dir($sSrcName)) {
                  copy($sSrcName, $image_dir . "/0/". basename($sImg));
                  copy($sSrcName, $image_dir . "/1/". basename($sImg));
              }
              $sImg = basename($sImg);
              $sQ1 = "UPDATE oxarticles SET oxthumb = '$sImg', oxpic1 = '$sImg' WHERE oxid = '".$rs->fields["oxid"]."'";
              oxDb::getDb(true)->Execute($sQ1);
          }
          $rs->moveNext();
        }
    }

    /**
     * Reserved for extended info import
     *
     */
    public function importExtended()
    {

    }

    /**
     * Returns option stock value
     *
     * @param resource $rs
     * @return int
     */
    protected function _getOptionStock($rs)
    {
        return 1;
    }

    /**
     * Gets Variant weight
     *
     * @param resource $rs
     * @return int
     */
    protected function _getOptionWeight($rs)
    {
        return 0;
    }
}

class XtImportHandler extends ImportHandler
{

    /**
     * Max image count
     *
     * @var int
     */
    protected $_iMaxImages = 7;

    /**
     * Category image path
     *
     * @var string
     */
    protected $_sCategoryImagePath = '/categories/';

    /**
     * Manufacturer image path
     *
     * @var string
     */
    protected $_sManufacturerImagePath = '/';


    /**
     * Aditionally import meta keywords, search words, short description, EAN, images, scale prices, crossselling products
     *
     */
    public function importProducts()
    {
        $sOcmDb = $this->_sOcmDb;
        $iLangCount = $this->_iLangCount;
        $sShopId = $this->_sShopId;

        parent::importProducts();

        //Importing search keywords
        for($i = 1; $i <= $iLangCount; $i++) {
            $sLangSuffix = getLangSuffix($i);

            $sQ = "UPDATE oxarticles, (SELECT products_keywords, products_short_description, products_id FROM $sOcmDb.products_description WHERE language_id = $i) AS src SET oxsearchkeys$sLangSuffix = src.products_keywords, oxshortdesc$sLangSuffix = src.products_short_description WHERE  src.products_id = oxid";
            oxDb::getDb(true)->Execute($sQ);

            $sQ = "UPDATE oxartextends, (SELECT products_keywords, products_id FROM $sOcmDb.products_description WHERE language_id = $i) AS src SET oxtags$sLangSuffix = src.products_keywords WHERE  src.products_id = oxid";
            oxDb::getDb(true)->Execute($sQ);
        }

        //import additional images
        for($i = 0; $i < $this->_iMaxImages; $i++) {
            $sZoomImg = '';
            if ($i <=4)
                $sZoomImg = ", oxzoom$i = src.image_name ";
            $j = $i + 1;
            $sQ = "UPDATE oxarticles, (SELECT image_name, products_id FROM $sOcmDb.products_images WHERE image_nr = $i) AS src SET oxpic$j = src.image_name $sZoomImg WHERE  src.products_id = oxid";
            oxDb::getDb(true)->Execute($sQ);
        }

        //import scale prices
        //delete existing scale price assignments
        $sQ = "DELETE FROM oxprice2article WHERE oxartid IN (SELECT products_id FROM $sOcmDb.products)";
        oxDb::getDb()->Execute($sQ);

        $sQ = "SELECT * FROM $sOcmDb.products_graduated_prices";
        $aScalePrices = array();
        $rs = oxDb::getDb(true)->Execute($sQ);
        while($rs && $rs->recordCount()>0 && !$rs->EOF) {
            $iProduct = $rs->fields["products_id"];
            $iQuantity = $rs->fields["quantity"];
            $dPrice = $rs->fields["unitprice"];
            $aScalePrices[$iProduct][$iQuantity] = $dPrice;
            $rs->MoveNext();
        }

        foreach($aScalePrices as $iProduct => $aPrices) {
            ksort($aPrices);
            $iQFrom = 0;
            foreach ($aPrices as $iQuantity => $dPrice) {
                $iQTo = $iQuantity - 1;
                if ($iQFrom && $iQTo) {
                    $sQ = "INSERT INTO oxprice2article (oxid,                oxshopid,   oxartid, oxaddabs,   oxamount, oxamountto) VALUES
                                (md5(concat('$iProduct', $iQFrom, RAND())), '$sShopId','$iProduct', $dNewPrice, $iQFrom, $iQTo)";
                    oxDb::getDb(true)->Execute($sQ);
                }
                $dNewPrice = $dPrice;
                $iQFrom = $iQuantity;
            }

            $iQTo = 99999999;
            $sQ = "INSERT INTO oxprice2article (oxid,                oxshopid,   oxartid, oxaddabs,   oxamount, oxamountto) VALUES
                                (md5(concat('$iProduct', $iQFrom, RAND())), '$sShopId','$iProduct', $dNewPrice, $iQFrom, $iQTo)";
            oxDb::getDb(true)->Execute($sQ);
        }


        //import crossell
        $sQ = "REPLACE INTO oxobject2article (oxid, oxobjectid, oxarticlenid, oxsort)
                                        (SELECT ID, xsell_id, products_id, sort_order
                                           FROM $sOcmDb.products_xsell)";
        oxDb::getDb(true)->Execute($sQ);

    }

    /**
     * Additionally imports category description
     */
    public function importCategories()
    {
        $sOcmDb = $this->_sOcmDb;
        $iLangCount = $this->_iLangCount;
        $sShopId = $this->_sShopId;

        parent::importCategories();

        //Importing category description
        for($i = 1; $i <= $iLangCount; $i++) 
        {
            $sLangSuffix = getLangSuffix($i);
            $sQ = "
              UPDATE oxcategories, (SELECT categories_id, categories_heading_title, categories_description FROM $sOcmDb.categories_description WHERE language_id = $i) AS src S
                ET oxlongdesc$sLangSuffix = src.categories_description, oxdesc$sLangSuffix = src.categories_heading_title WHERE  src.categories_id = oxid";
            oxDb::getDb(true)->Execute($sQ);
        }
    }

    public function importOrders()
    {
      $sOcmDb = $this->_sOcmDb;
      $iLangCount = $this->_iLangCount;
      $sShopId = $this->_sShopId;
      $oxDB=oxDb::getDb(true);
      //Import order data
      $sQ = "
      REPLACE INTO oxorder (
        oxid,
        oxshopid,
        oxuserid,
        oxorderdate,
        oxordernr,
        oxbillcompany,
        oxbillemail,
        oxbillfname,
        oxbilllname,
        oxbillstreet,
        oxbillstreetnr,
        oxbilladdinfo,
        oxbillustid,
        oxbillcity,
        oxbillcountryid,
        oxbillzip,
        oxbillfon,
        oxbillfax,
        oxbillsal,
        oxdelcompany,
        oxdelfname,
        oxdellname,
        oxdelstreet,
        oxdelstreetnr,
        oxdeladdinfo,
        oxdelcity,
        oxdelcountryid,
        oxdelzip,
        oxdelfon,
        oxdelfax,
        oxdelsal,
        oxpaymentid,
        oxpaymenttype,
        oxtotalnetsum,
        oxtotalbrutsum,
        oxtotalordersum,
        oxdelcost,
        oxdelvat,
        oxpaycost,
        oxpayvat,
        oxwrapcost,
        oxwrapvat,
        oxcardid,
        oxcardtext,
        oxdiscount,
        oxexport,
        oxbillnr,
        oxtrackcode,
        oxsenddate,
        oxremark,
        oxvoucherdiscount,
        oxcurrency,
        oxcurrate,
        oxfolder,
        oxpident,
        oxtransid,
        oxpayid,
        oxxid,
        oxpaid,
        oxstorno,
        oxip,
        oxtransstatus,
        oxlang,
        oxinvoicenr,
        oxdeltype
      )
      (
      SELECT 
      orders_id,
      '$sShopId',
      if (customers_cid,customers_cid,customers_id+20000),
      date_purchased,
      orders_id,
      billing_company,
      customers_email_address,
      billing_firstname,
      billing_lastname,
      billing_street_address,
      '',
      '',
      customers_vat_id,
      billing_city,
      billing_country_iso_code_2,
      billing_postcode,
      customers_telephone,
      '',
      '',
      delivery_company,
      delivery_firstname,
      delivery_lastname,
      delivery_street_address,
      '',
      '',
      delivery_city,
      delivery_country_iso_code_2,
      delivery_postcode,
      customers_telephone,
      '',
      '',
      0,
      0,
      0,
      0,
      0,
      0,
      0,
      0,
      0,
      0,
      0,
      0,
      '',
      0,
      0,
      orders_id,
      '',
      IF (orders_date_finished IS NOT NULL,orders_date_finished,now()),
      comments,
      0,
      currency,
      currency_value,
      'ORDERFOLDER_NEW',
      0,
      0,
      0,
      0,
      0,
      0,
      LEFT(customers_ip,16),
      '',
      0,
      orders_id,
      'oxidstandard'
      FROM 
      $sOcmDb.orders)";
      $oxDB->Execute($sQ);
      $error=$oxDB->ErrorMsg();
      if ($error)
      {
        printLine('importOrders 1 -- '.$error);
      }
      $oxConfig=oxconfig::getInstance();
      $mydb=$oxConfig->getConfigParam( 'dbName' );      
      //Import order detail data
      $sQ = "
      REPLACE INTO $mydb.oxorderarticles (
        oxid,
        oxorderid,
        oxamount,
        oxartid,
        oxartnum,
        oxtitle,
        oxshortdesc,
        oxselvariant,
        oxnetprice,
        oxbrutprice,
        oxvatprice,
        oxvat,
        oxpersparam,
        oxprice,
        oxbprice,
        oxnprice,
        oxwrapid,
        oxexturl,
        oxurldesc,
        oxurlimg,
        oxthumb,
        oxpic1,
        oxpic2,
        oxpic3,
        oxpic4,
        oxpic5,
        oxweight,
        oxstock,
        oxdelivery,
        oxinsert,
        oxtimestamp,
        oxlength,
        oxwidth,
        oxheight,
        oxfile,
        oxsearchkeys,
        oxtemplate,
        oxquestionemail,
        oxissearch,
        oxfolder,
        oxsubclass,
        oxstorno,
        oxordershopid
      )
      (
      SELECT 
        orders_id,
        orders_id,
        products_quantity,
        products_id,
        products_model,
        oa.oxtitle,
        oa.oxshortdesc,
        '',
        if (allow_tax=1,round(products_price,2)-round(products_tax,2),products_price),
        products_price,
        products_price*(products_tax/100),
        products_tax,
        '',
        round(products_price,2),
        round(products_price,2),
        round(products_price,2),
        0,
        oa.oxexturl,
        oa.oxurldesc,
        oa.oxurlimg,
        oa.oxthumb,
        oa.oxpic1,
        oa.oxpic2,
        oa.oxpic3,
        oa.oxpic4,
        oa.oxpic5,
        oa.oxweight,
        oa.oxstock,
        oa.oxdelivery,
        oa.oxinsert,
        oa.oxinsert,
        oa.oxlength,
        oa.oxwidth,
        oa.oxheight,
        oa.oxfile,
        oa.oxsearchkeys,
        oa.oxtemplate,
        oa.oxquestionemail,
        oa.oxissearch,
        oa.oxfolder,
        oa.oxsubclass,
        0,
        '$sShopId'
      FROM 
      $sOcmDb.orders_products op,
      $mydb.oxarticles oa
      WHERE
      oa.oxid=op.products_id)";
      $oxDB->Execute($sQ);
      $error=$oxDB->ErrorMsg();
      if ($error)
      {
        printLine('importOrders 2 -- '.$error);
      }
      //Loop thru orders to do some updates
      $oRs = $oxDB->execute('select oxid,oxorderdate,oxpaid,oxbillstreet,oxdelstreet,oxbillcountryid,oxdelcountryid from oxorder');
      if ( $oRs !== false && $oRs->recordCount() > 0) 
      {
        global $payment_types;
        
        $ot_sql="
          select 
            oph.orders_status_id as status,
            o.payment_method,
            ot.class,
            ot.value
          from 
            $sOcmDb.orders o,
            $sOcmDb.orders_products op,
            $sOcmDb.orders_total ot,
            $sOcmDb.orders_status_history oph
          where 
            o.orders_id='%s' and
            op.orders_id=o.orders_id and
            ot.orders_id=o.orders_id and
            oph.orders_id=o.orders_id
            order by oph.orders_status_id desc,ot.class
";
        $sQ = "select oxid,oxisoalpha2 from oxcountry";
        $oRs1 = $oxDB->Execute($sQ);
        if ( $oRs1 !== false && $oRs1->recordCount() > 0) 
        {
          $country_ids=array();
          while (!$oRs1->EOF) 
          {
            $country_ids[$oRs1->fields('oxisoalpha2')]=$oRs1->fields('oxid');
            $oRs1->moveNext();
          }
        }
        $sQ = "
        select 
        * 
        from 
        $sOcmDb.orders_status
        WHERE
        language_id=$this->_sXtcLangId";
        $oRs1 = $oxDB->Execute($sQ);
        if ( $oRs1 !== false && $oRs1->recordCount() > 0) 
        {
          $orders_status=array();
          while (!$oRs1->EOF) 
          {
            $orders_status[$oRs1->fields['orders_status_id']]=$oRs1->fields['orders_status_name'];
            $oRs1->moveNext();
          }
        }
        $seven_days=3600*24*7;
        while ( !$oRs->EOF ) 
        {
          //Separate street-number and street.
          $oxid=$oRs->fields['oxid'];
          $oxbillstreet=$oRs->fields['oxbillstreet'];
          $oxbillstreet=split_street($oxbillstreet,$oxbillstreetnr);
          $oxdelstreet=$oRs->fields['oxdelstreet'];
          $oxdelstreet=split_street($oxdelstreet,$oxdelstreetnr);
          //Get countrycodes.
          $oxbillcountryid=$country_ids[$oRs->fields['oxbillcountryid']];
          $oxdelcountryid=$country_ids[$oRs->fields['oxdelcountryid']];
          $sQ=sprintf($ot_sql,$oxid);
          $oRs1=$oxDB->execute($sQ);
          if ( $oRs1 !== false && $oRs1->recordCount() > 0) 
          {
            $oxfolder='ORDERFOLDER_NEW';
            $oxpaymenttype=$oxtransstatus=$oxpaid='';
            $oxtotalnetsum=$oxtotalbrutsum=$oxtotalordersum=$oxdelcost=$oxdelvat=$oxpaycost=$oxpayvat=$oxdiscount=$oxvoucherdiscount=0;
            $status=$oRs1->fields['status'];
            $oxtransstatus=$orders_status[$status];
            if ($status>2)
            {
              //$oxfolder='ORDERFOLDER_FINISHED';
              $oxorderdate=strtotime($oRs->fields['oxorderdate']);
              $oxpaid=$oxorderdate+$seven_days;
            }
            $payment_method=$oRs1->fields['payment_method'];
            $oxpaymenttype=$payment_types[$payment_method];
            if (!$oxpaymenttype)
            {
              $oxpaymenttype=$payment_method;
              printLine('Unknown payment type "'.$payment_method.'" in order-nr.'.$oxid);
            }
            while (!$oRs1->EOF)
            {
             if ($oRs1->fields['status']<>$status)
              {
                break;
              }
              $ot_class=$oRs1->fields['class'];
              $ot_value=$oRs1->fields['value'];
              switch ($ot_class)
              {
                case 'ot_cod_fee':
                case 'ot_ps_fee':
                case 'ot_loworderfee':
                  $oxpaycost=$ot_value;
                  break;
                case 'ot_discount':
                  $oxdiscount=$ot_value;
                  break;
                case 'ot_coupon':
                case 'ot_gv':
                case 'ot_redemptions':
                  $oxvoucherdiscount+=$ot_value;
                  break;
                case 'ot_payment':
                  break;
                case 'ot_shipping':
                  $oxdelcost=$ot_value;
                  break;
                case 'ot_subtotal':
                  $oxtotalbrutsum=$ot_value;
                  break;
                case 'ot_subtotal_no_tax':
                  $oxtotalnetsum=$ot_value;
                  break;
                case 'ot_tax':
                  $tax=$ot_value;
                  break;
                case 'ot_total':
                  $oxtotalordersum=$ot_value;
                  break;
              }
              $oRs1->moveNext();
            }
            if ($tax>0)
            {
              if (!$oxtotalnetsum)
              {
                $oxtotalnetsum=$oxtotalbrutsum-$tax;
              }
              $tax_p=round($tax/$oxtotalnetsum,4);
              $oxtotalnetsum=round($oxtotalnetsum,2);
              /*)
              $oxdelvat=round($oxdelcost*$tax_p,2); 
              $oxpayvat=round($oxpaycost*$tax_p,2); 
              */
              $oxdelvat=$oxpayvat=$tax_p*100; 
            }
            else
            {
              $oxtotalnetsum=$oxtotalbrutsum;
            }
            $oxtotalnetsum=round($oxtotalnetsum,2);
            $oxtotalbrutsum=round($oxtotalbrutsum,2); 
            $oxtotalordersum=round($oxtotalordersum,2); 
            $sQ="
            update 
              oxorder 
            SET 
              oxbillstreet='$oxbillstreet', 
              oxbillstreetnr='$oxbillstreetnr',
              oxdelstreet='$oxdelstreet', 
              oxdelstreetnr='$oxdelstreetnr',
              oxbillcountryid='$oxbillcountryid',
              oxdelcountryid='$oxdelcountryid',
              oxpaymenttype='$oxpaymenttype',
              oxtotalnetsum=$oxtotalnetsum, 
              oxtotalbrutsum=$oxtotalbrutsum, 
              oxtotalordersum=$oxtotalordersum, 
              oxdelcost=$oxdelcost, 
              oxdelvat=$oxdelvat, 
              oxpaycost=$oxpaycost, 
              oxpayvat=$oxpayvat, 
              oxdiscount=$oxdiscount, 
              oxvoucherdiscount=$oxvoucherdiscount, 
              oxtransstatus='$oxtransstatus',
              oxfolder='$oxfolder'";
              if ($oxpaid)
              {
                $oxpaid=date('Y-m-d H:i:s',$oxpaid);
                $sQ.=",
              oxpaid='$oxpaid',
              oxsenddate='$oxpaid'";
              }
              $sQ.="
              where 
              oxid='$oxid'
";
            $oxDB->execute($sQ);
          }
          $oRs->moveNext();
        }
      }  
    }

    /**
     * Copy product images
     *
     */
    public function handleProductImages()
    {
        $sOcmDb = $this->_sOcmDb;
        $sOscImageDir = $this->_sOscImageDir;

        $aPics = array();
        for($i = 1; $i < $this->_iMaxImages; $i++)
            $aPics[] = "oxpic".$i;
        $sPics = implode(', ', $aPics);
        $image_dir=oxConfig::getInstance()->getAbsDynImageDir();
        //take all imported products
        $sQ = "SELECT oxid, oxthumb, $sPics FROM oxarticles WHERE oxid in (SELECT products_id FROM $sOcmDb.products)";
        $rs = oxDb::getDb(true)->Execute($sQ);
        while ($rs && $rs->recordCount()>0 && !$rs->EOF) {
            $sImg = $rs->fields["oxthumb"];
            //copy images
            if ($sImg) 
            {
                $sSrcName = $sOscImageDir . "/product_images/thumbnail_images/" . $sImg;
                if (file_exists($sSrcName) && !is_dir($sSrcName))
                    copy($sSrcName, $image_dir . "/0/". basename($sImg));

                $sImg = basename($sImg);
                $sQ1 = "UPDATE oxarticles SET oxthumb = '$sImg' WHERE oxid = '".$rs->fields["oxid"]."'";
                oxDb::getDb(true)->Execute($sQ1);
            }

            for($i = 1; $i < $this->_iMaxImages; $i++) {
                $sImg = $rs->fields["oxpic".$i];
                if ($sImg) 
                {
                    //copy oxpic1,2,3,.. images
                    $sSrcName = $sOscImageDir . "/product_images/info_images/" . $sImg;
                    if (file_exists($sSrcName) && !is_dir($sSrcName))
                    {
                      $copy_dir=$image_dir . "/$i/";
                      if (is_dir($copy_dir))
                      {
                        copy($sSrcName, $copy_dir. basename($sImg));
                      }
                    }
                    //copy oxzoom1,2,.. imagess
                    $sSrcName = $sOscImageDir . "/product_images/popup_images/" . $sImg;
                    if (file_exists($sSrcName) && !is_dir($sSrcName))
                    {
                      $copy_dir=$image_dir . "/z$i/";
                      if (is_dir($copy_dir))
                      {
                        copy($sSrcName, $copy_dir. basename($sImg));
                      }
                    }
                    $sImg = basename($sImg);
                    $sZoomUpdate = '';
                    if ($i <= 4)
                        $sZoomUpdate = ", oxzoom$i = '$sImg' ";
                    $sQ1 = "UPDATE oxarticles SET oxpic$i = '$sImg' $sZoomUpdate WHERE oxid = '".$rs->fields["oxid"]."'";
                    oxDb::getDb(true)->Execute($sQ1);
                }
            }

            $rs->moveNext();
        }
    }

    /**
     * Imports newsletter information
     *
     */
    public function importExtended()
    {
        $sOcmDb = $this->_sOcmDb;
        $sQ = "
        REPLACE INTO oxnewssubscribed 
        (
          oxid,
          oxfname,
          oxlname,
          oxemail,
          oxdboptin,
          oxsubscribed
          )
          (
          SELECT 
          mail_id,
          customers_firstname,
          customers_lastname, 
          customers_email_address, 
          mail_status,
          date_added
          FROM 
          $sOcmDb.newsletter_recipients 
          WHERE 
          mail_id NOT IN (SELECT oxid FROM oxnewssubscribed)
          )";
        $oxDB=oxDb::getDb();
        $oxDB->Execute($sQ);
        $error=$oxDB->ErrorMsg();
        if ($error)
        {
          printLine('importOrders 1 -- '.$error);
        }
    }

    /**
     * Returns option stock value
     *
     * @param resource $rs
     * @return int
     */
    protected function _getOptionStock($rs)
    {
        return $rs->fields["attributes_stock"];
    }

    /**
     * Gets Variant weight
     *
     * @param resource $rs
     * @return int
     */
    protected function _getOptionWeight($rs)
    {
        return $rs->fields["options_values_weight"];
    }

}