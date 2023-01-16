<?php

/*

Plugin Name: Fetch Instagram Posts
Plugin URI: http://framecreative.com.au
Version: 2.1.2
Author: Frame
Author URI: http://framecreative.com.au
Description: Fetch latest posts from Instagram and save them in WP

Bitbucket Plugin URI: https://bitbucket.org/framecreative/fetch-insta-posts
Bitbucket Branch: master

*/

class Fetch_Insta_Posts {


	const POST_TYPE = 'insta-post';

	private $settingsPage;
	private $account;
	private $clientID;
	private $clientSecret;
	private $tokenOption = 'fetch_insta_posts_token';
    private $tokenExpiresOption = 'fetch_insta_posts_token_expires';
	private $token;
    private $tokenExpires;
	private $forceUpdate;

	function __construct() {

		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'check_url_variables' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );

		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'init', array( $this, 'schedule_jobs' ) );

		add_action( 'fetch_insta_posts', array( $this, 'fetch_insta_posts' ) );
        add_action( 'fetch_insta_posts_refresh_token', array( $this, 'refresh_token' ) );
		add_action( 'fetch_insta_posts_clean_up', array( $this, 'clean_up' ) );
		add_action( 'fetch_insta_posts_delete_attachment', array( $this, 'delete_attachment' ) );

		add_filter( 'manage_insta-post_posts_columns' , array( $this, 'insta_post_admin_columns' ) );
		add_action( 'manage_insta-post_posts_custom_column' , array( $this, 'insta_post_admin_column_content' ), 10, 2 );

		$this->tokenHelper = 'https://instatoken.frmdv.com/new.php';
		$this->settingsPage = admin_url( 'options-general.php?page=instagram' );
		$this->clientID = 295939274780027;
		$this->clientSecret = 'ade4c0d81ec44962a8851c45c7afe2b3';

	}

	function get_login_url() {

	    $return_uri = wp_parse_url($this->settingsPage);

	    if ( ( isset($return_uri['query']) ) )
            $return_uri['query'] = wp_parse_args( $return_uri['query'] );

	    $loginUrl = add_query_arg( [
	        'client_id' => $this->clientID,
            'redirect_uri' => $this->tokenHelper,
            'scope' => 'user_profile,user_media',
            'response_type' => 'code',
            'state' => urlencode( $this->settingsPage )
        ], 'https://api.instagram.com/oauth/authorize' );

	    return $loginUrl;


    }

	function register_post_type() {

		$labels = array(
			'name'               => 'Insta Posts',
			'singular_name'      => 'Insta Post',
			'menu_name'          => 'Insta Posts',
			'name_admin_bar'     => 'Insta Post',
			'add_new'            => 'Add New',
			'add_new_item'       => 'Add New Insta Post',
			'new_item'           => 'New Insta Post',
			'edit_item'          => 'Edit Insta Post',
			'view_item'          => 'View Insta Post',
			'all_items'          => 'All Insta Posts',
			'search_items'       => 'Search Insta Posts',
			'parent_item_colon'  => 'Parent Insta Posts:',
			'not_found'          => 'No Insta Posts found.',
			'not_found_in_trash' => 'No Insta Posts found in Trash.'
		);

		$args = array(
			'public'             => false,
			'labels'             => $labels,
			'capability_type'    => 'post',
			'hierarchical'       => false,
			'menu_icon'          => 'dashicons-camera',
			'supports'           => array( 'title', 'custom-fields', 'thumbnail' ),
			'menu_position'		 => 51,
			'has_archive'        => false,
			'show_ui'			 => true
		);

		register_post_type( self::POST_TYPE, $args );

	}

	function add_settings_page() {

		add_options_page( 'Instagram', 'Instagram', 'manage_options', 'instagram', array( $this, 'draw_settings_page' ) );

	}

	function draw_settings_page() {

		$this->setup_insta();

		$fetched = new WP_Query(array(
			'post_type' => self::POST_TYPE,
			'posts_per_page' => 20
		));

		include( 'instagram_settings.php' );

	}

	function admin_notices() {

        if ( isset($_GET['page']) && $_GET['page'] == 'instagram' )
            return;

	    $token = get_option( $this->tokenOption );

	    if ( $token )
	        return;

        printf( '<div class="%1$s"><p>%2$s</p></div>',
            'notice notice-warning',
            "<strong>Instagram posts not importing</strong> - No account is currently set, you may have been logged out automatically.<br> Visit the <a href='$this->settingsPage'>settings page</a> to set your account and restart the importer." );

    }

	function check_url_variables() {

		if ( isset($_GET['page']) && $_GET['page'] == 'instagram' ) {

			$this->setup_insta();

			if ( isset($_GET['force_update']) && $_GET['force_update'] ) {
				$this->forceUpdate = true;
			}

			if ( isset($_GET['fetch_insta_posts']) && $_GET['fetch_insta_posts'] ) {
				$this->fetch_insta_posts();
				wp_redirect( $this->settingsPage );
			}

			if ( isset($_GET['insta_token']) ) {

				update_option( $this->tokenOption, $_GET['insta_token'] );
                update_option( $this->tokenExpiresOption, $_GET['token_expires'] );

				wp_redirect( $this->settingsPage );

			}

			if ( isset($_GET['remove_insta_account']) ) {

				delete_option( $this->tokenOption );
                delete_option( $this->tokenExpiresOption );

				wp_redirect( $this->settingsPage );
			}

            if ( isset($_GET['refresh_token']) ) {
                $this->refresh_token();
                wp_redirect( $this->settingsPage );
            }

		}

	}

	function setup_insta() {

		$this->token = get_option( $this->tokenOption );
        $this->tokenExpires = get_option( $this->tokenExpiresOption );

		if ( $this->token ) {

		    $this->account = $this->get_account();

		}

	}

	function refresh_token() {

	    if ( !$this->token ) {
	        return;
        }

        $url = add_query_arg( [
            'access_token' => $this->token,
            'grant_type' => 'ig_refresh_token'
        ], 'https://graph.instagram.com/refresh_access_token' );

        $data = wp_remote_get( $url );

        if ( is_wp_error( $data ) ) {
            return;
        }

        $data = json_decode( $data['body'] );

        update_option( $this->tokenOption, $data->access_token );
        update_option( $this->tokenExpiresOption, time() + intval($data->expires_in) );

    }

	function get_account() {

	    if ( !$this->token ) {
	        return null;
        }

	    $url = add_query_arg( [
            'access_token' => $this->token,
            'fields' => 'id,username'
        ], 'https://graph.instagram.com/me' );


	    $data = wp_remote_get( $url );

	    if ( is_wp_error( $data ) || $data['response']['code'] !== 200 )
	        return null;

	    return json_decode( $data['body'] );


    }

    function get_feed_url() {

	    return add_query_arg( [
	        'fields' => 'caption,id,media_type,media_url,permalink,thumbnail_url,timestamp',
            'access_token' => $this->token
        ], 'https://graph.instagram.com/me/media' );

    }

	function fetch_insta_posts() {
		$this->setup_insta();

		if ( !$this->token ) return;

		$latestInstaPost = get_posts( array(
			'post_type' => self::POST_TYPE,
			'posts_per_page' => 1,
			'post_status' => [ 'publish', 'trash' ],
			'orderby' => 'date',
			'order' => 'DESC'
		) );

		if ( isset($latestInstaPost[0]) ) {
			$latestInstaPostLink = get_post_meta( $latestInstaPost[0]->ID, 'insta_link', true );
		} else {
            $latestInstaPostLink = false;
		}

		$url = $this->get_feed_url();

		$feed = wp_remote_get( $url );

		if ( is_wp_error( $feed ) )
		    return;

		$feed = json_decode( $feed['body'] );


		foreach( $feed->data as $data ) {

			$this->save_insta_post( $data );

		}

		// Clean up - These are mostly tasks to help prevent and tidy up any duplicate images (if present)
		wp_schedule_single_event( time(), 'fetch_insta_posts_clean_up' );
	}

	function clean_up() {
		$this->clear_detached_media();
		$this->clear_duplicate_attached_images();
	}

	function save_insta_post( $data ) {
		if (!$this->forceUpdate) {
			$id = $this->find_insta_post_id( $data->id );
		}

		// If something has gone wrong and the site has stored invalid duplicates
		// we want to remove those posts and re-import from the feed
		$validPosts = $this->forceUpdate ? null : $id;
		$this->clear_posts($data, $validPosts);

		if ( ! $id ) {
			$id = $this->create_insta_post( $data );
		}

		if ( ! $id ) {
			error_log( 'Unable to create insta post: ' . json_encode( $data ) );
			return;
		}

		$media_types = [ 'IMAGE', 'CAROUSEL_ALBUM' ];

		$imageUrl = in_array( $data->media_type, $media_types ) ? $data->media_url : $data->thumbnail_url;

		update_post_meta( $id, 'insta_id', $data->id );
		update_post_meta( $id, 'insta_link', $data->permalink );
		update_post_meta( $id, 'insta_img', $imageUrl );
		update_post_meta( $id, 'insta_media_type', $data->media_type );

		$this->attach_feature_image( $id, $imageUrl, $data->caption );

        update_post_meta( $id, 'insta_desc_hash', md5($data->caption) );

		do_action( 'fetch_insta_inserted_post', $id, $data );

	}

	function create_insta_post( $data ) {

		$created = new DateTime( $data->timestamp );

		$args = array(
			'post_title' => $data->caption,
			'post_status' => 'publish',
			'post_type' => 'insta-post',
			'post_date' => $created->format('Y-m-d H:i:s')
		);

		return wp_insert_post( $args );
	}

	function clear_posts($data, $validId) {
		// This uses the post permalink to delete, as it looks like the system was storing
		// IDs differently at some point - but the unique permalink stayed the same.

		$args = [
			'post_type' => self::POST_TYPE,
			'posts_per_page' => 0,
			'post_status' => [ 'publish', 'trash' ],
			'meta_query' => [
				'relation' => 'OR',
				[
					'key' => 'insta_link',
					'value' => $data->permalink
				],
				[
					'key' => 'insta_id',
					'value' => $data->id
				]
			],
			'fields' => 'ids'
		];

		if ($validId) {
			$args['post__not_in'] = [ $validId ];
		}

		$instaQuery = new WP_Query( $args );

		if ( $instaQuery->posts && !empty( $instaQuery->posts ) ) {
			foreach( $instaQuery->posts as $post_id) {
				$images = get_attached_media('image', $post_id);

				wp_delete_post($post_id, true);
				
				foreach($images as $image) {
					wp_delete_attachment($image, true);
				}
			}
		}

		return null;
	}

	function clear_detached_media() {
		$args = array(
			'post_type'       => 'attachment',
			'post_status'     => 'inherit',
			'posts_per_page'  => -1,
			'post_parent'	  => 0,
			'fields' 		  => 'ids',
			'meta_query' => array(
				array(
					'key'     => '_source_url',
					'value'   => 'cdninstagram.com',
					'compare' => 'LIKE',
				)
			)
		);

		$detachedMedia = new WP_Query( $args );

		foreach($detachedMedia->posts as $media) {
			wp_schedule_single_event( time(), 'fetch_insta_posts_delete_attachment', array( $media ) );
		}
	}

	function delete_attachment($id) {
		wp_delete_attachment( $id, true );
	}

	function clear_duplicate_attached_images() {
		$args = [
			'post_type' => self::POST_TYPE,
			'posts_per_page' => -1,
			'post_status' => [ 'publish', 'trash' ]
		];

		$postQuery = new WP_Query($args);

		foreach($postQuery->posts as $post) {

			$args = array(
				'post_parent'    => $post->ID,
				'post_type'      => 'attachment',
				'numberposts'    => -1, // show all
				'post_status'    => 'any',
				'post_mime_type' => 'image',
				'orderby'        => 'menu_order',
				'order'          => 'ASC',
				'fields' 		 => 'ids'
		   );
	
			$images = get_posts($args);

			if (count($images) >= 2) {
				// Keep the last (latest) attachment added
				array_pop($images);

				foreach($images as $image) {
					// deletes all duplicate attachments
					wp_schedule_single_event( time(), 'fetch_insta_posts_delete_attachment', array( $image ) );
				}
			}
		}

	}

	function find_insta_post_id( $id = ''){
		if ( ! $id ) return null;

		$args = [
			'post_type' => self::POST_TYPE,
			'posts_per_page' => 1,
			'post_status' => [ 'publish', 'trash' ],
			'meta_query' => [
				[
					'key' => 'insta_id',
					'value' => $id
				]
			],
			'fields' => 'ids'
		];

		$instaQuery = new WP_Query( $args );

		if ( ! $instaQuery->posts || empty( $instaQuery->posts ) ) return null;

		return $instaQuery->posts[0];
	}

	function attach_feature_image( $id, $featureUrl, $desc = null) {

		require_once(ABSPATH . 'wp-admin/includes/media.php');
		require_once(ABSPATH . 'wp-admin/includes/file.php');
		require_once(ABSPATH . 'wp-admin/includes/image.php');

		if ($desc) {
            $post_hash = get_post_meta($id, 'insta_desc_hash', true);
            $imageExists = $post_hash && $post_hash === md5($desc);
        }

		if (!$imageExists) {

			$image = media_sideload_image($featureUrl, $id, $desc, 'id');

			if (!is_wp_error($image)) {

				$data = wp_get_attachment_metadata( $image );
				wp_update_attachment_metadata($image, $data);
				set_post_thumbnail( $id, $image );

			}

		} else {

			$data = wp_get_attachment_metadata( $imageExists->ID );
			wp_update_attachment_metadata($imageExists->ID, $data);
			set_post_thumbnail( $id, $imageExists->ID );

		}

	}


	function cron_schedules( $schedules ) {

		$schedules['qtr-hour'] = array(
			'interval' => 15 * 60, // 15 minutes * 60 seconds
			'display' => 'Qtr Hour'
		);

		return $schedules;
	}

	function schedule_jobs() {

		if ( !wp_next_scheduled('fetch_insta_posts') ) {
			wp_schedule_event( time(), 'qtr-hour', 'fetch_insta_posts' );
		}

        if ( !wp_next_scheduled('fetch_insta_posts_refresh_token') ) {
            wp_schedule_event( time(), 'daily', 'fetch_insta_posts_refresh_token' );
        }

	}

	function insta_post_admin_columns( $columns ) {

		$insertAfter = 'title';
		$position = array_search( $insertAfter, array_keys($columns) ) + 1;

		if ( $insertAfter === false ) return $columns;

		$columns = array_slice( $columns, 0, $position, true ) + [ 'featured_image' => 'Preview' ] + array_slice( $columns, $position, count($columns) - 1, true );

		return $columns;

	}

	function insta_post_admin_column_content( $column, $post_id ) {

		if ( $column == 'featured_image' ) {

			echo get_the_post_thumbnail( $post_id, array( 150, 150 ) );

		}

	}

	function convert_smart_quotes($string) { 
		$search = array(chr(145), chr(146), chr(147), chr(148), chr(151)); 
		$replace = array("'", "'", '"', '"', '-'); 

		return str_replace($search, $replace, $string); 
	} 

}

new Fetch_Insta_Posts();

