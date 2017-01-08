<?php

use SilverStripeAustralia\ContentServiceAssets\ContentServiceAsset;

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
        $service = singleton('ContentService');
        $pointer = $this->obj('CDNFile');
        if (!$this->ID || !$this->getField('Filename') || !$pointer->exists() || !$service->getDefaultStore()) {
            return call_user_func_array('parent::getFormattedImage', func_get_args());
        }
        $args = func_get_args();

        $pointer = $this->obj('CDNFile');

        $cacheFile = call_user_func_array(array($this, "cacheFilename"), $args);
        $sampleName = basename($cacheFile);
        $resamples = $this->Resamplings->getValues();
        $samplePointer = isset($resamples[$sampleName]) ? $resamples[$sampleName] : null;

        if (!$samplePointer) {
            // regenerate if needed
            if(!file_exists(Director::baseFolder()."/".$cacheFile) || isset($_GET['flush'])) {
                $this->downloadFromContentService();
                call_user_func_array(array($this, "generateFormattedImage"), $args);
                singleton('ContentDeliveryService')->removeLocalFile($this->getFullPath());
            }
            
            // now create the content service asset
            if (file_exists(Director::baseFolder()."/".$cacheFile)) {
                $existing = $this->createResampledAsset($cacheFile);

                $samplings = $this->Resamplings->getValues();
                $samplings[$sampleName] = $samplePointer = $existing->FilePointer;
                $this->Resamplings = $samplings;
                try {
                    $this->write();
                } catch (ValidationException $e) {
                    // Stops a CMS page from erroring if the file suddenly starts
                    // failing validation.
                    // ie. ClamAV detected a virus in File::validate()
                    SS_Log::log($e, SS_Log::WARN);
                }
            }
        }

        $cached = new CdnImage_Cached($cacheFile);

        // Pass through the title so the templates can use it
        $cached->Title = $this->Title;
        // Pass through the parent, to store cached images in correct folder.
        $cached->ParentID = $this->ParentID;
        //Pass through a CanViewType type if we have any so that it can be used for canView checks
        $cached->CanViewType = $this->CanViewType;
        // needed for mtime calcs
        $cached->LastEdited = $this->LastEdited;

        $cached->SourceID = $this->ID;

        $cached->ResampledPointer = $samplePointer;

        return $cached;
	}
    
    /**
     * Creates a content service asset object based on a given resampled file path
     * 
     * @param type $filename
     * @return ContentServiceAsset
     */
    protected function createResampledAsset($filename) {
        $fullpath = Director::baseFolder() . '/' . $filename;
        $asset = ContentServiceAsset::get()->filter('Filename', $filename)->first();

		if(!$asset) {
            $asset = new ContentServiceAsset();
        }
        
        $this->service = singleton('ContentService');


        $asset->Filename = $filename;
        $asset->SourceID = $this->ID;
        $asset->ParentID = $this->ParentID;
        $mtime = time();

        $writer = $this->service->getWriterFor($asset, 'FilePointer', $this->targetStore());
        if ($writer) {
            if (file_exists($fullpath)) {
                // likely that cached image never got built correctly. 
                $name = \Controller::join_links(dirname($filename), $mtime, basename($filename));
                $writer->write(fopen($fullpath, 'r'), $name);

                $asset->FilePointer = $writer->getContentId();
                $asset->write();

                $reader = $writer->getReader();
                if ($reader && $reader->exists()) {
                    singleton('ContentDeliveryService')->removeLocalFile($fullpath);
                }
            } else {
                $asset = null;
            }
        } else {
            $asset = null;
        }

        return $asset;
    }
    
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        $changed = $this->getChangedFields(false, DataObject::CHANGE_VALUE);
        
        if (isset($changed['Name']) || isset($changed['Title'])) {
            // we want to store the new sampled values
            $this->deleteResamplings();
        }
    }
    
    /**
     * 
     * Deletes all content service asset representations of this item, which will mean they regenerate later
     * 
     * @return int
     */
    public function deleteFormattedImages() {
		if(!$this->Filename) return 0;

		$numDeleted = $this->deleteResamplings();
        $this->write();

		return $numDeleted;
	}
    
    /**
     * Mark content service assets as being deleted, and reset our Resamplings value
     * for update later
     * 
     * @return int
     */
    protected function deleteResamplings() {
        $children = ContentServiceAsset::get()->filter('SourceID', $this->ID);
        
        $numDeleted = 0;
        foreach ($children as $child) {
            $child->SourceID = -1;
            
            // we _DONT_ do a hard delete; if content has this image cached, it should be able to
            // hold it for a while. Instead, mark deleted and allow a cleanup job to collect it later
            $child->Filename = 'deleted';
            $child->write();
            $numDeleted++;
        }
        
        $this->Resamplings = [];
        return $numDeleted;
    }
    
    /**
     * Captures the image dimensions in a db field to avoid needing to download the file all the time
     * @param type $dim
     * @return string
     */
    public function getDimensions($dim = "string") {
        if ($this->ImageDim && strlen($this->ImageDim) > 1) {
            if ($dim == 'string') {
                return $this->ImageDim;
            }
            $parts = explode('x', $this->ImageDim);
            return isset($parts[$dim]) ? $parts[$dim] : null;
        }
        $pointer = $this->obj('CDNFile');
        if($this->ID && $this->Filename && $pointer->exists()) {
            $this->ensureLocalFile();
        }
        
        if ($this->localFileExists()) {
            // make sure to save the dimensions for next time
            $this->storeDimensions();
            $this->write();
            return parent::getDimensions($dim);
        }
    }
    
    public function storeDimensions() {
        $size = getimagesize($this->getFullPath());
        if (count($size)) {
            // store the size
            $this->ImageDim = $size[0] . 'x' . $size[1];
        }
    }

	/**
	 * Replaces the Preview Image and Link with secured links if the file is secured.
	 * @return FieldList
	 */
	public function getCMSFields() {
		$fields = parent::getCMSFields();

		$previewField = new LiteralField("SecureImageFull",
				"<img id='thumbnailImage' class='thumbnail-preview' src='".$this->Link()."' alt='Secured File' />\n"
		);

		$url = $this->Link();// 5 minute link expire
		$link = ReadonlyField::create('CDNUrl', 'CDN reference',  $this->CDNFile);
		
		$link->dontEscape = true;

		if ($top = $fields->fieldByName('Root.Main.FilePreview')) {
			$field = $top->fieldByName('FilePreviewImage');
			$field->insertBefore($previewField, 'ImageFull');
			$field->removeByName('ImageFull');
			$top->replaceField('CDNUrl', $link);
		}

		return $fields;
	}
}


