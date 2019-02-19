<?php

/* 
 *  @since      1.0.0
 *  @author     LiteSpeed Technologies <info@litespeedtech.com>
 *  @copyright  Copyright (c) 2017-2018 LiteSpeed Technologies, Inc. (https://www.litespeedtech.com)
 *  @license    https://opensource.org/licenses/GPL-3.0
 */

class ControllerExtensionModuleLSCache extends Controller {
    const LOG_ERROR = 3;
    const LOG_INFO = 6;
    const LOG_DEBUG = 8;    
    private $error = array();

    public function index() {

        $data = $this->load->language('extension/module/lscache');
        if(!$this->isCurl()){
            $data["button_recacheAll"] .= $data["text_curl_not_support"];
        }
        
        $currentLink = $this->url->link('extension/module/lscache', 'token=' . $this->session->data['token'], true);
        $this->session->data['previouseURL'] = $currentLink;
        $parentLink = $this->url->link('marketplace/extension', 'token=' . $this->session->data['token'].'&type=module', true);
        $siteUrl = new Url(HTTP_CATALOG, HTTPS_CATALOG);
        $recacheLink = $siteUrl->link('extension/module/lscache/recache', 'token=' . $this->session->data['token'], true);
        $data['success']="";
        
        $action = 'index' ;
        if(isset($this->request->get['action'])){
            $action = $this->request->get['action'];
        }
        $data['action'] = $action;

        if($action=='purgeAllButton'){
            $lscInstance = $this->lscacheInit(true);
        } else {
            $lscInstance = $this->lscacheInit();
        }
        $data["serverType"] = LITESPEED_SERVER_TYPE;
        
        if(isset($this->request->get['tab'])){
            $data["tab"] = $this->request->get['tab'];
        } else {
            $data["tab"] = "general";
        }

        $this->load->model('extension/module/lscache');
        $oldSetting = $this->model_extension_module_lscache->getItems();
        
        if (!$this->validate()){
    		$this->log('Invalid Access', self::LOG_ERROR);
        }
        else if ($this->request->server['REQUEST_METHOD'] == 'POST') {
            $this->session->data['lscacheOption'] = "debug";
            $this->model_extension_module_lscache->editSetting('module_lscache', $this->request->post);
            if(isset($this->session->data['error'])){
                $data['error_warning'] = $this->session->data['error'] ;
                $this->session->data['error']="";
            } else {
                $this->session->data['success'] = $this->language->get('text_success');
                $data['success'] = $this->language->get('text_success');
            }
            
            if(isset($this->session->data["previouseTab"])){
                $data["tab"] = $this->session->data["previouseTab"];
                unset($this->session->data["previouseTab"]);
            }
            
            if(($oldSetting["module_lscache_status"]!= $this->request->post["module_lscache_status"]) || ($oldSetting["module_lscache_esi"]!= $this->request->post["module_lscache_esi"])){
                if($lscInstance){
                    $lscInstance->purgeAllPublic();
                    $data['success'] .=  '<br><i class="fa fa-check-circle"></i> ' .  $this->language->get('text_purgeSuccess');
                }
            }
            
        }
        else if( ($action == 'purgeAll') && $lscInstance){
            $data['success'] = $this->language->get('text_purgeSuccess');
    		$lscInstance->purgeAllPublic();
            $this->log($lscInstance->getLogBuffer());
            $data['success'] = $this->language->get('text_purgeSuccess');
        }
        else if( ($action == 'purgeAllButton') && $lscInstance){
    		$lscInstance->purgeAllPublic();
            $this->log($lscInstance->getLogBuffer());
            if(isset($_SERVER['HTTP_REFERER'])){
                $this->response->redirect($_SERVER['HTTP_REFERER']);
                return;
            }
        }
        else if(($action == 'deletePage') && isset($this->request->get['key'])) {
            $key = $this->request->get['key'];
            $this->model_extension_module_lscache->deleteSettingItem('module_lscache', $key );
            if($lscInstance){
                $lscInstance->purgePublic($key);
                $this->log($lscInstance->getLogBuffer());
                $data['success'] = $this->language->get('text_purgeSuccess');
            }
            $data["tab"] = "pages";
        }
        else if($action == 'addESIModule'){
            $data['moduleOptions'] = $this->model_extension_module_lscache->getESIModuleOptions();
            $data['extensionOptions'] = $this->model_extension_module_lscache->getESIExtensionOptions();
            $data["tab"] = "modules";
            $this->session->data['previouseTab'] = "modules";
        }
        else if(($action == 'deleteESI') && isset($this->request->get['key'])) {
            $key = $this->request->get['key'];
            $this->model_extension_module_lscache->deleteSettingItem('module_lscache', $key );
            if($lscInstance){
                $lscInstance->purgePublic($key);
                $this->log($lscInstance->getLogBuffer());
                $data['success'] = $this->language->get('text_purgeModule');
            }
            $data["tab"] = "modules";
        }
        else if(($action == 'purgeESI') && isset($this->request->get['key']) && $lscInstance) {
            $key = $this->request->get['key'];
    		$lscInstance->purgePublic($key);
            $this->log($lscInstance->getLogBuffer());
            $data['success'] = $this->language->get('text_purgeModule');
            $data["tab"] = "modules";
        }
        else if(($action == 'purgePage') && isset($this->request->get['key']) && $lscInstance) {
            $key = $this->request->get['key'];
    		$lscInstance->purgePublic($key);
            $this->log($lscInstance->getLogBuffer());
            $data['success'] = $this->language->get('text_purgeSuccess');
            $data["tab"] = "pages";
        }
        else if($action == 'deleteESI'){
            $data["tab"] = "modules";
        }
        else if($action == 'deletePage'){
            $data["tab"] = "pages";
        }
        else if($action == 'addPage'){
            $this->session->data["previouseTab"] = "pages";
        }
        else if($action == 'addESIRoute'){
            $this->session->data["previouseTab"] = "modules";
        }

        $data['pages'] = $this->model_extension_module_lscache->getPages();
        $data['modules'] = $this->model_extension_module_lscache->getModules();
        $items = $this->model_extension_module_lscache->getItems();
        $data = array_merge($data, $items);
        
        $data['tabtool'] = new Tool('active', 'general');
        $data['selectEnable'] = new Tool('selected', '1');
        $data['selectDisable'] = new Tool('selected', '0');
        $data['selectDefault'] = new Tool('selected', '');
        $data['checkEnable'] = new Tool('checked', '1');
        $data['checkDisable'] = new Tool('checked', '0');
        
        if(!empty($data['error_warning']) ){
        } else if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }
        
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_module'),
            'href' => $this->url->link('marketplace/extension', 'token=' . $this->session->data['token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/lscache', 'token=' . $this->session->data['token'], true)
        );
        
