<?php
if (!defined('ABSPATH')) exit;

class YDZ_PlanMapper {

    public static function get_product_id($product_slug, $plan) {
        
        // 1. Cari Produk Induk berdasarkan Slug (URL)
        $parent_product = get_page_by_path( $product_slug, OBJECT, 'product' );
        
        if ( ! $parent_product ) {
            return null; // Produk tidak ditemukan
        }

        $wc_product = wc_get_product( $parent_product->ID );

        // Jika ini produk tunggal (Simple Product), langsung kembalikan ID-nya
        if ( $wc_product->is_type( 'simple' ) ) {
            return $wc_product->get_id();
        }

        // 2. Jika ini produk Variable, cari ID Variasinya secara dinamis
        if ( $wc_product->is_type( 'variable' ) ) {
            
            $variations = $wc_product->get_available_variations();
            $plan_lower = strtolower( trim( $plan ) );

            foreach ( $variations as $variation ) {
                $attributes = $variation['attributes'];
                
                // Cek setiap atribut di dalam variasi tersebut
                foreach ( $attributes as $attr_key => $attr_value ) {
                    // Jika nilai atribut cocok dengan plan di URL (contoh: 'agency' == 'agency')
                    if ( strtolower( trim( $attr_value ) ) === $plan_lower ) {
                        return $variation['variation_id']; // Ditemukan! Kembalikan ID Variasinya
                    }
                }
            }
        }

        return null; // Gagal menemukan kecocokan
    }
}