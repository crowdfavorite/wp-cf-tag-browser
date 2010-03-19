<?php
/*
Plugin Name: CF Tag Browser
Plugin URI: http://crowdfavorite.com/wordpress/
Description: Psuedo-hierarchical tag browsing. Inspired by Johnvey's excellent Del.icio.us Direc.tor.
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
Version: 1.0.1
*/

load_plugin_textdomain('cf_tag_browser');

function cftb_browser() {
	echo cftb_get_browser();
}

function cftb_get_browser() {
	$tags = get_tags();
	if (!count($tags)) {
		return '<div class="cftb_empty">'.__('No tags - add some!', 'cf_tag_browser').'</div>';
	}
	$categories = get_categories('hide_empty=0');
	$cat_options = '<option value="" selected="selected">'.__('All', 'cf_tag_browser').'</option>'.PHP_EOL;
	foreach ($categories as $category) {
		$cat_options .= '<option value="'.$category->term_id.'">'.htmlspecialchars($category->name).'</option>'.PHP_EOL;
	}
	return '
<h3>'.__('Tags', 'cf_tag_browser').'</h3>
<div class="cftb_cat">
	<label for="cftb_category">'.__('Limit to Category:', 'cf_tag_browser').'</label>
	<select id="cftb_category" name="cftb_category">
	'.$cat_options.'
	</select>
</div>
<div class="cftb_tags">
	<div class="column" rel="column_1">
	'.cftb_tags_html($tags).'
	</div>
	<div class="clear"></div>
</div>
<h3>'.__('Posts', 'cf_tag_browser').'</h3>
<div class="cftb_posts"></div>
	';
}
add_shortcode('tag-browser', 'cftb_get_browser');

