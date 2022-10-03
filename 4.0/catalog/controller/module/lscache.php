<?php

/*
 *  @since      1.0.0
 *  @author     LiteSpeed Technologies <info@litespeedtech.com>
 *  @copyright  Copyright (c) 2017-2018 LiteSpeed Technologies, Inc. (https://www.litespeedtech.com)
 *  @license    https://opensource.org/licenses/GPL-3.0
 */

namespace Opencart\Catalog\Controller\Extension\Litespeed\Module;
use Opencart\System\Engine\Action;

class Lscache extends \Opencart\System\Engine\Controller {

    const LOG_ERROR = 3;
    const LOG_INFO = 6;
    const LOG_DEBUG = 8;

    public function __construct(\Opencart\System\Engine\Registry $registry) {
        $this->registry = $registry;
        if(!$registry->has('lscache')){
            $lscache = (object) array('route' => null, 'setting' => null, 'cacheEnabled' => false, 'pageCachable' => false, 'urlRule' => false, 'esiEnabled' => false, 'esiOn' => false, 'cacheTags' => array(), 'lscInstance' => null, 'pages' => null, 'includeUrls' => null);
            $registry->set('lscache',$lscache);
        }
    }
    
    public function onAfterInitialize($route, &$args) {

        //$this->log('init:' . $route . PHP_EOL, self::LOG_DEBUG);
        
        if ($route == "extension/litespeed/module/lscache|renderESI") {
            $this->lscacheInit();
            return; //ESI render
        } else if ($this->lscache->pageCachable) {
            return;
        } else if ($this->lscache->cacheEnabled) {
            $this->onAfterRoute($route, $args);
            return;
        }

        $this->lscache->route = $route;
        
        $this->lscacheInit();
        
        if(!$this->lscache->cacheEnabled){
            return;
        }
        
        if ((isset($_SERVER['HTTP_USER_AGENT'])) && (($_SERVER['HTTP_USER_AGENT'] == 'lscache_runner') || ($_SERVER['HTTP_USER_AGENT'] == 'lscache_walker'))) {
            $recache = 0;
            if (isset($this->lscache->setting['recache_options'])) {
                $recache = $this->lscache->setting['recache_options'];
            }

            if (isset($_COOKIE['language']) && (($recache == 1) || ($recache == 3))) {
                $this->session->data['language'] = $_COOKIE['language'];
            }

            if (isset($_COOKIE['currency']) && (($recache == 2) || ($recache == 3))) {
                $this->session->data['currency'] = $_COOKIE['currency'];
            }
        }

        $includeUrls = isset($this->lscache->setting['module_lscache_include_urls']) ? explode(PHP_EOL, $this->lscache->setting['module_lscache_include_urls']) : null;
        $this->lscache->includeUrls = $includeUrls;
        $excludeLoginUrls = isset($this->lscache->setting['module_lscache_exclude_login_urls']) ? explode(PHP_EOL, $this->lscache->setting['module_lscache_exclude_login_urls']) : null;
        $excludeUrls = isset($this->lscache->setting['module_lscache_exclude_urls']) ? explode(PHP_EOL, $this->lscache->setting['module_lscache_exclude_urls']) : null;
        $uri = trim($_SERVER['REQUEST_URI']);

        if ($includeUrls && in_array($uri, $includeUrls)) {
            $this->lscache->pageCachable = true;
            $this->lscache->urlRule = true;
        }

        if ($this->customer->isLogged() && $excludeLoginUrls && in_array($uri, $excludeLoginUrls)) {
            $this->lscache->pageCachable = false;
            $this->lscache->urlRule = true;
        }

        if ($excludeUrls && in_array($uri, $excludeUrls)) {
            $this->lscache->pageCachable = false;
            $this->lscache->urlRule = true;
        }

        if ($route != "extension/litespeed/module/lscache/renderESI") {
            $this->onAfterRoute($route, $args);
        }
    }
    
    protected function lscacheInit() {
        $this->load->model('extension/litespeed/module/lscache');
        $this->lscache->setting = $this->model_extension_litespeed_module_lscache->getItems();
        $this->lscache->pages = $this->model_extension_litespeed_module_lscache->getPages();

        if (isset($this->lscache->setting['module_lscache_status']) && (!$this->lscache->setting['module_lscache_status'])) {
            return;
        }

        // Server type
        if (!defined('LITESPEED_SERVER_TYPE')) {
            if (isset($_SERVER['HTTP_X_LSCACHE']) && $_SERVER['HTTP_X_LSCACHE']) {
                define('LITESPEED_SERVER_TYPE', 'LITESPEED_SERVER_ADC');
            } elseif (isset($_SERVER['LSWS_EDITION']) && ( strpos($_SERVER['LSWS_EDITION'], 'Openlitespeed') !== FALSE )) {
                define('LITESPEED_SERVER_TYPE', 'LITESPEED_SERVER_OLS');
            } elseif (isset($_SERVER['SERVER_SOFTWARE']) && $_SERVER['SERVER_SOFTWARE'] == 'LiteSpeed') {
                define('LITESPEED_SERVER_TYPE', 'LITESPEED_SERVER_ENT');
            } else {
                define('LITESPEED_SERVER_TYPE', 'NONE');
            }
        }

        // Checks if caching is allowed via server variable
        if ( LITESPEED_SERVER_TYPE === 'LITESPEED_SERVER_ADC' || LITESPEED_SERVER_TYPE === 'LITESPEED_SERVER_ENT' || LITESPEED_SERVER_TYPE === 'LITESPEED_SERVER_OLS' || defined('LITESPEED_CLI')) {
            !defined('LITESPEED_ALLOWED') && define('LITESPEED_ALLOWED', true);
            $this->lscache->cacheEnabled = true;
        } else {
            $this->log->write('server type:' . LITESPEED_SERVER_TYPE);
            $this->log->write('lscache not enabled');
            return;
        }

        if (( LITESPEED_SERVER_TYPE !== 'LITESPEED_SERVER_OLS' ) && isset($this->lscache->setting['module_lscache_esi']) && ($this->lscache->setting['module_lscache_esi'] == '1')) {
            $this->lscache->esiEnabled = true;
        }
        
        include_once(DIR_EXTENSION . 'litespeed/system/library/lscache/lscachebase.php');
        include_once(DIR_EXTENSION . 'litespeed/system/library/lscache/lscachecore.php');
        $this->lscache->lscInstance = new \LiteSpeedCacheCore();
        $this->lscache->lscInstance->setHeaderFunction($this->response, 'addHeader');
    }

