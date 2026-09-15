<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Data Layer - Tutor is edu source, Woo is commercial source
 * No DB merge, link via _tutor_course_product_id
 * Compatible with snippetTutorCards.php
 */
final class AAP_Data {

    public static function tutor_available() {
        return function_exists( 'tutor' ) && function_exists( 'tutor_utils' );
    }

    public static function woo_available() {
        return function_exists( 'WC' ) && class_exists( 'WooCommerce' );
    }

    public static function course_post_type() {
        if ( self::tutor_available() && ! empty( tutor()->course_post_type ) ) {
            return sanitize_key( tutor()->course_post_type );
        }
        return post_type_exists( 'courses' ) ? 'courses' : 'tutor_course';
    }

    public static function course_taxonomy() {
        if ( self::tutor_available() && ! empty( tutor()->course_taxonomy ) ) {
            return sanitize_key( tutor()->course_taxonomy );
        }
        foreach ( array( 'course-category', 'course_cat', 'tutor_course_category' ) as $tax ) {
            if ( taxonomy_exists( $tax ) ) {
                return $tax;
            }
        }
        return '';
    }

    public static function is_featured( $course_id ) {
        foreach ( array( '_tutor_course_featured', '_tutor_is_featured', 'is_featured' ) as $key ) {
            if ( in_array( strtolower( (string) get_post_meta( $course_id, $key, true ) ), array( '1', 'yes', 'true', 'on' ), true ) ) {
                return true;
            }
        }
        return false;
    }

    public static function get_course_ids( $limit, $category = '' ) {
        $args = array(
            'post_type'              => self::course_post_type(),
            'post_status'            => 'publish',
            'posts_per_page'         => max( 12, min( 60, $limit * 4 ) ),
            'orderby'                => 'date',
            'order'                  => 'DESC',
            'no_found_rows'          => true,
            'ignore_sticky_posts'    => true,
            'fields'                 => 'ids',
            'update_post_meta_cache' => true,
            'update_post_term_cache' => true,
        );
        $taxonomy = self::course_taxonomy();
        if ( $taxonomy && $category ) {
            $term = term_exists( $category, $taxonomy );
            if ( $term ) {
                $args['tax_query'] = array(
                    array(
                        'taxonomy' => $taxonomy,
                        'field'    => is_numeric( $category ) ? 'term_id' : 'slug',
                        'terms'    => is_numeric( $category ) ? absint( $category ) : sanitize_title( $category ),
                    ),
                );
            }
        }
        $ids = get_posts( $args );
        $ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
        usort(
            $ids,
            function ( $left, $right ) {
                $lf = self::is_featured( $left ) ? 1 : 0;
                $rf = self::is_featured( $right ) ? 1 : 0;
                if ( $lf !== $rf ) {
                    return $rf <=> $lf;
                }
                $lo = (int) get_post_field( 'menu_order', $left );
                $ro = (int) get_post_field( 'menu_order', $right );
                if ( $lo !== $ro ) {
                    return $lo <=> $ro;
                }
                return (int) get_post_time( 'U', true, $right ) <=> (int) get_post_time( 'U', true, $left );
            }
        );
        return array_slice( $ids, 0, $limit );
    }

    public static function product_map( $course_ids ) {
        $map = array();
        $product_ids = array();
        foreach ( $course_ids as $cid ) {
            $pid = absint( get_post_meta( $cid, '_tutor_course_product_id', true ) );
            $map[ $cid ] = $pid;
            if ( $pid ) {
                $product_ids[] = $pid;
            }
        }
        if ( ! self::woo_available() || empty( $product_ids ) ) {
            return array_fill_keys( array_keys( $map ), false );
        }
        $products = wc_get_products(
            array(
                'include' => array_values( array_unique( $product_ids ) ),
                'limit'   => -1,
                'status'  => array( 'publish', 'private' ),
                'return'  => 'objects',
            )
        );
        $by_id = array();
        foreach ( $products as $p ) {
            if ( is_object( $p ) && method_exists( $p, 'get_id' ) ) {
                $by_id[ (int) $p->get_id() ] = $p;
            }
        }
        foreach ( $map as $cid => $pid ) {
            $map[ $cid ] = $pid && isset( $by_id[ $pid ] ) ? $by_id[ $pid ] : false;
        }
        return $map;
    }

    public static function enrolled_ids() {
        if ( ! is_user_logged_in() || ! self::tutor_available() ) {
            return array();
        }
        $enrolled = tutor_utils()->get_enrolled_courses_by_user( get_current_user_id(), array( 'publish', 'private' ) );
        $posts = $enrolled instanceof WP_Query ? $enrolled->posts : ( is_array( $enrolled ) ? $enrolled : array() );
        $ids = array();
        foreach ( $posts as $c ) {
            $id = is_object( $c ) && isset( $c->ID ) ? $c->ID : $c;
            if ( absint( $id ) ) {
                $ids[] = absint( $id );
            }
        }
        return array_values( array_unique( $ids ) );
    }

