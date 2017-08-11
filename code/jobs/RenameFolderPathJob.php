<?php

if (!class_exists('AbstractQueuedJob')) {
    return;
}
/**
 * @author marcus
 */
class RenameFolderPathJob extends AbstractQueuedJob
{
    public function __construct($folder = null, $children = null)
    {
        if ($folder) {
            if (is_numeric($folder)) {
                $folder = Folder::get()->byID($folder);
            }
            $this->setObject($folder);

            if (!$children) {
                $children = File::get()->filter('ParentID', $folder->ID);
            }

            $childIds = $children->column('ID');

            $this->totalSteps = count($childIds);

            $folders[$folder->Filename] = $childIds;
            $this->foldersToProcess = $folders;
        }
    }

    public function getTitle()
    {
        $title = "Rename folder #" . $this->getObject()->ID . " " . $this->getObject()->Title;
        return $title;
    }

    public function getSignature()
    {
        return md5($this->getObject()->ID);
    }

    public function process()
    {
        $toProcess = $this->foldersToProcess;
        $paths = array_keys($toProcess);

        $parentPath = array_shift($paths);
        $ids = $toProcess[$parentPath];

        $files = File::get()->filter('ID', $ids);

        $trimmedPath = rtrim($parentPath, '/');

        foreach ($files as $file) {
            $this->currentStep++;

            $oldPath = $file->Filename;
            $newPath = $trimmedPath . DIRECTORY_SEPARATOR . $file->Name;
            if ($file instanceof Folder) {
                $newPath .= DIRECTORY_SEPARATOR;
                // add the folder to the list of paths to process if it has kids
                $kids = File::get()->filter('ParentID', $file->ID);
                $toProcess[$newPath] = $kids->column('ID');
                $this->totalSteps += count($toProcess[$newPath]);
            }

            if ($newPath === $oldPath) {
                continue;
            }

            $file->Filename = $newPath;
            $file->IgnorePathChanges = true;
            $file->write();
        }

        unset($toProcess[$parentPath]);
        $this->foldersToProcess = $toProcess;

        if (count($toProcess) == 0) {
            $this->isComplete = true;
        }
    }
}