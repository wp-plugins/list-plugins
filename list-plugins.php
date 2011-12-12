<?php
/**
Plugin Name: List Plugins
Description: <p>Create a list of the active plugins in a page (when the shortcode <code>[list_plugins]</code> is found). </p><p> The list may contain: <ul><li>the name of the plugin, </li><li>the version, </li><li>the screenshots (up to 3 images),</li><li>a link to download the zip file of the current version.</li></ul><p>Plugin developped from the orginal plugin <a href="http://wordpress.org/extend/plugins/wp-pluginsused/">WP-PluginsUsed</a>. </p><p>This plugin is under GPL licence. </p>
Version: 1.1.2
Framework: SL_Framework
Author: SedLex
Author Email: sedlex@sedlex.fr
Framework Email: sedlex@sedlex.fr
Author URI: http://www.sedlex.fr/
Plugin URI: http://wordpress.org/extend/plugins/list-plugins/
License: GPL3
*/

require_once('core.php') ; 

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
		register_deactivation_hook(__FILE__, array($this,'uninstall'));
		
		//Parametres supplementaires
		add_shortcode( "list_plugins", array($this,"list_plugins") );

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
			case 'download' 	: return true 	; break ; 
			case 'path' 		: return "sedlex_plugins" 	; break ; 
			case 'show_version' : return true 	; break ; 
			case 'show_screen' 	: return true 	; break ; 
			case 'only_sedlex' 	: return "" 	; break ; 
		}
		return null ;
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
			<p><?php echo __('This plugin generates and display the list of the active plugins on your wordpress installation. Moreover it enables the download of the plugins.', $this->pluginID) ; ?></p>

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
				$params->add_title(__('Do you want the listed plugins to be downloadable?',$this->pluginID)) ; 
				$params->add_param('download', __('Yes/No:',$this->pluginID)) ; 
				$params->add_param('path', __('Path to store the zip file:',$this->pluginID), "@[^a-zA-Z_]@") ; 
				$params->add_comment(__('Note that, if the plugin is also hosted by wordpress.org, the downlad link will be a link to the plugin page on wordpress.org',$this->pluginID)) ; 
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
				
				$params->add_title(__('For the listed plugins:',$this->pluginID)) ; 
				$params->add_param('show_version', __('Show the version:',$this->pluginID)) ; 
				$params->add_comment(__('If you choose the plugins to be downloadable, the version will appear in the zip name whatever the option you choose here!',$this->pluginID)) ; 
				$params->add_param('show_screen', __('Show the embedded screenshots:',$this->pluginID)) ; 
				$params->add_param('only_sedlex', __('Show only plugins developped by the author:',$this->pluginID)) ; 
				$params->add_comment(__('If this field is empty, all plugins will be displayed',$this->pluginID)) ; 
				
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
		
		$plugins_used = array() ; 
		
		// On liste les differents plugins
		//--
		
		$active_plugins = get_option('active_plugins');
		
		$plugin_root = WP_PLUGIN_DIR;
		$plugins_dir = @dir($plugin_root);
		if($plugins_dir) {
			while(($file = $plugins_dir->read()) !== false) {
				if (substr($file, 0, 1) == '.') {
					continue;
				}
				if (is_dir($plugin_root.'/'.$file)) {
					$plugins_subdir = @ dir($plugin_root.'/'.$file);
					if ($plugins_subdir) {
						while (($subfile = $plugins_subdir->read()) !== false) {
							if (substr($subfile, 0, 1) == '.') {
								continue;
							}
							if (substr($subfile, -4) == '.php') {
								$plugin_files[] = "$file/$subfile";
							}
						}
					}
				} else {
					if (substr($file, -4) == '.php') {
						$plugin_files[] = $file;
					}
				}
			}
		}
		if (!$plugins_dir || !$plugin_files) {
			return $wp_plugins;
		}
		foreach ($plugin_files as $plugin_file) {
			if (!is_readable("$plugin_root/$plugin_file")) {
				continue;
			}
			$plugin_data = $this->get_plugins_data("$plugin_root/$plugin_file");
			if (empty($plugin_data['Plugin_Name'])) {
				continue;
			}
			$wp_plugins[plugin_basename($plugin_file)] = $plugin_data;
		}
		
		uasort($wp_plugins, create_function('$a, $b', 'return strnatcasecmp($a["Plugin_Name"], $b["Plugin_Name"]);'));
				
		// On filtre les sorties des differents plugins
		//--
		
		$plugins_allowedtags = array('a' => array('href' => array()),'code' => array(), 'p' => array() ,'ul' => array() ,'li' => array() ,'strong' => array());
		foreach($wp_plugins as $plugin_file => $plugin_data) {
			if(!in_array($plugin_data['Plugin_Name'], $this->pluginsused_hidden_plugins)) {
				$plugin_data['Plugin_Name'] = wp_kses($plugin_data['Plugin_Name'], $plugins_allowedtags);
				$plugin_data['Plugin_URI'] = wp_kses($plugin_data['Plugin_URI'], $plugins_allowedtags);
				$plugin_data['Description'] = wp_kses($plugin_data['Description'], $plugins_allowedtags);
				
				$plugin_data['Author'] = wp_kses($plugin_data['Author'], $plugins_allowedtags);
				$plugin_data['Author_URI'] = wp_kses($plugin_data['Author_URI'], $plugins_allowedtags);
				
				$plugin_data['Version'] = wp_kses($plugin_data['Version'], $plugins_allowedtags);
				
				if (!empty($active_plugins) && in_array($plugin_file, $active_plugins)) {
					$plugins_used[] = $plugin_data;
				}
			}
		}		
		
		// On affiche la liste des plugins
		//--
		foreach($plugins_used as $active_plugins) {
			if ((strlen($this->get_param('only_sedlex'))==0)||(preg_match("/".$this->get_param('only_sedlex')."/i",$active_plugins['Author']))) {
				
				$temp .= '<hr/><h4>' ; 
				$temp .= '<a href="'.$active_plugins['Plugin_URI'].'" title="'.$active_plugins['Plugin_Name'].'">' ; 
				$temp .= $active_plugins['Plugin_Name'] ; 
				if ($this->get_param('show_version')) {
					$temp .= ' '.$active_plugins['Version'] ; 
				}
				$temp .= '</a></h4>' ; 
				$temp .= '<p><strong>&raquo; '.sprintf(__('Created by %s',$this->pluginID),' '.$active_plugins['Author']).'</strong></p>' ; 
				$temp .= '<p>'.$active_plugins['Description'].'</p>';
				
				// The download link
				if ($this->get_param('download')) {
					// If the URI plugin point out on wordpress.org, then we put the wordpress link to the zip file.
					if (preg_match("@wordpress\.org\/extend@",$active_plugins['Plugin_URI'])) {
						$url = trim($active_plugins['Plugin_URI']) ; 
						$temp .= '<p class="download"><img src="'.WP_PLUGIN_URL."/".str_replace(basename( __FILE__),"",plugin_basename(__FILE__)).'/img/zip.jpg">'; 
						$temp .= '<a href="'.$url.'" alt="'.sprintf(__('Download %s',$this->pluginID),' '.$active_plugins['Plugin_Name']).'">' ; 
						$temp .= sprintf(__('Download %s (on Wordpress.org)',$this->pluginID),' '.$active_plugins['Plugin_Name']).' </a></p>'; 
					} else {
						$name_zip = Utils::create_identifier($active_plugins['Plugin_Name'].' ').$active_plugins['Version'].".zip" ; 
						$url = WP_CONTENT_URL."/sedlex/".$this->get_param('path').'/'.$name_zip ; 
						$path = WP_CONTENT_DIR."/sedlex/".$this->get_param('path').'/'.$name_zip ; 
						// on cree le zip, s'il n'existe pas
						if (!is_file($path)) {
							$zip = new PclZip($path) ; 
							echo WP_PLUGIN_DIR . $active_plugins['Dir_Plugin'] ; 
							$result = $zip->create(WP_PLUGIN_DIR . "/". $active_plugins['Dir_Plugin'], PCLZIP_OPT_REMOVE_PATH, WP_PLUGIN_DIR) ; 
							if ($result == 0) {
								$temp .= sprintf(__("Error: %s", $this->pluginID), $zip->errorInfo(true));
  							}
						}
						if (is_file($path)) {
							$temp .= '<p class="download"><img src="'.WP_PLUGIN_URL."/".str_replace(basename( __FILE__),"",plugin_basename(__FILE__)).'/img/zip.jpg">' ; 
							$temp .= '<a href="'.$url.'" alt="'.sprintf(__('Download %s',$this->pluginID),' '.$active_plugins['Plugin_Name']).'">' ; 
							$temp .= sprintf(__('Download %s',$this->pluginID),' '.$active_plugins['Plugin_Name']).'</a></p>'; 
						}
					}
				}
				
				// Screen Shot
				if ($this->get_param('show_screen')) {
					// capture d'ecran
					$temp .= "<div class='screenshot'>" ; 
					for ($i=1; $i<4 ; $i++) {
						$d = dir(WP_PLUGIN_DIR."/".$active_plugins['Dir_Plugin']); //Open Directory
						while (false!== ($file = $d->read())) {	
							if (preg_match("/screenshot-".$i."\.(jpg|jpeg|png|gif)/i", $file, $match)) {
								$url = WP_PLUGIN_URL."/".$active_plugins['Dir_Plugin']."/".$match[0] ; 
								$temp .= '<span >' ; 
								$temp .= '<a href="'.$url.'">' ; 
								$temp .= '<img alt="'.$match[0].'" src="'.$url.'" width="150">' ; 
								$temp .= '</a>' ; 
								$temp .= '</span>' ; 
							}
						}
						$d->close(); // Close Directory
					}
					$temp .= "</div>" ; 
					$temp .= "<div class='space'>&nbsp;" ; 
					$temp .= "</div>" ; 
				}
				

				
			}
		}

		return $temp;
	}
	
	
	
	### Function: WordPress Get Plugin Data
	function get_plugins_data($plugin_file) {
		$plugin_data = implode('', file($plugin_file));
		preg_match("|Plugin Name:(.*)|i", $plugin_data, $plugin_name);
		preg_match("|Plugin URI:(.*)|i", $plugin_data, $plugin_uri);
		preg_match("|Description:(.*)|i", $plugin_data, $description);
		preg_match("|Author:(.*)|i", $plugin_data, $author_name);
		preg_match("|Author URI:(.*)|i", $plugin_data, $author_uri);
		if (preg_match("|Version:(.*)|i", $plugin_data, $version)) {
			$version = trim($version[1]);
		} else {
			$version = '';
		}
		$plugin_name = trim($plugin_name[1]);
		$plugin_uri = trim($plugin_uri[1]);
		$description = wptexturize(trim($description[1]));
		$author = trim($author_name[1]);
		$author_uri = trim($author_uri[1]);
		return array('Dir_Plugin'=>basename(dirname($plugin_file)) , 'Plugin_Name' => $plugin_name, 'Plugin_URI' => $plugin_uri, 'Description' => $description, 'Author' => $author, 'Author_URI' => $author_uri, 'Version' => $version);
	}
}

$listplugins = listplugins::getInstance();

?>