<?php

/**
 *
 * @author <marcus@symbiote.com.au>
 * @license BSD License http://www.silverstripe.org/bsd-license
 */
class CDNFile extends DataExtension {

    const ANYONE_PERM = 'Anyone';
	const MAX_FILE_PATH_LENGTH = 1024;

	private static $db = array(
		'CDNFile'			=> 'FileContent',

        'FileSize'          => 'Int',
        'ImageDim'          => 'Varchar',

        'Resamplings'       => 'MultiValueField',
	);

	private static $dependencies = array(
		'contentService'		=> '%$ContentService',
	);

	/**
	 *
	 * @var ContentService
	 */
	public $contentService;

    /**
     * @return void
     */
    public function validate(ValidationResult $result) {
        // Rename file if name is already taken in the CMS
        if ($this->owner instanceof File &&
            !Upload::config()->replaceFile &&
            $this->owner->ID == 0) {

            $pathBefore = $this->owner->getFilename();

            // Get folder path
            $relativeFolderPath = ASSETS_DIR . '/';
            $parentFolder = $this->owner->Parent();
            if ($parentFolder instanceof Folder) {
                $relativeFolderPath = $parentFolder->getRelativePath();
            }

            // Get file info
            $relativeFilePath = $this->owner->getFilename();
            $fileName = $this->owner->Name;

            $fileSuffixArray = explode('.', $fileName);
            $fileTitle = array_shift($fileSuffixArray);
            $fileSuffix = !empty($fileSuffixArray)
                    ? '.' . implode('.', $fileSuffixArray)
                    : null;

            // NOTE: `while` logic copy pasted from framework's "Upload.php" file - 2017-10-12
            while(File::get()->filter('Filename', $relativeFilePath)->count() > 0) {
                $i = isset($i) ? ($i+1) : 2;
                $oldFilePath = $relativeFilePath;

                $prefix = Upload::config()->version_prefix;
                $pattern = '/' . preg_quote($prefix) . '([0-9]+$)/';
                if(preg_match($pattern, $fileTitle, $matches)) {
                    $fileTitle = preg_replace($pattern, $prefix . ($matches[1] + 1), $fileTitle);
                } else {
                    $fileTitle .= $prefix . $i;
                }
                $relativeFilePath = $relativeFolderPath . $fileTitle . $fileSuffix;

                if($oldFilePath == $relativeFilePath && $i > 2) {
                    user_error("Couldn't fix $relativeFilePath with $i tries", E_USER_ERROR);
                }
            }

            if ($pathBefore !== $relativeFilePath) {
                $this->owner->setName(basename($relativeFilePath));

                // Rename phyiscal file on disc (Taken from File::updateFilesystem, on 2017-10-12)
                //
                // NOTE: We have to do this manually as the file is only renamed when changes are
                //       detected with $this->getChangedFields(), and this is happening to an
                //       unwritten record.
                //
                $pathBeforeAbs = Director::getAbsFile($pathBefore);
                $pathAfterAbs = Director::getAbsFile($relativeFilePath);

                if(!file_exists($pathAfterAbs)) {
                    if(!is_a($this, 'Folder')) {
                        // Only throw a fatal error if *both* before and after paths don't exist.
                        if(!file_exists($pathBeforeAbs)) {
                            throw new Exception("Cannot move $pathBeforeAbs to $pathAfterAbs - $pathBeforeAbs doesn't exist");
                        }

                        // Check that target directory (not the file itself) exists.
                        // Only check if we're dealing with a file, otherwise the folder will need to be created
                        if(!file_exists(dirname($pathAfterAbs))) {
                            throw new Exception("Cannot move $pathBeforeAbs to $pathAfterAbs - Directory " . dirname($pathAfter)
                                . " doesn't exist");
                        }
                    }

                    // Rename file or folder
                    $success = rename($pathBeforeAbs, $pathAfterAbs);
                    if(!$success) throw new Exception("Cannot move $pathBeforeAbs to $pathAfterAbs");
                }
            }
        }
    }

	/**
	 * @return ContentReader
	 */
	public function reader() {
		if ($this->owner instanceof Folder) {
			return null;
		}
		$pointer = $this->owner->obj('CDNFile');
		if ($pointer && $pointer->getValue()) {
			return $pointer->getReader();
		}
	}

