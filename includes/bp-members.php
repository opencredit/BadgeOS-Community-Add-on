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
 * Loads BadgeOS_Community_Members Class from bp_init
 *
 * @since 1.0.0
 */
function badgeos_community_loader() {
	if ( bp_is_active( 'xprofile' ) ) {
		new BadgeOS_Community_Members();
	}
}
add_action( 'bp_init', 'badgeos_community_loader', 1 );

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
		$this->use_cache = false;
	}

	public function setup_globals( $args = '' ) {
		parent::setup_globals( array(
			'has_directory' => false,
			'root_slug'     => 'achievements',
			'slug'          => 'achievements',
		) );
		$this->network_achievements = $this->get_allowed_achievement_types_from_cache();
		$this->earned_achievements = $this->get_buffered_achievements_from_cache();
	}

	public function setup_nav( $main_nav = '', $sub_nav = '' ) {

		if ( ! bp_displayed_user_id() ) {
			return;
		}

		parent::setup_nav( array(
			'name'                => __( 'Achievements', 'badgeos-community' ),
			'slug'                => $this->slug,
			'position'            => 100,
			'screen_function'     => array( $this, 'achievements_template' ),
			'default_subnav_slug' => 'achievements',
		) );
	}

	public function achievements_template() {
		bp_core_load_template( apply_filters( 'bagdeos_bp_member_achievements', 'members/single/plugins' ) );
		add_action( 'bp_template_content', array( $this, 'achievements_template_content' ) );
	}

	public function achievements_template_content() {
		$output = '';
		foreach ( $this->get_relevant_site_ids() as $site_id ) {
			$output .= $this->render_site_achievements( $site_id );
		}
		if ( empty( $output ) ) {
			$output = '<p class="badgeos-no-achievements">' . __( 'No earned achievements to display.', 'badgeos-community' ) . '</p>';
		}
		echo $output;
	}

	/**
	 * Render all user achievements for a given site.
	 *
	 * @since  1.3.0
	 *
	 * @param  integer $site_id Site ID.
	 * @return string           HTML Markup.
	 */
	public function render_site_achievements( $site_id = 0 ) {

		$output = '<div id="site-' . absint( $site_id ) . '" class="badgeos-site-achivements badgeos-site-achivements-' . $site_id . '">';

		if ( badgeos_bp_show_network_achievements() ) {
			$output .= '<h2>' . get_blog_details( $site_id )->blogname . '</h2>';
		}

		$site_achievements = $this->earned_achievements[ $site_id ];
		foreach ( $site_achievements as $achievement_type => $achievements ) {
			$output .= '<div id="site-' . $site_id . '-' . $achievement_type . '" class="badgeos-achievement-type">';
			$output .= '<h3>' . $this->network_achievements[ $site_id ][ $achievement_type ]->post_title . '</h3>';
			$output .= $this->render_section_menu();
			$output .= implode( "\n", $achievements );
			$output .= '</div>';
		}

		$output .= '</div>';

		return $output;
	}

	/**
	 * Render a section jump menu (select input).
	 *
	 * @since  1.3.0
	 *
	 * @return string HTML Markup.
	 */
	public function render_section_menu() {

		// Bail early if output doesn't support multisite
		if ( ! ( badgeos_bp_show_network_achievements() ) ) {
			return;
		}

		$output = '<div class="badgeos-section-menu">';

		// Create an select input with options for each achievement type, grouped by site.
		$output .= '<select>';
		$output .= '<option value="">' . __( 'Jump to section:', 'badgeos-community' ) . '</option>';
		foreach( $this->network_achievements as $site_id => $achievement_types ) {
			$output .= '<optgroup label="' . esc_attr( get_blog_details( $site_id )->blogname ). '">';
			foreach ( $achievement_types as $slug => $achievement_type ) {
				if ( in_array( $slug, array_keys( $this->earned_achievements[ $site_id ] ) ) ) {
					$output .= sprintf(
						'<option value="site-%1$d-%2$s">%3$s</option>',
						absint( $site_id ),
						esc_attr( $slug ),
						esc_html( $achievement_type->post_title )
					);
				}
			}
			$output .= '</optgroup>';
		}
		$output .= '</select>';
		$output .= '</div>';

		$output .= "
			<script>
				jQuery('.badgeos-section-menu').on( 'change', 'select', function(event){
					event.preventDefault();

					var input = jQuery(this);
					var section = input.val();
					var target_offset = jQuery( '#' + section ).offset();
					var target_top = target_offset.top - 30;

					input.val('');
					jQuery('html, body').animate( {scrollTop:target_top}, 'fast');
				});
			</script>
		";
		return $output;
	}

	/**
	 * Get a user's buffered achievements from cache.
	 *
	 * @since  1.3.0
	 *
	 * @return array Buffered achievements.
	 */
	public function get_buffered_achievements_from_cache() {

		$user_id = bp_displayed_user_id();

		$achievements = get_site_transient( "badgeos_bp_achievements_user_{$user_id}" );
		if ( false === $this->use_cache || false === $achievements ) {
			$achievements = $this->get_buffered_achievements();
			set_site_transient( "badgeos_bp_achievements_user_{$user_id}", $achievements, 30 * DAY_IN_SECONDS );
		}

		return maybe_unserialize( $achievements );
	}

	/**
	 * Get a user's buffered achievements.
	 *
	 * @since  1.3.0
	 *
	 * @return array Buffered achievements.
	 */
	public function get_buffered_achievements() {

		$user_id = bp_displayed_user_id();
		$site_ids = $this->get_relevant_site_ids();

		foreach ( $site_ids as $site_id ) {
			$rendered_achievements[ $site_id ] = $this->get_buffered_achievements_for_site( $site_id );
		}

		return apply_filters( 'badgeos_bp_get_buffered_achievements', $rendered_achievements, $user_id );
	}

	/**
	 * Get a user's buffered achievements for a given site.
	 *
	 * @since  1.3.0
	 *
	 * @param  integer $site_id Site ID.
	 * @return array            Buffered achievements.
	 */
	public function get_buffered_achievements_for_site( $site_id = 0 ) {

		$site_achievements = array();
		$user_id = bp_displayed_user_id();
		$achievements = badgeos_get_user_achievements( array( 'user_id' => $user_id, 'site_id' => $site_id ) );

		// Pre-render each achievement and add it to the array
		switch_to_blog( $site_id );
		foreach ( $achievements as $achievement ) {
			if ( 'step' === $achievement->post_type ) {
				continue;
			}
			// Include achievement if it is still published of an allowed achievement type
			if ( badgeos_is_achievement( $achievement->ID ) && in_array( $achievement->post_type, array_keys( $this->network_achievements[ $site_id ] ) ) ) {
				$site_achievements[ $achievement->post_type ][ $achievement->ID ] = badgeos_render_achievement( $achievement->ID, bp_displayed_user_id() );
			}
		}
		restore_current_blog();

		// Sort array by achievement-type
		ksort( $site_achievements );

		return apply_filters( 'badgeos_bp_get_buffered_achievements_for_site', $site_achievements, $site_id, $achievements );
	}

	/**
	 * Get cached array of allowed achievement types.
	 *
	 * @since  1.3.0
	 *
	 * @return array Achievement Types.
	 */
	public function get_allowed_achievement_types_from_cache() {
		$achievement_types = get_site_transient( "badgeos_network_achievement_types" );
		if ( false === $this->use_cache || false === $achievement_types ) {
			$achievement_types = $this->get_allowed_achievement_types();
			set_site_transient( "badgeos_network_achievement_types", $achievement_types, 30 * DAY_IN_SECONDS );
		}
		return maybe_unserialize( $achievement_types );
	}

	/**
	 * Get fresh array of allowed achievement types.
	 *
	 * @since  1.3.0
	 *
	 * @return array Achievement Types.
	 */
	public function get_allowed_achievement_types() {
		foreach ( $this->get_relevant_site_ids() as $site_id ) {
			$network_achievement_types[ $site_id ] = $this->get_allowed_achievement_types_for_site_from_cache( $site_id );
		}
		return ! empty( $network_achievement_types ) ? $network_achievement_types : array();
	}

	/**
	 * Get cached array of allowed achievement types for a given site.
	 *
	 * @since  1.3.0
	 *
	 * @param  integer $site_id Site ID.
	 * @return array            Achievement type post objects.
	 */
	public function get_allowed_achievement_types_for_site_from_cache( $site_id = 0 ) {
		$achievement_types = maybe_unserialize( get_site_transient( "badgeos_site_{$site_id}_achievement_types" ) );
		if ( false === $this->use_cache || false === $achievement_types ) {
			$achievement_types = $this->get_allowed_achievement_types_for_site( $site_id );
			set_site_transient( "badgeos_site_{$site_id}_achievement_types", $achievement_types, 30 * DAY_IN_SECONDS );
		}
		return $achievement_types;
	}

	/**
	 * Get fresh array of allowed achievement types for a given site.
	 *
	 * @since  1.3.0
	 *
	 * @param  integer $site_id Site ID.
	 * @return array            Achievement type post objects.
	 */
	public function get_allowed_achievement_types_for_site( $site_id = 0 ) {

		// Fetch BP-enabled achievement types for the site
		switch_to_blog( $site_id );
		$achievement_query = new WP_Query( array(
			'post_type'  => 'achievement-type',
			'meta_query' => array(
				array(
					'key'   => '_badgeos_show_bp_member_menu',
					'value' => 'on',
					),
				),
			'orderby' => 'title',
			'order'   => 'ASC',
		) );
		restore_current_blog();

		// Rewrite the array to be keyed by achievement-type slug
		$achievement_slugs = wp_list_pluck( $achievement_query->posts, 'post_name' );
		$achievement_types = array_combine( $achievement_slugs, $achievement_query->posts );

		return $achievement_types;
	}

	public function get_relevant_site_ids() {
		if ( badgeos_bp_show_network_achievements() ) {
			$sites = wp_get_sites( array(
				'public'     => 1,
				'archived'   => 0,
				'spam'       => 0,
				'deleted'    => 0,
			) );
		} else {
			$sites[]['blog_id'] = get_current_blog_id();
		}
		return wp_list_pluck( $sites, 'blog_id' );
	}

}

