<?php
/**
 * Plugin Name: WP Woocommerce to Google merchant center
 * Plugin URI: https://github.com/asaquzzaman/woocommerce-to-google-merchant-center
 * Description: Submit your product woocommerce to google merchant center.
 * Author: asaquzzaman
 * Version: 0.3
 * Author URI: http://mishubd.com
 * License: GPL2
 * TextDomain: wogo
 */

/**
 * Copyright (c) 2013 Asaquzzaman Mishu (email: joy.mishu@gmail.com). All rights reserved.
 *
 * Released under the GPL license
 * http://www.opensource.org/licenses/gpl-license.php
 *
 * This is an add-on for WordPress
 * http://wordpress.org/
 *
 * **********************************************************************
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 * **********************************************************************
 */



/**
 * Autoload class files on demand
 *
 * @param string $class requested class name
 */
function WOGO_autoload( $class ) {

    if ( stripos( $class, 'WOGO_' ) !== false ) {

        $admin = ( stripos( $class, '_Admin_' ) !== false ) ? true : false;

        if ( $admin ) {
            $class_name = str_replace( array('WOGO_Admin_', '_'), array('', '-'), $class );
            $filename = dirname( __FILE__ ) . '/admin/' . strtolower( $class_name ) . '.php';
        } else {
            $class_name = str_replace( array('WOGO_', '_'), array('', '-'), $class );
            $filename = dirname( __FILE__ ) . '/class/' . strtolower( $class_name ) . '.php';
        }
        if ( file_exists( $filename ) ) {
            require_once $filename;
        }
    }
}

spl_autoload_register( 'WOGO_autoload' );
require_once dirname(__FILE__) . '/includes/function.php';

define( 'WOOGOO_PATH', dirname( __FILE__ ) );

class WP_Wogo {

    private $client_id;
    private $client_secret;
    private $merchant_account_id;
    public static $version = '0.1';

    /**
     * class handelar or initial readable function
     * @return void
     */
    function __construct() {


        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_filter( 'manage_edit-product_columns', array( $this, 'product_columns_head' ), 20, 1 );
        add_action( 'manage_product_posts_custom_column', array( $this, 'product_columns' ), 10, 2 );
        add_action( 'admin_init', array( $this, 'get_token_from_url_code' ) );
        add_action( 'admin_init', array( $this, 'delete_product' ) );
        add_action( 'settings_text_field', array( $this, 'settings_text_field' ), 10, 2 );
        add_action( 'admin_enqueue_scripts', array( $this, 'scripts') );
        add_action( 'admin_init', array( $this, 'new_feed' ) );
        add_action( 'admin_init', array( $this, 'register_post_type' ) );
        add_action( 'admin_init', array( $this, 'feed_delete' ) );
        add_action( 'admin_init', array( $this, 'check_categori_fetch' ) );

        add_action( 'add_meta_boxes', array( $this, 'feed_meta_box' ) );
        add_action( 'init', array( $this, 'xml_download' ) );

        $this->instantiate();
    }

    function check_categori_fetch() {
        $feed_cat_fetch_time = get_option( 'woogoo_google_product_type_fetch_time', false );
        if ( ! $feed_cat_fetch_time ) {
            $this->store_google_product_type();
            return;
        }

        $cat = get_option( 'woogoo_google_product_type' );
        if ( ! $cat || ! count( $cat ) || empty( $cat ) ) {
            $this->store_google_product_type();
            return;
        }
        $minute_diff = woogoo_get_minute_diff( current_time( 'mysql' ), $feed_cat_fetch_time );

        if ( $minute_diff > 600 ) {
            $this->store_google_product_type();
        }
    }

    function store_google_product_type() {
        $cat = wogo_get_google_product_type();
        $cat = $cat ? $cat : array();
        update_option( 'woogoo_google_product_type', $cat );
        update_option( 'woogoo_google_product_type_fetch_time', current_time( 'mysql' ) );
    }

    function feed_delete() {
        if( ! isset( $_GET['page'] ) || ! isset( $_GET['tab'] ) || ! isset( $_GET['action'] ) ) {
            return;
        }

        if ( $_GET['page'] != 'product_wogo' || $_GET['tab'] != 'wogo_xml_feed' || $_GET['action'] != 'delete' ) {
            return;
        }

        $feed_id = isset( $_GET['feed_id'] ) ? intval( $_GET['feed_id'] ) : 0;

        if ( ! $feed_id ) {
            return;
        }

        wp_delete_post( $feed_id, true );

        $url_feed_list   = admin_url( 'edit.php?post_type=product&page=product_wogo&tab=wogo_xml_feed_list' );
        wp_redirect( $url_feed_list );
        exit();
    }

    function xml_download() {
        if ( ! isset( $_GET['woogoo_feed_download'] ) || ! isset( $_GET['nonce'] ) ) {
            return;
        }

        if ( $_GET['woogoo_feed_download'] !== 'true' ) {
            return;
        }

        if ( ! wp_verify_nonce( $_GET['nonce'], 'woogoo_feed_download' ) ) {
            return;
        }

        $feed_id = isset( $_GET['feed_id'] ) ? intval( $_GET['feed_id'] ) : 0;

        if ( ! $feed_id ) {
            return;
        }
        $post_feed = get_post( $feed_id );

        header( 'Content-Disposition: attachment; filename="Woogoo_product_List.xml"' );
        echo $post_feed->post_content;
        exit();
    }

