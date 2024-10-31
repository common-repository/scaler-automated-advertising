<?php

function sc_register_menu_page() {

    add_menu_page('Scaler', 'Scaler', 'edit_pages', 'scaler',
        'sc_dashboard', plugin_dir_url(__FILE__) . "/assets/favicon-16x16.png", 6);

}

function sc_dashboard() {

//    $html = "<iframe id='scaler' src='http://getscaler.com/my/?integration=woocommerce&integration_token=" .
//        sc_get_token() .
//        "' style='border: none; height: 1700px; width: 100%;'></iframe>";

//    $html = "<div>
//        <form action='http://getscaler.com/wordpress/index.php' method='post'>
//            <input type='hidden' name='integration_token' value='".sc_get_token()."'>
//            <input type='hidden' name='lang' value='".sc_get_language_code()."'>
//            <input type='submit' formtarget='_blank' value='Dashboard'>
//        </form>
//    </div>";
//    $html = file_get_contents('https://getscaler.com/wordpress/my.php?integration_token='.sc_get_token().'&lang='.sc_get_language_code());
    $url = 'https://getscaler.com/wordpress/my.php?integration_token='.sc_get_token().'&lang='.sc_get_language_code();
    $html = "<div id='scaler-dashboard-container' data-url=$url></div>";
    echo $html;
}

function sc_get_language_code() {
    $wp_lang = get_locale();
    return substr($wp_lang, 0, 2);
}

function sc_add_ui_script() {
    wp_enqueue_script( 'scaler_dashboard_script', "http://getscaler.com/wordpress/ui.js", array('jquery'));
}

function sc_add_app_style() {
    wp_enqueue_style( 'scaler_dashboard_style', plugin_dir_url(__FILE__)."/assets/app.css");
}

function sc_add_stripe_script() {
    wp_enqueue_script('scaler_stripe', 'https://checkout.stripe.com/checkout.js', array('jquery'));
}

function sc_get_product_categories( $id ) {

    $term = get_term_by( 'id', $id, 'product_cat', 'ARRAY_A' );
    $link = get_term_link( $term['slug'], 'product_cat' );

    $result = ['name'=> $term['name'], 'url' => $link, 'parent'=> $term['parent']];
    return $result;

}

function sc_get_all_categories( $id, &$category ) {

    $res = sc_get_product_categories( $id );
    $category[] = $res;
    if ( $res['parent'] != 0 ) {
        sc_get_all_categories( $res['parent'], $category );
    }
}

function sc_autocomplete_order() {
    wp_add_inline_script( 'jsjs', "Scaler.api.registerConversion();" );
}

function sc_get_token() {
    return md5(get_site_url());
}

function plugin_activation() {

    if (version_compare($GLOBALS['wp_version'], SCALER__MINIMUM_WP_VERSION, '<')) {
        load_plugin_textdomain('scaler');

        $message = '<strong>' . sprintf(esc_html__('Scaler %s requires WordPress %s or higher.', 'scaler'),
                SCALER_VERSION,
                SCALER__MINIMUM_WP_VERSION) . '</strong> ' . sprintf(__('Please <a href="%1$s">upgrade WordPress</a> to a current version'), 'https://codex.wordpress.org/Upgrading_WordPress');

        Scaler::bail_on_activation($message);
    }
    if ( ! in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
        load_plugin_textdomain('scaler');

        $message = 'Scaler requires WooCommerce';

        bail_on_activation($message);
    }

    $lang     = sc_get_language_code();
    $wc       = new WC_Countries();
    $host     = get_site_url();
    $email    = get_option('admin_email');
    $currency = get_woocommerce_currency();
    $country  = $wc->get_base_country();
    $token    = get_token();
    $answer   = file_get_contents("https://getscaler.com/wordpress/register.php?host=$host&email=$email&currency=$currency&country=$country&token=$token&lang=$lang");
    if ($answer !== "ok") {
        $message = "Can't create account";
        bail_on_activation($message);
    }
}

function bail_on_activation( $message, $deactivate = true ) {

    ?>
    <!doctype html>
    <html>
    <head>
        <meta charset="<?php bloginfo('charset'); ?>">
        <style>
            * {
                text-align: center;
                margin: 0;
                padding: 0;
                font-family: "Lucida Grande", Verdana, Arial, "Bitstream Vera Sans", sans-serif;
            }

            p {
                margin-top: 1em;
                font-size: 18px;
            }
        </style>
    <body>
    <p><?php echo esc_html($message); ?></p>
    </body>
    </html>
    <?php
    if ($deactivate) {
        $plugins = get_option('active_plugins');
        $scaler = plugin_basename(SCALER__PLUGIN_DIR . 'scaler.php');
        $update  = false;
        foreach ($plugins as $i => $plugin) {
            if ($plugin === $scaler) {
                $plugins[$i] = false;
                $update      = true;
            }
        }
        if ($update) {
            update_option('active_plugins', array_filter($plugins));
        }
    }
    exit;
}

function sc_add_jsjs_script() {
    wp_enqueue_script( 'jsjs', "//getscaler.com/js.js?integration=woocommerce&token=" . sc_get_token() );
}

function sc_add_inline_script( $script ) {
    wp_add_inline_script( 'jsjs', $script );
}

function sc_init() {

    global $wp_query;

    add_action( 'wp_enqueue_scripts', 'sc_add_jsjs_script' );
    do_action( 'wp_enqueue_scripts');

    $script = "";

    if (is_product()) {

        $wc_product = new WC_Product(get_the_ID());
        $product['name']            = $wc_product->get_name();
        $product['price']           = $wc_product->get_price();
        $product['image']           = wp_get_attachment_image_src($wc_product->get_image_id())[0];
        $product['url']             = $wc_product->get_permalink();
        sc_get_all_categories(min($wc_product->get_category_ids()),$product['categories']);
        $product['categories'] = array_reverse($product['categories']);

        $json_product = json_encode($product);

        $script .= "Scaler.wooProduct = $json_product; Scaler.api.registerPageView(Scaler.wooProduct);";
    } elseif (is_product_category()) {
        $cat  = $wp_query->get_queried_object();
        $category = [];
        sc_get_all_categories($cat->term_id, $category);
        $category = array_reverse($category);
        $json_category = json_encode($category);
        $script .= "var sc_category = $json_category;  Scaler.api.registerCategory(sc_category);";

    }

    $cart = WC()->cart;
    $cart_items = $cart->get_cart_contents();
    $i = 0;
    $products = [];
    foreach ($cart_items as $item){
        $product = new WC_Product($item['product_id']);
        $products[$i]['url'] = $product->get_permalink();
        $products[$i]['qty'] = $item['quantity'];
        $products[$i]['sum'] = $item['line_total'];
        $i++;
    }
    $json_products = json_encode($products);
    $json_cart_total = json_encode($cart->get_cart_contents_total());
    $script .= "var sc_products = $json_products; var sc_cart_total = $json_cart_total;";
    $script .= "if ( sc_cart_total != +localStorage.getItem( 'scCartSum' ) ) { ";
    $script .= "if ( sc_cart_total != 0 ) {";
    $script .= "Scaler.api.registerCartProducts(sc_products, sc_cart_total);";
    $script .= "} localStorage.setItem('scCartSum',sc_cart_total); }";

    add_action( 'wp_enqueue_scripts', 'sc_add_inline_script', 10, 1);
    do_action( 'wp_enqueue_scripts', $script);
}
