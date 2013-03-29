<?php

// Start the updater, set whats required, set to dev environment
$theme_updater = new Pronamic_Theme_Updater();
$theme_updater
		->set_api_url( 'http://themes.pronamic.nl/1.0/' )
		->set_user_agent( 'PronamicWordpressThemeUpdate' )
		->dev_environment();


/**
 * Class that handles Theme Updating
 *
 * Uses themes_api filter, to reference the pronamic theme updater server.
 *
 * @see http://github.com/pronamic/pronamic-theme-updater-server
 *
 * ===================
 * Instructions
 * ===================
 *
 * 1. PREFIX THE CLASS WITH A UNIQUE NAME
 * 2. Set the api url above
 * 3. Set the user agent you want to require on your server.
 * 4. Change those values on the server.
 * 5. Include this file in your themes functions.php
 * 6. Done.
 *
 * @see http://pronamic.nl/plugins/pronamic-theme-updater
 *
 * ===================
 *
 * @author Leon Rowland <leon@rowland.nl>
 * @copyright (c) 2013, Leon Rowland
 * @license GPL
 * @version 1.0
 */
class Pronamic_Theme_Updater {
	private $api_url = '';
	private $user_agent = '';

	public function __construct() {
		add_filter( 'themes_api', array( $this, 'themes_api' ), 10, 3 );
		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'check_for_update' ) );
	}

	/**
	 * Sets the url of the Server.
	 *
	 * @see http://github.com/pronamic/pronamic-theme-updater-server#server
	 *
	 * @access public
	 * @param string $api_url
	 * @return \Pronamic_Theme_Updater
	 */
	public function set_api_url( $api_url ) {
		$this->api_url = $api_url;
		return $this;
	}

	/**
	 * Sets the user agent, that the server is looking for
	 *
	 * @see http://github.com/pronamic/pronamic-theme-updater-server#server
	 *
	 * @access public
	 * @param string $user_agent
	 * @return string
	 */
	public function set_user_agent( $user_agent ) {
		$this->user_agent = $user_agent;
		return $this;
	}

	/**
	 * Sets the transient to null, will make the request for information
	 * on theme version on EVERY page load.  Only have this method used
	 * when in development.
	 *
	 * @access public
	 * @return void
	 */
	public function dev_environment() {
		set_site_transient('update_themes', null);
	}

	/**
	 * Replaces the theme update server with your own.
	 *
	 * @todo requires a check to ensure it works with themes from the official repo
	 *
	 * @filter themes_api
	 *
	 * @access public
	 * @param object $checked_data
	 * @return $checked_data
	 */
	public function check_for_update( $checked_data ) {
		// Get theme information
		$theme_slug = get_option( 'template' );
		$theme_data = wp_get_theme( $theme_slug );
		$theme_version = $theme_data->Version;

		// Requested parameters for the server
		$request_body = array(
			'slug' => $theme_slug,
			'version' => $theme_version
		);

		// Make the request
		$request = wp_remote_post( $this->api_url, array(
			'body' => array(
				'action' => 'theme_update',
				'request' => serialize( $request_body )
			),
			'user-agent' => $this->user_agent
		) );

		$res = array();
		if ( ! is_wp_error( $request ) ) {
			$res = maybe_unserialize( wp_remote_retrieve_body( $request ) );
		}

		if ( ! empty( $res ) )
			$checked_data->response[$theme_slug] = $res;

		return $checked_data;
	}

	/**
	 * Sends the request to the update server, to get the file and update
	 * the theme.
	 *
	 * @access public
	 * @param false $false ?
	 * @param string $action
	 * @param array $args
	 * @return $result OR \WP_Error
	 */
	public function themes_api( $false, $action, $args ) {
		// Make the request
		$request = wp_remote_post( $this->api_url, array(
			'body' => array(
				'action' => $action,
				'request' => serialize( $args )
			)
		) );

		if ( is_wp_error( $request ) ) {
			$res = new WP_Error('themes_api_failed', __( 'An unexpected error occurred. Something may be wrong with WordPress.org or this server&#8217;s configuration. If you continue to have problems, please try the <a href="http://wordpress.org/support/">support forums</a>.' ), $request->get_error_message() );
		} else {
			$res = maybe_unserialize( wp_remote_retrieve_body( $request ) );
			if ( ! is_object( $res ) && ! is_array( $res ) )
				$res = new WP_Error('themes_api_failed', __( 'An unexpected error occurred. Something may be wrong with WordPress.org or this server&#8217;s configuration. If you continue to have problems, please try the <a href="http://wordpress.org/support/">support forums</a>.' ), wp_remote_retrieve_body( $request ) );
		}

		return $res;
	}
}

