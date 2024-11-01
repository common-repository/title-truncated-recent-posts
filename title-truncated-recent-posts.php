<?php
/*
Plugin Name: Title Truncated Recent Posts
Plugin URI: http://robot9.me/wp_widget_recent_posts_title_truncated/
Description: Recent Posts Widget with Truncated Title
Version: 1.0
Author: robot9
Author URI: http://robot9.me
License: GPLv2 or later
*/

/*  Copyright 2014  robot9  (email : hogan@robot9.me)
 
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.
 
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
 
    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

add_action( 'widgets_init', 'switch_recent_posts_widget' );

function switch_recent_posts_widget() {
	unregister_widget( 'WP_Widget_Recent_Posts' );
	register_widget( 'WP_Widget_Recent_Posts_Truncated' );
}

/**
* 字符串截取
*
* @param string $str 原始字符串
* @param int	$len 截取长度（中文/全角符号默认为 2 个单位，英文/数字为 1。
*					例如：长度 20 表示 10 个中文或全角字符或 20 个英文或数字）
* @param string   $prefix 前缀字符串
* @param string   $suffix 后缀字符串
* @return string
*/
function g_substr($str, $len = 20, $prefix = '', $suffix = '...') {
	$i = 0;
	$l = 0;
	$c = 0;
	$a = array();
	while ($l < $len) {
		$t = substr($str, $i, 1);
		if (ord($t) >= 224) {
			$c = 3;
			$t = substr($str, $i, $c);
			$l += 2;
		} elseif (ord($t) >= 192) {
			$c = 2;
			$t = substr($str, $i, $c);
			$l += 2;
		} else {
			$c = 1;
			$l++;
		}
		// $t = substr($str, $i, $c);
		$i += $c;
		if ($l > $len) break;
		$a[] = $t;
	}
	$re = implode('', $a);
	if (substr($str, $i, 1) !== false) {
		array_pop($a);
		($c == 1) and array_pop($a);
		$re = implode('', $a);
		$re .= $suffix;
	}
	return $prefix.$re;
}

class WP_Widget_Recent_Posts_Truncated extends WP_Widget {

	function __construct() {
		$widget_ops = array('classname' => 'widget_recent_entries', 'description' => __( "The most recent posts on your site (title truncated)") );
		parent::__construct('recent-posts', __('Recent Posts'), $widget_ops);
		$this->alt_option_name = 'widget_recent_entries';

		add_action( 'save_post', array(&$this, 'flush_widget_cache') );
		add_action( 'deleted_post', array(&$this, 'flush_widget_cache') );
		add_action( 'switch_theme', array(&$this, 'flush_widget_cache') );
	}

	function widget($args, $instance) {
		$cache = wp_cache_get('widget_recent_posts', 'widget');

		if ( !is_array($cache) )
			$cache = array();

		if ( isset($cache[$args['widget_id']]) ) {
			echo $cache[$args['widget_id']];
			return;
		}

		ob_start();
		extract($args);

		$title = apply_filters('widget_title', empty($instance['title']) ? __('Recent Posts') : $instance['title'], $instance, $this->id_base);
		if ( ! $number = absint( $instance['number'] ) )
			$number = 10;
			
		if ( ! $title_len = absint( $instance['title_len'] ) )
		$title_len = 20;
		
		$prefix = empty($instance['prefix']) ? __('') : $instance['prefix'];
		$suffix = empty($instance['suffix']) ? __('...') : $instance['suffix'];

		$r = new WP_Query(array('posts_per_page' => $number, 'no_found_rows' => true, 'post_status' => 'publish', 'ignore_sticky_posts' => true));
		if ($r->have_posts()) :
?>
		<?php echo $before_widget; global $post ?>
		<?php if ( $title ) echo $before_title . $title . $after_title; ?>
		<ul>
		<?php  while ($r->have_posts()) : $r->the_post(); ?>
		<li><a href="<?php the_permalink() ?>" title="<?php echo esc_attr(get_the_title() ? get_the_title() : get_the_ID()); ?>">
			<?php 

			if( get_the_title() ) {
					echo g_substr(the_title('','',FALSE), $title_len, $prefix, $suffix);
			}
			else {
				the_ID();
			}
			?>
		</a></li>
		<?php endwhile; ?>
		</ul>
		<?php echo $after_widget; ?>
<?php
		// Reset the global $the_post as this query will have stomped on it
		wp_reset_postdata();

		endif;

		$cache[$args['widget_id']] = ob_get_flush();
		wp_cache_set('widget_recent_posts', $cache, 'widget');
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['number'] = (int) $new_instance['number'];
		$instance['title_len'] = (int) $new_instance['title_len'];
		$instance['prefix'] = (string) $new_instance['prefix'];
		$instance['suffix'] = (string) $new_instance['suffix'];
		$this->flush_widget_cache();

		$alloptions = wp_cache_get( 'alloptions', 'options' );
		if ( isset($alloptions['widget_recent_entries']) )
			delete_option('widget_recent_entries');

		return $instance;
	}

	function flush_widget_cache() {
		wp_cache_delete('widget_recent_posts', 'widget');
	}

	function form( $instance ) {
		$title = isset($instance['title']) ? esc_attr($instance['title']) : '';
		$number = isset($instance['number']) ? absint($instance['number']) : 5;
		$title_len = isset($instance['title_len']) ? absint($instance['title_len']) : 20;
		$prefix = isset($instance['prefix']) ? esc_attr($instance['prefix']) : '';
		$suffix = isset($instance['suffix']) ? esc_attr($instance['suffix']) : '...';
?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></p>

		<p><label for="<?php echo $this->get_field_id('number'); ?>"><?php _e('Number of posts to show:'); ?></label>
		<input id="<?php echo $this->get_field_id('number'); ?>" name="<?php echo $this->get_field_name('number'); ?>" type="text" value="<?php echo $number; ?>" size="3" /></p>
		
		<p><label for="<?php echo $this->get_field_id('title_len'); ?>"><?php _e('Length of truncated title:'); ?></label>
		<input id="<?php echo $this->get_field_id('title_len'); ?>" name="<?php echo $this->get_field_name('title_len'); ?>" type="text" value="<?php echo $title_len; ?>" size="3" /></p>
		
		<p><label for="<?php echo $this->get_field_id('prefix'); ?>"><?php _e('Prefix of all titles:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('prefix'); ?>" name="<?php echo $this->get_field_name('prefix'); ?>" type="text" value="<?php echo $prefix; ?>" /></p>
		
		<p><label for="<?php echo $this->get_field_id('suffix'); ?>"><?php _e('suffix of truncated title:'); ?></label>
		<input class="widefat" id="<?php echo $this->get_field_id('suffix'); ?>" name="<?php echo $this->get_field_name('suffix'); ?>" type="text" value="<?php echo $suffix; ?>" /></p>
<?php
	}
}
?>