	/**
	 * @return ContentWriter
	 */
	public function writer() {
		if ($this->owner instanceof Folder) {
			return null;
		}
		$reader = $this->reader();
		if ($reader) {
			return $reader->getWriter();
		}

		$writer = null;
		if ($this->owner->ParentID) {
			$writer = $this->owner->Parent()->getCDNWriter();
		} else {
			//get default writer
			$writer = $this->contentService->getWriter();
		}

		return $writer;
	}

	/**
	 * Return the CDN store that this file should be stored into, based on its
	 * parent setting, if no parent is found the ContentService default is returned
	 */
	public function targetStore() {
		if ($this->owner->ParentID) {
			$store = $this->owner->Parent()->getCDNStore();
			return $store;
		}

		return $this->contentService->getDefaultStore();
	}

    public function Size() {
        $size = $this->owner->FileSize;
        return ($size) ? File::format_size($size) : false;;
    }

    /**
     * Handles FileVersion interaction
     */
    public function getCDNStore() {
        if ($this->owner->ParentID) {
            return $this->owner->Parent()->getCDNStore();
        }
    }

    public function getCDNWriter() {
        if ($this->owner->ParentID) {
            return $this->owner->Parent()->getCDNWriter();
        }
    }

	/**
	 * Update the URL used for a file in various locations
	 *
	 * @param type $url
	 * @return null
	 */
	public function updateURL(&$url) {
		if($this->owner instanceof \Image_Cached || $this->owner instanceof \CdnImage_Cached) {
			return; /** handled in @link ImageCachedExtension */
		}

        // in the CMS, we do _not_ change the asset
        $controller = Controller::has_curr() ? Controller::curr() : null;
        if ($controller instanceof LeftAndMain) {
            return;
        }

        if ($this->owner->CanViewType && $this->owner->getViewType() != CDNFile::ANYONE_PERM) {
            return;
        }

        $cdnLink = $this->getCdnLink();
        if ($cdnLink) {
            $url = $cdnLink;
        }
	}

    public function getCdnLink() {
        $pointer = $this->owner->obj('CDNFile');

		if($pointer->exists() && $reader = $this->reader()) {
			return $reader->getURL();
		}
    }

	/**
	 * Return a link to the S3SecureFileController with the id of this file appended allowing for secure link resolution
	 * at page load, including on cached pages
	 *
	 * @return String link to S3SecureFileController endpoint
	 */
	public function getSecureControllerLink() {
        $filename = $this->owner->Filename;
		return $filename;
	}

	/**
	 * Return a secure url for the file. Currently we expect all secure urls are time limited but other limiting methods
	 * nay be supported in the future
	 *
	 * @param Int $expires number of second the URL will remain valid
	 * @return String URL pointing the the resource
	 */
	public function getSecureURL($expires = 60) {
		if($this->owner instanceof \Image_Cached || $this->owner instanceof \CdnImage_Cached) {
			return;
		}

		/** @var \FileContent $pointer */
		$pointer = $this->owner->obj('CDNFile');

		if($pointer->exists()) {
			$reader = $this->reader();
			if ($reader && $this->owner->canView() && method_exists($reader, 'getSecureURL')) {
				return $reader->getSecureURL($expires);
			}
		}
	}

	/**
	 * Climbs the folder hierarchy until there's a CanViewType that does not equal Inherit
	 *
	 * @return String The first valid CanViewType of this File
	 */
	public function getViewType() {
        $perm = null;

        if ($this->owner->CanViewType == 'Inherit') {
            if ($this->owner->ParentID) {
                $perm = $this->owner->Parent()->getViewType();
            } else {
                $member = Member::currentUser();
                $perm = $this->owner->defaultPermissions($member);
            }
        } else {
            $perm = $this->owner->CanViewType;
        }

		$default = Config::inst()->get('SecureAssets', 'Defaults');

		$default = isset($default['Permission']) ? $default['Permission'] : CDNFile::ANYONE_PERM;

        return $perm ? $perm : $default;
	}

