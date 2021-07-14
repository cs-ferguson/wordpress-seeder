<?php

require_once('/var/_seeder/inc/class-image-block.php');
require_once('/var/_seeder/inc/class-seeder-post.php');
require_once('/var/_seeder/taxonomies.php');
require_once('/var/_seeder/quotes.php');
require_once('/var/_seeder/titles.php');

//get options 
$options = getopt( null, [ 'auto-gen::','earliest::']);
//santise options
$autogen = (int) $options['auto-gen'];
if( ! $options['earliest'] ){
    $earliest_date = new DateTime('2015-01-01');
} else {
    try {
        $earliest_date = new DateTime( $options['earliest'] );
    } catch( Exception $e ) {
        echo 'invalid date entered';
        $earliest_date = new DateTime('2015-01-01');
    }
}

function transformTitle( $filename ) {
    return ucwords(str_replace('-',' ',$filename));
}

function getHeaderLevel( $last_header_level ) {
    $next_header_level = 2;

    if( $last_header_level ){

        if( $last_header_level == 2 || $last_header_level == 4 ){
            if( mt_rand(0,1) > 0 ){
                $next_header_level = 2;
            } else {
                $next_header_level = 3;
            }
        }

        if( $last_header_level == 3 ){
            if( mt_rand(0,1) > 0 ){
                $next_header_level = 2;
            } else {
                $next_header_level = 4;
            }
        }

    }

    return $next_header_level;
}

/*Upload images and store filename in array */
$media_info = array();
$allowed_filetypes = array('jpg','jpeg','gif','png');
$seed_images_directory = '/var/_seeder/images/';
$seed_directory_iterator = new DirectoryIterator($seed_images_directory);
foreach ($seed_directory_iterator as $fileinfo) {
    if (!$fileinfo->isDot() && in_array( $fileinfo->getExtension(), $allowed_filetypes )) {
        $file_url =  $seed_images_directory . $fileinfo->getFilename();

        echo "Importing {$fileinfo->getFilename()} ...\n";
        
        $import_media_cmd = "wp media import $file_url --porcelain";
        $media_id = shell_exec($import_media_cmd);
        $media_id = (int) $media_id;

        /* retrieve media url */
        $get_media_url_cmd = "wp post meta pluck $media_id _wp_attachment_metadata file";
        $media_url = shell_exec($get_media_url_cmd);

        /* set array props */
        $media_details = array(
            'id' => (int) $media_id,
            'media_url' => $media_url,
            'filename' => $fileinfo->getFilename()
        );
        array_push( $media_info, $media_details );
    }
}

/* Create Categories and Tags */
$taxonomy_info = array();
if( is_array($taxonomies) ){
    foreach( $taxonomies as $taxonomy_name => $taxonomy_terms ){
        //add to array to pass to seeder post class
        $taxonomy_info[$taxonomy_name] = array();

        if ( is_array($taxonomy_terms) ){
            foreach( $taxonomy_terms as $term_name ){

                echo "\nCreating term {$term_name} in {$taxonomy_name} taxonomy...\n";

                $create_term_cmd = "wp term create {$taxonomy_name} '{$term_name}' --porcelain";
                $new_term_id = (int) shell_exec($create_term_cmd);
            }
        }

        //get all taxonomies as json and convert to object to pass
        $term_info_cmd = "wp term list {$taxonomy_name} --fields=term_id,name --format=json";
        $term_list_json = shell_exec($term_info_cmd);
        $term_list = json_decode($term_list_json, true);
        $taxonomy_info[$taxonomy_name] = $term_list;
    }
}

// var_dump($taxonomy_info);

/* content
* get content from files and then generate some more if specified 
*/
$seed_content_directory = '/var/_seeder/content/';
$seed_directory_iterator = new DirectoryIterator($seed_content_directory);
$allowed_contenttypes = array('txt');
foreach ($seed_directory_iterator as $fileinfo) {
    if( $fileinfo->valid() ){
        if ( !$fileinfo->isDot() && in_array( $fileinfo->getExtension(), $allowed_contenttypes ) ) {
            $filename = basename($fileinfo->getFilename(), '.txt');
            $post_title = transformTitle($filename);
            $post_content = file_get_contents($seed_content_directory . $fileinfo->getFilename());
            $seeder_post = new SeederPost( $post_content, $post_title, $media_info, $taxonomy_info, $earliest_date );  
            $seeder_post->uploadToWordPress();
            $seeder_post->setFeaturedImage();   
            $seeder_post->addTaxonomies();
        }
    }
}
/* random content */
$num_posts_to_generate = $autogen;
$min_paragraphs = 5;
$max_paragraphs = 25;
$min_images = 1;
$max_images = ( count($media_info) > 6 ) ? 6 : count($media_info) ;

