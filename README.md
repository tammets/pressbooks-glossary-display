# Pressbooks Glossary Display

A WordPress plugin to display Pressbooks glossary items on any page within the same multisite installation. This plugin retrieves glossary terms from a designated Pressbooks site and displays them on any page using a shortcode.

## Features

- Retrieves glossary items from a Pressbooks site in a multisite network.
- Includes a custom REST API endpoint (`/wp-json/custom/v1/glossary`) specifically for accessing glossary terms on the Pressbooks site.
- Provides a shortcode `[pressbooks_glossary]` to display glossary items on any WordPress page.

## Installation

1. **Download or Clone the Repository**:
   - Download the repository files or clone them into the `wp-content/plugins` directory of your WordPress multisite installation.

2. **Activate the Plugin**:
   - Go to **Plugins** in your WordPress network admin dashboard.
   - Network activate the **Pressbooks Glossary Display** plugin. This makes the plugin and the custom REST endpoint available on all sites within the multisite network.

## Usage

1. **Set Up the Glossary Terms in Pressbooks**:
   - Ensure that the glossary items are created on the designated Pressbooks site (e.g., `https://yoursite.eu/example-book`).

2. **Configure the Custom REST API Endpoint**:
   - The plugin automatically sets up a custom REST API endpoint `https://<your_pressbooks_site>/wp-json/custom/v1/glossary` on the Pressbooks site to retrieve glossary terms.
   - This endpoint is restricted to the Pressbooks site and will return a `403 Forbidden` error if accessed from any other site.

3. **Add the Glossary Shortcode on the Main Site**:
   - On the main WordPress site (e.g., `https://yoursite.eu/dictionary`), add the shortcode `[pressbooks_glossary]` to any page where you want to display the glossary terms.
   - This shortcode will retrieve and display glossary items from the Pressbooks site via the custom REST API endpoint.

## Example Code Snippets

- **Custom REST Endpoint** (for reference in `pressbooks-glossary-display.php`):

  ```php
  add_action('rest_api_init', function () {
      register_rest_route('custom/v1', '/glossary', [
          'methods' => 'GET',
          'callback' => 'get_glossary_terms',
          'permission_callback' => '__return_true', // Adjust permissions as needed
      ]);
  });

  function get_glossary_terms() {
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