<?php

use Symbiote\ContentServiceAssets\ContentServiceAsset;

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
        
        $filename = $request->getURL();
        
        if (strpos($filename, 'cdnassets') === 0) {
            $filename = 'assets/' . substr($filename, strlen('cdnassets/'));
        }
        
        $file = null;
        if (strpos($filename, '_resampled') !== false) {
            $file = ContentServiceAsset::get()->filter('Filename', $filename)->first();
        } else if (strpos($filename, '/_versions/') !== false) {
            $file = FileVersion::get()->filter('Filename', "/" . $filename)->first();
        } else {
            $file = File::get()->filter('filename', $filename)->first();
        }
        
		if ($file && $file->canView()) {
            if (!$file->CDNFile && !$file->FilePointer) {
                return $this->httpError(404);
            }
			// Permission passed redirect to file
            $redirectLink = '';
            if ($file->getViewType() != CDNFile::ANYONE_PERM) {
                if ($file->hasMethod('getSecureURL')) {
                    $redirectLink = $file->getSecureURL(180);
                } 
                if (!strlen($redirectLink)) {
                    // can we stream it?
                    return $this->sendFile($file);
                }
            } else {
                $redirectLink = $file->getURL();
            }

            if ($redirectLink && trim($redirectLink, '/') != $request->getURL()) {
                $response->redirect($redirectLink);
            } else {
                return $this->httpError(404);
            }
		} else {
            if (class_exists('SecureFileController')) {
                $handoff = SecureFileController::create();
                return $handoff->handleRequest($request, $model);
            } elseif ($file instanceof File) {
                // Permission failure
                Security::permissionFailure($this, 'You are not authorised to access this resource. Please log in.');
            } else {
                // File doesn't exist
                $response = new SS_HTTPResponse('File Not Found', 404);
            }
        }

		return $response;
	}
    
    /**
	 * Output file to the browser.
	 * For performance reasons, we avoid SS_HTTPResponse and just output the contents instead.
	 */
	public function sendFile($file) {
        $reader = $file->reader();
        if (!$reader || !$reader->isReadable()) {
            return;
        }

		if(class_exists('SapphireTest', false) && SapphireTest::is_running_test()) {
			return $reader->read();
		}
        
        $type = HTTP::get_mime_type($file->Filename);
        $disposition = strpos($type, 'image') !== false ? 'inline' : 'attachment';

		header('Content-Description: File Transfer');
		// Quotes needed to retain spaces (http://kb.mozillazine.org/Filenames_with_spaces_are_truncated_upon_download)
		header(sprintf('Content-Disposition: %s; filename="%s"', $disposition, basename($file->Filename)));
		header('Content-Length: ' . $file->FileSize);
		header('Content-Type: ' . $type);
		header('Content-Transfer-Encoding: binary');

		// Ensure we enforce no-cache headers consistently, so that files accesses aren't cached by CDN/edge networks
		header('Pragma: no-cache');
		header('Cache-Control: private, no-cache, no-store');
        
        increase_time_limit_to(0);

		// Clear PHP buffer, otherwise the script will try to allocate memory for entire file.
		while (ob_get_level() > 0) {
			ob_end_flush();
		}

		// Prevent blocking of the session file by PHP. Without this the user can't visit another page of the same
		// website during download (see http://konrness.com/php5/how-to-prevent-blocking-php-requests/)
		session_write_close();

        echo $reader->read();
		die();
	}
}
