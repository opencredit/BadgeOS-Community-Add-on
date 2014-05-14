<?php

function badgeos_bp_group_submission_filters( $output, $atts ) {

	if ( 'false' !== $atts['show_filter'] ) {
		$bp_public_groups = groups_get_groups(
			array(
				'orderby' => 'name',
				'order'   => 'ASC'
			)
		);

		if ( $bp_public_groups['total'] > 0 ) {
			$output .= '<div class="badgeos-feedback-bp-groups">';
			$output .= '<select name="group_id">';
				$output .= '<option>' . __( 'BuddyPress Group', 'badgeos-community' ) . '</option>';
			foreach( $bp_public_groups['groups'] as $group ) {
				$output .= '<option value="' . $group->id . '">' . $group->name . '</option>';
			}
			$output .= '</select>';
			$output .= '</div>';
		}
	}

	return $output;
}
add_filter( 'badgeos_render_feedback_filters', 'badgeos_bp_group_submission_filters', 10, 2 );

function badgeos_bp_filter_feedback_args( $args ) {

	if ( isset( $_REQUEST['group_id'] ) ) {
		$bp_member_ids = badgeos_bp_get_group_member_ids_from_group( $_REQUEST['group_id'] );

		if ( ! empty( $bp_member_ids ) ) {
			$args['author__in'] = $bp_member_ids;
		}
	}

	return $args;
}
add_filter( 'badgeos_get_feedback_args', 'badgeos_bp_filter_feedback_args' );

function badgeos_bp_get_group_member_ids_from_group( $group_id = 0 ) {
	$group_members = groups_get_group_members( array( 'group_id' => absint( $group_id ) ) );

	return ( ! empty( $group_members['members'] ) ) ? wp_list_pluck( $bp_group_members['members'], 'ID' ) : array();
}

function badgeos_bp_submissions_atts( $atts ) {
	$atts['filters']['group_id'] = '.badgeos-feedback-bp-groups select';

	return $atts;
}
add_filter( 'shortcode_atts_badgeos_submissions', 'badgeos_bp_submissions_atts' );
