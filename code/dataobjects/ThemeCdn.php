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

	/**
	 * @var ContentDeliveryService
	 */
	public $contentDelivery;
	
	/**
	 * @var ContentService
	 */
	public $contentService;

	public function getCMSFields() {
		$fields = parent::getCMSFields();
		
		$fields->replaceField('LastSync', new ReadonlyField('LastSync', _t('ThemeCdn.LAST_SYNC', 'Last Sync')));
		
		$config = SiteConfig::current_site_config();
		
		$themes = $config->getAvailableThemes();
		$themes = array_merge(array('' => ''), $themes);
		$fields->replaceField('Theme', new DropdownField('Theme', _t('ThemeCdn.THEME', 'Theme'), $themes));
		
		if ($this->Theme) {
			// $fields->addFieldToTab('Root.Main', new MultiValue
			$fields->replaceField('Files', MultiValueCheckboxField::create('Files', _t('ThemeCdn.FILES', 'Files'), $this->getThemeFiles()));
		}

		$stores = $this->contentService->getStoreTypes();
		if (count($stores)) {
			$default = array('' => 'No CDN');
			$stores = array_merge($default, array_combine(array_keys($stores), array_keys($stores)));
			$fields->replaceField('StoreIn', DropdownField::create('StoreIn', 'CDN', $stores));
		}
		
		return $fields;
	}
	
	public function onBeforeWrite() {
		parent::onBeforeWrite();
		
		if ($this->Files) {
			$files = $this->Files->getValues();
            if (is_array($files)) {
                foreach ($files as $file) {
                    // make sure we're a safe name
                    $file = realpath($this->themeBase() . '/' . $file);
                    if (!file_exists($file)) {
                        throw new Exception("Invalid, possibly malicious, file reference $file");
                    }
                }
            }
		}
		
		if ($this->SyncNow) {
			$this->sync();
		}

		$this->ForceResync = false;
	}
	
	public function sync() {
		if ($this->Files && count($this->Files)) {
			foreach ($this->Files->getValues() as $file) {
				$full = realpath($this->themeBase() . '/' . $file);
				$this->contentDelivery->storeThemeFile($this->StoreIn, $full, $this->ForceResync, strpos($full, '.css') > 0);
			}
		}

		$this->LastSync = date('Y-m-d H:i:s');
		$this->SyncNow = false;
	}
	
	public function getThemeFiles($theme = null) {
		$base = $this->themeBase($theme);

		$fileList = array();
		if (is_dir($base)) {
			$files = glob($base . '/*/*');
			foreach ($files as $file) {
				$file = str_replace($base .'/', '', $file);
				$fileList[$file] = $file;
			}
		}
		
		return $fileList;
	}
	
	protected function themeBase($theme = null) {
		if (!$theme) {
			$theme = $this->Theme;
		}
		return Director::baseFolder() . '/' . THEMES_DIR . '/' . $theme;
	}
}
