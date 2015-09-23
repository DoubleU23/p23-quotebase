<?php

class BP_Quotebase {
    public  $path   = BP_QUOTEBASE_PLUGIN_PATH,
            $slug   = BP_QUOTEBASE_PLUGIN_SLUG,
            $admin  = null;
    
    public function __construct( $args = array() ) {
        
        add_action('bp_init', array($this, 'initActions'));
    }
    
    public function initActions() {
        if( is_super_admin() ) # Also returns True if network mode is disabled and the user is an admin.
            $this->initAdminPage();
        
        $this->initSettings();
        $this->registerPosttype();
        
        if( is_super_admin() ) {
            $this->registerFilters();
        }
    }

    public function initSettings() {
        
    }
    
    public function getDefaults() {
        $defaults   = array();
        return $defaults;
    }
    
    /**
     *  initAdminPage()
     * 
     *  include and init the admin class only if necessary (for a better performance)
     * 
     *  @uses   BP_Quotebase_Admin
     *  @param  none
     *  @return none
     */
    public function initAdminPage(){        
        require           $this->path . 'inc/bp-quotebase-admin.class.php';
        if( !isset($this->admin) )
            $this->admin    = new BP_Quotebase_Admin($this);
            
        return true;
    }
    
    public function registerPosttype() {        
        
        register_post_type('p23_quote', array(
            'menu_position'         => 5,
            'label'                 => 'Zitate',
            'singular_label'        => 'Zitat',
            'labels'                => $this->getLabels('posttype'),
            'public'                => true,
            'show_ui'               => true, // UI in admin panel
            'has_archive'           => 'zitate',
            'capability_type'       => 'p23_quotes',
            'hierarchical'          => false,
            'supports'              => array( 'editor', 'post-formats'), # title, author, thumbnail
            'taxonomies'            => array(),
            'rewrite'               => array('slug' => 'zitate'),   // Permalinks format
            'register_meta_box_cb'  => array($this, 'metaboxAdd')
        ));
        $this->registerTaxonomies();
    }
    
    public function registerFilters() {
        # change update messages for our quotes
        add_filter('post_updated_messages', array($this, 'changeUpdateMessages'));
        
        # some modifications for backend quote creation form
        add_filter('user_can_richedit', array($this, 'disableWysywyg') );
        add_filter('quicktags_settings', array($this, 'disableQuicktagButtons'));
        
        add_action('admin_head', array($this, 'disableMediaButtons'), 1);
        add_action('admin_head', array($this, 'changeTextareaCss'), 2);
     
        # change quote data before it is going to the db
        add_action('wp_insert_post_data', array($this, 'changeQuoteData'));   
    }
    
    public function changeQuoteData($post){
        if( 'p23_quote' == $post['post_type'] ){
            $post['post_name']  =   (!empty($post['post_content']))? sanitize_title_with_dashes($post['post_content']) : '' ;
            $post['post_title'] =   (!empty($post['post_content']))? '' : '' ;
        }
        
        return $post;
    }
    
    public function registerTaxonomies() {
        global $wp_rewrite;
        
        $wp_rewrite->add_rewrite_tag('%p23_quotes_tags_base%', '/zitate/(.+?/)?', 'p23_quotes_tags=');
        $wp_rewrite->add_rewrite_tag('%p23_quotes_author_base%', '/zitate/(.+?/)?', 'p23_quotes_author=');
        
        register_taxonomy('p23_quote_author', 'p23_quote', array(
            'hierarchical'      => true,
            'label'             => 'Author',
            'labels'            => $this->getLabels('author'),
            'show_ui'           => true,
            'show_in_nav_menus' => true,
            'query_var'         => true,
            'rewrite' => array('slug' => 'zitate/autoren', 'with_front' => FALSE)
        ));
        register_taxonomy('p23_quote_tags', 'p23_quote', array(
            'hierarchical'      => false,
            'label'             => 'Tags',
            'labels'            => $this->getLabels('tags'),
            'show_ui'           => true,
            'show_in_nav_menus' => false,
            'query_var'         => true,
            'rewrite' => array( 'slug' => 'zitate/tags', 'with_front' => FALSE) 
        ));
        
        add_rewrite_rule('^zitate/autoren/([^/]*)/?','index.php?post_type=quotes&p23_quote_author=$matches[1]','top');
        add_rewrite_rule('^zitate/tags/([^/]*)/?','index.php?post_type=quotes&p23_quote_tags=$matches[1]','top');
        $wp_rewrite->flush_rules();
    }
    

