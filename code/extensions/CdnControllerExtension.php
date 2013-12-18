<?php

/**
 * 
 *
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class CdnControllerExtension extends Extension {

	static $store_type = 'File';
	
	/**
	 *
	 * @var ContentDeliveryService
	 */
	public $contentDelivery;
	
	/**
	 *
	 * @var ContentService 
	 */
	public $contentService;
	
	protected $currentCdn;

	public function requireCDN($assetPath, $uploadMissing = false, $verify = false) {
		// return the cdn URL for the given asset
		$type = strpos($assetPath, '.css')  ? 'css' : 'js';
		switch ($type) {
			case 'css': 
				Requirements::css($this->CDNPath($assetPath, $uploadMissing, $verify));
				break;
			case 'js': 
				Requirements::javascript($this->CDNPath($assetPath, $uploadMissing, $verify));
				break;
		}
		
	}
	
	public function currentThemeCdn() {
		if (!$this->currentCdn) {
			$this->currentCdn = $this->contentDelivery->getCdnForTheme(Config::inst()->get('SSViewer', 'theme'));
		}
		
		return $this->currentCdn;
	}

	public function CDNPath($assetPath, $uploadMissing = false, $verify = false) {
		$current = $this->currentThemeCdn();
		if ($current && (Director::isLive() || (isset($_GET['stage']) && $_GET['stage'] == 'Live'))) {
			$store = $current->StoreIn;
			
			$reader = $this->contentService->findReaderFor($store, $assetPath);
			if ($reader && (!$verify || $reader->exists())) {
				return $reader->getURL();
			}

			if ($uploadMissing) {
				if (strpos($assetPath, '.css')) {
					// if we're a relative path, make absolute
					$fullPath = $assetPath;
					if ($assetPath{0} != '/') {
						$fullPath = Director::baseFolder().'/' . $assetPath;
					}
					if (!file_exists($fullPath)) {
						return $assetPath;
					}
					// upload all references too
					return $this->contentDelivery->storeThemeFile($current->Theme, $fullPath, false, true);
				}

				// otherwise just upload
				$writer = $this->getWriter();
				// otherwise, we need to write the file
				$writer->write(Director::baseFolder().'/'.$assetPath, $assetPath);

				return $writer->getReader()->getURL();
			}
		}
		return $assetPath;
	}

	/**
	 * @return ContentWriter
	 */
	protected function getWriter() {
		$current = $this->currentThemeCdn();
		$writer = $this->contentService->getWriter($current->StoreIn);
		if (!$writer) {
			throw new Exception("Invalid writer type " . $current->StoreIn);
		}
		return $writer;
	}
}
