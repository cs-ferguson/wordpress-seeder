<?php

class ImageBlock {

    public function __construct( $id, $filename, $attributes, $inner_content, $new_src, $wp_attachment_id ) {
		$this->id               = $id;
        $this->filename         = $filename;
        $this->attributes       = $attributes;
        $this->inner_content    = $inner_content;
        $this->new_src          = $new_src;
        $this->wp_attachment_id = $wp_attachment_id;     
	}

    /* generate the markup for the opening block comment, including attributes */
    private function generateOpeningComment() {
        $attribute_markup = json_encode( $this->attributes );
        return '<!-- wp:image ' . $attribute_markup . ' -->';
    }

    private function updateSrc() {
        $updated_content = str_replace( $this->filename, $this->new_src, $this->inner_content );
        return $updated_content;
    }

    private function updateId() {
        $this->attributes->id = $this->wp_attachment_id;
        return $this->id;
    }

    public function getContent() {
        $this->updateId();
        $this->updateSrc();
        return $this->generateOpeningComment() . "\n" . $this->updateSrc() . "\n" . "<!-- /wp:image -->";
    }




}

?>