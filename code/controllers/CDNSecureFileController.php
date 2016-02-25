<?php

/**
 * Handles requests to access a CDN file, checking if the file can be viewed given
 * it's access settings. If successful it returns an expiring url for the resource
 *
 * @author Stephen McMahon <stephen@silverstripe.com.au>
 */
class CDNSecureFileController extends Controller {

	/**
	 * Process all incoming requests passed to this controller, checking
	 * that the file exists and passing the file through if possible.
	 */
	public function handleRequest(SS_HTTPRequest $request, DataModel $model) {

		$response = new SS_HTTPResponse();

		$filename = substr($request->getURL(), strlen('cdnassets/'));

		$file = File::get()->filter('filename', $filename)->first();

		if ($file && $file->canView()) {
			// Permission passed redirect to file
			$response->redirect($file->getSecureURL());
		} elseif ($file instanceof File) {
			// Permission failure
			Security::permissionFailure($this, 'You are not authorised to access this resource. Please log in.');
		} else {
			// File doesn't exist
			$response = new SS_HTTPResponse('File Not Found', 404);
		}

		return $response;
	}

}
