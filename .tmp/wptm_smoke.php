<?php
if (! defined('DOING_AJAX')) {
	define('DOING_AJAX', true);
}

wp_set_current_user(1);

$results = array();
$results['plugin_version'] = defined('WPTOMEDIUM_VERSION') ? WPTOMEDIUM_VERSION : 'missing';

$post_id = wp_insert_post(array(
	'post_title'   => 'WPtoMedium Smoke Test',
	'post_content' => '<!-- wp:paragraph --><p>Hallo Welt</p><!-- /wp:paragraph -->',
	'post_status'  => 'publish',
	'post_type'    => 'post',
));

if (is_wp_error($post_id) || ! $post_id) {
	echo json_encode(array('error' => 'Could not create test post'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	exit(1);
}

$results['post_id'] = $post_id;

$original_api_key = get_option('wptomedium_api_key', null);
update_option('wptomedium_api_key', '');

$translator = new WPtoMedium_Translator();
$translate_no_key = $translator->translate($post_id);
$results['translate_no_key'] = $translate_no_key;
$results['translate_no_key_ok'] = (
	isset($translate_no_key['success'], $translate_no_key['message'])
	&& false === $translate_no_key['success']
	&& false !== strpos($translate_no_key['message'], 'API key')
);

if (null === $original_api_key) {
	delete_option('wptomedium_api_key');
} else {
	update_option('wptomedium_api_key', $original_api_key);
}

$sanitized = WPtoMedium_Translator::sanitize_medium_html('<p>ok</p><script>alert(1)</script><h2>X</h2>');
$results['sanitize_removes_script'] = (false === strpos($sanitized, 'script') && false === strpos($sanitized, 'alert(1)'));

update_post_meta($post_id, '_wptomedium_translated_title', 'Smoke Title');
update_post_meta($post_id, '_wptomedium_translation', '<p>Hello <strong>World</strong></p>');

function wptm_run_ajax($callable, $post_data) {
	$_POST = $post_data;
	$_REQUEST = array_merge($_REQUEST, $post_data);

	$die_handler = static function($message = '', $title = '', $args = array()) {
		throw new RuntimeException(is_scalar($message) ? (string) $message : 'wp_die');
	};
	$wp_die_handler_filter = static function() use ($die_handler) {
		return $die_handler;
	};

	add_filter('wp_die_handler', $wp_die_handler_filter, 9999);
	add_filter('wp_die_ajax_handler', $wp_die_handler_filter, 9999);

	ob_start();
	try {
		call_user_func($callable);
	} catch (Throwable $e) {
		// expected: wp_send_json_* ends execution via wp_die.
	}
	$output = trim(ob_get_clean());

	remove_filter('wp_die_handler', $wp_die_handler_filter, 9999);
	remove_filter('wp_die_ajax_handler', $wp_die_handler_filter, 9999);

	$decoded = json_decode($output, true);
	return array(
		'raw' => $output,
		'json' => is_array($decoded) ? $decoded : null,
	);
}

$nonce = wp_create_nonce('wptomedium_nonce');

$copy_result = wptm_run_ajax(array('WPtoMedium_Workflow', 'ajax_copy_markdown'), array(
	'nonce'   => $nonce,
	'post_id' => $post_id,
));
$results['ajax_copy_markdown'] = $copy_result['json'];
$results['ajax_copy_markdown_ok'] = (
	isset($copy_result['json']['success'])
	&& true === $copy_result['json']['success']
	&& isset($copy_result['json']['data']['markdown'])
	&& false !== strpos($copy_result['json']['data']['markdown'], '# Smoke Title')
);

$mark_result = wptm_run_ajax(array('WPtoMedium_Workflow', 'ajax_mark_copied'), array(
	'nonce'   => $nonce,
	'post_id' => $post_id,
));
$results['ajax_mark_copied'] = $mark_result['json'];
$results['ajax_mark_copied_ok'] = (
	isset($mark_result['json']['success'])
	&& true === $mark_result['json']['success']
	&& 'copied' === get_post_meta($post_id, '_wptomedium_status', true)
);

$invalid_result = wptm_run_ajax(array('WPtoMedium_Workflow', 'ajax_copy_markdown'), array(
	'nonce'   => $nonce,
	'post_id' => 999999999,
));
$results['ajax_invalid_post'] = $invalid_result['json'];
$results['ajax_invalid_post_ok'] = (
	isset($invalid_result['json']['success'])
	&& false === $invalid_result['json']['success']
	&& isset($invalid_result['json']['data'])
	&& false !== strpos((string) $invalid_result['json']['data'], 'Invalid post ID')
);

$original_api_key = get_option('wptomedium_api_key', null);
update_option('wptomedium_api_key', '');

$translate_ajax = wptm_run_ajax(array('WPtoMedium_Workflow', 'ajax_translate'), array(
	'nonce'   => $nonce,
	'post_id' => $post_id,
));
$results['ajax_translate_no_key'] = $translate_ajax['json'];
$status_after_failed_translate = get_post_meta($post_id, '_wptomedium_status', true);
$results['ajax_translate_no_key_ok'] = (
	isset($translate_ajax['json']['success'])
	&& false === $translate_ajax['json']['success']
	&& false !== strpos((string) $translate_ajax['json']['data'], 'API key')
	&& '' === (string) $status_after_failed_translate
);

if (null === $original_api_key) {
	delete_option('wptomedium_api_key');
} else {
	update_option('wptomedium_api_key', $original_api_key);
}

$results['overall_ok'] = (
	! empty($results['translate_no_key_ok'])
	&& ! empty($results['sanitize_removes_script'])
	&& ! empty($results['ajax_copy_markdown_ok'])
	&& ! empty($results['ajax_mark_copied_ok'])
	&& ! empty($results['ajax_invalid_post_ok'])
	&& ! empty($results['ajax_translate_no_key_ok'])
);

wp_delete_post($post_id, true);

echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
