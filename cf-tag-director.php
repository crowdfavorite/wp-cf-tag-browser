<?php

/*
Plugin Name: Tag Director
Plugin URI: http://crowdfavorite.com/wordpress/
Description: Psuedo-hierarchical tag browsing. Inspired by Johnvey's excellent Del.icio.us Direc.tor.
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
Version: 1.0
*/

load_plugin_textdomain('cf_tag_director');

function cftd_director() {
	echo cftd_get_director();
}

function cftd_get_director() {
	$tags = get_tags();
	if (!count($tags)) {
		return '<div class="cftd_empty">'.__('No tags - add some!', 'cf_tag_director').'</div>';
	}
	$categories = get_categories('hide_empty=0');
	$cat_options = '<option value="" selected="selected">'.__('All', 'cf_tag_director').'</option>'.PHP_EOL;
	foreach ($categories as $category) {
		$cat_options .= '<option value="'.$category->term_id.'">'.htmlspecialchars($category->name).'</option>'.PHP_EOL;
	}
	return '
<h3>'.__('Tags', 'cf_tag_director').'</h3>
<div class="cftd_cat">
	<label for="cftd_category">'.__('Limit to Category:', 'cf_tag_director').'</label>
	<select id="cftd_category" name="cftd_category">
	'.$cat_options.'
	</select>
</div>
<div class="cftd_tags">
	<div class="column" rel="column_1">
	'.cftd_tags_html($tags).'
	</div>
	<div class="clear"></div>
</div>
<h3>'.__('Posts', 'cf_tag_director').'</h3>
<div class="cftd_posts"></div>
	';
}
add_shortcode('tag-director', 'cftd_get_director');

