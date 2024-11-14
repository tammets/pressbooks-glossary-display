<?php
/*
Plugin Name: Pressbooks Glossary Display
Description: A custom plugin to display Pressbooks glossary items on any page within the same WordPress installation.
Version: 1.0
Author: Your Name
*/

// Function to retrieve and display Pressbooks glossary items
function display_pressbooks_glossary() {
    $glossary_post_type = 'glossary';
    $glossary_terms = get_posts([
        'post_type' => $glossary_post_type,
        'posts_per_page' => -1,
        'orderby' => 'title',
        'order' => 'ASC'
    ]);

    if (!$glossary_terms) {
        return 'No glossary terms found.';
    }

    $output = '<div class="pressbooks-glossary"><h3>Glossary</h3><ul>';
    foreach ($glossary_terms as $term) {
        $output .= '<li><strong>' . esc_html($term->post_title) . '</strong>: ' . esc_html($term->post_content) . '</li>';
    }
    $output .= '</ul></div>';

    return $output;
}

// Register the shortcode
add_shortcode('pressbooks_glossary', 'display_pressbooks_glossary');

// Enqueue CSS for the glossary display
function pressbooks_glossary_enqueue_styles() {
    wp_enqueue_style('pressbooks-glossary-style', plugins_url('pressbooks-glossary-style.css', __FILE__));
}
add_action('wp_enqueue_scripts', 'pressbooks_glossary_enqueue_styles');