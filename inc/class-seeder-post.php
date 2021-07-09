<?php

class SeederPost {

    public function __construct( $post_content, $post_title, $media_info, $taxonomy_info, $earliest_date ) {
        $this->post_title       = $post_title;
        $this->html_content     = $post_content;
        $this->media_info       = $media_info;
        $this->taxonomy_info    = $taxonomy_info;
        $this->stripped_content = $post_content;
		$this->image_blocks     = $this->parseImageBlocks();
        $this->wp_post_id       = null;
        $this->earliest_date    = $earliest_date;

	}

    private function parseImageBlocks() {
        $image_blocks = array();
    
        preg_match_all(
            '/<!-- wp:image (?P<attributes>{.*}) -->\s(?P<content>.*src="(?P<filename>.*\.[a-z]*)".*)\s<!-- \/wp:image -->/',
            $this->html_content,
            $matches
        );
    
        /* loop through each match (image block) */
        for ( $x = 0; $x < count($matches[0]); $x++ ) {
            /* construct Image Block object 
            *   id
            *   filename
            *   attributes
            *   inner_content
            */
            $filename = $matches['filename'][$x];
            $id = hash('md5', $filename . $x);
            $attributes = json_decode($matches['attributes'][$x]);
            $new_src = '/wp-content/uploads/' . $this->getMediaInfoByFilename($filename)['media_url'];
            $wp_attachment_id = $this->getMediaInfoByFilename($filename)['id'];

            $image_block = new ImageBlock( 
                $id, 
                $filename, 
                $attributes, 
                $matches['content'][$x],
                $new_src,
                $wp_attachment_id
            );
            
            array_push( $image_blocks, $image_block );

            /* replace content with placeholder */
            $this->stripped_content = str_replace($matches[0][$x],"<!-- ${id} -->",$this->stripped_content);
        }

        // var_dump($image_blocks);
        // var_dump( $this->stripped_content);
    
        return $image_blocks; 
    }

    public function getModifiedContent() {
        $modified_content = $this->stripped_content;
        foreach( $this->image_blocks as $image_block ){
            $modified_content = str_replace( '<!-- ' . $image_block->id . ' -->', $image_block->getContent(), $modified_content );
        }
        return $modified_content;
    }

    /* matches the filename to the correct item in the media_info array and returns the first matching item an array) */
    private function getMediaInfoByFilename($filename) {
        $key = array_search($filename, array_column($this->media_info, 'filename'));
        return $this->media_info[$key];
    }

    public function uploadToWordPress() {
        $create_post_cmd = "wp post create --post_title='{$this->post_title}' --post_content='{$this->getModifiedContent()}' --post_status='publish' --post_date='{$this->generateDate()}' --porcelain";
        $new_post_id = (int) shell_exec($create_post_cmd);
        $this->wp_post_id = $new_post_id;
        echo "\nNew post ID {$new_post_id} created.\n";
        return $new_post_id;
    }

    /* sets first image in post as featured image */
    public function setFeaturedimage() {
        if( $this->wp_post_id > 0 && count($this->image_blocks) > 0 ){
            $set_feat_img_cmd = "wp post meta add {$this->wp_post_id} _thumbnail_id {$this->image_blocks[0]->wp_attachment_id}";
            $output = shell_exec($set_feat_img_cmd);
            echo $output;
            return true;
        } else {
            return false;
        }
    }

    /* randomly generates a publish date */
    private function generateDate() {
        $start = $this->earliest_date;
        $end = new DateTime('now');
        $randomTimestamp = mt_rand($start->getTimestamp(), $end->getTimestamp());
        $randomDate = new DateTime();
        $randomDate->setTimestamp($randomTimestamp);
        return $randomDate->format('Y-m-d H:i:s');
    }

    /* randomly assign taxonomies */
    public function addTaxonomies() {

        echo "\nAdding taxonomies to post {$this->wp_post_id}...\n";

        if( is_array($this->taxonomy_info) ){
            foreach( $this->taxonomy_info as $taxonomy_name => $terms ){
                $term_ids = implode( " ", $this->getRandomTerms($terms) );
                $set_terms_cmd = "wp post term set {$this->wp_post_id} {$taxonomy_name} {$term_ids} --by=id";
                $output = shell_exec( $set_terms_cmd );
            }
        }

        return true;
    }

    private function getRandomTerms( $terms_array ) {
        $term_ids = array();
        $min = 1;
        $max = ( count($terms_array) > 5 ) ? 5 : count($terms_array) ;
        $x = mt_rand( $min, $max );
        $rand_keys = array_rand( $terms_array, $x );

        foreach( (array) $rand_keys as $array_key ){
            array_push( $term_ids, $terms_array[$array_key]['term_id'] );
        }

        return $term_ids;

    }
}


?>