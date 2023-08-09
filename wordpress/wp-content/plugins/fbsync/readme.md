
## FB Sync Plugin Documentation

**Plugin Name:** FB Sync  
**Version:** 1.0  
**Author:** Bryan Asa Kristian  
**Description:** Automate Facebook publishing from your WordPress dashboard.

### Activation and Deactivation Hooks

The plugin registers activation and deactivation hooks to perform specific actions when the plugin is activated or deactivated.

-   `register_activation_hook(__FILE__, 'fbsync_activate');`  
    This function is called when the plugin is activated. It creates a custom database table for storing scheduled posts and sets up options for API keys.
    
-   `register_deactivation_hook(__FILE__, 'fbsync_deactivate');`  
    This function is called when the plugin is deactivated. Currently, it does not have any defined actions.
    

### Database Table Creation

The plugin creates a custom database table to store information about scheduled posts.

-   A table named `'prefix_fbsync_posts'` (where `'prefix'` is the WordPress database table prefix) is created to store scheduled posts.
-   The table has columns for `id`, `post_id`, `platform`, `scheduled_time`, and `status`.

### Admin Menu Integration

The plugin adds an options page to the WordPress admin menu for configuring FB Sync settings.

-   `add_action('admin_menu', 'fbsync_add_menu');`  
    This function adds an options page titled "FB Sync Settings" to the admin menu.

### Settings Page

The settings page allows users to configure settings for scheduling posts.

-   The `fbsync_settings_page()` function generates the HTML for the settings page.
-   Users can input their Facebook API Key and an API Endpoint URL.
-   Users can select a post from the list of existing posts to be scheduled.
-   Users can choose the platform (currently only Facebook) to publish the scheduled post.
-   Users can set the scheduled time for the post using a datetime-local input.
-   The `submit_button('Schedule Post');` is used to submit the form for scheduling a post.

### Scheduled Posts Display

The plugin displays a list of scheduled posts on the settings page.

-   The `Scheduled Posts` section lists the ID, Post Title, Platform, Scheduled Time, and Status of each pending scheduled post.
-   Data is fetched from the database and displayed using a loop and appropriate formatting.

### Settings Initialization and Post Scheduling

-   The `fbsync_settings_init()` function handles the initialization of settings and scheduling of posts.
-   Users' input for the scheduled post is processed.
-   If a post is selected for scheduling, the data is sanitized and inserted into the custom database table.

### Publishing to Custom API

-   The `publish_to_custom_api()` function sends the scheduled post content to a custom API.
-   It uses cURL to make a POST request to the specified API Endpoint URL.
-   The response from the API is returned.

### Publishing Scheduled Posts

-   The `fbsync_publish_scheduled_posts()` function checks and publishes scheduled posts.
-   It fetches pending scheduled posts whose scheduled time has passed.
-   If the platform is Facebook, the post content is sent to the custom API.
-   The status of the post in the database is updated based on the API response.

### Cron Event Scheduling

-   If the cron event for publishing posts is not already scheduled, it schedules the event to run hourly.
-   `wp_schedule_event(time(), 'hourly', 'fbsync_publish_posts');`

The provided code creates a WordPress plugin named "FB Sync" that enables automatic publishing of posts to Facebook from the WordPress dashboard. It uses a custom database table to store scheduled posts and interacts with a custom API for publishing.