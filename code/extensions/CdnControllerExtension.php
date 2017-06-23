<?php

/**
 * @author marcus@symbiote.com.au
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
    
    /**
     *
     * @var AssetUrlConversionFilter
     */
    public $contentFilter;
	
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
			
			// if we want to upload missing files, verify their existence. 
			if (!$verify && $uploadMissing) {
				$verify = true;
			}
			
			$mtime = @filemtime(Director::baseFolder().'/'.$assetPath);
			$timedAssetPath = ContentDeliveryService::CDN_THEME_PREFIX . '/' . $mtime . '/' . $assetPath;
			
			$reader = $this->contentService->findReaderFor($store, $timedAssetPath);
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
					return $this->contentDelivery->storeThemeFile($store, $fullPath, false, true);
				}

				// otherwise just upload
				$writer = $this->getWriter();
				// otherwise, we need to write the file
				
				$writer->write(Director::baseFolder().'/'.$assetPath, $timedAssetPath);

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
    
    public function beforeCallActionHandler($request, $action) {
        if (
            $this->owner instanceof LeftAndMain ||
            $this->owner instanceof TaskRunner ||
            $this->owner instanceof Security ||
            $this->owner instanceof DevelopmentAdmin ||
			$this->owner instanceof DevBuildController ||
			$this->owner instanceof DatabaseAdmin) {
			return;
		}
        $this->contentFilter->setConvertUrls(true);
    }
}
