<?php
/*
Plugin Name: FB Sync
Version: 1.0
Author: Bryan Asa Kristian
Description: Automate Facebook publishing from your WordPress dashboard.
*/

register_activation_hook(__FILE__, 'fbsync_activate');

register_deactivation_hook(__FILE__, 'fbsync_deactivate');

function fbsync_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'fbsync_posts';

    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        post_id bigint(20) NOT NULL,
        platform varchar(50) NOT NULL,
        scheduled_time datetime NOT NULL,
        status varchar(20) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    add_option('facebook_api_key', '');
    add_option('twitter_api_key', '');
}

function fbsync_deactivate() {
}

add_action('admin_menu', 'fbsync_add_menu');
function fbsync_add_menu() {
    add_options_page(
        'FB Sync Settings', 
        'FB Sync', 
        'manage_options', 
        'FB Sync', 
        'fbsync_settings_page'
    );
}

function fbsync_settings_page() {
    ?>
    <div class="wrap">
        <h2>FB Sync Settings</h2>
        <form method="post" action="">
            <?php settings_fields('fbsync_settings_group'); ?>
            <?php do_settings_sections('fbsync-pro'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Facebook API Key:</th>
                    <td><input type="text" name="facebook_api_key" value="<?php echo esc_attr(get_option('facebook_api_key')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">API Endpoint URL:</th>
                    <td><input type="text" name="api_endpoint_url" value="<?php echo esc_attr(get_option('api_endpoint_url')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Select Post:</th>
                    <td>
                        <select name="selected_post_id">
                            <option value="">Select a post</option>
                            <?php
                            $args = array(
                                'post_type' => 'post',
                                'posts_per_page' => -1,
                                'order' => 'DESC',
                            );
                            $posts = get_posts($args);
                            foreach ($posts as $post) {
                                echo '<option value="' . esc_attr($post->ID) . '">' . esc_html($post->post_title) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Scheduled Post Platform:</th>
                    <td>
                        <select name="post_platform">
                            <option value="facebook">Facebook</option>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Scheduled Time:</th>
                    <td><input type="datetime-local" name="post_scheduled_time"></td>
                </tr>
            </table>
            <?php submit_button('Schedule Post'); ?>
        </form>
    </div>
    <div class="wrap">

        <h2>Scheduled Posts</h2>
        <table class="widefat">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Post Title</th>
                    <th>Platform</th>
                    <th>Scheduled Time</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                global $wpdb;
                $table_name = $wpdb->prefix . 'fbsync_posts';
                $scheduled_posts = $wpdb->get_results("SELECT * FROM $table_name WHERE status = 'pending' ORDER BY id ASC");

                foreach ($scheduled_posts as $post) :
                ?>
                    <tr>
                        <td><?php echo $post->id; ?></td>
                        <td><?php echo get_the_title($post->post_id); ?></td>
                        <td><?php echo $post->platform; ?></td>
                        <td><?php echo date('Y-m-d H:i:s', strtotime($post->scheduled_time)); ?></td>
                        <td><?php echo $post->status; ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

add_action('admin_init', 'fbsync_settings_init');
function fbsync_settings_init() {
    register_setting('fbsync_settings_group', 'facebook_api_key');
    register_setting('fbsync_settings_group', 'twitter_api_key');
    register_setting('fbsync_settings_group', 'api_endpoint_url');

    if (isset($_POST['selected_post_id'])) {
        $selected_post_id = intval($_POST['selected_post_id']);
        $post = get_post($selected_post_id);

        if ($post) {
            $content = $post->post_content;
            $platform = sanitize_text_field($_POST['post_platform']);
            $scheduled_time = sanitize_text_field($_POST['post_scheduled_time']);

            global $wpdb;
            $table_name = $wpdb->prefix . 'fbsync_posts';

            $wpdb->insert(
                $table_name,
                array(
                    'post_id' => $selected_post_id,
                    'platform' => $platform,
                    'scheduled_time' => $scheduled_time,
                    'status' => 'pending',
                ),
                array('%d', '%s', '%s', '%s')
            );
        }
    }
}

function publish_to_custom_api($post_content, $api_key, $api_url) {
    $data = array(
        'message' => $post_content,
        'access_token' => $api_key,
    );

    $options = array(
        CURLOPT_URL => $api_url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_RETURNTRANSFER => true,
    );

    $curl = curl_init();
    curl_setopt_array($curl, $options);
    $response = curl_exec($curl);
    curl_close($curl);

    return $response;
}

function fbsync_publish_scheduled_posts() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'fbsync_posts';

    $current_time = current_time('timestamp');

    $posts = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table_name WHERE scheduled_time <= %d AND status = 'pending'", $current_time)
    );

    foreach ($posts as $post) {
        if ($post->platform === 'facebook') {
            $api_key = get_option('facebook_api_key');
            $api_url = get_option('api_endpoint_url');
            $response = publish_to_custom_api($post->content, $api_key, $api_url);
            
            
            if ($response) {
                $wpdb->update(
                    $table_name,
                    array('status' => 'published'),
                    array('id' => $post->id),
                    array('%s'),
                    array('%d')
                );
            } else {
                $wpdb->update(
                    $table_name,
                    array('status' => 'failed'),
                    array('id' => $post->id),
                    array('%s'),
                    array('%d')
                );
            }
        }
        
    }
}


if (!wp_next_scheduled('fbsync_publish_posts')) {
    wp_schedule_event(time(), 'hourly', 'fbsync_publish_posts');
}