<?php
/**
 * Plugin Name: WC Stock Update Form
 * Description: فرم مدیریت ورود/خروج انبار تولید + چاپ لیبل (ساده + ورییشن) با لاگ گروهی (batch)، گزارش ادمین/فرانت، گروه‌بندی pa_multi، نمایش موجودی و ID در سرچ، ثبت در جدول اختصاصی انبار تولید، و همگام‌سازی خروجی با ووکامرس یا YITH تهرانپارس.
 * Author: Sepand & Narges
 * Version: 2.5.0
 */

if ( ! defined('ABSPATH') ) exit;

/*--------------------------------------
| تنظیمات: URL وب‌اپ Google Apps Script
---------------------------------------*/
if ( ! defined('WC_SUF_GS_WEBAPP_URL') ) {
    define('WC_SUF_GS_WEBAPP_URL', 'https://script.google.com/macros/s/AKfycbxRGELbFGPSbVPrswNOxsQzxC5Epox1OLMEEH0LL38sPXcifIrd9g2fPdxWvMXzjYve/exec'); // TODO: جایگزین کن
}

/*--------------------------------------
| ثابت: Store تهرانپارس برای YITH POS
---------------------------------------*/
if ( ! defined('WC_SUF_TEHRANPARS_STORE_ID') ) {
    define('WC_SUF_TEHRANPARS_STORE_ID', 9343);
}

/*--------------------------------------
| DB: ساخت/آپدیت جدول لاگ + آماده‌سازی شمارنده‌ها
---------------------------------------*/
register_activation_hook(__FILE__, function(){
    global $wpdb;
    $table   = $wpdb->prefix.'stock_audit';
    $move_table = $wpdb->prefix.'stock_production_moves';
    $prod_table = $wpdb->prefix.'stock_production_inventory';
    $move_table = $wpdb->prefix.'stock_production_moves';
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH.'wp-admin/includes/upgrade.php';

    $sql = "CREATE TABLE `$table` (
      `id` BIGINT UNSIGNED AUTO_INCREMENT,
      `batch_code`   VARCHAR(64) NULL,
      `op_type`      VARCHAR(20) NULL,            -- in / out / onlyLabel / out_teh / in_teh
      `purpose`      TEXT NULL,                   -- برای out/out_teh/in_teh
      `print_label`  TINYINT(1) DEFAULT 0,        -- ارسال برای چاپ
      `product_id`   BIGINT UNSIGNED NOT NULL,
      `product_name` TEXT NULL,
      `old_qty`      DECIMAL(20,4) NULL,
      `added_qty`    DECIMAL(20,4) NULL,          -- مقدار تغییر (برای onlyLabel صفر)
      `new_qty`      DECIMAL(20,4) NULL,
      `user_id`      BIGINT UNSIGNED NULL,
      `user_login`   VARCHAR(60) NULL,
      `user_code`    VARCHAR(128) NULL,
      `ip`           VARCHAR(64) NULL,
      `created_at`   DATETIME NOT NULL,
      PRIMARY KEY (`id`),
      KEY `batch_code` (`batch_code`),
      KEY `product_id` (`product_id`),
      KEY `created_at` (`created_at`),
      KEY `user_id`    (`user_id`),
      KEY `user_code`  (`user_code`)
    ) $charset;";

    dbDelta($sql);

    $sql_prod = "CREATE TABLE `$prod_table` (
      `product_id` BIGINT UNSIGNED NOT NULL,
      `product_name` TEXT NULL,
      `sku` VARCHAR(191) NULL,
      `product_type` VARCHAR(40) NULL,
      `parent_id` BIGINT UNSIGNED NULL,
      `attributes_text` TEXT NULL,
      `qty` DECIMAL(20,4) NOT NULL DEFAULT 0,
      `updated_at` DATETIME NOT NULL,
      PRIMARY KEY (`product_id`),
      KEY `updated_at` (`updated_at`)
    ) $charset;";
    dbDelta($sql_prod);

    $sql_moves = "CREATE TABLE `$move_table` (
      `id` BIGINT UNSIGNED AUTO_INCREMENT,
      `batch_code` VARCHAR(64) NULL,
      `operation` VARCHAR(20) NOT NULL,
      `destination` VARCHAR(40) NULL,
      `product_id` BIGINT UNSIGNED NOT NULL,
      `product_name` TEXT NULL,
      `sku` VARCHAR(191) NULL,
      `product_type` VARCHAR(40) NULL,
      `parent_id` BIGINT UNSIGNED NULL,
      `attributes_text` TEXT NULL,
      `old_qty` DECIMAL(20,4) NOT NULL DEFAULT 0,
      `change_qty` DECIMAL(20,4) NOT NULL DEFAULT 0,
      `new_qty` DECIMAL(20,4) NOT NULL DEFAULT 0,
      `user_id` BIGINT UNSIGNED NULL,
      `user_login` VARCHAR(60) NULL,
      `user_code` VARCHAR(128) NULL,
      `created_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`),
      KEY `product_id` (`product_id`),
      KEY `batch_code` (`batch_code`),
      KEY `operation` (`operation`),
      KEY `created_at` (`created_at`)
    ) $charset;";
    dbDelta($sql_moves);
    add_option('wc_suf_db_version', '2.5.0');

    if ( get_option('wc_suf_counter_in', null) === null )        add_option('wc_suf_counter_in',  '0', '', false);
    if ( get_option('wc_suf_counter_out', null) === null )       add_option('wc_suf_counter_out', '0', '', false);
    if ( get_option('wc_suf_counter_label', null) === null )     add_option('wc_suf_counter_label', '0', '', false);

    $max_in    = $wpdb->get_var( "SELECT MAX(CAST(SUBSTRING_INDEX(batch_code, '_', -1) AS UNSIGNED)) FROM `$table` WHERE batch_code LIKE 'in\_%'" );
    $max_out   = $wpdb->get_var( "SELECT MAX(CAST(SUBSTRING_INDEX(batch_code, '_', -1) AS UNSIGNED)) FROM `$table` WHERE batch_code LIKE 'out\_%'" );
    $max_label = $wpdb->get_var( "SELECT MAX(CAST(SUBSTRING_INDEX(batch_code, '_', -1) AS UNSIGNED)) FROM `$table` WHERE batch_code LIKE 'onlyLabel\_%'" );

    if ( is_numeric($max_in) && (int)$max_in > (int)get_option('wc_suf_counter_in', '0') )           update_option('wc_suf_counter_in', (string) (int)$max_in );
    if ( is_numeric($max_out) && (int)$max_out > (int)get_option('wc_suf_counter_out', '0') )        update_option('wc_suf_counter_out', (string) (int)$max_out );
    if ( is_numeric($max_label) && (int)$max_label > (int)get_option('wc_suf_counter_label', '0') )  update_option('wc_suf_counter_label', (string) (int)$max_label );
});
add_action('plugins_loaded', function(){ wc_suf_maybe_upgrade_schema(); });

function wc_suf_maybe_upgrade_schema(){
    global $wpdb;
    $table = $wpdb->prefix.'stock_audit';
    $exists = $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s", $table
    ) );

    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH.'wp-admin/includes/upgrade.php';

    $prod_table = $wpdb->prefix.'stock_production_inventory';
    $move_table = $wpdb->prefix.'stock_production_moves';

    dbDelta("CREATE TABLE `$prod_table` (
      `product_id` BIGINT UNSIGNED NOT NULL,
      `product_name` TEXT NULL,
      `sku` VARCHAR(191) NULL,
      `product_type` VARCHAR(40) NULL,
      `parent_id` BIGINT UNSIGNED NULL,
      `attributes_text` TEXT NULL,
      `qty` DECIMAL(20,4) NOT NULL DEFAULT 0,
      `updated_at` DATETIME NOT NULL,
      PRIMARY KEY (`product_id`),
      KEY `updated_at` (`updated_at`)
    ) $charset;");

    dbDelta("CREATE TABLE `$move_table` (
      `id` BIGINT UNSIGNED AUTO_INCREMENT,
      `batch_code` VARCHAR(64) NULL,
      `operation` VARCHAR(20) NOT NULL,
      `destination` VARCHAR(40) NULL,
      `product_id` BIGINT UNSIGNED NOT NULL,
      `product_name` TEXT NULL,
      `sku` VARCHAR(191) NULL,
      `product_type` VARCHAR(40) NULL,
      `parent_id` BIGINT UNSIGNED NULL,
      `attributes_text` TEXT NULL,
      `old_qty` DECIMAL(20,4) NOT NULL DEFAULT 0,
      `change_qty` DECIMAL(20,4) NOT NULL DEFAULT 0,
      `new_qty` DECIMAL(20,4) NOT NULL DEFAULT 0,
      `user_id` BIGINT UNSIGNED NULL,
      `user_login` VARCHAR(60) NULL,
      `user_code` VARCHAR(128) NULL,
      `created_at` DATETIME NOT NULL,
      PRIMARY KEY (`id`),
      KEY `product_id` (`product_id`),
      KEY `batch_code` (`batch_code`),
      KEY `operation` (`operation`),
      KEY `created_at` (`created_at`)
    ) $charset;");

    if ( ! $exists ) return;

    $needed = [
        'batch_code'  => "ADD COLUMN `batch_code` VARCHAR(64) NULL AFTER `id`",
        'op_type'     => "ADD COLUMN `op_type` VARCHAR(20) NULL AFTER `batch_code`",
        'purpose'     => "ADD COLUMN `purpose` TEXT NULL AFTER `op_type`",
        'print_label' => "ADD COLUMN `print_label` TINYINT(1) DEFAULT 0 AFTER `purpose`",
        'product_name'=> "ADD COLUMN `product_name` TEXT NULL AFTER `product_id`",
        'old_qty'     => "ADD COLUMN `old_qty` DECIMAL(20,4) NULL AFTER `product_name`",
        'added_qty'   => "ADD COLUMN `added_qty` DECIMAL(20,4) NULL AFTER `old_qty`",
        'new_qty'     => "ADD COLUMN `new_qty` DECIMAL(20,4) NULL AFTER `added_qty`",
        'user_id'     => "ADD COLUMN `user_id` BIGINT UNSIGNED NULL AFTER `new_qty`",
        'user_login'  => "ADD COLUMN `user_login` VARCHAR(60) NULL AFTER `user_id`",
        'user_code'   => "ADD COLUMN `user_code` VARCHAR(128) NULL AFTER `user_login`",
        'ip'          => "ADD COLUMN `ip` VARCHAR(64) NULL AFTER `user_code`",
        'created_at'  => "ADD COLUMN `created_at` DATETIME NOT NULL AFTER `ip`",
    ];
    $missing = [];
    foreach($needed as $col => $ddl){
        $has = $wpdb->get_var( $wpdb->prepare("SHOW COLUMNS FROM `$table` LIKE %s", $col) );
        if ( ! $has ) $missing[] = $ddl;
    }
    if ( $missing ){
        $sql = "ALTER TABLE `$table` " . implode(", ", $missing) . ";";
        $wpdb->query($sql);
    }
    $indexes = [
        'batch_code' => "ADD KEY `batch_code` (`batch_code`)",
        'product_id' => "ADD KEY `product_id` (`product_id`)",
        'created_at' => "ADD KEY `created_at` (`created_at`)",
        'user_id'    => "ADD KEY `user_id` (`user_id`)",
        'user_code'  => "ADD KEY `user_code` (`user_code`)",
    ];
    foreach($indexes as $iname => $add){
        $hasIdx = $wpdb->get_var( $wpdb->prepare("SHOW INDEX FROM `$table` WHERE Key_name = %s", $iname) );
        if ( ! $hasIdx ){ $wpdb->query("ALTER TABLE `$table` $add"); }
    }

    if ( get_option('wc_suf_counter_in', null) === null )      add_option('wc_suf_counter_in',  '0', '', false);
    if ( get_option('wc_suf_counter_out', null) === null )     add_option('wc_suf_counter_out', '0', '', false);
    if ( get_option('wc_suf_counter_label', null) === null )   add_option('wc_suf_counter_label', '0', '', false);
}

