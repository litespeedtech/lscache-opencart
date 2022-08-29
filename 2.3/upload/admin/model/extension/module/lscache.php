<?php

class ModelExtensionModuleLSCache extends Model {
    
	public function editSetting($code, $data, $store_id = 0) {
        
		$query = $this->db->query("SELECT `key`, `value` FROM " . DB_PREFIX . "setting WHERE store_id = '" . (int)$store_id . "' AND `code` = '" . $this->db->escape($code) . "' AND (`key` like 'page%' or `key` like 'esi%')" );
        
        $settings = array();
        foreach($query->rows as $row){
            $key = $row['key'];
            $value = json_decode($row['value']);
            if(substr($key, 0, 4)=='page'){
                $value->cacheLogin = "0";
                $value->cacheLogout = "0";
            }
            $settings[$key] = $value;
        }
        
        foreach ($data as $key => $value) {
            if((substr($key, 0, 4)=='page') || (substr($key, 0, 3)=='esi')){
                list($field, $property) = explode('-', $key);
                if(!isset($settings[$field]) && (substr($key, 0, 3)=='esi')){
                    $settings[$field] = new stdClass();
                } else if(!isset($settings[$field]) && (substr($key, 0, 4)=='page')){
                    $settings[$field] = new stdClass();
                    $settings[$field]->cacheLogin = "0";
                    $settings[$field]->cacheLogout = "0";
                }
                $settings[$field]->$property = trim($value) ;
            } else if(substr($key, 0, 6)=='module') {
               $settings[$key] = trim($value);
            }
        }
        
		$this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE store_id = '" . (int)$store_id . "' AND `code` = '" . $this->db->escape($code) . "'");

        foreach ($settings as $key => $value) {
            if (!is_object($value)) {
                $this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '" . (int)$store_id . "', `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape($value) . "'");
                continue;
            }
            
            if($key=='page_add'){
                $key = 'page_' . str_replace('/', '_', $value->route);
                $value->default = '0';
                if($this->isRouterExclude($value->route)){
                    $this->session->data['error'] = $this->language->get("text_exclude_route");
                    continue;
                } else if(array_key_exists($key, $settings) ) {
                    $this->session->data['error'] = $this->language->get("text_duplicate_route");
                    continue;
                }
            }
            else if ($key == 'esi_add'){
                if(!empty($value->module)){
                    $module = $this->getModule($value->module);
                    if(!$module){
                        $value->name = $value->module;
                        $value->route = 'extension/module/' . $value->module;
                        $key = 'esi_module_' . $value->module;
                    } else {
                        $value->name = $module["code"] . '/' . $module["name"] ;
                        $value->route = 'extension/module/' . $module["code"];
                        $key = 'esi_extension_module_' . $module["code"] . '_' .$value->module;
                    }
                }
                else{
                    $key = 'esi_' . str_replace('/', '_', $value->route);
                }
                if(array_key_exists($key, $settings) ) {
                    $this->session->data['error'] = $this->language->get("text_duplicate_route");
                    continue;
                }
            }
            
            $this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '" . (int)$store_id . "', `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape(json_encode($value, true)) . "', serialized = '1'");
		}
        
        $this->cache->delete('lscache_pages');
        $this->cache->delete('lscache_modules');
        $this->cache->delete('lscache_esi_modules');
        $this->cache->delete('lscache_itemes');
        
	}
    
	public function deleteSettingItem($code, $key, $store_id = 0) {
		$this->db->query("DELETE FROM " . DB_PREFIX . "setting WHERE store_id = '" . (int)$store_id . "' AND `code` = '" . $this->db->escape($code) .  "' AND `key` = '" . $this->db->escape($key) . "'");
        if(substr($key, 0, 4)=='page'){
            $this->cache->delete('lscache_pages');
        }
        else if(substr($key, 0, 3)=='esi'){
            $this->cache->delete('lscache_modules');
            $this->cache->delete('lscache_esi_modules');
        }
        else{
            $this->cache->delete('lscache_itemes');
        }
	}
    
	public function getSettingItemValue($code, $key, $store_id = 0) {
		$query = $this->db->query("SELECT value FROM " . DB_PREFIX . "setting WHERE store_id = '" . (int)$store_id ."' AND `code` = '" . $this->db->escape($code) . "' AND `key` = '" . $this->db->escape($key) . "'");

		if ($query->num_rows) {
			return $query->row['value'];
		} else {
			return null;
		}
	}
    
