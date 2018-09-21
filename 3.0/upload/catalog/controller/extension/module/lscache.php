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

    public function onAfterInitialize($route, &$args) {

        if($this->lscache!=null){
            return;
        } else if($route=="extension/module/lscache/renderESI"){
            //ESI render
        } else if(strpos($route, "extension") === 0) {
            return;
        }
        
        $this->lscache =  (object) array('route' => $route, 'setting' => null, 'cacheEnabled' => false, 'pageCachable' => false, 'esiEnabled' => false, 'esiOn' => false,  'cacheTags'=> array(), 'lscInstance'=> null );
        $this->event->unregister('controller/*/before', 'extension/module/lscache/onAfterInitialize');

        $this->load->model('extension/module/lscache');
        $this->lscache->setting = $this->model_extension_module_lscache->getItems();
        
        if (isset($this->lscache->setting['module_lscache_status']) && (!$this->lscache->setting['module_lscache_status']))  {
            return;
        }

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

        // Checks if caching is allowed via server variable
        if (!empty($_SERVER['X-LSCACHE']) || LITESPEED_SERVER_TYPE === 'LITESPEED_SERVER_ADC' || defined('LITESPEED_CLI')) {
            !defined('LITESPEED_ALLOWED') && define('LITESPEED_ALLOWED', true);
            $this->lscache->cacheEnabled = true;
        } else {
            return;
        }

        if(( LITESPEED_SERVER_TYPE !== 'LITESPEED_SERVER_OLS' ) && isset($this->lscache->setting['module_lscache_esi']) && $this->lscache->setting['module_lscache_esi'] ) {
            $this->lscache->esiEnabled = true;
        }
        
        $this->event->register('controller/'. $route . '/after', new Action('extension/module/lscache/onAfterRender'));
        include_once(DIR_SYSTEM . 'library/lscache/lscachebase.php');
        include_once(DIR_SYSTEM . 'library/lscache/lscachecore.php');
        $this->lscache->lscInstance = new LiteSpeedCacheCore();
        $this->lscache->lscInstance->setHeaderFunction($this->response, 'addHeader');
        
        if($route!="extension/module/lscache/renderESI"){
            $this->onAfterRoute($route, $args);
        }
        
    }

    
    public function onAfterRoute($route, &$args) {
        
        $pageKey = 'page_' . str_replace('/', '_', $route);
        $pages = $this->model_extension_module_lscache->getPages();
        if(isset($pages[$pageKey])){
            $pageSetting = $pages[$pageKey];
        } else {
            return;
        }
        
        if($this->customer->isLogged()){
            if($pageSetting['cacheLogin']){
                $this->lscache->pageCachable = true;
            }
            else{
                return;
            }
        } else if ($pageSetting['cacheLogout']) {
            $this->lscache->pageCachable = true;
        } else {
            return;
        }
        
        $this->lscache->cacheTags[] = $pageKey;
        
        if($this->lscache->esiEnabled){
            $esiModules = $this->model_extension_module_lscache->getESIModules();
            $route = "";
            foreach ($esiModules as $key => $module){
                if($module['route']!=$route){
                    $route = $module['route'];
                    $this->event->register('controller/'. $route . '/before', new Action('extension/module/lscache/onAfterRenderModule'));
                }
            }
            $this->event->register('model/setting/module/getModule', new Action('extension/module/lscache/onAfterGetModule'));
        }
        
    }

    
    public function onAfterRenderModule($route, &$args){
        if(($this->lscache==null) || (!$this->lscache->pageCachable)){
            return;
        }

        $esiModules = $this->model_extension_module_lscache->getESIModules();
        $esiKey = 'esi_' .  str_replace('/', '_', $route);
        if(count($args)>0){
            $esiKey .= '_' . $args['module_id'];
        }
        if(!isset($esiModules[$esiKey])){
            return;
        }
        
        $module = $esiModules[$esiKey];
        $esiType = $module['esi_type'];
        
        $link = $this->url->link('extension/module/lscache/renderESI', '');
        $link .= '&esiRoute=' . $route;
        if(isset($module['module']) && ($module['name']!=$module['module'])){
            $link .= '&module_id=' . $module['module'];
        }
        
        if ($esiType == 3) {
            $output = '<esi:include src="' . $link . '" cache-control="public"/>';
            $this->lscache->esiOn = true;
        } else if ($esiType == 2) {
            $output = '<esi:include src="' . $link . '" cache-control="private"/>';
            $this->lscache->esiOn = true;
        } else if ($esiType == 1) {
            $output = '<esi:include src="' . $link . '" cache-control="no-cache"/>';
            $this->lscache->esiOn = true;
        }
        return $output;
        
    }

    public function onAfterRender($route, &$args, &$output){

        if(($this->lscache==null) || (!$this->lscache->pageCachable)){
            return;
        }
        
        if (function_exists('http_response_code')) {
            $httpcode = http_response_code();
            if ($httpcode > 201) {
                $this->log("Http Response Code Not Cachable:" . $httpcode);
                return;
            }
        }
        
        $this->checkVary();
        
        if((!$this->lscache->esiEnabled) && (!$this->emptySession())){
            return;
        }
        
        if (!isset($this->lscache->setting['module_lscache_public_ttl'])) {
            $cacheTimeout = 120000;
        }
        else{
            $cacheTimeout = $this->lscache->setting['module_lscache_public_ttl'];
            $cacheTimeout = empty($cacheTimeout)? 120000 : $cacheTimeout;
        }
        $this->lscache->lscInstance->setPublicTTL($cacheTimeout);
        $this->lscache->lscInstance->cachePublic($this->lscache->cacheTags, $this->lscache->esiOn);
        $this->log();
        
    }

    
    
    public function renderESI(){
        if(($this->lscache==null) || (!$this->lscache->cacheEnabled)){
            http_response_code(403);
            return;
        }

        if(isset($this->request->get['action']) && ($this->request->get['action'] == "editCurrency")){
            if($this->lscache->esiEnabled){
                $purgeTag = 'esi_currency' ;
                $this->lscache->lscInstance->purgePrivate($purgeTag);
                $this->log();
            }
            $this->checkVary();
            
            if(isset($this->session->data['redirect']) ){
                $content=  '<script type="text/javascript">
                               window.location = "' . $_SERVER['HTTP_REFERER'] . '"
                            </script>';
            } else if(isset($_SERVER['HTTP_REFERER'])){
                
                $content=  '<script type="text/javascript">
                               window.location = "' . $this->session->data['redirect'] . '"
                            </script>';
            } else {
                $content=  '<script type="text/javascript">
                               window.location = "' . $this->url->link('common/home') . '"
                            </script>';
            }
            
            $this->response->setOutput($content);
            return;
            
        }
        
        if(!isset($this->request->get['esiRoute'])){
            http_response_code(403);
            return;
        }

        $esiRoute = $this->request->get['esiRoute'];
        $esiKey =  'esi_' .  str_replace('/', '_', $esiRoute);
        $module_id = "";
        if(isset($this->request->get['module_id'])){
            $module_id = $this->request->get['module_id'];
            $esiKey .= '_' . $module_id;
        }
        $this->lscache->cacheTags[] = $esiKey;
        
        $this->load->model('extension/module/lscache');
        $esiModules = $this->model_extension_module_lscache->getESIModules();
        if(!isset($esiModules[$esiKey])){
            http_response_code(403);
            return;
        }
        
        $content = "";
        if (empty($module_id)) {
            $content = $this->load->controller($esiRoute);
        }
        else {
            $setting_info = $this->model_setting_module->getModule($module_id);

            if ($setting_info && $setting_info['status']) {
                $content = $this->load->controller($esiRoute, $setting_info);
            }
            else {
                http_response_code(403);
                return;
            }
        }
        $this->response->setOutput($content);
        
        $module = $esiModules[$esiKey];
        if($module['esi_type']>'1'){
            $cacheTimeout = $module['esi_ttl'];
            $this->lscache->cacheTags[] = $module['esi_tag'];
            $this->lscache->lscInstance->setPublicTTL($cacheTimeout);
            if($module['esi_type']=='2'){
              $this->lscache->lscInstance->checkPrivateCookie();
              $this->lscache->lscInstance->setPrivateTTL($cacheTimeout);
              $this->lscache->lscInstance->cachePrivate($this->lscache->cacheTags, $this->lscache->cacheTags);
            } else {
              $this->lscache->lscInstance->cachePublic($this->lscache->cacheTags);
            }
            $this->log();
        }

    }
    
    
    public function onAfterGetModule($route, &$args, &$output) {
        $output['module_id'] = $args[0];
    }


    public function onUserAfterLogin($route, &$args, &$output) {
        if (($this->lscache==null) || (!$this->lscache->cacheEnabled)) {
            return;
        }
        $this->lscache->lscInstance->checkPrivateCookie();
        $this->checkVary();
        if ($this->lscache->esiEnabled) {
            $this->lscache->lscInstance->purgeAllPrivate();
            $this->log();
        }
    }

    
    public function onUserAfterLogout($route, &$args, &$output) {
        if (($this->lscache==null) || (!$this->lscache->cacheEnabled)) {
            return;
        }

        $this->checkVary();
        if ($this->lscache->esiEnabled) {
            $this->lscache->lscInstance->purgeAllPrivate();
            $this->log();
        }
    }
    protected function checkVary() {
        
        if((!$this->lscache->esiEnabled) && (!$this->emptySession())){
                $this->lscache->lscInstance->checkVary("no-cache");
                //$this->log('vary:no-cache', 0);
                return;
        }

        $vary = array();
        
        if ($this->customer->isLogged() && isset($this->lscache->setting['module_lscache_vary_login']) && ($this->lscache->setting['module_lscache_vary_login']=='1'))  {
            $vary[] = 'userLoggedIn';
        }
        
        if($this->session->data['currency']!=$this->config->get('config_currency')){
            $vary[] = $this->session->data['currency'];
        }
        
        $varyKey = implode(',', $vary);
        //$this->log('vary:' . $varyKey, 0);
        $this->lscache->lscInstance->checkVary($varyKey);
    }


    public function getProducts($route, &$args, &$output) {
        if(($this->lscache==null) || (!$this->lscache->cacheEnabled)){
            return;
        }

        $this->lscache->cacheTags[] = 'Product';
    }

    
    public function getCategories($route, &$args, &$output) {
        if(($this->lscache==null) || (!$this->lscache->cacheEnabled)){
            return;
        }

        $this->lscache->cacheTags[] = 'Category';
    }

    
    public function getInformations($route, &$args, &$output) {
        if(($this->lscache==null) || (!$this->lscache->cacheEnabled)){
            return;
        }

        $this->lscache->cacheTags[] = 'Information';
    }

    
    public function getManufacturers($route, &$args, &$output) {
        if(($this->lscache==null) || (!$this->lscache->cacheEnabled)){
            return;
        }

        $this->lscache->cacheTags[] = 'Manufacturer';
    }
    
    
    public function getProduct($route, &$args, &$output) {
        if(($this->lscache==null) || (!$this->lscache->cacheEnabled)){
            return;
        }

        $this->lscache->cacheTags[] = 'P_' . $args[0];
    }

    
    public function getCategory($route, &$args, &$output) {
        if(($this->lscache==null) || (!$this->lscache->cacheEnabled)){
            return;
        }

        $this->lscache->cacheTags[] = 'C_' . $args[0];
    }

    
    public function getInformation($route, &$args, &$output) {
        if(($this->lscache==null) || (!$this->lscache->cacheEnabled)){
            return;
        }

        $this->lscache->cacheTags[] = 'I_' . $args[0];
    }

    
    public function getManufacturer($route, &$args, &$output) {
        if(($this->lscache==null) || (!$this->lscache->cacheEnabled)){
            return;
        }

        $this->lscache->cacheTags[] = 'M_' . $args[0];
    }
    
    
    public function editCart($route, &$args, &$output) {
        if(($this->lscache==null) || (!$this->lscache->cacheEnabled)){
            return;
        }

        if($this->lscache->esiEnabled){
            $purgeTag = 'esi_cart' ;
            $this->lscache->lscInstance->purgePrivate($purgeTag);
            $this->log();
        } else {
            $this->checkVary();
        }
    }

    public function confirmOrder($route, &$args, &$output) {
        if(($this->lscache==null) || (!$this->lscache->cacheEnabled)){
            return;
        }
        
        $purgeTag = 'Product,Category';
		foreach ($this->cart->getProducts() as $product) {
            $purgeTag .= ',' . $product['product_id'];
        }

        if($this->lscache->esiEnabled){
            $purgeTag = ',esi_cart' ;
            $this->lscache->lscInstance->purgePrivate($purgeTag);
            $this->log();
        } else {
            $this->lscache->lscInstance->purgePrivate($purgeTag);
            $this->log();
            $this->checkVary();
        }
    }

    public function getWishlist($route, &$args, &$output) {
        if(($this->lscache==null) || (!$this->lscache->cacheEnabled)){
            return;
        }
        
        if($this->lscache->lscInstance){
            $output .='<script type="text/javascript">$(document).ready(function() { wishlist.add(-1);})</script>';
        }
        
    }

    public function checkWishlist($route, &$args) {
        if(($this->lscache==null) || (!$this->lscache->cacheEnabled)){
            return;
        }
        
        if($this->lscache->esiEnabled && isset($this->request->post['product_id']) && ($this->request->post['product_id']==-1)){
			if ($this->customer->isLogged()) {
				$this->load->model('account/wishlist');
                $total = $this->model_account_wishlist->getTotalWishlist();
            } else {
                $total = isset($this->session->data['wishlist']) ? count($this->session->data['wishlist']) : 0;
            }
    		$this->load->language('account/wishlist');
            $json = array();
			$json['total'] = sprintf($this->language->get('text_wishlist'), $total);
            
    		$this->response->setOutput(json_encode($json));
            
            return json_encode($json);
        }
        
    }


    
    public function editWishlist($route, &$args, &$output) {
        if(($this->lscache==null) || (!$this->lscache->cacheEnabled)){
            return;
        }
        
        if($this->lscache->esiEnabled){
            $purgeTag = 'esi_wishlist' ;
            $this->lscache->lscInstance->purgePrivate($purgeTag);
            $this->log();
        } else {
            $this->checkVary();
        }
        
    }


    public function editCurrency($route, &$args) {
        if (($this->lscache==null) || (!$this->lscache->cacheEnabled)) {
            return;
        }
        
        $this->session->data['redirect'] = $this->request->post['redirect']  ; 
        $this->request->post['redirect']  = $this->url->link('extension/module/lscache/renderESI', 'action=editCurrency');
        
    }

    public function log($content = null, $logLevel = self::LOG_INFO) {
        if($this->lscache==null){
            if(($logLevel<=self::LOG_ERROR) && (!empty($content))){
        		$this->log->write($content);
            }
            return;
        }

        if ($content == null) {
            if (!$this->lscache->lscInstance) {
                return;
            }
            $content = $this->lscache->lscInstance->getLogBuffer();
        }
            
        if (!isset($this->lscache->setting['module_lscache_log_level'])) {
            return;
        }

        $logLevelSetting = $this->lscache->setting['module_lscache_log_level'];
        if ($logLevel > $logLevelSetting) {
            return;
        }
        
        $logInfo = "LiteSpeed Cache Info:\n";
        if($logLevel == self::LOG_ERROR){
            $logInfo = "LiteSpeed Cache Error:\n";
        } else if($logLevel==self::LOG_DEBUG){
            $logInfo = "LiteSpeed Cache Debug:\n";
        }

		$this->log->write($logInfo . $content);
        
    }

    
    public function recache(){

        if(!isset($this->session->data['previouseURL'])){
            http_response_code(403);
            return;
        }
        $previouseURL = $this->session->data['previouseURL']; 
        unset($this->session->data['previouseURL']);

        $urls = array();

        $this->load->model('extension/module/lscache');
        $pages = $this->model_extension_module_lscache->getPages();

        foreach($pages as $page){
            if($page['cacheLogout']){
                $urls[] = $this->url->link($page['route'],'');
            }
        }
        
		$this->load->model('catalog/category');
		$this->load->model('catalog/product');

		$categories_1 = $this->model_catalog_category->getCategories(0);
        $categoryPath = array();

		foreach ($categories_1 as $category_1) {
            $categoryPath[$category_1['category_id']] = $category_1['category_id'];

			$categories_2 = $this->model_catalog_category->getCategories($category_1['category_id']);

			foreach ($categories_2 as $category_2) {
                $categoryPath[$category_2['category_id']] = $category_1['category_id'] . '_' . $category_2['category_id'];

				$categories_3 = $this->model_catalog_category->getCategories($category_2['category_id']);

				foreach ($categories_3 as $category_3) {
                    $categoryPath[$category_3['category_id']] = $category_1['category_id'] . '_' . $category_2['category_id'] . '_' .  $category_3['category_id'];

					$urls[] =  $this->url->link('product/category', 'path=' . $category_1['category_id'] . '_' . $category_2['category_id'] . '_' . $category_3['category_id']);
				}

				$urls[] =  $this->url->link('product/category', 'path=' . $category_1['category_id'] . '_' . $category_2['category_id']) ;
			}

			$urls[] =  $this->url->link('product/category', 'path=' . $category_1['category_id']);
		}
        
		$this->load->model('catalog/information');
		foreach ($this->model_catalog_information->getInformations() as $result) {
			$urls[] = $this->url->link('information/information', 'information_id=' . $result['information_id']);
		}
        
		foreach ($this->model_catalog_product->getProducts() as $result) {
            foreach ($this->model_catalog_product->getCategories($result['product_id']) as $category) {
                $urls[] = $this->url->link('product/product', 'path=' . $categoryPath[$category['category_id']] . '&product_id=' . $result['product_id']);
            }

            $urls[] = $this->url->link('product/product', 'product_id=' . $result['product_id']);
		}
        
        $urls[] = $this->url->link('common/home');
        $urls[] = $this->url->link('information/contact');
        $urls[] = $this->url->link('information/sitemap');
        $urls[] = $this->url->link('product/manufacturer');
        $urls[] = HTTP_SERVER;
        $urls[] = HTTP_SERVER . 'index.php';
        
        $this->crawlUrls($urls);

        $data['success'] = $this->language->get('text_success');

        echo '<script type="text/javascript">
                   window.location = "' . str_replace('&amp;', '&', $previouseURL) . '"
              </script>';
        
    }
    
    
    private function crawlUrls($urls) {
        set_time_limit(0);

        $count = count($urls);
        if ($count < 1) {
            return "";
        }

        $cached = 0;
        $acceptCode = array(200, 201);
        $begin = microtime();
        $success = 0;
        $current = 0;

        ob_implicit_flush(TRUE);
        echo '<h3>Recache may take several minutes</h3><br/>';

        foreach ($urls as $url) {
            $start = microtime();
            $ch = curl_init();
            $url = str_replace('&amp;', '&', $url);
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HEADER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 1);
            curl_setopt($ch, CURLOPT_USERAGENT, 'lscache_runner');
//            $this->log('crawl:'.$url, 0);
            $buffer = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (in_array($httpcode, $acceptCode)) {
                $success++;
            }
            $current++;

            echo '*';
            if ($current % 10 == 0) {
                echo floor($current * 100 / $count) . '%<br/>';
            }
            
            $end = microtime();
            $diff = $this->microtimeMinus($start, $end);
            usleep(round($diff));
            
        }

        $totalTime = round($this->microtimeMinus($begin, microtime()) / 1000000);

        return $totalTime;  //script redirect to previous page
        
    }
    
    
    private function microtimeMinus($start, $end) {
        list($s_usec, $s_sec) = explode(" ", $start);
        list($e_usec, $e_sec) = explode(" ", $end);
        $diff = ((int) $e_sec - (int) $s_sec) * 1000000 + ((float) $e_usec - (float) $s_usec) * 1000000;
        return $diff;
    }
    
    protected function emptySession(){
        $amount = isset($this->session->data['wishlist']) ? count($this->session->data['wishlist']) : 0  + $this->cart->countProducts() + (isset($this->session->data['vouchers']) ? count($this->session->data['vouchers']) : 0) ;
        return $amount >0 ? false : true;
    }   
    
    
}