<?php

/**
 * @author marcus
 */
class HtmlEditorExtension extends Extension {
    public function processImage($image, $img) {
        if (!$image) {
            return;
        }
        if ($image->CanViewType && $image->getViewType() != CDNFile::ANYONE_PERM) {
            return;
        }
        
        $img->setAttribute('data-cdnfileid', $image->ID);
    }
}
