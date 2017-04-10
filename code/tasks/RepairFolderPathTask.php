<?php

class RepairFolderPathTask extends BuildTask
{
    public function run($request)
    {
        $folderID = $request->getVar('id');

        if($folderID == null || $folderID == '') {
            echo 'Please enter in a folder ID to repair e.g.: ?id=<id>';
            return;
        }

        if(!is_numeric($folderID)) {
            echo 'FolderID needs to be a valid integer';
            return;
        }

        $folderID = (int)$folderID;
        if(!is_int($folderID)) {
            echo 'FolderID needs to be a valid integer';
            return;
        }

        $folder = Folder::get()->byID($folderID);
        $fileName = $this->getFixedFolderPath($folder);

        DB::alteration_message("Fixing parent path : '{$folder->Filename}'");
        DB::alteration_message("Changing to        : '{$fileName}'");

        $folder->Filename = $fileName;
        $folder->write();

        $children = Folder::get()->filter('ParentID', $folderID);
        if($children->count() > 0) {
            DB::alteration_message("Fixed child paths : ");
            $this->logUpdatedChildren($children, $fileName);
        }

        DB::alteration_message("");
    }

    public function getFixedFolderPath($folder)
    {
        $folders = [$folder->Name];
        $parentFolder = $folder->Parent();

        $i = 0;
        while($parentFolder->Name != null) {
            array_unshift($folders, $parentFolder->Name);
            $lastFilename = $parentFolder->Filename;
            $parentFolder = $parentFolder->Parent();
        }
        $baseFilename = $parentFolder->Filename;
        return $baseFilename.implode('/', $folders).'/';
    }

    public function logUpdatedChildren($parentChildren, $updatedPath)
    {
        foreach($parentChildren as $child) {
            if(strpos($child->Filename, $updatedPath) !== 0) {
                $fileName = $this->getFixedFolderPath($child);
                $child->Filename = $fileName;
                $child->write();
            }

            DB::alteration_message("    {$child->Filename}");
            $children = Folder::get()->filter('ParentID', $child->ID);
            if($children->count() > 0) {
                $this->logUpdatedChildren($children, $fileName);
            }
        }
    }
}
