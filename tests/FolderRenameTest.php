<?php

class FolderRenameTest extends SapphireTest
{
	protected $usesDatabase = true;

	public static function setUpBeforeClass()
	{
		if(!File::has_extension('CDNFile')) {
			File::add_extension('CDNFile');
		}

		if(!Folder::has_extension('CDNFolder')) {
			Folder::add_extension('CDNFolder');
		}
		
		parent::setUpBeforeClass();
	}

	public function testFolderRenaming()
	{
		$parentFolder = Folder::create();
		$parentFolder->Name = "TestFolder";
		$parentFolder->Title = "TestFolder";
		$parentFolder->write();

		$parentFolderID = $parentFolder->ID;

		$this->assertEquals('assets/TestFolder/', $parentFolder->Filename);

		$childFolder = Folder::create();
		$childFolder->Name = "ChildFolder";
		$childFolder->Title = "ChildFolder";
		$childFolder->setParentID($parentFolderID);
		$childFolder->write();

		$this->assertEquals('assets/TestFolder/ChildFolder/', $childFolder->Filename);

		$tmpFile = File::create();
		$tmpFile->Name = "TestFile.txt";
		$tmpFile->Title = "TestFile";
		$tmpFile->setParentID($childFolder->write());
		$tmpFile->write();

		$this->assertEquals('assets/TestFolder/ChildFolder/TestFile.txt', $tmpFile->Filename);

		$parentFolder = Folder::get()->byID($parentFolderID);
		$parentFolder->Name = "TestFolderRename";
		$parentFolder->Title = "TestFolderRename";
		$parentFolder->write();

		$childFolder = $parentFolder->Children()->first();

		$this->assertEquals('assets/TestFolderRename/', $parentFolder->Filename);
		$this->assertEquals('assets/TestFolderRename/ChildFolder/', $childFolder->Filename);
		// Only enable this for in the future if it's decided that children file filenames should be renamed as well
		// $tmpFile = $childFolder->Children()->first();
		// $this->assertEquals('assets/TestFolderRename/ChildFolder/TestFile.txt', $tmpFile->Filename);

		$newTmpFile = File::create();
		$newTmpFile->Name = "NewTestFile.txt";
		$newTmpFile->Title = "NewTestFile";
		$newTmpFile->setParentID($childFolder->write());
		$newTmpFile->write();

		$this->assertEquals('assets/TestFolderRename/ChildFolder/NewTestFile.txt', $newTmpFile->Filename);

		$childFolder = $parentFolder->Children()->first();
		$fileCount = $childFolder->Children()->count();
		$this->assertEquals(2, $fileCount);

	}
}
