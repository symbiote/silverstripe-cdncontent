# CDN Theme module 

A module that allows the assets for a theme to be stored on a CDN

## Overview

Provides a few CDN related pieces of functionality

* Store assets from Files & Images in a specified CDN
* Store theme related assets in a configured CDN



## Requirements

* Content Services module https://github.com/nyeholt/silverstripe-content-services/
* Patches to the framework folder - see gist at https://gist.github.com/nyeholt/8642905

## Installation

* In your local configuration, add 

  ShortcodeParser::get('default')->register('embed', array('CdnEmbedder', 'handle\_shortcode'));

to ensure the cdn is manipulated for resized images because SS treats CDN sourced content
as an embed tag. This may be addressed in a future commit