    public function onAfterRoute($route, &$args) {
        if (!$this->lscache->pageCachable && !$this->lscache->urlRule) {
            $pageKey = 'page_' . str_replace('/', '_', $route);
            if (isset($this->lscache->pages[$pageKey])) {
                $pageSetting = $this->lscache->pages[$pageKey];
            } else {
                return;
            }

            if ($this->customer->isLogged()) {
                if ($pageSetting['cacheLogin']) {
                    $this->lscache->pageCachable = true;
                } else {
                    return;
                }
            } else if ($pageSetting['cacheLogout']) {
                $this->lscache->pageCachable = true;
            } else {
                return;
            }

            $this->lscache->cacheTags[] = $pageKey;
        }

        $this->log('route:' . $route . PHP_EOL, self::LOG_DEBUG);

        $this->event->unregister('controller/*/before', 'extension/litespeed/module/lscache|onAfterInitialize');
        $this->event->register('controller/' . $route . '/after', new Action('extension/litespeed/module/lscache|onAfterRender'));

        //$this->log('page cachable:' . $this->lscache->pageCachable);

        if ($this->lscache->esiEnabled) {
            $esiModules = $this->model_extension_litespeed_module_lscache->getESIModules();
            $route = "";
            foreach ($esiModules as $key => $module) {
                if ($module['route'] != $route) {
                    $route = $module['route'];
                    if( ($pos = strpos($route,'|'))!==FALSE  ){
                        $route = substr($route, 0, $pos) ;
                    }                    
                    $this->event->register('controller/' . $route . '/after', new Action('extension/litespeed/module/lscache|onAfterRenderModule'));
                }
            }
            //$this->event->register('model/setting/module/getModule', new Action('extension/litespeed/module/lscache|onAfterGetModule'));
        }
    }

    public function onAfterRenderModule($route, &$args, &$output) {
        if (($this->lscache == null) || (!$this->lscache->pageCachable)) {
            return;
        }

        $esiModules = $this->model_extension_litespeed_module_lscache->getESIModules();
        $esiKey = 'esi_' . str_replace('/', '_', $route);
        if (count($args) > 0) {
            $esiKey .= '_' . $args['module_id'];
        }
        if (!isset($esiModules[$esiKey])) {
            return;
        }

        $module = $esiModules[$esiKey];
        $esiType = $module['esi_type'];

        $link = $this->url->link('extension/litespeed/module/lscache|renderESI', '');
        $link .= '&esiRoute=' . $route;
        if (isset($module['module']) && ($module['name'] != $module['module'])) {
            $link .= '&module_id=' . $module['module'];
        }

        if ($esiType == 3) {
            $esiBlock = '<esi:include src="' . $link . '" cache-control="public"/>';
        } else if ($esiType == 2) {
//            if ($this->emptySession()) {
//                return;
//            }
            $esiBlock = '<esi:include src="' . $link . '" cache-control="private"/>';
        } else if ($esiType == 1) {
            $esiBlock = '<esi:include src="' . $link . '" cache-control="no-cache"/>';
        } else {
            return;
        }
        $this->lscache->esiOn = true;

        $output = $this->setESIBlock($output, $route, $esiBlock, '');
    }

    protected function setESIBlock($output, $route, $esiBlock, $divElement) {
        if ($route == 'common/header') {
            $bodyElement = stripos($output, '<body');
            if ($bodyElement === false) {
                return $esiBlock;
            }

            return substr($output, 0, $bodyElement) . $esiBlock;
        }

        //for later usage only, currently no demands
        if (!empty($divElement)) {
            
        }

        return $esiBlock;
    }

    protected function getESIBlock($content, $route, $divElement) {
        if ($route == 'common/header') {
            $bodyElement = stripos($content, '<body');
            if ($bodyElement === false) {
                return $content;
            }
            return substr($content, $bodyElement);
        }

        //for later usage only, currently no demands
        if (!empty($divElement)) {
            
        }

        return $content;
    }

    public function onAfterRender($route, &$args, &$output) {
        if (($this->lscache == null) || (!$this->lscache->cacheEnabled)) {
            return;
        }

        $httpcode = 200;
        if (function_exists('http_response_code')) {
            $httpcode = http_response_code();
        }

        if ($httpcode > 201) {
            $this->log("Http Response Code Not Cachable:" . $httpcode);
            return;
        }

        $this->checkVary();

        if (!isset($this->lscache->setting['module_lscache_public_ttl'])) {
            $cacheTimeout = 1200000;
        } else {
            $cacheTimeout = $this->lscache->setting['module_lscache_public_ttl'];
            $cacheTimeout = empty($cacheTimeout) ? 1200000 : $cacheTimeout;
        }
        $this->lscache->lscInstance->setPublicTTL($cacheTimeout);
        $this->lscache->lscInstance->cachePublic($this->lscache->cacheTags, $this->lscache->esiOn);
        $this->log();
    }

