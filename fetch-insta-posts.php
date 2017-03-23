<?php

/*

Plugin Name: Fetch Instagram Posts
Plugin URI: http://framecreative.com.au
Version: 1.1.0
Author: Frame
Author URI: http://framecreative.com.au
Description: Fetch latest posts from Instagram and save them in WP

Bitbucket Plugin URI: https://bitbucket.org/framecreative/fetch-insta-posts
Bitbucket Branch: master

*/

require 'Instagram-PHP-API/src/Instagram.php';
use MetzWeb\Instagram\Instagram;

class Fetch_Insta_Posts {

	private $settingsPage;
	private $instagram;
	private $account;
	private $clientID;
	private $clientSecret;

	function __construct() {

		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_init', array( $this, 'check_url_variables' ) );

		add_filter( 'cron_schedules', array( $this, 'cron_schedules' ) );
		add_action( 'init', array( $this, 'schedule_fetch' ) );
		add_action( 'fetch_insta_posts', array( $this, 'fetch_insta_posts' ) );
		add_filter( 'manage_insta-post_posts_columns' , array( $this, 'insta_post_admin_columns' ) );
		add_action( 'manage_insta-post_posts_custom_column' , array( $this, 'insta_post_admin_column_content' ), 10, 2 );

		$this->tokenHelper = 'http://instatoken.frmdv.com';
		$this->settingsPage = admin_url( 'options-general.php?page=instagram' );
		$this->clientID = '6fe48e6965234e7690a13460aa543525';
		$this->clientSecret = 'ade4c0d81ec44962a8851c45c7afe2b3';

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

		register_post_type( 'insta-post', $args );

	}

	function add_settings_page() {

		add_options_page( 'Instagram', 'Instagram', 'manage_options', 'instagram', array( $this, 'draw_settings_page' ) );

	}

	function draw_settings_page() {

		$this->setup_insta();

		$fetched = new WP_Query(array(
			'post_type' => 'insta-post',
			'posts_per_page' => 20
		));

		include( 'instagram_settings.php' );
		
	}

	function check_url_variables() {

		if ( isset($_GET['page']) && $_GET['page'] == 'instagram' ) {

			$this->setup_insta();

			if ( $_GET['fetch_insta_posts'] ) {
				$this->fetch_insta_posts();
				wp_redirect( $this->settingsPage );
			}

			if ( $_GET['update_feature_images'] ) {
				$this->update_feature_images();
				wp_redirect( $this->settingsPage );
			}

			if ( isset($_GET['insta_token']) ) {
				update_option( 'fetch_insta_posts_token', $_GET['insta_token'] );
				wp_redirect( $this->settingsPage );
			}

			if ( isset($_GET['remove_insta_account']) ) {
				delete_option( 'fetch_insta_posts_token' );
				wp_redirect( $this->settingsPage );
			}

		}

	}

	function setup_insta() {

		if ( !$this->clientID || !$this->clientSecret ) return;

		$this->instagram = new Instagram(array(
			'apiKey' => $this->clientID,
			'apiSecret' => $this->clientSecret,
			'apiCallback' => add_query_arg( 'return_uri', $this->settingsPage, $this->tokenHelper ) 
		));

		$token = get_option( 'fetch_insta_posts_token' );

		if ( $token ) {

			$this->instagram->setAccessToken($token);
			$user = $this->instagram->getUser();

			if ( isset($user->data) ) {
				$this->account = $user->data;
				$this->fetchUrl = 'https://api.instagram.com/v1/users/' . $this->account->id . '/media/recent?access_token=' . $token;
			}

		}

	}

	function fetch_insta_posts() {

		$this->setup_insta();

		if ( !$this->account ) return;

		$latestInstaPost = get_posts( array(
			'post_type' => 'insta-post',
			'posts_per_page' => 1,
			'post_status' => [ 'publish', 'trash' ],
			'orderby' => 'date',
			'order' => 'DESC'
		) );

		if ( $latestInstaPost[0] ) {
			$latestInstaPost = get_post_meta( $latestInstaPost[0]->ID, 'insta_id', true );
		} else {
			$latestInstaPost = false;
		}

		$url = 'https://api.instagram.com/v1/users/' . $this->account->id . '/media/recent?access_token=' . $this->instagram->getAccessToken();

		if ( $latestInstaPost ) {
			$url = add_query_arg( 'min_id', $latestInstaPost, $url );
		}

		$feed = file_get_contents( $url );
		$feed = json_decode( $feed );


		foreach( $feed->data as $data ) {

			if ( $data->id == $latestInstaPost ) break;

			$this->create_insta_post( $data );

		}

	}

	function create_insta_post( $data ) {

		$created = new DateTime();
		$created->setTimestamp($data->created_time);

		$args = array(
			'post_title' => $data->caption->text,
			'post_status' => 'publish',
			'post_type' => 'insta-post',
			'post_date' => $created->format('Y-m-d H:i:s')
		);

		if ( $id = wp_insert_post( $args ) ) {

			update_post_meta( $id, 'insta_id', $data->id );
			update_post_meta( $id, 'insta_link', $data->link );
			update_post_meta( $id, 'insta_img', $data->images->standard_resolution->url );
			update_post_meta( $id, 'insta_img_width', $data->images->standard_resolution->width );
			update_post_meta( $id, 'insta_img_height', $data->images->standard_resolution->height );
			update_post_meta( $id, 'insta_tags', $data->tags );

			$this->attach_feature_image( $id, $data->images->standard_resolution->url );

			do_action( 'fetch_insta_inserted_post', $id, $data );

		}

	}

	function attach_feature_image( $id, $featureUrl ) {

		$featureName = basename( $featureUrl );
		$featureUpload = wp_upload_bits( $featureName, null, file_get_contents($featureUrl) );

		if (!$featureUpload['error']) {

			$featureType = wp_check_filetype($featureName, null );

			$attachment = array(
				'post_mime_type' => $featureType['type'],
				'post_title' => preg_replace('/\.[^.]+$/', '', $featureName),
				'post_content' => '',
				'post_status' => 'inherit',
				'post_author' => get_current_user_id()
			);

			$attachment_id = wp_insert_attachment( $attachment, $featureUpload['file'], $id );

			if (!is_wp_error($attachment_id)) {

				require_once( ABSPATH . "wp-admin" . '/includes/image.php');
				$attachment_data = wp_generate_attachment_metadata( $attachment_id, $featureUpload['file'] );
				wp_update_attachment_metadata($attachment_id, $attachment_data);

				set_post_thumbnail( $id, $attachment_id );

			}

		}

	}

	function update_feature_images() {

		$instaPosts = get_posts([
			'post_type' => 'insta-post',
			'nopaging' => true
		]);

		foreach ( $instaPosts as $item ) {

			if ( has_post_thumbnail( $item->ID ) ) continue;

			$imageUrl = get_post_meta( $item->ID, 'insta_img', true );

			if ( !$imageUrl ) continue;

			$this->attach_feature_image( $item->ID, $imageUrl );

		}

	}

	function cron_schedules( $schedules ) {

		$schedules['qtr-hour'] = array(
			'interval' => 15 * 60, // 15 minutes * 60 seconds
			'display' => 'Qtr Hour'
		);

		return $schedules;
	}

	function schedule_fetch() {

		if ( !wp_next_scheduled('fetch_insta_posts') ) {
			wp_schedule_event( time(), 'qtr-hour', 'fetch_insta_posts' );
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


}

new Fetch_Insta_Posts();




















