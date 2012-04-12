<?php
/*
Plugin Name: WP Short URLs
Plugin URI: http://voceconnect.com
Description: 
Version: 0.1
Author: Kevin Langley
Author URI: http://voceconnect.com
License: GPLv2 or later
*/

class WP_Short_URLs {
	
	const OPTIONS_KEY = 'wp_short_urls';
	
	public static function init(){
		add_action('admin_menu', array(__CLASS__, 'add_options_page'));
		add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
		add_action('wp_ajax_add_short_url', array(__CLASS__, 'wp_ajax_add_short_url'));
		add_action('wp_ajax_delete_bulk_short_urls', array(__CLASS__, 'wp_ajax_delete_bulk_short_urls'));
		self::check_redirect();
	}
	
	public static function enqueue_scripts(){
		wp_enqueue_script('wp-short-url', plugins_url('js/wp-short-url.js', __FILE__), array('jquery', 'wp-ajax-response'));
	}
	
	public static function check_redirect(){
		if(!is_admin()){
			$short_urls = get_option(self::OPTIONS_KEY, array());
			$current_uri = $_SERVER['REQUEST_URI'];
			foreach($short_urls as $origin => $destination){
				if(untrailingslashit($current_uri) == untrailingslashit($origin)){
					wp_redirect($destination, 301);
					exit;
				}
			}
		}
	}
	
	public static function wp_ajax_delete_bulk_short_urls(){
		$del_short_urls = $_POST['shorturls'];
		$short_urls = get_option(self::OPTIONS_KEY, array());
		foreach($del_short_urls as $del){
			if(in_array($del, array_keys($short_urls))){
				unset($short_urls[$del]);
			}
		}
		update_option(self::OPTIONS_KEY, $short_urls);
		
		$response = new WP_Ajax_Response(array(
			'what' => 'wp_short_url',
			'action' => 'delete_bulk_short_urls',
			'id' => 1,
			'position' => 1,
			'data' => 'success'
		));
		$response->send();
	}
	
	public static function wp_ajax_add_short_url(){
		$origin = preg_replace("/[^a-z0-9$-_.+!*'(),]+/i", "", $_POST['origin']);
		if(strpos($origin, '/') === false){
			$origin = '/'.$origin;
		}
		$destination = $_POST['destination'];
		
		$short_urls = get_option(self::OPTIONS_KEY, array());
		if(in_array($origin, array_keys($short_urls))){
			$response = new WP_Ajax_Response(array(
				'what' => 'wp_short_url',
				'action' => 'add_short_url',
				'id' => 0,
				'position' => 1,
				'data' => new WP_Error('shorturl_already_exists', 'There is already a short URL defined for the requested origin.')
			));
			$response->send();
		}
		
		$short_urls[$origin] = $destination;
		update_option(self::OPTIONS_KEY, $short_urls);
		
		$row = sprintf('<tr><th class="check-column tbody-child"><input type="checkbox" class="bulk_actions_check" data-origin="%s" /></th><td><a href="%s" target="_blank">%s</a></td><td><a href="%s" target="_blank">%s</a></td></tr>', $origin, site_url($origin), site_url($origin), $destination, $destination);
		$response = new WP_Ajax_Response(array(
			'what' => 'wp_short_url',
			'action' => 'add_short_url',
			'id' => 1,
			'position' => 1,
			'data' => 'success',
			'supplemental' => array('row' => $row)
		));
		$response->send();
	}
	
	public static function add_options_page(){
		add_menu_page(__('WP Short URLs', 'wp_short_urls'), __('Short URLs', 'wp_short_urls'), 'manage_options', 'wp-short-url', array(__CLASS__, 'wp_shorturl_page'));
	}
	
	public static function wp_shorturl_page(){ 
		$short_urls = get_option(self::OPTIONS_KEY, array());
	?>
		<div class="wrap">
			<div id="icon-settings" class="icon32 icon-settings"><br></div>
			<h2>WP Short URLs</h2>
			<div id="ajax-response"></div>
			<br class="clear">
			<div id="col-container">
				<div id="col-right">
					<div class="col-wrap">
						<div class="tablenav top">
							<div class="alignleft actions">
								<select name="action">
									<option value="-1" selected="selected">Bulk Actions</option>
									<option value="delete">Delete</option>
								</select>
								<input type="submit" name="" id="doaction" class="button-secondary action" value="Apply">
							</div>
							<br class="clear">
						</div>
						<form action="" method="post" class="validate">
							<table class="widefat wp_shorturls_table">
								<thead>
									<tr>
										<th class="check-column"><input type="checkbox"/></th>
										<th>Origin URL</th>
										<th>Destination URL</th>
									</tr>
								</thead>
								<tfoot>
									<tr>
										<th class="check-column"><input type="checkbox"/></th>
										<th>Origin URL</th>
										<th>Destination URL</th>
									</tr>
								</tfoot>
								<tbody>
									<?php foreach($short_urls as $origin => $destination): ?>
									<tr>
										<th class="check-column tbody-child"><input type="checkbox" class="bulk_actions_check" data-origin="<?php echo esc_attr($origin); ?>" /></th>
										<td><?php printf('<a href="%s" target="_blank">%s</a>', site_url($origin), site_url($origin)); ?></td>
										<td><?php printf('<a href="%s" target="_blank">%s</a>', esc_attr($destination), $destination); ?></td>
									</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						</form>
					</div>
				</div>
				<div id="col-left">
					<div class="col-wrap">
						<div class="form-wrap">
							<h3>Add New Short URL</h3>
							<form id="add-short-url" method="post">
								<input type="hidden" id="action" name="action" value="add_short_url"/>
								<div class="form-field form-required">
									<label for="origin">Origin URL</label>
									<input name="origin" id="origin" type="text" value="" size="40">
									<p>The relative URL you want to redirect from.<br/> (e.g. /download-wordpress)</p>
								</div>
								<div class="form-field form-required">
									<label for="destination">Destination URL</label>
									<input name="destination" id="destination" type="text" value="http://" size="40">
									<p>The full URL you want to redirect to.<br/> (e.g. http://wordpress.org/download/)</p>
								</div>
								<p class="submit"><input type="submit" name="submit" id="submit" class="button" value="Add New Short URL"></p>
							</form>
						</div>
					</div>
				</div>
			</div>
	<?php
	}
	
}
add_action('init', array('WP_Short_URLs', 'init'));