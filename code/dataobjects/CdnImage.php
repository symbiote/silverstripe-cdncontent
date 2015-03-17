<?php

/**
 * Subclass that overwrites specific behaviour of Image 
 * 
 * This class type is switched into place when someone saves an image that has
 * the CDNFile extension applied
 *
 * @author <marcus@silverstripe.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class CdnImage extends Image {
	public function getFormattedImage($format) {
		$args = func_get_args();
		
		$pointer = $this->obj('CDNFile');
		
		if($this->ID && $this->Filename && $pointer->exists()) {
			$cacheFile = call_user_func_array(array($this, "cacheFilename"), $args);
			
			if(!file_exists(Director::baseFolder()."/".$cacheFile) || isset($_GET['flush'])) {
				call_user_func_array(array($this, "generateFormattedImage"), $args);
			}
			
			$cached = new Image_Cached($cacheFile);
			// Pass through the title so the templates can use it
			$cached->Title = $this->Title;
			// Pass through the parent, to store cached images in correct folder.
			$cached->ParentID = $this->ParentID;
			return $cached;
		}

		return parent::getFormattedImage($format);
	}
}
