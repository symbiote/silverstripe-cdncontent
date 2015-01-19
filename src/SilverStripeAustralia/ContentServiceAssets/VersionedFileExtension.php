<?php

namespace SilverStripeAustralia\ContentServiceAssets;

use ContentService;
use DirectoryIterator;
use RegexIterator;
use UnexpectedValueException;

/**
 * Changes the external content file when the version is changed, and
 * ensures resampled files are deleted from the remote so that they're
 * correctly regenerated later
 */
class VersionedFileExtension extends \DataExtension {

	private $service;
	
	private $cachedPaths;

	public function __construct(ContentService $service) {
		$this->service = $service;
		parent::__construct();
	}
	
	public function onAfterWrite() {
		parent::onAfterWrite();
		
		// figure out which files we need to regenerate (or at least delete to allow for regeneration
		// as Image will  call deleteFormattedImages during onAfterUpload which means the
		// updates below do NOT get triggered correctly
		$this->cachedPaths = $this->findCachedPaths();
		
	}

	public function onAfterUpload() {
		if (!$this->owner->hasExtension('CDNFile')) {
			return;
		}

		// If there's only a single version, it must have just been created.
		if(count($this->owner->Versions()) == 1) {
			return;
		}

		$this->uploadCachedImages();
	}
	
	protected function findCachedPaths() {
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
		$cached = array();
		foreach($iterator as $item) {
			$fullPath = "$dir/$item";
			$path = dirname($this->owner->getFilename()).'/_resampled/' . $item;
			
			$cached[] = "$item";
		}
		
		return $cached;
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
		
		if (!$this->cachedPaths || count($this->cachedPaths) === 0) {
			return;
		}
		foreach ($this->cachedPaths as $item) {
			$fullPath = "$dir/$item";
			$path = dirname($this->owner->getFilename()).'/_resampled/' . $item;
			$asset = ContentServiceAsset::get()->filter('Filename', $path)->first();
			
			if (!file_exists($fullPath) && $asset) {
				// delete the remote
				$writer = $this->service->getWriterFor($asset, 'FilePointer', $store);
				$writer->delete();
				$asset->delete();
				continue;
			}
			
			if (!file_exists($fullPath)) {
				continue;
			}

			if(!$asset) {
				$asset = new ContentServiceAsset();
				$asset->Filename = $path;
			}

			$writer = $this->service->getWriterFor($asset, 'FilePointer', $store);
			$mtime = @filemtime($path); 
			$writer->write(fopen($fullPath, 'r'), $mtime . '/' . $path);
			
			if ($asset->ID <= 0) {
				$asset->FilePointer = $writer->getContentId();
				$asset->write();
			}
		}

	}
}