    function feed_meta_box( $post_type ) {
        $msg1 = __( 'WooGoo Feed Information     (This feature is available for pro version)' );
        $msg2 =  __( 'Update to pro version', 'wogo' );
        $msg3 = sprintf( '%s       <a class="button button-primary" href="http://mishubd.com/product/woogoo/" target="_blank">%s</a>', $msg1, $msg2 );
        add_meta_box( 'wogo-feed-metabox-wrap', $msg3, array( $this, 'wogo_meta_box_callback' ), $post_type, 'normal', 'core' );
    }

    function wogo_meta_box_callback( $post ) {
        if ( $post->post_type != 'product' ) {
            return;
        }
        $post_id = $post->ID;
        include_once WOOGOO_PATH . '/views/new-feed.php';
    }

    function register_post_type() {
        register_post_type( 'woogoo_feed', array(
            'label'               => __( 'Feed', 'hrm' ),
            'public'              => false,
            'show_in_admin_bar'   => false,
            'exclude_from_search' => true,
            'publicly_queryable'  => false,
            'show_in_admin_bar'   => false,
            'show_ui'             => false,
            'show_in_menu'        => false,
            'capability_type'     => 'post',
            'hierarchical'        => false,
            'rewrite'             => array('slug' => ''),
            'query_var'           => true,
            'supports'            => array('title', 'editor'),
        ));
    }

    function new_feed() {
        if( ! isset( $_POST['wogo_submit_feed'] ) ) {
            return;
        }

        if ( ! wp_verify_nonce( $_POST['feed_nonce'], 'wogo_feed_nonce' ) ) {
            return;
        }

        $post   = $_POST;
        $feed_id = isset( $post['id'] ) ? intval( $post['id'] ) : false;

        $arg = array(
            'post_type'    => 'woogoo_feed',
            'post_title'   => $post['post_title'],
            'post_content' => $this->get_xml( $post ),
            'post_status'  => 'publish'
        );

        if ( $feed_id ) {
            $arg['ID'] = $feed_id;
            $post_id = wp_update_post( $arg );
        } else {
            $post_id = wp_insert_post( $arg );
        }

        if ( $post_id ) {
            $this->update_feed_meta( $post_id, $post );
        }

        $url_feed_list   = admin_url( 'edit.php?post_type=product&page=product_wogo&tab=wogo_xml_feed_list' );
        wp_redirect( $url_feed_list );
        exit();


       // header( 'Content-Disposition: attachment; filename="Woogoo_product_List.xml"' );
        //header( 'Content-Disposition: inline; filename="Woogoo_product_List.xml"' );

        /*echo "<?xml version='1.0' encoding='UTF-8' ?>\n";
        echo "<rss version='2.0' xmlns:atom='http://www.w3.org/2005/Atom' xmlns:g='http://base.google.com/ns/1.0'>\n";
        echo "  <channel>\n";
        echo "    <atom:link href='".htmlspecialchars( home_url() )."' rel='self' type='application/rss+xml' />\n";

        echo "        " . $this->content( $post );

        echo "  </channel>\n";
        echo '</rss>';*/
        // Core feed information

    }

    function get_xml( $post ) {
        ob_start();
        echo "<?xml version='1.0' encoding='ISO-8859-1'?>\n";
        echo "<rss version='2.0' xmlns:atom='http://www.w3.org/2005/Atom' xmlns:g='http://base.google.com/ns/1.0'>\n";
        echo "  <channel>\n";
        echo "    <atom:link href='".htmlspecialchars( home_url() )."' rel='self' type='application/rss+xml' />\n";

        echo $this->content( $post );

        echo "  </channel>\n";
        echo '</rss>';
        return ob_get_clean();
    }