    /**
     * Ensure update_filesystem is set to FALSE if we're writing something
     * to do with a CDN File/Folder
     */
    public function onAfterWrite() {
        $store = $this->targetStore();
        if (strlen($store)) {
            Config::inst()->update('File', 'update_filesystem', false);

            // however, lets also ensure the changed filename file update check is run
            // which is otherwise triggered by updateFilesystem
            $changedFields = $this->owner->getChangedFields();

            // $this->owner->IgnorePathChanges gets set by the rename path job
            if (isset($changedFields['Filename']) && !$this->owner->IgnorePathChanges && $this->owner instanceof File) {
                $pathBefore = $changedFields['Filename']['before'];
                $pathAfter = $changedFields['Filename']['after'];

                if($pathBefore && $pathBefore != $pathAfter) {
                    // update links call
                    $this->owner->extend('updateLinks', $pathBefore, $pathAfter);

                    $children = File::get()->filter('ParentID', $this->owner->ID);
                    if ($children->count() > 0) {
                        if (class_exists('QueuedJobService')) {
                            singleton('QueuedJobService')->queueJob(new RenameFolderPathJob($this->owner, $children));
                        } else {
                            $this->updateChildFolderLinks($children, $pathBefore, $pathAfter);
                        }
                    }
                }
            }
        }
        parent::onAfterWrite();
    }

    public function updateChildFolderLinks($children, $pathBefore, $pathAfter)
    {
		foreach($children as $child) {
            if ($child instanceof Folder) {
                $child->Filename = $pathAfter.$child->Name.'/';
                $child->write();
            }
		}
    }