    public function checkError($route, &$data, &$code) {
        if ($this->lscache == null) {
            http_response_code(403);
            return;
        }

        if (($route == 'error/not_found') && isset($this->lscache->setting['module_lscache_cache404']) && ($this->lscache->setting['module_lscache_cache404'] == '1')) {
            $url_data = $this->request->get;
            $route = trim($url_data['route']);
            if ($route == 'checkout/cart') {
                return;
            }

            $cacheTimeout = isset($this->lscache->setting['module_lscache_public_ttl']) ? $this->lscache->setting['module_lscache_public_ttl'] : 1200000;
            $this->lscache->lscInstance->setPublicTTL($cacheTimeout);
            $this->lscache->lscInstance->cachePublic('p_httpcode_404');
            return;
        }
    }

    public function renderESI() {
        if (($this->lscache == null) || (!$this->lscache->cacheEnabled)) {
            http_response_code(403);
            return;
        }

        if (isset($this->request->get['action'])) {
            if (($this->lscache->esiEnabled) && (substr($this->request->get['action'], 0, 4) == 'esi_')) {
                $purgeTag = $this->request->get['action'];
                $this->lscache->lscInstance->purgePrivate($purgeTag);
                $this->log();
            }

            $this->checkVary();

            $this->response->setOutput($content);
            return;
        }

        if (!isset($this->request->get['esiRoute'])) {
            http_response_code(403);
            return;
        }

        $esiRoute = $this->request->get['esiRoute'];
        $esiKey = 'esi_' . str_replace('/', '_', $esiRoute);
        $module_id = "";
        if (isset($this->request->get['module_id'])) {
            $module_id = $this->request->get['module_id'];
            $esiKey .= '_' . $module_id;
        }
        $this->lscache->cacheTags[] = $esiKey;

        $this->load->model('extension/litespeed/module/lscache');
        $esiModules = $this->model_extension_litespeed_module_lscache->getESIModules();
        if (!isset($esiModules[$esiKey])) {
            http_response_code(403);
            return;
        }

        $content = "";
        unset($this->request->get['route']);
        if (empty($module_id)) {
            $content = $this->load->controller($esiRoute);
        } else {
            $setting_info = $this->model_setting_module->getModule($module_id);

            if ($setting_info && $setting_info['status']) {
                $content = $this->load->controller($esiRoute, $setting_info);
            } else {
                http_response_code(403);
                return;
            }
        }

        $content = $this->getESIBlock($content, $esiRoute, '');

        $this->response->setOutput($content);

        $module = $esiModules[$esiKey];
        if ($module['esi_type'] > '1') {
            $cacheTimeout = $module['esi_ttl'];
            if(empty($cacheTimeout)){
                $cacheTimeout = 3600000 ;
            }            
            $this->lscache->cacheTags[] = $module['esi_tag'];
            $this->lscache->lscInstance->setPublicTTL($cacheTimeout);
            if ($module['esi_type'] == '2') {
                $this->lscache->lscInstance->checkPrivateCookie();
                $this->lscache->lscInstance->setPrivateTTL($cacheTimeout);
                $this->lscache->lscInstance->cachePrivate($this->lscache->cacheTags, $this->lscache->cacheTags);
            } else {
                $this->lscache->lscInstance->cachePublic($this->lscache->cacheTags);
            }
            $this->log();
        }

        $this->event->unregister('controller/*/before', 'extension/litespeed/module/lscache/onAfterInitialize');
    }

    public function onAfterGetModule($route, &$args) {
        //$output['module_id'] = $args[0];
    }

    public function onUserAfterLogin($route, &$args, &$output) {
        if (($this->lscache == null) || (!$this->lscache->cacheEnabled)) {
            return;
        }
        $this->lscache->lscInstance->checkPrivateCookie();
        if (!defined('LSC_PRIVATE')) {
            define('LSC_PRIVATE', true);
        }
        $this->checkVary();
        if ($this->lscache->esiEnabled) {
            $this->lscache->lscInstance->purgeAllPrivate();
            $this->log();
        }
    }