    function update_feed_meta( $post_id, $post ) {

        $all_products = isset( $post['all_products'] ) ? $post['all_products'] : 0;
        update_post_meta( $post_id, '_all_products', $all_products );

        $products = isset( $post['products'] ) ? $post['products'] : array();
        update_post_meta( $post_id, '_products', $products );

        $google_product_category = isset( $post['google_product_category'] ) ? $post['google_product_category'] : '';
        update_post_meta( $post_id, '_google_product_category', $google_product_category );

        $product_type = isset( $post['product_type'] ) ? $post['product_type'] : '';
        update_post_meta( $post_id, '_product_type', $product_type );

        $availability = isset( $post['availability'] ) ? $post['availability'] : '';
        update_post_meta( $post_id, '_availability', $availability );

        $availability_date = isset( $post['availability_date'] ) ? $post['availability_date'] : '';
        update_post_meta( $post_id, '_availability_date', $availability_date );

        $condition = isset( $post['condition'] ) ? $post['condition'] : '';
        update_post_meta( $post_id, '_condition', $condition );

        $brand = isset( $post['brand'] ) ? $post['brand'] : '';
        update_post_meta( $post_id, '_brand', $brand );

        $mpn = isset( $post['mpn'] ) ? $post['mpn'] : '';
        update_post_meta( $post_id, '_mpn', $mpn );

        $gender = isset( $post['gender'] ) ? $post['gender'] : '';
        update_post_meta( $post_id, '_gender', $gender );

        $age_group = isset( $post['age_group'] ) ? $post['age_group'] : '';
        update_post_meta( $post_id, '_age_group', $age_group );

        $color = isset( $post['color'] ) ? $post['color'] : '';
        update_post_meta( $post_id, '_color', $color );

        $size = isset( $post['size'] ) ? $post['size'] : '';
        update_post_meta( $post_id, '_size', $size );

        $size_type = isset( $post['size_type'] ) ? $post['size_type'] : '';
        update_post_meta( $post_id, '_size_type', $size_type );

        $size_system = isset( $post['size_system'] ) ? $post['size_system'] : '';
        update_post_meta( $post_id, '_size_system', $size_system );

        $custom_label_0 = isset( $post['custom_label_0'] ) ? $post['custom_label_0'] : '';
        update_post_meta( $post_id, '_custom_label_0', $custom_label_0 );

        $custom_label_1 = isset( $post['custom_label_1'] ) ? $post['custom_label_1'] : '';
        update_post_meta( $post_id, '_custom_label_1', $custom_label_1 );

        $custom_label_2 = isset( $post['custom_label_2'] ) ? $post['custom_label_2'] : '';
        update_post_meta( $post_id, '_custom_label_2', $custom_label_2 );

        $custom_label_3 = isset( $post['custom_label_3'] ) ? $post['custom_label_3'] : '';
        update_post_meta( $post_id, '_custom_label_3', $custom_label_3 );

        $custom_label_4 = isset( $post['custom_label_4'] ) ? $post['custom_label_4'] : '';
        update_post_meta( $post_id, '_custom_label_4', $custom_label_4 );

        $promotion_id = isset( $post['promotion_id'] ) ? $post['promotion_id'] : '';
        update_post_meta( $post_id, '_promotion_id', $promotion_id );
    }

