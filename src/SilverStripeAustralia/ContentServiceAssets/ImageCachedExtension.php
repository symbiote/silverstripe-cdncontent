<?php

namespace SilverStripeAustralia\ContentServiceAssets;

use ContentService;

/**
 * Services cached resized images from the external source.
 */
class ImageCachedExtension extends \DataExtension {

	private $service;

	public function __construct(ContentService $service) {
		$this->service = $service;
		parent::__construct();
	}

	public function updateURL(&$url) {
		$cached = $this->owner;
		/** @var \Image_Cached $cached */
		$filename = $cached->getFilename();
		
		$storeIn = $this->owner->targetStore();
		if (!$storeIn) {
			return;
		}

		$asset = ContentServiceAsset::get()->filter('Filename', $filename)->first();

		if(!$asset) {
			$asset = new ContentServiceAsset();
			$asset->Filename = $filename;

			$writer = $this->service->getWriterFor($asset, 'FilePointer', $storeIn);
			if ($writer) {
				$mtime = @filemtime($cached->getFullPath());
				$writer->write(fopen($cached->getFullPath(), 'r'), $mtime . '/' . $filename);

				$asset->FilePointer = $writer->getContentId();
				$asset->write();
			} else {
				$asset = null;
			}
			
		}

		if ($asset) {
			$url = $this->service->getReader($asset->FilePointer)->getURL();
		}
	}

}
