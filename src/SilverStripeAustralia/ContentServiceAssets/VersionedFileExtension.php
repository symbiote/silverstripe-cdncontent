<?php

namespace SilverStripeAustralia\ContentServiceAssets;

use ContentService;
use DirectoryIterator;
use RegexIterator;
use UnexpectedValueException;

/**
 * Changes the external content file when the version is changed.
 */
class VersionedFileExtension extends \DataExtension {

	private $service;

	public function __construct(ContentService $service) {
		$this->service = $service;
		parent::__construct();
	}

	public function onAfterWrite() {
		if(!$this->owner->isChanged('CurrentVersionID')) {
			return;
		}
		
		if (!$this->owner->hasExtension('CDNFile')) {
			return;
		}

		// If there's only a single version, it must have just been created.
		if(count($this->owner->Versions()) == 1) {
			return;
		}

		$this->owner->uploadToContentService();
		$this->uploadCachedImages();
	}

	protected function uploadCachedImages() {
		$store = $this->owner->targetStore();
		if (!$store) {
			return;
		}
		$iterator = null;
		$dir = dirname($this->owner->getFullPath()) . '/_resampled';

		try {
			$iterator = new DirectoryIterator($dir);
		} catch(UnexpectedValueException $e) {
			return;
		}

		$regex = sprintf('/([a-z]+)([0-9]?[0-9a-f]*)-%s/i', preg_quote($this->owner->Name, '/'));
		$iterator = new RegexIterator($iterator, $regex, RegexIterator::MATCH);

		foreach($iterator as $item) {
			$fullPath = "$dir/$item";
			$path = dirname($this->owner->getFilename()).'/_resampled/' . $item;
			$asset = ContentServiceAsset::get()->filter('Filename', $path)->first();

			if(!$asset) {
				$asset = new ContentServiceAsset();
				$asset->Filename = $path;
			}

			$writer = $this->service->getWriterFor($asset, 'FilePointer', $store);
			$writer->write(fopen($fullPath, 'r'), $path);
			
			if ($asset->ID <= 0) {
				$asset->FilePointer = $writer->getContentId();
				$asset->write();
			}
		}
	}
}
