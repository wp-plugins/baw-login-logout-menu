<?php
/*
Plugin Name: BAW Login/Logout menu
Plugin URI: http://www.boiteaweb.fr/llm
Description: You can now add a correct login & logout link in your WP menus.
Version: 1.3
Author: Juliobox
Author URI: http://www.boiteaweb.fr
*/

/* Used to return the correct title for the double login/logout menu item */
function bawllm_loginout_title( $title )
{
	$titles = explode( '|', $title );
	if ( ! is_user_logged_in() )
		return esc_html( isset( $titles[0] ) ? $titles[0] : $title );
	else
		return esc_html( isset( $titles[1] ) ? $titles[1] : $title );
}

/* The main code, this replace the #keyword# by the correct links with nonce ect */
function bawllm_setup_nav_menu_item( $item )
{
	global $pagenow;
	if( $pagenow!='nav-menus.php' && !defined('DOING_AJAX') && isset( $item->url ) && strstr( $item->url, '#baw' ) != '' ){
		$item_url = substr( $item->url, 0, strpos( $item->url, '#', 1 ) ) . '#';
		$item_redirect = str_replace( $item_url, '', $item->url );
		$item_redirect = $item_redirect != '%actualpage%' ? $item_redirect : $_SERVER['REQUEST_URI'];
		switch( $item_url ) {
			case '#bawloginout#' : 	
									$item_redirect = explode( '|', $item_redirect );
									if( count( $item_redirect ) != 2 ) 
										$item_redirect[1] = $item_redirect[0];
									$item->url = is_user_logged_in() ? wp_logout_url( $item_redirect[1] ) : wp_login_url( $item_redirect[0] );
									$item->title = bawllm_loginout_title( $item->title ) ; break;
			case '#bawlogin#' : 	$item->url = wp_login_url( $item_redirect ); break;
			case '#bawlogout#' : 	$item->url = wp_logout_url( $item_redirect ); break;
			case '#bawregister#' : 	if( is_user_logged_in() ) $item = null; else $item->url = site_url( 'wp-login.php?action=register', 'login' ); break;
		}
		$item->url = esc_url( $item->url );
	}
	return $item;
}
add_filter( 'wp_setup_nav_menu_item', 'bawllm_setup_nav_menu_item' );

/* [login] shortcode */
function bawllm_shortcode_login( $atts, $content = null )
{
	extract(shortcode_atts(array(
		"edit_tag" => "",
		"redirect" => $_SERVER['REQUEST_URI']
	), $atts ) );
	$edit_tag = esc_html( strip_tags( $edit_tag ) );
	$href = wp_login_url( $redirect );
	$content = $content != '' ? $content : __( 'Log In' );
	return '<a href="' . esc_url( $href ) . '"' .$edit_tag . '>' . $content . '</a>';
}
add_shortcode( 'login', 'bawllm_shortcode_login' );

/* [loginout] shortcode */
function bawllm_shortcode_loginout( $atts, $content = null )
{
	extract(shortcode_atts(array(
		"edit_tag" => "",
		"redirect" => $_SERVER['REQUEST_URI']
	), $atts ) );
	$edit_tag = strip_tags( $edit_tag );
	$href = is_user_logged_in() ? wp_logout_url( $redirect ) : wp_login_url( $redirect );
	if( $content && strstr( $content, '|' ) != '' ) { // the "|" char is used to split titles
		$content = explode( '|', $content );
		$content = is_user_logged_in() ? $content[1] : $content[0];
	}else{
		$content = is_user_logged_in() ? __( 'Logout' ) : __( 'Log In' );
	}
	return '<a href="' . esc_url( $href ) . '"' .$edit_tag . '>' . $content . '</a>';
}
add_shortcode( 'loginout', 'bawllm_shortcode_loginout' );

/* [logout] shortcode */
function bawllm_shortcode_logout( $atts, $content = null )
{
	extract(shortcode_atts(array(
		"edit_tag" => "",
		"redirect" => $_SERVER['REQUEST_URI']
	), $atts ) );
	$href = wp_logout_url( $redirect );
	$edit_tag = esc_html( strip_tags( $edit_tag ) );
	$content = $content != '' ? $content : __( 'Logout' );
	return '<a href="' . esc_url( $href ) . '"' .$edit_tag . '>' . $content . '</a>';
}
add_shortcode( 'logout', 'bawllm_shortcode_logout' );

