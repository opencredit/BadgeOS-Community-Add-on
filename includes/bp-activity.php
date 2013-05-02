<?php
/**
 * BuddyPress Activity Functions
 *
 * @package BadgeOS Community
 * @subpackage Activity
 * @author Credly, LLC
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 * @link https://credly.com
 */

/**
 * Create BuddyPress Activity when a user earns an achievement.
 *
 * @since 1.0.0
 */
function badgeos_award_achievement_bp_activity( $user_id, $achievement_id ){

	if( !$user_id || !$achievement_id )
		return false;

	$post = get_post($achievement_id);
	$type = $post->post_type;

	// Don't make activity posts for step post type
	if( 'step' == $type )
		return false;

	// Check if option is on/off
	$achievement_type = get_page_by_title( $type, 'OBJECT', 'achievement-type' );
	$can_bp_activity = get_post_meta( $achievement_type->ID, '_badgeos_create_bp_activty', true );
	if( $can_bp_activity )
		return false;

	// Grab the singular name for our achievement type
	$post_type_singular_name = strtolower( get_post_type_object( $type )->labels->singular_name );

	// Setup our entry content
	$content = '<div class="badgeos-achievements-list-item user-has-earned">';
	$content .= '<div class="badgeos-item-image"><a href="'. get_permalink( $achievement_id ) . '">' . badgeos_get_achievement_post_thumbnail( $achievement_id ) . '</a></div>';
	$content .= '<div class="badgeos-item-description">' . wpautop( $post->post_excerpt ) . '</div>';
	$content .= '</div>';

	// Insert the activity
	bp_activity_add( array(
		'action'       => sprintf( __( '%1$s earned a %2$s: %3$s', 'badgeos-community' ), bp_core_get_userlink( $user_id ), $post_type_singular_name, '<a href="' . get_permalink( $achievement_id ) . '">' . $post->post_title . '</a>' ),
		'content'      => $content,
		'component'    => 'badgeos',
		'type'         => 'activity_update',
		'primary_link' => get_permalink( $achievement_id ),
		'user_id'      => $user_id
	) );

}
add_action( 'badgeos_award_achievement', 'badgeos_award_achievement_bp_activity', 10, 2 );

/**
 * Filter activity allowed html tags to allow divs with classes and ids.
 *
 * @since 1.0.0
 */
function badgeos_bp_activity_allowed_tags( $activity_allowedtags ) {

	$activity_allowedtags['div'] = array();
	$activity_allowedtags['div']['id'] = array();
	$activity_allowedtags['div']['class'] = array();

	return $activity_allowedtags;
}
add_filter( 'bp_activity_allowed_tags', 'badgeos_bp_activity_allowed_tags' );


/**
 * Adds meta box to achievement types for turning on/off BuddyPress activity posts when a user earns an achievement
 *
 * @since 1.0.0
 */
function badgeos_bp_custom_metaboxes( array $meta_boxes ) {

	// Start with an underscore to hide fields from custom fields list
	$prefix = '_badgeos_';

	// Setup our $post_id, if available
	$post_id = isset( $_GET['post'] ) ? $_GET['post'] : 0;

	// New Achievement Types
	$meta_boxes[] = array(
		'id'         => 'bp_achievement_type_data',
		'title'      => __( 'BuddyPress Member Activity', 'badgeos-community' ),
		'pages'      => array( 'achievement-type' ), // Post type
		'context'    => 'normal',
		'priority'   => 'high',
		'show_names' => true, // Show field names on the left
		'fields'     => array(
			array(
				'name' => __( 'Activity Posts', 'badgeos-community' ),
				'desc' => ' '.__( 'When a user earns any achievements of this type create an activity entry on their profile.', 'badgeos-community' ),
				'id'   => $prefix . 'create_bp_activty',
				'type' => 'checkbox',
			),
			array(
				'name' => __( 'Profile Achievements', 'badgeos-community' ),
				'desc' => ' '.__( 'Display earned achievements of this type in the user profile "Achievements" section.', 'badgeos-community' ),
				'id'   => $prefix . 'show_bp_member_menu',
				'type' => 'checkbox',
			),
		)
	);

	return $meta_boxes;
}
add_filter( 'cmb_meta_boxes', 'badgeos_bp_custom_metaboxes' );
