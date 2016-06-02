<?php

/**
 *
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class CDNFile extends DataExtension {
    
    const ANYONE_PERM = 'Anyone';
    
	private static $db = array(
		'CDNFile'			=> 'FileContent'
	);
	
	private static $dependencies = array(
		'contentService'		=> '%$ContentService',
	);

	/**
	 *
	 * @var ContentService
	 */
	public $contentService;
	
	/**
	 * @return ContentReader
	 */
	public function reader() {
		$pointer = $this->owner->obj('CDNFile');
		if ($pointer && $pointer->getValue()) {
			return $pointer->getReader();
		}
	}

	/**
	 * @return ContentWriter
	 */
	public function writer() {
		if ($reader = $this->reader()) {
			return $reader->getWriter();
		}

		$writer = null;

		if ($this->owner->ParentID) {
			$writer = $this->owner->Parent()->getCDNWriter();
		} else {
			//get default writer
			$writer = $this->contentService->getWriter();
		}

		return $writer;
	}
	
	/**
	 * Return the CDN store that this file should be stored into, based on its
	 * parent setting, if no parent is found the ContentService default is returned
	 */
	public function targetStore() {
		if ($this->owner->ParentID) {
			$store = $this->owner->Parent()->getCDNStore();
			return $store;
		}

		return $this->contentService->getDefaultStore();
	}
    
    /**
     * Handles FileVersion interaction
     */
    public function getCDNStore() {
        if ($this->owner->ParentID) {
            return $this->owner->Parent()->getCDNStore();
        }
    }
    
    public function getCDNWriter() {
        if ($this->owner->ParentID) {
            return $this->owner->Parent()->getCDNWriter();
        }
    }

	/**
	 * Update the URL used for a file in various locations
	 * 
	 * @param type $url
	 * @return null
	 */
	public function updateURL(&$url) {
		if($this->owner instanceof \Image_Cached) {
			return; /** handled in @link ImageCachedExtension */
		}

        $controller = Controller::has_curr() ? Controller::curr() : null;
        if ($controller instanceof LeftAndMain) {
            return;
        }
        
        
        if ($this->owner->CanViewType && $this->owner->getViewType() != CDNFile::ANYONE_PERM) {
            return;
        }
        
        $cdnLink = $this->getCdnLink();
        if ($cdnLink) {
            $url = $cdnLink;
        }
	}
    
    public function getCdnLink() {
        $pointer = $this->owner->obj('CDNFile');

		if($pointer->exists() && $reader = $this->reader()) {
			return $reader->getURL();
		}
    }

	/**
	 * Return a link to the S3SecureFileController with the id of this file appended allowing for secure link resolution
	 * at page load, including on cached pages
	 *
	 * @return String link to S3SecureFileController endpoint
	 */
	public function getSecureControllerLink() {
        $filename = $this->owner->Filename;
		return $filename;
	}

	/**
	 * Return a secure url for the file. Currently we expect all secure urls are time limited but other limiting methods
	 * nay be supported in the future
	 *
	 * @param Int $expires number of second the URL will remain valid
	 * @return String URL pointing the the resource
	 */
	public function getSecureURL($expires = 60) {
		if($this->owner instanceof \Image_Cached) {
			return;
		}

		/** @var \FileContent $pointer */
		$pointer = $this->owner->obj('CDNFile');

		if($pointer->exists()) {
			$reader = $this->reader();
			if ($reader && $this->owner->canView()) {
				return $reader->getSecureURL($expires);
			}
		}
	}

	/**
	 * Climbs the folder hierarchy until there's a CanViewType that does not equal Inherit
	 *
	 * @return String The first valid CanViewType of this File
	 */
	public function getViewType() {

//		if ($this->owner instanceof Folder) {
			if ($this->owner->CanViewType == 'Inherit') {
					if ($this->owner->ParentID) {
                        return $this->owner->Parent()->getViewType();
                    } else {
						$member = Member::currentUser();
                        return $this->owner->defaultPermissions($member);
                    }
			} else {
				return $this->owner->CanViewType;
			}
//		}

		$default = Config::inst()->get('SecureAssets', 'Defaults');

		return isset($default['Permission']) ? $default['Permission'] : CDNFile::ANYONE_PERM;
	}

    /**
     * Ensure update_filesystem is set to FALSE if we're writing something 
     * to do with a CDN File/Folder
     */
    public function onBeforeWrite() {
        $store = $this->targetStore();
        if (strlen($store)) {
            Config::inst()->update('File', 'update_filesystem', false);
        }
        parent::onBeforeWrite();
    }
    
	public function onAfterDelete() {
		if ($this->owner->ParentID && $this->owner->Parent()->getCDNStore() && !($this->owner instanceof Folder)) {
			$obj = $this->owner->obj('CDNFile');
			if ($obj) {
				try {
					$writer = $obj->getReader()->getWriter();
					$writer->delete();
				} catch (Exception $ex) {
					// not much that can be done really?
					SS_Log::log($ex, SS_Log::WARN);
				}
			}
		}
	}
	
	public function onAfterUpload() {
		$this->uploadToContentService();
	}
    
    /**
     * Ensures there's a local assets path to the required file
     */
    public function ensureLocalFile() {
        if (!$this->localFileExists()) {
			$this->downloadFromContentService();
		}
    }
    
    public function localFileExists() {
        $path = $this->owner->getFullPath();
        if (!file_exists($path) || filesize($path) == 0) {
            return false;
        }
        return true;
    }

	public function downloadFromContentService() {
		/** @var \FileContent $pointer */
		$pointer = $this->owner->obj('CDNFile');

		if ($pointer->exists()) {
			$reader = $pointer->getReader();
			if ($reader) {
                $p = $this->owner->getFullPath();
                Filesystem::makeFolder(dirname($p));
                try {
                    file_put_contents($p, $pointer->getReader()->read());
                } catch (Exception $ex) {
                    // okay, make sure the local file is removed
                    if (file_exists($p) && filesize($p) == 0) {
                        unlink($p);
                    }
                }
			}
		}
	}

	/**
	 * Upload this content asset to the configured CDN
	 */
	public function uploadToContentService() {
		$file = $this->owner;
		if (!($file instanceof Folder) && $writer = $this->writer()) {
			/** @var \File $file */
			
			$path = $file->getFullPath();
			if (strlen($path) && is_file($path) && file_exists($path)) {

				// $writer->write($this->owner->getFullPath(), $this->owner->getFullPath());
				$mtime = @filemtime($path);
                $name = $file->getFilename();
                if ($lastPos = strrpos($name, '/')) {
                    $name = substr($name, 0, $lastPos) . '/' . $mtime . substr($name, $lastPos);
                }
                
				$writer->write(fopen($path, 'r'), $mtime . '/' . $file->getFilename());

				// writer should now have an id
				$file->CDNFile = $writer->getContentId();
				$file->write();
			}
		}
	}

	public function updateCMSFields(\FieldList $fields) {
		if ($file = $this->owner->obj('CDNFile')) {
			$v = $file->getValue();
			if (strlen($file->getValue())) {
				$url = $file->URL();
				$link = ReadonlyField::create('CDNUrl', 'CDN reference',  $v);
				$link->dontEscape = true;
				
				if ($top = $fields->fieldByName('Root.Main.FilePreview')) {
					$field = $top->fieldByName('FilePreviewData');
					$holder = $field->fieldByName('');
					if ($holder) {
						$holder->push($link);
					}
				} else {
					$fields->addFieldToTab('Root.Main', $link);
				}
			}
		}
        
        $fields->removeByName('PreviousVersion');
	}
}
