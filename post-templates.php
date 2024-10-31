<?php
/*
Plugin Name: Post Templates
Plugin URI: http://dev.wp-plugins.org/wiki/PostTemplates
Description: Change the "post template" on individual posts or entire categories.
Author: Jeff Minard
Version: 1.6
Author URI: http://thecodepro.com/
*/ 

// Hey!
// Yeah, you!
// ...
// Get outta here. Nothing to see, move it along!
// ...
// ...
// No, really, all the config work is done in your admin section. Check "Presentation" >> "Post Templates"




function pt_check_template($template) {
// Check to see if the current post needs a special template
// and switch it in if that is the case.
// See if the category assigns a template, then see if the post has one.
// (post takes priority.)
	global $wp_query, $pt_template_category_cache;
	
	if( !$pt_template_category_cache )
		$pt_template_category_cache = get_option('pt_template_category_cache');

	$post_obj = $wp_query->get_queried_object();
	$id       = $post_obj->ID; 
	$cats     = get_the_category($id);
	
	foreach($cats as $cat) {
		$cid = $cat->cat_ID;
		
		foreach($pt_template_category_cache as $k => $v) {
			if($cid == $k)
				$pt_template_hit[$v['template']] = $v['priority'];
		}
	}
		
	if( is_array($pt_template_hit) ) {
		arsort($pt_template_hit);
		$pt_template = array_shift(array_keys($pt_template_hit));
	}
	
	if( get_post_meta($id, '_pt_template', true) ) 
		$pt_template = get_post_meta($id, '_pt_template', true);
	
	if( !empty($pt_template) ) {
		if ( file_exists(TEMPLATEPATH . "/$pt_template") ) 
			$template = TEMPLATEPATH . "/$pt_template";
	}
	
	return $template;
}
add_filter('single_template', 'pt_check_template');





function pt_get_post_templates() {
// Almost a copy&paste of get_page_templates(), this function finds
// the files marked with a "Post Template: xxx" comment.
	global $post_templates_cache;
	
	if( $post_templates_cache ) 
		return $post_templates_cache;
	
	$themes = get_themes();
	$theme = get_current_theme();
	$templates = $themes[$theme]['Template Files'];
	$post_templates = array();

	foreach ($templates as $template) {
		$template_data = implode('', file(ABSPATH . $template));
		preg_match("|Post Template:(.*)|i", $template_data, $name);

		$name = $name[1];

		if (! empty($name)) {
			$post_templates[trim($name)] = basename($template);
		}
	}
	
	$post_templates_cache = $post_templates;

	return $post_templates;
}

function pt_admin_footer($content) {
// Hook for the admin section to insert the template controls
// weee! (there is an API hook, but I like the JS method for positioning better.)
	global $id, $post_status;

	if(!isset($id)) $id = $_REQUEST['post'];

	// Are we on the right page? post page, editing, and NOT a "page"
	if(stristr($_SERVER['SCRIPT_NAME'], 'post.php') 
/*	&& $_REQUEST['action'] == 'edit' 
*/	&& $post_status != 'static') {
	
		if ( get_post_meta($id, '_pt_template', true) ) 
			$selected_pt_template = get_post_meta($id, '_pt_template', true);
		
		?>
		<div id="pt">
			<fieldset class="options">
				<legend>Post Template</legend>
				<p>This post should use the 
				
				<select name="pt_template">
					<?php
					$templates = pt_get_post_templates();
					$def = " selected='selected'";
					foreach (array_keys($templates) as $template) {
						$selected = ($selected_pt_template == $templates[$template]) ? " selected='selected'" : '';
						$options .= "\n\t<option value='" . $templates[$template] . "' $selected>$template</option>";
						if($selected != '') 
							$def = '';
					}
					?>
					<option value='NULL' <?php echo $def; ?>>Default</option>
					<?php echo $options; ?>
				</select>
				
				template.</p>
					
			</fieldset>
		</div>
		<script language="JavaScript" type="text/javascript"><!--
		var placement = document.getElementById("titlediv");
		var substitution = document.getElementById("pt");
		var mozilla = document.getElementById&&!document.all;
		if(mozilla)
			 placement.parentNode.appendChild(substitution);
		else placement.parentElement.appendChild(substitution);
		//--></script>
		<?php


	}
}
add_filter('admin_footer', 'pt_admin_footer');


