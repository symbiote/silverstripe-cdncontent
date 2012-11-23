<?php

/**
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class CdnAdminController extends ModelAdmin {
	public static $managed_models = array(
		'ThemeCdn',
	);
	
	public static $url_segment = 'themecdn';
	
	public static $menu_title = 'Theme CDN';
	
	
}
