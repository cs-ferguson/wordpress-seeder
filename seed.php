<?php

require_once('/var/_seeder/inc/class-image-block.php');
require_once('/var/_seeder/inc/class-seeder-post.php');
require_once('/var/_seeder/taxonomies.php');
require_once('/var/_seeder/quotes.php');

function transformTitle($filename)
{
    return ucwords(str_replace('-',' ',$filename));
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
            $seeder_post = new SeederPost( $post_content, $post_title, $media_info, $taxonomy_info );  
            $seeder_post->uploadToWordPress();
            $seeder_post->setFeaturedImage();   
            $seeder_post->addTaxonomies();
        }
    }
}
/* random content */
$num_posts_to_generate = 1;
$min_paragraphs = 3;
$max_paragraphs = 16;
$min_images = 1;
$max_images = ( count($media_info) > 5 ) ? 5 : count($media_info) ;

for( $x = 0; $x < $num_posts_to_generate; $x++ ){

    $content_array = array();

    if( is_array($quotes) ){
        $num_paragraphs = mt_rand( $min_paragraphs, $max_paragraphs );
        $rand_keys = array_rand( $quotes, $num_paragraphs );

        foreach( $rand_keys as $array_key ){
            $paragraph_content = "<!-- wp:paragraph -->\n<p>{$quotes[$array_key]}</p>\n<!-- /wp:paragraph -->\n";
            array_push( $content_array, $paragraph_content );
        }
    }

    if( is_array( $media_info )){
        $num_images = mt_rand( $min_images, $max_images );
        $rand_keys = array_rand( $media_info, $num_images );

        foreach( (array) $rand_keys as $array_key ){
            $media_content = "<!-- wp:image {\"id\":{$media_info[$array_key]['id']} } -->\n<figure class=\"wp-block-image size-full\"><img src=\"{$media_info[$array_key]['filename']}\" alt=\"\" class=\"\"/></figure>\n<!-- /wp:image -->\n";
            array_push( $content_array, $media_content );
        }
    }

    shuffle( $content_array );
    $post_content = implode( "", $content_array );

    // var_dump($post_content);

    $seeder_post = new SeederPost( $post_content, 'Auto-generated Content', $media_info, $taxonomy_info );  
    $seeder_post->uploadToWordPress();
    $seeder_post->setFeaturedImage();   
    $seeder_post->addTaxonomies();
}




?>


