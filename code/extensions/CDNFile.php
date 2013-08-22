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
		
		if ($this->owner->ParentID && $this->owner->Parent()->StoreInCDN && !($this->owner instanceof Folder)) {
			$path = $this->owner->getFullPath();
			if (strlen($path) && is_file($path) && file_exists($path)) {
				$writer = $this->owner->Parent()->getCDNWriter();
				$writer->write($this->owner->getFullPath(), $this->owner->getFullPath());
				// writer should now have an id
				$this->owner->CDNFile = $writer->getContentId();
			}
		}
	}

	public function onAfterDelete() {
		if ($this->owner->ParentID && $this->owner->Parent()->StoreInCDN && !($this->owner instanceof Folder)) {
			$obj = $this->owner->obj('CDNFile');
			if ($obj) {
				$writer = $obj->getReader()->getWriter();
				$writer->delete();
			}
		}
	}

	public function updateCMSFields(\FieldList $fields) {
		if ($file = $this->owner->obj('CDNFile')) {
			if (strlen($file->getValue())) {
				$url = $file->URL();
				$fields->addFieldToTab('Root.Main', new LiteralField('CDNUrl', sprintf('<a href="%s" target="_blank">%s</a>', $url, $url)));
			}
		}
	}
}
