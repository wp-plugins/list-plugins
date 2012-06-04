<?php
/**
Plugin Name: List Plugins
Plugin Tag: list, plugin, active
Description: <p>Create a list of the active plugins in a page (when the shortcode <code>[list_plugins]</code> is found). </p><p> The list may contain: </p><ul><li>the name of the plugin, </li><li>the description, </li><li>the version, </li><li>the screenshots,</li><li>a link to download the zip file of the current version.</li></ul><p>Plugin developped from the orginal plugin <a href="http://wordpress.org/extend/plugins/wp-pluginsused/">WP-PluginsUsed</a>. </p><p>This plugin is under GPL licence. </p>
Version: 1.3.0
Framework: SL_Framework
Author: SedLex
Author Email: sedlex@sedlex.fr
Framework Email: sedlex@sedlex.fr
Author URI: http://www.sedlex.fr/
Plugin URI: http://wordpress.org/extend/plugins/list-plugins/
License: GPL3
*/

require_once('core.php') ; 
if (!function_exists('get_plugins'))
	require_once (ABSPATH."wp-admin/includes/plugin.php");
			

class listplugins extends pluginSedLex {
	/** ====================================================================================================================================================
	* Initialisation du plugin
	* 
	* @return void
	*/
	static $instance = false ; 
	
	var $wp_plugins ;
	var $plugins_used ;
	var $pluginsused_hidden_plugins ;

	protected function _init() {
		global $wpdb ; 
		// Configuration
		$this->pluginName = 'List Plugins' ; 
		$this->table_name = $wpdb->prefix . "pluginSL_" . get_class() ; 
		$this->tableSQL = "" ; 
		$this->path = __FILE__ ; 
		$this->pluginID = get_class() ; 
		
		//Init et des-init
		register_activation_hook(__FILE__, array($this,'install'));
		register_deactivation_hook(__FILE__, array($this,'deactivate'));
		register_uninstall_hook(__FILE__, array($this,'uninstall_removedata'));
		
		//Parametres supplementaires
		add_shortcode( "list_plugins", array($this,"list_plugins") );
		add_action('wp_print_styles', array($this,'header_init'));

		$this->wp_plugins = array();
		$this->plugins_used = array() ;
		$this->pluginsused_hidden_plugins = array() ;
	}
	
