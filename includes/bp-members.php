<?php
/**
 * BuddyPress Membership Functions
 *
 * @package BadgeOS Community
 * @subpackage Members
 * @author Credly, LLC
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 * @link https://credly.com
 */

/**
 * Creates a BuddyPress member page for BadgeOS
 *
 * @since 1.0.0
 */
function bagdeos_bp_member_achievements() {
	add_action( 'bp_template_content', 'bagdeos_bp_member_achievements_content' );
	bp_core_load_template( apply_filters( 'bagdeos_bp_member_achievements', 'members/single/plugins' ) );
}

/**
 * Displays a members achievements
 *
 * @since 1.0.0
 */
function bagdeos_bp_member_achievements_content() {
	global $bp;

	$achievement_types = badgeos_get_user_earned_achievement_types( bp_displayed_user_id() );
	if ( is_array( $achievement_types ) ) {
		foreach( $achievement_types as $achievement_type){
			$name = get_post_type_object( $achievement_type )->labels->name;
			$slug = str_replace(' ', '-', strtolower( $name ) );
			if ( $slug && strpos( $_SERVER[REQUEST_URI], $slug ) ) {
				$type = $achievement_type;
			}
		}
		if( !$type ) {
			$type = $achievement_types[0];
		}
	}

	$atts = array(
		'type'        => $type,
		'limit'       => '10',
		'show_filter' => 'false',
		'show_search' => 'false',
		'group_id'    => '0',
		'user_id'     => bp_displayed_user_id(),
	);
	echo badgeos_achievements_list_shortcode($atts);
}

/**
 * Loads BadgeOS_Community_Members Class from bp_init
 *
 * @since 1.0.0
 */
function badgeos_community_loader() {
	$hasbp = function_exists( 'buddypress' ) && buddypress() && ! buddypress()->maintenance_mode && bp_is_active( 'xprofile' );
	if ( !$hasbp )
		return;

	$GLOBALS['badgeos_community_members'] = new BadgeOS_Community_Members();

}
add_action( 'bp_init', 'badgeos_community_loader', 1 );


/**
 * Adds Credly option to profile settings general page
 *
 * @since 1.0.0
 */
function badgeos_bp_core_general_settings_before_submit(){
	$credly_user_enable = get_user_meta( bp_displayed_user_id(), 'credly_user_enable', true );?>
	<label for="credly"><?php _e( 'Badge Sharing', 'badgeos-community' ); ?></label>
	<input type="checkbox" value="true" <?php checked( $credly_user_enable, 'true' ); ?> name="credly_user_enable">
	<?php echo _e('Send eligible earned badges to Credly','badgeos-community');
}
add_action('bp_core_general_settings_before_submit','badgeos_bp_core_general_settings_before_submit');

/**
 * Save Profile settings general page
 *
 * @since 1.0.0
 */
function badgeos_bp_core_general_settings_after_save(){
	$credly_enable = get_user_meta( bp_displayed_user_id(), 'credly_user_enable', true );
	$credly_enable2 = ( ! empty( $_POST['credly_user_enable'] ) && $_POST['credly_user_enable'] == 'true' ? 'true' : 'false' );
	if( $credly_enable != $credly_enable2 ){
		bp_core_add_message( __( 'Your settings have been saved.', 'buddypress' ), 'success' );
		update_user_meta( bp_displayed_user_id(), 'credly_user_enable', $credly_enable2 );
	}
}
add_action('bp_core_general_settings_after_save','badgeos_bp_core_general_settings_after_save');


/**
 * Build BP_Component extension object
 *
 * @since 1.0.0
 */
class BadgeOS_Community_Members extends BP_Component {

	function __construct() {
		parent::start(
			'badgeos',
			__( 'BadgeOS', 'badgeos-community' ),
			BP_PLUGIN_DIR
		);

	}

	// Globals
	public function setup_globals( $args = '' ) {
		parent::setup_globals( array(
			'has_directory' => true,
			'root_slug'     => 'achievements',
			'slug'          => 'achievements',
		) );
	}

	// BuddyPress actions
	public function setup_actions() {
		parent::setup_actions();
	}

	// Member Profile Menu
	public function setup_nav( $main_nav = '', $sub_nav = '' ) {
		global $bp;
		if ( ! is_user_logged_in() && ! bp_displayed_user_id() )
			return;

		$parent_url = trailingslashit( bp_displayed_user_domain() . $this->slug );

		// Loop existing achievement types to build array of array( 'slug' => 'ID' )
		// @TODO: update global $badgeos->achievement_types to include the post_id of each slug
		$args=array(
			'post_type'      => 'achievement-type',
			'post_status'    => 'publish',
			'posts_per_page' => -1
		);
		$query = new WP_Query($args);
		if( $query->have_posts() ) {
  			while ($query->have_posts()) : $query->the_post();
 	 			$arr_achivement_types[$query->post->post_name] = $query->post->ID;
 	 		endwhile;
		}

		// Loop achievement types current user has earned
		$achievement_types = badgeos_get_user_earned_achievement_types( bp_displayed_user_id() );
		foreach( $achievement_types as $achievement_type){

		 	$name = get_post_type_object( $achievement_type )->labels->name;
			$slug = str_replace(' ', '-', strtolower( $name ) );
			// Get post_id of earned achievement type slug
			$post_id = $arr_achivement_types[$achievement_type];
			if( $post_id ) {

				//check if this achievement type can be shown on the member profile page
				$can_bp_member_menu = get_post_meta( $post_id, '_badgeos_show_bp_member_menu', true );
				if ( $slug && $can_bp_member_menu ) {

					// Only run once to set main nav and defautl sub nav
					if( !$main ){
						// Add to the main navigation
						$main_nav = array(
							'name'                => __( 'Achievements', 'badgeos-community' ),
							'slug'                => $this->slug,
							'position'            => 100,
							'screen_function'     => 'bagdeos_bp_member_achievements',
							'default_subnav_slug' => $slug
						);
						$main = true;
					}

					$sub_nav[] = array(
						'name'            => __( $name, 'buddypress' ),
						'slug'            => $slug,
						'parent_url'      => $parent_url,
						'parent_slug'     => $this->slug,
						'screen_function' => 'bagdeos_bp_member_achievements',
						'position'        => 10,
					);

				}

			}

		}

		parent::setup_nav( $main_nav, $sub_nav );
	}

}