function pt_update($id) {
// Handles the updating of post data.
	global $wpdb, $id;

	if(!isset($id)) $id = $_REQUEST['post_ID'];
	
	if( $id && $_POST['pt_template'] ){

		$qry = "DELETE FROM {$wpdb->postmeta} WHERE Post_ID = $id AND meta_key = '_pt_template' ";
		$wpdb->query($qry);
		
		if( $_POST['pt_template'] != 'NULL' ) {
			$qry = "INSERT INTO {$wpdb->postmeta} (Post_ID, meta_key, meta_value) VALUES ($id, '_pt_template', '$_POST[pt_template]') ";
			$wpdb->query($qry);
		}
	
	}
	
}
add_filter('edit_post', 'pt_update');
add_filter('publish_post', 'pt_update');

function pt_cat_rows($parent = 0, $level = 0, $categories = 0) {
	global $wpdb, $class, $user_level;
	if (!$categories)
		$categories = $wpdb->get_results("SELECT * FROM $wpdb->categories ORDER BY cat_name");

	if ($categories) {
		foreach ($categories as $category) {
			if ($category->category_parent == $parent) {
				$category->cat_name = wp_specialchars($category->cat_name);
				$pad = str_repeat('&#8212; ', $level);
				
				$class = ('alternate' == $class) ? '' : 'alternate';
				echo "
				<tr class=\"$class\">
					<th scope=\"row\">$category->cat_ID</th>
					<td>$pad $category->cat_name</td>
					<td>$category->category_description</td>
					<td>" . pt_get_template_form($category->cat_ID) . " <input value=\"Update\" type=\"submit\"></td>
				</tr>
				";
				pt_cat_rows($category->cat_ID, $level + 1, $categories);
			}
		}
	} else {
		return false;
	}
}

function pt_get_template_form($category_ID) {
	global $pt_template_category_cache;
	
	if( !$pt_template_category_cache )
		$pt_template_category_cache = get_option('pt_template_category_cache');
	
		
	$templates = pt_get_post_templates();
	
	$def = " selected='selected'";
	foreach (array_keys($templates) as $template) {
		$selected = ($pt_template_category_cache[$category_ID]['template'] == $templates[$template]) ? $def : '';
		$options .= "<option value='" . $category_ID . '/' . $templates[$template] . "' $selected> $template </option>";
		if($selected != '') 
			$def = '';
	}
	
	$def2 = " selected='selected'";
	for($i=9;$i>=0;$i--) {
		$selected2 = ($pt_template_category_cache[$category_ID]['priority'] == $i) ? $def2 : '';
		$options2 .= "<option value='$i' $selected2> $i </option>";
		if($selected2 != '') 
			$def2 = '';
	}
	
	$output = "
	<select name=\"ptct[]\">
		<option value=''" . $def . "> Default </option>
		" . $options . "
	</select>
	<select name=\"ptct_priority_" . $category_ID . "\">
		<option value='10'" . $def2 . "> 10 </option>
		" . $options2 . "
	</select>
	";
	
	return $output;

	
}

function pt_page() {
	global $wpdb, $pt_template_category_cache;
?>


<div class="wrap">

	<?php
	
	if( $_POST['ptct'] ) {
	
		$pt_cats = array();
		$c=0;
		foreach($_POST['ptct'] as $ptc) {
			if( stristr($ptc, '/') ) {
				$part = explode('/', $ptc);
				$pt_cats[$part[0]] = array('template'=>$part[1], 'priority'=>$_POST["ptct_priority_".$part[0]]);
			}
		}
		update_option('pt_template_category_cache', $pt_cats);
		$pt_template_category_cache = $pt_cats;
		
		echo '<div class="updated"><p>Post Templates updated.</p></div>';
		
	}
	
	?>

	<h2>Post Templates Per Category</h2>
	<p>If you want post in a category to use a certain template, you can define that here.</p>

	<form method="post">
	<table  cellpadding="3" cellspacing="3">
	<tr>
		<th scope="col"><?php _e('ID') ?></th>
        <th scope="col"><?php _e('Name') ?></th>
        <th scope="col"><?php _e('Description') ?></th>
        <th scope="col"><?php _e('Post Template') ?></th>
	</tr>
	<?php pt_cat_rows(); ?>
	</table>
	</form>

</div>

<?php

}

function pt_add_page() {
	 add_submenu_page('themes.php','Post Templates', 'Post Templates', 8, __FILE__, 'pt_page');
}

add_action('admin_menu', 'pt_add_page');

?>