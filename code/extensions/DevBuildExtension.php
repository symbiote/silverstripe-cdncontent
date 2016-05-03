<?php

/**
 * @author marcus
 */
class DevBuildExtension extends Extension {
    public function beforeCallActionHandler($request, $action) {
        $imageConf = Config::inst()->get('Injector', 'Image');
        $imageConf['class'] = 'Image';
        Config::inst()->update('Injector', 'Image', $imageConf);
    }
}
