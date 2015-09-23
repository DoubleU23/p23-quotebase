<?php
error_reporting(E_ALL);


class P23_Quotebase_Admin {
    public  $core,
            $slug,
            $tabs,
            $tabActive,
            $hook_suffix;
    
    public function __construct($core = NULL) {
        $this->core     = $core;
		$this->initSettings();
		
        # register settigs via WP Settings API
        add_action('admin_init', array($this, 'registerSettings') );
        
        # add admin submenu page
        #   output via $this->showSettingsPage()
		add_action(is_multisite() ? 'network_admin_menu' : 'admin_menu', array($this, 'createMenu'));
    }
    
	public function initSettings() {
        $this->slug     = $this->core->slug;

        $this->pages    =   array(
            'main'                  => array(  #   page (tab) main
                'title'     =>  'Main',
                'link_title'=>  'Main',
                'slug'      =>  'main',
                'sections'  => array(
                    'main'               => array(
                        'title'         => 'Main Section Title',
                        'link_title'    => 'Main',
                        'teaser'        => 'Section Teaser Main',
                        'fields'        => array(
                            array(
                                'id'        => 'main_test_field',
                                'title'     => 'Main Test Field Title',
                                'type'      => 'input',
                                'default'   => 'Main Test Field Default'
                            )
                        )
                    )
                )
            ),
            'buddypress'            => array(  #   page (tab) buddypress
                'title'     =>  'Buddypress',
                'link_title'=>  'Buddypress',
                'slug'      =>  'buddypress',
                'sections'  => array(
                    'buddypress'        => array(
                        'title'         => 'Buddypress Section Title',
                        'link_title'    => 'Buddypress',
                        'teaser'        =>  'Section Teaser Buddypress',
                        'fields'        => array(
                            array(
                                'id'        => 'buddypress_test_field',
                                'title'     => 'Buddypress Test Field Title',
                                'type'      => 'input',
                                'default'   => 'Buddypress Test Field Default'
                            )
                        )
                    )
                )
            ),
            'widgets'               => array(  #   page (tab) widtgets
                'title'     =>  'Widgets',
                'link_title'=>  'Widgets',
                'slug'      =>  'widgets',
                'sections'  => array(
                    'widgets'           => array(
                        'title'         => 'Widgets Section Title',
                        'link_title'    => 'Widgets',
                        'teaser'        =>  'Section Teaser Widgets',
                        'fields'        => array(
                            array(
                                'id'        => 'widgets_test_field',
                                'title'     => 'Widgets Test Field Title',
                                'type'      => 'input',
                                'default'   => 'Widgets Test Field Default'
                            )
                        )
                    )
                )
            )
        );
		
		return true;
	}
	
    /**
     *  creates the admin menu page
     * 
     *  @param  none
     *  @return (bool)
     */
    public function createMenu() {
        if( is_multisite() ){   # 'network_admin_menu' 
            $navParent     =    'bp-general-settings';
        }else{                  # 'admin_menu'
            $navParent     =    'options-general.php';
        }
        
        # the resulting page's hook_suffix
        $this->hook_suffix =   add_submenu_page(
                                    $navParent,                         # parent page slug
                                    'BP Quotebase',                     # page title
                                    'BP Quotebase',                     # menu link title
                                    'administrator',                    # capability required
                                    $this->slug,                        # admin page slug
                                    array($this, 'showSettingsPage')    # output function
                                );
        return ( false === $this->hook_suffix )? false : true;
    }
    

    public function registerSettings(){
        $slug		= $this->slug;
        $pages      = $this->pages;
        
        foreach( $pages as $page_key => $page  ) {
            $sections   = $page['sections'];
            $page_slug  = $this->slug.'-'.$page['slug'];
            
            # if there istn't an option for the page/tab, add it
            if( false === get_option($page_slug) )
                add_option($page_slug);
            
            # register setting sections
            foreach( $sections as $section_key => $section ) {
                $section_slug   = $page_slug.'-section';
                $section_teaser = $section['teaser'];
                
                add_settings_section( $section_slug, $section['title'], array($this, 'showSettingsSectionTeaser'), $page_slug); #array($this, 'showSectionTeaser')
                
                # Finally, we register the fields of each section
                foreach( $section['fields'] as $section_slug_suffix => $field ) {
                    add_settings_field( $field['id'], $field['title'], array($this, 'showSettingsField'), $page_slug, $section_slug, $field );
                }
            }
            register_setting($page_slug, $page_slug, array($this, 'sanitizeData'));
        }
    }

    public function sanitizeData($input) {
        # var_dump($input);
        return $input;
    }
    
	public function showSettingsSectionTeaser($args) {
        $section_id     =   $args['id'];
        
        # f.e.: 'bp-quotebase-main-section' => 'main'
        $section_key    =   str_replace('-section', '' ,str_replace($this->slug.'-', '', $section_id));
        $teaser         =   $this->pages[$this->activeTab]['sections'][$section_key]['teaser']; 
        
        if( isset($teaser) )
		  echo $teaser;
	}
    
	public function showSettingsField($field) {
        extract($field); # id, type, default
        
        $activeTab  = $this->activeTab();
        $page_slug  = $this->slug.'-'.$activeTab;
        
        $options    = get_option($page_slug);
        
        $value  = isset($options[$id])? $options[$id] : '' ;
        $name   = $page_slug.'['.$id.']';

        if( $type == 'textfield' ) {
            echo '<textfield id="'.$id.'" name="'.$name.'">'.$value.'</textarea>';
        } elseif( $type == 'checkbox' ) {
            echo '<input type="checkbox" id="'.$id.'" name="'.$name.'" value="'.$value.'">';
        } elseif( $type == 'radio' ) {
            echo '&gt;radio /&lt;';
        } elseif( $type == 'select' ) {
            echo '&gt;select /&lt;';
        } elseif( $type == 'input' ) {
            echo '<input type="text" id="'.$id.'" name="'.$name.'" value="'.$value.'" />';
        }
	}
	
	public function activeTab( $activeTab = NULL ) {
		if( isset($activeTab) ){
			$this->activeTab	= $activeTab;
		}else{
			$this->activeTab	= ( isset($_GET['tab']) && isset($this->pages[$_GET['tab']]) )? $_GET[ 'tab' ] : 'main'; # replace main with FIRST KEY OF TABS
		}
		return $this->activeTab;
	}
	
    public function showSettingsPage(){
    	$slug				= $this->slug;
        $pages				= $this->pages;
		$activeTab			= $this->activeTab();
    ?>
        <!-- Create a header in the default WordPress 'wrap' container -->
        <div class="wrap">
        
            <div id="icon-themes" class="icon32"></div>
            <h2>BP Quotebase Settings</h2>
            <?php settings_errors(); ?>
            
            <h2 class="nav-tab-wrapper">
            <?php
				foreach( $pages as $page_slug => $page )
					echo '<a href="?page='.$slug.'&tab='.$page_slug.'" class="nav-tab '.($activeTab == $page_slug ? 'nav-tab-active' : '') . '">'.$page['link_title'] . '</a>';
		    ?>
            </h2>
    
            <form method="post" action="/wp-admin/options.php">
            <?php

                settings_fields( $this->slug.'-'.$activeTab );
                do_settings_sections( $this->slug.'-'.$activeTab );

                submit_button();
            ?>
            </form>
        </div><!-- /.wrap -->
    <?php
    }

} # ENDOF class P23_Quotebase_Admin()