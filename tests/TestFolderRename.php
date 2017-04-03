<?php

class TestFolderRename extends SapphireTest
{
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
		$childFolder->setParentID($parentFolder->ID);
		$childFolder->write();

		$this->assertEquals('assets/TestFolder/ChildFolder/', $childFolder->Filename);

		$tmpFile = File::create();
		$tmpFile->Name = "TestFile.txt";
		$tmpFile->Title = "TestFile";
		$tmpFile->setParentID($childFolder->write());
		$tmpFile->write();

		$this->assertEquals('assets/TestFolder/ChildFolder/TestFile.txt', $tmpFile->Filename);

		// $parentFolder = Folder::get()->byID($parentFolderID);
		$parentFolder->Name = "TestFolderRename";
		$parentFolder->Title = "TestFolderRename";
		$parentFolder->write();

		$childFolder = $parentFolder->Children()->first();
		$tmpFile = $childFolder->Children()->first();

		$this->assertEquals('assets/TestFolderRename/', $parentFolder->Filename);
		$this->assertEquals('assets/TestFolderRename/ChildFolder/', $childFolder->Filename);
		$this->assertEquals('assets/TestFolderRename/ChildFolder/TestFile.txt', $tmpFile->Filename);

		$newTmpFile = File::create();
		$newTmpFile->Name = "NewTestFile.txt";
		$newTmpFile->Title = "NewTestFile";
		$newTmpFile->setParentID($childFolder->write());
		$newTmpFile->write();

		$this->assertEquals('assets/TestFolderRename/ChildFolder/NewTestFile.txt', $newTmpFile->Filename);
	}
}