    public function onUserAfterLogout($route, &$args, &$output) {
        if (($this->lscache == null) || (!$this->lscache->cacheEnabled)) {
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

        if ($this->session->data['currency'] != $this->config->get('config_currency')) {
            $vary['currency'] = $this->session->data['currency'];
        }

        if ((isset($this->session->data['language'])) && ($this->session->data['language'] != $this->config->get('config_language'))) {
            $vary['language'] = $this->session->data['language'];
        }


        //cookie not enabled
        if ((count($vary) == 0) && !$this->checkCookiesEnabled()) {
            return;
        }


        if ($this->customer->isLogged() && isset($this->lscache->setting['module_lscache_vary_login']) && ($this->lscache->setting['module_lscache_vary_login'] == '1')) {
            $vary['session'] = 'loggedIn';
        }

        if (isset($this->lscache->setting['module_lscache_vary_safari']) && ($this->lscache->setting['module_lscache_vary_safari'] == '1') && $this->checkSafari()) {
            $vary['browser'] = 'safari';
        }

        if (isset($this->lscache->setting['module_lscache_vary_mobile']) && ($this->lscache->setting['module_lscache_vary_mobile'] == '1') && ($device = $this->checkMobile())) {
            $vary['device'] = $device;
        }

        if ((count($vary) == 0) && (isset($_COOKIE['lsc_private']) || defined('LSC_PRIVATE'))) {
            $vary['session'] = 'loggedOut';
        }

        ksort($vary);

        $varyKey = $this->implode2($vary, ',', ':');

        //$this->log('vary:' . $varyKey, 0);
        $this->lscache->lscInstance->checkVary($varyKey);
    }

    public function getProducts($route, &$args, &$output) {
        if (($this->lscache == null) || (!$this->lscache->cacheEnabled)) {
            return;
        }

        //$this->lscache->cacheTags[] = 'Product';
    }

    public function getCategories($route, &$args, &$output) {
        if (($this->lscache == null) || (!$this->lscache->cacheEnabled)) {
            return;
        }

        //$this->lscache->cacheTags[] = 'Category';
    }

    public function getInformations($route, &$args, &$output) {
        if (($this->lscache == null) || (!$this->lscache->cacheEnabled)) {
            return;
        }

        //$this->lscache->cacheTags[] = 'Information';
    }

    public function getManufacturers($route, &$args, &$output) {
        if (($this->lscache == null) || (!$this->lscache->cacheEnabled)) {
            return;
        }

        //$this->lscache->cacheTags[] = 'Manufacturer';
    }

    public function getProduct($route, &$args, &$output) {
        if (($this->lscache == null) || (!$this->lscache->cacheEnabled)) {
            return;
        }

        $this->lscache->cacheTags[] = 'P_' . $args[0];
    }

    public function getCategory($route, &$args, &$output) {
        if (($this->lscache == null) || (!$this->lscache->cacheEnabled)) {
            return;
        }

        if (isset($this->lscache->setting['module_lscache_purge_category']) && ($this->lscache->setting['module_lscache_purge_category'] == '0') && (strpos($this->lscache->route, 'category') == false)) {
            return;
        }

        $this->lscache->cacheTags[] = 'C_' . $args[0];
    }

    public function getInformation($route, &$args, &$output) {
        if (($this->lscache == null) || (!$this->lscache->cacheEnabled)) {
            return;
        }

        $this->lscache->cacheTags[] = 'I_' . $args[0];
    }

    public function getManufacturer($route, &$args, &$output) {
        if (($this->lscache == null) || (!$this->lscache->cacheEnabled)) {
            return;
        }

        $this->lscache->cacheTags[] = 'M_' . $args[0];
    }

    public function editCart($route, &$args, &$output) {
        if (($this->lscache == null) || (!$this->lscache->cacheEnabled)) {
            return;
        }

        if ($this->lscache->esiEnabled) {
            $this->lscache->lscInstance->checkPrivateCookie();
            if (!defined('LSC_PRIVATE')) {
                define('LSC_PRIVATE', true);
            }
            $this->checkVary();
            $purgeTag = 'esi_common_header,esi_cart';
            $this->lscache->lscInstance->purgePrivate($purgeTag);
            $this->log();
        } else {
            $this->checkVary();
        }
    }

    public function confirmOrder($route, &$args, &$output) {
        if (($this->lscache == null) || (!$this->lscache->cacheEnabled)) {
            return;
        }

        $purgeTag = 'Product,Category';
        foreach ($this->cart->getProducts() as $product) {
            $purgeTag .= ',P_' . $product['product_id'];
        }

        if ($this->lscache->esiEnabled) {
            $purgeTag .= ',esi_cart';
            $this->lscache->lscInstance->purgePrivate($purgeTag);
            $this->log();
        } else {
            $this->lscache->lscInstance->purgePrivate($purgeTag);
            $this->log();
            $this->checkVary();
        }
    }

    public function addAjax($route, &$args, &$output) {
        if (($this->lscache == null) || (!$this->lscache->pageCachable)) {
            return;
        }

        $ajax = 'wishlist.add("-1");';
        if ($this->lscache->esiEnabled && isset($this->lscache->setting['module_lscache_ajax_wishlist']) && ($this->lscache->setting['module_lscache_ajax_wishlist'] == '0')) {
            $ajax = '';
        }

        if (isset($this->lscache->setting['module_lscache_ajax_compare']) && ($this->lscache->setting['module_lscache_ajax_compare'] == '1')) {
            $ajax .= 'compare.add("-1");';
        }

        if (!$this->lscache->esiEnabled || (isset($this->lscache->setting['module_lscache_ajax_shopcart']) && ($this->lscache->setting['module_lscache_ajax_shopcart'] == '1'))) {
            $output .= '<script>$(document).ready(function() {try{ ' . $ajax . ' cart.remove("-1");} catch(err){console.log(err.message);}});</script>';
        } else if (!empty($ajax)) {
            $output .= '<script>$(document).ready(function() { try {  ' . $ajax . ' } catch(err){console.log(err.message);}});</script>';
        }

        if (isset($_SERVER['HTTP_USER_AGENT'])) {
            $comment = '<!-- LiteSpeed Cache created with user_agent: ' . $_SERVER['HTTP_USER_AGENT'] . ' -->' . PHP_EOL;
            $output = $comment . $output;
        }
    }

    public function checkWishlist($route, &$args) {
        if (($this->lscache == null) || (!$this->lscache->cacheEnabled)) {
            return;
        }

        $this->response->addHeader('Access-Control-Allow-Origin: *');
        $this->response->addHeader("Access-Control-Allow-Credentials: true");
        $this->response->addHeader("Access-Control-Allow-Methods: GET,HEAD,OPTIONS,POST,PUT");
        $this->response->addHeader("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");

        if (isset($this->request->post['product_id']) && ($this->request->post['product_id'] == "-1")) {
            if ($this->customer->isLogged()) {
                $this->load->model('account/wishlist');
                $total = $this->model_account_wishlist->getTotalWishlist();
            } else {
                $total = isset($this->session->data['wishlist']) ? count($this->session->data['wishlist']) : 0;
            }
            $this->load->language('account/wishlist');
            $text_wishlist = $this->language->get('text_wishlist');
            if (!empty($text_wishlist)) {
                $text_wishlist = 'Wish List (%s)';
            }
            $json = array();
            $json['count'] = $total;
            $json['total'] = sprintf($text_wishlist, $total);

            $this->response->setOutput(json_encode($json));
            return json_encode($json);
        }
    }

    public function checkCompare($route, &$args) {
        if (($this->lscache == null) || (!$this->lscache->cacheEnabled)) {
            return;
        }

        $this->response->addHeader('Access-Control-Allow-Origin: *');
        $this->response->addHeader("Access-Control-Allow-Credentials: true");
        $this->response->addHeader("Access-Control-Allow-Methods: GET,HEAD,OPTIONS,POST,PUT");
        $this->response->addHeader("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization");

        if (isset($this->request->post['product_id']) && ($this->request->post['product_id'] == "-1")) {
            $total = isset($this->session->data['compare']) ? count($this->session->data['compare']) : 0;
            $this->load->language('product/compare');
            $text_compare = $this->language->get('text_compare');
            $json = array();
            if (!empty($text_compare)) {
                $json['total'] = sprintf($text_compare, $total);
            }
            $json['count'] = $total;
            $this->response->setOutput(json_encode($json));
            return json_encode($json);
        }
    }

    public function editWishlist($route, &$args, &$output) {
        if (($this->lscache == null) || (!$this->lscache->cacheEnabled)) {
            return;
        }

        if (($this->lscache->esiEnabled) && isset($this->lscache->setting['module_lscache_ajax_wishlist']) && ($this->lscache->setting['module_lscache_ajax_wishlist'] == '1')) {
            $this->lscache->lscInstance->checkPrivateCookie();
            if (!defined('LSC_PRIVATE')) {
                define('LSC_PRIVATE', true);
            }
            $this->checkVary();
            $purgeTag = 'esi_common_header,esi_wishlist';
            $this->lscache->lscInstance->purgePrivate($purgeTag);
            $this->log();
        } else {
            $this->checkVary();
        }
    }

    public function editCompare($route, &$args, &$output) {
        if (($this->lscache == null) || (!$this->lscache->cacheEnabled)) {
            return;
        }

        if (($this->lscache->esiEnabled) && isset($this->lscache->setting['module_lscache_ajax_compare']) && ($this->lscache->setting['module_lscache_ajax_compare'] == '1')) {
            $this->lscache->lscInstance->checkPrivateCookie();
            if (!defined('LSC_PRIVATE')) {
                define('LSC_PRIVATE', true);
            }
            $this->checkVary();
            $purgeTag = 'esi_common_header,esi_compare';
            $this->lscache->lscInstance->purgePrivate($purgeTag);
            $this->log();
        } else {
            $this->checkVary();
        }
    }

    public function editCurrency($route, &$args) {
        if (($this->lscache == null) || (!$this->lscache->cacheEnabled)) {
            return;
        }

        if ($this->lscache->esiEnabled) {
            $this->lscache->lscInstance->checkPrivateCookie();
            if (!defined('LSC_PRIVATE')) {
                define('LSC_PRIVATE', true);
            }
        }
        $this->session->data['currency'] = $this->request->post['code'];
        $this->checkVary();
    }

    public function editLanguage($route, &$args) {
        if (($this->lscache == null) || (!$this->lscache->cacheEnabled)) {
            return;
        }

        if ($this->lscache->esiEnabled) {
            $this->lscache->lscInstance->checkPrivateCookie();
            if (!defined('LSC_PRIVATE')) {
                define('LSC_PRIVATE', true);
            }
        }

        $this->session->data['language'] = $this->request->post['code'];
        $this->checkVary();
    }

    public function log($content = null, $logLevel = self::LOG_INFO) {
        if ($this->lscache == null) {
            $this->load->model('extension/litespeed/module/lscache');
            $this->lscache = (object) array('setting' => $this->model_extension_litespeed_module_lscache->getItems());
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

        if (isset($this->session->data['lscacheOption']) && ($this->session->data['lscacheOption'] == "debug")) {
            $this->log->write($content);
            return;
        } else if ($logLevelSetting == self::LOG_DEBUG) {
            return;
        } else if ($logLevel > $logLevelSetting) {
            return;
        }

        $logInfo = "LiteSpeed Cache Info:\n";
        if ($logLevel == self::LOG_ERROR) {
            $logInfo = "LiteSpeed Cache Error:\n";
        } else if ($logLevel == self::LOG_DEBUG) {
            $logInfo = "LiteSpeed Cache Debug:\n";
        }

        $this->log->write($logInfo . $content);
    }

    public function recache() {

        $cli = false;

        if (php_sapi_name() == 'cli') {
            $cli = true;
        }

        if (isset($this->request->get['from']) && ($this->request->get['from'] == 'cli')) {
            $ip = trim($_SERVER['REMOTE_ADDR']);
            $serverIP = trim($_SERVER['SERVER_ADDR']);
            if ((substr($serverIP, 0, 7) == "127.0.0") || (substr($ip, 0, 7) == "127.0.0") || ($ip == $serverIP)) {
                $cli = true;
            }
        }

        if ($cli) {
            
        } else if (!isset($this->session->data['previouseURL'])) {
            http_response_code(403);
            return;
        } else {
            $previouseURL = $this->session->data['previouseURL'];
            unset($this->session->data['previouseURL']);
        }

        echo 'Recache may take several minutes' . ($cli ? '' : '<br>') . PHP_EOL;
        flush();

        echo 'recache site urls...' . ($cli ? '' : '<br>') . PHP_EOL;

        $urls = array();
        $urls[] = $this->url->link('common/home');
        $urls[] = $this->url->link('information/contact');
        $urls[] = $this->url->link('information/sitemap');
        $urls[] = $this->url->link('product/manufacturer');
        $urls[] = HTTP_SERVER;
        $urls[] = HTTP_SERVER . 'index.php';
        if ($this->lscache->includeUrls) {
            foreach ($this->lscache->includeUrls as $uri) {
                $urls[] = $this->url->link($uri);
            }
        }
        $this->crawlUrls($urls, $cli);
        $urls = array();

        $this->load->model('extension/litespeed/module/lscache');
        $pages = $this->model_extension_litespeed_module_lscache->getPages();

        echo 'recache page urls...' . ($cli ? '' : '<br>') . PHP_EOL;
        foreach ($pages as $page) {
            if ($page['cacheLogout']) {
                $urls[] = $this->url->link($page['route'], '');
            }
        }
        $this->crawlUrls($urls, $cli);
        $urls = array();

        $this->load->model('catalog/category');
        $this->load->model('catalog/product');

        $categories_1 = $this->model_catalog_category->getCategories(0);
        $categoryPath = array();

        echo 'recache category urls...' . ($cli ? '' : '<br>') . PHP_EOL;
        foreach ($categories_1 as $category_1) {
            $categoryPath[$category_1['category_id']] = $category_1['category_id'];
            $categories_2 = $this->model_catalog_category->getCategories($category_1['category_id']);
            foreach ($categories_2 as $category_2) {
                $categoryPath[$category_2['category_id']] = $category_1['category_id'] . '_' . $category_2['category_id'];
                $categories_3 = $this->model_catalog_category->getCategories($category_2['category_id']);
                foreach ($categories_3 as $category_3) {
                    $categoryPath[$category_3['category_id']] = $category_1['category_id'] . '_' . $category_2['category_id'] . '_' . $category_3['category_id'];
                    $categories_4 = $this->model_catalog_category->getCategories($category_3['category_id']);
                    foreach ($categories_4 as $category_4) {
                        $categoryPath[$category_4['category_id']] = $category_1['category_id'] . '_' . $category_2['category_id'] . '_' . $category_3['category_id'] . '_' . $category_4['category_id'];
                        $categories_5 = $this->model_catalog_category->getCategories($category_4['category_id']);
                        foreach ($categories_5 as $category_5) {
                            $categoryPath[$category_5['category_id']] = $category_1['category_id'] . '_' . $category_2['category_id'] . '_' . $category_3['category_id'] . '_' . $category_4['category_id'] . '_' . $category_5['category_id'];
                            $urls[] = $this->url->link('product/category', 'path=' . $category_1['category_id'] . '_' . $category_2['category_id'] . '_' . $category_3['category_id'] . '_' . $category_4['category_id'] . '_' . $category_5['category_id']);
                        }
                        $urls[] = $this->url->link('product/category', 'path=' . $category_1['category_id'] . '_' . $category_2['category_id'] . '_' . $category_3['category_id'] . '_' . $category_4['category_id']);
                        $filter_data = array('filter_category_id' => $category_4['category_id']);
                        $num_pages = $this->CountNumberOfPages($filter_data);
                        for ($num_page = 2; $num_page <= $num_pages; $num_page++) {
                            $urls[] = $this->url->link('product/category', 'path=' . $category_1['category_id'] . '_' . $category_2['category_id'] . '_' . $category_3['category_id'] . '_' . $category_4['category_id'] . '&page=' . $num_page);
                        }
                    }
                    $urls[] = $this->url->link('product/category', 'path=' . $category_1['category_id'] . '_' . $category_2['category_id'] . '_' . $category_3['category_id']);
                    $filter_data = array('filter_category_id' => $category_3['category_id']);
                    $num_pages = $this->CountNumberOfPages($filter_data);
                    for ($num_page = 2; $num_page <= $num_pages; $num_page++) {
                        $urls[] = $this->url->link('product/category', 'path=' . $category_1['category_id'] . '_' . $category_2['category_id'] . '_' . $category_3['category_id'] . '&page=' . $num_page);
                    }
                }
                $urls[] = $this->url->link('product/category', 'path=' . $category_1['category_id'] . '_' . $category_2['category_id']);
            }
            $urls[] = $this->url->link('product/category', 'path=' . $category_1['category_id']);
        }
        $this->crawlUrls($urls, $cli);
        $urls = array();

        $this->crawlUrls($urls, $cli);
        echo 'recache manufacturers urls...' . ($cli ? '' : '<br>') . PHP_EOL;
        $this->load->model('catalog/manufacturer');
        foreach ($this->model_catalog_manufacturer->getManufacturers() as $result) {
            $urls[] = $this->url->link('product/manufacturer/info', 'manufacturer_id=' . $result['manufacturer_id']);
        }
        $this->crawlUrls($urls, $cli);
        $urls = array();

        echo 'recache information urls...' . ($cli ? '' : '<br>') . PHP_EOL;
        $this->load->model('catalog/information');
        foreach ($this->model_catalog_information->getInformations() as $result) {
            $urls[] = $this->url->link('information/information', 'information_id=' . $result['information_id']);
        }
        $this->crawlUrls($urls, $cli);
        $urls = array();

        echo 'recache product urls...' . ($cli ? '' : '<br>') . PHP_EOL;
        foreach ($this->model_catalog_product->getProducts() as $result) {
            foreach ($this->model_catalog_product->getCategories($result['product_id']) as $category) {
                if (isset($categoryPath[$category['category_id']])) {
                    $urls[] = $this->url->link('product/product', 'path=' . $categoryPath[$category['category_id']] . '&product_id=' . $result['product_id']);
                }
            }

            $urls[] = $this->url->link('product/product', 'product_id=' . $result['product_id']);
            if (defined('JOURNAL3_ACTIVE')) {
                $urls[] = $this->url->link('journal3/product', 'product_id=' . $result['product_id'] . '&popup=quickview');
            }
        }

        $this->crawlUrls($urls, $cli);

        $data['success'] = $this->language->get('text_success');

        if (!$cli) {
            echo '<script type="text/javascript">
                       window.location = "' . str_replace('&amp;', '&', $previouseURL) . '"
                  </script>';
        }
    }

    private function crawlUrls($urls, $cli = false) {
        set_time_limit(0);

        $count = count($urls);
        if ($count < 1) {
            return "";
        }

        $cached = 0;
        $acceptCode = array(200, 201);
        $begin = microtime();
        $success = 0;
        $current = 1;

        ob_implicit_flush(TRUE);
        if (ob_get_contents()) {
            ob_end_clean();
        }
        $this->log('Start Recache:');

        $recacheOption = isset($this->lscache->setting['module_lscache_recache_option']) ? $this->lscache->setting['module_lscache_recache_option'] : 0;
        $recacheUserAgents = isset($this->lscache->setting['module_lscache_recache_userAgent']) ? explode(PHP_EOL, $this->lscache->setting['module_lscache_recache_userAgent']) : array("lscache_runner");
        if (empty($recacheUserAgents) || empty($recacheUserAgents[0])) {
            $recacheUserAgents = array('lscache_runner');
        } else {
            foreach($recacheUserAgents as $ua){
                if(strpos($ua, 'lscache_runner')===false){
                    $ua = $ua . ' lscache-runner';
                }
            }
        }

        if ($this->lscache->esiEnabled) {
            $cookies = array('', '_lscache_vary=session%3AloggedOut');
        } else {
            $cookies = array('');
        }

        $this->load->model('localisation/language');
        $languages = array();
        $results = $this->model_localisation_language->getLanguages();
        foreach ($results as $result) {
            if ($result['status']) {
                $languages[] = array(
                    'code' => $result['code'],
                    'name' => $result['name'],
                );
            }
            if (($recacheOption == '1') && ($result['code'] != $this->config->get('config_language'))) {
                $cookies[] = '_lscache_vary=language%3A' . $result['code'] . ';language=' . $result['code'] ;
            }    
        }

        $this->load->model('localisation/currency');
        $currencies = array();
        $results = $this->model_localisation_currency->getCurrencies();
        foreach ($results as $result) {
            if ($result['status']) {
                $currencies[] = array(
                    'code' => $result['code'],
                    'title' => $result['title'],
                );
            }

            if (($recacheOption == '2') && ($result['code'] != $this->config->get('config_currency'))) {
                $cookies[] = '_lscache_vary=currency%3A' . $result['code'] . ';currency=' . $result['code'];
            }
        }

        if ($recacheOption == '3') {
            foreach ($languages as $language) {
                foreach ($currencies as $currency) {
                    if (($language['code'] != $this->config->get('config_language')) && ($currency['code'] != $this->config->get('config_currency'))) {
                        $cookies[] = '_lscache_vary=language%3A' . $language['code'] . ',currency%3A' . $currency['code'] . ';language=' . $language['code'] . ';currency=' . $currency['code'];
                    }
                }
            }
        }

        foreach ($urls as $url) {

            $url = str_replace('&amp;', '&', $url);

            foreach ($recacheUserAgents as $userAgent) {
                $cookies1 = $cookies;

                $varyMobile = false;
                if (isset($this->lscache->setting['module_lscache_vary_mobile']) && ($this->lscache->setting['module_lscache_vary_mobile'] == '1') && $this->checkMobile($userAgent)) {
                    $device = $this->checkMobile($userAgent);
                    $cookies1[] = '_lscache_vary=device%3A' . $device;
                    $varyMobile = true;
                }

                $varySafari = false;
                if (isset($this->lscache->setting['module_lscache_vary_safari']) && ($this->lscache->setting['module_lscache_vary_safari'] == '1') && $this->checkSafari($userAgent)) {
                    $cookies1[] = '_lscache_vary=browser%3Asafari';
                    $varySafari = true;
                }

                if($varyMobile && $varySafari){
                    $cookies1[] = '_lscache_vary=browser%3Asafari%2Cdevice%3A' . $device ;
                }
                
                foreach ($cookies1 as $cookie) {
                    $this->log('crawl:' . $url . '  useragent:' . $userAgent . '    cookie:' . $cookie);
                    $start = microtime();
                    $ch = curl_init();
                    curl_setopt($ch, CURLOPT_URL, $url);
                    curl_setopt($ch, CURLOPT_HEADER, false);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                    curl_setopt($ch, CURLOPT_MAXREDIRS, 1);
                    curl_setopt($ch, CURLOPT_ENCODING, "");
                    curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

                    if ($cli && ($userAgent == 'lscache_runner')) {
                        $userAgent = 'lscache_walker';
                    }

                    curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);

                    if ($cookie != '') {
                        curl_setopt($ch, CURLOPT_COOKIE, $cookie);
                    }

                    $buffer = curl_exec($ch);
                    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    curl_close($ch);

                    if (in_array($httpcode, $acceptCode)) {
                        $success++;
                    } else if ($httpcode == 428) {
                        if (!$cli) {
                            echo 'Web Server crawler feature not enabled, please check <a href="https://www.litespeedtech.com/support/wiki/doku.php/litespeed_wiki:cache:lscwp:configuration:enabling_the_crawler" target="_blank">web server settings</a>';
                        } else {
                            echo 'Web Server crawler feature not enabled, please check "https://www.litespeedtech.com/support/wiki/doku.php/litespeed_wiki:cache:lscwp:configuration:enabling_the_crawler"' . PHP_EOL;
                        }
                        $this->log('httpcode:' . $httpcode);
                        sleep(5);
                        return;
                    } else {
                        $this->log('httpcode:' . $httpcode);
                    }

                    $end = microtime();
                    $diff = $this->microtimeMinus($start, $end);
                    usleep(round($diff));
                }
            }

            if ($cli) {
                echo $current . '/' . $count . ' ' . $url . ' : ' . $httpcode . PHP_EOL;
            } else {
                echo $current . '/' . $count . ' ' . $url . ' : ' . $httpcode . '<br/>' . PHP_EOL;
            }
            flush();

            $current++;
        }

    }

    public function purgeAll() {
        $cli = false;

        if (php_sapi_name() == 'cli') {
            $cli = true;
        }

        if (isset($this->request->get['from']) && ($this->request->get['from'] == 'cli')) {
            $ip = $_SERVER['REMOTE_ADDR'];
            $serverIP = $_SERVER['SERVER_ADDR'];
            if ((substr($serverIP, 0, 7) == "127.0.0") || (substr($ip, 0, 7) == "127.0.0") || ($ip == $serverIP)) {
                $cli = true;
            }
        }

        if (!$cli) {
            http_response_code(403);
            return;
        }

        $url = $this->url->link('extension/litespeed/module/lscache/purgeAllAction');
        $content = $this->file_get_contents_curl($url);
        echo $content;
    }

    public function purgeAllAction() {
        if (($this->lscache == null) || (!$this->lscache->cacheEnabled)) {
            http_response_code(403);
            return;
        }

        $visitorIP = $_SERVER['REMOTE_ADDR'];
        $serverIP = $_SERVER['SERVER_ADDR'];

        if (($visitorIP == "127.0.0.1") || ($serverIP == "127.0.0.1") || ($visitorIP == $serverIP)) {
            $lscInstance = new LiteSpeedCacheCore();
            $lscInstance->purgeAllPublic();
            echo 'All LiteSpeed Cache has been purged' . PHP_EOL;
            flush();
        } else {
            echo 'Operation not allowed from this device' . PHP_EOL;
            flush();
            http_response_code(403);
        }
    }

    private function microtimeMinus($start, $end) {
        list($s_usec, $s_sec) = explode(" ", $start);
        list($e_usec, $e_sec) = explode(" ", $end);
        $diff = ((int) $e_sec - (int) $s_sec) * 1000000 + ((float) $e_usec - (float) $s_usec) * 1000000;
        return $diff;
    }

    protected function emptySession() {
        if (isset($_COOKIE['lsc_private'])) {
            return false;
        }

        if ($this->customer->isLogged()) {
            return false;
        }

        if ($this->session->data['currency'] != $this->config->get('config_currency')) {
            return false;
        }

        if ($this->session->data['language'] != $this->config->get('config_language')) {
            return false;
        }

        return true;
    }

    protected function implode2(array $arr, $d1, $d2) {
        $arr1 = array();

        foreach ($arr as $key => $val) {
            $arr1[] = urlencode($key) . $d2 . urlencode($val);
        }
        return implode($d1, $arr1);
    }

    protected function file_get_contents_curl($url) {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_AUTOREFERER, TRUE);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, TRUE);