function cftd_related_tags($tags = array(), $cat = null) {
	global $wpdb;
	$related = array();
	$post_ids = cftd_post_ids_by_tags($tags, $cat);
	if (count($post_ids)) {
		// category filtering already done in cftd_post_ids_by_tags()
		$related = $wpdb->get_results("
			SELECT t.*
			FROM $wpdb->terms t
			JOIN $wpdb->term_taxonomy tt
			ON t.term_id = tt.term_id
			JOIN $wpdb->term_relationships tr
			ON tr.term_taxonomy_id = tt.term_taxonomy_id
			WHERE tt.taxonomy = 'post_tag'
			AND t.slug NOT IN ('".implode("','", $tags)."')
			AND tr.object_id IN (".implode(',', $post_ids).")
			GROUP BY t.term_id
			ORDER BY t.name
		");
	}
	return $related;
}

function cftd_post_ids_by_tags($tags, $cat = null) {
	$posts = cftd_posts_by_tags($tags, $cat);
	$post_ids = array();
	foreach ($posts as $post) {
		$post_ids[] = $post->ID;
	}
	return $post_ids;
}

function cftd_posts_by_tags($tags, $cat = null) {
	global $post;
	$posts = array();
	$args = array();
	if (count($tags)) {
		$args[] = 'tag='.$tags[0].'+'.implode('+', $tags);
	}
	if ($cat) {
		$args[] = 'cat='.$cat;
	}
	$query = new WP_Query(implode('&', $args));
	while ($query->have_posts()) {
		$query->the_post();
		$posts[] = $post;
	}
	return $posts;
}

function cftd_tags_html($tags) {
	$output = '<ul>';
	if (is_array($tags) && count($tags)) {
		foreach ($tags as $tag) {
			$output .= cftd_tag_html($tag);
		}
	}
	else {
		$output .= '<li class="none">'.__('No tags found.', 'cf_tag_director').'</li>';
	}
	$output .= '</ul>';
	return $output;
}

function cftd_tag_html($tag) {
	return '<li><a href="'.get_tag_link($tag->term_id).'" rel="tag-'.$tag->slug.'">'.htmlspecialchars($tag->name).'</a></li>';
}

function cftd_posts_html($posts) {
	$output = '<ul>';
	if (is_array($posts) && count($posts)) {
		foreach ($posts as $post) {
			$output .= '<li><a href="'.get_permalink($post->ID).'">'.get_the_title($post->ID).'</a><span>Tags: '.get_the_term_list($post->ID, 'post_tag', '', ', ').'</span></li>';
		}
	}
	else {
		$output .= '<li class="none">'.__('No posts found.', 'cf_tag_director').'</li>';
	}
	$output .= '</ul>';
	return $output;
}

function cftd_request_handler() {
	if (!empty($_GET['cf_action'])) {
		switch ($_GET['cf_action']) {
			case 'cftd_get_related':
				if (!empty($_GET['cftd_tags'])) {
					$tags = explode(',', $_GET['cftd_tags']);
				}
				else {
					$tags = array();
				}
				if (!empty($_GET['cftd_cat'])) {
					$cat = intval($_GET['cftd_cat']);
				}
				else {
					$cat = null;
				}
				if ((!is_array($tags) || !count($tags)) && $cat == 0) {
					$related = get_tags();
				}
				else {
					$related = cftd_related_tags($tags, $cat);
				}
				$related_html = cftd_tags_html($related);
				if (count($tags)) {
					$posts = cftd_posts_by_tags($tags, $cat);
				}
				else {
					$posts = array();
				}
				$posts_html = cftd_posts_html($posts);
				$data = array(
					'tags' => $related_html
					, 'posts' => $posts_html
				);
				cf_json_out($data);
				die();
				break;
			case 'cftd_page':
				cftd_template_redirect();
				die();
				break;
			case 'cftd_js':
				header('Content-type: text/javascript');
?>
cftd = {}

cftd.tpl_loading = function() {
	return '<div class="loading"><span><?php _e('Loading...', 'cf_tag_director'); ?></span></div>';
}

cftd.direct = function(this_col, next_col) {
	for (var i = next_col; i < 999; i++) {
		var test = jQuery('.cftd_tags [rel="column_' + i + '"]');
		test.size() ? test.remove() : i = 999;
	}
	tags = [];
	jQuery('.cftd_tags li a.selected').each(function() {
		tags[tags.length] = jQuery(this).attr('rel').replace('tag-', '');
	});
	loading = '<div class="column" rel="column_' + next_col + '">' + cftd.tpl_loading() + '</div>';
	this_col_elem = jQuery('.cftd_tags [rel="column_' + this_col + '"]');
	if (this_col_elem.size()) {
		this_col_elem.after(loading);
	}
	else {
		jQuery('.cftd_tags').html(loading + '<div class="clear"></div>');
	}
	jQuery('.cftd_tags [rel="column_' + next_col + '"]').css('left', (this_col * 150) + 'px');
	jQuery('.cftd_posts').html(cftd.tpl_loading());
	jQuery.get(
		'<?php echo trailingslashit(get_bloginfo('url')); ?>'
		, {
			cf_action: 'cftd_get_related'
			, cftd_tags: tags.join(',')
			, cftd_cat: jQuery('#cftd_category').val()
		}
		, function(response) {
			var result = eval('(' + response + ')');
			jQuery('.cftd_tags [rel="column_' + next_col + '"]').html(result.tags);
			jQuery('.cftd_posts').html(result.posts);
			cftd.handlers();
		}
	);
}

cftd.handlers = function() {
	jQuery('#cftd_category').unbind().change(function() {
		cftd.direct(0, 1);
	});
	jQuery('.cftd_tags li a').unbind().click(function() {
		var parent_div = jQuery(this).parent().parent().parent();
		parent_div.find('a.selected').removeClass('selected');
		jQuery(this).addClass('selected');
		this_col = parseInt(parent_div.attr('rel').replace('column_', ''));
		next_col = this_col + 1;
		cftd.direct(this_col, next_col);
		return false;
	});
}

jQuery(function() {
	cftd.handlers();
});

<?php
				die();
				break;
			case 'cftd_css':
				header('Content-type: text/css');
				$tags_height = '300';
				$tags_width = '150';
?>
.clear {
	clear: both;
	float: none;
}
.cftd_tags .loading, .cftd_posts .loading {
	color: #999;
}
.cftd_cat {
	background: #eee;
	border: 1px solid #ccc;
	border-width: 1px 0;
	padding: 5px;
}
.cftd_tags {
	height: <?php echo $tags_height; ?>px;
	overflow: auto;
	position: relative;
}
.cftd_tags .column {
	border-left: 1px solid #ccc;
	height: <?php echo $tags_height; ?>px;
	left: 0;
	overflow: auto;
	position: absolute;
	top: 0;
	width: <?php echo $tags_width; ?>px;
}
.cftd_tags .column ul, .cftd_tags .column ul li {
	list-style: none;
	list-style-image: url();
	margin: 0;
	overflow: hidden;
	padding: 0;
	text-indent: 0;
}
.cftd_tags .column ul li:before, .cftd_posts ul li:before {
	content: '';
}
.cftd_tags .column ul li.none {
	color: #999;
	padding: 10px 10px;
}
.cftd_tags .column ul li a {
	display: block;
	padding: 2px 5px;
}
.cftd_tags .column ul li a.selected {
	background: #eee;
	font-weight: bold;
}
.cftd_posts ul {
	list-style: none;
	margin: 0;
	padding: 0;
}
.cftd_posts ul li {
	font-size: 13px;
	margin: 0 0 10px;
	padding: 0;
}
.cftd_posts ul li span {
	color: #999;
	display: block;
	font-size: 11px;
	margin-top: 3px;
}
.cftd_posts ul li span a, .cftd_posts ul li span a:visited {
	color: #666;
}
<?php
				die();
				break;
			case 'cftd_admin_css':
				header('Content-type: text/css');
?>
fieldset.options div.option {
	background: #EAF3FA;
	margin-bottom: 8px;
	padding: 10px;
}
fieldset.options div.option label {
	display: block;
	float: left;
	font-weight: bold;
	margin-right: 10px;
	width: 150px;
}
fieldset.options div.option span.help {
	color: #666;
	font-size: 11px;
	margin-left: 8px;
}
<?php
				die();
				break;
		}
	}
	if (!empty($_POST['cf_action'])) {
		switch ($_POST['cf_action']) {
			case 'cftd_update_settings':
				cftd_save_settings();
				wp_redirect(trailingslashit(get_bloginfo('wpurl')).'wp-admin/options-general.php?page='.basename(__FILE__).'&updated=true');
				die();
				break;
		}
	}
}
add_action('init', 'cftd_request_handler');

wp_enqueue_script('jquery');
wp_enqueue_script('cftd_js', trailingslashit(get_bloginfo('url')).'?cf_action=cftd_js', 'jquery');

function cftd_wp_head() {
	echo '<link rel="stylesheet" type="text/css" href="'.trailingslashit(get_bloginfo('url')).'?cf_action=cftd_css" />';
}
add_action('wp_head', 'cftd_wp_head');

function cftd_admin_head() {
	echo '<link rel="stylesheet" type="text/css" href="'.trailingslashit(get_bloginfo('url')).'?cf_action=cftd_admin_css" />';
}
add_action('admin_head', 'cftd_admin_head');

if (cftd_setting('cftd_create_page') == 'yes') {

function cftd_generate_rewrite_rules() {
	global $wp_rewrite;
	$slug = cftd_setting('cftd_slug');
	$new_rules = array(
		$slug => 'index.php?pagename=home&cf_action=cftd_page'
	);
	$wp_rewrite->rules = $new_rules + $wp_rewrite->rules;		
}
add_action('generate_rewrite_rules','cftd_generate_rewrite_rules');

function cf_flushRewriteRules() {
	global $wp_rewrite;
	$wp_rewrite->flush_rules();
}
add_action('init', 'cf_flushRewriteRules');

function cftd_query_vars($vars) {
	array_push($vars, 'cf_action');
	return $vars;
}
add_action('query_vars', 'cftd_query_vars');

function cftd_template_redirect() {
	if (get_option('permalink_structure') != '') {
		$cf_action = get_query_var('cf_action');
	}
	else if (!empty($_GET['cf_action']) && $_GET['cf_action'] == 'cftd_page') {
		$cf_action = 'cftd_page';
	}
	if ($cf_action == 'cftd_page') {
		global $wp_query;
		$wp_query->post_type = 'page';
		$wp_query->post_count = 1;
		$wp_query->current_post = -1;
		add_filter('the_title', 'cftd_the_title');
		add_filter('the_content', 'cftd_the_content');
		$template = get_page_template();
		include($template);
		die();
	}
}
add_action('template_redirect', 'cftd_template_redirect');

function cftd_the_title($title) {
	remove_filter('the_title', 'cftd_the_title');
	return cftd_setting('cftd_page_title');
}

function cftd_the_content($content) {
	remove_filter('the_content', 'cftd_the_content');
	return cftd_get_director();
}

function cftd_list_pages($output) {
	if (get_option('permalink_structure') == '') {
		$url = trailingslashit(get_bloginfo('url')).'?cf_action=cftd_page';
	}
	else {
		$url = trailingslashit(get_bloginfo('url')).trailingslashit(cftd_setting('cftd_slug'));
	}
	$output .= '<li><a href="'.$url.'">'.__('Tag Browser', 'cf_tag_director').'</a></li>';
	if (strpos($output, '<li class="pagenav">') !== false) {
		$output = str_replace('</ul></li>', '', $output).'</ul></li>';
	}
	return $output;
}
add_filter('wp_list_pages', 'cftd_list_pages');

}

$cftd_settings = array(
	'cftd_create_page' => array(
		'type' => 'select',
		'label' => 'Auto-create Tag Browser Page',
		'default' => 'yes',
		'help' => 'If this is set to No, you can use the template tag or shortcode to add the browser to a page.',
		'options' => array(
			'yes' => 'Yes',
			'no' => 'No',
		)
	),
	'cftd_slug' => array(
		'type' => 'string',
		'label' => 'Page URL',
		'default' => 'tag-browser',
		'help' => 'default: tag-browser'
	),
	'cftd_page_title' => array(
		'type' => 'string',
		'label' => 'Page Title',
		'default' => 'Tag Browser',
	),
);

function cftd_setting($option) {
	$value = get_option($option);
	if (empty($value)) {
		global $cftd_settings;
		$value = $cftd_settings[$option]['default'];
	}
	return $value;
}

function cftd_admin_menu() {
	if (current_user_can('manage_options')) {
		add_options_page(
			__('Tag Director Settings', '')
			, __('Tag Director', '')
			, 10
			, basename(__FILE__)
			, 'cftd_settings_form'
		);
	}
}
add_action('admin_menu', 'cftd_admin_menu');

function cftd_plugin_action_links($links, $file) {
	$plugin_file = basename(__FILE__);
	if ($file == $plugin_file) {
		$settings_link = '<a href="options-general.php?page='.$plugin_file.'">'.__('Settings', '<?php echo $localization; ?>').'</a>';
		array_unshift($links, $settings_link);
	}
	return $links;
}
add_filter('plugin_action_links', 'cftd_plugin_action_links', 10, 2);

if (!function_exists('cf_settings_field')) {

function cf_settings_field($key, $config) {
	$option = get_option($key);
	if (empty($option) && !empty($config['default'])) {
		$option = $config['default'];
	}
	$label = '<label for="'.$key.'">'.$config['label'].'</label>';
	$help = '<span class="help">'.$config['help'].'</span>';
	switch ($config['type']) {
		case 'select':
			$output = $label.'<select name="'.$key.'" id="'.$key.'">';
			foreach ($config['options'] as $val => $display) {
				$option == $val ? $sel = ' selected="selected"' : $sel = '';
				$output .= '<option value="'.$val.'"'.$sel.'>'.$display.'</option>';
			}
			$output .= '</select>'.$help;
			break;
		case 'string':
		case 'int':
		default:
			$output = $label.'<input name="'.$key.'" id="'.$key.'" value="'.$option.'" />'.$help;
			break;
	}
	return '<div class="option">'.$output.'<div class="clear"></div></div>';
}

}

function cftd_settings_form() {
	global $cftd_settings;
	print('
<div class="wrap">
	<h2>'.__('Tag Director Settings', '').'</h2>
	<form id="cftd_settings_form" name="cftd_settings_form" action="'.get_bloginfo('wpurl').'/wp-admin/options-general.php" method="post">
		<input type="hidden" name="cf_action" value="cftd_update_settings" />
		<fieldset class="options form-table">
	');
	foreach ($cftd_settings as $key => $config) {
		echo cf_settings_field($key, $config);
	}
	print('
		</fieldset>
		<p class="submit">
			<input type="submit" name="submit" value="'.__('Save Settings', '').'" />
		</p>
	</form>
	<h2>'.__('Adding Manually', 'cf_tag_director').'</h2>
	<p>'.__("If you don't auto-create a page, you can add the Tag Browser to your site using the following methods.", 'cf_tag_director').'</p>
	<h3>'.__('Template Tag', 'cf_tag_director').'</h3>
	<p>'.__('Add this to your template page:', 'cf_tag_director').'</p>
	<p><code>&lt;?php if (function_exists("cftd_director")) { cftd_director(); } ?&gt;</code></p>
	<h3>'.__('Shortcode', 'cf_tag_director').'</h3>
	<p>'.__('Add this to a post or page: [tag-director]', 'cf_tag_director').'</p>
</div>
	');
}

function cftd_save_settings() {
	if (!current_user_can('manage_options')) {
		return;
	}
	global $cftd_settings;
	foreach ($cftd_settings as $key => $option) {
		$value = '';
		switch ($option['type']) {
			case 'int':
				$value = intval($_POST[$key]);
				break;
			case 'select':
				$test = stripslashes($_POST[$key]);
				if (isset($option['options'][$test])) {
					$value = $test;
				}
				break;
			case 'string':
			default:
				$value = stripslashes($_POST[$key]);
				break;
		}
		update_option($key, $value);
	}
}



// JSON functions below

if (!function_exists('cf_json_out')) {

function cf_json_out($data) {
	header('Content-type: text/javascript');
	echo cf_json_encode($data);
	die();
}

}

if (!function_exists('cf_json_encode')) {

function cf_json_encode($data) {
	if (function_exists('json_encode')) {
		return json_encode($data);
	}
	else {
		return php_json_encode($data);
	}
}

}

if (!function_exists('json_encode_string')) {

function json_encode_string($str) {
/*
	mb_internal_encoding("UTF-8");
	$convmap = array(0x80, 0xFFFF, 0, 0xFFFF);
	$str = '';
	for ($i = mb_strlen($in_str) - 1; $i >= 0; $i--) {
		$mb_char = mb_substr($in_str, $i, 1);
		if (mb_ereg("&#(\\d+);", mb_encode_numericentity($mb_char, $convmap, "UTF-8"), $match)) {
			$str = sprintf("\\u%04x", $match[1]) . $str;
		}
		else {
			$str = $mb_char . $str;
		}
	}
*/
	return str_replace(
		array(
			'"'
			, '/'
			, "\n"
		)
		, array(
			'\"'
			, '\/'
			, '\n'
		)
		, $str
	);
}

function php_json_encode($arr) {
	$json_str = '';
	if (is_array($arr)) {
		$pure_array = true;
		$array_length = count($arr);
		for ( $i = 0; $i < $array_length ; $i++) {
			if (!isset($arr[$i])) {
				$pure_array = false;
				break;
			}
		}
		if ($pure_array) {
			$json_str = '[';
			$temp = array();
			for ($i=0; $i < $array_length; $i++) {
				$temp[] = sprintf("%s", php_json_encode($arr[$i]));
			}
			$json_str .= implode(',', $temp);
			$json_str .="]";
		}
		else {
			$json_str = '{';
			$temp = array();
			foreach ($arr as $key => $value) {
				$temp[] = sprintf("\"%s\":%s", $key, php_json_encode($value));
			}
			$json_str .= implode(',', $temp);
			$json_str .= '}';
		}
	}
	else if (is_object($arr)) {
		$json_str = '{';
		$temp = array();
		foreach ($arr as $k => $v) {
			$temp[] = '"'.$k.'":'.php_json_encode($v);
		}
		$json_str .= implode(',', $temp);
		$json_str .= '}';
	}
	else if (is_string($arr)) {
		$json_str = '"'. json_encode_string($arr) . '"';
	}
	else if (is_numeric($arr)) {
		$json_str = $arr;
	}
	else {
		$json_str = '"'. json_encode_string($arr) . '"';
	}
	return $json_str;
}

}

?>