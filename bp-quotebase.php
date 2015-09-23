<?
/*
	Plugin Name: BP Quotebase
	Plugin URI: http://example.org/my/awesome/bp/plugin
	Description: This BuddyPress component is the greatest thing since sliced bread.
	Version: 0.8
	Revision Date: MMMM DD, YYYY
	Requires at least: What WP version, what BuddyPress version? ( Example: WP 3.2.1, BuddyPress 1.2.9 )
	Tested up to: What WP version, what BuddyPress version?
	License: (Example: GNU General Public License 2.0 (GPL) http://www.gnu.org/licenses/gpl.html)
	Author: Stefan Friedl
	Author URI: https://github.com/DoubleU23
	Network: true
*/

# Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) exit;

define('BP_QUOTEBASE_PLUGIN_SLUG', basename(__FILE__, '.php'));
define('BP_QUOTEBASE_PLUGIN_PATH', plugin_dir_path(__FILE__));
define( 'BP_QUOTEBASE_PLUGIN_DIR', dirname( __FILE__ ) );

require	BP_QUOTEBASE_PLUGIN_PATH . 'inc/bp-quotebase.class.php';

if( !function_exists('bp_quotebase_init') ) {
	function bp_quotebase_init(){
	    global $bp;
	    
	    if( isset($bp) && !isset($bp->quotes))
	        $bp->quotes     = new BP_Quotebase();
	    else
	        $p23_quotes     = new BP_Quotebase();
	    }
}
add_action('bp_loaded', 'bp_quotebase_init');


function bp_quotebase_activate() {
# Put setup procedures to be run when the plugin is activated in the following function
}
register_activation_hook( __FILE__, 'bp_quotebase_activate' );

# On deacativation, clean up anything your component has added.
function bp_quotebase_deactivate() {
# You might want to delete any options or tables that your component created.
}
register_deactivation_hook( __FILE__, 'bp_quotebase_deactivate' );