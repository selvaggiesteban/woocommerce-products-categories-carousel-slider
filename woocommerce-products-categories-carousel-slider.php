<?php
/*
Plugin Name: WooCommerce Products & Categories Carousel Slider
Plugin URI: https://github.com/selvaggiesteban/woocommerce-products-categories-carousel-slider
Description: Crea carruseles para productos y categorías de WooCommerce con múltiples opciones de configuración.
Version: 1.1.0
Author: Esteban Selvaggi
Author URI: https://selvaggiesteban.dev
License: GPL2
Text Domain: wpccs-slider
Domain Path: /languages
WC requires at least: 4.0.0
WC tested up to: 8.5.0
*/

// Prevenir el acceso directo al archivo
if (!defined('ABSPATH')) {
    exit;
}

// Clase principal del plugin para gestionar carruseles de WooCommerce
class WooCommerce_PC_Carousel_Slider {
    
    // Constructor de la clase
    // Inicializa los hooks y verifica la compatibilidad con WooCommerce
    public function __construct() {
        // Verificar si WooCommerce está activo
        if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
            return;
        }

        // Cargar traducciones
        add_action('plugins_loaded', array($this, 'load_plugin_textdomain'));

        // Hooks de inicialización
        add_action('init', array($this, 'initialize_plugin'));
        add_action('admin_menu', array($this, 'create_admin_menu'));
        add_action('wp_enqueue_scripts', array($this, 'load_frontend_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'load_admin_scripts'));
        add_action('admin_post_save_carousel', array($this, 'save_carousel'));
        add_action('admin_post_delete_carousel', array($this, 'delete_carousel'));
        
        // Registrar shortcode para usar carruseles
        add_shortcode('wpccs_carousel', array($this, 'generate_carousel_shortcode'));
    }

    // Método para cargar el dominio de texto para traducciones
    // Permite internacionalizar el plugin
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'wpccs-slider',
            false,
            dirname(plugin_basename(__FILE__)) . '/languages/'
        );
    }

    // Método para cargar scripts y estilos en el frontend
    // Incluye Slick Carousel y estilos personalizados
    public function load_frontend_scripts() {
        // Cargar Slick Carousel desde CDN
        wp_enqueue_style('slick-carousel', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css');
        wp_enqueue_style('slick-theme', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick-theme.css');
        wp_enqueue_script('slick-carousel', 'https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.min.js', array('jquery'), null, true);

        // Estilos personalizados para el carrusel
        $custom_css = "
            /* Estilos para los slides */
            .wpccs-carousel.slick-initialized .slick-slide {
                display: flex;
                flex-wrap: wrap;
                margin: 10px 10px;
            }

            /* Ocultar completamente texto y botones de navegación */
            .wpccs-carousel .slick-prev,
            .wpccs-carousel .slick-next {
                font-size: 0 !important;
                line-height: 0 !important;
                padding: 0 !important;
                border: none !important;
                background: transparent !important;
                cursor: pointer !important;
                color: transparent !important;
                outline: none !important;
                width: 25px !important;
                height: 25px !important;
            }

            /* Mostrar solo las flechas */
            .wpccs-carousel .slick-prev:before,
            .wpccs-carousel .slick-next:before {
                font-family: 'slick';
                font-size: 20px;
                line-height: 1;
                opacity: .75;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }

            /* Ajustar hover de las flechas */
            .wpccs-carousel .slick-prev:hover:before,
            .wpccs-carousel .slick-next:hover:before {
                opacity: 1;
            }

            /* Asegurar que no se muestre texto en hover o focus */
            .wpccs-carousel .slick-prev:hover,
            .wpccs-carousel .slick-next:hover,
            .wpccs-carousel .slick-prev:focus,
            .wpccs-carousel .slick-next:focus {
                background: transparent !important;
                color: transparent !important;
                outline: none !important;
            }
        ";
        wp_add_inline_style('slick-carousel', $custom_css);
    }

    // Método para cargar scripts y estilos en el panel de administración
    public function load_admin_scripts($hook) {
        if (strpos($hook, 'wpccs-carousel') !== false) {
            wp_enqueue_style('wpccs-admin', plugin_dir_url(__FILE__) . 'css/wpccs-admin.css');
            wp_enqueue_script('wpccs-admin', plugin_dir_url(__FILE__) . 'js/wpccs-admin.js', array('jquery'), '1.0', true);
        }
    }// Método para inicializar el plugin
    // Registra el tipo de post personalizado para los carruseles
    public function initialize_plugin() {
        register_post_type('wpccs_carousel', array(
            'labels' => array(
                'name' => __('Carruseles para WooCommerce', 'wpccs-slider'),
                'singular_name' => __('Carrusel', 'wpccs-slider'),
                'add_new' => __('Añadir nuevo', 'wpccs-slider'),
                'add_new_item' => __('Añadir nuevo carrusel', 'wpccs-slider'),
                'edit_item' => __('Editar carrusel', 'wpccs-slider'),
                'new_item' => __('Nuevo carrusel', 'wpccs-slider'),
                'view_item' => __('Ver carrusel', 'wpccs-slider'),
                'search_items' => __('Buscar carruseles', 'wpccs-slider'),
                'not_found' => __('No se encontraron carruseles', 'wpccs-slider'),
                'not_found_in_trash' => __('No se encontraron carruseles en la papelera', 'wpccs-slider')
            ),
            'public' => false,
            'show_ui' => true,
            'capability_type' => 'post',
            'hierarchical' => false,
            'rewrite' => array('slug' => 'wpccs-carousel'),
            'supports' => array('title'),
            'show_in_menu' => false
        ));
    }

    // Método para crear el menú en el panel de administración
    // Añade página principal y subpáginas para gestionar carruseles
    public function create_admin_menu() {
        // Menú principal
        add_menu_page(
            __('Carruseles para WooCommerce', 'wpccs-slider'),
            __('Carruseles para WooCommerce', 'wpccs-slider'),
            'manage_options',
            'wpccs-carousels',
            array($this, 'admin_carousels_page'),
            'dashicons-images-alt2',
            20
        );
        
        // Submenú para ver todos los carruseles
        add_submenu_page(
            'wpccs-carousels',
            __('Todos los carruseles', 'wpccs-slider'),
            __('Todos los carruseles', 'wpccs-slider'),
            'manage_options',
            'wpccs-carousels',
            array($this, 'admin_carousels_page')
        );
        
        // Submenú para añadir nuevo carrusel
        add_submenu_page(
            'wpccs-carousels',
            __('Añadir nuevo carrusel', 'wpccs-slider'),
            __('Añadir nuevo', 'wpccs-slider'),
            'manage_options',
            'wpccs-new-carousel',
            array($this, 'new_carousel_page')
        );
    }

    // Método para mostrar la página principal de administración de carruseles
    // Lista todos los carruseles creados con opciones de edición y eliminación
    public function admin_carousels_page() {
        // Verificar permisos de usuario
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos suficientes para acceder a esta página.', 'wpccs-slider'));
        }

        // Obtener todos los carruseles
        $carousels = get_posts(array(
            'post_type' => 'wpccs_carousel',
            'numberposts' => -1
        ));

        ?>
        <div class="wrap">
            <h1><?php echo __('WooCommerce Products & Categories Carousel Slider', 'wpccs-slider'); ?></h1>
            <a href="<?php echo admin_url('admin.php?page=wpccs-new-carousel'); ?>" class="page-title-action">
                <?php echo __('Añadir nuevo', 'wpccs-slider'); ?>
            </a>
            
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo __('Título', 'wpccs-slider'); ?></th>
                        <th><?php echo __('Tipo', 'wpccs-slider'); ?></th>
                        <th><?php echo __('Shortcode', 'wpccs-slider'); ?></th>
                        <th><?php echo __('Acciones', 'wpccs-slider'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    if (!empty($carousels)) {
                        foreach ($carousels as $carousel) {
                            $config = get_post_meta($carousel->ID, 'wpccs_config', true);
                            $type = isset($config['carousel_type']) ? $config['carousel_type'] : 'products';
                            $type_label = $type === 'products' ? __('Productos', 'wpccs-slider') : __('Categorías', 'wpccs-slider');
                            ?>
                            <tr>
                                <td><?php echo esc_html($carousel->post_title); ?></td>
                                <td><?php echo esc_html($type_label); ?></td>
                                <td><code>[wpccs_carousel id="<?php echo $carousel->ID; ?>"]</code></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=wpccs-new-carousel&action=edit&id=' . $carousel->ID); ?>">
                                        <?php echo __('Editar', 'wpccs-slider'); ?>
                                    </a> | 
                                    <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=delete_carousel&id=' . $carousel->ID), 'delete_carousel_' . $carousel->ID); ?>" 
                                       onclick="return confirm('<?php echo esc_js(__('¿Estás seguro de eliminar este carrusel?', 'wpccs-slider')); ?>');">
                                        <?php echo __('Eliminar', 'wpccs-slider'); ?>
                                    </a>
                                </td>
                            </tr>
                            <?php
                        }
                    } else {
                        ?>
                        <tr>
                            <td colspan="4"><?php echo __('No hay carruseles creados.', 'wpccs-slider'); ?></td>
                        </tr>
                        <?php
                    }
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }// Método para mostrar la página de creación/edición de carrusel
    // Proporciona un formulario completo para configurar carruseles de productos y categorías
    public function new_carousel_page() {
        // Verificar permisos de usuario
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos suficientes para acceder a esta página.', 'wpccs-slider'));
        }

        // Obtener ID del carrusel si se está editando
        $carousel_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
        $config = $carousel_id ? get_post_meta($carousel_id, 'wpccs_config', true) : array();
        $carousel_type = isset($config['carousel_type']) ? $config['carousel_type'] : 'products';

        ?>
        <div class="wrap">
            <h1><?php echo $carousel_id ? __('Editar carrusel', 'wpccs-slider') : __('Nuevo carrusel', 'wpccs-slider'); ?></h1>
            
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <?php wp_nonce_field('wpccs_save_carousel'); ?>
                <input type="hidden" name="action" value="save_carousel">
                <input type="hidden" name="carousel_id" value="<?php echo $carousel_id; ?>">

                <!-- Configuración básica -->
                <table class="form-table">
                    <tr>
                        <th><label><?php echo __('Título del carrusel', 'wpccs-slider'); ?></label></th>
                        <td>
                            <input type="text" 
                                   name="title" 
                                   value="<?php echo $carousel_id ? get_the_title($carousel_id) : ''; ?>" 
                                   class="regular-text" 
                                   required>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php echo __('Tipo de carrusel', 'wpccs-slider'); ?></label></th>
                        <td>
                            <select name="carousel_type" id="carousel_type">
                                <option value="products" <?php selected($carousel_type, 'products'); ?>>
                                    <?php echo __('Productos', 'wpccs-slider'); ?>
                                </option>
                                <option value="categories" <?php selected($carousel_type, 'categories'); ?>>
                                    <?php echo __('Categorías', 'wpccs-slider'); ?>
                                </option>
                            </select>
                        </td>
                    </tr>
                </table>

                <!-- Configuraciones específicas de productos -->
                <div id="products_settings" style="display: <?php echo $carousel_type === 'products' ? 'block' : 'none'; ?>">
                    <?php $this->render_products_settings($config); ?>
                </div>

                <!-- Configuraciones específicas de categorías -->
                <div id="categories_settings" style="display: <?php echo $carousel_type === 'categories' ? 'block' : 'none'; ?>">
                    <?php $this->render_categories_settings($config); ?>
                </div>

                <!-- Configuración responsiva -->
                <h3><?php echo __('Configuración responsiva', 'wpccs-slider'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><label><?php echo __('Total de elementos a mostrar', 'wpccs-slider'); ?></label></th>
                        <td>
                            <input type="number" 
                                   name="total_items" 
                                   value="<?php echo isset($config['total_items']) ? $config['total_items'] : 12; ?>" 
                                   min="1" 
                                   max="50">
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php echo __('Elementos por pantalla', 'wpccs-slider'); ?></label></th>
                        <td>
                            <div class="responsive-settings">
                                <label>
                                    <?php echo __('Escritorio (>1366px)', 'wpccs-slider'); ?>
                                    <input type="number" 
                                           name="items_desktop" 
                                           value="<?php echo isset($config['items_desktop']) ? $config['items_desktop'] : 4; ?>" 
                                           min="1" 
                                           max="6">
                                </label>
                                <br>
                                <label>
                                    <?php echo __('Portátiles (≤1366px)', 'wpccs-slider'); ?>
                                    <input type="number" 
                                           name="items_laptop" 
                                           value="<?php echo isset($config['items_laptop']) ? $config['items_laptop'] : 3; ?>" 
                                           min="1" 
                                           max="5">
                                </label>
                                <br>
                                <label>
                                    <?php echo __('Tablets (≤1024px)', 'wpccs-slider'); ?>
                                    <input type="number" 
                                           name="items_tablet" 
                                           value="<?php echo isset($config['items_tablet']) ? $config['items_tablet'] : 2; ?>" 
                                           min="1" 
                                           max="4">
                                </label>
                                <br>
                                <label>
                                    <?php echo __('Móviles (≤767px)', 'wpccs-slider'); ?>
                                    <input type="number" 
                                           name="items_mobile" 
                                           value="<?php echo isset($config['items_mobile']) ? $config['items_mobile'] : 1; ?>" 
                                           min="1" 
                                           max="2">
                                </label>
                            </div>
                        </td>
                    </tr>
                </table>

                <!-- Opciones del carrusel -->
                <h3><?php echo __('Opciones del carrusel', 'wpccs-slider'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th><label><?php echo __('Autoplay', 'wpccs-slider'); ?></label></th>
                        <td>
                            <input type="checkbox" 
                                   name="autoplay" 
                                   value="1" 
                                   <?php checked(isset($config['autoplay']) ? $config['autoplay'] : false); ?>>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php echo __('Velocidad de transición (ms)', 'wpccs-slider'); ?></label></th>
                        <td>
                            <input type="number" 
                                   name="transition_speed" 
                                   value="<?php echo isset($config['transition_speed']) ? $config['transition_speed'] : 300; ?>" 
                                   min="100" 
                                   max="3000">
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php echo __('Flechas de navegación', 'wpccs-slider'); ?></label></th>
                        <td>
                            <input type="checkbox" 
                                   name="show_arrows" 
                                   value="1" 
                                   <?php checked(isset($config['show_arrows']) ? $config['show_arrows'] : true); ?>>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php echo __('Paginación', 'wpccs-slider'); ?></label></th>
                        <td>
                            <input type="checkbox" 
                                   name="show_dots" 
                                   value="1" 
                                   <?php checked(isset($config['show_dots']) ? $config['show_dots'] : true); ?>>
                        </td>
                    </tr>
                    <tr>
                        <th><label><?php echo __('Loop infinito', 'wpccs-slider'); ?></label></th>
                        <td>
                            <input type="checkbox" 
                                   name="infinite_loop" 
                                   value="1" 
                                   <?php checked(isset($config['infinite_loop']) ? $config['infinite_loop'] : true); ?>>
                        </td>
                    </tr>
                </table>

                <?php submit_button($carousel_id ? __('Actualizar carrusel', 'wpccs-slider') : __('Crear carrusel', 'wpccs-slider')); ?>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#carousel_type').on('change', function() {
                if ($(this).val() === 'products') {
                    $('#products_settings').show();
                    $('#categories_settings').hide();
                } else {
                    $('#products_settings').hide();
                    $('#categories_settings').show();
                }
            });
        });
        </script>
        <?php
    }// Método privado para renderizar configuraciones específicas de productos
    // Muestra opciones para filtrar y ordenar productos en el carrusel
    private function render_products_settings($config) {
        ?>
        <h3><?php echo __('Configuración de productos', 'wpccs-slider'); ?></h3>
        <table class="form-table">
            <!-- Selección por categorías -->
            <tr>
                <th><label><?php echo __('Seleccionar categorías', 'wpccs-slider'); ?></label></th>
                <td>
                    <?php
                    $categories = get_terms(array(
                        'taxonomy' => 'product_cat',
                        'hide_empty' => false
                    ));
                    if (!empty($categories)) {
                        foreach ($categories as $category) {
                            $selected = isset($config['product_categories']) && 
                                      in_array($category->term_id, $config['product_categories']);
                            ?>
                            <label style="display: block; margin-bottom: 5px;">
                                <input type="checkbox" 
                                       name="product_categories[]" 
                                       value="<?php echo $category->term_id; ?>" 
                                       <?php checked($selected, true); ?>>
                                <?php echo esc_html($category->name); ?>
                            </label>
                            <?php
                        }
                    }
                    ?>
                    <p class="description">
                        <?php echo __('Selecciona las categorías de productos a mostrar. Si no seleccionas ninguna, se mostrarán productos de todas las categorías.', 'wpccs-slider'); ?>
                    </p>
                </td>
            </tr>

            <!-- Selección por etiquetas -->
            <tr>
                <th><label><?php echo __('Seleccionar etiquetas', 'wpccs-slider'); ?></label></th>
                <td>
                    <?php
                    $tags = get_terms(array(
                        'taxonomy' => 'product_tag',
                        'hide_empty' => false
                    ));
                    if (!empty($tags)) {
                        foreach ($tags as $tag) {
                            $selected = isset($config['product_tags']) && 
                                      in_array($tag->term_id, $config['product_tags']);
                            ?>
                            <label style="display: block; margin-bottom: 5px;">
                                <input type="checkbox" 
                                       name="product_tags[]" 
                                       value="<?php echo $tag->term_id; ?>" 
                                       <?php checked($selected, true); ?>>
                                <?php echo esc_html($tag->name); ?>
                            </label>
                            <?php
                        }
                    }
                    ?>
                    <p class="description">
                        <?php echo __('Selecciona las etiquetas de productos a mostrar. Si no seleccionas ninguna, se ignorará el filtro por etiquetas.', 'wpccs-slider'); ?>
                    </p>
                </td>
            </tr>

            <!-- Ordenamiento de productos -->
            <tr>
                <th><label><?php echo __('Ordenar productos por', 'wpccs-slider'); ?></label></th>
                <td>
                    <select name="products_orderby">
                        <option value="date" <?php selected(isset($config['products_orderby']) ? $config['products_orderby'] : 'date', 'date'); ?>>
                            <?php echo __('Fecha', 'wpccs-slider'); ?>
                        </option>
                        <option value="title" <?php selected(isset($config['products_orderby']) ? $config['products_orderby'] : '', 'title'); ?>>
                            <?php echo __('Título', 'wpccs-slider'); ?>
                        </option>
                        <option value="price" <?php selected(isset($config['products_orderby']) ? $config['products_orderby'] : '', 'price'); ?>>
                            <?php echo __('Precio', 'wpccs-slider'); ?>
                        </option>
                        <option value="popularity" <?php selected(isset($config['products_orderby']) ? $config['products_orderby'] : '', 'popularity'); ?>>
                            <?php echo __('Popularidad', 'wpccs-slider'); ?>
                        </option>
                        <option value="rand" <?php selected(isset($config['products_orderby']) ? $config['products_orderby'] : '', 'rand'); ?>>
                            <?php echo __('Aleatorio', 'wpccs-slider'); ?>
                        </option>
                    </select>
                </td>
            </tr>

            <tr>
                <th><label><?php echo __('Orden', 'wpccs-slider'); ?></label></th>
                <td>
                    <select name="products_order">
                        <option value="DESC" <?php selected(isset($config['products_order']) ? $config['products_order'] : 'DESC', 'DESC'); ?>>
                            <?php echo __('Descendente', 'wpccs-slider'); ?>
                        </option>
                        <option value="ASC" <?php selected(isset($config['products_order']) ? $config['products_order'] : '', 'ASC'); ?>>
                            <?php echo __('Ascendente', 'wpccs-slider'); ?>
                        </option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    // Método privado para renderizar configuraciones específicas de categorías
    // Muestra opciones para filtrar y ordenar categorías de productos
    private function render_categories_settings($config) {
        ?>
        <h3><?php echo __('Configuración de categorías', 'wpccs-slider'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label><?php echo __('Seleccionar categorías', 'wpccs-slider'); ?></label></th>
                <td>
                    <?php
                    $categories = get_terms(array(
                        'taxonomy' => 'product_cat',
                        'hide_empty' => false,
                        'parent' => 0  // Solo categorías de primer nivel
                    ));
                    
                    if (!empty($categories)) {
                        foreach ($categories as $category) {
                            $this->render_category_checkbox($category, $config);
                        }
                    }
                    ?>
                    <p class="description">
                        <?php echo __('Selecciona las categorías específicas a mostrar en el carrusel. Si no seleccionas ninguna, se mostrarán todas las categorías de primer nivel.', 'wpccs-slider'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th><label><?php echo __('Mostrar solo categorías con productos', 'wpccs-slider'); ?></label></th>
                <td>
                    <input type="checkbox" 
                           name="show_only_with_products" 
                           value="1" 
                           <?php checked(isset($config['show_only_with_products']) ? $config['show_only_with_products'] : false); ?>>
                    <p class="description">
                        <?php echo __('Si está marcado, solo se mostrarán las categorías que contengan productos.', 'wpccs-slider'); ?>
                    </p>
                </td>
            </tr>

            <tr>
                <th><label><?php echo __('Ordenar categorías por', 'wpccs-slider'); ?></label></th>
                <td>
                    <select name="categories_orderby">
                        <option value="name" <?php selected(isset($config['categories_orderby']) ? $config['categories_orderby'] : 'name', 'name'); ?>>
                            <?php echo __('Nombre', 'wpccs-slider'); ?>
                        </option>
                        <option value="id" <?php selected(isset($config['categories_orderby']) ? $config['categories_orderby'] : '', 'id'); ?>>
                            <?php echo __('ID', 'wpccs-slider'); ?>
                        </option>
                        <option value="count" <?php selected(isset($config['categories_orderby']) ? $config['categories_orderby'] : '', 'count'); ?>>
                            <?php echo __('Cantidad de productos', 'wpccs-slider'); ?>
                        </option>
                    </select>
                </td>
            </tr>

            <tr>
                <th><label><?php echo __('Orden', 'wpccs-slider'); ?></label></th>
                <td>
                    <select name="categories_order">
                        <option value="ASC" <?php selected(isset($config['categories_order']) ? $config['categories_order'] : 'ASC', 'ASC'); ?>>
                            <?php echo __('Ascendente', 'wpccs-slider'); ?>
                        </option>
                        <option value="DESC" <?php selected(isset($config['categories_order']) ? $config['categories_order'] : '', 'DESC'); ?>>
                            <?php echo __('Descendente', 'wpccs-slider'); ?>
                        </option>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    // Método privado para renderizar categorías con sus subcategorías
    // Permite selección jerárquica de categorías
    private function render_category_checkbox($category, $config, $depth = 0) {
        $selected = isset($config['selected_categories']) && 
                    in_array($category->term_id, $config['selected_categories']);
        ?>
        <label style="display: block; margin-bottom: 5px; padding-left: <?php echo $depth * 20; ?>px;">
            <input type="checkbox" 
                   name="selected_categories[]" 
                   value="<?php echo $category->term_id; ?>" 
                   <?php checked($selected, true); ?>>
            <?php echo esc_html($category->name); ?>
        </label>
        <?php

        // Buscar y mostrar subcategorías recursivamente
        $subcategories = get_terms(array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
            'parent' => $category->term_id
        ));

        if (!empty($subcategories)) {
            foreach ($subcategories as $subcategory) {
                $this->render_category_checkbox($subcategory, $config, $depth + 1);
            }
        }
    }// Método para guardar la configuración del carrusel
    public function save_carousel() {
        // Verificar nonce y permisos
        check_admin_referer('wpccs_save_carousel');
        if (!current_user_can('manage_options')) {
            wp_die(__('No tienes permisos suficientes para realizar esta acción.', 'wpccs-slider'));
        }

        // Obtener datos básicos
        $carousel_id = isset($_POST['carousel_id']) ? intval($_POST['carousel_id']) : 0;
        $title = sanitize_text_field($_POST['title']);
        $carousel_type = sanitize_text_field($_POST['carousel_type']);

        // Preparar configuración común
        $config = array(
            'carousel_type' => $carousel_type,
            'total_items' => intval($_POST['total_items']),
            'items_desktop' => intval($_POST['items_desktop']),
            'items_laptop' => intval($_POST['items_laptop']),
            'items_tablet' => intval($_POST['items_tablet']),
            'items_mobile' => intval($_POST['items_mobile']),
            'autoplay' => isset($_POST['autoplay']) ? 1 : 0,
            'transition_speed' => intval($_POST['transition_speed']),
            'show_arrows' => isset($_POST['show_arrows']) ? 1 : 0,
            'show_dots' => isset($_POST['show_dots']) ? 1 : 0,
            'infinite_loop' => isset($_POST['infinite_loop']) ? 1 : 0
        );

        // Configuración específica para productos
        if ($carousel_type === 'products') {
            $config['product_categories'] = isset($_POST['product_categories']) ? array_map('intval', $_POST['product_categories']) : array();
            $config['product_tags'] = isset($_POST['product_tags']) ? array_map('intval', $_POST['product_tags']) : array();
            $config['products_orderby'] = sanitize_text_field($_POST['products_orderby']);
            $config['products_order'] = sanitize_text_field($_POST['products_order']);
        } 
        // Configuración específica para categorías
        else {
            $config['selected_categories'] = isset($_POST['selected_categories']) ? array_map('intval', $_POST['selected_categories']) : array();
            $config['show_only_with_products'] = isset($_POST['show_only_with_products']) ? 1 : 0;
            $config['categories_orderby'] = sanitize_text_field($_POST['categories_orderby']);
            $config['categories_order'] = sanitize_text_field($_POST['categories_order']);
        }

        // Crear o actualizar el post
        $post_data = array(
            'post_title' => $title,
            'post_type' => 'wpccs_carousel',
            'post_status' => 'publish'
        );

        if ($carousel_id) {
            $post_data['ID'] = $carousel_id;
            wp_update_post($post_data);
        } else {
            $carousel_id = wp_insert_post($post_data);
        }

        // Guardar configuración
        if ($carousel_id) {
            update_post_meta($carousel_id, 'wpccs_config', $config);
        }

        wp_redirect(admin_url('admin.php?page=wpccs-carousels&message=1'));
        exit;
    }

    // Método para generar el shortcode
    public function generate_carousel_shortcode($atts) {
        $atts = shortcode_atts(array(
            'id' => 0
        ), $atts, 'wpccs_carousel');

        $carousel_id = intval($atts['id']);
        if (!$carousel_id) return '';

        $config = get_post_meta($carousel_id, 'wpccs_config', true);
        if (!$config) return '';

        return $config['carousel_type'] === 'products' ? 
               $this->render_products_carousel($carousel_id, $config) : 
               $this->render_categories_carousel($carousel_id, $config);
    }

    // Método para renderizar carrusel de productos
    private function render_products_carousel($carousel_id, $config) {
        // Preparar argumentos de la consulta
        $args = array(
            'post_type' => 'product',
            'posts_per_page' => $config['total_items'],
            'orderby' => $config['products_orderby'],
            'order' => $config['products_order']
        );

        // Añadir filtros de taxonomía si están configurados
        $tax_query = array();

        if (!empty($config['product_categories'])) {
            $tax_query[] = array(
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $config['product_categories']
            );
        }

        if (!empty($config['product_tags'])) {
            $tax_query[] = array(
                'taxonomy' => 'product_tag',
                'field' => 'term_id',
                'terms' => $config['product_tags']
            );
        }

        if (!empty($tax_query)) {
            $args['tax_query'] = $tax_query;
        }

        $products = new WP_Query($args);
        
        ob_start();

        if ($products->have_posts()) {
            // Contenedor del carrusel
            echo '<div class="wpccs-carousel wpccs-products-carousel-' . $carousel_id . '">';
            
            while ($products->have_posts()) {
                $products->the_post();
                wc_get_template_part('content', 'product');
            }
            
            echo '</div>';

            // Script de inicialización
            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('.wpccs-products-carousel-<?php echo $carousel_id; ?>').slick({
                    slidesToShow: <?php echo $config['items_desktop']; ?>,
                    slidesToScroll: 1,
                    autoplay: <?php echo $config['autoplay'] ? 'true' : 'false'; ?>,
                    speed: <?php echo $config['transition_speed']; ?>,
                    arrows: <?php echo $config['show_arrows'] ? 'true' : 'false'; ?>,
                    dots: <?php echo $config['show_dots'] ? 'true' : 'false'; ?>,
                    infinite: <?php echo $config['infinite_loop'] ? 'true' : 'false'; ?>,
                    responsive: [
                        {
                            breakpoint: 1366,
                            settings: {
                                slidesToShow: <?php echo $config['items_laptop']; ?>
                            }
                        },
                        {
                            breakpoint: 1024,
                            settings: {
                                slidesToShow: <?php echo $config['items_tablet']; ?>
                            }
                        },
                        {
                            breakpoint: 767,
                            settings: {
                                slidesToShow: <?php echo $config['items_mobile']; ?>
                            }
                        }
                    ]
                });
            });
            </script>
            <?php
        }

        wp_reset_postdata();
        return ob_get_clean();
    }// Método para renderizar carrusel de categorías
    private function render_categories_carousel($carousel_id, $config) {
        $args = array(
            'taxonomy' => 'product_cat',
            'hide_empty' => $config['show_only_with_products'],
            'orderby' => $config['categories_orderby'],
            'order' => $config['categories_order']
        );

        // Si hay categorías seleccionadas, filtrarlas
        if (!empty($config['selected_categories'])) {
            $args['include'] = $config['selected_categories'];
        } else {
            // Si no hay selección, mostrar solo categorías de primer nivel
            $args['parent'] = 0;
        }

        $categories = get_terms($args);
        
        ob_start();

        if (!empty($categories) && !is_wp_error($categories)) {
            echo '<div class="wpccs-carousel wpccs-categories-carousel-' . $carousel_id . '">';
            
            foreach ($categories as $category) {
                $thumbnail_id = get_term_meta($category->term_id, 'thumbnail_id', true);
                $image = wp_get_attachment_url($thumbnail_id);
                ?>
                <div class="wpccs-category-item">
                    <a href="<?php echo esc_url(get_term_link($category)); ?>">
                        <?php if ($image) : ?>
                            <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($category->name); ?>">
                        <?php endif; ?>
                        <h3><?php echo esc_html($category->name); ?></h3>
                    </a>
                </div>
                <?php
            }
            
            echo '</div>';

            ?>
            <script type="text/javascript">
            jQuery(document).ready(function($) {
                $('.wpccs-categories-carousel-<?php echo $carousel_id; ?>').slick({
                    slidesToShow: <?php echo $config['items_desktop']; ?>,
                    slidesToScroll: 1,
                    autoplay: <?php echo $config['autoplay'] ? 'true' : 'false'; ?>,
                    speed: <?php echo $config['transition_speed']; ?>,
                    arrows: <?php echo $config['show_arrows'] ? 'true' : 'false'; ?>,
                    dots: <?php echo $config['show_dots'] ? 'true' : 'false'; ?>,
                    infinite: <?php echo $config['infinite_loop'] ? 'true' : 'false'; ?>,
                    responsive: [
                        {
                            breakpoint: 1366,
                            settings: {
                                slidesToShow: <?php echo $config['items_laptop']; ?>
                            }
                        },
                        {
                            breakpoint: 1024,
                            settings: {
                                slidesToShow: <?php echo $config['items_tablet']; ?>
                            }
                        },
                        {
                            breakpoint: 767,
                            settings: {
                                slidesToShow: <?php echo $config['items_mobile']; ?>
                            }
                        }
                    ]
                });
            });
            </script>
            <?php
        }

        return ob_get_clean();
    }
}

// Inicializar el plugin
$wpccs_plugin = new WooCommerce_PC_Carousel_Slider();
