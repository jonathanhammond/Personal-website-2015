<?php
//--------------------------
// Tumblr Stack v3.4.7
//--------------------------

//--------------------------
// Generic Helper Functions
//--------------------------
set_error_handler("customError");
function customError($errno, $errstr) {
	log_message("Error Handler: [$errno] $errstr");
	return;
};
function log_message($string) {
	echo "<script>console.log('$string')</script>";
	return;
}

function iscurlinstalled() {
	return in_array('curl', get_loaded_extensions()) ? true : false;
};
function get_current_url($trim_page = false) {
    $pageURL = 'http';
    if (isset($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] == "on") {$pageURL .= "s";}
    $pageURL .= "://";
    
    $path = $trim_page ? pathinfo($_SERVER["SCRIPT_NAME"], PATHINFO_DIRNAME) : $_SERVER["SCRIPT_NAME"];
    
    if ($_SERVER["SERVER_PORT"] != "80") {
        $pageURL .= $_SERVER["SERVER_NAME"].":".$_SERVER["SERVER_PORT"].$path;
    } else {
        $pageURL .= $_SERVER["SERVER_NAME"].$path;
    }
    return $pageURL;
};
function build_url_string($pretty_url,$post_id,$tag_name,$search_string,$post_type,$current_page,$clear_cache,$total_posts,$posts_per_page) {
	$url_string = array();

	$url_string['refresh_url']  	= $_SERVER["SCRIPT_NAME"] . '?refresh=1';
	$url_string['refresh_all_url'] 	= $_SERVER["SCRIPT_NAME"] . '?refresh=all';

	if ($pretty_url) {
	// TODO: Need to rethink the pretty url structure because types and tags now support pages. 
		$script_info = pathinfo($_SERVER["SCRIPT_NAME"]);
		$dirname = $script_info['dirname'] == '/' ? null : $script_info['dirname'];
		$url_string['type_url']	  	 	= $dirname . '/type-';
		$url_string['tag_url'] 	  	 	= $dirname . '/tag-';
		$url_string['post_url']     	= $dirname . '/post-';
		$url_string['page_url']     	= $dirname . '/page-';
		$url_string['search_url']   	= $dirname . '/search-';
		$url_string['ext_post_url'] 	= get_current_url(true) . '/post-';	
		$url_string['rss'] 	            = get_current_url(true) . '/rss';	
	}
	else {
		$url_string['type_url'] 	  	= $_SERVER["SCRIPT_NAME"] . '?type=';
		$url_string['tag_url'] 	  	 	= $_SERVER["SCRIPT_NAME"] . '?tag=';
		$url_string['post_url']     	= $_SERVER["SCRIPT_NAME"] . '?id=';
		$url_string['page_url']     	= $_SERVER["SCRIPT_NAME"] . '?page=';
		$url_string['search_url']   	= $_SERVER["SCRIPT_NAME"] . '?search=';
		$url_string['ext_post_url'] 	= get_current_url() . '?id=';
		$url_string['rss'] 				= get_current_url() . '?rss=1';
	}
	
	if ($post_id or $current_page or $search_string or $post_type or $tag_name) {
		// $url_string['refresh_url'] .= $pretty_url		? '?' 						: null;
		$url_string['refresh_url'] .= $post_id 			? '&id='.$post_id 			: null;
		$url_string['refresh_url'] .= $current_page 	? '&page='.$current_page 	: null;
		$url_string['refresh_url'] .= $search_string 	? '&search='.$search_string : null;
		$url_string['refresh_url'] .= $post_type 		? '&type='.$post_type 		: null;
		$url_string['refresh_url'] .= $tag_name 		? '&tag='.$tag_name 		: null;
	
		// $url_string['refresh_all_url'] .= $pretty_url		? '?' 						: null;
		// $url_string['refresh_all_url'] .= $post_id 			? '&id='.$post_id 			: null;
		// $url_string['refresh_all_url'] .= $current_page 	? '&page='.$current_page 	: null;
		// $url_string['refresh_all_url'] .= $search_string 	? '&search='.$search_string : null;
		// $url_string['refresh_all_url'] .= $post_type 		? '&type='.$post_type 		: null;
		// $url_string['refresh_all_url'] .= $tag_name 		? '&tag='.$tag_name 		: null;
	}
	
	if ($total_posts > 0) {
	// Pagnation URLs	
		if ($current_page * $posts_per_page < $total_posts and !$post_id) {
			//Need to include other criteria if they are defined
			$pagination_url  = $url_string['page_url'] . ($current_page + 1);
			$pagination_url .= $search_string 	? '&search='.$search_string : null;
			$pagination_url .= $post_type 		? '&type='.$post_type 		: null;
			$pagination_url .= $tag_name 		? '&tag='.$tag_name 		: null;
			$url_string['old_post_url'] = $pagination_url;
		}
		if ($current_page != 1 and !$post_id) {
			//Need to include other criteria if they are defined
			$pagination_url  = $url_string['page_url'] . ($current_page - 1);
			$pagination_url .= $search_string 	? '&search='.$search_string : null;
			$pagination_url .= $post_type 		? '&type='.$post_type 		: null;
			$pagination_url .= $tag_name 		? '&tag='.$tag_name 		: null;
			$url_string['new_post_url'] = $pagination_url;
		}
	}
	return $url_string;
};
//--------------------------
// Caching Methods
//--------------------------
function create_cache_file($cache_dir, $cache_file, $json){
	// Create the cache dir if it does not exist
	if (!file_exists($cache_dir)) { mkdir($cache_dir,0777); }
	$handle = fopen($cache_file, "w");
	fwrite($handle, json_encode($json));
	fclose($handle);
	return;
}
function get_cache_file($tumblr_domain, $post_id, $tag_name, $post_type, $posts_per_page, $start, $cache_dir){
	$cache_file = $cache_dir .'/'. $tumblr_domain .'.'. $posts_per_page .'.'. $start;
	if ($post_id) {
		$cache_file .= '.'. $post_id;
	}
	else {
		$cache_file .= $tag_name  ? '.'. $tag_name  : null;
		$cache_file .= $post_type ? '.'. $post_type : null;
	}
	$cache_file .= '.json';
	return $cache_file;
}
function clear_cache_file($tumblr_domain, $post_id, $tag_name, $post_type, $posts_per_page, $start, $cache_dir) {
	$cache_file = get_cache_file($tumblr_domain, $post_id, $tag_name, $post_type, $posts_per_page, $start, $cache_dir);
	unlink($cache_file);
	return;
};
function clear_all_cache($dir) {
	if ($handle = opendir($dir)) {
	    while (false !== ($file = readdir($handle))) {
    	    if ( is_dir($file) || $file === 'tumblr-cache.tmp') continue;
            unlink($dir .'/'. $file);
	    }
	}
    closedir($handle);
    return;
};
//--------------------------
// Tumblr Query Methods
//--------------------------
function query_tumblr($url) {
	if (iscurlinstalled()) {
		log_message("Using Curl");
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL,$url);		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
		curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);		
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		
		$result = curl_exec($ch);
		curl_close($ch);
		
		return $result;		
	}
	else {
		log_message("Using fopen. Curl was not found.");
		ini_set('user_agent', $_SERVER['HTTP_USER_AGENT']); 
		$http_request = fopen($url, "rb");

		if ($http_request) {
			$result = stream_get_contents($http_request);
			fclose($http_request);
			return $result;
		}
		else {
			// echo hidden error into the markup for debugging
			log_message("There was problem using fopen on the url: $url");
			//Return the status code
			return 100;
		}
	}
};
function get_tumblr_posts($tumblr_domain, $post_id, $tag_name, $post_type, $search_string, $posts_per_page, $start, $cache_dir, $cache_sec, $debug) {

	if ($search_string == null) {
		// No cache for searches
		$cache_file = get_cache_file($tumblr_domain, $post_id, $tag_name, $post_type, $posts_per_page, $start, $cache_dir);
		$cache_delete = false;
	
		if (file_exists($cache_file)) { 
			$file_age = time() - filemtime($cache_file);
			if ($file_age >= $cache_sec) { 
				// Delete the file since its too old. - We dont delete the file here because we want to keep it around incase the query does fail. 
				$cache_delete = true;
			}
			else {
				//Return the contents of the cache file
				return 	json_decode(file_get_contents($cache_file), true);
			}
		}
	}	

	$public_key = 'E82n6SaJxo86o16jyrx8bia3QCYnPX755u6NUOxPTlTEWESnh8';
	$url = 'http://api.tumblr.com/v2/blog/'. $tumblr_domain .'/posts?reblog_info=true&api_key='. $public_key .'&limit='. $posts_per_page;
	$url .= $start > 0 ? '&offset='. $start : null;
	
	if ($post_id) {	
		$url .= '&id='.$post_id;
	}
	else if ($search_string) {
		$url .= '&search='.urlencode($search_string);	
	}
	else {	
		$url .= $tag_name ? '&tag='.urlencode($tag_name) : null;
		$url .= $post_type ? '&type='.$post_type : null;
	}

	$response = query_tumblr($url);
	
	if ($response and $response !== 100) {
		$tumblr_response = json_decode($response, true);	
		
		if ($tumblr_response){
			$tumblr_meta = $tumblr_response['meta'];
		
			if ($tumblr_meta['status'] == 200) {
				// Everything was OK! Now delete old cache files, create new ones and return the posts
				$tumblr_posts = $tumblr_response['response'];
				if ($cache_delete) { unlink($cache_file); }
				create_cache_file($cache_dir, $cache_file, $tumblr_posts);			
				return $tumblr_posts;
			}
			else {
				// echo hidden error into the markup for debugging
				log_message("There was an error returned from Tumblr: ".$tumblr_meta['status']." - $url");
	
				//Return the contents of the cache file since the query failed else return the status code
				return 	!$search_string && file_exists($cache_file) ? json_decode(file_get_contents($cache_file), true) : $tumblr_meta['status'];
			}			
		}			
	}
	// echo hidden error into the markup for debugging
	log_message("There was an error querying the url: $url");
	log_message("The response from the url: ".trim($response));
	//Return the contents of the cache file since the query failed else return the status code
	return 	!$search_string && file_exists($cache_file) ? json_decode(file_get_contents($cache_file), true) : 100;
}
//--------------------------
// General Post Functions
//--------------------------
function get_post_date($post,$inline = 'outside_date') {
	if ($inline == 'inline_date') {	
		return  '<div class="date inline_date">'.
	                '<a href="'. $post['local-page-url'] .'" class="permalink">'. strftime('%A, %B %d, %Y',$post['timestamp']) .'</a>'.
	            '</div>';
	}
	else {
		return  '<div class="date outside_date">'.
	                '<a href="'. $post['local-page-url'] .'" class="permalink"><span class="month">'. strftime('%b',$post['timestamp']) .'</span><span class="day">'. strftime('%d',$post['timestamp']) .'</span></a>'.
	            '</div>';
	}
};
function get_title($post) {
	$title = '';
    switch ($post['type']) {
        case 'quote':
			$title = substr($post['text'], 0, 50) . '...';
        case 'answer':
			$title = $post['question'];
        case 'audio':
        	if (isset($post['track_name'])) {
        		$title = $post['track_name'];
        	}
        	else {
        		$title = $post['blog-title'] .' ('. $post['type'] .'-'. $post['id'] .')';
        	}
        default:
			$title = isset($post['title']) ? $post['title'] : $post['blog-title'] .' ('. $post['type'] .'-'. $post['id'] .')';
    }
    return htmlspecialchars($title);
};
function get_reblog_info($post, $text_reblog) {
	if (isset($post['reblogged_root_url']) and isset($post['reblogged_root_title'])) {
		echo '<div class="reblogged"><a href="'. $post['reblogged_root_url'] .'">'. $text_reblog .' '. $post['reblogged_root_title'] .'</a></div>';
	}
	return;
};
//--------------------------
// Post Footer Functions
//--------------------------
function get_post_tags($post) { 
    $tags = '';
    if (is_array($post['tags']) and count($post['tags']) != 0) {    
        $tags .= '<p class="tags">';
        foreach ($post['tags'] as $tag) {
            $tags .= '<a class="tag" href="'. $post['tag-url'] . urlencode($tag) .'">'. $tag .'</a>';
        }
        $tags .= '</p>';
    }
    return $tags;
};
function get_comment_count($post) { 
	if ($post['comment-username'] != null) {
		if ($post['comment-type'] === 'facebook') {
		    return '<p class="comments"><a href="'. $post['local-page-url'] .'#fb-root"><fb:comments-count href='. $post['external-page-url'] .'></fb:comments-count> '. $post['text-comment'] .'</a></p>';
		}
		else {
		    return '<p class="comments"><a href="'. $post['local-page-url'] .'#disqus_thread" data-disqus-identifier="'. $post['id'] .'">'. $post['text-comment'] .'</a></p>';
		}
	}
	return;
};
function get_social_buttons($post) { 
	if ($post['social-buttons'] != null) {
	    return 	'<div class="share_button_wrapper">'.
                    '<div class="share_button facebook"><fb:like href="'. $post['external-page-url'] .'" layout="button_count" show_faces="false" width="150" font=""></fb:like></div>'.
                    '<div class="share_button twitter '. $post['lang'] .'"><a href="http://twitter.com/share" class="twitter-share-button" data-lang="'. $post['lang'] .'" data-url="'. $post['external-page-url'] .'" data-text="'. get_title($post) .'" data-count="horizontal">Tweet</a></div>'.
                    '<div class="share_button google"><g:plusone size="medium" annotation="inline" width="120" href="'. $post['external-page-url'] .'"></g:plusone></div>'.
                '</div>';
	}
	return;
};
function get_post_footer($post) {
	return 	'<div class="post-footer">'.
                '<div class="meta_wrapper">'.
                    '<p class="posttime"><a href="'. $post['local-page-url'] .'">'. $post['text-posted'] .' '. strftime('%I:%M %p',$post['timestamp']) .'</a></p>'.
                    get_comment_count($post).
                    get_post_tags($post).
                '</div>'.
	            get_social_buttons($post).
	            '<div class="clear-footer"></div>'.
            '</div>';
};
//--------------------------
// Photo Post Functions
//--------------------------
function get_photo($photo,$photo_size = null) {
// This function assumes that the order of the photo sizes is largest to smallest. 
	if ($photo_size == null) {
		// Return the largest photo, assuming that its the first in the array
		return $photo['alt_sizes'][0];
	}
	if ($photo_size == 75) {
		// Return the last square photo, assuming that its the first in the array
		return end($photo['alt_sizes']);
	}
	// Determine the orientation that is tallest. The maximum height will be 500px
	$orientation = $photo['alt_sizes'][0]['width'] > $photo['alt_sizes'][0]['height'] ? 'width' : 'height';
	foreach($photo['alt_sizes'] as $size) {
		if ($size[$orientation] <= $photo_size) {
			return $size;
		}
	}
};
function get_single_photo_post($post) {
	$large_photo = $post['photos'][0]['original_size'];
	$small_photo = get_photo($post['photos'][0],500);
	return 	'<div class="photo post-content">'.
                '<div class="image">'.
                    '<a class="photo-link" data-height="'. $large_photo['height'] .'" data-width="'. $large_photo['width'] .'" href="'. $large_photo['url'] .'" target="_blank"><img height="'. $small_photo['height'] .'" width="'. $small_photo['width'] .'" src="'. $small_photo['url'] .'" alt/></a>'.
                '</div>'.
                '<div class="caption">'. $post['caption'] .'</div>'.
            '</div>';
};
function get_photoset_gallery_post($post) {
	$set_markup = '<div class="photoset post-content">';
    $set_markup .= '<div class="photoset-gallery">';

	foreach($post['photos'] as $photo) {
		$large_photo = $photo['original_size'];
		$small_photo = get_photo($photo,75);
    	$set_markup .= 	'<div class="image">';
    	$set_markup .= 		'<a class="photo-link" data-height="'. $large_photo['height'] .'" data-width="'. $large_photo['width'] .'" href="'. $large_photo['url'] .'" title="'. $photo['caption'] .'" target="_blank">';
    	$set_markup .= 			'<img src="'.$small_photo['url'].'" width="75" height="75" alt="'. $photo['caption'] .'"/>';
        $set_markup .= 		'</a>';
        $set_markup .= 	'</div>';
	}
    $set_markup .= '</div>';
    $set_markup .= '<div class="caption">'. $post['caption'] .'</div>';
    $set_markup .= '</div>';
    return $set_markup;
};
function get_photoset_show_post($post) {
	$set_markup = '<div class="photoset post-content">';
    $set_markup .= '<div class="photoset-cycler">';
	
	foreach($post['photos'] as $photo) {
		$large_photo = $photo['original_size'];
		$small_photo = get_photo($photo,500);
    	$set_markup .= '<div class="image">';
        $set_markup .= '<a class="photo-link" data-height="'. $large_photo['height'] .'" data-width="'. $large_photo['width'] .'" href="'. $large_photo['url'] .'" target="_blank"><img height="'. $small_photo['height'] .'" width="'. $small_photo['width'] .'" src="'. $small_photo['url'] .'" alt/></a>';
        $set_markup .= '</div>';
	}
    $set_markup .= '</div>';
    $set_markup .= '<div class="caption">'. $post['caption'] .'</div>';
    $set_markup .= '</div>';
    return $set_markup;
};
//--------------------------
// Other Type Post Functions
//--------------------------
function get_text_post($post) {
	return  '<div class="text post-content">'.
                '<h3><a href="'. $post['local-page-url'] .'">'. $post['title'] .'</a></h3>'.
                '<div class="text-body">'. $post['body'] .'</div>'.
            '</div>';
};
function get_answer_post($post) {
	return  '<div class="answer post-content">'.
                '<h3><a href="'. $post['local-page-url'] .'">'. $post['question'] .'</a></h3>'.
                '<p class="asking_name">'.$post['asking-from'].' <a href="'. $post['asking_url'] .'">'. $post['asking_name'] .'</a></h3>'.
                '<div class="text-body">'. $post['answer'] .'</div>'.
            '</div>';
};
function get_link_post($post) {
	return 	'<div class="link post-content">'.
                '<h3><a href="'. $post['url'] .'">'. $post['title'] .'</a></h3>'.
                '<div class="link-body">'. $post['description'] .'</div>'.
            '</div>';
};
function get_video_post($post) {
	// Take the last video in the array, assuming that its the highest resolution
	$player = end($post['player']);
	return 	'<div class="video post-content">'.
				'<script type="text/javascript" language="javascript" src="http://assets.tumblr.com/javascript/tumblelog.js?506"></script>'.
				'<div class="player">'. $player['embed_code'] .'</div>' .
				'<div class="caption">'. $post['caption'] .'</div>' .
			'</div>';
};
function get_quote_post($post) {
	return 	'<div class="quote post-content">'.
                '<div class="quote-words">'. $post['text'] .'</div>' .
                '<div class="source">- '. $post['source'] .'</div>' .
            '</div>';
};
function get_audio_post($post) {
	$audio_node = '<div class="audio post-content">';
	
	// Set Track Name as Title if available
	$audio_node .= isset($post['track_name']) ? '<h3><a href="'. $post['url'] .'">'. $post['track_name'] .'</a></h3>': null;
	
	$audio_node .= 	'<div class="player_wrapper">';

	// Artwork
	$audio_node .= 	'<div class="artwork">';
	$audio_node .= 		'<img class="album-case" width="11" height="109" src="'. $post['assetpath'] .'/audiocase.png" alt/>';
	$audio_node .= 		isset($post['source_url']) ? '<a href="'.$post['source_url'].'" target="_blank">' : null;
	$audio_node .= 			isset($post['album_art']) ? '<img width="109" height="109" src="'. $post['album_art'] .'" alt/>' : '<img width="109" height="109" class="default_art" src="'. $post['assetpath'] .'/no_art.jpg" alt/>' ;
	$audio_node .= 		isset($post['source_url']) ? '</a>' : null;
	$audio_node .= 	'</div>';

	// Player
    $audio_node .= 	'<div class="track">';
	$audio_node .= 		isset($post['track_name']) ? '<div class="track_name">'. $post['track_name'] .'</div>' : null;
	$audio_year  = 		isset($post['year']) ? ' - '. $post['year'] : null;
	$audio_node .= 		isset($post['album']) ? '<div class="album">'. $post['album'] . $audio_year .'</div>' : null;
	$audio_node .= 		isset($post['artist']) ? '<div class="artist">'. $post['artist'] .'</div>' : null;
    $audio_node .= 		'<div class="player">';
	$audio_node .= 			'<div class="audio_player">'. $post['player'] .'</div>';
    $audio_node .= 			'<div class="player_footer">';
	$audio_node .= 				'<span class="play_count">'. $post['plays'] .' '. $post['plays-text'] .'</span>';
	$audio_node .= 				'<span class="audio_source">';
	$audio_node .= 					isset($post['source_url']) ? '<a href="'.$post['source_url'].'" target="_blank">' : null;
	$audio_node .= 					isset($post['source_title']) ? $post['source_title'] : null;
	$audio_node .= 					isset($post['source_url']) ? '</a>' : null;
	$audio_node .= 				'</span>';
	$audio_node .= 			'</div>';
	$audio_node .= 		'</div>';
	$audio_node .= 	'</div>';

	$audio_node .= 	'</div>';

	// Caption
	$audio_node .= '<div class="caption">'. $post['caption'] .'</div>';

	// Close things up and return
	$audio_node .= '</div>';
	return 	$audio_node;
};
function get_chat_post($post) {
	// Start Chat Node and add the title
    $chat_node = '<div class="chat post-content"><h3><a href="'. $post['local-page-url'] .'">'. $post['title'] .'</a></h3><ul>';

	// Build the chat 
    $list_class = 'odd';
    foreach($post['dialogue'] as $line) {
    	$chat_node .= '<li class="'. $list_class .'"><span class="who '. $line['name'] .'">'. $line['label'] .'</span> '. $line['phrase'] .'</li>';
    	$list_class = $list_class == 'odd' ? 'even' : 'odd'; // Switch odd/even
    }
    $chat_node .= '</ul></div>';
	return $chat_node;
};
?>
