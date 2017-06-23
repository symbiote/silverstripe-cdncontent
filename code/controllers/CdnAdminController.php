<?php

/**
 * @author marcus@symbiote.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class CdnAdminController extends ModelAdmin {
	private static $managed_models = array(
		'ThemeCdn',
	);
	
	private static $url_segment = 'themecdn';
	
	private static $menu_title = 'Theme CDN';
	
	
}
