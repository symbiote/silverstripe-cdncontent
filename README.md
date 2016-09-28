# CDN Theme module 

A module that allows the assets for a theme to be stored on a CDN

## Overview

Provides a few CDN related pieces of functionality

* Store assets from Files & Images in a specified CDN
* Store theme related assets in a configured CDN



## Requirements

* Content Services module https://github.com/nyeholt/silverstripe-content-services/
* Patches to the framework folder - see the framework.patch file 

## Installation

* Add the following extensions

```yml

File:
  extensions:
    - CDNFile
Folder: 
  extensions:
    - CDNFolder
# If using the Versioned Files module
FileVersion:
  extensions:
    - CDNFile
```
