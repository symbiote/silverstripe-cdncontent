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
class ResampledFileExtension extends \DataExtension {

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
        if (!$this->owner->hasExtension('CDNFile')) {
			return;
		}
        
		$store = $this->owner->targetStore();
		if (!$store) {
			return;
		}
		$iterator = null;
		$dir = dirname($this->owner->getFullPath()) . '/_resampled';

        if (!is_dir($dir)) {
            return;
        }
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
        if (!$this->owner->hasExtension('CDNFile')) {
			return;
		}
        
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
				if ($writer) {
					$writer->delete();
				}
				
				$asset->delete();
				continue;
			}
			
			if (!file_exists($fullPath)) {
				continue;
			}

            $mtime = strtotime($this->owner->LastEdited);
			if(!$asset) {
				$asset = new ContentServiceAsset();
				$asset->Filename = $path;
                $asset->ParentID = $this->owner->ParentID;
			}

			$writer = $this->service->getWriterFor($asset, 'FilePointer', $store);
			if ($writer) {
                $name = \Controller::join_links(dirname($filename), $mtime, basename($filename));
				$writer->write(fopen($fullPath, 'r'), $name);
				
				if ($asset->ID <= 0) {
					$asset->FilePointer = $writer->getContentId();
					$asset->write();
				}

                $reader = $writer->getReader();
                if ($reader && $reader->exists()) {
                    @unlink($fullPath);
                }
			}
		}
	}
}
