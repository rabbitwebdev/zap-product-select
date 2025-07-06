<?php
/**
 * Plugin Name: Woo Product Popup Selector
 * Description: A simple plugin that creates a Vue-based popup allowing users to select a WooCommerce product from a dropdown.
 * Version: 1.0
 * Author: Pip the Plugin Wizard
 */

if (!defined('ABSPATH')) exit;

class WooProductPopupSelector {

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_shortcode('product_popup_selector', [$this, 'render_popup']);
        add_action('rest_api_init', [$this, 'register_rest_routes']);
    }

    public function enqueue_scripts() {
        wp_enqueue_style('woo-popup-style', plugin_dir_url(__FILE__) . 'style.css');
        wp_enqueue_script('vue', 'https://cdn.jsdelivr.net/npm/vue@2', [], null, true);
        wp_enqueue_script('axios', 'https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js', [], null, true);
    }

    public function render_popup() {
        ob_start(); ?>
       <div id="product-app" class="product-popup">
            <div class="product-container">
                <div class="messages">
                    <div v-for="(message, index) in messages" :key="index" :class="message.type" v-html="message.text"></div>
                </div>
                <p class="fs-3 mb-2">Find a Product</p>
                <div v-if="productDetails" class="product-info">
      <h2>{{ productDetails.name }}</h2>
      <div v-html="productDetails.price_html"></div>
      <a :href="productDetails.permalink" target="_blank" class="view-button">View Product</a>
    </div>

    <select v-model="selectedProductId" @change="fetchProductPricing(selectedProductId)">
      <option disabled value="">Select a product</option>
      <option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }}</option>
    </select>

   
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  new Vue({
    el: '#product-app',
    data: {
      products: [],
      selectedProductId: '',
      productDetails: null,
      messages: [],
    },
    mounted() {
      this.fetchProducts();
    },
    methods: {
      fetchProducts() {
        axios.get('<?php echo esc_url(rest_url('wp/v2/woo-products')); ?>')
          .then(response => {
            this.products = response.data;
          })
          .catch(() => {
            this.messages.push({ type: 'error', text: 'Error loading products.' });
          });
      },
      fetchProductPricing(productId) {
        axios.get('<?php echo esc_url(rest_url('wp/v2/woo-products/')); ?>' + productId)
          .then(response => {
            this.productDetails = response.data;
          })
          .catch(() => {
            this.productDetails = null;
            this.messages = [{ type: 'error', text: 'Failed to load product details.' }];
          });
      }
    }
  });
});
</script>
        <?php
        return ob_get_clean();
    }

    public function register_rest_routes() {
        register_rest_route('wp/v2', '/woo-products', [
            'methods' => 'GET',
            'callback' => [$this, 'get_products'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('wp/v2', '/woo-products/(?P<id>\d+)', [
            'methods' => 'GET',
            'callback' => [$this, 'get_product_details'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function get_products() {
        $args = [
            'status' => 'publish',
            'limit' => 50,
        ];
        $products = wc_get_products($args);
        $result = [];

        foreach ($products as $product) {
            $result[] = [
                'id' => $product->get_id(),
                'name' => $product->get_name(),
            ];
        }

        return $result;
    }

    public function get_product_details($data) {
        $product = wc_get_product($data['id']);

        if (!$product) {
            return new WP_Error('not_found', 'Product not found', ['status' => 404]);
        }

        return [
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'price_html' => $product->get_price_html(),
            'permalink' => get_permalink($product->get_id()),
        ];
    }
}

new WooProductPopupSelector();