    function content( $post ) {
        if ( isset( $post['all_products'] ) ) {
            $products = $this->get_products();
            $products = wp_list_pluck( $products, 'ID' );
        } else {
            $products = $post['products'];
        }
        if ( ! count( $products ) ) {
            return false;
        }

        ob_start();
        $product_cat = get_option( 'woogoo_google_product_type' );

        foreach ( $products as $key => $product_id ) {
            $disabled = get_post_meta( $product_id, '_disabled_feed', true );
            if ( $disabled == 'disabled' ) {
                continue;
            }
            $wc_product    = new WC_Product( $product_id );

            $color = $wc_product->get_attribute('color');
            $size = $wc_product->get_attribute('size');

            if ( isset( $post['color'] ) && ! empty( $color ) ) {
                $color = str_replace(' ', '', $color );
                $color_attr = wogo_is_product_attribute_taxonomy( 'color', $wc_product ) ? str_replace( ',', '/', $color ) : str_replace( '|', '/', $color );
            } else {
                $color_attr = false;
            }

            if ( isset( $post['size'] ) && ! empty( $size ) ) {
                $size = str_replace(' ', '', $size );
                $size_attr = wogo_is_product_attribute_taxonomy( 'size', $wc_product ) ? str_replace( ',', '/', $size ) : str_replace( '|', '/', $size );
            } else {
                $size_attr = false;
            }

            $color_ind = get_post_meta( $product_id, '_color_default', true );
            if ( $color_ind != 'default' ) {
                $color = get_post_meta( $product_id, '_color', true );
                if ( empty( $color ) ) {
                    $color_attr = false;
                } else {
                    $color = str_replace(' ', '', $color );
                    $color_attr = str_replace( ',', '/', $color );
                }
            }

            $size_ind = get_post_meta( $product_id, '_size_default', true );
            if ( $size_ind != 'default' ) {
                $size = get_post_meta( $product_id, '_size', true );
                if ( empty( $size ) ) {
                    $size_attr = false;
                } else {
                    $size = str_replace(' ', '', $size );
                    $size_attr = str_replace( ',', '/', $size );
                }
            }

            $sale_price    = $wc_product->get_sale_price();

            $additional_images = array();

            $main_thumbnail = get_post_meta( $product_id, '_thumbnail_id', true );
            $images = get_children(
                array(
                    'post_parent'    => $product_id,
                    'post_status'    => 'inherit',
                    'post_type'      => 'attachment',
                    'post_mime_type' => 'image',
                    'exclude'        => isset($main_thumbnail) ? $main_thumbnail : '',
                    'order'          => 'ASC',
                    'orderby'        => 'menu_order',
                )
            );

            if ( is_array( $images ) && count( $images ) ) {
                foreach ( $images as $image ) {
                    $full_image_src      = wp_get_attachment_image_src( $image->ID, 'original' );
                    $additional_images[] = $full_image_src[0];
                }
            }
            $currency       = get_woocommerce_currency();
            $post_title     = $wc_product->post->post_title;
            $description    = $wc_product->post->post_content;
            $link           = $wc_product->get_permalink();
            $feed_image_url = wp_get_attachment_url( $wc_product->get_image_id() );

            //required attribute
            $condition_ind = get_post_meta( $product_id, '_condition', true );
            if ( $condition_ind == '-1' ) {
                $condition      = $post['condition'];
            } else {
                $condition  = empty( $condition_ind ) ? $post['condition'] : $condition_ind;
            }

            //required attribute
            $avaibility_ind = get_post_meta( $product_id, '_availability', true );
            if ( $avaibility_ind == 'default' ) {
                $availability   = $post['availability'];
            } else {
                $availability   = $avaibility_ind;
            }

            $pro_cat_ind = get_post_meta( $product_id, '_google_product_category', true );
            if ( $pro_cat_ind == 'default' ) {
                $category = $post['google_product_category'] ? $product_cat[$post['google_product_category']] : false;
                $category = $category ? str_replace( "&", "&amp;", $category ) : false;
                $category = $category ? str_replace( ">", "&gt;", $category ) : false;
            } else {
                if ( empty( $pro_cat_ind ) ) {
                    $category = false;
                } else {
                    $category = $product_cat[$pro_cat_ind];
                    $category = $category ? str_replace( "&", "&amp;", $category ) : false;
                    $category = $category ? str_replace( ">", "&gt;", $category ) : false;
                }
            }

            $pro_typ_ind = get_post_meta( $product_id, '_product_type', true );
            if ( $pro_typ_ind == 'default' ) {
                $type = $post['product_type'] ? $product_cat[$post['product_type']] : false;
                $type = $type ? str_replace( "&", "&amp;", $type ) : false;
                $type = $type ? str_replace( ">", "&gt;", $type ) : false;
            } else {
                if ( empty( $pro_typ_ind ) ) {
                    $type = false;
                } else {
                    $type = $product_cat[$pro_typ_ind];
                    $type = $type ? str_replace( "&", "&amp;", $type ) : false;
                    $type = $type ? str_replace( ">", "&gt;", $type ) : false;
                }
            }

            $availability_date_ind = get_post_meta( $product_id, '_availability_date_default', true );
            if ( $availability_date_ind == 'default' ) {
                $availability_date = !empty( $post['availability_date'] ) ? $post['availability_date'] : false;
            } else {
                $availability_date = get_post_meta( $product_id, '_availability_date', true );
                $availability_date = empty( $availability_date ) ? false : $availability_date;
            }

            if ( $availability_date ) {
                $tz_offset = get_option( 'gmt_offset' );
                $availability_value = $availability_date.'T00:00:00' . sprintf( '%+03d', $tz_offset ) . '00';
            }

            $sku_ind = get_post_meta( $product_id, '_mpn_default', true );
            if ( $sku_ind == 'default' ) {
                $mpn = isset( $post['mpn'] ) ? true : false;
                $sku = $wc_product->get_sku();
                $sku           = ! empty( $sku ) ? $sku : false;
                $sku_as_mpn    = $mpn ? $sku : false;
            } else {
                $sku_as_mpn = get_post_meta( $product_id, '_mpn', true );
                $sku_as_mpn = empty( $sku_as_mpn ) ? false : $sku_as_mpn;
            }

            $gender_ind = get_post_meta( $product_id, '_gender', true );
            if ( $gender_ind == 'default' ) {
                $gender = $post['gender'] == '-1' ? false : $post['gender'];
            } else {
                $gender = ( $gender_ind == '-1' ) ? false : $gender_ind;
            }

            $age_group_ind = get_post_meta( $product_id, '_age_group', true );
            if ( $age_group_ind == 'default' ) {
                $age_group = $post['age_group'] == '-1' ? false : $post['age_group'];
            } else {
                $age_group = ( $age_group_ind == '-1' ) ? false : $age_group_ind;
            }

            $size_type_ind = get_post_meta( $product_id, '_size_type', true );
            if ( $size_type_ind == 'default' ) {
                $size_type = $post['size_type'] == '-1' ? false : $post['size_type'];
            } else {
                $size_type = ( $size_type_ind == '-1' ) ? false : $size_type_ind;
            }

            $size_system_ind = get_post_meta( $product_id, '_size_system', true );
            if ( $size_system_ind == 'default' ) {
                $size_system = $post['size_system'] == '-1' ? false : $post['size_system'];
            } else {
                $size_system = ( $size_system_ind == '-1' ) ? false : $size_system_ind;
            }

            $custom_label_0_ind = get_post_meta( $product_id, '_custom_label_0_default', true );
            if ( $custom_label_0_ind == 'default' ) {
                $custom_label_0 = ! empty( $post['custom_label_0'] ) ? $post['custom_label_0'] : false;
            } else {
                $custom_label_0 = get_post_meta( $product_id, '_custom_label_0', true );
                $custom_label_0 = empty( $custom_label_0 ) ? false : $custom_label_0;
            }

            $custom_label_1_ind = get_post_meta( $product_id, '_custom_label_1_default', true );
            if ( $custom_label_1_ind == 'default' ) {
                $custom_label_1 = ! empty( $post['custom_label_1'] ) ? $post['custom_label_1'] : false;
            } else {
                $custom_label_1 = get_post_meta( $product_id, '_custom_label_1', true );
                $custom_label_1 = empty( $custom_label_1 ) ? false : $custom_label_1;
            }

            $custom_label_2_ind = get_post_meta( $product_id, '_custom_label_2_default', true );
            if ( $custom_label_2_ind == 'default' ) {
                $custom_label_2 = ! empty( $post['custom_label_2'] ) ? $post['custom_label_2'] : false;
            } else {
                $custom_label_2 = get_post_meta( $product_id, '_custom_label_2', true );
                $custom_label_2 = empty( $custom_label_2 ) ? false : $custom_label_2;
            }

            $custom_label_3_ind = get_post_meta( $product_id, '_custom_label_3_default', true );
            if ( $custom_label_3_ind == 'default' ) {
                $custom_label_3 = ! empty( $post['custom_label_3'] ) ? $post['custom_label_3'] : false;
            } else {
                $custom_label_3 = get_post_meta( $product_id, '_custom_label_3', true );
                $custom_label_3 = empty( $custom_label_3 ) ? false : $custom_label_3;
            }

            $custom_label_4_ind = get_post_meta( $product_id, '_custom_label_4_default', true );
            if ( $custom_label_4_ind == 'default' ) {
                $custom_label_4 = ! empty( $post['custom_label_4'] ) ? $post['custom_label_4'] : false;
            } else {
                $custom_label_4 = get_post_meta( $product_id, '_custom_label_4', true );
                $custom_label_4 = empty( $custom_label_4 ) ? false : $custom_label_4;
            }

            $promotion_id_ind = get_post_meta( $product_id, '_promotion_id_default', true );
            if ( $promotion_id_ind == 'default' ) {
                $promotion_id = ! empty( $post['promotion_id'] ) ? $post['promotion_id'] : false;
            } else {
                $promotion_id = get_post_meta( $product_id, '_promotion_id', true );
                $promotion_id = empty( $promotion_id ) ? false : $promotion_id;
            }


            $brand_ind = get_post_meta( $product_id, '_brand_default', true );
            if ( $brand_ind == 'default' ) {
                $brand = ! empty( $post['brand'] ) ? $post['brand'] : false;
            } else {
                $brand = get_post_meta( $product_id, '_brand', true );
                $brand = empty( $brand ) ? false : $brand;
            }

            $price  = $wc_product->get_price() . ' ' . $currency;

            echo "<item>\n";
            echo "   <g:id>$wc_product->id</g:id>\n";
            echo "   <title>$post_title</title>\n";
            echo "   <description>$description</description>\n";
            echo "   <link>$link</link>\n";
            echo "   <g:image_link>$feed_image_url</g:image_link>\n";
            echo "   <g:condition>$condition</g:condition>\n";
            echo "   <g:availability>$availability</g:availability>\n";
            echo "   <g:price>$price</g:price>\n";

            echo $category ?  "<g:google_product_category>$category</g:google_product_category>\n" : '';
            echo $type ? "<g:product_type>$type</g:product_type>\n" : '';
            echo $availability_date ? "<g:availability_date>$availability_value</g:availability_date>\n" : '';
            echo ! empty( $sale_price ) ? "<g:sale_price>$sale_price $currency</g:sale_price>\n" : '';
            echo $sku_as_mpn ? "<g:mpn>$sku_as_mpn</g:mpn>\n" : '';
            echo $gender ? "<g:gender>$gender</g:gender>\n" : '';
            echo $age_group ? "<g:age_group>$age_group</g:age_group>\n" : '';
            echo $brand ? "<g:brand>$brand</g:brand>\n" : '';
            echo $size_type ? "<g:size_type>$size_type</g:size_type>\n" : '';
            echo $size_system ? "<g:size_system>$size_system</g:size_system>\n" : '';
            echo $custom_label_0 ? "<g:custom_label_0>$custom_label_0</g:custom_label_0>\n" : '';
            echo $custom_label_1 ? "<g:custom_label_1>$custom_label_1</g:custom_label_1>\n" : '';
            echo $custom_label_2 ? "<g:custom_label_2>$custom_label_2</g:custom_label_2>\n" : '';
            echo $custom_label_3 ? "<g:custom_label_3>$custom_label_3</g:custom_label_3>\n" : '';
            echo $custom_label_4 ? "<g:custom_label_4>$custom_label_4</g:custom_label_4>\n" : '';
            echo $promotion_id ? "<g:promotion_id>$promotion_id</g:promotion_id>\n" : '';
            echo $color_attr ? "<g:color>$color_attr</g:color>\n" : '';
            echo $size_attr ? "<g:size>$size_attr</g:size>\n" : '';

                $cnt = 1;
                foreach ( $additional_images as $image_url ) {
                    // Google limit the number of additional images to 10
                    if ( $cnt == 10 )
                        break;
                    echo "<g:additional_image_link>$image_url</g:additional_image_link>\n";
                    $cnt++;
                }



            echo "</item>\n";

        }
        return ob_get_clean();
    }

