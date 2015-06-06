<?php
/**
 * Custom Achievement Steps UI
 *
 * @package BadgeOS Community
 * @subpackage Achievements
 * @author LearningTimes, LLC
 * @license http://www.gnu.org/licenses/agpl.txt GNU AGPL v3.0
 * @link https://credly.com
 */

/**
 * Update badgeos_get_step_requirements to include our custom requirements
 *
 * @since  1.0.0
 * @param  array   $requirements The current step requirements
 * @param  integer $step_id      The given step's post ID
 * @return array                 The updated step requirements
 */
function badgeos_bp_step_requirements( $requirements, $step_id ) {
	// Add our new requirements to the list
	$requirements['community_trigger'] = get_post_meta( $step_id, '_badgeos_community_trigger', true );
	$requirements['group_id'] = get_post_meta( $step_id, '_badgeos_group_id', true );

	// Return the requirements array
	return $requirements;
}
add_filter( 'badgeos_get_step_requirements', 'badgeos_bp_step_requirements', 10, 2 );

/**
 * Filter the BadgeOS Triggers selector with our own options
 *
 * @since  1.0.0
 * @param  array $triggers The existing triggers array
 * @return array           The updated triggers array
 */
function badgeos_bp_activity_triggers( $triggers ) {
	$triggers['community_trigger'] = __( 'Community Activity', 'badgeos-community' );
	return $triggers;
}
add_filter( 'badgeos_activity_triggers', 'badgeos_bp_activity_triggers' );

/**
 * Add a Community Triggers selector to the Steps UI
 *
 * @since 1.0.0
 * @param integer $step_id The given step's post ID
 * @param integer $post_id The given parent post's post ID
 */
function badgeos_bp_step_community_trigger_select( $step_id, $post_id ) {

	// Setup our select input
	echo '<select name="community_trigger" class="select-community-trigger select-community-trigger-' . $post_id . '">';
	echo '<option value="">' . __( 'Select a Community Trigger', 'badgeos-community' ) . '</option>';

	// Loop through all of our community trigger groups
	$current_selection = get_post_meta( $step_id, '_badgeos_community_trigger', true );
	$community_triggers = $GLOBALS['badgeos_community']->community_triggers;
	if ( !empty( $community_triggers ) ) {
		foreach ( $community_triggers as $optgroup_name => $triggers ) {
			echo '<optgroup label="' . $optgroup_name . '">';
			// Loop through each trigger in the group
			foreach ( $triggers as $trigger_hook => $trigger_name )
				echo '<option' . selected( $current_selection, $trigger_hook, false ) . ' value="' . $trigger_hook . '">' . $trigger_name . '</option>';
			echo '</optgroup>';
		}
	}

	echo '</select>';

}
add_action( 'badgeos_steps_ui_html_after_trigger_type', 'badgeos_bp_step_community_trigger_select', 10, 2 );

/**
 * Add a BuddyPress group selector to the Steps UI
 *
 * @since 1.0.0
 * @param integer $step_id The given step's post ID
 * @param integer $post_id The given parent post's post ID
 */
function badgeos_bp_step_group_select( $step_id, $post_id ) {

	// Setup our select input
	echo '<select name="group_id" class="select-group-id select-group-id-' . $post_id . '">';
	echo '<option value="">' . __( 'Select a Group', 'badgeos-community' ) . '</option>';

	// Loop through all existing BP groups and include them here
	if ( function_exists( 'bp_is_active' ) && bp_is_active( 'groups' ) ) {
		$current_selection = get_post_meta( $step_id, '_badgeos_group_id', true );
		$bp_groups = groups_get_groups( array( 'show_hidden' => true, 'per_page' => 300 ) );
		if ( !empty( $bp_groups ) ) {
			foreach ( $bp_groups['groups'] as $group ) {
				echo '<option' . selected( $current_selection, $group->id, false ) . ' value="' . $group->id . '">' . $group->name . '</option>';
			}
		}
	}
	echo '</select>';

}
add_action( 'badgeos_steps_ui_html_after_trigger_type', 'badgeos_bp_step_group_select', 10, 2 );

/**
 * AJAX Handler for saving all steps
 *
 * @since  1.0.0
 * @param  string  $title     The original title for our step
 * @param  integer $step_id   The given step's post ID
 * @param  array   $step_data Our array of all available step data
 * @return string             Our potentially updated step title
 */
function badgeos_bp_save_step( $title, $step_id, $step_data ) {

	// If we're working on a community trigger
	if ( 'community_trigger' == $step_data['trigger_type'] ) {

		// Update our community trigger post meta
		update_post_meta( $step_id, '_badgeos_community_trigger', $step_data['community_trigger'] );

		// Rewrite the step title
		$title = $step_data['community_trigger_label'];

		// If we're looking to join a specific group...
		if ( 'groups_join_specific_group' == $step_data['community_trigger'] && function_exists( 'bp_get_group_name' ) ) {

			// Store our group ID in meta
			update_post_meta( $step_id, '_badgeos_group_id', $step_data['group_id'] );

			// Pass along our custom post title
			$title = sprintf( __( 'Join group "%s"', 'badgeos-community' ), bp_get_group_name( groups_get_group( array( 'group_id' => $step_data['group_id'] ) ) ) );
		}
	}

	// Send back our custom title
	return $title;
}
add_filter( 'badgeos_save_step', 'badgeos_bp_save_step', 10, 3 );

/**
 * Include custom JS for the BadgeOS Steps UI
 *
 * @since 1.0.0
 */
function badgeos_bp_step_js() { ?>
	<script type="text/javascript">
	jQuery(document).ready(function($) {

		// Listen for our change to our trigger type selector
		$( document ).on( 'change', '.select-trigger-type', function() {

			var trigger_type = $(this);

			// Show our group selector if we're awarding based on a specific group
			if ( 'community_trigger' == trigger_type.val() ) {
				trigger_type.siblings('.select-community-trigger').show().change();
			} else {
				trigger_type.siblings('.select-community-trigger').hide().change();
			}

		});

		// Listen for our change to our trigger type selector
		$( document ).on( 'change', '.select-community-trigger', function() {

			var trigger_type = $(this);

			// Show our group selector if we're awarding based on a specific group
			if ( 'groups_join_specific_group' == trigger_type.val() ) {
				trigger_type.siblings('.select-group-id').show();
			} else {
				trigger_type.siblings('.select-group-id').hide();
			}

		});

		// Trigger a change so we properly show/hide our community menues
		$('.select-trigger-type').change();

		// Inject our custom step details into the update step action
		$(document).on( 'update_step_data', function( event, step_details, step ) {
			step_details.community_trigger = $('.select-community-trigger', step).val();
			step_details.community_trigger_label = $('.select-community-trigger option', step).filter(':selected').text();
			step_details.group_id = $('.select-group-id', step).val();
		});

	});
	</script>
<?php }
add_action( 'admin_footer', 'badgeos_bp_step_js' );