/**
 * A resized / processed {@link Image} object.
 * When Image object are processed or resized, a suitable Image_Cached object is returned, pointing to the
 * cached copy of the processed image.
 *
 * @package framework
 * @subpackage filesystem
 */
class CdnImage_Cached extends CdnImage {

	/**
	 * Create a new cached image.
	 * @param string $filename The filename of the image.
	 * @param boolean $isSingleton This this to true if this is a singleton() object, a stub for calling methods.
	 *                             Singletons don't have their defaults set.
	 */
	public function __construct($filename = null, $isSingleton = false) {
		parent::__construct(array(), $isSingleton);
		$this->ID = -1;
		$this->Filename = $filename;
	}

	/**
	 * Override the parent's exists method becuase the ID is explicitly set to -1 on a cached image we can't use the
	 * default check
	 *
	 * @return bool Whether the cached image exists
	 */
	public function exists() {
		return !is_null($this->ResampledPointer);
	}

	public function getRelativePath() {
		return $this->getField('Filename');
	}

	/**
	 * Prevent creating new tables for the cached record
	 *
	 * @return false
	 */
	public function requireTable() {
		return false;
	}

	/**
	 * Prevent writing the cached image to the database
	 *
	 * @throws Exception
	 */
	public function write($showDebug = false, $forceInsert = false, $forceWrite = false, $writeComponents = false) {
        return;
	}
}
