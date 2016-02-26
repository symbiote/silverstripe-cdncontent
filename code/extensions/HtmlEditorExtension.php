<?php

/**
 * @author marcus
 */
class HtmlEditorExtension extends Extension {
    public function processImage($image, $img) {
        if ($image->CanViewType && $image->getViewType() != 'Anyone') {
            return;
        }
        $img->setAttribute('data-cdnfileid', $image->ID);
    }
}
