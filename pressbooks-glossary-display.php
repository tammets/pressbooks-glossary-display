<?php
/*
Plugin Name: Pressbooks Glossary Display
Description: A custom plugin to display Pressbooks glossary items on any page within the same WordPress installation.
Version: 1.0
Author: Priit Tammets
*/

// Register a custom REST API endpoint to serve glossary terms
add_action('rest_api_init', function () {
    register_rest_route('custom/v1', '/glossary', [
        'methods' => 'GET',
        'callback' => 'get_glossary_terms',
        'permission_callback' => '__return_true', // Adjust permissions as needed
    ]);
});

function get_glossary_terms() {
    // Check if the current site is the Pressbooks site
    if (strpos(get_site_url(), '/example-book') === false) {
        return new WP_Error('invalid_site', 'This endpoint is only available on the Pressbooks site.', ['status' => 403]);
    }

    $glossary_terms = get_posts([
        'post_type' => 'glossary',
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC',
    ]);

    $data = [];

    foreach ($glossary_terms as $term) {
        $data[] = [
            'title' => $term->post_title,
            'content' => apply_filters('the_content', $term->post_content),
        ];
    }

    return $data;
}

// Add a menu item in the admin dashboard
add_action('admin_menu', 'pressbooks_glossary_add_admin_menu');

function pressbooks_glossary_add_admin_menu() {
    add_options_page(
        'Pressbooks Glossary Settings', // Page title
        'Glossary Settings',            // Menu title
        'manage_options',               // Capability required
        'pressbooks-glossary',          // Menu slug
        'pressbooks_glossary_settings_page' // Callback function to render the page
    );
}

// Render the settings page
function pressbooks_glossary_settings_page() {
    ?>
    <div class="wrap">
        <h1>Pressbooks Glossary Settings</h1>
        <form method="post" action="options.php">
            <?php
            // Output security fields for the registered setting
            settings_fields('pressbooks_glossary_settings');
            
            // Output setting sections and their fields
            do_settings_sections('pressbooks-glossary');
            
            // Submit button
            submit_button();
            ?>
        </form>
    </div>
    <?php
}

add_action('admin_init', 'pressbooks_glossary_register_settings');

function pressbooks_glossary_register_settings() {
    // Register a new setting for the plugin
    register_setting('pressbooks_glossary_settings', 'pressbooks_glossary_sites');

    // Add a new section for the settings
    add_settings_section(
        'pressbooks_glossary_main_section', // Section ID
        'Glossary Sources',                // Title
        'pressbooks_glossary_section_callback', // Callback function for description
        'pressbooks-glossary'              // Page slug
    );

    // Add a field for entering Pressbooks URLs
    add_settings_field(
        'pressbooks_glossary_sites',       // Field ID
        'Pressbooks Sites',                // Title
        'pressbooks_glossary_sites_callback', // Callback function for field rendering
        'pressbooks-glossary',             // Page slug
        'pressbooks_glossary_main_section' // Section ID
    );
}

// Section description callback
function pressbooks_glossary_section_callback() {
    echo '<p>Enter the URLs of the Pressbooks sites whose glossaries you want to use. Add one URL per line.</p>';
}

// Field rendering callback
function pressbooks_glossary_sites_callback() {
    // Get the current value from the database
    $urls = get_option('pressbooks_glossary_sites', '');
    ?>
    <textarea name="pressbooks_glossary_sites" rows="10" cols="50" class="large-text"><?php echo esc_textarea($urls); ?></textarea>
    <?php
}



function display_pressbooks_glossary() {
    // Get the list of Pressbooks URLs
    $urls = get_option('pressbooks_glossary_sites', '');
    $urls = array_filter(array_map('trim', explode("\n", $urls))); // Convert to array

    if (empty($urls)) {
        return 'No Pressbooks sites configured.';
    }

    $output = '<div class="pressbooks-glossary"><h3>Glossary</h3><ul>';

    foreach ($urls as $url) {
        $api_url = rtrim($url, '/') . '/wp-json/custom/v1/glossary';
        $response = wp_remote_get($api_url);

        if (is_wp_error($response)) {
            $output .= '<li>Unable to fetch glossary from ' . esc_html($url) . ': ' . $response->get_error_message() . '</li>';
            continue;
        }

        $body = wp_remote_retrieve_body($response);
        $glossary_terms = json_decode($body, true);

        if (!is_array($glossary_terms) || empty($glossary_terms)) {
            $output .= '<li>No glossary terms found for ' . esc_html($url) . '</li>';
            continue;
        }

        foreach ($glossary_terms as $term) {
            if (isset($term['title']) && isset($term['content'])) {
                $output .= '<li><strong>' . esc_html($term['title']) . '</strong>: ' . $term['content'] . '</li>';
            }
        }
    }

    $output .= '</ul></div>';

    return $output;
}

add_shortcode('pressbooks_glossary', 'display_pressbooks_glossary');

// Enqueue CSS for the glossary display
function pressbooks_glossary_enqueue_styles() {
    wp_enqueue_style('pressbooks-glossary-style', plugins_url('pressbooks-glossary-style.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'pressbooks_glossary_enqueue_styles');