<?php
/*
Plugin Name: Location Based Product
Description: A plugin to select a location and filter products based on the chosen location.
Version: 1.5
Author: Suhel Shaikh
*/

if (!defined('ABSPATH')) exit; // Exit if accessed directly

// Register the custom taxonomy for location
function location_selector_register_taxonomy() {
    $args = array(
        'labels' => array(
            'name' => 'Locations',
            'singular_name' => 'Location',
        ),
        'hierarchical' => true,
        'public' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_rest' => true,
        'rewrite' => array('slug' => 'location'),
    );
    register_taxonomy('location', 'product', $args);
}
add_action('init', 'location_selector_register_taxonomy');

function location_selector_enqueue_assets() {
    wp_enqueue_script('jquery');
    wp_enqueue_script('google-jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js', array(), null, true);
    wp_enqueue_style('location-selector-style', plugin_dir_url(__FILE__) . 'style.css');
    wp_enqueue_script('location-selector-popup', plugin_dir_url(__FILE__) . 'script.js', array('jquery'), null, true);

    wp_enqueue_script('google-maps-api', 'YOUR_API_KEY', array(), null, true); // Replace YOUR_API_KEY with your Google Maps API key

    wp_localize_script('location-selector-popup', 'location_selector_vars', array(
        'locationSelectorNonce' => wp_create_nonce('location_selector_nonce'),
        'ajaxUrl' => admin_url('admin-ajax.php'),
    ));
}
add_action('wp_enqueue_scripts', 'location_selector_enqueue_assets');

// Popup HTML content
function location_selector_popup_html() {
    ?>
    <div id="location-selector-popup" class="location-popup">
        <div class="popup-content">
            <h2>Select Your Location</h2>
            <?php
            $locations = get_terms(array(
                'taxonomy' => 'location',
                'orderby' => 'name',
                'order' => 'ASC',
                'hide_empty' => false,
            ));
            if (!empty($locations)) {
                echo '<div class="location-dropdown-container">';
                echo '<select id="location-selector-dropdown">';
                echo '<option value="">Select a location...</option>';
                foreach ($locations as $location) {
                    echo '<option value="' . $location->term_id . '">' . $location->name . '</option>';
                }
                echo '</select>';
                echo '</div>';
            } else {
                echo '<p>No locations available.</p>';
            }
            ?>
            <h4>OR</h4>
            <input class="form-control" type="text" id="current-address" >
            <br><button id="fetch-location" class="btn-two">Fetch Current Location</button>
            <button id="location-selector-submit" class="btn-one">Submit</button>
        </div>
    </div>
    <?php
}
add_action('wp_footer', 'location_selector_popup_html');

// AJAX handler to save location in a cookie
function location_selector_save_location() {
    if (isset($_POST['location_id']) && wp_verify_nonce($_POST['nonce'], 'location_selector_nonce')) {
        setcookie('selected_location', $_POST['location_id'], time() + 3600, '/');
        error_log('Location saved successfully: ' . $_POST['location_id']);
        wp_send_json_success();
    } else {
        error_log('Failed to save location. Invalid nonce or missing location ID.');
        wp_send_json_error();
    }
}
add_action('wp_ajax_location_selector_save', 'location_selector_save_location');
add_action('wp_ajax_nopriv_location_selector_save', 'location_selector_save_location');

// Modify product query based on selected location
function location_selector_filter_global_query($query) {

    if (!is_admin() && $query->is_main_query()) {
        if (current_user_can('administrator')) {
            return;
        }
        if (is_front_page() || is_home()) {
            return;
        }
        if (isset($_COOKIE['selected_location'])) {
            $location_id = intval($_COOKIE['selected_location']);

            if ($location_id) {
                
                $query->set('tax_query', array(
                    array(
                        'taxonomy' => 'location',
                        'field'    => 'id',
                        'terms'    => $location_id,
                        'operator' => 'IN',
                    ),
                ));
            }
        }
    }
}
add_action('pre_get_posts', 'location_selector_filter_global_query');

function location_selector_filter_woocommerce_query($query) {

    if (current_user_can('administrator')) {
        return;
    }
    if (is_front_page() || is_home()) {
        return;
    }
    if (isset($_COOKIE['selected_location'])) {
        $location_id = intval($_COOKIE['selected_location']);

        if ($location_id) {
            if (isset($query->query_vars['post_type']) && $query->query_vars['post_type'] === 'product') {
                $query->set('tax_query', array(
                    array(
                        'taxonomy' => 'location',
                        'field'    => 'id',
                        'terms'    => $location_id,
                        'operator' => 'IN',
                    ),
                ));
            }
        }
    }
}
add_action('pre_get_posts', 'location_selector_filter_woocommerce_query');


// Remove ADD TO CART Button and add warning if the product not assign to product.
function restrict_product_by_location() {
    if (is_product() && isset($_COOKIE['selected_location'])) {
        global $post;

        $selected_location = intval($_COOKIE['selected_location']);
        $product_id = $post->ID;

        $product_locations = wp_get_post_terms($product_id, 'location', array('fields' => 'ids'));

        if (empty($product_locations) || !in_array($selected_location, $product_locations)) {
           
            add_action('woocommerce_single_product_summary', function() {
                echo '<p class="location-unavailable-message" style="color: red; font-weight: bold;">This product is not available for your selected location.</p>';
            }, 20);

            add_action('woocommerce_single_product_summary', function() {
                remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30);
            }, 30);
            // Hide price and add to cart button and add css class accordingly.
            add_action('wp_head', function() {
                echo '<style>
                    .box-price-wrap, .message-pricing-wrap {
                        display: none !important;
                    }
                </style>';
            });
        }
    }
}
add_action('wp', 'restrict_product_by_location');