/*--------------------------------------
| Helpers
---------------------------------------*/
function wc_suf_normalize_digits($s){
    $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $ar = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $en = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($ar, $en, str_replace($fa, $en, $s));
}
function wc_suf_capacity_from_product($product){
    $cap = 0;
    if ( $product->is_type('variation') ) {
        $slug = $product->get_meta('attribute_pa_multi', true);
        if ($slug !== '') {
            $slug = wc_suf_normalize_digits($slug);
            if (preg_match('/(\d+)/', $slug, $m)) $cap = intval($m[1]);
        }
        if (!$cap) {
            $val = $product->get_attribute('pa_multi');
            if ($val !== '') {
                $val = wc_suf_normalize_digits($val);
                if (preg_match('/(\d+)/', $val, $m)) $cap = intval($m[1]);
            }
        }
    } else {
        $val = $product->get_attribute('pa_multi');
        if ($val !== '') {
            $val = wc_suf_normalize_digits($val);
            if (preg_match('/(\d+)/', $val, $m)) $cap = intval($m[1]);
        }
    }
    return $cap > 0 ? $cap : 0;
}
function wc_suf_full_product_label( $product ){
    if ( ! $product ) return '';
    if ( $product->is_type('variation') ) {
        $parent = wc_get_product( $product->get_parent_id() );
        $base   = $parent ? $parent->get_name() : ('Variation #'.$product->get_id());
        $attrs  = wc_get_formatted_variation( $product, true, false, false );
        $attrs  = trim( wp_strip_all_tags( (string) $attrs ) );
        $label  = trim( $base . ( $attrs ? ' – ' . $attrs : '' ) );
        return $label !== '' ? $label : ( $product->get_name() ?: ('#'.$product->get_id()) );
    }
    $name = $product->get_name();
    return $name !== '' ? $name : ('#'.$product->get_id());
}

/**
 * ساخت رشتهٔ جستجو برای پاپ‌آپ:
 * - نام والد + نام/ویژگی‌های ورییشن (هم slug و هم نام ترم)
 * - pa_multi (هم meta و هم attribute) برای سرچ‌هایی مثل «۶ نفره»
 * - نرمال‌سازی اعداد فارسی/عربی به انگلیسی جهت یکسان‌سازی
 */
function wc_suf_build_search_blob( $product ) {
    if ( ! $product || ! is_object( $product ) ) return '';

    $parts = [];

    $name = $product->get_name();
    if ( is_string( $name ) && $name !== '' ) $parts[] = $name;

    if ( $product->is_type('variation') ) {
        $parent = wc_get_product( $product->get_parent_id() );
        if ( $parent ) {
            $pname = $parent->get_name();
            if ( is_string( $pname ) && $pname !== '' ) $parts[] = $pname;
        }

        $formatted = wc_get_formatted_variation( $product, true, false, false );
        $formatted = trim( wp_strip_all_tags( (string) $formatted ) );
        if ( $formatted !== '' ) $parts[] = $formatted;

        $va = $product->get_variation_attributes();
        if ( is_array( $va ) ) {
            foreach ( $va as $k => $v ) {
                $k = (string) $k;
                $v = (string) $v;
                if ( $k !== '' ) $parts[] = $k;
                if ( $v !== '' ) $parts[] = $v;

                $tax = str_replace( 'attribute_', '', $k );
                if ( $tax && taxonomy_exists( $tax ) && $v !== '' ) {
                    $term = get_term_by( 'slug', $v, $tax );
                    if ( $term && ! is_wp_error( $term ) && isset( $term->name ) ) {
                        $parts[] = (string) $term->name;
                    }
                }
            }
        }

        $multi_slug = $product->get_meta('attribute_pa_multi', true);
        if ( is_string( $multi_slug ) && $multi_slug !== '' ) $parts[] = $multi_slug;

        $multi_attr = $product->get_attribute('pa_multi');
        if ( is_string( $multi_attr ) && $multi_attr !== '' ) $parts[] = $multi_attr;

    } else {
        $multi_attr = $product->get_attribute('pa_multi');
        if ( is_string( $multi_attr ) && $multi_attr !== '' ) $parts[] = $multi_attr;
    }

    $blob = trim( implode( ' ', array_filter( array_map( 'strval', $parts ) ) ) );
    $blob = wc_suf_normalize_digits( $blob );

    if ( function_exists('mb_strtolower') ) {
        $blob = mb_strtolower( $blob );
    } else {
        $blob = strtolower( $blob );
    }

    return $blob;
}

/**
 * جمع‌آوری همه ویژگی‌های محصول برای فیلتر پاپ‌آپ (همه pa_* های موجود):
 * خروجی: [ 'pa_color' => ['زرشکی'], 'pa_multi' => ['6 نفره'], ... ]
 */
function wc_suf_collect_product_attributes_for_picker( $product ) {
    if ( ! $product || ! is_object( $product ) ) return [];

    $out = [];

    $attr_taxonomies = function_exists('wc_get_attribute_taxonomies') ? wc_get_attribute_taxonomies() : [];
    if ( empty($attr_taxonomies) || ! is_array($attr_taxonomies) ) return $out;

    if ( $product->is_type('variation') ) {
        $va = $product->get_variation_attributes();
        if ( is_array($va) ) {
            foreach ( $va as $k => $slug ) {
                $tax = str_replace( 'attribute_', '', (string) $k );
                $slug = (string) $slug;
                if ( $tax === '' || $slug === '' ) continue;
                if ( taxonomy_exists( $tax ) ) {
                    $term = get_term_by( 'slug', $slug, $tax );
                    if ( $term && ! is_wp_error($term) && isset($term->name) && $term->name !== '' ) {
                        $out[ $tax ][] = (string) $term->name;
                    } else {
                        $out[ $tax ][] = $slug;
                    }
                } else {
                    $out[ $tax ][] = $slug;
                }
            }
        }
    }

    foreach ( $attr_taxonomies as $a ) {
        if ( ! is_object($a) || empty($a->attribute_name) ) continue;
        $tax = 'pa_' . sanitize_title( (string) $a->attribute_name );
        if ( ! taxonomy_exists( $tax ) ) continue;

        $val = $product->get_attribute( $tax );
        if ( ! is_string($val) || $val === '' ) continue;

        $pieces = array_map('trim', explode(',', $val));
        $pieces = array_values(array_filter($pieces, function($x){ return $x !== ''; }));
        if ( empty($pieces) ) continue;

        foreach ( $pieces as $p ) {
            $out[ $tax ][] = $p;
        }
    }

    foreach ( $out as $tax => $vals ) {
        $clean = [];
        foreach ( (array) $vals as $v ) {
            $v = trim( (string) $v );
            if ( $v === '' ) continue;
            $clean[] = $v;
        }
        $clean = array_values( array_unique( $clean ) );
        if ( empty($clean) ) {
            unset($out[$tax]);
        } else {
            $out[$tax] = $clean;
        }
    }

    return $out;
}

/**
 * تعریف ویژگی‌ها برای UI پاپ‌آپ: همه ویژگی‌های global (pa_*)
 * خروجی: [ ['tax'=>'pa_multi','label'=>'چند نفره'], ... ]
 */
function wc_suf_get_picker_attribute_defs() {
    $defs = [];
    $attr_taxonomies = function_exists('wc_get_attribute_taxonomies') ? wc_get_attribute_taxonomies() : [];
    if ( empty($attr_taxonomies) || ! is_array($attr_taxonomies) ) return $defs;

    foreach ( $attr_taxonomies as $a ) {
        if ( ! is_object($a) || empty($a->attribute_name) ) continue;
        $tax = 'pa_' . sanitize_title( (string) $a->attribute_name );
        if ( ! taxonomy_exists( $tax ) ) continue;
        $defs[] = [
            'tax'   => $tax,
            'label' => function_exists('wc_attribute_label') ? wc_attribute_label( $tax ) : $tax,
        ];
    }

    return $defs;
}

/*--------------------------------------
| YITH POS multistock helpers
---------------------------------------*/
/*--------------------------------------
| YITH POS multistock helpers (fixed)
---------------------------------------*/
function wc_suf_get_stock_product( $product ) {
    if ( ! $product ) return $product;
    $managed_id = method_exists( $product, 'get_stock_managed_by_id' ) ? $product->get_stock_managed_by_id() : $product->get_id();
    if ( $managed_id && $managed_id !== $product->get_id() ) {
        $managed = wc_get_product( $managed_id );
        if ( $managed ) return $managed;
    }
    return $product;
}

/**
 * خواندن متای multi-stock و تبدیل مطمئن به آرایه تمیز
 * - اگر رشتهٔ JSON باشد decode می‌شود
 * - اگر مقدار تودرتو هم JSON باشد merge می‌شود
 * - همه کلیدها/مقادیر به int تبدیل می‌شوند
 */
