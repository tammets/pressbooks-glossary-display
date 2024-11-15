<?php
/*
Plugin Name: Pressbooks Glossary Display
Description: A custom plugin to display Pressbooks glossary items on any page within the same WordPress installation and highlight terms in the content.
Version: 1.1
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
    register_setting('pressbooks_glossary_settings', 'pressbooks_glossary_sites');

    add_settings_section(
        'pressbooks_glossary_main_section',
        'Glossary Sources',
        'pressbooks_glossary_section_callback',
        'pressbooks-glossary'
    );

    add_settings_field(
        'pressbooks_glossary_sites',
        'Pressbooks Sites',
        'pressbooks_glossary_sites_callback',
        'pressbooks-glossary',
        'pressbooks_glossary_main_section'
    );
}

function pressbooks_glossary_section_callback() {
    echo '<p>Enter the URLs of the Pressbooks sites whose glossaries you want to use. Add one URL per line.</p>';
}

function pressbooks_glossary_sites_callback() {
    $urls = get_option('pressbooks_glossary_sites', '');
    ?>
    <textarea name="pressbooks_glossary_sites" rows="10" cols="50" class="large-text"><?php echo esc_textarea($urls); ?></textarea>
    <?php
}

// Highlight glossary terms in the content
function highlight_glossary_terms($content) {
    // Fetch glossary terms from the saved configuration
    $urls = get_option('pressbooks_glossary_sites', '');
    $urls = array_filter(array_map('trim', explode("\n", $urls))); // Convert to array

    if (empty($urls)) {
        return $content; // No glossary sources configured
    }

    $glossary_terms = [];
    foreach ($urls as $url) {
        $api_url = rtrim($url, '/') . '/wp-json/custom/v1/glossary';
        $response = wp_remote_get($api_url);

        if (!is_wp_error($response)) {
            $terms = json_decode(wp_remote_retrieve_body($response), true);
            if (is_array($terms)) {
                $glossary_terms = array_merge($glossary_terms, $terms);
            }
        }
    }

    if (empty($glossary_terms)) {
        return $content; // No terms fetched
    }

    // Build a regex pattern for all glossary terms
    $terms = array_map(function ($term) {
        return preg_quote($term['title'], '/');
    }, $glossary_terms);

    $pattern = '/\b(' . implode('|', $terms) . ')\b/i';

    // Replace terms with highlighted HTML
    $content = preg_replace_callback($pattern, function ($matches) use ($glossary_terms) {
        $term = strtolower($matches[1]);

        foreach ($glossary_terms as $entry) {
            if (strtolower($entry['title']) === $term) {
                $tooltip = esc_attr(strip_tags($entry['content']));
                return '<span class="glossary-term" title="' . $tooltip . '">' . esc_html($entry['title']) . '</span>';
            }
        }

        return $matches[1]; // Fallback: return the original term
    }, $content);

    return $content;
}

add_filter('the_content', 'highlight_glossary_terms');

// Enqueue CSS for the glossary display
function pressbooks_glossary_enqueue_styles() {
    wp_enqueue_style('pressbooks-glossary-style', plugins_url('pressbooks-glossary-style.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'pressbooks_glossary_enqueue_styles');