        $data = curl_exec($ch);
        curl_close($ch);

        return $data;
    }

    protected function checkMobile($ua = '') {
        if (empty($ua)) {
            $ua = $_SERVER['HTTP_USER_AGENT'];
        }

        if (defined('JOURNAL3_ACTIVE')) {
            //error_log(print_r('Journal3 mobile detection algorithm used',true));
            if (strpos($ua, 'iPhone') !== FALSE) {
                return 'mobile';
            } elseif (strpos($ua, 'iPad') !== FALSE) {
                return 'tablet';
            } elseif ((strpos($ua, 'Android') !== FALSE) && (strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome') !== FALSE) && (strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile') !== FALSE)) {
                return 'mobile';
            } elseif ((strpos($ua, 'Android') !== FALSE) && (strpos($_SERVER['HTTP_USER_AGENT'], 'Chrome') !== FALSE) && (strpos($_SERVER['HTTP_USER_AGENT'], 'Mobile') == FALSE)) {
                return 'tablet';
            } else {
                return false;
            }
        } else {
            //only use .htaccess rule to mark separate cache copy for mobile view
            return false;
//            include_once(DIR_SYSTEM . 'extension/litespeed/library/Mobile_Detect/Mobile_Detect.php');
//            $detect = new Mobile_Detect();
//            if ($detect->isTablet()) {
//                return 'tablet';
//            } else if ($detect->isMobile()) {
//                return 'mobile';
//            } else {
//                return false;
//            }
        }
    }

    protected function checkSafari($ua = '') {
        if (empty($ua)) {
            $ua = $_SERVER['HTTP_USER_AGENT'];
        }

        if (strpos($ua, 'CriOS') !== FALSE) {
            return FALSE;
        }

        if (strpos($ua, 'Chrome') !== FALSE) {
            return FALSE;
        }
        if (strpos($ua, 'Safari') !== FALSE) {
            return TRUE;
        }
        return FALSE;
    }

    protected function checkCookiesEnabled() {
        if (isset($_SERVER['HTTP_COOKIE'])) {
            return TRUE;
        }
        return FALSE;
    }

    protected function CountNumberOfPages($filter_data) {

        if (isset($this->request->get['limit'])) {
            $limit = (int) $this->request->get['limit'];
        } else if (defined('JOURNAL3_ACTIVE')) {
            $limit = $this->journal3->themeConfig('product_limit');
        } else {
            return 1;
        }

        if (defined('JOURNAL3_ACTIVE')) {
            $this->load->model('journal3/filter');

            $filter_data = array_merge($this->model_journal3_filter->parseFilterData(), $filter_data);

            $this->model_journal3_filter->setFilterData($filter_data);

            \Journal3\Utils\Profiler::start('journal3/filter/total_products');

            $product_total = $this->model_journal3_filter->getTotalProducts();

            \Journal3\Utils\Profiler::end('journal3/filter/total_products');
        } else {
            $product_total = $this->model_catalog_product->getTotalProducts($filter_data);
        }

        $num_pages = ceil($product_total / $limit);

        return $num_pages;
    }

}
