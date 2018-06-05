<?php

namespace Symbiote\ContentServiceAssets;

use File;

/**
 * Saves files to the configured content service when uploaded.
 */
class UploadExtension extends \Extension {
//	public function onAfterUpload(File $file) {
//		$file->uploadToContentService();
//		// force the write because the content field update doesn't appear to get captured for some
//		// unknown reason :(
//		$file->write();
//	}
}