	public function getESIExtensionOptions($store_id = 0){
		$query = $this->db->query("SELECT code FROM " . DB_PREFIX . "extension WHERE `type`='module' and `code`!='lscache' and `code` not in (select code from " . DB_PREFIX . "module) and concat('esl_extension_module_',`code`) not in (select `key` from "  . DB_PREFIX . "setting where store_id = '" . (int)$store_id . "' and `code`='module_lscache')");

		if ($query->num_rows) {
			return $query->rows;
		} else {
			return array();
		}
    }

    
	public function getESIModuleOptions($store_id = 0){
		$query = $this->db->query("SELECT module_id, name, code FROM " . DB_PREFIX . "module WHERE  concat('esl_extension_module_',`code`, '.', `module_id`) not in (select `key` from "  . DB_PREFIX . "setting where store_id = '" . (int)$store_id . "' and `code`='module_lscache')");

		if ($query->num_rows) {
			return $query->rows;
		} else {
			return array();
		}
    }

    
	private function getModule($module_id) {
		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "module` WHERE `module_id` = '" . (int)$module_id . "'");

		if ($query->row) {
			return $query->row;
		} else {
			return false;
		}
	}

    
    public function getESIModules($store_id = 0){
        $setting_data = $this->cache->get('lscache_esi_modules');
        if($setting_data){
            return $setting_data;
        }

        $setting_data = array();

		$query = $this->db->query("SELECT `key`, `value` FROM " . DB_PREFIX . "setting WHERE store_id = '" . (int)$store_id . "' AND `code` = 'module_lscache' AND `key` like 'esi%'");

		foreach ($query->rows as $result) {
            $value = json_decode($result['value'], true);
            if($value->esl_type>0){
                $setting_data[$result['key']] = $value;
            }
		}
		ksort($setting_data);
        return $setting_data;
    }

    
    public function getPages($store_id = 0){
        $setting_data = $this->cache->get('lscache_pages');
        if($setting_data){
            return $setting_data;
        }

        $setting_data = array();

		$query = $this->db->query("SELECT `key`, `value` FROM " . DB_PREFIX . "setting WHERE store_id = '" . (int)$store_id . "' AND `code` = 'module_lscache' AND `key` like 'page%'");

		foreach ($query->rows as $result) {
            $value = json_decode($result['value'], true);
            $value['key'] = $result['key'];
			$setting_data[$result['key']] = $value; 
		}

        $this->cache->set('lscache_pages', $setting_data);

        return $setting_data;
    }


    public function getModules($store_id = 0){
        $setting_data = $this->cache->get('lscache_modules');
        if($setting_data){
            return $setting_data;
        }
        
		$setting_data = array();

		$query = $this->db->query("SELECT `key`, `value` FROM " . DB_PREFIX . "setting WHERE store_id = '" . (int)$store_id . "' AND `code` = 'module_lscache' AND `key` like 'esi%'");

		foreach ($query->rows as $result) {
            $value = json_decode($result['value'], true);
            $value['key'] = $result['key'];
			$setting_data[$result['key']] = $value;
		}
        
        $this->cache->set('lscache_modules', $setting_data);

		return $setting_data;
    }

    
    public function getItems($store_id = 0){
        
        $setting_data = $this->cache->get('lscache_itemes');
        if($setting_data){
            return $setting_data;
        }

        $setting_data = array();

		$query = $this->db->query("SELECT `key`, `value`, `serialized` FROM " . DB_PREFIX . "setting WHERE store_id = '" . (int)$store_id . "' AND `code` = 'module_lscache' AND `key` not like 'esi%' AND `key` not like 'page%'");

		foreach ($query->rows as $result) {
			if (!$result['serialized']) {
				$setting_data[$result['key']] = $result['value'];
			} else {
				$setting_data[$result['key']] = json_decode($result['value'], true);
			}
		}

		return $setting_data;
        
    }
    
    
	public function  installLSCache() {
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'page_common_home', '{\"name\":\"Home Page\",\"route\":\"common\\/home\",\"cacheLogout\":\"1\",\"cacheLogin\":\"0\", \"default\":\"1\"}', '1')" ) ;
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'page_product_category', '{\"name\":\"Product Category\",\"route\":\"product\\/category\",\"cacheLogout\":\"1\",\"cacheLogin\":\"1\", \"default\":\"1\" }', '1')" ) ;
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'page_product_product', '{\"name\":\"Product Detail\",\"route\":\"product\\/product\",\"cacheLogout\":\"1\",\"cacheLogin\":\"1\", \"default\":\"1\"}', '1')" ) ;
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'page_information_information', '{\"name\":\"Information\",\"route\":\"information\\/information\",\"cacheLogout\":\"1\",\"cacheLogin\":\"1\", \"default\":\"1\"}', '1')" ) ;
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'page_information_contact', '{\"name\":\"Contact Us\",\"route\":\"information\\/contact\",\"cacheLogout\":\"1\",\"cacheLogin\":\"1\", \"default\":\"1\"}', '1')" ) ;
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'page_information_sitemap', '{\"name\":\"Site Map\",\"route\":\"information\\/sitemap\",\"cacheLogout\":\"1\",\"cacheLogin\":\"1\", \"default\":\"1\" }', '1')" ) ;
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'page_product_manufacturer', '{\"name\":\"Brands\",\"route\":\"product\\/manufacturer\",\"cacheLogout\":\"1\",\"cacheLogin\":\"1\", \"default\":\"1\"}', '1')" ) ;
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'page_journal3_blog_post', '{\"name\":\"Journal3 Blog Post\",\"route\":\"journal3\\/blog\\/post\",\"cacheLogout\":\"1\",\"cacheLogin\":\"1\", \"default\":\"1\"}', '1')" ) ;
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'page_journal3_blog_feed', '{\"name\":\"Journal3 Blog Feed\",\"route\":\"journal3\\/blog\\/feed\",\"cacheLogout\":\"1\",\"cacheLogin\":\"1\", \"default\":\"1\"}', '1')" ) ;
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'page_product_manufacturer_info', '{\"name\":\"Journal3 Brands\",\"route\":\"product\\/manufacturer\\/info\",\"cacheLogout\":\"1\",\"cacheLogin\":\"1\", \"default\":\"1\"}', '1')" ) ;
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'esi_common_cart', '{\"name\":\"cart\",\"route\":\"common\\/cart\",\"esi_type\":\"2\",\"esi_ttl\":\"1800\",\"esi_tag\":\"esi_cart\", \"default\":\"1\"}', '1')") ;
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'esi_common_header', '{\"name\":\"header\",\"route\":\"common\\/header\",\"esi_type\":\"0\",\"esi_ttl\":\"1800\",\"esi_tag\":\"esi_common_header\", \"default\":\"1\"}', '1')") ;
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'esi_common_footer', '{\"name\":\"footer\",\"route\":\"common\\/footer\",\"esi_type\":\"0\",\"esi_ttl\":\"360000\",\"esi_tag\":\"esi_common_footer\", \"default\":\"1\"}', '1')");        
        
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'module_lscache_status', '1', '0')") ;
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'module_lscache_public_ttl', '1200000', '0')") ;
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'module_lscache_esi', '1', '0')") ;
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'module_lscache_log_level', '0', '0')") ;
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'module_lscache_recache_option', '0', '0')") ;
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'module_lscache_ajax_wishlist', '1', '0')") ;
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'module_lscache_ajax_compare', '0', '0')") ;
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'module_lscache_ajax_shopcart', '0', '0')") ;
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'module_lscache_vary_login', '0', '0')") ;
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'module_lscache_vary_mobile', '0', '0')") ;
        $this->db->query(" insert into " . DB_PREFIX . "setting (store_id, code, `key`, value, serialized) values ('0', 'module_lscache', 'module_lscache_vary_safari', '0', '0')") ;

        $this->cache->delete('lscache_pages');
        $this->cache->delete('lscache_modules');
        $this->cache->delete('lscache_esi_modules');
        $this->cache->delete('lscache_itemes');
        
    }

    
	public function  uninstallLSCache() {
		$this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE store_id = '0' AND `code` = 'module_lscache'" );
        
        $this->cache->delete('lscache_pages');
        $this->cache->delete('lscache_modules');
        $this->cache->delete('lscache_esi_modules');
        $this->cache->delete('lscache_itemes');
        
    }
    
    public function isRouterExclude($router){
        
        $excludeRoutes = array(
		'checkout/cart', 
		'checkout/checkout',
		'checkout/success',
		'account/register',
		'account/login',
		'account/edit',
		'account/account',
		'account/password',
		'account/address',
		'account/address/update',
		'account/address/delete',
		'account/wishlist',
		'account/order',
		'account/download',
		'account/return',
		'account/return/insert',
		'account/reward',
		'account/voucher',
		'account/transaction',
		'account/newsletter',
		'account/logout',
		'affiliate/login',
		'affiliate/register',
		'affiliate/account',
		'affiliate/edit',
		'affiliate/password',
		'affiliate/payment',
		'affiliate/tracking',
		'affiliate/transaction',
		'affiliate/logout',
		'information/contact',
		'product/compare',
		'error/not_found');
        
        return in_array($router, $excludeRoutes);
        
    }
    
    
}