	/**
	 * Function to instantiate our class and make it a singleton
	 */
	public static function getInstance() {
		if ( !self::$instance ) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	/** ====================================================================================================================================================
	* Define the default option value of the plugin
	* 
	* @return variant of the option
	*/
	function get_default_option($option) {
		switch ($option) {
			case 'path' 		: return "sedlex_plugins" 	; break ; 
			case 'show_wordpress' : return true 	; break ; 
			case 'show_hosted' : return true 	; break ; 
			case 'show_inactive_wordpress' : return false 	; break ; 
			case 'show_inactive_hosted' : return false 	; break ; 
			case 'only_sedlex' 	: return "" 	; break ; 
			case 'html' : return "*<div class='listplugin'>
   <h4 class='listplugin_title'>%name% 
      <span class='listplugin_version'>(%version%)</span>
   </h4>
   <div class='listplugin_author'>by %author%</div>
   <div  class='listplugin_download'>%download%</div>
   <div class='listplugin_text'>%description% </div>
   <div class='listplugin_images'>
      %screen1%
      %screen2%
      %screen3%
   </div>
</div>" 	; break ; 
			case 'css' : return "*.test {}" 	; break ; 
			case 'image_size' : return 150 ; break ; 
			
		}
		return null ;
	}

	/** ====================================================================================================================================================
	* Load the configuration of the css in the header
	* 
	* @return variant of the option
	*/
	function header_init() {
		$css = $this->get_param('css') ; 
		$this->add_inline_css($css) ; 
	}
	
	/** ====================================================================================================================================================
	* The configuration page
	* 
	* @return void
	*/
	function configuration_page() {
		global $wpdb;
	
		?>
		<div class="wrap">
			<div id="icon-themes" class="icon32"><br></div>
			<h2><?php echo $this->pluginName ?></h2>
		</div>
		<div style="padding:20px;">
			<?php echo $this->signature ; ?>
			<p><?php echo __('This plugin generates and display the list of the plugins on your wordpress installation. Moreover it enables the download of the plugins.', $this->pluginID) ; ?></p>
			<p><?php echo sprintf(__('To display the list please type %s on pages/posts where you want the list to be displayed.', $this->pluginID), "<code>[list_plugins]</code>") ; ?></p>

		<?php
			// On verifie que les droits sont corrects
			$this->check_folder_rights( array() ) ; 
			
			//==========================================================================================
			//
			// Mise en place du systeme d'onglet
			//		(bien mettre a jour les liens contenu dans les <li> qui suivent)
			//
			//==========================================================================================
			
			$tabs = new adminTabs() ; 
			
			ob_start() ; 
				$params = new parametersSedLex($this, 'tab-parameters') ; 
				
				$params->add_title(__('Which plugins do you want to list?',$this->pluginID)) ; 
				$params->add_param('show_wordpress', __('Display plugins that are hosted by Wordpress:',$this->pluginID), "", "", array('show_inactive_wordpress')) ; 
				$params->add_param('show_inactive_wordpress', __('Display Wordpress plugins that are inactive:',$this->pluginID)) ; 
				$params->add_param('show_hosted', __('Display plugins that are not hosted by Wordpress:',$this->pluginID), "", "", array('show_inactive_hosted')) ; 
				$params->add_param('show_inactive_hosted', __('Display non-Wordpress plugins that are inactive:',$this->pluginID)) ; 
				$params->add_param('only_sedlex', __('Show only plugins developped by the author:',$this->pluginID)) ; 
				$params->add_comment(__('If this field is empty, all plugins (matching the previous conditions) will be displayed',$this->pluginID)) ; 
				
				$params->add_title(__('Advanced options',$this->pluginID)) ; 
				$params->add_param('image_size', __('What is the width of screenshots (in pixels):',$this->pluginID)) ; 
				$params->add_param('html', __('What is the HTML:',$this->pluginID)) ; 
				$html = "<br><code>" ; 
				$html .= "&lt;div class='listplugin'&gt;<br>
&nbsp;&nbsp;&nbsp;&lt;h4&nbsp;class='listplugin_title'&gt;%name%&nbsp;<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&lt;span&nbsp;class='listplugin_version'&gt;(%version%)&lt;/span&gt;<br>
&nbsp;&nbsp;&nbsp;&lt;/h4&gt;<br>
&nbsp;&nbsp;&nbsp;&lt;div&nbsp;class='listplugin_author'&gt;by&nbsp;%author%&lt;/div&gt;<br>
&nbsp;&nbsp;&nbsp;&lt;div&nbsp;&nbsp;class='listplugin_download'&gt;%download%&lt;/div&gt;<br>
&nbsp;&nbsp;&nbsp;&lt;div&nbsp;class='listplugin_text'&gt;%description%&nbsp;&lt;/div&gt;<br>
&nbsp;&nbsp;&nbsp;&lt;div&nbsp;class='listplugin_images'&gt;<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;%screen1%<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;%screen2%<br>
&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;%screen3%<br>
&nbsp;&nbsp;&nbsp;&lt;/div&gt;<br>
&lt;/div&gt;<br>" ; 
				$html .= "</code>" ; 
				$params->add_comment(sprintf(__('Default HTML: %s',$this->pluginID), $html)) ; 
				$params->add_param('css', __('What is the CSS:',$this->pluginID)) ; 
				$css = "<br><code>" ; 
				$css .= ".listplugin&nbsp;{<br>
&nbsp;&nbsp;&nbsp;border:&nbsp;1px&nbsp;solid&nbsp;#666666&nbsp;;&nbsp;<br>
&nbsp;&nbsp;&nbsp;padding:&nbsp;10px&nbsp;;&nbsp;<br>
&nbsp;&nbsp;&nbsp;margin:&nbsp;10px&nbsp;;&nbsp;<br>
}<br>
<br>
.listplugin_title&nbsp;{<br>
&nbsp;&nbsp;&nbsp;font-variant:&nbsp;small-caps&nbsp;;&nbsp;<br>
&nbsp;&nbsp;&nbsp;font-size:14px&nbsp;;&nbsp;<br>
}<br>
<br>
.listplugin_version&nbsp;{<br>
&nbsp;&nbsp;&nbsp;font-variant:&nbsp;small-caps&nbsp;;&nbsp;<br>
&nbsp;&nbsp;&nbsp;font-size:10px&nbsp;;&nbsp;<br>
}<br>
<br>
.listplugin_author&nbsp;{<br>
&nbsp;&nbsp;&nbsp;font-variant:&nbsp;small-caps&nbsp;;&nbsp;<br>
&nbsp;&nbsp;&nbsp;font-size:10px&nbsp;;&nbsp;<br>
&nbsp;&nbsp;&nbsp;color:#888888&nbsp;;&nbsp;<br>
}<br>
<br>
.listplugin_text&nbsp;{<br>
&nbsp;&nbsp;&nbsp;color:#888888&nbsp;;&nbsp;<br>
}<br>
<br>
.listplugin_image&nbsp;{<br>
&nbsp;&nbsp;&nbsp;text-align:center&nbsp;;&nbsp;<br>
&nbsp;&nbsp;&nbsp;border:&nbsp;1px&nbsp;solid&nbsp;#DDDDDD&nbsp;;&nbsp;<br>
&nbsp;&nbsp;&nbsp;padding:&nbsp;10px&nbsp;;&nbsp;<br>
&nbsp;&nbsp;&nbsp;margin:&nbsp;10px&nbsp;;&nbsp;<br>
}" ; 
				$css .= "</code>" ; 
				$params->add_comment(sprintf(__('Default CSS: %s',$this->pluginID), $css)) ; 
				$params->add_param('path', __('Path to store the zip file if needed:',$this->pluginID), "@[^a-zA-Z_]@") ; 
				$params->add_comment(__('Note that, if the plugin is also hosted by wordpress.org, the download link will be a link to the plugin page on wordpress.org',$this->pluginID)) ; 
				//on verifie que le path existe et sinon on le cree
				$path = WP_CONTENT_DIR."/sedlex/".$this->get_param('path') ; 
				if (is_dir($path)) {
					$params->add_comment(sprintf(__('The path %s exists',$this->pluginID)," '<code>$path</code>' ")) ; 
				} else {
					// On cree le chemin
					if (!mkdir("$path", 0700, true)) {
						$params->add_comment(sprintf(__('The path %s does not exist and cannot be created due to rights in the folder',$this->pluginID)," '<code>$path</code>' ")) ; 
					} else {
						$params->add_comment(sprintf(__('The path %s have been just created !',$this->pluginID)," '<code>$path</code>' ")) ; 
					}
				}
				$params->flush() ; 
			$tabs->add_tab(__('Parameters',  $this->pluginID), ob_get_clean() , WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_param.png") ; 	
			
			ob_start() ; 
				$plugin = str_replace("/","",str_replace(basename(__FILE__),"",plugin_basename( __FILE__))) ; 
				$trans = new translationSL($this->pluginID, $plugin) ; 
				$trans->enable_translation() ; 
			$tabs->add_tab(__('Manage translations',  $this->pluginID), ob_get_clean() , WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_trad.png") ; 	

			ob_start() ; 
				$plugin = str_replace("/","",str_replace(basename(__FILE__),"",plugin_basename( __FILE__))) ; 
				$trans = new feedbackSL($plugin, $this->pluginID) ; 
				$trans->enable_feedback() ; 
			$tabs->add_tab(__('Give feedback',  $this->pluginID), ob_get_clean() , WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_mail.png") ; 	

			ob_start() ; 
				$trans = new otherPlugins("sedLex", array('wp-pirates-search')) ; 
				$trans->list_plugins() ; 
			$tabs->add_tab(__('Other plugins',  $this->pluginID), ob_get_clean() , WP_PLUGIN_URL.'/'.str_replace(basename(__FILE__),"",plugin_basename(__FILE__))."core/img/tab_plug.png") ; 	
			
			echo $tabs->flush() ; 
			
			echo $this->signature ; ?>
		</div>
		<?php
	}

	/** ====================================================================================================================================================
	* Call when meet "[list_plugins]" in an article
	* 
	* @return string the replacement string
	*/	
	function list_plugins($attribs) {	
	
		$plugins_all = 	get_plugins() ; 	
		$plugins_to_show = array() ; 
		
		foreach($plugins_all as $url => $pa) {
			if ((strlen($this->get_param('only_sedlex'))==0)||(preg_match("/".$this->get_param('only_sedlex')."/i",$pa['Author']))) {
				if (preg_match("@wordpress\.org\/extend@",$pa['PluginURI'])) {
					if ($this->get_param('show_wordpress')) {
						if (($this->get_param('show_inactive_wordpress')) || (!$this->get_param('show_inactive_wordpress') && is_plugin_active($url)) ) {
							$plugins_to_show[$url] = $pa ; 
						} 
					}
				} else {
					if ($this->get_param('show_hosted')) {
						if (($this->get_param('show_inactive_hosted')) || (!$this->get_param('show_inactive_hosted') && is_plugin_active($url)) ) {
							$plugins_to_show[$url] = $pa ; 
						} 
					}
				}
			}
		}
		
		
		// On affiche la liste des plugins
		//--
		$all_html = "" ; 
		foreach($plugins_to_show as $url_plug => $pts) {
			$html = $this->get_param('html') ;
			$html = str_replace('%name%', $pts['Name'], $html)  ; 
			$html = str_replace('%version%', $pts['Version'], $html) ;  
			$html = str_replace('%description%', $pts['Description'], $html) ; 
			$html = str_replace('%author%', $pts['Author'], $html) ; 
			
			// The download link
			$download_link = "" ; 
			// If the URI plugin point out on wordpress.org, then we put the wordpress link to the zip file.
			if (preg_match("@wordpress\.org\/extend@",$pts['PluginURI'])) {
				$url = trim($pts['PluginURI']) ; 
				$download_link .= '<p class="download"><img src="'.WP_PLUGIN_URL."/".str_replace(basename( __FILE__),"",plugin_basename(__FILE__)).'/img/zip.jpg">'; 
				$download_link .= '<a href="'.$url.'" alt="'.sprintf(__('Download %s',$this->pluginID),' '.$pts['Name']).'">' ; 
				$download_link .= sprintf(__('Download %s (on Wordpress.org)',$this->pluginID),' '.$pts['Name']).' </a></p>'; 
			} else {
				$name_zip = Utils::create_identifier($pts['Name'].' ').$pts['Version'].".zip" ; 
				$url = WP_CONTENT_URL."/sedlex/".$this->get_param('path').'/'.$name_zip ; 
				$path = WP_CONTENT_DIR."/sedlex/".$this->get_param('path').'/'.$name_zip ; 
				// on cree le zip, s'il n'existe pas
				if (!is_file($path)) {
					$zip = new PclZip($path) ; 
					$dir = explode("/", $url_plug) ; 
					$result = $zip->create(WP_PLUGIN_DIR . "/". $dir[0], PCLZIP_OPT_REMOVE_PATH, WP_PLUGIN_DIR) ; 
					if ($result == 0) {
						$download_link .= sprintf(__("Error: %s", $this->pluginID), $zip->errorInfo(true));
					}
				}
				if (is_file($path)) {
					$download_link .= '<p class="download"><img src="'.WP_PLUGIN_URL."/".str_replace(basename( __FILE__),"",plugin_basename(__FILE__)).'/img/zip.jpg">' ; 
					$download_link .= '<a href="'.$url.'" alt="'.sprintf(__('Download %s',$this->pluginID),' '.$pts['Name']).'">' ; 
					$download_link .= sprintf(__('Download %s',$this->pluginID),' '.$pts['Name']).'</a></p>'; 
				}
			}
			$html = str_replace('%download%', $download_link, $html) ; 
			
			// Screen Shots
			$dir = explode("/", $url_plug) ; 
			$d = @scandir(WP_PLUGIN_DIR."/".$dir[0]); //Open Directory
			if (is_array($d)) {
				foreach ($d as $file) {
					if (preg_match("/screenshot-([0-9]*)\.(jpg|jpeg|png|gif)/i", $file, $match)) {
						$url = WP_PLUGIN_URL."/".$dir[0]."/".$match[0] ; 
						$screen_link = "" ; 
						$screen_link .= '<div class="listplugin_image">' ; 
						$screen_link .= '<a style="text-decoration:none;" href="'.$url.'">' ; 
						$screen_link .= '<img alt="'.$match[0].'" src="'.$url.'" style="max-width: '.$this->get_param('image_size').'px">' ; 
						$screen_link .= '</a>' ; 
						$screen_link .= '</div>' ; 
						$html = str_replace('%screen'.$match[1].'%', $screen_link, $html) ; 
					}
				}
			}
			
			for ($i=1; $i<10 ; $i++) {
				$html = str_replace('%screen'.$i.'%', "", $html) ; 
			}

			$all_html .= $html ; 
		}

		return $all_html;
	}
}

$listplugins = listplugins::getInstance();

?>