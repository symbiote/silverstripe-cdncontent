<?php

/**
 *
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class CDNFile extends DataExtension {
	public static $db = array(
		'CDNFile'			=> 'FileContent'
	);

	public function onAfterWrite() {
		parent::onAfterWrite();
		
		if ($this->owner->ParentID && $this->owner->Parent()->StoreInCDN) {
			$path = $this->owner->getFullPath();
			if (is_file($path)) {
				$writer = $this->owner->Parent()->getCDNWriter();
				$writer->write($this->owner->getFullPath(), $this->owner->getFullPath());
			}
			
		}
	}
}
