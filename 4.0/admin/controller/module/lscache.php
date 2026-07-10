<?php

/*
 *  @since      1.0.0
 *  @author     LiteSpeed Technologies <info@litespeedtech.com>
 *  @copyright  Copyright (c) 2017-2018 LiteSpeed Technologies, Inc. (https://www.litespeedtech.com)
 *  @license    https://opensource.org/licenses/GPL-3.0
 */

namespace Opencart\Admin\Controller\Extension\Litespeed\Module;
use Opencart\System\Library\Language;
use Opencart\System\Library\Url;

class Lscache extends \Opencart\System\Engine\Controller {

    const LOG_ERROR = 3;
    const LOG_INFO = 6;
    const LOG_DEBUG = 8;

    private $error = array();

    public function index() {

        $data = $this->load->language('extension/litespeed/module/lscache');
        if (!$this->isCurl()) {
            $data["button_recacheAll"] .= $data["text_curl_not_support"];
        }

        $currentLink = $this->url->link('extension/litespeed/module/lscache', 'user_token=' . $this->session->data['user_token'], true);
        $this->session->data['previouseURL'] = $currentLink;
        $parentLink = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $surl = HTTP_CATALOG;
        if(defined('HTTPS_CATALOG')){
            $surl = HTTPS_CATALOG;
        }
        $siteurl = new Url($surl);
        $recacheLink = $siteurl->link('extension/litespeed/module/lscache|recache', 'user_token=' . $this->session->data['user_token'], true);

        $action = 'index';
        if (isset($this->request->get['action'])) {
            $action = $this->request->get['action'];
        }
        $data['action'] = $action;

        if ($action == 'purgeAllButton') {
            $lscInstance = $this->lscacheInit(true);
        } else {
            $lscInstance = $this->lscacheInit();
        }
        $data["serverType"] = LITESPEED_SERVER_TYPE;

        if (isset($this->request->get['tab'])) {
            $data["tab"] = $this->request->get['tab'];
        } else {
            $data["tab"] = "general";
        }

        $this->load->model('extension/litespeed/module/lscache');
        $oldSetting = $this->model_extension_litespeed_module_lscache->getItems();

        if (!$this->validate()) {
            $this->log('Invalid Access', self::LOG_ERROR);
        } else if (($this->request->server['REQUEST_METHOD'] == 'POST') && isset($this->request->post['purgeURL'])) {
            $msg = $this->purgeUrls();
            $data["tab"] = "urls";
            $data['success'] = $msg;
        } else if (($this->request->server['REQUEST_METHOD'] == 'POST')) {
            $this->session->data['lscacheOption'] = "debug";
            $this->model_extension_litespeed_module_lscache->editSetting('module_lscache', $this->request->post);
            if (isset($this->session->data['error'])) {
                $data['error_warning'] = $this->session->data['error'];
                $this->session->data['error'] = "";
            } else {
                $this->session->data['success'] = $this->language->get('text_success');
                $data['success'] = $this->language->get('text_success');
            }

            if (isset($this->session->data["previouseTab"])) {
                $data["tab"] = $this->session->data["previouseTab"];
                unset($this->session->data["previouseTab"]);
            }

            if (($oldSetting["module_lscache_status"] != $this->request->post["module_lscache_status"]) || ($oldSetting["module_lscache_esi"] != $this->request->post["module_lscache_esi"])) {
                if ($lscInstance) {
                    $lscInstance->purgeAllPublic();
                    $data['success'] .= '<br><i class="fa fa-check-circle"></i> ' . $this->language->get('text_purgeSuccess');
                }
            }

            if (!isset($oldSetting["module_lscache_vary_mobile"])) {
                $oldSetting["module_lscache_vary_mobile"] = '0';
            }

            if (!isset($oldSetting["module_lscache_vary_safari"])) {
                $oldSetting["module_lscache_vary_safari"] = '0';
            }

            if (($oldSetting["module_lscache_vary_mobile"] != $this->request->post["module_lscache_vary_mobile"]) || ($oldSetting["module_lscache_vary_safari"] != $this->request->post["module_lscache_vary_safari"])) {
                $data['success'] = $this->language->get('text_commentHtaccess');
                if ($lscInstance) {
                    $lscInstance->purgeAllPublic();
                    $data['success'] .= '<br><i class="fa fa-check-circle"></i> ' . $this->language->get('text_purgeSuccess');
                }
            }
        } else if (($action == 'purgeAll') && $lscInstance) {
            $lscInstance->purgeAllPublic();
            $this->log($lscInstance->getLogBuffer());
            $data['success'] = $this->language->get('text_purgeSuccess') . $this->purgeCacheFiles(0);
        } else if (($action == 'purgeAllButton') && $lscInstance) {
            $lscInstance->purgeAllPublic();
            $this->log($lscInstance->getLogBuffer());
            $this->purgeCacheFiles(0);
            if (isset($_SERVER['HTTP_REFERER'])) {
                $this->response->redirect($_SERVER['HTTP_REFERER']);
                return;
            }
        } else if ($action == 'verifyCacheDir') {
            $data['success'] = $this->verifyCacheFiles();
            $data["tab"] = "files";
        } else if ($action == 'purgeCacheFiles') {
            $data['success'] = $this->language->get('text_files_purged') . $this->purgeCacheFiles(0);
            $data["tab"] = "files";
        } else if ($action == 'regenerateApiKey') {
            $data['success'] = $this->regenerateApiKey();
            $data["tab"] = "files";
        } else if ($action == 'installCron') {
            $data['success'] = $this->manageCron(true);
            $data["tab"] = "files";
        } else if ($action == 'removeCron') {
            $data['success'] = $this->manageCron(false);
            $data["tab"] = "files";
        } else if (($action == 'deletePage') && isset($this->request->get['key'])) {
            $key = $this->request->get['key'];
            $this->model_extension_litespeed_module_lscache->deleteSettingItem('module_lscache', $key);
            if ($lscInstance) {
                $lscInstance->purgePublic($key);
                $this->log($lscInstance->getLogBuffer());
                $data['success'] = $this->language->get('text_purgeSuccess');
            }
            $data["tab"] = "pages";
        } else if ($action == 'addESIModule') {
            $data['moduleOptions'] = $this->model_extension_litespeed_module_lscache->getESIModuleOptions();
            $data['extensionOptions'] = $this->model_extension_litespeed_module_lscache->getESIExtensionOptions();
            $data["tab"] = "modules";
            $this->session->data['previouseTab'] = "modules";
        } else if (($action == 'deleteESI') && isset($this->request->get['key'])) {
            $key = $this->request->get['key'];
            $this->model_extension_litespeed_module_lscache->deleteSettingItem('module_lscache', $key);
            if ($lscInstance) {
                $lscInstance->purgePublic($key);
                $this->log($lscInstance->getLogBuffer());
                $data['success'] = $this->language->get('text_purgeModule');
            }
            $data["tab"] = "modules";
        } else if (($action == 'purgeESI') && isset($this->request->get['key']) && $lscInstance) {
            $key = $this->request->get['key'];
            $lscInstance->purgePublic($key);
            $this->log($lscInstance->getLogBuffer());
            $data['success'] = $this->language->get('text_purgeModule');
            $data["tab"] = "modules";
        } else if (($action == 'purgePage') && isset($this->request->get['key']) && $lscInstance) {
            $key = $this->request->get['key'];
            $lscInstance->purgePublic($key);
            $this->log($lscInstance->getLogBuffer());
            $data['success'] = $this->language->get('text_purgeSuccess');
            $data["tab"] = "pages";
        } else if ($action == 'deleteESI') {
            $data["tab"] = "modules";
        } else if ($action == 'deletePage') {
            $data["tab"] = "pages";
        } else if ($action == 'addPage') {
            $this->session->data["previouseTab"] = "pages";
        } else if ($action == 'addESIRoute') {
            $this->session->data["previouseTab"] = "modules";
        }

        $data['pages'] = $this->model_extension_litespeed_module_lscache->getPages();
        $data['modules'] = $this->model_extension_litespeed_module_lscache->getModules();
        $items = $this->model_extension_litespeed_module_lscache->getItems();
        $data = array_merge($data, $items);

        // Cache-file storage info for the General tab (path / file count / total size on disk).
        $fileRoot = $this->cacheFilesRoot($items);
        if ($fileRoot !== '') {
            $fileStats = \LiteSpeedCacheFiles::stats($fileRoot);
            $data['lscache_dir'] = $fileRoot;
            $data['lscache_file_count'] = (int)$fileStats['count'];
            $data['lscache_file_size'] = \LiteSpeedCacheFiles::humanBytes($fileStats['bytes']);
        } else {
            $data['lscache_dir'] = '';
            $data['lscache_file_count'] = 0;
            $data['lscache_file_size'] = \LiteSpeedCacheFiles::humanBytes(0);
        }

        $data['tabtool'] = new Tool('active', 'general');
        $data['selectEnable'] = new Tool('selected', '1');
        $data['selectDisable'] = new Tool('selected', '0');
        $data['selectDefault'] = new Tool('selected', '');
        $data['checkEnable'] = new Tool('checked', '1');
        $data['checkDisable'] = new Tool('checked', '0');

        if (!empty($data['error_warning'])) {
            
        } else if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_module'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/litespeed/module/lscache', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['cancel'] = $parentLink;
        $data['self'] = $currentLink;
        $data['purgeAll'] = $currentLink . '&action=purgeAll';
        $data['purgePage'] = $currentLink . '&action=purgePage';
        $data['purgeESI'] = $currentLink . '&action=purgeESI';
        $data['recacheAll'] = $this->isCurl() ? $recacheLink : '#';
        $data['addPage'] = $currentLink . '&tab=pages&action=addPage';
        $data['deletePage'] = $currentLink . '&tab=pages&action=deletePage';
        $data['addESIModule'] = $currentLink . '&tab=modules&action=addESIModule';
        $data['addESIRoute'] = $currentLink . '&tab=modules&action=addESIRoute';
        $data['deleteESI'] = $currentLink . '&tab=modules&action=deleteESI';

        $this->document->setTitle($this->language->get('heading_title'));
        $this->document->addScript('https://gitcdn.github.io/bootstrap-toggle/2.2.2/js/bootstrap-toggle.min.js');
        $this->document->addStyle('https://gitcdn.github.io/bootstrap-toggle/2.2.2/css/bootstrap-toggle.min.css');

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/litespeed/module/lscache', $data));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/litespeed/module/lscache')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }
        return !$this->error;
    }

    public function purgeAllButton($route, &$args, &$output) {
        // Fires on every admin page (common/header/after) - piggyback the throttled age-based cleanup here.
        $this->maybeAutoCleanFiles();
        if ($this->user && $this->user->hasPermission('modify', 'extension/litespeed/module/lscache')) {
            $href = $this->url->link('extension/litespeed/module/lscache', 'user_token=' . $this->session->data['user_token'] . '&action=purgeAllButton', true);
            // Fixed literal instead of $this->load->language(): loading the module language file here
            // would overwrite the current page's heading_title (it defines heading_title = 'LiteSpeed
            // Cache'), which showed up as the Dashboard heading.
            $title = 'Purge All LiteSpeed Cache';
            // OC4 admin header is Bootstrap 5 (the old OC3 ".navbar-nav.navbar-right" markup no longer
            // exists). Drop the LiteSpeed Cache logo (inlined SVG, no external request) into the top bar
            // right before the notification bell; the tooltip is auto-initialised by admin common.js.
            $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="-75 -75 300 300" style="display:block"><path fill="#67777F" d="M12.3-1.939h48.429l17.229-32.682H-24.657c-5.492,0-9.963,4.44-9.963,9.932v199.379 c0,5.46,4.472,9.932,9.963,9.932h42.019l19.038-37.021H12.3c-6.085,0-11.016-4.933-11.016-10.983V9.11 C1.284,3.06,6.215-1.939,12.3-1.939z M12.3,157.206h8.359l-9.16,17.811h-36.157c-0.193,0-0.358-0.18-0.358-0.327V-24.689 c0-0.177,0.164-0.327,0.358-0.327h86.695l-7.103,13.472H12.3C0.93-11.544-8.321-2.279-8.321,9.11v127.507 C-8.321,147.97,0.93,157.206,12.3,157.206z"></path><path fill="#67777F" d="M174.657-34.622h-35.542c-0.168,0.527-0.361,0.988-0.593,1.449l-16.47,31.233h17.786 c6.05,0,11.014,5,11.014,11.049v127.507c0,6.051-4.964,10.983-11.014,10.983H97.982l-19.334,37.021h96.009 c5.491,0,9.963-4.472,9.963-9.932V-24.689C184.62-30.181,180.148-34.622,174.657-34.622z M175.015,174.689 c0,0.147-0.165,0.327-0.357,0.327H94.501l9.302-17.811h36.036c11.369,0,20.618-9.236,20.618-20.589V9.11 c0-11.389-9.249-20.654-20.618-20.654h-1.863l7.104-13.472h29.577c0.193,0,0.357,0.15,0.357,0.327V174.689z"></path><polygon fill="#FF7E33" points="30.844,184.621 71.349,105.447 105.608,105.447 65.069,184.621 "></polygon><path fill="#FF7E33" d="M35.808,94.137c-0.23-0.067-0.492-0.129-0.789-0.129h-2.991c-2.533-0.462-4.636-2.896-4.636-5.824 l1.775-4.272L91.572-34.622H125.7l-51.688,98.18c-0.461,0.855-0.425,1.906,0.066,2.761c0.528,0.855,1.447,1.347,2.435,1.347h38.634 c4.928,0.363,7.264,2.93,7.264,5.922l-0.788,2.792l-9.272,17.756H73.423H35.808z"></path></svg>';
            $button = '<li id="nav-lscache" class="nav-item d-flex align-items-center"><a href="' . $href . '" class="nav-link d-flex align-items-center py-0" data-bs-toggle="tooltip" data-bs-placement="bottom" title="' . $title . '">' . $svg . '</a></li>';
            $search = '<li id="nav-notification"';
            $output = str_replace($search, $button . $search, $output);
        }
    }

    /* ----- Physical cache-file housekeeping (the LiteSpeed purge API never removes files) ----- */

    // The cache lives relative to the CATALOG webroot, not the admin dir.
    private function catalogWebroot() {
        if (defined('DIR_CATALOG')) {
            return dirname(rtrim(DIR_CATALOG, '/\\'));
        }
        return isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '';
    }

    // Resolve + validate the cache directory (auto-detect, or the configured override). '' if not found.
    private function cacheFilesRoot($settings) {
        require_once(DIR_EXTENSION . 'litespeed/system/library/lscache/lscachefiles.php');
        $configured = isset($settings['module_lscache_cache_dir']) ? $settings['module_lscache_cache_dir'] : '';
        return \LiteSpeedCacheFiles::resolve($configured, $this->catalogWebroot());
    }

    // Physically delete cache files if the feature is enabled. $days = 0 => all. Returns an HTML fragment.
    private function purgeCacheFiles($days) {
        $settings = $this->model_extension_litespeed_module_lscache->getItems();
        if (empty($settings['module_lscache_file_delete'])) {
            return '';
        }
        $root = $this->cacheFilesRoot($settings);
        if ($root === '') {
            return '<br><i class="fa fa-exclamation-triangle"></i> ' . $this->language->get('text_files_no_dir');
        }
        $result = \LiteSpeedCacheFiles::purge($root, (int)$days);
        return '<br><i class="fa fa-trash"></i> ' . sprintf($this->language->get('text_files_deleted'), (int)$result['deleted']);
    }

    // Once-a-day, age-based cleanup triggered on admin visits (no cron needed).
    private function maybeAutoCleanFiles() {
        $this->load->model('extension/litespeed/module/lscache');
        $settings = $this->model_extension_litespeed_module_lscache->getItems();
        if (empty($settings['module_lscache_file_delete'])) {
            return;
        }
        $days = isset($settings['module_lscache_file_days']) ? (int)$settings['module_lscache_file_days'] : 0;
        if ($days <= 0) {
            return; // auto cleanup is age-based only; a full wipe must be explicit
        }
        $marker = (defined('DIR_CACHE') ? DIR_CACHE : sys_get_temp_dir() . '/') . 'lscache_lastclean';
        if (is_file($marker) && (time() - filemtime($marker) < 86400)) {
            return;
        }
        @touch($marker); // stamp first so concurrent requests do not double-run
        $root = $this->cacheFilesRoot($settings);
        if ($root !== '') {
            \LiteSpeedCacheFiles::purge($root, $days);
        }
    }

    // "Verify" button: report the resolved path, file count and total size.
    private function verifyCacheFiles() {
        $settings = $this->model_extension_litespeed_module_lscache->getItems();
        $root = $this->cacheFilesRoot($settings);
        if ($root === '') {
            return '<i class="fa fa-exclamation-triangle"></i> ' . $this->language->get('text_files_no_dir');
        }
        $stats = \LiteSpeedCacheFiles::stats($root);
        return sprintf($this->language->get('text_files_verify'), $root, (int)$stats['count'], \LiteSpeedCacheFiles::humanBytes($stats['bytes']));
    }

    private function regenerateApiKey() {
        $key = function_exists('random_bytes') ? bin2hex(random_bytes(20)) : sha1(uniqid('lscache', true));
        $this->model_extension_litespeed_module_lscache->setSettingItem('module_lscache', 'module_lscache_api_key', $key);
        return $this->language->get('text_apikey_regenerated');
    }

    // Build the cron command that calls the API-key-gated cleanup endpoint.
    private function cronLine($settings) {
        $time = isset($settings['module_lscache_cron_time']) ? trim($settings['module_lscache_cron_time']) : '03:00';
        if (preg_match('/^(\d{1,2}):(\d{2})$/', $time, $m)) {
            $hour = max(0, min(23, (int)$m[1]));
            $min = max(0, min(59, (int)$m[2]));
        } else {
            $hour = 3;
            $min = 0;
        }
        $key = isset($settings['module_lscache_api_key']) ? $settings['module_lscache_api_key'] : '';
        $base = defined('HTTPS_CATALOG') ? HTTPS_CATALOG : HTTP_CATALOG;
        $url = rtrim($base, '/') . '/index.php?route=extension/litespeed/module/lscache.apiCleanup';
        return sprintf('%d %d * * * curl -s -H "X-LSCache-Key: %s" "%s" >/dev/null 2>&1 # lscache-cleanup', $min, $hour, $key, $url);
    }

    // Install/remove the cleanup cron in the web user's crontab. Falls back to showing the line to paste.
    private function manageCron($install) {
        $settings = $this->model_extension_litespeed_module_lscache->getItems();
        $line = $this->cronLine($settings);
        $manual = sprintf($this->language->get('text_cron_manual'), htmlspecialchars($line));

        if (!function_exists('exec') || !function_exists('proc_open')) {
            return $manual;
        }
        $current = array();
        @exec('crontab -l 2>/dev/null', $current);
        $kept = array();
        foreach ($current as $l) {
            if (strpos($l, 'lscache-cleanup') === false) {
                $kept[] = $l;
            }
        }
        if ($install) {
            $kept[] = $line;
        }
        $content = trim(implode("\n", $kept));
        $content = ($content === '') ? '' : $content . "\n";

        if ($this->writeCrontab($content)) {
            return $install ? $this->language->get('text_cron_installed') : $this->language->get('text_cron_removed');
        }
        return $install ? $manual : $this->language->get('text_cron_manual_remove');
    }

    private function writeCrontab($content) {
        $spec = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
        $proc = @proc_open('crontab -', $spec, $pipes);
        if (!is_resource($proc)) {
            return false;
        }
        fwrite($pipes[0], $content);
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        return proc_close($proc) === 0;
    }

    private function addEvent($code, $description, $trigger, $action) {
        $event = ['code' => $code, 'description'=> $description, 'trigger'=>$trigger, 'action'=>$action, 'status'=> '1', 'sort_order'=>'1'];
        $this->model_setting_event->addEvent($event);
    }    

    public function install() {
        $this->load->model('setting/event');
        $this->load->model('extension/litespeed/module/lscache');
        $this->addEvent('lscache_init', '', 'catalog/controller/*/before', 'extension/litespeed/module/lscache.onAfterInitialize');
        $this->addEvent('lscache_button_purgeall','', 'admin/controller/common/header/after', 'extension/litespeed/module/lscache.purgeAllButton');
        $this->addEvent('lscache_product_list','', 'catalog/model/catalog/product.getProducts/after', 'extension/litespeed/module/lscache.getProducts');
        $this->addEvent('lscache_product_get','', 'catalog/model/catalog/product.getProduct/after', 'extension/litespeed/module/lscache.getProduct');
        $this->addEvent('lscache_product_add','', 'admin/model/catalog/product.addProduct/after', 'extension/litespeed/module/lscache.addProduct');
        $this->addEvent('lscache_product_edit','', 'admin/model/catalog/product.editProduct/after', 'extension/litespeed/module/lscache.editProduct');
        $this->addEvent('lscache_product_delete','', 'admin/model/catalog/product.deleteProduct/after', 'extension/litespeed/module/lscache.editProduct');

        $this->addEvent('lscache_category_list','', 'catalog/model/catalog/category.getCategories/after', 'extension/litespeed/module/lscache.getCategories');
        $this->addEvent('lscache_category_get', '','catalog/model/catalog/category.getCategory/after', 'extension/litespeed/module/lscache.getCategory');
        $this->addEvent('lscache_category_add', '','admin/model/catalog/category.addCategory/after', 'extension/litespeed/module/lscache.addCategory');
        $this->addEvent('lscache_category_edit','', 'admin/model/catalog/category.editCategory/after', 'extension/litespeed/module/lscache.editCategory');
        $this->addEvent('lscache_category_delete','', 'admin/model/catalog/category.deleteCategory/after', 'extension/litespeed/module/lscache.editCategory');

        $this->addEvent('lscache_manufacturer_list','', 'catalog/model/catalog/manufacturer.getManufacturers/after', 'extension/litespeed/module/lscache.getManufacturers');
        $this->addEvent('lscache_manufacturer_get','', 'catalog/model/catalog/manufacturer.getManufacturer/after', 'extension/litespeed/module/lscache.getManufacturer');
        $this->addEvent('lscache_manufacturer_add','', 'admin/model/catalog/manufacturer.addManufacturer/after', 'extension/litespeed/module/lscache.addManufacturer');
        $this->addEvent('lscache_manufacturer_edit','', 'admin/model/catalog/manufacturer.editManufacturer/after', 'extension/litespeed/module/lscache.editManufacturer');
        $this->addEvent('lscache_manufacturer_delete','', 'admin/model/catalog/manufacturer.deleteManufacturer/after', 'extension/litespeed/module/lscache.editManufacturer');

        $this->addEvent('lscache_information_list','', 'catalog/model/catalog/information.getInformations/after', 'extension/litespeed/module/lscache.getInformations');
        $this->addEvent('lscache_information_get','', 'catalog/model/catalog/information.getInformation/after', 'extension/litespeed/module/lscache.getInformation');
        $this->addEvent('lscache_information_add','', 'admin/model/catalog/information.addInformation/after', 'extension/litespeed/module/lscache.addInformation');
        $this->addEvent('lscache_information_edit','', 'admin/model/catalog/information.editInformation/after', 'extension/litespeed/module/lscache.editInformation');
        $this->addEvent('lscache_information_delete','', 'admin/model/catalog/information.deleteInformation/after', 'extension/litespeed/module/lscache.editInformation');

        $this->addEvent('lscache_checkout_confirm','', 'catalog/controller/checkout/confirm/after', 'extension/litespeed/module/lscache.confirmOrder');
        $this->addEvent('lscache_checkout_success','', 'catalog/controller/checkout/success/after', 'extension/litespeed/module/lscache.confirmOrder');

        $this->addEvent('lscache_add_ajax','', 'catalog/controller/common/header/after', 'extension/litespeed/module/lscache.addAjax');
        $this->addEvent('lscache_cart_add','', 'catalog/controller/checkout/cart.add/after', 'extension/litespeed/module/lscache.editCart');
        $this->addEvent('lscache_cart_edit','', 'catalog/controller/checkout/cart.edit/after', 'extension/litespeed/module/lscache.editCart');
        $this->addEvent('lscache_cart_remove','', 'catalog/controller/checkout/cart.remove/after', 'extension/litespeed/module/lscache.editCart');
        $this->addEvent('lscache_compare_check','', 'catalog/controller/product/compare.add/before', 'extension/litespeed/module/lscache.checkCompare');
        $this->addEvent('lscache_compare_edit','', 'catalog/controller/product/compare.add/after', 'extension/litespeed/module/lscache.editCompare');
        $this->addEvent('lscache_wishlist_check','', 'catalog/controller/account/wishlist.add/before', 'extension/litespeed/module/lscache.checkWishlist');
        $this->addEvent('lscache_wishlist_edit','', 'catalog/controller/account/wishlist.add/after', 'extension/litespeed/module/lscache.editWishlist');
        $this->addEvent('lscache_wishlist_display','', 'catalog/controller/account/wishlist.after', 'extension/litespeed/module/lscache.editWishlist');

        $this->addEvent('lscache_user_forgotten','', 'catalog/controller/account/forgotten.validate/after', 'extension/litespeed/module/lscache.onUserAfterLogin');
        $this->addEvent('lscache_user_login','', 'catalog/controller/account/login.validate/after', 'extension/litespeed/module/lscache.onUserAfterLogin');
        $this->addEvent('lscache_user_logout','', 'catalog/model/account/customer.deleteLoginAttempts/after', 'extension/litespeed/module/lscache.onUserAfterLogout');
        $this->addEvent('lscache_currency_change','', 'catalog/controller/common/currency.save/before', 'extension/litespeed/module/lscache.editCurrency');
        $this->addEvent('lscache_language_change','', 'catalog/controller/common/language.save/before', 'extension/litespeed/module/lscache.editLanguage');
        $this->addEvent('lscache_check_error','', 'catalog/view/error/*/before', 'extension/litespeed/module/lscache.checkError');

        $this->model_extension_litespeed_module_lscache->installLSCache();
        $this->initHtaccess();

        $lscInstance = $this->lscacheInit();
        if ($lscInstance) {
            $lscInstance->purgeAllPublic();
            //$this->log($lscInstance->getLogBuffer(), 0);
        }

        if (function_exists('opcache_reset')) {
            opcache_reset();
        } else if (function_exists('phpopcache_reset')) {
            phpopcache_reset();
        }

        //clear template cache file
//        try {
//            $template = new Template($this->registry->get('config')->get('template_engine'));
//            $loader = new \Twig_Loader_Filesystem(DIR_TEMPLATE);
//            $twig = new \Twig_Environment($loader);
//            $name = 'extension/litespeed/module/lscache.twig';
//            $cls = $twig->getTemplateClass($name);
//            $cache = new Twig_Cache_Filesystem(DIR_CACHE);
//            $key = $cache->generateKey($name, $cls);
//            unlink($key);
//        } catch (Exception $ex) {
//            
//        }
    }

    public function uninstall() {
        $this->load->model('setting/event');
        $this->load->model('extension/litespeed/module/lscache');
        $this->model_setting_event->deleteEventByCode('lscache_init');
        $this->model_setting_event->deleteEventByCode('lscache_button_purgeall');
        $this->model_setting_event->deleteEventByCode('lscache_product_list');
        $this->model_setting_event->deleteEventByCode('lscache_product_add');
        $this->model_setting_event->deleteEventByCode('lscache_product_get');
        $this->model_setting_event->deleteEventByCode('lscache_product_edit');
        $this->model_setting_event->deleteEventByCode('lscache_product_delete');
        $this->model_setting_event->deleteEventByCode('lscache_category_list');
        $this->model_setting_event->deleteEventByCode('lscache_category_add');
        $this->model_setting_event->deleteEventByCode('lscache_category_get');
        $this->model_setting_event->deleteEventByCode('lscache_category_edit');
        $this->model_setting_event->deleteEventByCode('lscache_category_delete');
        $this->model_setting_event->deleteEventByCode('lscache_manufacturer_list');
        $this->model_setting_event->deleteEventByCode('lscache_manufacturer_add');
        $this->model_setting_event->deleteEventByCode('lscache_manufacturer_get');
        $this->model_setting_event->deleteEventByCode('lscache_manufacturer_edit');
        $this->model_setting_event->deleteEventByCode('lscache_manufacturer_delete');
        $this->model_setting_event->deleteEventByCode('lscache_information_list');
        $this->model_setting_event->deleteEventByCode('lscache_information_add');
        $this->model_setting_event->deleteEventByCode('lscache_information_get');
        $this->model_setting_event->deleteEventByCode('lscache_information_edit');
        $this->model_setting_event->deleteEventByCode('lscache_information_delete');

        $this->model_setting_event->deleteEventByCode('lscache_checkout_confirm');
        $this->model_setting_event->deleteEventByCode('lscache_checkout_success');
        $this->model_setting_event->deleteEventByCode('lscache_cart_add');
        $this->model_setting_event->deleteEventByCode('lscache_cart_edit');
        $this->model_setting_event->deleteEventByCode('lscache_cart_remove');
        $this->model_setting_event->deleteEventByCode('lscache_add_ajax');
        $this->model_setting_event->deleteEventByCode('lscache_wishlist_display');
        $this->model_setting_event->deleteEventByCode('lscache_wishlist_edit');
        $this->model_setting_event->deleteEventByCode('lscache_wishlist_check');
        $this->model_setting_event->deleteEventByCode('lscache_compare_check');
        $this->model_setting_event->deleteEventByCode('lscache_compare_edit');
        $this->model_setting_event->deleteEventByCode('lscache_user_forgotten');
        $this->model_setting_event->deleteEventByCode('lscache_user_login');
        $this->model_setting_event->deleteEventByCode('lscache_user_logout');
        $this->model_setting_event->deleteEventByCode('lscache_currency_change');
        $this->model_setting_event->deleteEventByCode('lscache_language_change');
        $this->model_setting_event->deleteEventByCode('lscache_check_error');

        $this->clearHtaccess();
        $lscInstance = $this->lscacheInit();
        if ($lscInstance) {
            $lscInstance->purgeAllPublic();
            $this->log($lscInstance->getLogBuffer(), 0);
        }
        $this->model_extension_litespeed_module_lscache->uninstallLSCache();
    }

    public function addProduct($route, &$args, &$output) {
        $lscInstance = $this->lscacheInit(true);
        if ($lscInstance) {
            $purgeTag = 'Product, Category';
            $data = $args[0];
            if (isset($data['product_category'])) {
                foreach ($data['product_category'] as $category_id) {
                    $purgeTag .= ',' . 'C_' . $category_id;
                }
            }
            $lscInstance->purgePublic($purgeTag);
            $this->log($lscInstance->getLogBuffer());
            $this->load->language('extension/litespeed/module/lscache');
            $text_success = $this->language->get('text_success') . '<br><i class="fa fa-check-circle"></i> ' . $this->language->get('text_purgeSuccess');
            $this->language->set('text_success', $text_success);
        }
    }

    public function editProduct($route, &$args, &$output) {
        $lscInstance = $this->lscacheInit(true);
        if ($lscInstance) {
            $purgeTag = 'Product, Category,P_' . $args[0];
            $this->load->model('catalog/product');
            $categories = $this->model_catalog_product->getCategories($args[0]);
            foreach ($categories as $category) {
                $purgeTag .= ',' . 'C_' . $category;
            }

            $lscInstance->purgePublic($purgeTag);
            $this->log($lscInstance->getLogBuffer());

            $this->load->language('extension/litespeed/module/lscache');

            $text_success = $this->language->get('text_success') . '<br><i class="fa fa-check-circle"></i> ' . $this->language->get('text_purgeSuccess');
            $this->language->set('text_success', $text_success);
        }
    }

    public function addCategory($route, &$args, &$output) {
        $lscInstance = $this->lscacheInit(true);
        if ($lscInstance) {
            $purgeTag = 'Category';
            $lscInstance->purgePublic($purgeTag);
            $this->log($lscInstance->getLogBuffer());
            $this->load->language('extension/litespeed/module/lscache');
            $text_success = $this->language->get('text_success') . '<br><i class="fa fa-check-circle"></i> ' . $this->language->get('text_purgeSuccess');
            $this->language->set('text_success', $text_success);
        }
    }

    public function editCategory($route, &$args, &$output) {
        $lscInstance = $this->lscacheInit(true);
        if ($lscInstance) {
            $purgeTag = 'Category,C_' . $args[0];
            $lscInstance->purgePublic($purgeTag);
            $this->log($lscInstance->getLogBuffer());
            $this->load->language('extension/litespeed/module/lscache');
            $text_success = $this->language->get('text_success') . '<br><i class="fa fa-check-circle"></i> ' . $this->language->get('text_purgeSuccess');
            $this->language->set('text_success', $text_success);
        }
    }

    public function addInformation($route, &$args, &$output) {
        $lscInstance = $this->lscacheInit(true);
        if ($lscInstance) {
            $purgeTag = 'Information';
            $lscInstance->purgePublic($purgeTag);
            $this->log($lscInstance->getLogBuffer());
            $this->load->language('extension/litespeed/module/lscache');
            $text_success = $this->language->get('text_success') . '<br><i class="fa fa-check-circle"></i> ' . $this->language->get('text_purgeSuccess');
            $this->language->set('text_success', $text_success);
        }
    }

    public function editInformation($route, &$args, &$output) {
        $lscInstance = $this->lscacheInit(true);
        if ($lscInstance) {
            $purgeTag = 'Information,I_' . $args[0];
            $lscInstance->purgePublic($purgeTag);
            $this->log($lscInstance->getLogBuffer());
            $this->load->language('extension/litespeed/module/lscache');
            $text_success = $this->language->get('text_success') . '<br><i class="fa fa-check-circle"></i> ' . $this->language->get('text_purgeSuccess');
            $this->language->set('text_success', $text_success);
        }
    }

    public function addManufacturer($route, &$args, &$output) {
        $lscInstance = $this->lscacheInit(true);
        if ($lscInstance) {
            $purgeTag = 'Manufacturer';
            $lscInstance->purgePublic($purgeTag);
            $this->log($lscInstance->getLogBuffer());
            $this->load->language('extension/litespeed/module/lscache');
            $text_success = $this->language->get('text_success') . '<br><i class="fa fa-check-circle"></i> ' . $this->language->get('text_purgeSuccess');
            $this->language->set('text_success', $text_success);
        }
    }

    public function editManufacturer($route, &$args, &$output) {
        $lscInstance = $this->lscacheInit(true);
        if ($lscInstance) {
            $purgeTag = 'Manufacturer,M_' . $args[0];
            $lscInstance->purgePublic($purgeTag);
            $this->log($lscInstance->getLogBuffer());
            $this->load->language('extension/litespeed/module/lscache');
            $text_success = $this->language->get('text_success') . '<br><i class="fa fa-check-circle"></i> ' . $this->language->get('text_purgeSuccess');
            $this->language->set('text_success', $text_success);
        }
    }

    private function lscacheInit($redirect = false) {

        $this->load->model('extension/litespeed/module/lscache');
        $setting = $this->model_extension_litespeed_module_lscache->getItems();

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

        if (isset($setting['module_lscache_status']) && (!$setting['module_lscache_status'])) {
            return false;
        }

        // Checks if caching is allowed via server variable
        if ( LITESPEED_SERVER_TYPE === 'LITESPEED_SERVER_ADC' || LITESPEED_SERVER_TYPE === 'LITESPEED_SERVER_ENT' || LITESPEED_SERVER_TYPE === 'LITESPEED_SERVER_OLS' || defined('LITESPEED_CLI')) {
            !defined('LITESPEED_ALLOWED') && define('LITESPEED_ALLOWED', true);
            include_once(DIR_EXTENSION . 'litespeed/system/library/lscache/lscachebase.php');
            include_once(DIR_EXTENSION . 'litespeed/system/library/lscache/lscachecore.php');
            $lscInstance = new \LiteSpeedCacheCore();
            if (!$redirect) {
                $lscInstance->setHeaderFunction($this->response, 'addHeader');
            }
            return $lscInstance;
        } else {
            return false;
        }
    }

    private function initHtaccess($mobile = false) {
        $htaccess = realpath(DIR_APPLICATION . '/../') . '/.htaccess';

        $directives = '### LITESPEED_CACHE_START - Do not remove this line' . PHP_EOL;
        $directives .= '<IfModule LiteSpeed>' . PHP_EOL;
        $directives .= 'CacheLookup on' . PHP_EOL;
        $directives .= '## Uncomment the following directives if you has a separate mobile view' . PHP_EOL;
        $directives .= '##RewriteEngine On' . PHP_EOL;
        $directives .= '##RewriteCond %{HTTP_USER_AGENT} "iPhone|iPod|BlackBerry|Palm|Googlebot-Mobile|Mobile|mobile|mobi|Windows Mobile|Safari Mobile|Android|Opera Mini" [NC] ' . PHP_EOL;
        $directives .= '##RewriteRule .* - [E=Cache-Control:vary=isMobile]' . PHP_EOL;

        $directives .= '## Uncomment the following directives if you has a separate Safari browser view' . PHP_EOL;
        $directives .= '##RewriteCond %{HTTP_USER_AGENT} Safari' . PHP_EOL;
        $directives .= '##RewriteCond %{HTTP_USER_AGENT} !Chrome' . PHP_EOL;
        $directives .= '##RewriteCond %{HTTP_USER_AGENT} !CriOS' . PHP_EOL;
        $directives .= '##RewriteRule .* - [E=Cache-Control:vary=isSafari]' . PHP_EOL;

        $directives .= '##RewriteCond %{HTTP_USER_AGENT} "iPhone|iPod|BlackBerry|Palm|Googlebot-Mobile|Mobile|mobile|mobi|Windows Mobile|Safari Mobile|Android|Opera Mini" [NC]' . PHP_EOL;
        $directives .= '##RewriteCond %{HTTP_USER_AGENT} Safari' . PHP_EOL;
        $directives .= '##RewriteCond %{HTTP_USER_AGENT} !Chrome' . PHP_EOL;
        $directives .= '##RewriteCond %{HTTP_USER_AGENT} !CriOS' . PHP_EOL;
        $directives .= '##RewriteRule .* - [E=Cache-Control:vary=isMobileSafari]' . PHP_EOL;

        $directives .= '##RewriteCond %{HTTP_USER_AGENT} "iPhone|iPod|BlackBerry|Palm|Googlebot-Mobile|Mobile|mobile|mobi|Windows Mobile|Safari Mobile|Android|Opera Mini" [NC] ' . PHP_EOL;
        $directives .= '##RewriteRule .* - [E=Cache-Control:vary=isMobile]' . PHP_EOL;
        $directives .= '##RewriteCond %{HTTP_USER_AGENT} Bot' . PHP_EOL;
        $directives .= '##RewriteRule .* - [E=Cache-Control:vary=isMobileBot]' . PHP_EOL;

        $directives .= '</IfModule>' . PHP_EOL;
        $directives .= '### LITESPEED_CACHE_END';

        $pattern = '@### LITESPEED_CACHE_START - Do not remove this line.*?### LITESPEED_CACHE_END@s';

        if (file_exists($htaccess)) {
            $content = file_get_contents($htaccess);
            $newContent = preg_replace($pattern, $directives, $content, -1, $count);

            if ($count <= 0) {
                $newContent = preg_replace('@\<IfModule\ LiteSpeed\>.*?\<\/IfModule\>@s', '', $content);
                $newContent = preg_replace('@CacheLookup\ on@s', '', $newContent);
                file_put_contents($htaccess, $directives . PHP_EOL . $newContent);
            } else if ($count > 0) {
                file_put_contents($htaccess, $newContent);
            } else {
                file_put_contents($htaccess, $directives . PHP_EOL . $newContent);
            }
        } else {
            file_put_contents($htaccess, $directives);
        }
    }

    private function clearHtaccess() {
        $htaccess = realpath(DIR_APPLICATION . '/../') . '/.htaccess';

        if (file_exists($htaccess)) {
            $contents = file_get_contents($htaccess);
            $pattern = '@\n?### LITESPEED_CACHE_START - Do not remove this line.*?### LITESPEED_CACHE_END@s';

            $clean_contents = preg_replace($pattern, '', $contents, -1, $count);

            if ($count > 0) {
                file_put_contents($htaccess, $clean_contents);
            }
        }
    }

    public function log($content = null, $logLevel = self::LOG_INFO) {
        if (empty($content)) {
            return;
        }

        $this->load->model('extension/litespeed/module/lscache');
        $setting = $this->model_extension_litespeed_module_lscache->getItems();

        if (!isset($setting['module_lscache_log_level'])) {
            return;
        }

        $logLevelSetting = $setting['module_lscache_log_level'];
        if (isset($this->session->data['lscacheOption']) && ($this->session->data['lscacheOption'] == "debug")) {
            $this->log->write($content);
            return;
        } else if ($logLevelSetting == self::LOG_DEBUG) {
            return;
        } else if ($logLevel > $logLevelSetting) {
            return;
        }

        $logInfo = "LiteSpeed Cache Info:";
        if ($logLevel == self::LOG_ERROR) {
            $logInfo = "LiteSpeed Cache Error:";
        } else if ($logLevel == self::LOG_DEBUG) {
            $logInfo = "LiteSpeed Cache Debug:";
        }

        $this->log->write($logInfo . $content);
    }

    protected function isCurl() {
        return function_exists('curl_version');
    }

    private function purgeUrls() {
        $url = $this->request->post['lscache_purge_url'];
        if (empty($url) || empty(trim($url))) {
            return;
        }

        $urls = explode("\n", str_replace(array("\r\n", "\r"), "\n", $url));

        $success = 0;
        $acceptCode = array(200, 201);

        $domain = $_SERVER['SERVER_NAME'];
        $host = $_SERVER['SERVER_ADDR'];
        $header = ['Host: ' . $_SERVER['HTTP_HOST']];
        $msg = [];

        foreach ($urls as $key => $path) {

            // Check that URL is in this domain
            if (strpos($path, $domain) === FALSE) {
                $msg[] = $path . ' - url not allowed';
                continue;
            }

            $ch = curl_init();

            // Replace domain with host, and set Header Host, to support Cloudflare or reverse proxies
            $host_path = str_replace($domain, $host, $path);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            curl_setopt($ch, CURLOPT_URL, $host_path);
            curl_setopt($ch, CURLOPT_VERBOSE, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 3);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PURGE");
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

            $buffer = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (in_array($httpcode, $acceptCode)) {
                $success++;
            } else {
                $msg[] = $path . ' - ' . ' purge failed' . $httpcode . curl_error($ch);
            }
            curl_close($ch);
        }

        $msg[] = $success . ' URL purged!';
        return implode('<br>', $msg);
    }

}

final class Tool {

    private $result;
    private $default;

    public function __construct($result = "", $default = "") {
        $this->result = $result;
        $this->default = $default;
    }

    public function check($value, $compare = "1", $attribute = "") {

        if ($value == "") {
            $value = $this->default;
        }

        if ($compare != $value) {
            return "";
        } else if (empty($attribute)) {
            return $this->result;
        } else {
            return $attribute . '="' . $this->result . '"';
        }
    }

}
