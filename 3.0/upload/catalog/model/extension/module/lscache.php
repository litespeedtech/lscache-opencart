<?php
class ModelExtensionModuleLSCache extends Model {
	public function getSetting($code, $store_id = 0) {
		$setting_data = array();

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE store_id = '" . (int)$store_id . "' AND `code` = '" . $this->db->escape($code) . "'");

		foreach ($query->rows as $result) {
			if (!$result['serialized']) {
				$setting_data[$result['key']] = $result['value'];
			} else {
				$setting_data[$result['key']] = json_decode($result['value'], true);
			}
		}

		return $setting_data;
	}

	
	public function getSettingValue($code, $key, $store_id = 0) {

        $query = $this->db->query("SELECT value FROM " . DB_PREFIX . "setting WHERE store_id = '" . (int)$store_id ."' AND `code` = '" . $this->db->escape($code) . "' AND `key` = '" . $this->db->escape($key) . "'");

		if ($query->num_rows) {
			return $query->row['value'];
		} else {
			return null;
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
            if($value['esi_type']>0){
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
		'product/compare',
		'error/not_found');
        
        return in_array($router, $excludeRoutes);
        
    }
    
}
