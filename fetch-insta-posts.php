<?php

/*

Plugin Name: Fetch Instagram Posts
Plugin URI: http://framecreative.com.au
Version: 1.0.0
Author: Frame
Author URI: http://framecreative.com.au
Description: Fetch latest posts from Instagram and save them in WP

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
			'public'             => true,
			'labels'             => $labels,
			'capability_type'    => 'post',
			'hierarchical'       => false,
			'menu_icon'          => 'dashicons-camera',
			'supports'           => array( 'title', 'custom-fields' ),
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

		if ( $_GET['page'] == 'instagram' ) {

			$this->setup_insta();

			if ( $_GET['fetch_insta_posts'] ) {
				$this->fetch_insta_posts();
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
			}

		}

	}

	function fetch_insta_posts() {

		$this->setup_insta();

		if ( !$this->account ) return;

		$latestInstaPost = get_posts( array(
			'post_type' => 'insta-post',
			'posts_per_page' => 1,
			'post_status' => 'publish',
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

		foreach( $feed->data as $post ) {

			if ( $post->id == $latestInstaPost ) break;

			$created = new DateTime();
			$created->setTimestamp($post->created_time);

			$args = array(
				'post_title' => $post->caption->text,
				'post_status' => 'publish',
				'post_type' => 'insta-post',
				'post_date' => $created->format('Y-m-d H:i:s')
			);
			

			if ( $id = wp_insert_post( $args ) ) {

				update_post_meta( $id, 'insta_id', $post->id );
				update_post_meta( $id, 'insta_link', $post->link );
				update_post_meta( $id, 'insta_img', $post->images->standard_resolution->url );
				update_post_meta( $id, 'insta_caption', $post->caption->text );
				update_post_meta( $id, 'insta_tags', $post->tags );
//				wp_set_object_terms( $id, 'instagram', 'news-type' );

				do_action( 'fetch_insta_inserted_post', $id, $post );

			}

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


}

new Fetch_Insta_Posts();




















