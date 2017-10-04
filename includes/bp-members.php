<?php
/**
 * BuddyPress Membership Functions
 *
 * @package BadgeOS Community
 * @subpackage Members
 * @author LearningTimes, LLC
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 * @link https://credly.com
 */

/**
 * Creates a BuddyPress member page for BadgeOS
 *
 * @since 1.0.0
 */
function badgeos_bp_member_achievements() {
	add_action( 'bp_template_content', 'badgeos_bp_member_achievements_content' );
	bp_core_load_template( apply_filters( 'badgeos_bp_member_achievements', 'members/single/plugins' ) );
}

/**
 * Displays a members achievements
 *
 * @since 1.0.0
 */
function badgeos_bp_member_achievements_content() {

	$achievement_types = badgeos_get_network_achievement_types_for_user( bp_displayed_user_id() );
	// Eliminate step cpt from array
	if ( ( $key = array_search( 'step', $achievement_types ) ) !== false ) {
		unset( $achievement_types[$key] );
		$achievement_types = array_values( $achievement_types );
	}

	$type = '';

	if ( is_array( $achievement_types ) && !empty( $achievement_types ) ) {
		foreach ( $achievement_types as $achievement_type ) {
			$name = get_post_type_object( $achievement_type )->labels->name;
			$slug = str_replace( ' ', '-', strtolower( $name ) );
			if ( $slug && strpos( $_SERVER['REQUEST_URI'], $slug ) ) {
				$type = $achievement_type;
			}
		}
		if ( empty( $type ) )
			$type = $achievement_types[0];
	}

	$atts = array(
		'type'        => $type,
		'limit'       => '10',
		'show_filter' => 'false',
		'show_search' => 'false',
		'group_id'    => '0',
		'user_id'     => bp_displayed_user_id(),
		'wpms'        => badgeos_ms_show_all_achievements(),
	);
	echo badgeos_achievements_list_shortcode( $atts );
}

/**
 * Loads BadgeOS_Community_Members Class from bp_init
 *
 * @since 1.0.0
 */
function badgeos_community_loader() {
	$bp = buddypress();
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
function badgeos_bp_core_general_settings_before_submit() {
	global $badgeos_credly;
	$credly_settings = $badgeos_credly->credly_settings;

	if ( 'false' == $credly_settings['credly_enable'] ) {
		return;
	}

	$credly_user_enable = get_user_meta( bp_displayed_user_id(), 'credly_user_enable', true );?>
	<label for="credly"><?php _e( 'Badge Sharing', 'badgeos-community' ); ?></label>
	<input id="credly" type="checkbox" value="true" <?php checked( $credly_user_enable, 'true' ); ?> name="credly_user_enable">
	<?php _e( 'Send eligible earned badges to Credly', 'badgeos-community' );
}
add_action( 'bp_core_general_settings_before_submit', 'badgeos_bp_core_general_settings_before_submit' );

/**
 * Save Profile settings general page
 *
 * @since 1.0.0
 */
function badgeos_bp_core_general_settings_after_save() {
	$credly_enable = get_user_meta( bp_displayed_user_id(), 'credly_user_enable', true );
	$credly_enable2 = ( ! empty( $_POST['credly_user_enable'] ) && $_POST['credly_user_enable'] == 'true' ? 'true' : 'false' );
	if ( $credly_enable != $credly_enable2 ) {
		bp_core_add_message( __( 'Your settings have been saved.', 'buddypress' ), 'success' );
		update_user_meta( bp_displayed_user_id(), 'credly_user_enable', $credly_enable2 );
	}
}
add_action( 'bp_core_general_settings_after_save', 'badgeos_bp_core_general_settings_after_save' );


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
	public function setup_nav( $main_nav = array(), $sub_nav = array() ) {

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
		$query = new WP_Query( $args );
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) : $query->the_post();
			$arr_achievement_types[$query->post->post_name] = $query->post->ID;
			endwhile;
		}

		$achievement_types = badgeos_get_network_achievement_types_for_user( bp_displayed_user_id() );

		if ( !empty( $achievement_types ) ) {
			// Loop achievement types current user has earned
			foreach ( $achievement_types as $achievement_type ) {

				$achievement_object = get_post_type_object( $achievement_type );
				$name = is_object( $achievement_object ) ? $achievement_object->labels->name : '';
				$slug = str_replace( ' ', '-', strtolower( $name ) );
				// Get post_id of earned achievement type slug
				$post_id = isset( $arr_achievement_types[$achievement_type] ) ? $arr_achievement_types[$achievement_type] : 0;
				if ( $post_id ) {

					//check if this achievement type can be shown on the member profile page
					$can_bp_member_menu = get_post_meta( $post_id, '_badgeos_show_bp_member_menu', true );
					if ( $slug && $can_bp_member_menu ) {

						// Only run once to set main nav and defautl sub nav
						if ( empty( $main ) ) {
							// Add to the main navigation
							$main_nav = array(
								'name'                => __( 'Achievements', 'badgeos-community' ),
								'slug'                => $this->slug,
								'position'            => 100,
								'screen_function'     => 'badgeos_bp_member_achievements',
								'default_subnav_slug' => $slug
							);
							$main = true;
						}

						$sub_nav[] = array(
							'name'            => $name,
							'slug'            => $slug,
							'parent_url'      => $parent_url,
							'parent_slug'     => $this->slug,
							'screen_function' => 'badgeos_bp_member_achievements',
							'position'        => 10,
						);

					}

				}

			}
		}
		else {
			// Add to the main navigation
			$main_nav = array(
				'name'                => __( 'Achievements', 'badgeos-community' ),
				'slug'                => $this->slug,
				'position'            => 100,
				'screen_function'     => 'badgeos_bp_member_achievements',
				'default_subnav_slug' => 'achievements'
			);

			$sub_nav[] = array(
				'name'            => __( 'Achievements', 'badgeos-community' ),
				'slug'            => 'achievements',
				'parent_url'      => $parent_url,
				'parent_slug'     => $this->slug,
				'screen_function' => 'badgeos_bp_member_achievements',
				'position'        => 10,
			);
		}

		parent::setup_nav( $main_nav, $sub_nav );
	}

}

/**
 * Override the achievement earners list to use BP details
 *
 * @since  1.0.0
 * @param string  $user_content The list item output for the given user
 * @param integer $user_id      The given user's ID
 * @return string               The updated user output
 */
function badgeos_bp_achievement_earner( $user_content, $user_id ) {
	$user = new BP_Core_User( $user_id );
	return '<li><a href="' .  $user->user_url . '">' . $user->avatar_mini . '</a></li>';
}
add_filter( 'badgeos_get_achievement_earners_list_user', 'badgeos_bp_achievement_earner', 10, 2 );
