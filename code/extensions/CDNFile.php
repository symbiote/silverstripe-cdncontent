<?php

/**
 *
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class CDNFile extends DataExtension {
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
	
	public function onBeforeWrite() {
		if ($this->owner instanceof Image && $this->owner->ClassName == 'Image') {
			$this->owner->ClassName = 'CdnImage';
		}
	}
	
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

		if ($this->owner->ParentID) {
			$writer = $this->owner->Parent()->getCDNWriter();
			return $writer;
		}
	}
	
	/**
	 * Return the CDN store that this file should be stored into, based on its
	 * parent setting
	 */
	public function targetStore() {
		if ($this->owner->ParentID) {
			$store = $this->owner->Parent()->getCDNStore();
			return $store;
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
			return;
		}

		/** @var \FileContent $pointer */
		$pointer = $this->owner->obj('CDNFile');

		if($pointer->exists()) {
			$reader = $this->reader();
			if ($reader) {
				$url = $reader->getURL();
			}
		}
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

	public function downloadFromContentService() {
		/** @var \FileContent $pointer */
		$pointer = $this->owner->obj('CDNFile');

		if ($pointer->exists()) {
			file_put_contents($this->owner->getFullPath(), $pointer->getReader()->read());
		}
	}

	/**
	 * Upload this content asset to the configured CDN
	 */
	public function uploadToContentService() {
		if ($this->owner->ParentID && $this->owner->Parent()->getCDNStore() && !($this->owner instanceof Folder)) {
			/** @var \File $file */
			$file = $this->owner;

			$path = $this->owner->getFullPath();
			if (strlen($path) && is_file($path) && file_exists($path)) {
				$writer = $this->writer();
				// $writer->write($this->owner->getFullPath(), $this->owner->getFullPath());
				$writer->write(fopen($file->getFullPath(), 'r'), $file->getFilename());

				// writer should now have an id
				$file->CDNFile = $writer->getContentId();
			}
		}
	}

	public function updateCMSFields(\FieldList $fields) {
		if ($file = $this->owner->obj('CDNFile')) {
			$v = $file->getValue();
			if (strlen($file->getValue())) {
				$url = $file->URL();
				$link = ReadonlyField::create('CDNUrl', 'CDN link',  sprintf('<a href="%s" target="_blank">%s</a>', $url, $url));
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
	}
}
