<?php

/*
 * @module		ECommerce
 * @version		1.0
 * @copyright	Auguria
 * @author		<franck.charpentier@auguria.net>
 * @licence		GNU General Public License
 */
/* PAGE setup ecommerce */

$res=0;
if (! $res && ! empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) $res=@include($_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php");
if (! $res && file_exists("../../main.inc.php")) $res=@include("../../main.inc.php");
if (! $res && file_exists("../../../main.inc.php")) $res=@include("../../../main.inc.php");
if (! $res && file_exists("../../../../main.inc.php")) $res=@include("../../../../main.inc.php");
if (! $res && file_exists("../../../../../main.inc.php")) $res=@include("../../../../../main.inc.php");
if (! $res && preg_match('/\/nltechno([^\/]*)\//',$_SERVER["PHP_SELF"],$reg)) $res=@include("../../../../dolibarr".$reg[1]."/htdocs/main.inc.php"); // Used on dev env only
if (! $res && preg_match('/\/teclib([^\/]*)\//',$_SERVER["PHP_SELF"],$reg)) $res=@include("../../../../dolibarr".$reg[1]."/htdocs/main.inc.php"); // Used on dev env only
if (! $res) die("Include of main fails");

if (!defined('DOL_CLASS_PATH'))
    define('DOL_CLASS_PATH', null);

if (DOL_CLASS_PATH == null)
    dol_include_once('/ecommerce/inc/pre.inc.php');

require_once(DOL_DOCUMENT_ROOT . '/core/lib/admin.lib.php');
dol_include_once('/ecommerce/class/data/eCommerceSite.class.php');
dol_include_once('/ecommerce/admin/class/gui/eCommerceMenu.class.php');

require_once(DOL_DOCUMENT_ROOT . '/categories/' . DOL_CLASS_PATH . 'categorie.class.php');

$langs->load('admin');
$langs->load('ecommerce@ecommerce');
$siteId = null;
$errors = array();
$success = array();
//CHECK ACCESS
if (!$user->admin || !$user->rights->ecommerce->site)
    accessforbidden();

//DATABASE ACCESS
$siteDb = new eCommerceSite($db);


/*
 * Actions
 */
 
if ($_POST['site_form_detail_action'] == 'save')
{
    if (trim($_POST['ecommerce_name']) == '')
        $errors[] = $langs->trans('ECommerceSetupNameEmpty');
    if ($_POST['ecommerce_fk_cat_product'] == 0)
        $errors[] = $langs->trans('ECommerceSetupCatProductEmpty');
    if ($_POST['ecommerce_fk_cat_societe'] == 0)
        $errors[] = $langs->trans('ECommerceSetupCatSocieteEmpty');
    if ($_POST['ecommerce_type'] == 0)
        $errors[] = $langs->trans('ECommerceSetupTypeEmpty');
    if (trim($_POST['ecommerce_webservice_address']) == '')
        $errors[] = $langs->trans('ECommerceSetupAddressEmpty');
    if ($_POST['ecommerce_user_password'] != $_POST['ecommerce_user_password2'])
        $errors[] = $langs->trans('ECommerceSetupPasswordNotSame');
    if (trim($_POST['ecommerce_timeout']) == '')
        $errors[] = $langs->trans('ECommerceSetupTimeoutEmpty');
    elseif (!ctype_digit($_POST['ecommerce_timeout']))
        $errors[] = $langs->trans('ECommerceSetupTimeoutMustBeInt');

    if ($errors == array())
    {
        $db->begin();
        $siteDb->name = $_POST['ecommerce_name'];
        $siteDb->type = $_POST['ecommerce_type'];
        $siteDb->webservice_address = $_POST['ecommerce_webservice_address'];
        $siteDb->user_name = $_POST['ecommerce_user_name'];
        $siteDb->user_password = $_POST['ecommerce_user_password'];
        $siteDb->filter_label = $_POST['ecommerce_filter_label'];
        $siteDb->filter_value = $_POST['ecommerce_filter_value'];
        $siteDb->fk_cat_societe = $_POST['ecommerce_fk_cat_societe'];
        $siteDb->fk_cat_product = $_POST['ecommerce_fk_cat_product'];
        $siteDb->last_update = $_POST['ecommerce_last_update'];
        $siteDb->timeout = $_POST['ecommerce_timeout'];
        $siteDb->magento_use_special_price = ($_POST['ecommerce_magento_use_special_price'] ? 1 : 0);
        $siteDb->magento_price_type = $_POST['ecommerce_magento_price_type'];

        $result;
        if (intval($_POST['ecommerce_id']))
        {            
            $siteDb->id = $_POST['ecommerce_id'];
            $result = $siteDb->update($user);
        } else
        {            
            $result = $siteDb->create($user);
        }

        if ($result > 0)
        {
            $eCommerceMenu = new eCommerceMenu($db, $siteDb);
            $eCommerceMenu->updateMenu();
            $db->commit();
            setEventMessages($langs->trans('ECommerceSetupSaved'), null);
        } else
        {
            $db->rollback();
            setEventMessages($langs->trans('ECommerceSetupErrorDb'), null, 'errors');
        }
    }
}
//DELETE
elseif ($_POST['site_form_detail_action'] == 'delete')
{
    $siteDb->id = $_POST['ecommerce_id'];
    $result = $siteDb->delete($user);
    if ($result < 0)
        $errors[] = $langs->trans('ECommerceDeleteErrorDb');
    else
    {
        $eCommerceMenu = new eCommerceMenu($db, $siteDb);
        $eCommerceMenu->updateMenu();
        $success[] = $langs->trans('ECommerceDeleteOk');
        $siteDb->id = null;
        unset($_POST);
    }
}

//LOAD SITE
if (isset($_POST['site_form_select_site']))
    $siteId = $_POST['site_form_select_site'];
elseif (isset($_POST['ecommerce_id']))
    $siteId = $_POST['ecommerce_id'];
if ($siteId != null)
    $siteDb->fetch($siteId);

$sites = $siteDb->listSites();
$siteTypes = $siteDb->getSiteTypes();
$classCategorie = new Categorie($db);
$productCategories = $classCategorie->get_full_arbo(0);
$societeCategories = $classCategorie->get_full_arbo(2);

//SET VARIABLES
$ecommerceId = ($_POST['ecommerce_id'] ? $_POST['ecommerce_id'] : $siteDb->id);
$ecommerceName = ($_POST['ecommerce_name'] ? $_POST['ecommerce_name'] : $siteDb->name);
$ecommerceType = ($_POST['ecommerce_type'] ? $_POST['ecommerce_type'] : intval($siteDb->type));
$ecommerceWebserviceAddress = ($_POST['ecommerce_webservice_address'] ? $_POST['ecommerce_webservice_address'] : $siteDb->webservice_address);
$ecommerceUserName = ($_POST['ecommerce_user_name'] ? $_POST['ecommerce_user_name'] : $siteDb->user_name);
$ecommerceUserPassword = ($_POST['ecommerce_user_password'] ? $_POST['ecommerce_user_password'] : $siteDb->user_password);
$ecommerceUserPassword2 = ($_POST['ecommerce_user_password2'] ? $_POST['ecommerce_user_password2'] : $siteDb->user_password);
$ecommerceFilterLabel = ($_POST['ecommerce_filter_label'] ? $_POST['ecommerce_filter_label'] : $siteDb->filter_label);
$ecommerceFilterValue = ($_POST['ecommerce_filter_value'] ? $_POST['ecommerce_filter_value'] : $siteDb->filter_value);
$ecommerceFkCatSociete = ($_POST['ecommerce_fk_cat_societe'] ? $_POST['ecommerce_fk_cat_societe'] : intval($siteDb->fk_cat_societe));
$ecommerceFkCatProduct = ($_POST['ecommerce_fk_cat_product'] ? $_POST['ecommerce_fk_cat_product'] : intval($siteDb->fk_cat_product));
$ecommerceMagentoUseSpecialPrice = ($_POST['ecommerce_magento_use_special_price'] ? $_POST['ecommerce_magento_use_special_price'] : intval($siteDb->magento_use_special_price));
$ecommerceMagentoPriceType = ($_POST['ecommerce_magento_price_type'] ? $_POST['ecommerce_magento_price_type'] : $siteDb->ecommerce_magento_price_type);
$ecommerceTimeout = 300;
if (isset($_POST['ecommerce_timeout']))
    $ecommerceTimeout = $_POST['ecommerce_timeout'];
elseif (isset($siteDb->timeout))
    $ecommerceTimeout = $siteDb->timeout;

$ecommerceLastUpdate = $siteDb->last_update;
$var = true;
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">' . $langs->trans("BackToModuleList") . '</a>';
$title = '';
if ($siteDb->name)
    $title = $langs->trans('ECommerceSetupSite') . ' ' . $siteDb->name;
else
    $title = $langs->trans('ECommerceCreateSite');

//SHOW PAGE
$urltpl=dol_buildpath('/ecommerce/admin/tpl/eCommerceSetup.tpl.php',0);
include($urltpl);

$db->close();
clearstatcache();