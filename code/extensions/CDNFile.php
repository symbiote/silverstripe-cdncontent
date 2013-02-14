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

	public function onBeforeWrite() {
		parent::onBeforeWrite();
		
		if ($this->owner->ParentID && $this->owner->Parent()->StoreInCDN) {
			$path = $this->owner->getFullPath();
			if (is_file($path)) {
				$writer = $this->owner->Parent()->getCDNWriter();
				$writer->write($this->owner->getFullPath(), $this->owner->getFullPath());
				// writer should now have an id
				$this->owner->CDNFile = $writer->getContentId();
			}
		}
	}
	
	public function updateCMSFields(\FieldList $fields) {
		if ($file = $this->owner->obj('CDNFile')) {
			if (strlen($file->getValue())) {
				$url = $file->URL();
				$fields->addFieldToTab('Root.Main', new LiteralField('CDNUrl', sprintf('<a href="%s">%s</a>', $url, $url)));
			}
		}
	}
}
