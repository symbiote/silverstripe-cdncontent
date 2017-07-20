<?php

/**
 * @author <marcus@symbiote.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class CDNFolder extends DataExtension {
	
	private static $db = array(
		'StoreInCDN'		=> 'Varchar',
	);

	private static $dependencies = array(
		'contentService'		=> '%$ContentService',
	);

	/**
	 * @var ContentService
	 */
	public $contentService;
	
	public function updateCMSFields(\FieldList $fields) {
		parent::updateCMSFields($fields);

		$stores = $this->contentService->getStoreTypes();
		if (count($stores)) {
            if ($this->owner->ID) {
                $default = array('' => 'Inherit');
                $stores = array_merge($default, array_combine(array_keys($stores), array_keys($stores)));
                $fields->push(new DropdownField('StoreInCDN', 'Store content in CDN', $stores));
            } else {
                if ($default = $this->contentService->getDefaultStore()) {
                    $fields->push(LiteralField::create("DefaultStoreNote", sprintf(_t('CDNFolder.DEFAULT_NOTE', '<h3>Storing in %s</h3>'), $default)));
                }
            }
		}
	}

	public function getCDNWriter() {
		$stores = $this->contentService->getStoreTypes();
		if ($stores && isset($stores[$this->getCDNStore()])) {
			return $this->contentService->getWriter($this->getCDNStore());
		}
	}
	
	/**
	 * Get the CDN store that this item should be stored in
	 */
	public function getCDNStore() {
		if ($this->owner->StoreInCDN) {
			return $this->owner->StoreInCDN;
		}
		
		return $this->getInheritedCdn();
	}

    public function getInheritedCdn() {
        if(!$this->owner->ID || $this->owner->ID === "root" || !$this->owner->ParentID) {
			return $this->contentService->getDefaultStore();
		}

		if ($this->owner->ParentID) {
			return $this->owner->Parent()->getCDNStore();
		}
    }

    /**
     * If a folder's CDN has been updated, this retrieves all contained files that need to be moved.
     *
     * These are downloaded, pushed to their new location, then removed from their source location
     */
    public function updateChildPaths($oldCdn, $newCdn) {

    }

    public function onAfterWrite()
    {
        $changes = $this->owner->getChangedFields(false, DataObject::CHANGE_VALUE);

        if (isset($changes['StoreInCDN']) && strlen($changes['StoreInCDN']['after'])
            && $changes['StoreInCDN']['after'] != $changes['StoreInCDN']['before']) {
            // get all contained folders then query for children
            $folderIds = [];
            $toProcess = [$this->owner->ID];

            while($next = array_shift($toProcess)) {
                $folderIds[] = $next;
                // get contained files
                $files = Folder::get()->filter('ParentID', $next);
                $moreIds = $files->column('ID');
                $toProcess = array_merge($toProcess, $moreIds);
            }

            $oldCdn = isset($changes['StoreInCDN']['before']) ? $changes['StoreInCDN']['before'] : '';
            if (strlen($oldCdn) === 0) {
                // ask the parent
                $oldCdn = $this->getInheritedCdn();
            }
            $allToUpdate = File::get()->filter([
                'CDNFile:StartsWith' => $oldCdn . ContentService::SEPARATOR,
                'ParentID'    => $folderIds,
            ]);

            foreach ($allToUpdate as $file) {
                try {
                    $file->moveToCdn($this->owner->StoreInCDN);
                } catch (Exception $ex) {
                    SS_Log::log($ex, SS_Log::WARN);
                }
            }
        }
    }
}