        $data['cancel'] = $parentLink;
        $data['self'] = $currentLink;
        $data['purgeAll'] = $currentLink . '&action=purgeAll';
        $data['purgePage'] = $currentLink . '&action=purgePage';
        $data['purgeESI'] = $currentLink . '&action=purgeESI';
        $data['recacheAll'] = $this->isCurl()? $recacheLink : '#';
        $data['addPage'] = $currentLink . '&tab=pages&action=addPage';
        $data['deletePage'] = $currentLink . '&tab=pages&action=deletePage';
        $data['addESIModule'] = $currentLink . '&tab=modules&action=addESIModule';
        $data['addESIRoute'] = $currentLink . '&tab=modules&action=addESIRoute';
        $data['deleteESI'] = $currentLink . '&tab=modules&action=deleteESI';
        
        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addScript('view/javascript//bootstrap-toggle.min.js');
        $this->document->addStyle('view/stylesheet//bootstrap-toggle.min.css');
        
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        error_reporting(0);
        $this->response->setOutput($this->load->view('extension/module/lscache', $data));
        
    }

    
    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/lscache')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }
    
    public function purgeAllButton($route, &$args, &$output){
        if ($this->user && $this->user->hasPermission('modify', 'extension/module/lscache')) {
            $lan = new Language();
            $lan->load('extension/module/lscache');
            $button = '<li><a href="' . $this->url->link('extension/module/lscache', 'token=' . $this->session->data['token']) . '&action=purgeAllButton'  . '" data-toggle="tooltip" title="" class="btn" data-original-title="'. $lan->get('button_purgeAll') .'"><i class="fa fa-trash"></i><span class="hidden-xs hidden-sm hidden-md"> Purge All LiteSpeed Cache</span></a></li>';
            $search = '<ul class="nav pull-right">';
            $output = str_replace($search, $search.$button, $output);
        }
    }
    
    
	public function install() {
		$this->load->model('extension/event');
		$this->load->model('extension/module/lscache');
        $this->model_extension_event->addEvent('lscache_init', 'catalog/controller/*/before', 'extension/module/lscache/onAfterInitialize');

        $this->model_extension_event->addEvent('lscache_button_purgeall', 'admin/controller/common/header/after', 'extension/module/lscache/purgeAllButton');
        $this->model_extension_event->addEvent('lscache_product_list', 'catalog/model/catalog/product/getProducts/after', 'extension/module/lscache/getProducts');
        $this->model_extension_event->addEvent('lscache_product_get', 'catalog/model/catalog/product/getProduct/after', 'extension/module/lscache/getProduct');
        $this->model_extension_event->addEvent('lscache_product_add', 'admin/model/catalog/product/addProduct/after', 'extension/module/lscache/addProduct');
        $this->model_extension_event->addEvent('lscache_product_edit', 'admin/model/catalog/product/editProduct/after', 'extension/module/lscache/editProduct');
        $this->model_extension_event->addEvent('lscache_product_delete', 'admin/model/catalog/product/deleteProduct/after', 'extension/module/lscache/editProduct');

        $this->model_extension_event->addEvent('lscache_category_list', 'catalog/model/catalog/category/getCategories/after', 'extension/module/lscache/getCategories');
        $this->model_extension_event->addEvent('lscache_category_get', 'catalog/model/catalog/category/getCategory/after', 'extension/module/lscache/getCategory');
        $this->model_extension_event->addEvent('lscache_category_add', 'admin/model/catalog/category/addCategory/after', 'extension/module/lscache/addCategory');
        $this->model_extension_event->addEvent('lscache_category_edit', 'admin/model/catalog/category/editCategory/after', 'extension/module/lscache/editCategory');
        $this->model_extension_event->addEvent('lscache_category_delete', 'admin/model/catalog/category/deleteCategory/after', 'extension/module/lscache/editCategory');
        
        $this->model_extension_event->addEvent('lscache_manufacturer_list', 'catalog/model/catalog/manufacturer/getManufacturers/after', 'extension/module/lscache/getManufacturers');
        $this->model_extension_event->addEvent('lscache_manufacturer_get', 'catalog/model/catalog/manufacturer/getManufacturer/after', 'extension/module/lscache/getManufacturer');
        $this->model_extension_event->addEvent('lscache_manufacturer_add', 'admin/model/catalog/manufacturer/addManufacturer/after', 'extension/module/lscache/addManufacturer');
        $this->model_extension_event->addEvent('lscache_manufacturer_edit', 'admin/model/catalog/manufacturer/editManufacturer/after', 'extension/module/lscache/editManufacturer');
        $this->model_extension_event->addEvent('lscache_manufacturer_delete', 'admin/model/catalog/manufacturer/deleteManufacturer/after', 'extension/module/lscache/editManufacturer');
        
        $this->model_extension_event->addEvent('lscache_information_list', 'catalog/model/catalog/information/getInformations/after', 'extension/module/lscache/getInformations');
        $this->model_extension_event->addEvent('lscache_information_get', 'catalog/model/catalog/information/getInformation/after', 'extension/module/lscache/getInformation');
        $this->model_extension_event->addEvent('lscache_information_add', 'admin/model/catalog/information/addInformation/after', 'extension/module/lscache/addInformation');
        $this->model_extension_event->addEvent('lscache_information_edit', 'admin/model/catalog/information/editInformation/after', 'extension/module/lscache/editInformation');
        $this->model_extension_event->addEvent('lscache_information_delete', 'admin/model/catalog/information/deleteInformation/after', 'extension/module/lscache/editInformation');

        $this->model_extension_event->addEvent('lscache_checkout_confirm', 'catalog/controller/checkout/confirm/after', 'extension/module/lscache/confirmOrder');
        $this->model_extension_event->addEvent('lscache_checkout_success', 'catalog/controller/checkout/success/after', 'extension/module/lscache/confirmOrder');

        $this->model_extension_event->addEvent('lscache_add_ajax', 'catalog/controller/common/header/after', 'extension/module/lscache/addAjax');
        $this->model_extension_event->addEvent('lscache_cart_add', 'catalog/controller/checkout/cart/add/after', 'extension/module/lscache/editCart');
        $this->model_extension_event->addEvent('lscache_cart_edit', 'catalog/controller/checkout/cart/edit/after', 'extension/module/lscache/editCart');
        $this->model_extension_event->addEvent('lscache_cart_remove', 'catalog/controller/checkout/cart/remove/after', 'extension/module/lscache/editCart');
        $this->model_extension_event->addEvent('lscache_compare_check', 'catalog/controller/product/compare/add/before', 'extension/module/lscache/checkCompare');
        $this->model_extension_event->addEvent('lscache_compare_edit', 'catalog/controller/product/compare/add/after', 'extension/module/lscache/checkCompare');
        $this->model_extension_event->addEvent('lscache_wishlist_check', 'catalog/controller/account/wishlist/add/before', 'extension/module/lscache/checkWishlist');
        $this->model_extension_event->addEvent('lscache_wishlist_edit', 'catalog/controller/account/wishlist/add/after', 'extension/module/lscache/editWishlist');
        $this->model_extension_event->addEvent('lscache_wishlist_display', 'catalog/controller/account/wishlist/after', 'extension/module/lscache/editWishlist');
        
        $this->model_extension_event->addEvent('lscache_user_forgotten', 'catalog/controller/account/forgotten/validate/after', 'extension/module/lscache/onUserAfterLogin');
        $this->model_extension_event->addEvent('lscache_user_login', 'catalog/controller/account/login/validate/after', 'extension/module/lscache/onUserAfterLogin');
        $this->model_extension_event->addEvent('lscache_user_logout', 'catalog/model/account/customer/deleteLoginAttempts/after', 'extension/module/lscache/onUserAfterLogout');
        $this->model_extension_event->addEvent('lscache_currency_change', 'catalog/controller/common/currency/currency/before', 'extension/module/lscache/editCurrency');
        $this->model_extension_event->addEvent('lscache_language_change', 'catalog/controller/common/language/language/before', 'extension/module/lscache/editLanguage');
        
        $this->model_extension_module_lscache->installLSCache();
        $this->initHtaccess();
        
        $lscInstance = $this->lscacheInit();
        if($lscInstance){
            $lscInstance->purgeAllPublic();
            //$this->log($lscInstance->getLogBuffer(), 0);
        }
        
        if (function_exists('opcache_reset')){
            opcache_reset();
        } else if (function_exists('phpopcache_reset')){
            phpopcache_reset();
        }
        
	}
    
    
	public function uninstall() {
		$this->load->model('extension/event');
		$this->load->model('extension/module/lscache');
		$this->model_extension_event->deleteEvent('lscache_init');
		$this->model_extension_event->deleteEvent('lscache_button_purgeall');
		$this->model_extension_event->deleteEvent('lscache_product_list');
		$this->model_extension_event->deleteEvent('lscache_product_add');
		$this->model_extension_event->deleteEvent('lscache_product_get');
		$this->model_extension_event->deleteEvent('lscache_product_edit');
		$this->model_extension_event->deleteEvent('lscache_product_delete');
		$this->model_extension_event->deleteEvent('lscache_category_list');
		$this->model_extension_event->deleteEvent('lscache_category_add');
		$this->model_extension_event->deleteEvent('lscache_category_get');
		$this->model_extension_event->deleteEvent('lscache_category_edit');
		$this->model_extension_event->deleteEvent('lscache_category_delete');
		$this->model_extension_event->deleteEvent('lscache_manufacturer_list');
		$this->model_extension_event->deleteEvent('lscache_manufacturer_add');
		$this->model_extension_event->deleteEvent('lscache_manufacturer_get');
		$this->model_extension_event->deleteEvent('lscache_manufacturer_edit');
		$this->model_extension_event->deleteEvent('lscache_manufacturer_delete');
		$this->model_extension_event->deleteEvent('lscache_information_list');
		$this->model_extension_event->deleteEvent('lscache_information_add');
		$this->model_extension_event->deleteEvent('lscache_information_get');
		$this->model_extension_event->deleteEvent('lscache_information_edit');
		$this->model_extension_event->deleteEvent('lscache_information_delete');

        $this->model_extension_event->deleteEvent('lscache_checkout_confirm');
        $this->model_extension_event->deleteEvent('lscache_checkout_success');
        $this->model_extension_event->deleteEvent('lscache_cart_add');
        $this->model_extension_event->deleteEvent('lscache_cart_edit');
        $this->model_extension_event->deleteEvent('lscache_cart_remove');
		$this->model_extension_event->deleteEvent('lscache_add_ajax');
		$this->model_extension_event->deleteEvent('lscache_wishlist_display');
		$this->model_extension_event->deleteEvent('lscache_wishlist_edit');
		$this->model_extension_event->deleteEvent('lscache_wishlist_check');
		$this->model_extension_event->deleteEvent('lscache_compare_check');
		$this->model_extension_event->deleteEvent('lscache_compare_edit');
		$this->model_extension_event->deleteEvent('lscache_user_forgotten');
		$this->model_extension_event->deleteEvent('lscache_user_login');
		$this->model_extension_event->deleteEvent('lscache_user_logout');
		$this->model_extension_event->deleteEvent('lscache_currency_change');
		$this->model_extension_event->deleteEvent('lscache_language_change');
                
        $this->clearHtaccess();
        $lscInstance = $this->lscacheInit();
        if($lscInstance){
            $lscInstance->purgeAllPublic();
            $this->log($lscInstance->getLogBuffer(), 0);
        }
        $this->model_extension_module_lscache->uninstallLSCache();
	}
    

    public function addProduct($route, &$args, &$output) {
        $lscInstance = $this->lscacheInit();
        if($lscInstance){
            $purgeTag = 'Product, Category';
            $data = $args[0];
            if (isset($data['product_category'])) {
                foreach ($data['product_category'] as $category_id) {
                    $purgeTag .= ',' . 'C_' . $category_id ;
                }
            }
            $lscInstance->purgePublic($purgeTag);
            $this->log($lscInstance->getLogBuffer());
            $lan = new Language();
            $lan->load('extension/module/lscache');
            $text_success = $this->language->get('text_success') .  '<br><i class="fa fa-check-circle"></i> '.  $lan->get('text_purgeSuccess');
            $this->language->set('text_success' ,  $text_success);
        }
    }

    
    public function editProduct($route, &$args, &$output) {
        $lscInstance = $this->lscacheInit();
        if($lscInstance){
            $purgeTag = 'Product, Category,P_' . $args[0];
            $this->load->model('catalog/product');
            $categories = $this->model_catalog_product->getProductCategories($args[0]);
            foreach ($categories as $category){
                $purgeTag .= ',' . 'C_' . $category ;
            }
            
            $lscInstance->purgePublic($purgeTag);
            $this->log($lscInstance->getLogBuffer());
            
            $lan = new Language();
            $lan->load('extension/module/lscache');
            $text_success = $this->language->get('text_success') .  '<br><i class="fa fa-check-circle"></i> '.  $lan->get('text_purgeSuccess');
            $this->language->set('text_success' ,  $text_success);
        }
    }

    
    public function addCategory($route, &$args, &$output) {
        $lscInstance = $this->lscacheInit();
        if($lscInstance){
            $purgeTag = 'Category';
            $lscInstance->purgePublic($purgeTag);
            $this->log($lscInstance->getLogBuffer());
            $lan = new Language();
            $lan->load('extension/module/lscache');
            $text_success = $this->language->get('text_success') .  '<br><i class="fa fa-check-circle"></i> '.  $lan->get('text_purgeSuccess');
            $this->language->set('text_success' ,  $text_success);
        }
    }
    
    
    public function editCategory($route, &$args, &$output) {
        $lscInstance = $this->lscacheInit();
        if($lscInstance){
            $purgeTag = 'Category,C_' . $args[0];
            $lscInstance->purgePublic($purgeTag);
            $this->log($lscInstance->getLogBuffer());
            $lan = new Language();
            $lan->load('extension/module/lscache');
            $text_success = $this->language->get('text_success') .  '<br><i class="fa fa-check-circle"></i> '.  $lan->get('text_purgeSuccess');
            $this->language->set('text_success' ,  $text_success);
        }
    }
    

    public function addInformation($route, &$args, &$output) {
        $lscInstance = $this->lscacheInit();
        if($lscInstance){
            $purgeTag = 'Information';
            $lscInstance->purgePublic($purgeTag);
            $this->log($lscInstance->getLogBuffer());
            $lan = new Language();
            $lan->load('extension/module/lscache');
            $text_success = $this->language->get('text_success') .  '<br><i class="fa fa-check-circle"></i> '.  $lan->get('text_purgeSuccess');
            $this->language->set('text_success' ,  $text_success);
        }
    }
    
    
    public function editInformation($route, &$args, &$output) {
        $lscInstance = $this->lscacheInit();
        if($lscInstance){
            $purgeTag = 'Information,I_' . $args[0];
            $lscInstance->purgePublic($purgeTag);
            $this->log($lscInstance->getLogBuffer());
            $lan = new Language();
            $lan->load('extension/module/lscache');
            $text_success = $this->language->get('text_success') .  '<br><i class="fa fa-check-circle"></i> '.  $lan->get('text_purgeSuccess');
            $this->language->set('text_success' ,  $text_success);
        }
    }

    
    public function addManufacturer($route, &$args, &$output) {
        $lscInstance = $this->lscacheInit();
        if($lscInstance){
            $purgeTag = 'Manufacturer';
            $lscInstance->purgePublic($purgeTag);
            $this->log($lscInstance->getLogBuffer());
            $lan = new Language();
            $lan->load('extension/module/lscache');
            $text_success = $this->language->get('text_success') .  '<br><i class="fa fa-check-circle"></i> '.  $lan->get('text_purgeSuccess');
            $this->language->set('text_success' ,  $text_success);
        }
    }

    
    public function editManufacturer($route, &$args, &$output) {
        $lscInstance = $this->lscacheInit();
        if($lscInstance){
            $purgeTag = 'Manufacturer,M_' . $args[0];
            $lscInstance->purgePublic($purgeTag);
            $this->log($lscInstance->getLogBuffer());
            $lan = new Language();
            $lan->load('extension/module/lscache');
            $text_success = $this->language->get('text_success') .  '<br><i class="fa fa-check-circle"></i> '.  $lan->get('text_purgeSuccess');
            $this->language->set('text_success' ,  $text_success);
        }
    }
    
    
    private function lscacheInit($redirect = false) {

        $this->load->model('extension/module/lscache');
        $setting = $this->model_extension_module_lscache->getItems();

        // Server type
        if (!defined('LITESPEED_SERVER_TYPE')) {
            if (isset($_SERVER['HTTP_X_LSCACHE']) && $_SERVER['HTTP_X_LSCACHE']) {
                define('LITESPEED_SERVER_TYPE', 'LITESPEED_SERVER_ADC');
            } elseif (isset($_SERVER['LSWS_EDITION']) && strpos($_SERVER['LSWS_EDITION'], 'Openlitespeed') === 0) {
                define('LITESPEED_SERVER_TYPE', 'LITESPEED_SERVER_OLS');
            } elseif (isset($_SERVER['SERVER_SOFTWARE']) && $_SERVER['SERVER_SOFTWARE'] == 'LiteSpeed') {
                define('LITESPEED_SERVER_TYPE', 'LITESPEED_SERVER_ENT');
            } else {
                define('LITESPEED_SERVER_TYPE', 'NONE');
            }
        }
        
        if (isset($setting['module_lscache_status']) && (!$setting['module_lscache_status']))  {
            return false;
        }

        // Checks if caching is allowed via server variable
        if (!empty($_SERVER['X-LSCACHE']) || LITESPEED_SERVER_TYPE === 'LITESPEED_SERVER_ADC' || defined('LITESPEED_CLI')) {
            !defined('LITESPEED_ALLOWED') && define('LITESPEED_ALLOWED', true);
            include_once(DIR_SYSTEM . 'library/lscache/lscachebase.php');
            include_once(DIR_SYSTEM . 'library/lscache/lscachecore.php');
            $lscInstance = new LiteSpeedCacheCore();
            if(!$redirect){
                $lscInstance->setHeaderFunction($this->response, 'addHeader');
            }
            return $lscInstance;
        } else {
            return false;
        }

    }
    
    
    private function initHtaccess() {
        $htaccess = realpath(DIR_APPLICATION . '/../') . '/.htaccess';

        $directives = '### LITESPEED_CACHE_START - Do not remove this line' . PHP_EOL;
        $directives .= '<IfModule LiteSpeed>' . PHP_EOL;
        $directives .= 'CacheLookup on' . PHP_EOL;
        $directives .= '</IfModule>' . PHP_EOL;
        $directives .= '### LITESPEED_CACHE_END';

        $pattern = '@### LITESPEED_CACHE_START - Do not remove this line.*?### LITESPEED_CACHE_END@s';

        if (file_exists($htaccess)) {
            $content = file_get_contents($htaccess);
            $newContent = preg_replace($pattern, $directives, $content, -1, $count);

            if ($count <= 0) {
                $newContent = preg_replace('@\<IfModule\ LiteSpeed\>.*?\<\/IfModule\>@s', '', $content);
                $newContent = preg_replace('@CacheLookup\ on@s', '', $newContent);
                file_put_contents($htaccess, $newContent . PHP_EOL . $directives . PHP_EOL);
            } else if ($count > 0) {
                file_put_contents($htaccess, $newContent);
            } else {
                file_put_contents($htaccess, PHP_EOL . $directives . PHP_EOL, FILE_APPEND);
            }
        } else {
            file_put_contents($htaccess, $directives);
        }
    }
    
    
	private function clearHtaccess()
	{
        $htaccess = realpath(DIR_APPLICATION . '/../') . '/.htaccess';

        if (file_exists($htaccess))
        {
            $contents = file_get_contents($htaccess);
            $pattern = '@\n?### LITESPEED_CACHE_START - Do not remove this line.*?### LITESPEED_CACHE_END@s';

            $clean_contents = preg_replace($pattern, '', $contents, -1, $count);

            if($count > 0)
            {
                file_put_contents($htaccess, $clean_contents);
            }
        }
	}
    
    public function log($content = null, $logLevel = self::LOG_INFO) {
        if(empty($content)){
            return;
        }
        
        $this->load->model('extension/module/lscache');
        $setting = $this->model_extension_module_lscache->getItems();

        if (!isset($setting['module_lscache_log_level'])) {
            return;
        }

        $logLevelSetting = $setting['module_lscache_log_level'];
        if(isset($this->session->data['lscacheOption']) && ($this->session->data['lscacheOption']=="debug")){
            $this->log->write($content);
            return;
        } else if($logLevelSetting ==self::LOG_DEBUG) {
            return;
        } else if ($logLevel > $logLevelSetting) {
            return;
        }
        
        $logInfo = "LiteSpeed Cache Info:";
        if($logLevel == self::LOG_ERROR){
            $logInfo = "LiteSpeed Cache Error:";
        } else if($logLevel==self::LOG_DEBUG){
            $logInfo = "LiteSpeed Cache Debug:";
        }

		$this->log->write($logInfo . $content);
        
    }
    
    protected function isCurl(){
        return function_exists('curl_version');
    }
    
    
}

final class Tool {
    
    private $result;
    private $default;
    
    public function __construct($result="", $default="") {
        $this->result = $result;
        $this->default = $default;
    }
    
    public function check($value, $compare = "1", $attribute="") {
        
        if($value==""){
            $value = $this->default;
        }

        if($compare != $value){
            return "";
        } else if (empty($attribute)){
            return $this->result;
        } else {
            return $attribute . '="' . $this->result . '"';
        }
        
	}
    
}

