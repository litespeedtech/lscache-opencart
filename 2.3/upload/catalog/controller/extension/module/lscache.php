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

        //$this->log->write('init:' . $route . PHP_EOL);

        if($this->lscache==null){
            //pass
        } else if($route=="extension/module/lscache/renderESI"){
            return; //ESI render
        } else if($this->lscache->pageCachable) {
            return;
        } else if($this->lscache->cacheEnabled) {
            $this->onAfterRoute($route, $args);
            return;
        } else{
            return;
        }
        
        
        $this->lscache =  (object) array('route' => $route, 'setting' => null, 'cacheEnabled' => false, 'pageCachable' => false, 'esiEnabled' => false, 'esiOn' => false,  'cacheTags'=> array(), 'lscInstance'=> null, 'pages'=>null );

        $this->load->model('extension/module/lscache');
        $this->lscache->setting = $this->model_extension_module_lscache->getItems();
        $this->lscache->pages =   $this->model_extension_module_lscache->getPages();
        
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
        //$this->log('server type:' . LITESPEED_SERVER_TYPE);

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
        if(isset($this->lscache->pages[$pageKey])){
            $pageSetting = $this->lscache->pages[$pageKey];
        } else {
            return;
        }
        //$this->log('route:' . $route);

        $this->event->unregister('controller/*/before', 'extension/module/lscache/onAfterInitialize');
        $this->event->register('controller/'. $route . '/after', new Action('extension/module/lscache/onAfterRender'));
        
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

        //$this->log('page cachable:' . $this->lscache->pageCachable);
        
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
            $this->event->register('model/extension/module/getModule', new Action('extension/module/lscache/onAfterGetModule'));
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

        if(isset($this->request->get['action'])) {
            if(($this->lscache->esiEnabled) && (substr($this->request->get['action'],0,4)=='esi_') ){
                $purgeTag = $this->request->get['action'];
                $this->lscache->lscInstance->purgePrivate($purgeTag);
                $this->log();
            }
            
            $this->checkVary();
            
            if(isset($this->session->data['redirect']) ){
                $content=  '<script>
                               window.location = "' . $this->session->data['redirect'] . '";
                            </script>';
                
            } else if(isset($_SERVER['HTTP_REFERER'])){
                $content=  '<script>
                               window.location = "' . $_SERVER['HTTP_REFERER'] . '";
                            </script>';
                
            } else {
                $content=  '<script>
                               window.location = "' . $this->url->link('common/home') . '";
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
            $setting_info = $this->model_extension_module_lscache->getModule($module_id);

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
        $this->event->unregister('controller/*/before', 'extension/module/lscache/onAfterInitialize');

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
        
        $vary = array();
        
        if ($this->customer->isLogged() && isset($this->lscache->setting['module_lscache_vary_login']) && ($this->lscache->setting['module_lscache_vary_login']=='1'))  {
            $vary[] = 'userLoggedIn';
        }
        
        if($this->session->data['currency']!=$this->config->get('config_currency')){
            $vary[] = $this->session->data['currency'];
        }
        
        if($this->session->data['language']!=$this->config->get('config_language')){
            $vary[] = $this->session->data['language'];
        }
        
        $varyKey = implode(',', $vary);
        //$this->log('vary:' . $varyKey, 0);
        $this->lscache->lscInstance->checkVary($varyKey, $this->request->server['HTTP_HOST']);
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
            $purgeTag .= ',P_' . $product['product_id'];
        }

        if($this->lscache->esiEnabled){
            $purgeTag .= ',esi_cart' ;
            $this->lscache->lscInstance->purgePrivate($purgeTag);
            $this->log();
        } else {
            $this->lscache->lscInstance->purgePrivate($purgeTag);
            $this->log();
            $this->checkVary();
        }
    }

    public function addAjax($route, &$args, &$output) {
        if(($this->lscache==null) || (!$this->lscache->pageCachable)){
            return;
        }
        
        $ajax = 'wishlist.add("-1");';
        if (isset($this->lscache->setting['module_lscache_ajax_wishlist']) && ($this->lscache->setting['module_lscache_ajax_wishlist']=='0')) {
            $ajax = '';
        }

        if (isset($this->lscache->setting['module_lscache_ajax_compare']) && ($this->lscache->setting['module_lscache_ajax_compare']=='1')) {
            $ajax .= 'compare.add("-1");';
        }

        if(!$this->lscache->esiEnabled){
            $output .='<script type="text/javascript">$(document).ready(function() {try{ ' . $ajax . ' cart.remove("-1");} catch(err){console.log(err.message);}});</script>';
        } else if(!empty($ajax)) {
            $output .='<script type="text/javascript">$(document).ready(function() { try {  ' . $ajax . ' } catch(err){console.log(err.message);}});</script>';
        }
        
    }
    
    public function checkWishlist($route, &$args) {
        if(($this->lscache==null) || (!$this->lscache->cacheEnabled)){
            return;
        }

        $this->response->addHeader('Access-Control-Allow-Origin: *');
        $this->response->addHeader("Access-Control-Allow-Credentials: true");
        $this->response->addHeader("Access-Control-Allow-Methods: GET,HEAD,OPTIONS,POST,PUT");
        $this->response->addHeader("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");

        if(isset($this->request->post['product_id']) && ($this->request->post['product_id']=="-1")){
			if ($this->customer->isLogged()) {
				$this->load->model('account/wishlist');
                $total = $this->model_account_wishlist->getTotalWishlist();
            } else {
                $total = isset($this->session->data['wishlist']) ? count($this->session->data['wishlist']) : 0;
            }
    		$this->load->language('account/wishlist');
            $text_wishlist = $this->language->get('text_wishlist');
            if(empty($text_wishlist)){
                $text_wishlist = 'Wish List (%s)';
            }
            $json = array();
			$json['total'] = sprintf($text_wishlist, $total);

    		$this->response->setOutput(json_encode($json));
            return json_encode($json);
        }
        
    }

    public function checkCompare($route, &$args) {
        if(($this->lscache==null) || (!$this->lscache->cacheEnabled)){
            return;
        }

        $this->response->addHeader('Access-Control-Allow-Origin: *');
        $this->response->addHeader("Access-Control-Allow-Credentials: true");
        $this->response->addHeader("Access-Control-Allow-Methods: GET,HEAD,OPTIONS,POST,PUT");
        $this->response->addHeader("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");

        if(isset($this->request->post['product_id']) && ($this->request->post['product_id']=="-1")){
            $total = isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0;
    		$this->load->language('product/compare');
            $text_compare = $this->language->get('text_compare');
            $json = array();
			$json['total'] = sprintf($text_compare, $total);
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
        
        $this->session->data['currency'] = $this->request->post['code'];
        $this->checkVary();
    }
    

    public function editLanguage($route, &$args) {
        if (($this->lscache==null) || (!$this->lscache->cacheEnabled)) {
            return;
        }
        
        $this->session->data['language'] = $this->request->post['code'];
        $this->checkVary();       
    }
    
    

    public function log($content = null, $logLevel = self::LOG_INFO) {
        if($this->lscache==null){
            $this->load->model('extension/module/lscache');
            $this->lscache =  (object) array('setting'=> $this->model_extension_module_lscache->getItems() );
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
        
        if(isset($this->session->data['lscacheOption']) && ($this->session->data['lscacheOption']=="debug")){
            $this->log->write($content);
            return;
        } else if($logLevelSetting ==self::LOG_DEBUG) {
            return;
        } else if ($logLevel > $logLevelSetting) {
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
                if(isset( $categoryPath[$category['category_id']] )){
                    $urls[] = $this->url->link('product/product', 'path=' . $categoryPath[$category['category_id']] . '&product_id=' . $result['product_id']);
                }
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
        if (ob_get_contents()){
            ob_end_clean();
        }
        $this->log('Start Recache:');
        echo '<h3>Recache may take several minutes</h3><br/>';
        flush();
        
        $printURL = isset($this->lscache->setting['module_lscache_log_level']) && ($this->lscache->setting['module_lscache_log_level']==self::LOG_DEBUG) ;
        
        foreach ($urls as $url) {
            $this->log('crawl:'.$url);
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
            $buffer = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (in_array($httpcode, $acceptCode)) {
                $success++;
            } else if($httpcode==428){
                echo 'Web Server crawler feature not enabled, please check <a href="https://www.litespeedtech.com/support/wiki/doku.php/litespeed_wiki:cache:lscwp:configuration:enabling_the_crawler" target="_blank">web server settings</a>';
                $this->log('httpcode:'.$httpcode);
                sleep(5);
                break;
            } else {
                $this->log('httpcode:'.$httpcode);
            }
            
            $current++;

            if($printURL){
                echo 'url: ' . $url . ' httpcode: ' . $httpcode . '<br/>';
            } else {
                echo '*';
            }
            if ($current % 10 == 0) {
                echo floor($current * 100 / $count) . '%<br/>';
            }
            flush();
            
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