<?php

function badgeos_bp_group_submission_filters( $output, $atts ) {

	if ( 'false' !== $atts['show_filter'] ) {
		$bp_public_groups = groups_get_groups();

		if ( $bp_public_groups['total'] > 0 ) {
			$output .= '<div class="badgeos-feedback-bp-groups">';
			$output .= '<select name="badgeos_bp_groups" id="badgeos_bp_groups">';
			foreach( $bp_public_groups['groups'] as $group ) {
				$output .= '<option value="' . $group->id . '">' . $group->name . '</option>';
			}
			$output .= '</select>';
			$output .= '</div>';
		}
	}

	return $output;
}
add_filter( 'badgeos_render_feedback', 'badgeos_bp_group_submission_filters', 10, 2 );
