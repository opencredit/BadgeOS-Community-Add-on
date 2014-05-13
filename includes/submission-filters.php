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
			$output .= '<select name="group_id" id="group_id">';
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

	if ( ! isset( $_REQUEST['group_id'] ) ) {
		return $args;
	}

	$bp_member_ids = array();
	$bp_group_id = absint( $_REQUEST['group_id'] );

	if ( $bp_group_id ) {
		$bp_member_ids = badgeos_bp_get_group_member_ids_from_group( $bp_group_id );
	}

	if ( is_array( $bp_member_ids ) && !empty( $bp_member_ids ) ) {
		$args['author__in'] = $bp_member_ids;
	}

	return $args;
}
add_filter( 'badgeos_get_feedback_args', 'badgeos_bp_filter_feedback_args' );

function badgeos_bp_get_group_member_ids_from_group( $bp_group_id = 0 ) {
	$bp_group_members = groups_get_group_members(
		array(
			'group_id' => $bp_group_id
		)
	);

	if ( !empty( $bp_group_members['members'] ) ) {
		$bp_member_ids = wp_list_pluck( $bp_group_members['members'], 'ID' );
	}

	//wp_list_pluck returns an array with an empty 0 index if nothing found.
	return ( !empty( $bp_member_ids[0] ) ) ? $bp_member_ids : array();
}