    public static function install() {

        update_option( 'wogo_version', self::$version );
        self::set_feed_default();
    }

    public static function set_feed_default() {
        $products = self::get_products();

        foreach ( $products as $key => $product ) {
            $product_id = $product->ID;

            update_post_meta( $product_id, '_google_product_category', 'default' );
            update_post_meta( $product_id, '_product_type', 'default' );
            update_post_meta( $product_id, '_availability', 'default' );
            update_post_meta( $product_id, '_availability_date_default', 'default' );
            update_post_meta( $product_id, '_brand_default', 'default' );
            update_post_meta( $product_id, '_mpn_default', 'default' );
            update_post_meta( $product_id, '_color_default', 'default' );
            update_post_meta( $product_id, '_size_default', 'default' );
            update_post_meta( $product_id, '_custom_label_0_default', 'default' );
            update_post_meta( $product_id, '_custom_label_1_default', 'default' );
            update_post_meta( $product_id, '_custom_label_2_default', 'default' );
            update_post_meta( $product_id, '_custom_label_3_default', 'default' );
            update_post_meta( $product_id, '_custom_label_4_default', 'default' );
            update_post_meta( $product_id, '_promotion_id_default', 'default' );
            update_post_meta( $product_id, '_gender', 'default' );
            update_post_meta( $product_id, '_age_group', 'default' );
            update_post_meta( $product_id, '_size_type', 'default' );
            update_post_meta( $product_id, '_size_system', 'default' );
        }
    }

