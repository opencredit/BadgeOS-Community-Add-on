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

	$earned_achievements = badgeos_bp_get_user_achievements_from_cache( bp_displayed_user_id() );
	if ( ! empty( $earned_achievements ) ) {
		$displayed_type = bp_current_action();
		if ( ! empty( $earned_achievements[ $displayed_type ] ) ) {
			foreach( $earned_achievements[ $displayed_type ] as $site_id => $achievements ) {
				$site_name = is_multisite() ? get_blog_details( $site_id )->blogname : '';
				echo '<div class="badgeos-site-achivements badgeos-site-achivements-' . $site_id . '">';
				if ( ! empty( $site_name ) ) { echo '<h2>' . $site_name . '</h2>'; }
				echo implode( "\n", $achievements );
				echo '</div>';
			}
		} else {
			_e( 'No earned achievements to display.', 'badgeos-community' );
		}
	}
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
function badgeos_bp_core_general_settings_before_submit() {
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
				'has_directory' => false,
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

		if ( ! bp_displayed_user_id() ) {
			return;
		}

		$achievement_types = badgeos_bp_get_network_achievement_types_from_cache();

		if ( is_array( $achievement_types ) && ! empty( $achievement_types ) ) {
			foreach ( $achievement_types as $site_id => $types ) {
				foreach ( $types as $type ) {
					$sub_nav[] = array(
						'name'            => $type->post_title,
						'slug'            => $type->post_name,
						'parent_url'      => $this->get_parent_url(),
						'parent_slug'     => $this->slug,
						'screen_function' => 'bagdeos_bp_member_achievements',
						'position'        => 10,
					);
				}
			}
		} else {
			$sub_nav[] = array(
				'name'            => __( 'Achievements', 'badgeos-community' ),
				'slug'            => 'achievements',
				'parent_url'      => $this->get_parent_url(),
				'parent_slug'     => $this->slug,
				'screen_function' => 'bagdeos_bp_member_achievements',
				'position'        => 10,
			);
		}

		$main_nav = array(
			'name'                => __( 'Achievements', 'badgeos-community' ),
			'slug'                => $this->slug,
			'position'            => 100,
			'screen_function'     => 'bagdeos_bp_member_achievements',
			'default_subnav_slug' => $sub_nav[0]['slug'],
		);

		parent::setup_nav( $main_nav, $sub_nav );
	}

	private function get_parent_url() {
		return trailingslashit( bp_displayed_user_domain() . $this->slug );
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



function badgeos_bp_get_user_achievements_from_cache( $user_id = 0 ) {

	if ( ! absint( $user_id ) ) {
		$user_id = bp_displayed_user_id();
	}

	$achievements = get_transient( "badgeos_bp_achievements_user_{$user_id}" );
	if ( 1==1 || false === $achievements ) {
		$achievements = badgeos_bp_buffer_user_achievements( $user_id );
		set_transient( "badgeos_bp_achievements_user_{$user_id}", $achievements, 7 * DAY_IN_SECONDS );
	}

	return maybe_unserialize( $achievements );
}

function badgeos_bp_buffer_user_achievements( $user_id = 0 ) {

	if ( ! absint( $user_id ) ) {
		$user_id = bp_displayed_user_id();
	}

	$rendered_achievements = array();
	$earned = badgeos_get_user_achievements( array( 'user_id' => $user_id, 'site_id' => 'all' ) );
	foreach ( $earned as $site_id => $achievements ) {
		$rendered_achievements[ $site_id ] = badgeos_bp_buffer_site_achievements( $site_id, $achievements );
	}

	$rekeyed_achievements = badgeos_bp_rekey_user_achievements( $rendered_achievements );

	return apply_filters( 'badgeos_bp_buffer_user_achievements', $rekeyed_achievements, $user_id, $earned );
}

function badgeos_bp_buffer_site_achievements( $site_id = 0, $achievements = array() ) {
	$site_achievements = array();
	switch_to_blog( $site_id );
	foreach ( $achievements as $achievement ) {
		if ( 'step' === $achievement->post_type ) {
			continue;
		}
		$site_achievements[ $achievement->post_type ][] = badgeos_render_achievement( $achievement->ID );
	}
	restore_current_blog();
	ksort( $site_achievements );
	return apply_filters( 'badgeos_bp_buffer_site_achievements', $site_achievements, $site_id, $achievements );
}

function badgeos_bp_get_network_achievement_types_from_cache() {
	$achievement_types = get_transient( "badgeos_network_achievement_types" );
	if ( 1==1 || false === $achievement_types ) {
		$achievement_types = badgeos_bp_get_network_achievement_types();
		set_transient( "badgeos_network_achievement_types", $achievement_types, 7 * DAY_IN_SECONDS );
	}
	return maybe_unserialize( $achievement_types );
}

function badgeos_bp_get_network_achievement_types() {
	$network_achievement_types = array();

	if ( is_multisite() ) {
		$sites = wp_get_sites( array(
			'public'     => 1,
			'archived'   => 0,
			'spam'       => 0,
			'deleted'    => 0,
		) );
		foreach ( $sites as $site ) {
			$network_achievement_types[ $site['blog_id'] ] = badgeos_bp_get_site_achievement_types_from_cache( $site['blog_id'] );
		}
	} else {
		$blog_id = get_current_blog_id();
		$network_achievement_types[ $blog_id ] = badgeos_bp_get_site_achievement_types_from_cache();
	}

	return $network_achievement_types;
}

function badgeos_bp_get_site_achievement_types_from_cache( $site_id = 0 ) {
	$site_id = absint( $site_id ) ? $site_id : get_current_blog_id();
	$achievement_types = get_transient( "badgeos_site_{$site_id}_achievement_types" );
	if ( 1==1 || false === $achievement_types ) {
		$achievement_types = badgeos_bp_get_site_achievement_types( $site_id );
		set_transient( "badgeos_site_{$site_id}_achievement_types", $achievement_types, 7 * DAY_IN_SECONDS );
	}
	return maybe_unserialize( $achievement_types );
}

function badgeos_bp_get_site_achievement_types( $site_id = 0 ) {
	$site_id = absint( $site_id ) ? $site_id : get_current_blog_id();
	switch_to_blog( $site_id );
	$achievement_types = new WP_Query( array(
		'post_type'  => 'achievement-type',
		'meta_query' => array(
			array(
				'key'   => '_badgeos_show_bp_member_menu',
				'value' => 'on',
			),
		),
	) );
	restore_current_blog();
	return $achievement_types->posts;
}

function badgeos_bp_rekey_user_achievements( $earned_achievements = array() ) {
	$rekeyed_achievements = array();
	if ( is_array( $earned_achievements ) && ! empty( $earned_achievements ) ) {
		foreach ( $earned_achievements as $site_id => $achievement_types ) {
			foreach ( $achievement_types as $achievement_type => $achievements ) {
				$rekeyed_achievements[ $achievement_type ][ $site_id ] = $achievements;
			}
		}
	}
	return $rekeyed_achievements;
}
