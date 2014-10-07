<?php

/**
 * @author <marcus@silverstripe.com.au>
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
	 *
	 * @var ContentService
	 */
	public $contentService;
	
	public function updateCMSFields(\FieldList $fields) {
		parent::updateCMSFields($fields);

		$stores = $this->contentService->getStoreTypes();
		if (count($stores)) {
			$default = array('' => 'Inherit');
			$stores = array_merge($default, array_combine(array_keys($stores), array_keys($stores)));
			$fields->push(new DropdownField('StoreInCDN', 'Store content in CDN', $stores));
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

		if ($this->owner->ParentID) {
			return $this->owner->Parent()->getCDNStore();
		}
	}
}
