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
		/** @var \CdnImage_Cached $cached */
		$filename = $cached->getFilename();
		
		$storeIn = $this->owner->targetStore();
		if (!$storeIn) {
			return;
		}
        
        $pointer = null;
        
        if ($this->owner->ResampledPointer) {
            $pointer = $this->owner->ResampledPointer;
        }

        if (!$pointer) {
            ////////////////////////////////////
            /* THE FOLLOWING IS LEGACY AND SHOULD BE REMOVED LATER - CdnImage has obsoleted it */
            ////////////////////////////////////
            $asset = ContentServiceAsset::get()->filter('Filename', $filename)->first();

            if(!$asset) {
                $asset = new ContentServiceAsset();
                $asset->Filename = $filename;
                $asset->SourceID = $this->owner->SourceID;
                $asset->ParentID = $this->owner->ParentID;
                $mtime = strtotime($this->owner->LastEdited);

                $writer = $this->service->getWriterFor($asset, 'FilePointer', $storeIn);
                if ($writer) {
                    if (file_exists($cached->getFullPath())) {
                        // likely that cached image never got built correctly. 
                        $name = \Controller::join_links(dirname($filename), $mtime, basename($filename));
                        $writer->write(fopen($cached->getFullPath(), 'r'), $name);

                        $asset->FilePointer = $writer->getContentId();

                        // @TODO Cleanup local system copy; see note further below
                        $reader = $writer->getReader();
                        if ($reader && $reader->exists()) {
                            singleton('ContentDeliveryService')->removeLocalFile($cached->getFullPath());
                        }

                        $asset->write();

                        $reader = $writer->getReader();
                        if ($reader && $reader->exists()) {
                            singleton('ContentDeliveryService')->removeLocalFile($cached->getFullPath());
                        }
                    } else {
                        $asset = null;
                    }
                } else {
                    $asset = null;
                }
            }

            if ($asset) {
                $pointer = $asset->FilePointer;
            }
            
            ////////////////////////////////////
            /* End obsolete code */
            ////////////////////////////////////
        }
        
        if ($pointer) {
			$reader = $this->service->getReader($pointer);
			if ($reader) {
                if ($this->owner->CanViewType && $this->owner->getViewType() != \CDNFile::ANYONE_PERM) {
                    return;
                } else {
                    $controller = \Controller::has_curr() ? \Controller::curr() : null;
                    if ($controller instanceof \LeftAndMain) {
                        return;
                    }
                    $url = $reader->getURL();
                }
			}
		}
	}
}
