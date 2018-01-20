<?php




add_action('nopage/has_content', 'nopage_check_elementor_content', 10, 2);
function nopage_check_elementor_content($has_content, $id){
	if(!$has_content && class_exists('\\Elementor\\Plugin'))
		$has_content = \Elementor\Plugin::$instance->db->is_built_with_elementor( $id ?: get_the_ID() );
	return $has_content;
}
