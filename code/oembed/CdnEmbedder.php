<?php

/**
 *
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class CdnEmbedder extends Oembed {
	public static function handle_shortcode($arguments, $url, $parser, $shortcode) {
		// get all registered cdn things, and see if any of them have URLs that 
		// need to be handled by US, instead of looked up. _if_ we need to handle it,
		// perform relevant transforms etc. 
		$contentService = singleton('ContentService');
		/* @var $contentService ContentService */
		$cdns = $contentService->getStoreTypes();
		
		foreach ($cdns as $name => $types) {
			$reader = $contentService->getReader($name);
			if ($actualReader = $reader->providerOfUrl($url)) {
				if ($actualReader instanceof ContentReader) {
					$contentId = $actualReader->getContentId();
					$file = File::get()->filter('CDNFile', $contentId)->first();
					
					if ($file) {
						if ($file instanceof Image && isset($arguments['width']) && isset($arguments['height'])) {
							// return the formatted image
							$cached = $file->ResizedImage($arguments['width'], $arguments['height']);
							if ($cached) {
								return $cached->forTemplate();
							}
						}
					}
				}
			}
		}
		
		return parent::handle_shortcode($arguments, $url, $parser, $shortcode);
	}
}
