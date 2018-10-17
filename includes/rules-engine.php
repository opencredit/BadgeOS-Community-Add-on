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

	if ( empty( $user_ID ) && 'bp_core_activated_user' == current_filter() ) {
		$user_ID = absint( $args );
	}

	if ( 'groups_join_specific_group' == current_filter() || 'bbp_new_reply_specific_forum' == current_filter() ) {
		$user_ID = absint( $args[1] );
	}

	$user_data = get_user_by( 'id', $user_ID );

	// Sanity check, if we don't have a user object, bail here
	if ( ! is_object( $user_data ) ) {
		return $args[0];
	}

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
		# Since we are triggering multiple times based on group joining, we need to check if we're on the groups_join_specific_group filter.
		if ( 'groups_join_specific_group' == current_filter() ) {
			# We only want to trigger this when we're checking for the appropriate triggered group ID.
			$group_id = get_post_meta( $achievement->post_id, '_badgeos_group_id', true );
			if ( $group_id == $args[0] ) {
				badgeos_maybe_award_achievement_to_user( $achievement->post_id, $user_ID, $this_trigger, $blog_id, $args );
			}
		# Since we are triggering multiple times based on replying or creating a new topic in a forum, we need to check if we're on the bbp_new_reply_specific_forum filter.
		} else if ( 'bbp_new_reply_specific_forum' == current_filter() ) {
			# We only want to trigger this when we're checking for the appropriate triggered forum ID.
			$forum_id = get_post_meta( $achievement->post_id, '_badgeos_forum_id', true );
			if ( $forum_id == $args[0] ) {
				badgeos_maybe_award_achievement_to_user( $achievement->post_id, $user_ID, $this_trigger, $blog_id, $args );
			}
		} else {
			badgeos_maybe_award_achievement_to_user( $achievement->post_id, $user_ID, $this_trigger, $blog_id, $args );
}
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

/**
 * Check if user has reply to forum or created a topic in a forum.
 *
 * @param  integer $user_id  The given user's ID.
 * @param  integer $forum_id  The given achievement's post ID.
 *
 * @return bool                    True if the user has replied or created a topic.
 */
function badgeos_user_has_replied_to_forum( $user_id = 0, $forum_id = 0 ) {
	// if the user or forum id are empty return false
	if ( empty( $user_id ) || empty( $forum_id ) ) {
		return false;
	}
	$args = array(
		'post_author'            => $user_id,
		'post_type'              => array( bbp_get_topic_post_type(), bbp_get_reply_post_type() ),
		'post_parent__in'        => array_merge( array( $forum_id ), bbp_get_all_child_ids( $forum_id, bbp_get_topic_post_type() ) ),
		'no_found_rows'          => true,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
		'fields'                 => 'ids',
		'posts_per_page'         => 1,
	);
	
	add_filter( 'post_limits', 'badgeos_user_has_replied_to_forum_limit' );
	
	$posts = new WP_Query( $args );
	$total = $posts->post_count;
	wp_reset_postdata();
	
	remove_filter( 'post_limits', 'badgeos_user_has_replied_to_forum_limit' );
	
	return ! empty( $total );
}

/**
 * Limit the uery results to 1.
 *
 * @see http://codex.wordpress.org/Plugin_API/Filter_Reference/post_limits
 * 
 * @param string $limit The 'LIMIT' clause for the query.
 *
 * @return string The filtered LIMIT.
 */
function badgeos_user_has_replied_to_forum_limit( $limit ) {
		return 'LIMIT 0, 1';
}

/**
 * Check if user deserves a "reply to specific forum" step
 *
 * @param  bool    $return         Whether or not the user deserves the step
 * @param  integer $user_id        The given user's ID
 * @param  integer $achievement_id The given achievement's post ID
 *
 * @return bool                    True if the user deserves the step, false otherwise
 */
function badgeos_bp_user_deserves_forum_step( $return, $user_id, $achievement_id ) {

	// If we're not dealing with a step, bail here
	if ( 'step' != get_post_type( $achievement_id ) )
		return $return;

	// Grab our step requirements
	$requirements = badgeos_get_step_requirements( $achievement_id );

	// If the step is triggered by joining a specific group
	if ( 'bbp_new_reply_specific_forum' == $requirements['community_trigger'] ) {
		// And our user has replied to a topic or created a new topic in this forum, return true
		if ( badgeos_user_has_replied_to_forum( $user_id, $requirements['forum_id'] ) )
			$return = true;
		// Else, return false
		else
			$return = false;
	}

	return $return;
}
add_filter( 'user_deserves_achievement', 'badgeos_bp_user_deserves_forum_step', 15, 3 );

/**
 * Fires our bbp_new_reply_specific_forum action for replying to specific forum.
 *
 * @param bool $topic_id       The topic ID.
 * @param int  $forum_id       The given forum ID
 * @param int  $anonymous_data The post anonymous data.
 * @param int  $topic_author   The topic author ID.
 */
function badgeos_do_specific_forum_new_topic( $topic_id, $forum_id, $anonymous_data, $topic_author ) {
	do_action( 'bbp_new_reply_specific_forum', array( $forum_id, $topic_author ) );
}
add_action( 'bbp_new_topic', 'badgeos_do_specific_forum_new_topic', 10, 4 );

/**
 * Fires our bbp_new_reply_specific_forum action for replying to specific forum.
 *
 * @param bool $reply_id       The reply ID.
 * @param int  $topic_id       The topic ID.
 * @param int  $forum_id       The forum ID.
 * @param int  $anonymous_data The post anonymous data.
 * @param int  $reply_author   The reply author ID.
 */
function badgeos_do_specific_forum_new_reply( $reply_id, $topic_id, $forum_id, $anonymous_data, $reply_author ) {
	do_action( 'bbp_new_reply_specific_forum', array( $forum_id, $reply_author ) );
}
add_action( 'bbp_new_reply', 'badgeos_do_specific_forum_new_reply', 10, 5 );

/**
 * Fires our group_join_specific_group action for joining public groups.
 *
 * @since 1.2.1
 *
 * @param int $group_id ID of the public group being joined.
 * @param int $user_id ID of the user joining the group.
 */
function badgeos_bp_do_specific_group( $group_id = 0, $user_id = 0 ) {
	do_action( 'groups_join_specific_group', array( $group_id, $user_id ) );
}
add_action( 'groups_join_group', 'badgeos_bp_do_specific_group', 15, 2 );

/**
 * Fires our group_join_specific_group action for joining Membership request or Hidden groups.
 *
 * @since 1.2.2
 *
 * @param int       $user_id  ID of the user joining the group.
 * @param int       $group_id ID of the group being joined.
 * @param bool|true $accepted Whether or not the membership was accepted. Default true.
 */
function badgeos_bp_do_specific_group_requested_invited( $user_id = 0, $group_id = 0, $accepted = true ) {
    do_action( 'groups_join_specific_group', array( $group_id, $user_id ) );
}
add_action( 'groups_membership_accepted', 'badgeos_bp_do_specific_group_requested_invited', 15, 3 );
add_action( 'groups_accept_invite', 'badgeos_bp_do_specific_group_requested_invited', 15, 3 );
