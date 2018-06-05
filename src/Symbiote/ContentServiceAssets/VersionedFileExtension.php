<?php

namespace Symbiote\ContentServiceAssets;

use ContentService;
use DirectoryIterator;
use RegexIterator;
use UnexpectedValueException;

/**
 * uploads the 'old' version of a file in private mode
 */
class VersionedFileExtension extends \DataExtension {

	private $service;
	
	private $cachedPaths;
    
    private $doUpload = true;

	public function __construct(ContentService $service) {
		$this->service = $service;
		parent::__construct();
	}
    
	public function onAfterWrite() {
		parent::onAfterWrite();
        if ($this->doUpload) {
            $this->doUpload = false;
            $this->owner->uploadToContentService();
        }
	}

    public function getParentID() {
        return $this->owner->FileID;
    }
    
    public function Parent() {
        return $this->owner->File();
    }
    
    public function canView() {
        return \Permission::check('ADMIN') || $this->owner->File()->canView();
    }
    
    public function getCanViewType() {
        return 'OnlyTheseUsers';
    }
    
    public function getViewType() {
        return $this->getCanViewType();
    }
    
    public function getFilename() {
        return $this->owner->getField('Filename');
    }
}
