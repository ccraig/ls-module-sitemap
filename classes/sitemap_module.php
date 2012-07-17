<?

	class SiteMap_Module extends Core_ModuleBase {
	
	
		protected function createModuleInfo() {
			return new Core_ModuleInfo(
				"Site Map",
				"Adds a sitemap to your store",
				"Limewheel Creative Inc." );				
		}
		
		public function subscribeEvents() {
			Backend::$events->addEvent('cms:onExtendPageModel', $this, 'extend_page_model');
			Backend::$events->addEvent('cms:onExtendPageForm', $this, 'extend_page_form');
		}
		
		public function extend_page_model($page, $context) {
			$page->define_column('sitemap_visible', 'Appears on site map')->defaultInvisible()->listTitle('Sitemap Visible');
		}
		
		public function extend_page_form($page, $context) {
			if ($context != 'content')
				$page->add_form_field('sitemap_visible', 'Appears on site map')->tab('Visibility')->renderAs(frm_checkbox);
		}
		
		public function listSettingsItems()	{
			$result = array(
				array(
					'icon'=>'/modules/sitemap/resources/images/sitemap.png', 
					'title'=>'Sitemap Configuration', 
					'url'=>'/sitemap/config/', 
					'description'=>'Setup the sitemap',
					'sort_id'=>300
				)
			);
			
			return $result;
		}
		
		public function register_access_points()	{
				return array(
					'sitemap.xml' =>'generate_sitemap',
					'ls_sitemap' => 'generate_sitemap'
				);
		}
		
		public function generate_sitemap() {
			$sitemap = new Sitemap_Generator();
			$sitemap->generate();
			$sitemap->send();
		}
		
	}