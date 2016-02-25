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
                if (file_exists($cached->getFullPath())) {
                    // likely that cached image never got built correctly. 
                    $writer->write(fopen($cached->getFullPath(), 'r'), $mtime . '/' . $filename);

                    $asset->FilePointer = $writer->getContentId();
                    $asset->write();
                } else {
                    $asset = null;
                }
			} else {
				$asset = null;
			}
		}

		if ($asset) {
			$pointer = $asset->FilePointer;
			$reader = $this->service->getReader($asset->FilePointer);

			if ($reader) {
				if ($this->owner->CanViewType) {
					if($this->owner->hasMethod('canView') && !$this->owner->canView()) {
						return;
					} else {
						$url  = $reader->getSecureURL();
					}
				} else {
					$url = $reader->getURL();
				}
			}
		}
	}

}
