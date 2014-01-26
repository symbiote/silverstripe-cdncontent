<?php

namespace SilverStripeAustralia\ContentServiceAssets;

/**
 * A non-file asset which is served from an external content source.
 */
class ContentServiceAsset extends \DataObject {

	private static $db = array(
		'Filename' => 'Varchar(255)',
		'FilePointer' => 'FileContent'
	);

	private static $indexes = array(
		'Filename' => true
	);

}
