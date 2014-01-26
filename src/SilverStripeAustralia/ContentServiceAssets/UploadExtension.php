<?php

namespace SilverStripeAustralia\ContentServiceAssets;

use File;

/**
 * Saves files to the configured content service when uploaded.
 */
class UploadExtension extends \Extension {
	public function onAfterLoad(File $file) {
		$file->uploadToContentService();
		$file->write();
	}
}