for( $x = 0; $x < $num_posts_to_generate; $x++ ){

    //CHANGE THIS TO GENERATE A STRUCTURE FIRST WITH ITEM TYPE - ITEM TYPE AND KEY, THEN CONSTRUCT CONTENT ARRAY
    $structure_array = array();
    //add paras to structure
    if( is_array($quotes) ){
        $num_paragraphs = mt_rand( $min_paragraphs, $max_paragraphs );
        $rand_para_keys = array_rand( $quotes, $num_paragraphs );
        foreach( $rand_para_keys as $array_key ){
            $element_info = array(
                'type'  => 'p',
                'key'   => $array_key
            );
            array_push( $structure_array, $element_info );
        }
    }
    //add images 
    if( is_array( $media_info )){
        $num_images = mt_rand( $min_images, $max_images );
        $rand_img_keys = (array) array_rand( $media_info, $num_images );
        foreach( $rand_img_keys as $array_key ){
            $element_info = array(
                'type'  => 'img',
                'key'   => $array_key
            );
            array_push( $structure_array, $element_info );
        }
    }
    //shuffle structure before adding headers
    shuffle( $structure_array );
    //add headers
    if( is_array( $headers ) ){

        shuffle( $headers );
        $para_count = 0;
        $header_count = 0;
        $paras_since_last_header = null;
        $last_header_level = null;

        foreach( $structure_array as $el_count => $element ){

            if( $element['type'] == 'p' ){
                $para_count++;
                $paras_since_last_header++;
            }

            if( $para_count > 2 && $el_count < ( count($structure_array) - 3 ) ){
                //if no headers have been inserted yet or more than 2 paras sinc last header 
                if( ! $paras_since_last_header || $paras_since_last_header > 2 ){
                    if( mt_rand(0,1) > 0 ){
                        $header_level   = getHeaderLevel( $last_header_level );
                        $header_type    = 'h' . (int) $header_level;
                        $element_info   = array(
                                            'type'  => $header_type,
                                            'key'   => $header_count
                                        );
                        array_splice( $structure_array, $el_count, 0, [$element_info]);
                        //increments
                        $last_header_level          = $header_level;
                        $paras_since_last_header    = 0;
                        $header_count++;
                    }
                }
            }
        }
    }

    //cycle through sturcture and add to content array
    $content_array = array();

    foreach( $structure_array as $element_info ){
        //if paragraph
        if( $element_info['type'] == 'p' ) {
            $paragraph_content = "<!-- wp:paragraph -->\n<p>{$quotes[$element_info['key']]}</p>\n<!-- /wp:paragraph -->\n";
            array_push( $content_array, $paragraph_content );
        } 
        //if header
        if( $element_info['type'] == 'h2' || $element_info['type'] == 'h3' || $element_info['type'] == 'h4' ) {

            $heading_level = (int) str_replace( 'h', '', $element_info['type'] );
            $heading_content = "<!-- wp:heading {\"level\":{$heading_level}} -->\n<{$element_info['type']}>{$headers[$element_info['key']]}</{$element_info['type']}>\n<!-- /wp:heading -->\n";
            array_push( $content_array, $heading_content );

        }
        //if image 
        if( $element_info['type'] == 'img' ) {
            $array_key = $element_info['key'];
            $media_content = "<!-- wp:image {\"id\":{$media_info[$array_key]['id']} } -->\n<figure class=\"wp-block-image size-full\"><img src=\"{$media_info[$array_key]['filename']}\" alt=\"\" class=\"\"/></figure>\n<!-- /wp:image -->\n";
            array_push( $content_array, $media_content );
        } 

    }

    $post_content = implode( "", $content_array );

    $post_title = "Auto-generated Content";
    if( is_array($titles) ){
        $title_key = mt_rand( 0, count($titles) );
        $post_title = $titles[$title_key];
    }


    $seeder_post = new SeederPost( $post_content, $post_title, $media_info, $taxonomy_info, $earliest_date );  
    $seeder_post->uploadToWordPress();
    $seeder_post->setFeaturedImage();   
    $seeder_post->addTaxonomies();
}


?>


