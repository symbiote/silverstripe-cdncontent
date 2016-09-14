<?php

/**
 * Provides an interface to content delivery networks
 * 
 * @author marcus@silverstripe.com.au
 * @license BSD License http://silverstripe.org/bsd-license/
 */
class ContentDeliveryService {

    const CDN_THEME_PREFIX = 'cdntheme';
    
	/**
	 * @var ContentService
	 */
	public $contentService;
	
	
	public static $dependencies = array(
		'contentService'		=> '%$ContentService'
	);
	
	/**
	 * Get the first available CDN for a given theme
	 * 
	 * @param string $theme
	 * @return ThemeCdn
	 */
	public function getCdnForTheme($theme) {
		$cdn = ThemeCdn::get()->filter('Theme', $theme);
		return $cdn->first();
	}
	
	/**
	 * Store the contents of a folder on a CDN. 
	 * 
	 * If processReferences is set, relative URL references are attempted to be 
	 * detected and stored remotely as well, with the file to be stored rewritten 
	 * to refer to the CDN value. This really is only useful for CSS 
	 *
	 * @param string $folder
	 * @param boolean $processReferences 
	 */
	public function storeThemeFile($toCdn, $file, $forceUpdate = false, $processReferences = false) {
		$mtime = @filemtime($file);
		$relativeName = self::CDN_THEME_PREFIX . '/' . $mtime . '/' . trim(str_replace(Director::baseFolder(), '', $file), '/');
		
		if (!$forceUpdate) {
			// see if the file already exists, if not we do NOT do an update
			$reader = $this->contentService->findReaderFor($toCdn, $relativeName);
			if ($reader && $reader->exists()) {
				return $reader->getURL();
			}
		}

		$clear = false;
		if ($processReferences) {
			$clear = true;
			$file = $this->processFileReferences($toCdn, $file, $forceUpdate);
		}

		// otherwise, lets get a content writer
		$writer = $this->contentService->getWriter($toCdn);
		try {
			$writer->write($file, $relativeName);
		} catch (Exception $e) {
			SS_Log::log($e, SS_Log::WARN);
		}

		if ($clear && strpos($file, '.cdn') > 0) {
			@unlink($file);
		}

		$id = $writer->getContentId();
		return $writer->getReader()->getURL();
	}
	
	protected function processFileReferences($toCdn, $file, $forceUpdate = false) {
		$content = file_get_contents($file);
		
		$processed = array();
		
		if (preg_match_all('/url\((.*?)\)/', $content, $matches)) {
			foreach ($matches[1] as $segment) {
				$segment = trim($segment, '\'"');
				
				if (strpos($segment, '#') !== false) {
					$segment = substr($segment, 0, strpos($segment, '#'));
				}
				
				if (isset($processed[$segment])) {
					continue;
				}

				if (strpos($segment, '//') !== false  || $segment{0} == '/') {
					continue;
				}

				$realPath = realpath(dirname($file) .'/' . $segment);
				if (!strlen($realPath) || !file_exists($realPath)) {
					continue;
				}

				$replacement = $this->storeThemeFile($toCdn, $realPath, $forceUpdate);
				
				$content = str_replace($segment, $replacement, $content);
				$processed[$segment] = $replacement;
			}
		}

		if (count($processed)) {
			// we need to upload a temp version of the file
			$file = $file . '.cdn';
			file_put_contents($file, $content);
		}
		
		return $file;
	}
}
