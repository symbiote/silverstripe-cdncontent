<?php

/**
 * Wrapper around configuration for storing theme files in a CDN
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class ThemeCdn extends DataObject {
	private static $db = array(
		'Title'				=> 'Varchar',
		'Theme'				=> 'Varchar',
		'StoreIn'			=> 'Varchar',			// identifier of the CDN to store content into
		'LastSync'			=> 'SS_Datetime',
		'SyncNow'			=> 'Boolean',
		'ForceResync'		=> 'Boolean',
		'Files'				=> 'MultiValueField',
		
	);

	private static $summary_fields = array(
		'Title',
		'Theme',
		'LastSync',
	);
	
	private static $searchable_fields = array(
		'Title'
	);
	
	private static $dependencies = array(
		'contentDelivery'		=> '%$ContentDeliveryService',
	);

	public $contentDelivery;

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		
		$fields->replaceField('LastSync', new ReadonlyField('LastSync', _t('ThemeCdn.LAST_SYNC', 'Last Sync')));
		
		$config = SiteConfig::current_site_config();
		
		$themes = $config->getAvailableThemes();
		$themes = array_merge(array('' => ''), $themes);
		$fields->replaceField('Theme', new DropdownField('Theme', _t('ThemeCdn.THEME', 'Theme'), $themes));
		
		if ($this->Theme) {
			// $fields->addFieldToTab('Root.Main', new MultiValue
			$fields->replaceField('Files', new MultiValueCheckboxField('Files', _t('ThemeCdn.FILES', 'Files'), $this->getThemeFiles()));
		}
		
		return $fields;
	}
	
	public function onBeforeWrite() {
		parent::onBeforeWrite();
		if ($this->SyncNow) {
			$this->sync();
		}

		$this->ForceResync = false;
	}
	
	public function sync() {
		if ($this->Files && count($this->Files)) {
			foreach ($this->Files->getValues() as $file) {
				$this->contentDelivery->storeThemeFile($file, $this->ForceResync, strpos($file, '.css') > 0);
			}
		}

		$this->LastSync = date('Y-m-d H:i:s');
		$this->SyncNow = false;
	}
	
	public function getThemeFiles($theme = null) {
		if (!$theme) {
			$theme = $this->Theme;
		}
		$base = Director::baseFolder() . '/' . THEMES_DIR . '/' . $theme;

		$fileList = array();
		if (is_dir($base)) {
			$files = glob($base . '/*/*');
			foreach ($files as $file) {
				$fileList[$file] = $file;
			}
		}
		
		return $fileList;
	}
}
