<?

	class Sitemap_Generator {
		
		private $url_count = 0; //count number of urls added on sitemap
		const max_urls = 50000; //max urls on site map (sitemap protocol limit is 50,000)
		const max_generated = 10000; //max of each of categories, products and blog posts

		private $xml = null;	//the doc
		private $urlset = null;	//the set
		private $params = null;	//the params
		
		public function generate()
		{
			$this->params = Sitemap_Params::create();
			
			$this->xml = new DOMDocument();
			$this->xml->encoding = 'UTF-8';
			
			$this->urlset = $this->xml->createElement('urlset');
			$this->urlset->setAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
			$this->urlset->setAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
			$this->urlset->setAttribute('xsi:schemaLocation', 'http://www.sitemaps.org/schemas/sitemap/0.9 http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd');
			
			try {
				$active_theme = false;
				if(Cms_Theme::is_theming_enabled())
					$active_theme = Cms_Theme::get_active_theme();
				
				if($this->params->include_navigation_hidden)
					$page_list = Cms_Page::create()->where('is_published=1')->where('sitemap_visible=1')->where('security_mode_id <> "customers"');
				else
					$page_list = Cms_Page::create()->where('is_published=1')->where('sitemap_visible=1')->where('navigation_visible=1')->where('security_mode_id <> "customers"');
				if($active_theme)
					$page_list->where('theme_id = ?', $active_theme->id);
				
				$page_list = $page_list->find_all();
				if(count($page_list)) {
					foreach($page_list as $page) {
						$page_url = site_url($page->url);
						if(substr($page_url, -1) != '/') $page_url .= '/';
						
						$this->add_url_element($page_url,  date('c', strtotime($page->updated_at?$page->updated_at:$page->created_at)), 'weekly', '0.7');
					}
				}
				
				if($this->url_count < self::max_urls && $this->params->include_categories) {
					if($this->params->include_hidden_categories)
						$category_list = Shop_Category::create()->limit(self::max_generated)->order('shop_categories.updated_at desc')->find_all();
					else
						$category_list = Shop_Category::create()->limit(self::max_generated)->order('shop_categories.updated_at desc')->where('category_is_hidden is not true')->find_all();
					foreach($category_list as $category) {
						$category_url = site_url($category->page_url($this->params->categories_path));
						
						if(substr($category_url, -1) != '/') $category_url .= '/';
						
						$this->add_url_element($category_url,  date('c', strtotime($category->updated_at?$category->updated_at:$category->created_at)), $this->params->categories_changefreq, $this->params->categories_priority);
					}
				}
				
				if($this->url_count < self::max_urls && $this->params->include_products) {
					$root_url = Phpr::$request->getRootUrl();
					$lssalestracking_installed = Core_ModuleManager::findById('lssalestracking');
					//$lssalestracking_installed = Db_DbHelper::scalar('select count(*) from core_install_history where moduleId = ?', 'lssalestracking');
					
					if($lssalestracking_installed && class_exists('LsSalesTracking_ProductManager')) {
						$product_list = new Shop_Product(null, array('no_column_init' => true, 'no_validation' => true)); 
						$product_list = $product_list->apply_filters()->where('enabled=1')->limit(self::max_generated)->order('shop_products.updated_at desc')->find_all();
					}
					else {
					$product_list = Db_DbHelper::objectArray('select sp.url_name, sp.updated_at, sp.created_at, sp.id, p.url, p.is_published from shop_products sp left outer join pages p on (sp.page_id = p.id) where sp.enabled is true and (sp.grouped is null or sp.grouped = 0) order by updated_at limit '.self::max_generated);
					}
					foreach($product_list as $product) {
						if($lssalestracking_installed && class_exists('LsSalesTracking_ProductManager')) {
							$product_url = site_url($this->params->products_path.LsSalesTracking_ProductManager::get_marketplace_product_url($product));
						}
						else {
							//$product_url = $root_url.$product->page_url($this->params->products_path);
							$product->url = $this->get_product_url($product->id, $product->url);
							if($product->url != '' && $product->is_published == 1)
								$page = $product->url;
							else
								$page = $this->params->products_path;
							$product_url = site_url($page.'/'.$product->url_name);
						}
						if(substr($product_url, -1) != '/')
							$product_url .= '/';
						
						$this->add_url_element($product_url,  date('c', strtotime($product->updated_at?$product->updated_at:$product->created_at)), $this->params->products_changefreq, $this->params->products_priority);
					}
				}
				if($this->url_count < self::max_urls && $this->params->include_blogposts) {
					$blog_post_list = Blog_Post::create()->limit(self::max_generated)->order('blog_posts.updated_at desc')->find_all();
					foreach($blog_post_list as $blog_post) {
						$blogpost_url = site_url($this->params->blogposts_path.'/'.$blog_post->url_title);
						if(substr($blogpost_url, -1) != '/') $blogpost_url .= '/';
						
						$this->add_url_element($blogpost_url,  date('c', strtotime($blog_post->published_date)), $this->params->blogposts_changefreq, $this->params->blogposts_priority);
					}
				}
				
				$wiki_installed = Core_ModuleManager::findById('wiki');
				
				if($this->url_count < self::max_urls && $wiki_installed && $this->params->include_wiki && class_exists('Wiki_Page')) {
					$wiki_page_list = Wiki_Page::create()->limit(self::max_generated)->where('is_published=1')->order('wiki_pages.updated_at desc')->find_all();
					foreach($wiki_page_list as $wiki_page) {
						$wiki_url = site_url($this->params->wiki_path.'/'.$wiki_page->url_title);
						if(substr($wiki_url, -1) != '/') $wiki_url .= '/';
						
						$this->add_url_element($wiki_url, date('c', strtotime($wiki_page->updated_at?$wiki_page->updated_at:$wiki_page->created_at)), $this->params->wiki_changefreq, $this->params->wiki_priority);
					}
				}
				
				Backend::$events->fireEvent('sitemap:onGenerateSitemap', $this,  $this->params );
			}
			catch(Sitemap_Generator_Exception $ex) {}

			$this->xml->appendChild($this->urlset);
			
		}
		
		public function send()
		{
			header("Content-Type: application/xml");
			echo $this->xml->saveXML();
		}

		public function add_url_element($page_url, $page_lastmod, $page_changefreq, $page_priority) {
			
			if($this->url_count >= self::max_urls)
				throw new Sitemap_Generator_Exception();
			
			$url = $this->xml->createElement('url');
			
			$url->appendChild($this->xml->createElement('loc', $page_url));
			$url->appendChild($this->xml->createElement('lastmod', $page_lastmod));
			$url->appendChild($this->xml->createElement('changefreq', $page_changefreq));
			$url->appendChild($this->xml->createElement('priority', $page_priority));
			
			$this->urlset->appendChild($url);
			$this->url_count++;
		}

		public function get_product_url($product_id, $default = null)
		{
			if(Cms_Theme::is_theming_enabled() && $active_theme = Cms_Theme::get_active_theme()) {
				$page = Db_DbHelper::scalar("select url from pages inner join cms_page_references cpr on (pages.id = cpr.page_id)
					where cpr.object_id=:product_id
					and object_class_name = 'Shop_Product'
					and reference_name = 'page_id'
					and page_id in (select id from pages where theme_id = :theme_id)", array('product_id' => $product_id, 'theme_id' => $active_theme->id));
				return $page;
			}
			else {
				return $default;
			}
		}
		
	}


	class Sitemap_Generator_Exception extends Exception {
		
	}
