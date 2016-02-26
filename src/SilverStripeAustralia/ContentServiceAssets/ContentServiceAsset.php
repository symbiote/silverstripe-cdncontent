<?php

namespace SilverStripeAustralia\ContentServiceAssets;

/**
 * A non-file asset which is served from an external content source.
 */
class ContentServiceAsset extends \DataObject {

	private static $db = array(
		'Filename' => 'Varchar(255)',
		'FilePointer' => 'FileContent'
	);

    private static $has_one = array(
        'Parent'        => 'File',
    );

	private static $indexes = array(
		'Filename' => true
	);
    
    public function getSecureURL($expires = 60) {
		
		/** @var \FileContent $pointer */
		$pointer = $this->obj('FilePointer');

		if($pointer->exists() && $pointer->getValue()) {
			$reader = $pointer->getReader();
			if ($reader) {
                if ($this->ParentID) {
                    // check for things first
                    $parent = $this->Parent();
                    if ($parent && $parent->ID && !$parent->canView()) {
                        return;
                    }
                } 
                return $reader->getSecureURL($expires);
			}
		}
	}
    
    public function getURL() {
        $pointer = $this->obj('FilePointer');
        
        $controller = \Controller::curr();
        if ($controller instanceof \CMSMain) {
            return $this->Filename;
        }

		if($pointer->exists() && $pointer->getValue()) {
			$reader = $pointer->getReader();
			if ($reader) {
				return $reader->getURL();
			}
		}
    }
    
    public function canView($member = null) {
        $parent = $this->Parent();
        if ($parent && $parent->ID) {
            return $parent->canView($member);
        }
        return parent::canView($member);
    }
    
    public function getViewType() {
        $parent = $this->Parent();
        if ($parent && $parent->ID) {
            return $parent->getViewType();
        }
        return 'Anyone';
    }
}