    function delete_product() {
        if( !isset( $_GET['page'] ) ) {
            return;
        }

        if ( $_GET['page'] != 'product_wogo' ) {
            return;
        }

        if ( !isset( $_GET['product_id'] ) || empty( $_GET['product_id'] ) ) {
            return;
        }

        if ( !isset( $_GET['action'] ) ) {
            return;
        }
        if ( $_GET['action'] != 'delete' ) {
            return;
        }

        $this->delete_product_from_merchant( $_GET['product_id'] );
        wp_redirect( admin_url('edit.php?post_type=product&page=product_wogo') );
    }

    /**
     * Delete product form google merchant center
     * @param  init $post_id
     * @return void
     */
    function product_delete( $post_id ) {
        global $post_type;
        if ( $post_type != 'product' ) return;
        $this->delete_product_from_merchant( $post_id );
    }

    function delete_product_from_merchant( $product_id ) {
        $merchant_status = get_post_meta( $product_id, 'merchant_status', true );

        if ( $merchant_status != 'yes' ) {
            return;
        }

        $merchant_id = get_post_meta( $product_id, 'merchant_id', true );
        $merchant_product_id = get_post_meta( $product_id, 'merchant_product_id', true );

        $client = wogo_get_client();

        if ( !$client ) {
            wp_redirect( admin_url( 'edit.php?post_type=product&page=product_wogo' ) );
        }

        $shoppinContent = new Google_Service_ShoppingContent($client);
        try {

            $shoppinContent->products->delete( $merchant_id, $merchant_product_id );
            delete_post_meta( $product_id, 'merchant_status' );
            delete_post_meta( $product_id, 'merchant_product_id' );
            delete_post_meta( $product_id, 'merchant_id' );
        }  catch( Google_Service_Exception $e ) {
            if ( strpos( $e->getMessage(), 'item not found' ) === false ) {

            } else {
                delete_post_meta( $product_id, 'merchant_status' );
                delete_post_meta( $product_id, 'merchant_product_id' );
                delete_post_meta( $product_id, 'merchant_id' );
            }
        }
    }

    /**
     * Initialy instantiate some class
     * @return void
     */
    function instantiate() {
        $user_id = get_current_user_id();
        $client_id = get_user_meta( $user_id, 'wogo_client_id', true );
        $client_secret = get_user_meta( $user_id, 'wogo_client_secret', true );
        $this->client_id = str_replace( ' ', '', $client_id );
        $this->client_secret = str_replace( ' ', '', $client_secret );
        new WOGO_Admin_ajax();
    }

