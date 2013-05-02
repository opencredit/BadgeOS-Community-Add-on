<?php

/**
 * Check if user deserves a specific-group-based step
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
		return;

	// Grab our step requirements
	$requirements = badgeos_get_step_requirements( $achievement_id );

	// If the step is triggered by joining a specific group
	if ( 'groups_join_specific_group' == $requirements['trigger_type'] ) {
		// And our user is a part of that group, return true
		if ( groups_is_user_member( $user_id, $requirements['group_id'] ) )
			$return = true;
		// Else, return false
		else
			$return = false;
	}

	return $return;
}
add_action( 'user_deserves_achievement', 'badgeos_bp_user_deserves_group_step', 15, 3 );

/**
 * Add a BuddyPress group selector to the Steps UI
 *
 * @since 1.0.0
 * @param integer $step_id The given step's post ID
 * @param integer $post_id The given parent post's post ID
 */
function badgeos_bp_step_group_select( $step_id, $post_id ) {

	// Setup our select input
	echo '<select name="group_id" class="select-group-id">';
	echo '<option value="">' . __( 'Select a Group', '' ) . '</option>';

	// Loop through all existing BP groups and include them here
	$current_selection = get_post_meta( $step_id, '_badgeos_group_id', true );
	$bp_groups = groups_get_groups( array( 'show_hidden' => true, 'per_page' => 300 ) );
	if ( !empty( $bp_groups ) ) {
		foreach ( $bp_groups['groups'] as $group ) {
			echo '<option' . selected( $current_selection, $group->id, false ) . ' value="' . $group->id . '">' . $group->name . '</option>';
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

	// If we're working with an activity type of step...
	if ( 'groups_join_specific_group' == $step_data['trigger_type'] ) {

		// Grab our group ID
		$group_id = isset( $step_data['group_id'] ) ? $step_data['group_id'] : 1;

		// Store our group ID in meta
		update_post_meta( $step_id, '_badgeos_group_id', $group_id );

		// Pass along our custom post title
		$title = sprintf( __( 'Join group "%s"', 'mvp' ), bp_get_group_name( groups_get_group( array( 'group_id' => $group_id ) ) ) );
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
			if ( 'groups_join_specific_group' == trigger_type.val() ) {
				trigger_type.siblings('.select-group-id').show();
			} else {
				trigger_type.siblings('.select-group-id').hide();
			}

		});

		// Trigger a change so we properly show/hide our group id selector on page load
		$('.select-trigger-type').change();

		// Inject our custom step details into the update step action
		$(document).on( 'update_step_data', function( event, step_details, step ) {
			step_details.group_id = step.children('.select-group-id').val();
		});

	});
	</script>
<?php }
add_action( 'admin_footer', 'badgeos_bp_step_js' );