    /**
     * And if deleting don't do so
     */
    public function onBeforeDelete() {
        $store = $this->targetStore();
        if (strlen($store)) {
            Config::inst()->update('File', 'update_filesystem', false);
        }

		parent::onBeforeDelete();
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

    /**
     * Ensures there's a local assets path to the required file
     */
    public function ensureLocalFile() {
        if (!$this->localFileExists()) {
			$this->downloadFromContentService();
		}
    }

    public function localFileExists() {
    	if (!$this->owner->getField('Filename')) {
    		return false;
    	}
        $path = $this->owner->getFullPath();
        if (!file_exists($path) || filesize($path) == 0) {
            return false;
        }
        return true;
    }

	public function downloadFromContentService() {
		/** @var \FileContent $pointer */
		$pointer = $this->owner->obj('CDNFile');

		if ($pointer->exists()) {
			$reader = $pointer->getReader();
			if ($reader) {
                $p = $this->owner->getFullPath();
                Filesystem::makeFolder(dirname($p));

                $result = false;
                try {
                    $result = (file_put_contents($p, $pointer->getReader()->read()) !== FALSE);
                } catch (Exception $ex) {
                    // okay, make sure the local file is removed
                    if (file_exists($p) && filesize($p) == 0) {
                        singleton('ContentDeliveryService')->removeLocalFile($p);
                    }
                }
                return $result;
			}
		}
		return false;
	}

	/**
	 * Upload this content asset to the configured CDN
	 */
	public function uploadToContentService() {
		/** @var \File $file */
		$file = $this->owner;
		$writer = $this->writer();
		if (!$writer) {
			return;
		}
		$path = $file->getFullPath();
		if (strlen($path) && is_file($path) && file_exists($path)) {

			$mtime = @filemtime($path);
            $name = trim($file->getFilename(), '/');
		if (!$mtime) {
                    $mtime = '0';
                }

			//Insert the file modified time to make file unique-ish
			if ($lastPos = strrpos($name, '/')) {
				// Add in at as last folder name
				$name = substr($name, 0, $lastPos) . '/' . $mtime . substr($name, $lastPos);
			} else {
				//No folder found create one for the mtime
				$name = $mtime . '/' . $name;
			}

			$fileKeyLength = strlen($name);
			if ($fileKeyLength > CDNFile::MAX_FILE_PATH_LENGTH) {
				$lastPos = strrpos($name, '/');
				$ext = '';
				$filename = substr($name, $lastPos + 1);
				// Try and find a file extension
				if (strrpos($name, '.') !== false) {
					// Store and trim extension for later replacement
					$ext = substr($name, strrpos($name, '.'));
					$filename = substr($filename, 0, strrpos($filename, '.'));
				}
				// add 1 here so we can add in a ~ to indictate truncation
				$truncateLength = ($fileKeyLength + strlen($ext) + 1) - CDNFile::MAX_FILE_PATH_LENGTH;
				if (strlen($filename) <= $truncateLength) {
					// Folder length exceeds CDNFile::MAX_FILE_PATH_LENGTH. MD5 file to prevent file loss log error
					SS_Log::log("CDNFile: Total file length (folders + name) exceeds " . CDNFile::MAX_FILE_PATH_LENGTH . " characters and can't be "
							. "trimmed. File key has been MD5 encoded. File key: " . md5($name) . $ext . " Filename: "
							. "$name", SS_Log::ERR);
					$name = md5($name) . $ext;
				} else {
					// Recombine folder and file name while truncating the filename and appending a ~ then extension
					$name = substr($name, 0, $lastPos) . '/' . substr($filename, 0, (0 - $truncateLength)) . '~' . $ext;
				}
			}


			$writer->write(fopen($path, 'r'), $name);

			// writer should now have an id
			$file->CDNFile = $writer->getContentId();
            $file->FileSize = @filesize($path);

            // check whether it's an image, and handle its dimensions
            if ($file instanceof CdnImage) {
                $file->storeDimensions();
            }

			$file->write();

            // confirm the remote upload is there, and delete the local file
            // Oct 2016 - UNLESS the versioned files extension is on, we have to treat it slightly different
            $this->deleteLocalIfExistsOnContentService();
		}
	}

	/**
	 * Added so that a file can be brought down from CDN to be manipulated/examined and then
	 * removed safely if it already exists on CDN.
	 */
	public function deleteLocalIfExistsOnContentService() {
		/** @var \File $file */
		$file = $this->owner;
		$writer = $this->writer();
		if (!$writer) {
			return;
		}
		// confirm the remote upload is there, and delete the local file
        // Oct 2016 - UNLESS the versioned files extension is on, we have to treat it slightly different
        $reader = $writer->getReader();
        if (!$reader || !$reader->exists()) {
        	return;
        }
        $path = $file->getFullPath();
		if (strlen($path) && is_file($path) && file_exists($path)) {
	        // check if we're a file with versioned on, we need to check the presence of the VersionNumber
	        // before deleting
	        if ($this->owner instanceof FileVersion) {
	            singleton('ContentDeliveryService')->removeLocalFile($path);

	            // and the _parent_ file?
	            $parentFile = $this->owner->File();
				if ($parentFile
					&& $parentFile->ID
					&& $parentFile->CDNFile
					&& file_exists($parentFile->getFullPath())
				) {
	                singleton('ContentDeliveryService')->removeLocalFile($parentFile->getFullPath());
	            }
	        } else {
	            singleton('ContentDeliveryService')->removeLocalFile($path);
	        }
	    }
	}


    /**
     * Moves this file to its same path on the named CDN
     *
     * @param string $newCdn
     */
    public function moveToCdn($newCdn) {
        $reader = $this->reader();

        // gets the _new_ writer
        $writer = $this->getCDNWriter();

        // hooking it to match the current cdn path.
        $writer->setId($reader->getId());
        $writer->write($reader);

        $this->owner->CDNFile = $writer->getContentId();
        $this->owner->write();

        // do the same for all versions
        if ($this->owner->hasExtension('VersionedFileExtension')) {
            foreach ($this->owner->Versions() as $version) {
                if ($version->hasMethod('moveToCdn')) {
                    try {
                        $version->moveToCdn($newCdn);
                    } catch (Exception $ex) {

                    }
                }
            }
        }

        // delete source
        $newReader = $writer->getReader();
        if ($newReader->exists() && $newReader->getContentId() != $reader->getContentId()) {
            $oldWriter = $reader->getWriter();
            $oldWriter->delete();
        }
    }

	public function updateCMSFields(\FieldList $fields) {

		if ($file = $this->owner->obj('CDNFile')) {
			$v = $file->getValue();
			if (strlen($file->getValue())) {
				$url = $file->URL();
				$link = ReadonlyField::create('CDNUrl', 'CDN reference',  $v);
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

            $sizeField = $fields->dataFieldByName('Size');
            if ($sizeField) {
                $sizeField->setValue($filesize = $this->owner->Size());
            }
		}


        $fields->removeByName('PreviousVersion');
	}
}