/* [register] shortcode */
function bawllm_shortcode_register( $atts, $content = null )
{
	if( is_user_logged_in() )
		return '';
	$href = site_url('wp-login.php?action=register', 'login');
	$content = $content != '' ? $content : __( 'Register' );
	$link = '<a href="' . $href. '">' . $content . '</a>';
	return $link;
}
add_shortcode( 'register', 'bawllm_shortcode_register' );

/* Add a metabox in admin menu page */
function bawllm_add_nav_menu_metabox() {
	add_meta_box( 'bawllm', __( 'Login/Logout links' ), 'bawllm_nav_menu_metabox', 'nav-menus', 'side', 'default' );
}
add_action('admin_head-nav-menus.php', 'bawllm_add_nav_menu_metabox');

/* The metabox code : Awesome code stolen from screenfeed.fr (GregLone) Thank you mate. */
function bawllm_nav_menu_metabox( $object )
{
	global $nav_menu_selected_id;

	$elems = array( '#bawlogin#' => __( 'Log In' ), '#bawlogout#' => __( 'Log Out' ), '#bawloginout#' => __( 'Log In' ).'|'.__( 'Log Out' ), '#bawregister#' => __( 'Register' ) );
	class bawlogItems {
		public $db_id = 0;
		public $object = 'bawlog';
		public $object_id;
		public $menu_item_parent = 0;
		public $type = 'custom';
		public $title;
		public $url;
		public $target = '';
		public $attr_title = '';
		public $classes = array();
		public $xfn = '';
	}

	$elems_obj = array();
	foreach ( $elems as $value => $title ) {
		$elems_obj[$title] = new bawlogItems();
		$elems_obj[$title]->object_id	= esc_attr( $value );
		$elems_obj[$title]->title		= esc_attr( $title );
		$elems_obj[$title]->url			= esc_attr( $value );
	}

	$walker = new Walker_Nav_Menu_Checklist( array() );
	?>
	<div id="login-links" class="loginlinksdiv">

		<div id="tabs-panel-login-links-all" class="tabs-panel tabs-panel-view-all tabs-panel-active">
			<ul id="login-linkschecklist" class="list:login-links categorychecklist form-no-clear">
				<?php echo walk_nav_menu_tree( array_map( 'wp_setup_nav_menu_item', $elems_obj ), 0, (object)array( 'walker' => $walker ) ); ?>
			</ul>
		</div>

		<p class="button-controls">
			<span class="list-controls hide-if-no-js">
				<a href="javascript:void(0);" class="help" onclick="jQuery( '#help-login-links' ).toggle();"><?php _e( 'Help' ); ?></a>
			</span>
			<p class="hide-if-js" id="help-login-links"><a name="help-login-links"></a>
				<?php
				if( get_locale() == 'fr_FR' ) { // Light L10N
					echo '&#9725; Vous pouvez ajouter une page de redirection apr&egrave;s le login/logout du membre en ajoutant simplement le lien relatif apr&egrave;s le mot cl&eacute; dans le lien, exemple <code>#bawloginout#index.php</code>.';
					echo '<br />&#9725; Vous pouvez aussi ajouter <code>%actualpage%</code> pour que la redirection soit faite sur la page en cours, exemple : <code>#bawloginout#%actualpage%</code>.';
				}else{
					echo '&#9725; You can add a redirection page after the user\'s login/logout simply adding a relative link after the link\'s keyword, example <code>#bawloginout#index.php</code>.';
					echo '<br />&#9725; You can also add <code>%actualpage%</code> to redirect the user on the actual visited page, example : <code>#bawloginout#%actualpage%</code>.';
				}
				
				?>
			</p>

			<span class="add-to-menu">
				<img class="waiting" src="<?php echo esc_url( admin_url( 'images/wpspin_light.gif' ) ); ?>" alt="" />
				<input type="submit"<?php disabled( $nav_menu_selected_id, 0 ); ?> class="button-secondary submit-add-to-menu" value="<?php esc_attr_e('Add to Menu'); ?>" name="add-login-links-menu-item" id="submit-login-links" />
			</span>
		</p>

	</div>
	<?php
}


/* Modify the "type_label" */
function bawllm_nav_menu_type_label( $menu_item )
{
	$elems = array( '#bawlogin#', '#bawlogout#', '#bawloginout#', '#bawregister#' );
	if ( isset($menu_item->object, $menu_item->url) && $menu_item->object == 'custom' && in_array($menu_item->url, $elems) )
		$menu_item->type_label = ( get_locale() == 'fr_FR' ? 'Connexion' : 'Connection' );

	return $menu_item;
}
add_filter( 'wp_setup_nav_menu_item', 'bawllm_nav_menu_type_label' );
?>