    /**
     * Load script
     * @return  void
     */
    function scripts() {
        wp_enqueue_script( 'jquery' );
        wp_enqueue_script( 'jquery-ui-datepicker' );
        wp_enqueue_script( 'wogo-chosen', plugins_url( '/assets/js/chosen.jquery.min.js', __FILE__ ), array( 'jquery' ), false, true);
        wp_enqueue_script( 'wogo-script', plugins_url( 'assets/js/wogo.js', __FILE__ ), array( 'jquery' ), false, true );
        wp_localize_script( 'wogo-script', 'wogo_var', array(
            'ajaxurl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'wogo_nonce' ),
            'is_admin' => is_admin() ? 'yes' : 'no',
        ));
        wp_enqueue_style( 'wogo-chosen', plugins_url( '/assets/css/chosen.min.css', __FILE__ ), false, false, 'all' );
        wp_enqueue_style( 'wogo-style', plugins_url( 'assets/css/wogo.css', __FILE__ ) );
        wp_enqueue_style( 'wogo-jquery-ui', plugins_url( '/assets/css/jquery-ui.css', __FILE__ ), false, false, 'all' );
    }
    /**
     * Set woocommerce submenu
     * @return void
     */
    function admin_menu() {
        $wogo = add_submenu_page( 'edit.php?post_type=product', __( 'WooGoo', 'woocommerce' ), __( 'WooGoo', 'woocommerce' ), 'manage_product_terms', 'product_wogo', array( $this, 'wogo_page' ) );
        add_action( 'admin_print_styles-' . $wogo, array( $this, 'scripts' ) );
    }
    /**
     * View page controller
     * @return [type] [description]
     */
    function wogo_page() {

        if ( isset( $_GET['product_id'] ) ) {
            update_user_meta( get_current_user_id(), 'wogo_product_id', $_GET['product_id'] );
        }

        include_once dirname (__FILE__) . '/views/header.php';

    }

    /**
     * check google authenticate for show submitted product form
     * @return boolen
     */
    function check_authenticate() {

        if ( ! isset( $_GET['post_type'] ) || $_GET['post_type'] != 'product' ) {
            return false;
        }

        if ( ! isset( $_GET['page'] ) || $_GET['page'] != 'product_wogo' ) {
            return false;
        }

        if ( wogo_get_access_token() ) {
           return true;
        }

        return false;
    }

    function authentication_process() {
        $client = wogo_google_class();
        $scriptUri = admin_url( 'edit.php?post_type=product&page=product_wogo' );
        $client->setClientId( $this->client_id );
        $client->setClientSecret( $this->client_secret );
        $client->setRedirectUri( $scriptUri );
        $client->setScopes( 'https://www.googleapis.com/auth/content' );

        if ( ! isset($_GET['code']) ) {
            $loginUrl = $client->createAuthurl();
            echo '<h1>Expired Access Token Please Click <a class="button button-primary" href="'.$loginUrl.'">Here </a> To Login</h1>';
        }
    }

    /**
     * Save WooGoo setting and get token from url code
     * @return void
     */
    function get_token_from_url_code() {
        if ( !isset( $_GET['post_type'] ) || $_GET['post_type'] != 'product' ) {
            return;
        }

        if ( !isset( $_GET['page'] ) || $_GET['page'] != 'product_wogo' ) {
            return;
        }

        $this->save_setting();
        $this->get_new_token();
    }

    /**
     * Save WooGoo settings
     * @return void
     */
    function save_setting() {
        if ( isset( $_POST['wogo_settings'] ) ) {
            update_user_meta( get_current_user_id(), 'wogo_client_id', $_POST['client_id'] );
            update_user_meta( get_current_user_id(), 'wogo_client_secret', $_POST['client_secret'] );
            update_user_meta( get_current_user_id(), 'merchant_account_id', $_POST['merchant_account_id'] );
            $redirect_url = admin_url( 'edit.php?post_type=product&page=product_wogo&tab=wogo_settings' );
            wp_redirect( $redirect_url );
            exit();
        }
    }

    /**
     * Get google token from url parametar code
     * @return void
     */
    function get_new_token() {

        $client = wogo_google_class();
        $scriptUri = admin_url( 'edit.php?post_type=product&page=product_wogo' );
        $client->setClientId( $this->client_id );
        $client->setClientSecret( $this->client_secret );
        $client->setRedirectUri( $scriptUri );
        $client->setScopes( 'https://www.googleapis.com/auth/content' );

        if ( isset($_GET['code']) ) {
            $client->authenticate( $_GET['code'] );
            $access_token = $client->getAccessToken();
            $user_id = get_current_user_id();
            update_user_meta( $user_id, 'access_token', $access_token );
            wp_redirect( $scriptUri );
            exit();
        }
    }

    /**
     * Product listing table header
     * @return product table column
     */
    function product_columns( $column_name, $post_id  ) {
        if ( $column_name != 'merchent_center' ) {
            return;
        }
        $url = admin_url( 'edit.php?post_type=product&page=product_wogo&tab=wogo_new_product&product_id=' . $post_id );
        $merchant_status = get_post_meta( $post_id, 'merchant_status', true );
        if ( $merchant_status == 'yes' ) {
            $merchant_product_id = get_post_meta( $post_id, 'merchant_product_id', true );
            $merchant_id = get_post_meta( $post_id, 'merchant_id', true );

            echo '<a class="wogo-delete-product" data-post_id="'.$post_id.'" data-merchant_product_id="'.$merchant_product_id.'" data-merchant_id="'.$merchant_id.'" href="#">
            Delete</a>';
            return;
        }

        ?>
        <a data-post_id="<?php echo $post_id; ?>" class="wogo-add-product" href="<?php echo $url; ?>"><?php _e( 'Add', 'wogo' ); ?></a>
        <div class="wogo-merchant-wrap wogo-product-<?php echo $post_id; ?>"></div>
        <?php

    }