/**
 * Check if site is set to display all network achievements.
 *
 * @since  1.3.0
 *
 * @return bool True if network achievements display enabled, otherwise false.
 */
function badgeos_bp_show_network_achievements() {
	$badgeos_settings = get_option( 'badgeos_settings' );
	return ( is_multisite() && isset( $badgeos_settings[ 'badgeos_community_show_all' ] ) && 'true' == $badgeos_settings[ 'badgeos_community_show_all' ] );
}

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

/**
 * Flush user's earned achievement cache on each new earning.
 *
 * @since  1.3.0
 *
 * @param  integer $user_id User ID.
 */
function badgeos_bp_bust_profile_cache( $user_id = 0 ) {
	delete_site_transient( "badgeos_bp_achievements_user_{$user_id}" );
}
add_action( 'badgeos_award_achievement', 'badgeos_bp_bust_profile_cache' );

/**
 * Flush network achievement type cache whenever an achievement type is published.
 *
 * @since 1.3.0
 *
 * @param string $new_status New status.
 * @param string $old_status Old status.
 * @param object $post       Post object.
 */
function badgeos_bp_bust_network_cache( $new_status, $old_status, $post ) {
	if ( 'achievement-type' === $post->post_type && 'publish' === $new_status && 'publish' !== $old_status ) {
		$site_id = get_current_blog_id();
		delete_site_transient( "badgeos_site_{$site_id}_achievement_types" );
		delete_site_transient( 'badgeos_network_achievement_types' );
	}
}
add_action( 'transition_post_status', 'badgeos_bp_bust_network_cache', 10, 3 );
