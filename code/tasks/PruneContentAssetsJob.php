<?php

use \Symbiote\ContentServiceAssets\ContentServiceAsset;

if (class_exists('AbstractQueuedJob')) {
    

/**
 * Cleans up content assets from X days ago
 *
 * @author marcus
 */
class PruneContentAssetsJob extends AbstractQueuedJob
{
    public function __construct($daysAgo = 0)
    {
        if ($daysAgo) {
            $this->daysAgo = (int) $daysAgo;
            if (!$this->daysAgo) {
                $this->daysAgo = 30;
            }
            $age = date('Y-m-d 00:00:00', strtotime("-{$this->daysAgo} days"));
            
            $this->totalSteps = ContentServiceAsset::get()->filter(array('Filename' => 'deleted', 'LastEdited:LessThan' => $age))->count();
        }
    }
    
    public function getTitle()
    {
        return "Prune deleted content assets from $this->daysAgo days ago";
    }
    
    public function process()
    {
        $daysAgo = (int) $this->daysAgo;
        if (!$daysAgo) {
            $daysAgo = 30;
        }
        $age = date('Y-m-d 00:00:00', strtotime("-{$daysAgo} days"));
        $assets = ContentServiceAsset::get()->filter(array('Filename' => 'deleted', 'LastEdited:LessThan' => $age));
        foreach ($assets as $asset) {
            $asset->delete();
            $this->currentStep++;
        }
        
        $this->isComplete = true;
        $job = new PruneContentAssetsJob($daysAgo);
        singleton('QueuedJobService')->queueJob($job, date('Y-m-d H:00:00', time() + 86400));
    }
}


}