function cftb_related_tags($tags = array(), $cat = null) {
	global $wpdb;
	
	// Escape the passed in tags for safety, if we have tags
	$escaped = array();
	if (is_array($tags) && !empty($tags)) {
		foreach ($tags as $tag) {
			$escaped[] = $wpdb->escape($tag);
		}
		$tags = $escaped;
	}
	
	
	$related = array();
	$post_ids = cftb_post_ids_by_tags($tags, $cat);
	if (count($post_ids)) {
		// category filtering already done in cftb_post_ids_by_tags()
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

function cftb_post_ids_by_tags($tags, $cat = null) {
	$posts = cftb_posts_by_tags($tags, $cat);
	$post_ids = array();
	foreach ($posts as $post) {
		$post_ids[] = $post->ID;
	}
	return $post_ids;
}

function cftb_posts_by_tags($tags, $cat = null) {
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

function cftb_tags_html($tags) {
	$output = '<ul>';
	if (is_array($tags) && count($tags)) {
		foreach ($tags as $tag) {
			$output .= cftb_tag_html($tag);
		}
	}
	else {
		$output .= '<li class="none">'.__('No tags found.', 'cf_tag_browser').'</li>';
	}
	$output .= '</ul>';
	return $output;
}

function cftb_tag_html($tag) {
	return '<li><a href="'.get_tag_link($tag->term_id).'" rel="tag-'.$tag->slug.'">'.htmlspecialchars($tag->name).'</a></li>';
}

function cftb_posts_html($posts) {
	$output = '<ul>';
	if (is_array($posts) && count($posts)) {
		foreach ($posts as $post) {
			$output .= '<li><a href="'.get_permalink($post->ID).'">'.get_the_title($post->ID).'</a><span>Tags: '.get_the_term_list($post->ID, 'post_tag', '', ', ').'</span></li>';
		}
	}
	else {
		$output .= '<li class="none">'.__('No posts found.', 'cf_tag_browser').'</li>';
	}
	$output .= '</ul>';
	return $output;
}

wp_enqueue_script('jquery');

if (!is_admin()) {
	wp_enqueue_script('cftb_js', trailingslashit(get_bloginfo('url')).'?cf_action=cftb_js', 'jquery');
	wp_enqueue_style('cftb_css',trailingslashit(get_bloginfo('url')).'?cf_action=cftb_css');
}

function cftb_admin_head() {
	echo '<link rel="stylesheet" type="text/css" href="'.trailingslashit(get_bloginfo('url')).'?cf_action=cftb_admin_css" />';
}
add_action('admin_head', 'cftb_admin_head');

function cftb_request_handler() {
	if (!empty($_GET['cf_action'])) {
		switch ($_GET['cf_action']) {
			case 'cftb_page':
				cftb_template_redirect();
				die();
				break;			
			case 'cftb_get_related':
				if (!empty($_GET['cftb_tags'])) {
					$tags = explode(',', $_GET['cftb_tags']);
				}
				else {
					$tags = array();
				}
				if (!empty($_GET['cftb_cat'])) {
					$cat = intval($_GET['cftb_cat']);
				}
				else {
					$cat = null;
				}
				if ((!is_array($tags) || !count($tags)) && $cat == 0) {
					$related = get_tags();
				}
				else {
					$related = cftb_related_tags($tags, $cat);
				}
				$related_html = cftb_tags_html($related);
				if (count($tags)) {
					$posts = cftb_posts_by_tags($tags, $cat);
				}
				else {
					$posts = array();
				}
				$posts_html = cftb_posts_html($posts);
				
				$data = array(
					'tags' => $related_html
					, 'posts' => $posts_html
				);
				cf_json_out($data);
				die();
				break;

			case 'cftb_js':
				header('Content-type: text/javascript');
?>
cftd = {}

cftd.tpl_loading = function() {
	return '<div class="loading"><span><?php _e('Loading...', 'cf_tag_browser'); ?></span></div>';
}

cftd.direct = function(this_col, next_col) {
	for (var i = next_col; i < 999; i++) {
		var test = jQuery('.cftb_tags [rel="column_' + i + '"]');
		test.size() ? test.remove() : i = 999;
	}
	tags = [];
	jQuery('.cftb_tags li a.selected').each(function() {
		tags[tags.length] = jQuery(this).attr('rel').replace('tag-', '');
	});
	loading = '<div class="column" rel="column_' + next_col + '">' + cftd.tpl_loading() + '</div>';
	this_col_elem = jQuery('.cftb_tags [rel="column_' + this_col + '"]');
	if (this_col_elem.size()) {
		this_col_elem.after(loading);
	}
	else {
		jQuery('.cftb_tags').html(loading + '<div class="clear"></div>');
	}
	jQuery('.cftb_tags [rel="column_' + next_col + '"]').css('left', (this_col * 150) + 'px');
	jQuery('.cftb_posts').html(cftd.tpl_loading());
	jQuery.get(
		'<?php echo trailingslashit(get_bloginfo('url')); ?>'
		, {
			cf_action: 'cftb_get_related'
			, cftb_tags: tags.join(',')
			, cftb_cat: jQuery('#cftb_category').val()
		}
		, function(response) {
			var result = eval('(' + response + ')');
			jQuery('.cftb_tags [rel="column_' + next_col + '"]').html(result.tags);
			jQuery('.cftb_posts').html(result.posts);
			cftd.handlers();
		}
	);
}

cftd.handlers = function() {
	jQuery('#cftb_category').unbind().change(function() {
		cftd.direct(0, 1);
	});
	jQuery('.cftb_tags li a').unbind().click(function() {
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
			case 'cftb_css':
				header('Content-type: text/css');
				$tags_height = '300';
				$tags_width = '150';
?>
.clear {
	clear: both;
	float: none;
}
.cftb_tags .loading, .cftb_posts .loading {
	color: #999;
	padding: 5px;
}
.cftb_cat {
	background: #eee;
	border: 1px solid #ccc;
	border-width: 1px;
	padding: 5px 12px;
	text-align: right;
}
.cftb_tags {
	height: <?php echo $tags_height; ?>px;
	overflow: auto;
	position: relative;
	border-right: 1px solid #ccc;
	border-bottom: 1px solid #ccc;
	border-left: 1px solid #ccc;
	margin-bottom: 1em;
}
.cftb_tags .column {
	border-right: 1px solid #ccc;
	height: <?php echo $tags_height; ?>px;
	left: 0;
	overflow: auto;
	position: absolute;
	top: 0;
	width: <?php echo $tags_width - 1; ?>px;
}
.cftb_tags .column ul, .cftb_tags .column ul li {
	list-style: none;
	list-style-image: url();
	margin: 0;
	overflow: hidden;
	padding: 0;
	text-indent: 0;
}
.cftb_tags .column ul li:before, .cftb_posts ul li:before {
	content: '';
}
.cftb_tags .column ul li.none {
	color: #999;
	padding: 10px 10px;
}
.cftb_tags .column ul li a {
	display: block;
	padding: 4px 5px 0px;
}
.cftb_tags .column ul li a.selected {
	background: #eee;
}
.cftb_posts ul {
	list-style: none;
	margin: 0;
	padding: 0;
}
.cftb_posts ul li {
	font-size: 13px;
	margin: 0 0 10px;
	padding: 0;
}
.cftb_posts ul li span {
	color: #999;
	display: block;
	font-size: 11px;
	margin-top: 3px;
}
.cftb_posts ul li span a, .cftb_posts ul li span a:visited {
	color: #666;
}
<?php
				die();
				break;
			case 'cftb_admin_css':
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
			case 'cftb_update_settings':
				cftb_save_settings();
				wp_redirect(trailingslashit(get_bloginfo('wpurl')).'wp-admin/options-general.php?page='.basename(__FILE__).'&updated=true');
				die();
				break;
		}
	}
}
add_action('init', 'cftb_request_handler');

if (cftb_setting('cftb_create_page') == 'yes') {

	function cftb_generate_rewrite_rules() {
		global $wp_rewrite;
		$slug = cftb_setting('cftb_slug');
		$new_rules = array(
			$slug => 'index.php?pagename=home&cf_action=cftb_page'
		);
		$wp_rewrite->rules = $new_rules + $wp_rewrite->rules;		
	}
	add_action('generate_rewrite_rules','cftb_generate_rewrite_rules');

	function cf_flushRewriteRules() {
		global $wp_rewrite;
		$wp_rewrite->flush_rules();
	}
	add_action('init', 'cf_flushRewriteRules');

	function cftb_query_vars($vars) {
		array_push($vars, 'cf_action');
		return $vars;
	}
	add_action('query_vars', 'cftb_query_vars');

	function cftb_template_redirect() {
		if (get_option('permalink_structure') != '') {
			$cf_action = get_query_var('cf_action');
		}
		else if (!empty($_GET['cf_action']) && $_GET['cf_action'] == 'cftb_page') {
			$cf_action = 'cftb_page';
		}
		if ($cf_action == 'cftb_page') {
			global $wp_query;
			$wp_query->post_type = 'page';
			$wp_query->post_count = 1;
			$wp_query->current_post = -1;
			add_filter('the_title', 'cftb_the_title');
			add_filter('the_content', 'cftb_the_content');
			$template = get_page_template();
			include($template);
			die();
		}
		error_log('getting here');
	}
	add_action('template_redirect', 'cftb_template_redirect');

	function cftb_the_title($title) {
		remove_filter('the_title', 'cftb_the_title');
		return cftb_setting('cftb_page_title');
	}

	function cftb_the_content($content) {
		remove_filter('the_content', 'cftb_the_content');
		return cftb_get_browser();
	}

	function cftb_list_pages($output) {
		if (get_option('permalink_structure') == '') {
			$url = trailingslashit(get_bloginfo('url')).'?cf_action=cftb_page';
		}
		else {
			$url = trailingslashit(get_bloginfo('url')).trailingslashit(cftb_setting('cftb_slug'));
		}
		$output .= '<li><a href="'.$url.'">'.__('Tag Browser', 'cf_tag_browser').'</a></li>';
		if (strpos($output, '<li class="pagenav">') !== false) {
			$output = str_replace('</ul></li>', '', $output).'</ul></li>';
		}
		return $output;
	}
	add_filter('wp_list_pages', 'cftb_list_pages');
}

$cftb_settings = array(
	'cftb_create_page' => array(
		'type' => 'select',
		'label' => 'Auto-create Tag Browser Page',
		'default' => 'yes',
		'help' => 'If this is set to No, you can use the template tag or shortcode to add the browser to a page.',
		'options' => array(
			'yes' => 'Yes',
			'no' => 'No',
		)
	),
	'cftb_slug' => array(
		'type' => 'string',
		'label' => 'Page URL',
		'default' => 'tag-browser',
		'help' => 'default: tag-browser'
	),
	'cftb_page_title' => array(
		'type' => 'string',
		'label' => 'Page Title',
		'default' => 'Tag Browser',
	),
);

function cftb_setting($option) {
	$value = get_option($option);
	if (empty($value)) {
		global $cftb_settings;
		$value = $cftb_settings[$option]['default'];
	}
	return $value;
}

function cftb_admin_menu() {
	if (current_user_can('manage_options')) {
		add_options_page(
			__('Tag Browser Settings', '')
			, __('CF Tag Browser', '')
			, 10
			, basename(__FILE__)
			, 'cftb_settings_form'
		);
	}
}
add_action('admin_menu', 'cftb_admin_menu');

function cftb_plugin_action_links($links, $file) {
	$plugin_file = basename(__FILE__);
	if ($file == $plugin_file) {
		$settings_link = '<a href="options-general.php?page='.$plugin_file.'">'.__('Settings', '<?php echo $localization; ?>').'</a>';
		array_unshift($links, $settings_link);
	}
	return $links;
}
add_filter('plugin_action_links', 'cftb_plugin_action_links', 10, 2);

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

function cftb_settings_form() {
	global $cftb_settings;
	print('
<div class="wrap">
	<h2>'.__('Tag Browser Settings', '').'</h2>
	<form id="cftb_settings_form" name="cftb_settings_form" action="'.get_bloginfo('wpurl').'/wp-admin/options-general.php" method="post">
		<input type="hidden" name="cf_action" value="cftb_update_settings" />
		<fieldset class="options form-table">
	');
	foreach ($cftb_settings as $key => $config) {
		echo cf_settings_field($key, $config);
	}
	print('
		</fieldset>
		<p class="submit">
			<input type="submit" name="submit" value="'.__('Save Settings', '').'" />
		</p>
	</form>
	<h2>'.__('Adding Manually', 'cf_tag_browser').'</h2>
	<p>'.__("If you don't auto-create a page, you can add the Tag Browser to your site using the following methods.", 'cf_tag_browser').'</p>
	<h3>'.__('Template Tag', 'cf_tag_browser').'</h3>
	<p>'.__('Add this to your template page:', 'cf_tag_browser').'</p>
	<p><code>&lt;?php if (function_exists("cftb_browser")) { cftb_browser(); } ?&gt;</code></p>
	<h3>'.__('Shortcode', 'cf_tag_browser').'</h3>
	<p>'.__('Add this to a post or page: [tag-browser]', 'cf_tag_browser').'</p>
</div>
	');
}

function cftb_save_settings() {
	if (!current_user_can('manage_options')) {
		return;
	}
	global $cftb_settings;
	foreach ($cftb_settings as $key => $option) {
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