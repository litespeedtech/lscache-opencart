<?php

/* 
 *  @since      1.0.0
 *  @author     LiteSpeed Technologies <info@litespeedtech.com>
 *  @copyright  Copyright (c) 2017-2018 LiteSpeed Technologies, Inc. (https://www.litespeedtech.com)
 *  @license    https://opensource.org/licenses/GPL-3.0
 */

$_['heading_title']    = 'LiteSpeed Cache';
 
$_['text_success']     = 'Success: You have modified "LiteSpeed Cache" extension!';
$_['text_curl_not_support']     = ',but curl feature not supported in this web server';
$_['text_edit']        = 'LiteSpeed Cache Settings';
$_['text_module']        = 'Extensions';
$_['text_action'] = 'Action';
$_['text_error']        = 'Errors Only';
$_['text_info']        = 'All Information';
$_['text_debug'] = 'Debug Current Session';
$_['text_show_default'] = 'Show Default Settings';
$_['text_purgeSuccess']     = 'Informed LiteSpeed Server to purge all related cached pages successfully!';
$_['text_purgeModule']     = 'Informed LiteSpeed Server to purge related cached ESI module successfully!';
$_['text_default'] = 'Default';
$_['text_Cart'] = 'Cart';
$_['text_Login'] = 'Login';
$_['text_duplicate_route'] = 'The new setting has a duplicate route already exists';
$_['text_exclude_route'] = 'The new setting is in exclude route list';
$_['text_recache_default'] = 'Rebuild cache for only default language and default currency';
$_['text_recache_language'] = 'Rebuild cache for all languages and only default currency';
$_['text_recache_currency'] = 'Rebuild cache for all currencies and only default language';
$_['text_recache_combination'] = 'Rebuild cache for all language and currency cobinations';
$_['text_commentHtaccess'] = 'Please comment/uncomment related .htaccess directives inside &lt;IfModule LiteSpeed&gt;';


$_['entry_status']     = 'LiteSpeed Cache Status';
$_['entry_esi']     = 'LiteSpeed ESI Feature';
$_['entry_public_ttl']     = 'LiteSpeed Cache TTL (seconds)';
$_['entry_loglevel']     = 'Logging Level';
$_['entry_login_cachable']     = 'Page Cache for Logged-in Users';
$_['entry_vary_mobile']     = 'Separate View for Mobile Device';
$_['entry_vary_safari']     = 'Separate View for Safari Browser';
$_['entry_vary_login']     = 'Seperate View for Logged-in Users';
$_['entry_purge_system_cache']     = 'Purge System Cache';
$_['entry_ajax_wishlist']     = 'Ajax Load Wishlist';
$_['entry_ajax_compare']     = 'Ajax Load Compare';
$_['entry_ajax_shopcart']     = 'Ajax Load Shopcart';
$_['entry_recache_option']     = 'Rebuild Cache Options';
$_['entry_recache_userAgent']     = 'Rebuild Cache for specific devices/browsers';
$_['entry_include_urls']     = 'Include URLs';
$_['entry_exclude_login_urls']     = 'Exclude URLs for logged-in users';
$_['entry_exclude_urls']     = 'Exclude URLs';
$_['entry_purge_urls']     = 'Purge URLs';
$_['entry_purge_category']     = 'Purge Category Cache on Product Save';
$_['entry_lscache_cache404']     = 'Cache 404 Pages';

$_['help_public_ttl']     = 'LiteSpeed page cache lifetime in seconds';
$_['help_login_cachable']     = 'If disabled, all web page cache will not be available for logged-in users';
$_['help_page_login_cachable']     = 'Page Cache for logged-in users';
$_['help_page_logout_cachable']     = 'Page Cache for logged-out users';
$_['help_vary_mobile']     = 'Create a separate cached copy of each page for mobile devices, please check .htaccess file and comment/uncomment mobile view part according to this option';
$_['help_vary_safari']     = 'Create a separate cached copy of each page for Safari browser, please check .htaccess file and comment/uncomment Safari view part according to this option';
$_['help_vary_login']     = 'Create a separate cached copy of each page for Logged-in Users';
$_['help_recache_userAgent']  = 'Input User Agent of your device browser,  one User Agent per line';
$_['hint_include_urls'] = "Please input one URL per line. \neg: \n/index.php?route=product/product&product_id=40";
$_['hint_exclude_login_urls'] = "Please input one URL per line. \neg: \n/account/register";
$_['hint_exclude_urls'] = "Please input one URL per line. \neg: \n/product/list/latest";
$_['hint_purge_urls'] = "Please input one full URL per line. \neg: \nhttps://www.your.site/product/nostock/item1";
$_['help_purge_category']     = 'Purge Product Category cache after saving an Product';

$_['help_purge_system_cache']     = 'Purge all Opencart system cache after purge all LiteSpeed Cache';
$_['help_esi_ttl']     = 'ESI module cache lifetime in seconds';
$_['help_esi_tag']     = 'ESI module cache will be purged on esi tag related event';
$_['help_include_urls']     = 'It will override page settings';
$_['help_exclude_login_urls']     = 'It will override page settings and Include URLs rule';
$_['help_exclude_urls']     = 'It will override page settings and other URL rules';

$_['error_permission'] = 'Warning: You do not have permission to modify "LiteSpeed Cache" module!';
$_['button_purgeAll'] = 'Purge All LiteSpeed Cache';
$_['button_purgePage'] = 'Purge LiteSpeed Cache of this page route';
$_['button_purgeESI'] = 'Purge LiteSpeed Cache of this module';
$_['button_recacheAll'] = 'Rebuild All LiteSpeed Cache';
$_['button_addModule'] = 'Add New ESI Module';
$_['button_addRoute'] = 'Add New ESI Route';
$_['button_deleteModule'] = 'Delete Module Setting';
$_['button_deletePage'] = 'Delete Page Setting';

$_['tab_general'] = 'General';
$_['tab_pages'] = 'Page Settings';
$_['tab_urls'] = 'URL Settings';
$_['tab_advanced'] = 'Advanced';
$_['tab_modules'] = 'ESI Modules';

$_['page_name'] = 'Page Name';
$_['page_route'] = 'Page Route';
$_['page_cachable'] = 'Page Cache ( Logout )';
$_['login_cachable'] = 'Page Cache ( Login )';
$_['extension_code'] = 'Extension Code';
$_['esi_name'] = 'ESI Module Name';
$_['esi_route'] = 'ESI Module Route';
$_['esi_type'] = 'ESI Type';
$_['esi_ttl'] = 'ESI TTL (second)';
$_['esi_tag'] = 'ESI Tag';
$_['esi_not_support'] = 'ESI feature is not support in this Web Server, please upgrade to LiteSpeed Enterprice';

$_['esi_public']     = 'ESI with public cache';
$_['esi_private']    = 'ESI with private cache';
$_['esi_none']       = 'ESI without cache';
$_['esi_disabled'] = 'ESI Disabled';