    /**
     * Get woocommerce all product
     * @return array()
     */
    public static function get_products() {
        $args = array(
            'posts_per_page'   => -1,
            'post_type'        => 'product',
            'post_status'      => 'publish',
        );

        return get_posts( $args );
    }

    /**
     * Product table column head
     * @param  array $existing_columns
     * @return array
     */
    function product_columns_head( $existing_columns ) {

        unset( $existing_columns['date'] );
        $head = array();
        $head['merchent_center'] = __('Google Merchant Center', 'wogo' );
        $head['date'] = __( 'Date', 'woocommerce' );

        return array_merge( $existing_columns, $head );
    }

    /**
     * Filter for add something after text field
     * @param  string $name
     * @param  array $element
     * @return void
     */
    function settings_text_field( $name, $element ) {

        if ( isset( $element['extra']['data-add_more'] ) && $element['extra']['data-add_more'] === true ) {
            ?>
            <i class="wogo-more-field">+</i>
            <?php
        }
        if ( isset( $element['extra']['data-remove_more'] ) && $element['extra']['data-remove_more'] === true ) {
            ?>
                <i class="wogo-remove-more">-</i>
            <?php
        }
    }
}

register_activation_hook( __FILE__, array( 'WP_Wogo', 'install' ) );
add_action( 'plugins_loaded', 'woogoo_init' );

function woogoo_init() {
    new WP_Wogo();
}

/**
 * Google client class Instantiate
 * @return object
 */
function wogo_google_class() {
    static $client;
    if ( !$client ) {
        $plugin_path = plugin_dir_path( __FILE__ ) . 'includes';
        set_include_path( $plugin_path . PATH_SEPARATOR . get_include_path());

        require_once 'Google/Client.php';
        require_once 'Google/Service/ShoppingContent.php';

        $client = new Google_Client();
    }

    return $client;
}

/**
 * Check token validate
 * @return string
 */
function wogo_get_access_token() {

    $user_id = get_current_user_id();
    $access_token = get_user_meta( $user_id, 'access_token', true );
    if ( empty( $access_token ) ) {
        return false;
    }

    $client = wogo_google_class();

    $client->setAccessToken( $access_token );

    if ( $client->isAccessTokenExpired() ) {
        return false;
    }
    return $access_token;
}

/**
 * Get client token
 * @return string or boolen
 */
function wogo_get_client() {
    $client = wogo_google_class();
    $access_token = wogo_get_access_token();
    if ( $access_token ) {
        $client->setAccessToken( $access_token );
        $client->getAccessToken();

        return $client;
    }
    return false;
}

function wogo_get_products_list() {
    $client         = wogo_get_client();
    $shoppinContent = new Google_Service_ShoppingContent($client);
    $merchant_id    = get_user_meta( get_current_user_id(), 'merchant_account_id', true );
    $products       = $shoppinContent->products->listProducts( $merchant_id );

    return $products;
}

function wogo_get_product( $product_id ) {
    $client         = wogo_get_client();
    $shoppinContent = new Google_Service_ShoppingContent($client);
    $merchant_id    = get_user_meta( get_current_user_id(), 'merchant_account_id', true );
    $products       = $shoppinContent->products->get( $merchant_id, $product_id );

    return $products;
}

function wogo_get_google_product_type() {
    $request = wp_remote_get( 'http://www.google.com/basepages/producttype/taxonomy.en-US.txt' );
    if ( is_wp_error( $request ) || ! isset( $request['response']['code'] ) || '200' != $request['response']['code'] ) {
        return array();
    }
    $taxonomies = explode( "\n", $request['body'] );
    // Strip the comment at the top
    array_shift( $taxonomies );
    // Strip the extra newline at the end
    array_pop( $taxonomies );
    $taxonomies = array_merge( array( __( '-Select-', 'wogo' ) ), $taxonomies );
    return $taxonomies;
}

function wogo_get_feeds() {

    $args = array(
        'posts_per_page'   => -1,
        'post_type'        => 'woogoo_feed',
        'post_status'      => 'publish',
    );

    return get_posts( $args );
}

function woogoo_get_minute_diff( $current_time, $request_time ) {
    $current_time = new DateTime( $current_time );
    $request_time = new DateTime( $request_time );
    $interval     = $request_time->diff( $current_time );
    $day          = $interval->d ? $interval->d * 24 * 60 : 0;
    $hour         = $interval->h ? $interval->h * 60 : 0;
    $minute       = $interval->i ? $interval->i : 0;
    $total_minute = $day + $hour + $minute;

    return $total_minute;
}

function wogo_is_product_attribute_taxonomy( $attr, $porduct_obj ) {

    $attributes = $porduct_obj->get_attributes();

    $attr = sanitize_title( $attr );

    if ( isset( $attributes[ $attr ] ) || isset( $attributes[ 'pa_' . $attr ] ) ) {

        $attribute = isset( $attributes[ $attr ] ) ? $attributes[ $attr ] : $attributes[ 'pa_' . $attr ];
        if ( $attribute['is_taxonomy'] ) {
            return true;
        } else {
         return false;
        }
    }
    return false;
}