    public function metaboxAdd() {
        add_meta_box('p23_quotes_meta', 'Quote Settings', array($this, 'metaboxViewSettings'), 'p23_quote', 'normal', 'high', array('default' => 'Unbekannt'));
        
        // save the custom fields of the metaboxes
        add_action('save_post', 'metaboxSave', 1, 2);
    }
    
    public function metaboxViewSettings($post, $metabox) {
        global $post, $wp_locale;
        $default            = $metabox['args']['default'];
        $metaboxContent     = '';
        
        // Use nonce for verification ... ONLY USE ONCE!
        echo    '<input type="hidden" name="p23_quotes_meta_nonce" id="p23_quotes_meta_nonce" value="' .
                wp_create_nonce(plugin_basename(__FILE__)) . '" />';
        
        // Show in header ??
        $h1         = get_post_meta($post->ID, 'p23_quotes_h1', true);
        if( !empty($h1) && $h1 == 'on' )
            $h1     = 'checked="checked"';
        $metaboxContent     .=  '<label for="p23_quotes_h1" class="selectit">';
        $metaboxContent     .=  '<input type="checkbox" name="p23_quotes_h1" '.$h1.' />';
        $metaboxContent     .=  ' Show Quote in Header Widget?';
        $metaboxContent     .=  '</label><br />';
        
        echo '<p class="meta-options">'."\n";
        echo $metaboxContent."\n";
        echo '</p>'."\n";
        
        // add more metaoptions if needed
    }
    
    public function metaboxSave() {
        // Is the user allowed to edit the post or page?
        if ( !current_user_can( 'edit_post', $post->ID ))
            return $post->ID;
        
        // get sure save_post is triggered by a quote from us
        if ( !wp_verify_nonce( $_POST['p23_quotes_meta_nonce'], plugin_basename(__FILE__)) )
            return $post->ID;
        
        $meta['p23_quotes_h1']          = $_POST['p23_quotes_h1'];
        // add more if needed
        
        
        // Add values of $events_meta as custom fields
        foreach ($meta as $key => $value) { // Cycle through the $events_meta array!
            if( $post->post_type == 'revision' ) return; // Don't store custom data twice
            if( get_post_meta($post->ID, $key, FALSE) ) { // If the custom field already has a value
                update_post_meta($post->ID, $key, $value);
            } else { // If the custom field doesn't have a value
                add_post_meta($post->ID, $key, $value);
            }
            if(!$value) delete_post_meta($post->ID, $key); // Delete if blank
        }
    }

/**
 *  some litte functions used in registerFilters()
 */

    public function disableMediaButtons() {
        global $post_type;        
        if( 'p23_quote' == $post_type ) remove_all_actions('media_buttons');
    }
    
    public function changeTextareaCss() {
        global $post_type;
        if( 'p23_quote' == $post_type )
            echo '<style type="text/css">textarea#content{ height: 100px; font-size: 18px; } #wp-content-editor-container #ed_toolbar, #wp-content-editor-tools { display: none; }</style>';
    }
    
    public function disableQuicktagButtons($data) {
        global $post_type;
        $dataNoButtons   = array('id' => 'content', 'buttons' => ' ', 'disabled_buttons' => 'strong,em,link,block,del,ins,img,ul,ol,li,code,more,spell,close,fullscreen');
        if( 'p23_quote' == $post_type ) return $dataNoButtons;
        return $data;
    }
    
    public function disableWysywyg( $default ) {
        global $post_type;
        if ( 'p23_quote' == $post_type ) return false;
        return $default;
    }

