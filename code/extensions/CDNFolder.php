<?php

/**
 * 
 *
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class CDNFolder extends DataExtension {
	
	public static $db = array(
		'StoreInCDN'		=> 'Varchar',
	);

	public static $dependencies = array(
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
			$default = array('' => 'No CDN');
			$stores = array_merge($default, array_combine(array_keys($stores), array_keys($stores)));
			$fields->push(new DropdownField('StoreInCDN', 'Store content in CDN', $stores));
		}
	}
	
	public function getCDNWriter() {
		$stores = $this->contentService->getStoreTypes();
		if ($stores && isset($stores[$this->owner->StoreInCDN])) {
			return $this->contentService->getWriter($this->owner->StoreInCDN);
		}
	}
}