function wc_suf_yith_parse_multistock_meta( $product ) {
    $raw = $product->get_meta( '_yith_pos_multistock' );

    if ( is_string( $raw ) && $raw !== '' ) {
        $decoded = json_decode( $raw, true );
        $multi   = is_array( $decoded ) ? $decoded : [];
    } elseif ( is_array( $raw ) ) {
        $multi = $raw;
    } else {
        $multi = [];
    }

    $clean = [];
    foreach ( $multi as $k => $v ) {
        // اگر مقدار خودش JSON باشد (مثل حالت خراب قبلی)، بازش کن و merge کن
        if ( is_string( $v ) ) {
            $decoded_v = json_decode( $v, true );
            if ( is_array( $decoded_v ) ) {
                foreach ( $decoded_v as $dk => $dv ) {
                    $clean[ (int) $dk ] = (int) $dv;
                }
                continue;
            }
        }
        $clean[ (int) $k ] = (int) $v;
    }

    return $clean;
}

function wc_suf_yith_prepare_store_stock( $product, $store_id ) {
    if ( ! function_exists( 'yith_pos_stock_management' ) ) {
        return new WP_Error( 'yith_missing', 'YITH POS فعال نیست.' );
    }
    $product = wc_suf_get_stock_product( $product );

    $multi   = wc_suf_yith_parse_multistock_meta( $product );
    $enabled = $product->get_meta( '_yith_pos_multistock_enabled' );

  if ( ! isset( $multi[ $store_id ] ) ) {
    $multi[ $store_id ] = 0; // استارت از صفر؛ فقط مقادیر انتقالی اضافه می‌شود
    }
    if ( 'yes' !== $enabled ) {
        $product->update_meta_data( '_yith_pos_multistock_enabled', 'yes' );
    }
    $product->update_meta_data( '_yith_pos_multistock', $multi );
    $product->save();

    return true;
}

function wc_suf_yith_get_store_stock( $product, $store_id ) {
    if ( ! function_exists( 'yith_pos_stock_management' ) ) return false;
    $product = wc_suf_get_stock_product( $product );
    $product->update_meta_data( '_yith_pos_multistock', wc_suf_yith_parse_multistock_meta( $product ) );
    $product->save();

    $manager = yith_pos_stock_management();
    return $manager->get_stock_amount( $product, $store_id );
}

function wc_suf_yith_change_store_stock( $product, $qty, $store_id, $operation = 'increase' ) {
    if ( ! function_exists( 'yith_pos_stock_management' ) ) {
        return new WP_Error( 'yith_missing', 'YITH POS فعال نیست.' );
    }
    $product = wc_suf_get_stock_product( $product );
    $prep    = wc_suf_yith_prepare_store_stock( $product, $store_id );
    if ( is_wp_error( $prep ) ) return $prep;

    // پس از عادی‌سازی متا، update_product_stock روی آرایهٔ تمیز کار می‌کند
    $manager = yith_pos_stock_management();
    $current = $manager->get_stock_amount( $product, $store_id );
    if ( false === $current ) $current = 0;

    return $manager->update_product_stock( $product, absint( $qty ), $store_id, (int) $current, $operation );
}


/*--------------------------------------
| Helpers (برچسب عملیات و کد ثبت ترتیبی)
---------------------------------------*/
function wc_suf_op_label($op){
    if ($op === 'out_main') return 'خروج به انبار اصلی';
    if ($op === 'out_teh')  return 'خروج به تهرانپارس';
    if ($op === 'out')      return 'خروج';
    if ($op === 'in')       return 'ورود';
    if ($op === 'onlyLabel') return 'فقط لیبل';
    return $op;
}

function wc_suf_get_product_attributes_text( $product ) {
    if ( ! $product || ! is_object( $product ) ) return '';
    $txt = wc_get_formatted_variation( $product, true, false, false );
    $txt = trim( wp_strip_all_tags( (string) $txt ) );
    return $txt;
}

function wc_suf_get_production_stock_qty( $product_id ) {
    global $wpdb;
    $table = $wpdb->prefix.'stock_production_inventory';
    $qty = $wpdb->get_var( $wpdb->prepare("SELECT qty FROM `$table` WHERE product_id = %d", absint($product_id) ) );
    return (int) ($qty ?? 0);
}

function wc_suf_ensure_production_inventory_row( $product ) {
    global $wpdb;
    $table = $wpdb->prefix.'stock_production_inventory';
    $pid   = absint( $product->get_id() );
    if ( ! $pid ) return;

    $wpdb->query( $wpdb->prepare(
        "INSERT IGNORE INTO `$table` (`product_id`,`product_name`,`sku`,`product_type`,`parent_id`,`attributes_text`,`qty`,`updated_at`) VALUES (%d,%s,%s,%s,%d,%s,%f,%s)",
        $pid,
        wc_suf_full_product_label( $product ),
        $product->get_sku() ?: null,
        $product->get_type(),
        $product->is_type('variation') ? $product->get_parent_id() : null,
        wc_suf_get_product_attributes_text( $product ),
        0,
        current_time('mysql')
    ) );
}

function wc_suf_get_production_stock_qty_for_update( $product ) {
    global $wpdb;
    $table = $wpdb->prefix.'stock_production_inventory';
    $pid   = absint( $product->get_id() );
    if ( ! $pid ) return 0;

    wc_suf_ensure_production_inventory_row( $product );
    $qty = $wpdb->get_var( $wpdb->prepare(
        "SELECT qty FROM `$table` WHERE product_id = %d FOR UPDATE",
        $pid
    ) );

    return (int) ($qty ?? 0);
}

function wc_suf_set_production_stock_qty( $product, $new_qty ) {
    global $wpdb;
    $table = $wpdb->prefix.'stock_production_inventory';
    $pid   = absint( $product->get_id() );
    if ( ! $pid ) return;

    $data = [
        'product_name'     => wc_suf_full_product_label( $product ),
        'sku'              => $product->get_sku() ?: null,
        'product_type'     => $product->get_type(),
        'parent_id'        => $product->is_type('variation') ? $product->get_parent_id() : null,
        'attributes_text'  => wc_suf_get_product_attributes_text( $product ),
        'qty'              => max( 0, (int) $new_qty ),
        'updated_at'       => current_time('mysql'),
    ];

    $wpdb->update( $table, $data, [ 'product_id' => $pid ], [ '%s','%s','%s','%d','%s','%f','%s' ], [ '%d' ] );
}

function wc_suf_update_production_stock_qty( $product, $delta ) {
    global $wpdb;
    $table = $wpdb->prefix.'stock_production_inventory';

    $pid = absint( $product->get_id() );
    $current = wc_suf_get_production_stock_qty( $pid );
    $new = max( 0, $current + (int) $delta );

    $data = [
        'product_id'       => $pid,
        'product_name'     => wc_suf_full_product_label( $product ),
        'sku'              => $product->get_sku() ?: null,
        'product_type'     => $product->get_type(),
        'parent_id'        => $product->is_type('variation') ? $product->get_parent_id() : null,
        'attributes_text'  => wc_suf_get_product_attributes_text( $product ),
        'qty'              => $new,
        'updated_at'       => current_time('mysql'),
    ];

    $exists = (int) $wpdb->get_var( $wpdb->prepare("SELECT COUNT(*) FROM `$table` WHERE product_id = %d", $pid ) );
    if ( $exists > 0 ) {
        $wpdb->update( $table, $data, [ 'product_id' => $pid ], [ '%d','%s','%s','%s','%d','%s','%f','%s' ], [ '%d' ] );
    } else {
        $wpdb->insert( $table, $data, [ '%d','%s','%s','%s','%d','%s','%f','%s' ] );
    }

    return [ $current, $new ];
}

function wc_suf_next_batch_code( $op_type ){
    global $wpdb;

    $op_type = ($op_type === 'out') ? 'out' : ( ($op_type === 'onlyLabel') ? 'onlyLabel' : 'in' );

    $opt_map = [
        'in'        => 'wc_suf_counter_in',
        'out'       => 'wc_suf_counter_out',
        'onlyLabel' => 'wc_suf_counter_label',
    ];
    $opt_name = $opt_map[$op_type];

    if ( get_option($opt_name, null) === null ) {
        add_option($opt_name, '0', '', false);
    }

    $current_val = get_option($opt_name, '0');
    if ( ! preg_match('/^\d+$/', (string) $current_val) ) {
        update_option($opt_name, '0', false);
        $current_val = '0';
    }

    $tbl = $wpdb->options;
    $wpdb->query( $wpdb->prepare(
        "UPDATE $tbl SET option_value = CAST(option_value AS UNSIGNED) + 1 WHERE option_name = %s",
        $opt_name
    ) );

    $n = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT option_value FROM $tbl WHERE option_name = %s",
        $opt_name
    ) );

    if ( $n <= 0 ) {
        $n = (int) $current_val + 1;
        update_option($opt_name, (string)$n, false);
    }

    $num = sprintf('%04d', $n);
    $prefix = ($op_type === 'onlyLabel') ? 'onlyLabel_' : ( $op_type === 'out' ? 'out_' : 'in_' );
    return $prefix . $num;
}

