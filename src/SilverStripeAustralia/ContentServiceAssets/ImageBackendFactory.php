<?php

namespace SilverStripeAustralia\ContentServiceAssets;

use Director;
use File;
use ReflectionClass;
use SilverStripe\Framework\Injector\Factory;

/**
 * An image backend factory which ensures that files are downloaded from the CDN.
 */
class ImageBackendFactory implements Factory {

	private $backend;

	/**
	 * @param string $backend The underlying backend name.
	 */
	public function __construct($backend) {
		$this->backend = $backend;
	}

	public function create($service, array $params = array()) {
		$path = $params[0];

		if (!file_exists($path) || filesize($path) == 0) {
			$filename = Director::makeRelative($path);
			$file = File::find(trim($filename, '/'));

			if ($file) {
				$file->downloadFromContentService();
			}
		}

		$reflector = new ReflectionClass($this->backend);
		return $reflector->newInstanceArgs($params);
	}

}
