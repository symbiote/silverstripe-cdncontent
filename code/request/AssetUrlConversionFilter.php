<?php

/**
 * @author marcus
 */
class AssetUrlConversionFilter implements RequestFilter {
    
    private $convertUrls = false;
    
    
    public function setConvertUrls($v) {
        $this->convertUrls = $v;
    }
    
    public function postRequest(\SS_HTTPRequest $request, \SS_HTTPResponse $response, \DataModel $model) {
        if ($this->convertUrls) {
            $body = $response->getBody();
            
            if (preg_match_all('/data-cdnfileid="(\d+)"/', $body, $matches)) {
                $files = CdnImage::get()->filter('ID', $matches[1]);
                foreach ($files as $file) {
                    $url = $file->getUrl();
                    $filename = $file->Filename;
                    $body = str_replace("src=\"$filename\"", "src=\"$url\"", $body);
                }
                $response->setBody($body);
            }
        }
    }

    public function preRequest(\SS_HTTPRequest $request, \Session $session, \DataModel $model) {
        
    }

}