    public function changeUpdateMessages() {
        global $post, $post_ID;

        $messages['p23_quote'] = array(
            0 => '', // Unused. Messages start at index 1.
            1 => sprintf( __('Quote updated. <a href="%s">Zitat ansehen</a>'), esc_url( get_permalink($post_ID) ) ),
            2 => __('Custom field updated.'),
            3 => __('Custom field deleted.'),
            4 => 'Zitat gespeichert.',
            /* translators: %s: date and time of the revision */
            5 => isset($_GET['revision']) ? sprintf( 'gespeicherte Version des Zitats von %s wiederhergestellt.'/* __('Quote restored to revision from %s')*/, wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
            6 => sprintf( 'Zitat veröffentlicht. <a href="%s">Zitat ansehen</a>', esc_url( get_permalink($post_ID) ) ),
            7 => 'Zitat gespeichert.',
            8 => sprintf( __('Quote submitted. <a target="_blank" href="%s">Preview book</a>'), esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
            9 => sprintf( __('Quote scheduled for: <strong>%1$s</strong>. <a target="_blank" href="%2$s">Preview book</a>'),
              // translators: Publish box date format, see http://php.net/date
              date_i18n( __( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink($post_ID) ) ),
            10 => sprintf( 'Event Entwurf updated. <a target="_blank" href="%s">Vorschau des Zitats</a>', esc_url( add_query_arg( 'preview', 'true', get_permalink($post_ID) ) ) ),
        );

        return $messages;
    }

/**
 *  change labels later to po-file or plugin config
 */
    
    private function getLabels($type='posttype') {
        $posttype   =   array(
            'name'                  => 'Zitate',
            'singular_name'         => 'Zitat',
            'add_new'               => 'Erstellen',
            'add_new_item'          => 'Neues Zitat anlegen',
            'edit_item'             => 'Zitat bearbeiten',
            'new_item'              => 'Neues Zitat',
            'view_item'             => 'Zitat ansehen',
            'search_items'          => 'Zitat suchen',
            'not_found'             => 'keine Zitate gefunden',
            'not_found_in_trash'    => 'keine Zitate im Papierkorb gefunden gefunden',
            'parent_item_colon'     => '',
            'menu_name'             => 'Zitate'
        );
        
        $author     =   array(
            'name'                          => _x( 'Author', 'taxonomy general name' ),
            'singular_name'                 => _x( 'Author', 'taxonomy singular name' ),
            'search_items'                  =>  __( 'Search Authors' ),
            'popular_items'                 => __( 'Popular Authors' ),
            'all_items'                     => __( 'All Authors' ),
            'parent_item'                   => __( 'Parent Genre' ),
            'parent_item_colon'             => __( 'Parent Genre:' ),
            'edit_item'                     => __( 'Edit Author' ), 
            'update_item'                   => __( 'Update Author' ),
            'add_new_item'                  => __( 'Add New Author' ),
            'new_item_name'                 => __( 'New Author Name' ),
            'separate_items_with_commas'    => __( 'Separate Authors with commas' ),
            'add_or_remove_items'           => __( 'Add or remove Authors' ),
            'choose_from_most_used'         => __( 'Choose from the most used Authors' ),
            'menu_name'                     => __( 'Authors' )
        );
        
        $tags       =   array(
            'name'                          => _x( 'Tag', 'taxonomy general name' ),
            'singular_name'                 => _x( 'Tag', 'taxonomy singular name' ),
            'search_items'                  => __( 'Search Tags' ),
            'popular_items'                 => __( 'Popular Tags' ),
            'all_items'                     => __( 'All Tags' ),
            'parent_item'                   => null,
            'parent_item_colon'             => null,
            'edit_item'                     => __( 'Edit Tag' ), 
            'update_item'                   => __( 'Update Tag' ),
            'add_new_item'                  => __( 'Add New Tag' ),
            'new_item_name'                 => __( 'New Tag Name' ),
            'separate_items_with_commas'    => __( 'Separate Tags with commas' ),
            'add_or_remove_items'           => __( 'Add or remove Tags' ),
            'choose_from_most_used'         => __( 'Choose from the most used Tags' ),
            'menu_name'                     => __( 'Tags' )
        );
        
        
        if( isset(${$type}) )
            return ${$type};
    }
    
} # ENDOF CLASS P23_quotes