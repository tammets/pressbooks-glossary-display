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

// Function to retrieve and display Pressbooks glossary items
function display_pressbooks_glossary() {
    $api_url = 'https://owlplus.eu/example-book/wp-json/custom/v1/glossary';

    // Fetch glossary terms from the custom API endpoint
    $response = wp_remote_get($api_url);

    // Check for errors in the response
    if (is_wp_error($response)) {
        return 'Unable to fetch glossary terms. Error: ' . $response->get_error_message();
    }

    $body = wp_remote_retrieve_body($response);
    $glossary_terms = json_decode($body, true);

    if (!is_array($glossary_terms) || empty($glossary_terms)) {
        return 'No glossary terms found or failed to parse JSON response. Raw response: <pre>' . esc_html($body) . '</pre>';
    }

    $output = '<div class="pressbooks-glossary"><h3>Glossary</h3><ul>';

    foreach ($glossary_terms as $term) {
        $output .= '<li><strong>' . esc_html($term['title']) . '</strong>: ' . $term['content'] . '</li>';
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