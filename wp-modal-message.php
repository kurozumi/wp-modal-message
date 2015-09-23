<?php
/*
  Plugin Name: WP Modal Message
  Version: 0.1-alpha
  Description: modal window message
  Author: kurozumi
  Author URI: http://a-zumi.net
  Plugin URI: http://a-zumi.net
  Text Domain: wp-modal-message
  Domain Path: /languages
 */

$wp_modal_message = new WP_Modal_Message();
$wp_modal_message->register();

class WP_Modal_Message
{
	const PLUGIN_NAME = "モーダルメッセージ";
	const ADMIN_TITLE = "モーダルメッセージ設定";

	public function register()
	{
		add_action('plugins_loaded', array($this, 'plugins_loaded'));
	}

	public function plugins_loaded()
	{
		register_activation_hook(__FILE__, array($this, 'register_activation_hook'));
		register_activation_hook(__FILE__, array($this, 'register_deactivation_hook'));
		
		add_action('admin_menu', array($this, 'add_menu_page'));
		add_action('admin_init', array($this, 'admin_init'));
		add_action('admin_notices', array($this, 'admin_notices'));

		//if (wp_is_mobile())
		//	return;

		if(isset($_COOKIE['wp-modal-message']) && $_COOKIE['wp-modal-message'] == 'stop')
			return;

		add_action('wp_enqueue_scripts', array($this, 'wp_enqueue_scripts'));
		add_action('wp_footer', array($this, 'wp_footer'));
	}
	
	public function register_activation_hook()
	{
		update_option('wp-modal-message', array(
			'display' => 2,
			'expires' => 1,
			'title'   => 'お知らせ',
			'body'    => 'お知らせです。'
		));
	}
	
	public function register_deactivation_hook()
	{
		delete_option('wp-modal-message');
	}

	public function add_menu_page()
	{
		add_menu_page(
			__(self::PLUGIN_NAME), 
			__(self::PLUGIN_NAME), 
			'manage_options', 
			__FILE__, 
			array($this, 'print_options_page')
		);
	}

	public function get_option()
	{
		$default_option = array(
			'display' => 2,
			'expires' => 1,
			'title'   => 'お知らせ。',
			'body'    => 'お知らせです。'
		);

		if ($option = get_option('wp-modal-message'))
		{
			return wp_parse_args($option, $default_option);
		} else
		{
			return $default_option;
		}
	}

	public function print_options_page()
	{
		$options = $this->get_option();
		?>
		<div class="wrap">
			<h1><?php echo esc_html(__(self::ADMIN_TITLE)); ?></h1>

			<form method="post" action="">
				<?php wp_nonce_field('nonce_key', 'wp-modal-message'); ?>
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="display"><?php _e('表示の有無'); ?></label>
						</th>
						<td>
							<select name="display">
								<?php foreach (array(1 => '表示', 2 => '非表示') as $key => $name) : ?>
									<?php $selected = ($key == $options['display']) ? " selected" : ""; ?>
									<option value="<?php echo esc_attr($key); ?>"<?php echo $selected; ?>><?php echo esc_html($name); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="expires"><?php _e('次回表示までの期間'); ?></label>
						</th>
						<td>
							<input name="expires" type="number" step="1" min="1" value="<?php echo esc_attr($options['expires']); ?>" class="small-text" /> <?php _e( '日' ); ?>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="title"><?php _e('Title'); ?></label>
						</th>
						<td>
							<input name="title" type="text" class="regular-text" value="<?php echo esc_attr($options['title']); ?>" />
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="body"><?php _e('本文'); ?></label>
						</th>
						<td>
							<textarea name="body" class="large-text code" rows="10"><?php echo $this->allowed_html($options['body']); ?></textarea>
						</td>
					</tr>
				</table>
				<p><input type="submit" value="<?php echo esc_attr(__('Save')); ?>" class="button button-primary button-large" /></p>
			</form>

		</div>
		<?php
	}

	public function admin_init()
	{
		if (isset($_POST['wp-modal-message']) && $_POST['wp-modal-message'])
		{
			if (check_admin_referer('nonce_key', 'wp-modal-message'))
			{
				$e = new WP_Error();

				$options = array();

				if (isset($_POST['display']) && $_POST['display'])
				{
					$options['display'] = trim($_POST['display']);
					
					if($_POST['display'] == 2 && isset($_COOKIE['wp-modal-message']))
						setcookie("wp-modal-message", '', time() - 1800, "/");
				}
				
				if (isset($_POST['expires']) && $_POST['expires'])
				{
					$options['expires'] = trim($_POST['expires']);
				}

				if (isset($_POST['title']) && $_POST['title'])
				{
					$options['title'] = trim($_POST['title']);
				} else
				{
					$options['title'] = "";
					$e->add('error', __('タイトルを入力して下さい。'));
				}

				if (isset($_POST['body']) && $_POST['body'])
				{
					$options['body'] = trim($_POST['body']);
				} else
				{
					$options['body'] = "";
					$e->add('error', __('本文を入力して下さい。'));
				}

				update_option('wp-modal-message', $options);

				set_transient('wp-modal-message', $e->get_error_messages(), 10);

				wp_safe_redirect(menu_page_url(__FILE__, false));
			}
		}
	}

	public function admin_notices()
	{
		if ($messages = get_transient('wp-modal-message')):
		?>
			<div class="updated">
				<ul>
					<?php foreach ($messages as $message): ?>
						<li><?php echo esc_html($message); ?>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php
		endif;
	}

	public function wp_enqueue_scripts()
	{
		wp_enqueue_style('backbone.modal', '//cdnjs.cloudflare.com/ajax/libs/backbone.modal/1.1.5/backbone.modal-min.css');
		wp_enqueue_style('backbone.modal-theme', '//cdnjs.cloudflare.com/ajax/libs/backbone.modal/1.1.5/backbone.modal.theme-min.css');

		wp_enqueue_script('underscore');
		wp_enqueue_script('backbone');
		wp_enqueue_script('backbone.modal', '//cdnjs.cloudflare.com/ajax/libs/backbone.modal/1.1.5/backbone.modal-min.js', array('jquery'), false, true);
		wp_enqueue_script('js-cookie',      '//cdnjs.cloudflare.com/ajax/libs/js-cookie/2.0.3/js.cookie.js');
	}

	public function wp_footer()
	{
		$options = $this->get_option();

		if ($options['display'] == 2)
			return;
		?>
		<div class="app"></div>

		<script type="text/template" id="modal-template">
		    <div class="bbm-modal__topbar">
				<h3 class="bbm-modal__title"><?php echo esc_html($options['title']);?></h3>
		    </div>
		    <div class="bbm-modal__section">
				<?php echo $this->allowed_html($options['body']);?>
			</div>
			<div class="bbm-modal__bottombar">
				<a href="#" class="bbm-button">close</a>
		    </div>
		</script>

		<script>
			jQuery(function ($) {
				var Modal = Backbone.Modal.extend({
					template: _.template($('#modal-template').html()),
					cancelEl: '.bbm-button',
					onDestroy: function(){
						Cookies.set('wp-modal-message', 'stop', { expires: 1 });
					}
				});
				$(document).ready(function () {
					var modalView = new Modal();
					$('.app').html(modalView.render().el);
				});
			});
		</script>

		<?php
	}
	
	public function allowed_html($text)
	{
		$allowed_html = array_merge(wp_kses_allowed_html('post'), array(
			'iframe' => array(
				'width'           => array(),
				'height'          => array(),
				'src'             => array(),
				'frameborder'     => array(),
				'allowfullscreen' => array()
			)
		));
		return wp_kses($text, $allowed_html);
	}

}
