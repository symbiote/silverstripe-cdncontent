<?php

/**
 * Post upgrade, update filesizes for assets uploaded prior to FileSize capture
 *
 * @author marcus
 */
class UpdateFileSizeTask extends BuildTask
{
    public function run($request)
    {
        $number = $request->getVar('number');
        
        if (!$number) {
            $number = 50;
        }
        
        if (!$request->getVar('run')) {
            exit("Please supply the run= parameter, and a 'number' parameter (defaults to 50 if not set)");
        }
        
        $files = File::get()->filter(['FileSize' => 0, 'ClassName:not' => 'Folder', 'CDNFile:PartialMatch' => ':||'])->limit($number);
        
        echo "Processing " . $number . " files<br/>\n"; flush();
        
        foreach ($files as $file) {
            echo "Downloading $file->ClassName #{$file->ID} " . $file->Title . " ... "; flush();
            $file->ensureLocalFile();
            if ($file->localFileExists()) {
                $file->FileSize = filesize($file->getFullPath());
                echo "filesize {$file->FileSize} ... "; flush();
                if ($file instanceof CdnImage) {
                    $dim = $file->getDimensions();
                    echo "dimensions $dim ... "; flush();
                }
                $file->write();
                unlink($file->getFullPath());
            } else {
                echo " cannot download $file->Title - $file->CDNFile <br/>\n";
            }
            echo "<br/>\n";
        }
    }

}