/*--------------------------------------
| فرانت: Select2/SelectWoo + استایل‌ها
---------------------------------------*/
function wc_suf_enqueue_front_assets() {
    wp_enqueue_script('jquery');
    $use_core_selectwoo = ( wp_script_is('selectWoo', 'registered') || wp_script_is('selectWoo', 'enqueued') );
    if ( $use_core_selectwoo ) {
        wp_enqueue_script('selectWoo');
        if ( wp_style_is('select2', 'registered') ) wp_enqueue_style('select2');
    } else {
        wp_enqueue_style('wc-suf-select2', plugins_url('assets/select2.min.css', __FILE__), [], '4.1.0');
        wp_enqueue_script('wc-suf-select2', plugins_url('assets/select2.min.js', __FILE__), ['jquery'], '4.1.0', true);
    }
    $css = '
    #sel-id, #sel-name { font-size: 18px; line-height: 1.6; padding: 6px 8px; }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        font-size: 18px !important; line-height: 1.8 !important; padding-top: 2px !important; padding-bottom: 2px !important; font-weight: 600;
    }
    .select2-results__option { font-size: 16px; }
    .select2-search--dropdown .select2-search__field { font-size: 16px; padding: 8px; }
    .select2-container .select2-results > .select2-results__options { max-height: 85vh !important; }
    .select2-container .select2-dropdown { max-height: 90vh !important; overflow: auto !important; }
    .select2-container { z-index: 999999 !important; }
    .suf-muted { color:#6b7280; font-size:12px }
    .wc-suf-modal-overlay{ position:fixed; inset:0; background:rgba(0,0,0,.55); z-index:1000000; display:none; }
    .wc-suf-modal{ position:fixed; inset:0; z-index:1000001; display:none; align-items:center; justify-content:center; padding:18px; }
    .wc-suf-modal .wc-suf-modal-card{ width:min(980px, 96vw); max-height:88vh; background:#fff; border-radius:14px; overflow:hidden; box-shadow:0 10px 30px rgba(0,0,0,.25); display:flex; flex-direction:column; }
    .wc-suf-modal .wc-suf-modal-head{ padding:12px 14px; border-bottom:1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between; gap:10px; background:#f9fafb; }
    .wc-suf-modal .wc-suf-modal-title{ font-weight:800; }
    .wc-suf-modal .wc-suf-modal-close{ border:1px solid #ef4444; background:#ef4444; color:#fff; border-radius:10px; padding:6px 10px; cursor:pointer; font-weight:900; line-height:1; }
    .wc-suf-modal .wc-suf-modal-body{ padding:12px 14px; overflow:auto; }
    .wc-suf-modal .wc-suf-modal-foot{ padding:12px 14px; border-top:1px solid #e5e7eb; display:flex; align-items:center; justify-content:space-between; gap:12px; background:#f9fafb; }
    .wc-suf-picker-row{ display:grid; grid-template-columns: 1fr 140px; gap:10px; align-items:center; padding:10px 8px; border-bottom:1px solid #f1f5f9; }
    .wc-suf-picker-row:last-child{ border-bottom:none; }
    .wc-suf-picker-name{ font-weight:600; }
    .wc-suf-picker-meta{ font-size:12px; color:#6b7280; margin-top:2px; }
    .wc-suf-picker-qty{ display:flex; align-items:center; justify-content:flex-end; gap:6px; }
    .wc-suf-picker-qty input{ width:76px; text-align:center; padding:6px; font-size:16px; border:1px solid #e5e7eb; border-radius:10px; }
    .wc-suf-picker-qty button{ padding:6px 10px; font-size:16px; cursor:pointer; border:1px solid #e5e7eb; background:#fff; border-radius:10px; }
    .wc-suf-filter-grid{ display:flex; gap:10px; align-items:center; flex-wrap:wrap; }
    .wc-suf-filter-grid .wc-suf-filter{ display:flex; gap:6px; align-items:center; }
    .wc-suf-filter-grid label{ font-weight:700; font-size:13px; color:#111827; }
    .wc-suf-filter-grid select{ padding:10px 10px; border:1px solid #e5e7eb; border-radius:12px; font-size:14px; background:#fff; min-width:190px; max-width:260px; }
    ';
    wp_register_style('wc-suf-ui', false, [], '2.4.0');
    wp_enqueue_style('wc-suf-ui');
    wp_add_inline_style('wc-suf-ui', $css);
}

/*--------------------------------------
| Shortcode: [stock_update_form key="910"]
---------------------------------------*/
add_shortcode('stock_update_form', function($atts){
    wc_suf_enqueue_front_assets();
    if ( ! function_exists('wc_get_products') ) {
        return '<div dir="rtl" style="color:#b91c1c">WooCommerce فعال نیست.</div>';
    }
    if( ! current_user_can('manage_woocommerce') && ! current_user_can('manage_options') ){
        return '<div dir="rtl" style="color:#b91c1c">دسترسی کافی برای استفاده از فرم ندارید.</div>';
    }
    $atts = shortcode_atts(['key' => ''], $atts, 'stock_update_form');

    $cache_key = 'wc_suf_products_cache_v250';
    $products = get_transient( $cache_key );
    if ( ! is_array($products) ) {
        $products = wc_get_products([
            'status' => 'publish',
            'limit' => -1,
            'type' => ['simple','variation'],
            'return' => 'objects',
        ]);
        set_transient( $cache_key, $products, MINUTE_IN_SECONDS * 5 );
    }

    $make_label = function( $p ){
        if ( $p->is_type('variation') ) {
            $parent = wc_get_product( $p->get_parent_id() );
            $base   = $parent ? $parent->get_name() : ('Variation #'.$p->get_id());
            $attrs  = wc_get_formatted_variation( $p, true, false, false );
            $attrs  = trim( wp_strip_all_tags( (string) $attrs ) );
            $label  = trim( $base . ( $attrs ? ' – ' . $attrs : '' ) );
            if ( $label === '' ) $label = $p->get_name() ?: ('#'.$p->get_id());
            return $label;
        }
        $name = $p->get_name();
        return $name !== '' ? $name : ('#'.$p->get_id());
    };

    $picker_attr_defs = wc_suf_get_picker_attribute_defs();

    global $wpdb;
    $prod_table = $wpdb->prefix.'stock_production_inventory';
    $prod_rows = $wpdb->get_results( "SELECT product_id, qty FROM `$prod_table`", ARRAY_A );
    $prod_map = [];
    if ( is_array($prod_rows) ) {
        foreach ( $prod_rows as $pr ) {
            $ppid = isset($pr['product_id']) ? absint($pr['product_id']) : 0;
            if ( ! $ppid ) continue;
            $prod_map[$ppid] = (int) ($pr['qty'] ?? 0);
        }
    }

    $bucketed = [];
    $preferred_order = [4,6,8,12];
    foreach ($products as $p){
        $pid = $p->get_id();
        $wc_stock   = (int) max(0, (int) ($p->get_stock_quantity() ?? 0));
        $prod_stock = isset($prod_map[$pid]) ? (int) $prod_map[$pid] : 0;
        $teh_stock  = 0;
        $teh_ok     = 0;

        if ( function_exists('yith_pos_stock_management') ) {
            $teh_read = wc_suf_yith_get_store_stock( $p, (int) WC_SUF_TEHRANPARS_STORE_ID );
            if ( false !== $teh_read ) {
                $teh_stock = (int) $teh_read;
                $teh_ok = 1;
            }
        }

        $label = $make_label($p);
        $row = [
            'id'           => $pid,
            'label'        => $label,
            'stock'        => $prod_stock,
            'prod_stock'   => $prod_stock,
            'wc_stock'     => $wc_stock,
            'teh_stock'    => $teh_stock,
            'teh_stock_ok' => $teh_ok,
            'search'       => wc_suf_build_search_blob( $p ),
            'attrs'        => wc_suf_collect_product_attributes_for_picker( $p ),
        ];
        $cap = wc_suf_capacity_from_product($p) ?: 0;
        $bucketed[$cap][] = $row;
    }
    foreach ($bucketed as $cap => &$list) { usort($list, fn($a,$b)=> strcasecmp($a['label'],$b['label'])); } unset($list);

    $name_select_html = '';
    $id_select_html   = '';
    $all              = [];

    $emit_group = function($cap, $rows) use (&$name_select_html,&$id_select_html,&$all){
        $group_label = ($cap ? $cap.' نفره' : 'سایر');
        $name_select_html .= '<optgroup label="'.esc_attr($group_label).'">';
        $id_select_html   .= '<optgroup label="'.esc_attr($group_label).'">';
        foreach ($rows as $row) {
            $opt_text = '['.esc_html($row['id']).' | موجودی: '.esc_html($row['stock']).'] '.esc_html($row['label']);
            $name_select_html .= '<option value="'.esc_attr($row['id']).'" data-stock="'.esc_attr($row['stock']).'">'. $opt_text .'</option>';
            $id_select_html   .= '<option value="'.esc_attr($row['id']).'" data-stock="'.esc_attr($row['stock']).'">'. esc_html($row['id']).'</option>';
            $all[] = $row;
        }
        $name_select_html .= '</optgroup>';
        $id_select_html   .= '</optgroup>';
    };

    foreach ($preferred_order as $cap) if (!empty($bucketed[$cap])) $emit_group($cap, $bucketed[$cap]);
    $other_caps = array_diff(array_keys($bucketed), array_merge($preferred_order, [0]));
    sort($other_caps, SORT_NUMERIC);
    foreach ($other_caps as $cap) $emit_group($cap, $bucketed[$cap]);
    if (!empty($bucketed[0])) $emit_group(0, $bucketed[0]);

    ob_start(); ?>
    <div id="stock-form" dir="rtl" style="display:grid; gap:12px; align-items:center;">

        <div id="optype-block" style="display:flex; gap:16px; align-items:center; flex-wrap:wrap; background:#f9fafb; padding:10px 12px; border:1px solid #e5e7eb; border-radius:8px">
          <div style="font-weight:700; min-width:220px">نوع عملیات موجودی / لیبل</div>
          <label style="display:flex; align-items:center; gap:6px; cursor:pointer">
            <input type="radio" name="op-type" value="in">
            <span>ورود به انبار تولید</span>
          </label>
          <label style="display:flex; align-items:center; gap:6px; cursor:pointer">
            <input type="radio" name="op-type" value="out">
            <span>خروج از انبار</span>
          </label>
          <label style="display:flex; align-items:center; gap:6px; cursor:pointer">
            <input type="radio" name="op-type" value="onlyLabel">
            <span>صرفاً چاپ لیبل</span>
          </label>
          <span class="suf-muted">(پس از انتخاب، قابل تغییر نیست مگر با رفرش)</span>
        </div>

            
        <div id="out-destination-wrap" style="display:none; gap:8px; align-items:center; flex-wrap:wrap">
          <label style="min-width:120px">مقصد خروج:</label>
          <label style="display:flex; align-items:center; gap:6px; cursor:pointer">
            <input type="radio" name="out-destination" value="main">
            <span>خروج به انبار اصلی</span>
          </label>
          <label style="display:flex; align-items:center; gap:6px; cursor:pointer">
            <input type="radio" name="out-destination" value="teh">
            <span>خروج به انبار تهران پارس</span>
          </label>
        </div>
        <div style="display:flex; gap:12px; align-items:center; flex-wrap:wrap; opacity:.5" id="picker-open-block">
          <button type="button" id="btn-open-picker" style="padding:12px 18px; cursor:pointer; border:1px solid #10b981; border-radius:10px; background:#bbf7d0; color:#065f46; font-weight:700" disabled>➕ اضافه کردن محصولات</button>
          <span class="suf-muted">ابتدا نوع عملیات را انتخاب کنید، سپس محصولات را در پنجره انتخاب کنید.</span>
        </div>

        <table id="items-table" style="margin-top:10px; display:none; width:100%; border-collapse:collapse; border:1px solid #e5e7eb; font-size:14px">
          <thead>
            <tr style="background:#f3f4f6; border-bottom:1px solid #e5e7eb">
              <th style="padding:8px; text-align:right; width:110px">ID</th>
              <th style="padding:8px; text-align:right">محصول</th>
              <th style="padding:8px; text-align:center; width:140px">موجودی فعلی</th>
              <th style="padding:8px; text-align:center; width:280px">تعداد (+/−)</th>
              <th style="padding:8px; text-align:center; width:100px">حذف</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>

        <div>
          <button type="button" id="btn-save" style="margin-top:10px; display:none; padding:12px 18px; cursor:pointer; border:1px solid #2563eb; background:#2563eb; color:#fff; border-radius:10px" disabled>✅ ثبت نهایی</button>
        </div>
    </div>

    <div class="wc-suf-modal-overlay" id="wc-suf-modal-overlay" aria-hidden="true"></div>
    <div class="wc-suf-modal" id="wc-suf-modal" aria-hidden="true" role="dialog" aria-modal="true">
      <div class="wc-suf-modal-card">
        <div class="wc-suf-modal-head">
          <div>
            <div class="wc-suf-modal-title">انتخاب محصولات (جستجو + فیلتر ویژگی‌ها)</div>
            <div class="suf-muted" id="wc-suf-modal-subtitle">ابتدا جستجو کنید، سپس در صورت نیاز از فیلتر ویژگی‌ها استفاده کنید. برای هر محصول تعداد را وارد کنید و «اضافه کن» را بزنید.</div>
          </div>
          <button type="button" class="wc-suf-modal-close" id="wc-suf-modal-close" aria-label="بستن">✕</button>
        </div>

        <div class="wc-suf-modal-body">
          <div style="display:flex; gap:10px; align-items:flex-start; flex-wrap:wrap; margin-bottom:10px">
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; flex:1; min-width:260px">
              <label for="wc-suf-picker-q" style="min-width:80px; font-weight:700">جستجو:</label>
              <input id="wc-suf-picker-q" type="text" placeholder="مثلاً توران / توران ۶ نفره / زرشکی" style="flex:1; min-width:260px; padding:10px; border:1px solid #e5e7eb; border-radius:12px; font-size:16px">
              <button type="button" id="wc-suf-picker-clear" aria-label="پاک کردن جستجو" title="پاک کردن" style="width:44px; height:44px; display:inline-flex; align-items:center; justify-content:center; padding:0; border:1px solid #2563eb; background:#2563eb; color:#fff; border-radius:12px; cursor:pointer; font-size:18px; font-weight:800">✕</button>
            </div>

            <div style="width:100%; margin-top:8px">
              <div id="wc-suf-picker-filters" class="wc-suf-filter-grid"></div>
            </div>
          </div>

          <div class="suf-muted" style="margin-bottom:8px">
            نکته: جستجو نام + ورییشن (ویژگی‌ها) را پوشش می‌دهد. فیلترها همزمان با جستجو اعمال می‌شوند.
          </div>

          <div id="wc-suf-picker-results" style="border:1px solid #e5e7eb; border-radius:12px; overflow:hidden"></div>
        </div>

        <div class="wc-suf-modal-foot">
          <div class="suf-muted" id="wc-suf-picker-selected-info">هیچ موردی انتخاب نشده است.</div>
          <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap">
            <button type="button" id="wc-suf-picker-add" style="padding:12px 16px; cursor:pointer; border:1px solid #10b981; border-radius:12px; background:#10b981; color:#fff; font-weight:800">✅ اضافه کن</button>
          </div>
        </div>
      </div>
    </div>

    <script>
    const ajaxurl = "<?php echo esc_js( admin_url('admin-ajax.php') ); ?>";

    jQuery(function($){
        const allProducts = <?php echo wp_json_encode($all); ?>;
        const pickerAttrDefs = <?php echo wp_json_encode($picker_attr_defs); ?>;

        const defaultShortcodeKey = "<?php echo esc_js($atts['key']); ?>";
        const urlParams = new URLSearchParams(window.location.search);
        const urlKey = urlParams.get('key') || urlParams.get('code') || '';
        const userCode = urlKey || defaultShortcodeKey;

        const items = [];
        let opType = null;
        let outDestination = null;

        const $overlay = $('#wc-suf-modal-overlay');
        const $modal   = $('#wc-suf-modal');
        const $q       = $('#wc-suf-picker-q');
        const $results = $('#wc-suf-picker-results');
        const $info    = $('#wc-suf-picker-selected-info');
        const $filters = $('#wc-suf-picker-filters');

        const pickerQty = Object.create(null);
        const activeFilters = Object.create(null); // tax => selectedValueNormalized

        function escapeHtml(s){
            return String(s)
                .replaceAll('&','&amp;')
                .replaceAll('<','&lt;')
                .replaceAll('>','&gt;')
                .replaceAll('"','&quot;')
                .replaceAll("'","&#039;");
        }

        function norm(s){
            s = String(s || '').trim();
            s = s.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d));
            s = s.replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
            return s.toLowerCase();
        }

        function findById(id){ return allProducts.find(p => String(p.id) === String(id)); }
        function findLabelById(id){ const f = findById(id); return f ? f.label : ''; }
        function findProductionStockById(id){ const f = findById(id); return f ? (+f.prod_stock || 0) : 0; }
        function getPickerMetaLine(p){
            const pid = String(p.id || '');
            const prod = (+p.prod_stock || 0);
            if(opType === 'in'){
                return `ID: ${pid} | موجودی انبار تولید: ${prod}`;
            }
            if(opType === 'out'){
                if(outDestination === 'main'){
                    return `ID: ${pid} | موجودی انبار تولید: ${prod} | موجودی انبار اصلی: ${(+p.wc_stock || 0)}`;
                }
                if(outDestination === 'teh'){
                    const teh = (+p.teh_stock || 0);
                    const note = (+p.teh_stock_ok || 0) ? '' : ' (نامشخص)';
                    return `ID: ${pid} | موجودی انبار تولید: ${prod} | موجودی تهران پارس: ${teh}${note}`;
                }
                return `ID: ${pid} | موجودی انبار تولید: ${prod}`;
            }
            return `ID: ${pid} | موجودی انبار تولید: ${prod}`;
        }

        function canSave(){
            if(opType !== 'in' && opType !== 'out' && opType !== 'onlyLabel') return false;
            if(opType === 'out' && !outDestination) return false;
            return items.length > 0;
        }

        function canOpenPicker(){
            if(!opType) return false;
            if(opType === 'out' && !outDestination) return false;
            return true;
        }

        function getDestinationInfoById(id){
            const p = findById(id);
            if(!p) return {label:'', stock:0};
            if(outDestination === 'main'){
                return {label:'موجودی انبار اصلی', stock:(+p.wc_stock || 0)};
            }
            if(outDestination === 'teh'){
                return {label:'موجودی انبار تهران‌پارس', stock:(+p.teh_stock || 0)};
            }
            return {label:'', stock:0};
        }

        function refreshPickerOpenButton(){
            const enabled = canOpenPicker();
            const $btn = $('#btn-open-picker');
            $('#picker-open-block').css('opacity', enabled ? 1 : 0.5);
            $btn.prop('disabled', !enabled);
            if(enabled){
                $btn.css({background:'#16a34a', borderColor:'#15803d', color:'#ffffff'});
            } else {
                $btn.css({background:'#bbf7d0', borderColor:'#10b981', color:'#065f46'});
            }
        }

        function renderTable(){
            const tbody = $('#items-table tbody').empty();
            const theadRow = $('#items-table thead tr').empty();

            const isOutMain = (opType === 'out' && outDestination === 'main');
            const isOutTeh  = (opType === 'out' && outDestination === 'teh');

            theadRow.append('<th style="padding:8px; text-align:right; width:110px">ID</th>');
            theadRow.append('<th style="padding:8px; text-align:right">محصول</th>');
            theadRow.append('<th style="padding:8px; text-align:center; width:160px">موجودی انبار تولید</th>');
            if (isOutMain){
                theadRow.append('<th style="padding:8px; text-align:center; width:170px">موجودی انبار اصلی</th>');
            } else if (isOutTeh){
                theadRow.append('<th style="padding:8px; text-align:center; width:180px">موجودی انبار تهران‌پارس</th>');
            }
            theadRow.append('<th style="padding:8px; text-align:center; width:280px">تعداد (+/−)</th>');
            theadRow.append('<th style="padding:8px; text-align:center; width:100px">حذف</th>');

            if(items.length === 0){
                $('#items-table').hide();
                $('#btn-save').prop('disabled', true).hide();
                return;
            }
            $('#items-table').show();
            $('#btn-save').show().prop('disabled', !canSave());

            items.forEach((it,idx)=>{
                const tr = $('<tr style="border-top:1px solid #e5e7eb">');
                tr.append(`<td style="padding:8px">${escapeHtml(it.id)}</td>`);
                tr.append(`<td style="padding:8px">${escapeHtml(it.name)}</td>`);
                tr.append(`<td style="padding:8px; text-align:center">${escapeHtml(it.stock)}</td>`);

                if (isOutMain || isOutTeh){
                    const dst = getDestinationInfoById(it.id);
                    tr.append(`<td style="padding:8px; text-align:center">${escapeHtml(dst.stock)}</td>`);
                }

                const qtyControls = $(`
                  <td style="padding:6px; text-align:center">
                    <button class="row-dec" data-i="${idx}" style="font-size:18px; padding:4px 10px; margin-inline:4px">➖</button>
                    <input type="number" class="row-qty" data-i="${idx}" value="${escapeHtml(it.qty)}" min="1" style="width:80px; text-align:center; font-size:16px; padding:4px">
                    <button class="row-inc" data-i="${idx}" style="font-size:18px; padding:4px 10px; margin-inline:4px">➕</button>
                  </td>
                `);
                tr.append(qtyControls);
                tr.append(`<td style="padding:8px; text-align:center"><button data-i="${idx}" class="btn-del" style="cursor:pointer">❌</button></td>`);
                tbody.append(tr);
            });
        }

        function enforceOutLimit(idx){
            if(opType !== 'out') return;
            const it = items[idx];
            if(!it) return;
            if(it.qty > it.stock){ it.qty = it.stock; }
        }

        function openModal(){
            if(!opType) return;

            $overlay.show().attr('aria-hidden','false');
            $modal.css('display','flex').attr('aria-hidden','false');
            $('body').css('overflow','hidden');

            buildAttributeFilters();
            $q.trigger('focus');
            renderPickerResults();
        }

        function closeModal(){
            $overlay.hide().attr('aria-hidden','true');
            $modal.hide().attr('aria-hidden','true');
            $('body').css('overflow','');
            $q.val('');
            $results.empty();
            updateSelectedInfo();
        }

        function updateSelectedInfo(){
            let cnt = 0;
            let sum = 0;
            for (const k in pickerQty){
                const v = +pickerQty[k];
                if (v > 0){ cnt++; sum += v; }
            }
            if (cnt <= 0){
                $info.text('هیچ موردی انتخاب نشده است.');
            } else {
                $info.text(`تعداد محصولات انتخاب‌شده: ${cnt} | جمع تعداد: ${sum}`);
            }
        }

        function buildAttributeFilters(){
            $filters.empty();
            for (const k in activeFilters){
                if (Object.prototype.hasOwnProperty.call(activeFilters, k)) delete activeFilters[k];
            }

            if (!Array.isArray(pickerAttrDefs) || pickerAttrDefs.length === 0){
                return;
            }

            const optionsByTax = Object.create(null);

            for (let i=0; i<allProducts.length; i++){
                const p = allProducts[i];
                const attrs = p && p.attrs ? p.attrs : null;
                if (!attrs || typeof attrs !== 'object') continue;

                for (let d=0; d<pickerAttrDefs.length; d++){
                    const def = pickerAttrDefs[d];
                    if (!def || !def.tax) continue;
                    const tax = String(def.tax);

                    const vals = attrs[tax];
                    if (!Array.isArray(vals) || vals.length === 0) continue;

                    if (!optionsByTax[tax]) optionsByTax[tax] = Object.create(null);
                    for (let v=0; v<vals.length; v++){
                        const rawVal = String(vals[v] || '').trim();
                        if (!rawVal) continue;
                        const key = norm(rawVal);
                        if (!key) continue;
                        optionsByTax[tax][key] = rawVal;
                    }
                }
            }

            for (let d=0; d<pickerAttrDefs.length; d++){
                const def = pickerAttrDefs[d];
                if (!def || !def.tax) continue;

                const tax = String(def.tax);
                const label = String(def.label || tax);

                const bag = optionsByTax[tax];
                if (!bag || typeof bag !== 'object') continue;

                const keys = Object.keys(bag);
                if (keys.length === 0) continue;
                keys.sort((a,b) => a.localeCompare(b, 'fa'));

                const selectId = 'wc-suf-filter-' + tax.replace(/[^a-z0-9_]/gi,'_');

                const opts = [];
                opts.push(`<option value="">همه</option>`);
                for (let i=0; i<keys.length; i++){
                    const k = keys[i];
                    const display = bag[k];
                    opts.push(`<option value="${escapeHtml(k)}">${escapeHtml(display)}</option>`);
                }

                const html = `
                  <div class="wc-suf-filter">
                    <label for="${escapeHtml(selectId)}">${escapeHtml(label)}:</label>
                    <select id="${escapeHtml(selectId)}" data-tax="${escapeHtml(tax)}">
                      ${opts.join('')}
                    </select>
                  </div>
                `;
                $filters.append(html);
            }
        }

        function productMatchesFilters(p){
            for (const tax in activeFilters){
                if (!Object.prototype.hasOwnProperty.call(activeFilters, tax)) continue;
                const sel = String(activeFilters[tax] || '');
                if (!sel) continue;

                const attrs = p && p.attrs ? p.attrs : null;
                if (!attrs || typeof attrs !== 'object') return false;

                const vals = attrs[tax];
                if (!Array.isArray(vals) || vals.length === 0) return false;

                let ok = false;
                for (let i=0; i<vals.length; i++){
                    if (norm(vals[i]) === sel){
                        ok = true;
                        break;
                    }
                }
                if (!ok) return false;
            }
            return true;
        }

        function renderPickerResults(){
            const q = norm($q.val());
            const matched = [];

            if (q.length > 0){
                for (let i=0; i<allProducts.length; i++){
                    const p = allProducts[i];
                    const hay = String(p.search || p.label || '').toLowerCase();
                    if (!hay.includes(q)) continue;
                    if (!productMatchesFilters(p)) continue;
                    matched.push(p);
                }
            } else {
                // بدون جستجو: اگر فیلتر انتخاب شده باشد، اجازه بده نتایج بر اساس فیلتر دیده شوند
                const hasAnyFilter = Object.keys(activeFilters).some(k => (activeFilters[k] || '') !== '');
                if (hasAnyFilter){
                    for (let i=0; i<allProducts.length; i++){
                        const p = allProducts[i];
                        if (!productMatchesFilters(p)) continue;
                        matched.push(p);
                    }
                }
            }

            const showList = matched;
            if (showList.length === 0){
                const hasAnyFilter = Object.keys(activeFilters).some(k => (activeFilters[k] || '') !== '');
                const msg = (q.length || hasAnyFilter) ? 'موردی یافت نشد.' : 'برای نمایش نتایج، عبارت جستجو را وارد کنید یا یک فیلتر انتخاب کنید.';
                $results.html(`<div style="padding:14px" class="suf-muted">${escapeHtml(msg)}</div>`);
                updateSelectedInfo();
                return;
            }

            const frag = [];
            for (let i=0; i<showList.length; i++){
                const p = showList[i];
                const pid = String(p.id);
                const stock = (+p.prod_stock || 0);
                const cur = (pickerQty[pid] != null) ? +pickerQty[pid] : 0;
                const metaLine = getPickerMetaLine(p);

                frag.push(`
                    <div class="wc-suf-picker-row" data-pid="${escapeHtml(pid)}">
                      <div>
                        <div class="wc-suf-picker-name">${escapeHtml(p.label || ('#'+pid))}</div>
                        <div class="wc-suf-picker-meta">${escapeHtml(metaLine)}</div>
                      </div>
                      <div class="wc-suf-picker-qty">
                        <button type="button" class="picker-dec" data-pid="${escapeHtml(pid)}">➖</button>
                        <input type="number" min="0" class="picker-qty" data-pid="${escapeHtml(pid)}" value="${escapeHtml(cur)}" />
                        <button type="button" class="picker-inc" data-pid="${escapeHtml(pid)}">➕</button>
                      </div>
                    </div>
                `);
            }

            $results.html(frag.join(''));
            updateSelectedInfo();
        }

        function capQtyForOut(pid, qty, showAlert){
            if(opType !== 'out') return qty;
            const stock = findProductionStockById(pid);
            if (qty > stock){
                if (showAlert){
                    const name = findLabelById(pid) || ('#'+pid);
                    alert(`برای "${name}" حداکثر قابل انتخاب ${stock} عدد است (موجودی انبار تولید).`);
                }
                return stock;
            }
            return qty;
        }

        refreshPickerOpenButton();

        $('#btn-open-picker').on('click', function(){
            if(!canOpenPicker()) return;
            openModal();
        });

        $('#wc-suf-modal-close').on('click', closeModal);
        $overlay.on('click', closeModal);

        $(document).on('keydown', function(e){
            if ($modal.is(':visible') && e.key === 'Escape'){
                e.preventDefault();
                closeModal();
            }
        });

        $q.on('input', function(){
            renderPickerResults();
        });

        $filters.on('change', 'select[data-tax]', function(){
            const tax = String($(this).data('tax') || '');
            const val = String($(this).val() || '');
            if (!tax) return;
            activeFilters[tax] = val; // normalized already
            renderPickerResults();
        });

        $('#wc-suf-picker-clear').on('click', function(){
            $q.val('');
            $filters.find('select[data-tax]').val('');
            for (const k in activeFilters){
                if (Object.prototype.hasOwnProperty.call(activeFilters, k)) activeFilters[k] = '';
            }
            $results.html('<div style="padding:14px" class="suf-muted">برای نمایش نتایج، عبارت جستجو را وارد کنید یا یک فیلتر انتخاب کنید.</div>');
            updateSelectedInfo();
            $q.trigger('focus');
        });

        $results.on('click', '.picker-inc', function(){
            const pid = String($(this).data('pid'));
            let current = (+pickerQty[pid] || 0) + 1;
            current = capQtyForOut(pid, current, true);
            pickerQty[pid] = current;
            $results.find(`.picker-qty[data-pid="${pid}"]`).val(current);
            updateSelectedInfo();
        });

        $results.on('click', '.picker-dec', function(){
            const pid = String($(this).data('pid'));
            const current = Math.max(0, (+pickerQty[pid] || 0) - 1);
            pickerQty[pid] = current;
            $results.find(`.picker-qty[data-pid="${pid}"]`).val(current);
            updateSelectedInfo();
        });

        $results.on('change', '.picker-qty', function(){
            const pid = String($(this).data('pid'));
            let v = +$(this).val();
            if (!Number.isFinite(v)) v = 0;
            v = Math.max(0, Math.floor(v));
            v = capQtyForOut(pid, v, true);
            pickerQty[pid] = v;
            $(this).val(v);
            updateSelectedInfo();
        });

        $('#wc-suf-picker-add').on('click', function(){
            if(!opType) return;

            let addedAny = false;

            for (const pid in pickerQty){
                let qty = +pickerQty[pid];
                if (!qty || qty <= 0) continue;

                const name  = findLabelById(pid) || '(بدون نام)';
                const stock = findProductionStockById(pid);

                if (opType === 'out' && qty > stock){
                    alert(`مقدار انتخابی برای «${name}» بیشتر از موجودی انبار تولید است.`);
                    return;
                }

                const existingIdx = items.findIndex(x => String(x.id) === String(pid));
                if (existingIdx >= 0){
                    items[existingIdx].qty = (items[existingIdx].qty || 0) + qty;
                    items[existingIdx].stock = stock;
                    enforceOutLimit(existingIdx);
                } else {
                    items.push({id: pid, name, qty, stock});
                    enforceOutLimit(items.length - 1);
                }

                addedAny = true;
            }

            if (!addedAny){
                alert('هیچ محصولی با تعداد بالاتر از صفر انتخاب نشده است.');
                return;
            }

            for (const pid in pickerQty){
                if (Object.prototype.hasOwnProperty.call(pickerQty, pid)) pickerQty[pid] = 0;
            }

            renderTable();
            closeModal();
        });

        $('input[name="op-type"]').on('change', function(){
            if(opType) return;
            opType = $(this).val();

            $('input[name="op-type"]').prop('disabled', true);

            if(opType === 'out'){
                $('#out-destination-wrap').css('display','flex');
            } else {
                $('#out-destination-wrap').hide();
            }

            refreshPickerOpenButton();
            renderTable();
        });

        $('input[name="out-destination"]').on('change', function(){
            if(opType !== 'out') return;
            outDestination = $(this).val() || null;
            refreshPickerOpenButton();
            $('#btn-save').prop('disabled', !canSave());
            renderTable();
            if ($modal.is(':visible')) {
                renderPickerResults();
            }
        });

        $('#items-table').on('click','.row-inc', function(){
            const i = +$(this).data('i'); items[i].qty++; enforceOutLimit(i); renderTable();
        });
        $('#items-table').on('click','.row-dec', function(){
            const i = +$(this).data('i'); items[i].qty = Math.max(1, items[i].qty-1); enforceOutLimit(i); renderTable();
        });
        $('#items-table').on('change','.row-qty', function(){
            const i = +$(this).data('i'); let v = +$(this).val();
            v = Math.max(1, v||1); items[i].qty = v; enforceOutLimit(i); renderTable();
        });

        $('#items-table').on('click','.btn-del',function(){
            items.splice($(this).data('i'),1);
            renderTable();
        });

        let submitting = false;
        $('#btn-save').on('click', function(){
            if (submitting) return;
            if (!canSave()) return;
            submitting = true;

            const $btn = $(this);
            const originalText = $btn.text();
            $btn.prop('disabled', true).css({opacity: 0.6, cursor: 'not-allowed'}).text('در حال ثبت...');

            $.post(ajaxurl, {
                action      : 'save_stock_update',
                items       : JSON.stringify(items),
                user_code   : userCode,
                out_destination : String(outDestination || ''),
                op_type     : opType,
                _wpnonce    : '<?php echo wp_create_nonce('save_stock_update'); ?>'
            }).done(function(res){
                try{
                    if(res && res.success){
                        alert(res.data && res.data.message ? res.data.message : 'ثبت شد.');
                        location.reload();
                    }else{
                        alert((res && res.data && res.data.message) ? res.data.message : 'ثبت ناموفق.');
                        submitting = false; $btn.prop('disabled', false).css({opacity: 1, cursor: 'pointer'}).text(originalText);
                    }
                }catch(e){
                    alert('پاسخ نامعتبر از سرور.');
                    submitting = false; $btn.prop('disabled', false).css({opacity: 1, cursor: 'pointer'}).text(originalText);
                }
            }).fail(function(){
                alert('خطای ارتباطی هنگام ثبت.');
                submitting = false; $btn.prop('disabled', false).css({opacity: 1, cursor: 'pointer'}).text(originalText);
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
});

/*--------------------------------------
| AJAX: ثبت نهایی (YITH POS)
---------------------------------------*/
add_action('wp_ajax_save_stock_update','wc_suf_save_stock_update_handler');
function wc_suf_save_stock_update_handler(){
    check_ajax_referer('save_stock_update');

    if( ! is_user_logged_in() || ( ! current_user_can('manage_woocommerce') && ! current_user_can('manage_options') ) ){
        wp_send_json_error(['message'=>'دسترسی غیرمجاز.']);
    }

    $raw   = isset($_POST['items']) ? wp_unslash($_POST['items']) : '[]';
    $items = json_decode($raw, true);
    if ( ! is_array($items) || empty($items) ) {
        wp_send_json_error(['message'=>'داده‌ای ارسال نشده است.']);
    }

    $user_code   = isset($_POST['user_code']) ? sanitize_text_field( wp_unslash($_POST['user_code']) ) : '';
    $op_type_in  = isset($_POST['op_type']) ? sanitize_text_field( wp_unslash($_POST['op_type']) ) : '';
    $op_type     = in_array($op_type_in, ['in','out','onlyLabel'], true) ? $op_type_in : '';

    if( ! $op_type ){
        wp_send_json_error(['message'=>'نوع عملیات مشخص نیست (ورود/خروج/صرفاً چاپ لیبل).']);
    }

    $out_destination = isset($_POST['out_destination']) ? sanitize_text_field( wp_unslash($_POST['out_destination']) ) : '';
    $transfer_store_id = null;
    if ( $op_type === 'out' ) {
        if ( ! in_array( $out_destination, ['main','teh'], true ) ) {
            wp_send_json_error(['message'=>'مقصد خروج مشخص نیست.']);
        }
        if ( $out_destination === 'teh' ) {
            $transfer_store_id = (int) WC_SUF_TEHRANPARS_STORE_ID;
        }
    }

    $user      = wp_get_current_user();
    $uid       = (int) ($user->ID ?? 0);
    $ulog      = $uid ? ($user->user_login ?? '') : '';
    $ip        = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field( wp_unslash($_SERVER['REMOTE_ADDR']) ) : '';

    global $wpdb;
    $table   = $wpdb->prefix.'stock_audit';
    $move_table = $wpdb->prefix.'stock_production_moves';

    $tx_started = false;
    if ( in_array( $op_type, ['in','out'], true ) ) {
        $tx_started = ( false !== $wpdb->query('START TRANSACTION') );
    }

    if ($op_type === 'out') {
        $insufficient = [];
        $locked_old_qty = [];
        foreach($items as $it){
            $pid = isset($it['id'])  ? absint($it['id']) : 0;
            $req = isset($it['qty']) ? (int) $it['qty']  : 0;
            if( ! $pid || $req <= 0 ) continue;

            $product = wc_get_product($pid);
            if( ! $product ) continue;
            $old   = $tx_started ? wc_suf_get_production_stock_qty_for_update( $product ) : wc_suf_get_production_stock_qty( $pid );
            $pname = wc_suf_full_product_label( $product );
            $locked_old_qty[$pid] = $old;

            if( $req > $old ){
                $insufficient[] = [
                    'id'   => $pid,
                    'name' => $pname,
                    'req'  => $req,
                    'have' => $old,
                ];
            }
        }

        if ( ! empty($insufficient) ) {
            if ( $tx_started ) {
                $wpdb->query('ROLLBACK');
            }
            $lines = array_map(function($r){
                return sprintf('محصول %s (ID: %d): درخواست %d، موجودی فعلی %d', $r['name'], $r['id'], $r['req'], $r['have']);
            }, $insufficient);

            $msg = "ثبت ناموفق؛ به‌دلیل کمبود موجودی موارد زیر امکان خروج ندارند:\n- " . implode("\n- ", $lines) . "\n\nلطفاً مقادیر را اصلاح کنید و دوباره تلاش کنید.";
            wp_send_json_error(['message' => $msg]);
        }
    }

    $batch_code = wc_suf_next_batch_code( $op_type === 'out' ? 'out' : $op_type );

    $inserted = 0;
    $rows_for_sheet = [];

    foreach($items as $it){
        $pid = isset($it['id'])  ? absint($it['id']) : 0;
        $req = isset($it['qty']) ? (int) $it['qty']  : 0;
        if( ! $pid || $req <= 0 ) continue;

        $product = wc_get_product($pid);
        if( ! $product ) continue;
        $stock_product = wc_suf_get_stock_product( $product );

        if( ! $stock_product->managing_stock() ){
            $stock_product->set_manage_stock(true);
            if( $stock_product->get_stock_quantity() === null ){
                $stock_product->set_stock_quantity(0);
            }
            $stock_product->save();
        }

        $old_qty = (int) ( $stock_product->get_stock_quantity() ?? 0 );
        $pname   = $stock_product->get_name();

        if( $op_type === 'out' ){
            $prod_old = isset($locked_old_qty[$pid]) ? (int) $locked_old_qty[$pid] : ( $tx_started ? wc_suf_get_production_stock_qty_for_update( $product ) : wc_suf_get_production_stock_qty( $pid ) );
            $prod_new = max( 0, $prod_old - $req );
            wc_suf_set_production_stock_qty( $product, $prod_new );
            $old_qty      = $prod_old;
            $new_qty      = $prod_new;
            $logged_added = $req;

            if ( $out_destination === 'main' ) {
                wc_update_product_stock($stock_product, $req, 'increase');
                $stock_product->save();
            } elseif ( $out_destination === 'teh' ) {
                $store_result  = wc_suf_yith_change_store_stock( $stock_product, $req, $transfer_store_id, 'increase' );
                if ( is_wp_error( $store_result ) ) {
                    if ( $tx_started ) {
                        $wpdb->query('ROLLBACK');
                    }
                    wp_send_json_error(['message'=>'افزایش موجودی استور YITH ناموفق: '.$store_result->get_error_message()]);
                }
            }

        } elseif( $op_type === 'in' ){
            $prod_old = $tx_started ? wc_suf_get_production_stock_qty_for_update( $product ) : wc_suf_get_production_stock_qty( $pid );
            $prod_new = max( 0, $prod_old + $req );
            wc_suf_set_production_stock_qty( $product, $prod_new );
            $old_qty      = $prod_old;
            $new_qty      = $prod_new;
            $logged_added = $req;

        } else {
            $new_qty      = $old_qty;
            $logged_added = $req;
        }

        $data = [
            'batch_code'   => $batch_code,
            'op_type'      => ( $op_type === 'out' && $out_destination === 'teh' ) ? 'out_teh' : ( $op_type === 'out' ? 'out_main' : $op_type ),
            'purpose'      => ($op_type === 'out')
                            ? ( $out_destination === 'teh' ? 'انتقال به انبار تهرانپارس' : 'خروج به انبار اصلی' )
                            : null,
            'print_label'  => ($op_type === 'onlyLabel') ? 1 : 0,
            'product_id'   => $pid,
            'product_name' => $pname,
            'old_qty'      => $old_qty,
            'added_qty'    => $logged_added,
            'new_qty'      => $new_qty,
            'user_id'      => $uid ?: null,
            'user_login'   => $ulog ?: null,
            'user_code'    => $user_code ?: null,
            'ip'           => $ip ?: null,
            'created_at'   => current_time('mysql'),
        ];
        $formats = ['%s','%s','%s','%d','%d','%s','%f','%f','%f','%d','%s','%s','%s','%s'];

        $ok = $wpdb->insert( $table, $data, $formats );
        if( false === $ok ){
            if ( $tx_started ) {
                $wpdb->query('ROLLBACK');
            }
            error_log('[WC Stock Update] DB Insert FAILED: '.$wpdb->last_error.' | Data: '.wp_json_encode($data));
            wp_send_json_error(['message'=>'ثبت در پایگاه‌داده ناموفق بود.']);
        } else {
            $inserted++;
        }

        if ( in_array( $op_type, ['in','out'], true ) ) {
            $move_data = [
                'batch_code'      => $batch_code,
                'operation'       => $op_type,
                'destination'     => ( $op_type === 'out' ) ? $out_destination : 'production',
                'product_id'      => $pid,
                'product_name'    => wc_suf_full_product_label( $product ),
                'sku'             => $product->get_sku() ?: null,
                'product_type'    => $product->get_type(),
                'parent_id'       => $product->is_type('variation') ? $product->get_parent_id() : null,
                'attributes_text' => wc_suf_get_product_attributes_text( $product ),
                'old_qty'         => (float) $old_qty,
                'change_qty'      => (float) $req,
                'new_qty'         => (float) $new_qty,
                'user_id'         => $uid ?: null,
                'user_login'      => $ulog ?: null,
                'user_code'       => $user_code ?: null,
                'created_at'      => current_time('mysql'),
            ];
            $wpdb->insert(
                $move_table,
                $move_data,
                ['%s','%s','%s','%d','%s','%s','%s','%d','%s','%f','%f','%f','%d','%s','%s','%s']
            );
            if ( ! empty($wpdb->last_error) ) {
                if ( $tx_started ) {
                    $wpdb->query('ROLLBACK');
                }
                wp_send_json_error(['message'=>'ثبت لاگ حرکات انبار ناموفق بود.']);
            }
        }

        $full_name = wc_suf_full_product_label($product);
        $price = wc_get_price_to_display( $product );
        for ($i=0; $i<$req; $i++){
            $rows_for_sheet[] = [
                'batch_code' => (string) $batch_code,
                'op'         => (string) (
                    $op_type === 'out' ? ( $out_destination === 'teh' ? 'out_teh' : 'out_main' ) : $op_type
                ),
                'print_label'=> (int)    ( $op_type === 'onlyLabel' ? 1 : 0 ),
                'id'         => (string) $pid,
                'name'       => (string) $full_name,
                'price'      => (float)  $price,
                'purpose'    => (string) (
                    $op_type === 'out' ? ( $out_destination === 'teh' ? 'انتقال به انبار تهرانپارس' : 'خروج به انبار اصلی' ) : ''
                ),
            ];
        }
    }

    if ( ! empty($rows_for_sheet) && defined('WC_SUF_GS_WEBAPP_URL') && WC_SUF_GS_WEBAPP_URL ) {
        $payload = [
            'op'   => 'append',
            'rows' => $rows_for_sheet,
            'meta' => [
                'user_code'  => $user_code,
                'user_login' => $ulog,
                'ip'         => $ip,
                'ts'         => current_time('mysql'),
            ],
            'headers'       => ['batch_code','op','print_label','ID','name','price','purpose'],
            'baseSheetName' => 'Sheet1'
        ];
        $response = wp_remote_post( WC_SUF_GS_WEBAPP_URL, [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body'    => wp_json_encode( $payload ),
        ] );
        if ( is_wp_error($response) ) {
            error_log('[WC Stock Update] Google Sheets POST failed: '.$response->get_error_message());
        } else {
            $code = wp_remote_retrieve_response_code($response);
            if ( $code < 200 || $code >= 300 ) {
                error_log('[WC Stock Update] Google Sheets bad status: '.$code.' | body: '.wp_remote_retrieve_body($response));
            }
        }
    }

    if ( $inserted === 0 ) {
        if ( $tx_started ) {
            $wpdb->query('ROLLBACK');
        }
        $msg = 'هیچ موردی ثبت نشد.' . ( $wpdb->last_error ? (' DB error: '.$wpdb->last_error) : '' );
        wp_send_json_error(['message'=>$msg]);
    }

    if ( $tx_started ) {
        $wpdb->query('COMMIT');
    }

    $op_label = wc_suf_op_label( $op_type === 'out' ? ( $out_destination === 'teh' ? 'out_teh' : 'out_main' ) : $op_type );
    wp_send_json_success(['message'=>"ثبت {$op_label} انجام شد. کد ثبت: {$batch_code}"]);
}

/*--------------------------------------
| رندر مشترک گزارش (لیست و جزئیات)
---------------------------------------*/
function wc_suf_render_audit_html($args = []){
    $public = ! empty( $args['public'] );

    if( ! $public ){
        if( ! current_user_can('manage_woocommerce') && ! current_user_can('manage_options') ){
            return '<div class="wrap" dir="rtl" style="color:#b91c1c">دسترسی کافی برای مشاهده گزارش ندارید.</div>';
        }
    }

    global $wpdb;
    $table = $wpdb->prefix.'stock_audit';
    ob_start();

    if( isset($_GET['view'], $_GET['code']) && $_GET['view']==='batch' ){
        $code = sanitize_text_field( wp_unslash($_GET['code']) );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM $table WHERE batch_code = %s ORDER BY id ASC", $code
        ) );
        ?>
        <div class="wrap" dir="rtl">
            <h1>جزئیات کد ثبت: <?php echo esc_html($code); ?></h1>
            <p><a href="<?php echo esc_url( remove_query_arg(['view','code']) ); ?>">&larr; بازگشت به فهرست</a></p>
            <table class="widefat fixed striped">
                <thead>
                    <tr>
                        <th>#</th><th>عملیات</th><th>ID</th><th>محصول</th>
                        <th>تغییر</th><th>قبل → بعد</th><th>چاپ لیبل</th>
                        <th>کاربر</th><th>کد</th><th>IP</th><th>تاریخ</th>
                    </tr>
                </thead>
                <tbody>
                <?php
                if($rows){
                    foreach($rows as $r){
                        $user_disp = $r->user_login ? $r->user_login : ( $r->user_id ? 'user#'.$r->user_id : 'مهمان' );
                        ?>
                        <tr>
                            <td><?php echo esc_html($r->id); ?></td>
                            <td><?php echo esc_html( wc_suf_op_label($r->op_type) ); ?></td>
                            <td><?php echo esc_html($r->product_id); ?></td>
                            <td><?php echo esc_html($r->product_name ?: ''); ?></td>
                            <td><?php echo esc_html(intval($r->added_qty)); ?></td>
                            <td><?php echo esc_html(intval($r->old_qty).' → '.intval($r->new_qty)); ?></td>
                            <td><?php echo esc_html($r->print_label ? 'بله' : 'خیر'); ?></td>
                            <td><?php echo esc_html($user_disp); ?></td>
                            <td><?php echo esc_html($r->user_code ?: '—'); ?></td>
                            <td><?php echo esc_html($r->ip ?: ''); ?></td>
                            <td><?php echo esc_html($r->created_at); ?></td>
                        </tr>
                        <?php
                    }
                } else {
                    echo '<tr><td colspan="11" style="text-align:center">‌رکوردی یافت نشد.</td></tr>';
                }
                ?>
                </tbody>
            </table>
            <?php
            $op_for_batch = $wpdb->get_var( $wpdb->prepare(
                "SELECT MAX(op_type) FROM $table WHERE batch_code=%s", $code
            ) );
            if ( in_array( $op_for_batch, ['out','out_main','out_teh'], true ) ) {
                $pur = $wpdb->get_var( $wpdb->prepare(
                    "SELECT purpose FROM $table WHERE batch_code=%s AND purpose IS NOT NULL AND purpose<>'' LIMIT 1", $code
                ) );
                if($pur){
                    echo '<p><strong>هدف خروج:</strong> '.esc_html($pur).'</p>';
                }
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    $limit = 200;
    $rows = $wpdb->get_results(
        $wpdb->prepare("
            SELECT
              batch_code,
              MIN(created_at) as created_at,
              MAX(op_type) as op_type,
              MAX(print_label) as print_label,
              MAX(user_login) as user_login,
              MAX(user_id) as user_id,
              MAX(user_code) as user_code,
              COUNT(*) as items_count,
              SUM(added_qty) as total_qty_change
            FROM $table
            GROUP BY batch_code
            ORDER BY MAX(id) DESC
            LIMIT %d
        ", $limit)
    );
    ?>
    <div class="wrap" dir="rtl">
        <h1>گزارش تغییر موجودی (گروهی)</h1>
        <p>آخرین <?php echo esc_html($limit); ?> ثبت گروهی. برای جزئیات روی کد ثبت کلیک کنید.</p>
        <table class="widefat fixed striped">
            <thead>
                <tr>
                    <th>کد ثبت</th>
                    <th>عملیات</th>
                    <th>تعداد آیتم‌ها</th>
                    <th>جمع تغییر</th>
                    <th>چاپ لیبل</th>
                    <th>کاربر</th>
                    <th>کد (URL/Shortcode)</th>
                    <th>تاریخ</th>
                </tr>
            </thead>
            <tbody>
                <?php
                if($rows){
                    foreach($rows as $r){
                        $user_disp = $r->user_login ? $r->user_login : ( $r->user_id ? 'user#'.$r->user_id : 'مهمان' );
                        $link = esc_url( add_query_arg( ['view'=>'batch','code'=>$r->batch_code] ) );
                        ?>
                        <tr>
                            <td><a href="<?php echo $link; ?>"><?php echo esc_html($r->batch_code); ?></a></td>
                            <td><?php echo esc_html( wc_suf_op_label($r->op_type) ); ?></td>
                            <td><?php echo esc_html((int)$r->items_count); ?></td>
                            <td><?php echo esc_html((int)$r->total_qty_change); ?></td>
                            <td><?php echo esc_html($r->print_label ? 'بله' : 'خیر'); ?></td>
                            <td><?php echo esc_html($user_disp); ?></td>
                            <td><?php echo esc_html($r->user_code ?: '—'); ?></td>
                            <td><?php echo esc_html($r->created_at); ?></td>
                        </tr>
                        <?php
                    }
                } else {
                    echo '<tr><td colspan="8" style="text-align:center">‌رکوردی یافت نشد.</td></tr>';
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
    return ob_get_clean();
}

/*--------------------------------------
| Admin: گزارش گروهی
---------------------------------------*/
add_action('admin_menu', function(){
    $cap = current_user_can('manage_woocommerce') ? 'manage_woocommerce' : 'manage_options';
    add_menu_page(
        'گزارش تغییر موجودی',
        'گزارش موجودی',
        $cap,
        'wc-stock-audit',
        'wc_suf_render_audit_page',
        'dashicons-clipboard',
        56
    );
});
function wc_suf_render_audit_page(){
    if( ! current_user_can('manage_woocommerce') && ! current_user_can('manage_options') ) return;
    echo wc_suf_render_audit_html();
}

/*--------------------------------------
| Shortcode گزارش فرانت: [stock_audit_report]
---------------------------------------*/
add_shortcode('stock_audit_report', function($atts){
    $atts = shortcode_atts(['public' => '0'], $atts, 'stock_audit_report');
    $is_public = ($atts['public'] === '1' || strtolower($atts['public']) === 'true');
    return wc_suf_render_audit_html(['public' => $is_public]);
});