    public static function progress( $course_id ) {
        if ( ! self::tutor_available() || ! is_user_logged_in() ) {
            return array( 'percent' => 0, 'completed' => 0, 'total' => 0 );
        }
        $stats = tutor_utils()->get_course_completed_percent( $course_id, get_current_user_id(), true );
        if ( ! is_array( $stats ) ) {
            return array( 'percent' => max( 0, min( 100, (int) $stats ) ), 'completed' => 0, 'total' => 0 );
        }
        return array(
            'percent'   => max( 0, min( 100, (int) ( $stats['completed_percent'] ?? 0 ) ) ),
            'completed' => absint( $stats['completed_count'] ?? 0 ),
            'total'     => absint( $stats['total_count'] ?? 0 ),
        );
    }

    public static function continue_url( $course_id, $percent ) {
        $course_url = get_permalink( $course_id );
        if ( $percent <= 0 || ! self::tutor_available() ) {
            return $course_url;
        }
        $lesson = tutor_utils()->get_course_first_lesson( $course_id );
        if ( is_numeric( $lesson ) ) {
            $lesson = get_permalink( absint( $lesson ) );
        } elseif ( $lesson instanceof WP_Post ) {
            $lesson = get_permalink( $lesson->ID );
        }
        return $lesson ? $lesson : $course_url;
    }

    public static function course_detail( $course_id, $kind ) {
        if ( self::tutor_available() ) {
            $method = 'level' === $kind ? 'get_course_level' : 'get_course_duration_context';
            if ( is_object( tutor_utils() ) && method_exists( tutor_utils(), $method ) ) {
                $v = tutor_utils()->$method( $course_id );
                if ( is_scalar( $v ) && '' !== trim( (string) $v ) ) {
                    return wp_strip_all_tags( (string) $v );
                }
            }
        }
        $keys = 'level' === $kind ? array( '_tutor_course_level', 'course_level' ) : array( '_tutor_course_duration', 'course_duration' );
        foreach ( $keys as $k ) {
            $v = get_post_meta( $course_id, $k, true );
            if ( is_scalar( $v ) && '' !== trim( (string) $v ) ) {
                return wp_strip_all_tags( (string) $v );
            }
        }
        return '';
    }

    public static function cart_contains( $product_id ) {
        if ( ! $product_id || ! self::woo_available() ) {
            return false;
        }
        if ( function_exists( 'wc_load_cart' ) && ( ! WC()->cart || ! WC()->session ) ) {
            wc_load_cart();
        }
        if ( ! WC()->cart ) {
            return false;
        }
        foreach ( WC()->cart->get_cart() as $item ) {
            if ( $product_id === absint( $item['product_id'] ?? 0 ) || $product_id === absint( $item['variation_id'] ?? 0 ) ) {
                return true;
            }
        }
        return false;
    }

    public static function course_data( $course_ids ) {
        $products = self::product_map( $course_ids );
        $enrolled = self::enrolled_ids();
        $rows = array();
        $author_ids = array();
        foreach ( $course_ids as $cid ) {
            $author_ids[] = (int) get_post_field( 'post_author', $cid );
        }
        $authors = array();
        foreach ( array_unique( $author_ids ) as $aid ) {
            $authors[ $aid ] = get_the_author_meta( 'display_name', $aid );
        }
        foreach ( $course_ids as $cid ) {
            $product = $products[ $cid ] ?? false;
            $prog = self::progress( $cid );
            $is_enrolled = in_array( $cid, $enrolled, true );
            $is_free = ! $product || ( method_exists( $product, 'is_free' ) ? $product->is_free() : (float) $product->get_price() <= 0 );
            $tax = self::course_taxonomy();
            $terms = $tax ? get_the_terms( $cid, $tax ) : array();
            $term_names = is_array( $terms ) ? wp_list_pluck( array_slice( $terms, 0, 2 ), 'name' ) : array();
            $image = get_the_post_thumbnail_url( $cid, 'medium_large' );
            $course_url = get_permalink( $cid );
            $action_url = $is_enrolled ? self::continue_url( $cid, $prog['percent'] ) : $course_url;
            $rows[] = array(
                'id'          => $cid,
                'title'       => get_the_title( $cid ),
                'url'         => $course_url,
                'action_url'  => $action_url,
                'image'       => $image,
                'author'      => $authors[ (int) get_post_field( 'post_author', $cid ) ] ?? '',
                'terms'       => $term_names,
                'product'     => $product,
                'is_free'     => $is_free,
                'is_enrolled' => $is_enrolled,
                'progress'    => $prog,
                'in_cart'     => $product && self::cart_contains( $product->get_id() ),
            );
        }
        return $rows;
    }

    public static function categories( $number = 8 ) {
        $tax = self::course_taxonomy();
        if ( ! $tax ) {
            return array();
        }
        $terms = get_terms(
            array(
                'taxonomy'   => $tax,
                'hide_empty' => true,
                'number'     => $number,
                'orderby'    => 'count',
                'order'      => 'DESC',
            )
        );
        return is_wp_error( $terms ) ? array() : $terms;
    }

    public static function active_courses( $limit = 3 ) {
        $ids = array_slice( self::enrolled_ids(), 0, $limit );
        return $ids ? self::course_data( $ids ) : array();
    }
}
