<?php 
/*
Plugin Name: Automatr
Plugin URI: http://www.poeticcoding.co.uk/plugins/automatr
Description: Automatr updates your WordPress automatically, never again do you have to worry about applying updates to WordPress.
Version: 0.3.1
Author: Poetic Coding
Author URI: http://www.poeticcoding.co.uk
License: GPL2
*/

require_once ( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' );

class automatr {
	const VERSION = '0.3';
	private $_settings;
	private $_optionsName = 'automatr';
	private $_optionsGroup = 'automatr-options';
	
	public function __construct() {
		$this->_getSettings();
		if (is_admin()) {
			add_action('admin_init', array(&$this,'registerOptions'));
			add_action('admin_menu', array(&$this,'adminMenu'));
			add_filter('plugin_action_links_' . plugin_basename(__FILE__), array(&$this, 'actionLinks'));
		}
		register_activation_hook( __FILE__, array(&$this, 'activatePlugin'));
		register_deactivation_hook( __FILE__, array(&$this, 'deactivatePlugin'));
	}
	
	public function registerOptions() {
		register_setting($this->_optionsGroup, $this->_optionsName);
	}
	
	public function activatePlugin() {
		update_option($this->_optionsName, $this->_settings);
		add_action('automatrUpdater', array($this, 'runUpdate'));
		wp_schedule_event(time(), 'hourly', 'automatrUpdater');
	}
	
	public function deactivatePlugin() {
		delete_option($this->_optionsName);
		$timestamp = wp_next_scheduled( 'automatrUpdater' );
		wp_unschedule_event($timestamp, 'automatrUpdater', array($this, 'runUpdate'));
	}
	
	public function getSetting( $settingName, $default = false ) {
		if (empty($this->_settings)) {
			$this->_getSettings();
		}
		if ( isset($this->_settings[$settingName]) ) {
			return $this->_settings[$settingName];
		} else {
			return $default;
		}
	}
	
	private function _getSettings() {
		if (empty($this->_settings)) {
			$this->_settings = get_option($this->_optionsName);
		}
		if ( !is_array( $this->_settings ) ) {
			$this->_settings = array();
		}
		$defaults = array(
			'version'	=> self::VERSION,
			'theme'	=> 0,
			'plugin'	=> 0,
			'core'	=> 0,
			'coreType' => 'minor',
			'pluginException'	=>	array(),
			'themeException'	=>	array(),
			'notification'	=>	0,
			'notificationEmail'	=>	''
		);
		$this->_settings = wp_parse_args($this->_settings, $defaults);
	}
	
	public function adminMenu() {
		add_options_page(__('Automatr'), __('Automatr'), 'manage_options', 'Automatr', array($this, 'options'));
	}
	
	public function options() {
		?>
		<div class="wrap">
		<?php screen_icon( 'tools' ); ?><h2>Automatr</h2>

		<form method="post" action="options.php">
			<?php settings_fields($this->_optionsGroup); ?>
			<?php do_settings_sections($this->_optionsGroup); ?>
			<h2>General Settings</h2>
			<table class="form-table">
				<tr valign="top">
					<td><input type="checkbox" name="<?php echo $this->_optionsName; ?>[theme]" id="<?php echo $this->_optionsName; ?>_theme" <?php echo (esc_attr($this->_settings['theme']) ? 'checked="checked"' : ''); ?> /></td>
					<td>Automatically update themes: </td>
				</tr>
				 
				<tr valign="top">
					<td><input type="checkbox" name="<?php echo $this->_optionsName; ?>[plugin]" id="<?php echo $this->_optionsName; ?>_plugin" <?php echo (esc_attr($this->_settings['plugin']) ? 'checked="checked"' : ''); ?> /></td>
					<td>Automatically update plugins: </td>
				</tr>
				
				<tr valign="top">
					<td><input type="checkbox" name="<?php echo $this->_optionsName; ?>[core]" id="<?php echo $this->_optionsName; ?>_core" <?php echo (esc_attr($this->_settings['core']) ? 'checked="checked"' : ''); ?> /></td>
					<td>Automatically update WordPress: </td>
					
				</tr>
			</table>
			
			<h2>Notifications</h2>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">Would you like to be notified by email when something is updated?</th>
					<td><input type="checkbox" name="<?php echo $this->_optionsName;?>[notification]" id="<?php echo $this->_optionsName;?>_notification" <?php echo (esc_attr($this->_settings['notification']) ? 'checked="checked"' : ''); ?> /></td>
				</tr>
				<tr valign="top">
					<th scope="row">Email Address: </th>
					<td><input type="text" name="<?php echo $this->_optionsName; ?>[notificationEmail]" id="<?php echo $this->_optionsName; ?>_notificationEmail" value="<?php echo esc_attr($this->_settings['notificationEmail']); ?>" /></td>
				</tr>
			</table>
			
			<h2>Themes</h2>
			<p>This allows you to prevent Automatr from applying updates to the following themes.</p>
			<table class="form-table">
				<?php 
				foreach(get_themes() as $theme=>$themedetails) {
					
					?>
						<tr valign="top">
							<th scope="row"><?php echo $themedetails['Name']; ?></th>
							<td><input type="checkbox" name="<?php echo $this->_optionsName;?>[themeException][<?php echo $theme; ?>]" id="<?php echo $this->_optionsName; ?>_themeException" value="<?php echo $theme; ?>" <?php echo (esc_attr($this->_settings['themeException'][$theme]) ? 'checked="checked"' : ''); ?> /></td>
						</tr>
					<?php
				}
				?>
			</table>
			
			<h2>Plugins</h2>
			<p>This allows you to prevent Automatr from applying updates to the following plugins.</p>
			<table class="form-table">
				<?php 
				foreach(get_plugins() as $plugin=>$plugindetails) {
					?>
						<tr valign="top">
							<th scope="row"><?php echo $plugindetails['Name']; ?></th>
							<td><input type="checkbox" name="<?php echo $this->_optionsName;?>[pluginException][<?php echo $plugin; ?>]" id="<?php echo $this->_optionsName; ?>_pluginException" value="<?php echo $plugin ?>" <?php echo (esc_attr($this->_settings['pluginException'][$plugin]) ? 'checked="checked"' : ''); ?> /></td>
						</tr>
					<?php
				}
				?>
			</table>
			
			<h2>WordPress Update Options</h2>
			<p><strong>All</strong> - Selecting this option will apply updates that are released for wordpress.</p>
			<p><strong>Minor</strong> - This option will only apply minor updates (those that tend to include security related fixes), and not update to the latest major version.</p>
			<table class="form-table">
				<tr valign="top">
					<th scope="row">All</th>
					<td><input type="radio" id="<?php echo $this->_optionsName;?>_coreType" name="<?php echo $this->_optionsName;?>coreType" <?php echo (esc_attr($this->_settings['coreType'])=='all' ? 'checked="checked"' : ''); ?> /></td>
				</tr>
					 
				<tr valign="top">
					<th scope="row">Minor Updates</th>
					<td><input type="radio" id="<?php echo $this->_optionsName;?>_coreType" name="<?php echo $this->_optionsName;?>coreType" <?php echo (esc_attr($this->_settings['coreType'])=='minor' ? 'checked="checked"' : ''); ?> /></td>
				</tr>
			</table>
			
			<?php submit_button(); ?>

		</form>
		</div>
		<?php
	}

	public function checkPluginCompatibility($plugin) {
		require_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
		$info = plugins_api('plugin_information', array('fields' => array('tested' => true, 'requires' => true, 'rating' => false, 'downloaded' => false, 'downloadlink' => false, 'last_updated' => false, 'homepage' => false, 'tags' => false, 'sections' => false, 'compatibility' => false, 'author' => false, 'author_profile' => false, 'contributors' => false, 'added' => false), 'slug' => $plugin ));
		if (!is_wp_error ($info)) {
			return $info->tested;
		} else {
			return false;
		}
	}

	public function runUpdate() {
		$plugin = $this->runPluginUpdate();
		$theme = $this->runThemeUpdate();
		$core = $this->runCoreUpdate();
		$message = '<ul>';
		if(is_array($plugin)) {
			foreach($plugin as $messages) {
				$message .= "<li>$messages</li>";
			}
		}
		if(is_array($theme)) {
			foreach($theme as $messages) {
				$message .= "<li>$messages</li>";
			}
		}
		if(is_array($core)) {
			foreach($core as $messages) {
				$message .= "<li>$messages</li>";
			}
		}
		$message .= "</ul>";
		// Proccess the email to send here
		if($this->_settings['notification']) {
			if($this->_settings['notificationEmail']!='') {
				$mail = wp_mail($this->_settings['notificationEmail'], 'Automatr Report', $message, 'text/html');
			} 
		}
	}

	private function runPluginUpdate() {
		if($this->_settings['plugin']) {
			$pluginupdates = $this->preparePluginUpdates();
			$plugins = get_plugins();
			if(is_array($pluginupdates)) {
				$messages[] = 'Plugin updates to process';
				if(is_array($this->_settings['pluginException'])) {
					$messages[] =  "Processing plugin exception list";
					foreach($this->_settings['pluginException'] as $exception) {
						$pluginupdates=array_diff($pluginupdates, array($exception));
						$messages[] = "Removing $exception from the list of updates";	
					}
				}
				foreach($pluginupdates as $updater) {
					$messages[] = "Preparing update for $updater";
					if(version_compare(get_bloginfo('version'), $this->checkPluginCompatibility($plugins[$updater]['Name'])) < 1) {
						$messages[] = "Passed compatibility check";
						$messages[] = $this->updatePlugin($updater);
					} else {
						$messages[] = "Failed compatibility check";
					}
				}
			} else {
				$messages[] = "No plugin updates to process";
			}
			return $messages;
		}
	}
	
	private function runThemeUpdate() {
		if($this->_settings['theme']) {
			$themeupdates = $this->prepareThemeUpdates();
			if(is_array($themeupdates)) {
				$messages[] = 'Theme updates to procces';
				if(is_array($this->_settings['themeException'])) {
					$messages[] = "Process theme exception list";
					foreach($this->_settings['themeException'] as $exception) {
						$themeupdates=array_diff($themeupdates, array($exception));
						$messages[] = "Removing $exception from the list of updates";
					}
				}
				foreach($themeupdates as $updater) {
					$messages[] = "Running update for $updater";
					$messages[] = $this->updateTheme($updater);
				}
			} else {
				$messages[] = "No theme updates to process";
			}
			return $messages;
		}
	}
	
	private function runCoreUpdate() {
		if($this->_settings['core']) {
			$this->prepareCoreUpdate();
			return $this->updateCore();
		}
	}
	
	private function preparePluginUpgrader() {
		$pluginUpgrader = new automatrUpdatePluginSkin();
		return $pluginUpgrader;
	}
	
	private function prepareThemeUpgrader() {
		$themeUpgrader = new automatrUpdateThemeSkin();
		return $themeUpgrader;
	}
	
	private function prepareCoreUpgrader() {
		$coreUpgrader = new automatrUpdateCoreSkin();
		return $coreUpgrader;
	}
	
	private function preparePluginUpdates() {
		// Get a list of all the plugins
		require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
		
		$plugins = get_plugins();
		$active  = get_option( 'active_plugins', array() );
		if ( function_exists( 'get_site_transient' ) ) {
			delete_site_transient( 'update_plugins' );
		} else {
			delete_transient( 'update_plugins' );
		}
		wp_update_plugins();
		if( function_exists( 'get_site_transient' ) && $transient = get_site_transient( 'update_plugins' ) ) {
			$current = $transient;
		} elseif( $transient = get_transient( 'update_plugins' ) ) {
			$current = $transient;
		} else {
			$current = get_option( 'update_plugins' );
		}
		
		foreach ( (array) $plugins as $plugin_file => $plugin ) {
			$new_version = isset( $current->response[$plugin_file] ) ? $current->response[$plugin_file]->new_version : null;
			if ( $new_version ) {
				$updates[] = $plugin_file;		
			}
		}
		return $updates;
	}
	
	private function prepareThemeUpdates() {
		require_once( ABSPATH . '/wp-admin/includes/theme.php' );
		$themes = get_themes();
		$active  = get_option( 'current_theme' );
		wp_update_themes();
		if ( function_exists( 'get_site_transient' ) && $transient = get_site_transient( 'update_themes' ) ) {
			$current = $transient;
		} elseif ( $transient = get_transient( 'update_themes' ) ) {
			$current = $transient;
		} else {
			$current = get_option( 'update_themes' );
		}
		foreach ( (array) $themes as $theme ) {
			$new_version = isset( $current->response[$theme['Template']] ) ? $current->response[$theme['Template']]['new_version'] : null;
			if($new_version) {
				$updates[] = $theme['Name'];
			}
		}
		return $updates;
	}

	private function prepareCoreUpdate() {
		// force refresh
		wp_version_check();
		$updates = get_core_updates();
		return $updates;
	}
	
	private function updatePlugin($plugin) {
		include_once ( ABSPATH . 'wp-admin/includes/admin.php' );
		$skin = $this->preparePluginUpgrader();
		$upgrader = new Plugin_Upgrader( $skin );
		$is_active = is_plugin_active( $plugin );
		wp_update_plugins();
		ob_start();
		$result = $upgrader->upgrade( $plugin );
		$data = ob_get_contents();
		ob_clean();
		if ( ( ! $result && ! is_null( $result ) ) || $data ) {
			return array( 'status' => 'error', 'error' => 'file_permissions_error' );
		} elseif ( is_wp_error( $result ) ) {
			return array( 'status' => 'error', 'error' => $result->get_error_code() );
		}
		if ( $skin->error ) {
			return array( 'status' => 'error', 'error' => $skin->error );
		}
		if ( $is_active ) {
			$request = $this->pluginActivator($plugin);
			if($request) {
				return 'Plugin updated and activated';
			} else {
				return $request;
			}
		}
		return 'Update successful';
	}

	private function updateTheme( $theme ) {
		include_once ( ABSPATH . 'wp-admin/includes/admin.php' );
		$skin = $this->prepareThemeUpgrader();
		$upgrader = new Theme_Upgrader( $skin );
		ob_start();
		$result = $upgrader->upgrade( $theme );
		$data = ob_get_contents();
		ob_clean();
		if ( ( ! $result && ! is_null( $result ) ) || $data ) {
			return array( 'status' => 'error', 'error' => 'file_permissions_error' );
		} elseif ( is_wp_error( $result ) ) {
			return array( 'status' => 'error', 'error' => $result->get_error_code() );
		}
		if ( $skin->error ) {
			return array( 'status' => 'error', 'error' => $skin->error );
		}
		return array( 'status' => 'success' );
	}

	private function pluginActivator($plugin) {
		include_once ABSPATH . 'wp-admin/includes/plugin.php';
		$result = activate_plugin( $plugin );
		if ( is_wp_error( $result ) ) {
			return "Plugin activation failed due to: ".$result->get_error_code();
		} else {
			return true;	
		}	
	}
	
	private function updateCore() {
		include_once ( ABSPATH . 'wp-admin/includes/admin.php' );
		include_once ( ABSPATH . 'wp-admin/includes/upgrade.php' );
		include_once ( ABSPATH . 'wp-includes/update.php' );
		// force refresh
		wp_version_check();
	
		$updates = get_core_updates();
		
		if ( is_wp_error( $updates ) || ! $updates )
			return false;
		
		$update = reset( $updates );
		// Check an update is an update
		if(!version_compare(get_bloginfo('version'), $update->current, '<' )) {
			return array('No new core update available');
		}
		
		if ( ! $update )
			return false;
		
		
		$skin = new automatrUpdateCoreSkin();
		$upgrader = new Core_Upgrader( $skin );
		$continue = false;
		if(substr_count($updates[0]->current, '.')>=2) {
			if($this->_settings['coreType']=='minor') {
				$continue = true;
			}
		} else {
			if($this->_settings['coreType']=='all') {
				$continue = true;
			}
		}
		
		if(!$continue) {
			return array('The update did not match your settings');
		}

		$result = $upgrader->upgrade($update);
	
		if ( is_wp_error( $result ) )
			return array($result);
	
		global $wp_current_db_version, $wp_db_version;
	
		require( ABSPATH . WPINC . '/version.php' );
		
		wp_upgrade();
		return array('Wordpress has been upgraded');
	}
	
	public function actionLinks($links) {
		return array_merge(
			array(
				'settings' => '<a href="'.admin_url( 'options-general.php?page=automatr' ).'">Settings</a>'
			),
			$links
		);
	}
}


class automatrUpdatePluginSkin extends Plugin_Installer_Skin {
	var $feedback;
	var $error;
	function error( $error ) {
		$this->error = $error;
	}
	function feedback( $feedback ) {
		$this->feedback = $feedback;
	}
	function before() {
	}
	function after() {
	}
	function header() {
	}
	function footer() {
	}
}

class automatrUpdateThemeSkin extends Theme_Installer_Skin {
	var $feedback;
	var $error;
	function error( $error ) {
		$this->error = $error;
	}
	function feedback( $feedback ) {
		$this->feedback = $feedback;
	}
	function before() {
	}
	function after() {
	}
	function header() {
	}
	function footer() {
	}
}

class automatrUpdateCoreSkin extends WP_Upgrader_Skin {
	var $feedback;
	var $error;
	function error( $error ) {
		$this->error = $error;
	}
	function feedback( $feedback ) {
		$this->feedback = $feedback;
	}
	function before() { }

	function after() { }

	function header() { }

	function footer() { }

}

$automatr = new automatr();
?>