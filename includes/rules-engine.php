<?php
/**
 * Custom Achievement Rules
 *
 * @package BadgeOS Community
 * @subpackage Achievements
 * @author LearningTimes, LLC
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 * @link https://credly.com
 */

/**
 * Load up our community triggers so we can add actions to them
 *
 * @since 1.0.0
 */
function badgeos_bp_load_community_triggers() {

	// Grab our community triggers
	$community_triggers = $GLOBALS['badgeos_community']->community_triggers;
	if ( !empty( $community_triggers ) ) {
		foreach ( $community_triggers as $optgroup_name => $triggers ) {
			foreach ( $triggers as $trigger_hook => $trigger_name ) {
				add_action( $trigger_hook, 'badgeos_bp_trigger_event', 10, 10 );
			}
		}
	}

}
add_action( 'init', 'badgeos_bp_load_community_triggers' );

/**
 * Handle each of our community triggers
 *
 * @since 1.0.0
 */
function badgeos_bp_trigger_event( $args = '' ) {
	// Setup all our important variables
	global $user_ID, $blog_id, $wpdb;

	if ( empty( $user_ID ) && 'bp_core_activated_user' == current_filter() )
		$user_ID = absint( $args );

	$user_data = get_user_by( 'id', $user_ID );

	// Sanity check, if we don't have a user object, bail here
	if ( ! is_object( $user_data ) )
		return $args[ 0 ];

	// Grab the current trigger
	$this_trigger = current_filter();

	// Update hook count for this user
	$new_count = badgeos_update_user_trigger_count( $user_ID, $this_trigger, $blog_id );

	// Mark the count in the log entry
	badgeos_post_log_entry( null, $user_ID, null, sprintf( __( '%1$s triggered %2$s (%3$dx)', 'badgeos-community' ), $user_data->user_login, $this_trigger, $new_count ) );

	// Now determine if any badges are earned based on this trigger event
	$triggered_achievements = $wpdb->get_results( $wpdb->prepare(
		"
		SELECT post_id
		FROM   $wpdb->postmeta
		WHERE  meta_key = '_badgeos_community_trigger'
		       AND meta_value = %s
		",
		$this_trigger
	) );
	foreach ( $triggered_achievements as $achievement ) {
		badgeos_maybe_award_achievement_to_user( $achievement->post_id, $user_ID, $this_trigger, $blog_id, $args );
	}
}

/**
 * Check if user deserves a community trigger step
 *
 * @since  1.0.0
 * @param  bool    $return         Whether or not the user deserves the step
 * @param  integer $user_id        The given user's ID
 * @param  integer $achievement_id The given achievement's post ID
 * @return bool                    True if the user deserves the step, false otherwise
 */
function badgeos_bp_user_deserves_community_step( $return, $user_id, $achievement_id ) {

	// If we're not dealing with a step, bail here
	if ( 'step' != get_post_type( $achievement_id ) )
		return $return;

	// Grab our step requirements
	$requirements = badgeos_get_step_requirements( $achievement_id );

	// If the step is triggered by community actions...
	if ( 'community_trigger' == $requirements['trigger_type'] ) {

		// Grab the trigger count
		$trigger_count = badgeos_get_user_trigger_count( $user_id, $requirements['community_trigger'] );

		// If we meet or exceed the required number of checkins, they deserve the step
		if ( $trigger_count >= $requirements['count'] )
			$return = true;
		else
			$return = false;
	}

	return $return;
}
add_filter( 'user_deserves_achievement', 'badgeos_bp_user_deserves_community_step', 15, 3 );

/**
 * Check if user deserves a "join a specific group" step
 *
 * @since  1.0.0
 * @param  bool    $return         Whether or not the user deserves the step
 * @param  integer $user_id        The given user's ID
 * @param  integer $achievement_id The given achievement's post ID
 * @return bool                    True if the user deserves the step, false otherwise
 */
function badgeos_bp_user_deserves_group_step( $return, $user_id, $achievement_id ) {

	// If we're not dealing with a step, bail here
	if ( 'step' != get_post_type( $achievement_id ) )
		return $return;

	// Grab our step requirements
	$requirements = badgeos_get_step_requirements( $achievement_id );

	// If the step is triggered by joining a specific group
	if ( 'groups_join_specific_group' == $requirements['community_trigger'] ) {
		// And our user is a part of that group, return true
		if ( groups_is_user_member( $user_id, $requirements['group_id'] ) )
			$return = true;
		// Else, return false
		else
			$return = false;
	}

	return $return;
}
add_filter( 'user_deserves_achievement', 'badgeos_bp_user_deserves_group_step', 15, 3 );

function badgeos_bp_do_specific_group( $group_id = 0, $user_id = 0 ) {
	do_action( 'groups_join_specific_group', array( $group_id, $user_id ) );
}
add_action( 'groups_join_group', 'badgeos_bp_do_specific_group', 15, 2 );
