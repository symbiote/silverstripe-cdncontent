<?php

/**
 * Cleans up any paths for local files that may have been uploaded
 * or downloaded during this request. 
 * 
 *
 * @author marcus
 */
class FileCleanupFilter implements RequestFilter
{
    /**
     * @var ContentDeliveryService
     */
    public $contentDelivery;
    
    public function postRequest(\SS_HTTPRequest $request, \SS_HTTPResponse $response, \DataModel $model)
    {
        $this->contentDelivery->cleanup();
    }

    public function preRequest(\SS_HTTPRequest $request, \Session $session, \DataModel $model)
    {
        // no-op
    }
}
