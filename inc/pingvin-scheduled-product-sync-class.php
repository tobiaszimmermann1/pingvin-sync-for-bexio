<?php 
namespace Pingvin;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

class PvBexioProductsSync {

  public function __construct() {
    add_action( 'update_option_pv_bexio_productsync_action_settings', array($this, 'pv_bexio_productsync_action_settings_updated'), 10, 2 );  
    add_action( 'pingvin_bexio_connector_productsync', array($this, 'pingvin_bexio_connector_sync_action_products')); 
  }

  /**
   * Update scheduled task when 'pv_bexio_productsync_action_settings' is changed
   */
  public function pv_bexio_productsync_action_settings_updated( $old_value, $new_value ) {
    if ($old_value != $new_value)  {
      PingvinLogger::log('info', '`pv_bexio_productsync_action_settings` changed from '.$old_value.' to '.$new_value);
      $updated_options = json_decode($new_value);

      // check if sync is enabled or disabled
      $sync_enabled = $updated_options->enabled;

      if ($sync_enabled) {
        // handle action scheduler task
        // 1. check if there is a scheduled action. If yes, unschedule it
        $existing_scheduled_action = as_has_scheduled_action('pingvin_bexio_connector_productsync');

        if ($existing_scheduled_action) {
          // unschedule the existing action
          $unscheduled_action = as_unschedule_all_actions('pingvin_bexio_connector_productsync');
          
          // logging results
          if (!is_wp_error($unscheduled_action)) {
            PingvinLogger::log('info', 'scheduled action `pingvin_bexio_connector_productsync` has been successfully UNscheduled.');
          } else {
            PingvinLogger::log('error', $unscheduled_action->get_error_message());
          }
        }
        
        // 2. schedule new action with updated options
        $new_scheduled_action = as_schedule_recurring_action( strtotime('now'), (int)$updated_options->interval, 'pingvin_bexio_connector_productsync', array(), '', true, 1 );

        // update transient for next sync
        $time_locale = 'de_DE';
        $formatter = new \IntlDateFormatter($time_locale, \IntlDateFormatter::LONG, \IntlDateFormatter::SHORT);  
        $now = new \DateTime();
        $date_next = $now->modify('+' . (int)$updated_options->interval . ' seconds');
        $date_next = $formatter->format($date_next);

        set_transient('pv_bexio_connector_next', json_encode(array(
          'date' => $date_next,
        )), 0);

        // logging results
        if (!is_wp_error($new_scheduled_action)) {
          PingvinLogger::log('info', 'new scheduled action `pingvin_bexio_connector_productsync` with interval '.(int)$updated_options->interval).' has been successfully scheduled.';
        } else {
          PingvinLogger::log('error', $new_scheduled_action->get_error_message());
        }

      } else {
        // check if there is a scheduled action. If yes, unschedule it
        $existing_scheduled_action = as_has_scheduled_action('pingvin_bexio_connector_productsync');

        if ($existing_scheduled_action) {
          // unschedule the existing action
          $unscheduled_action = as_unschedule_all_actions('pingvin_bexio_connector_productsync');

          // remove transient for next sync
          delete_transient( 'pv_bexio_connector_next' );
          
          // logging results
          PingvinLogger::log('info', 'sync has been disabled.');
          if (!is_wp_error($unscheduled_action)) {
            PingvinLogger::log('info', 'scheduled action `pingvin_bexio_connector_productsync` has been successfully UNscheduled.');
          } else {
            PingvinLogger::log('error', $unscheduled_action->get_error_message());
          }
        }
        return;
      }
    } else {
      return;
    }
  }

  /**
   * Scheduled syncing action task to update products / create new products
   */
  function pingvin_bexio_connector_sync_action_products() {
    $sync_direction = get_option('pv_bexio_productsync_options')['pv_productsync_sync_direction'];
    if ($sync_direction === 'from_bexio') {
      $this->pingvin_bexio_connector_sync_action_products_from_bexio();
    } else {
      $this->pingvin_bexio_connector_sync_action_products_to_bexio();
    }
  }
  
  function pingvin_bexio_connector_sync_action_products_from_bexio() {
    // statistics for sync status:
    $interval = json_decode(get_option('pv_bexio_productsync_action_settings'))->interval;
    $time_locale = 'de_DE';
    $formatter = new \IntlDateFormatter($time_locale, \IntlDateFormatter::LONG, \IntlDateFormatter::SHORT);
    $now = new \DateTime();
    $date_last = $formatter->format($now);
    $date_next = $now->modify('+' . $interval . ' seconds');
    $date_next = $formatter->format($date_next);
    $products_count = 0;

    // get products from Bexio
    $res_products = pv_api_call('GET', '2.0/article/');
    $products = $res_products['result'];

    // get tax rates from Bexio and determine wc tax class per tax rate specified in plugin settings
    $res_taxes = pv_api_call('GET', '3.0/taxes/');
    $bexio_taxes = $res_taxes['result'];

    $bexio_tax_array = array();
    $standard_tax_rate = get_option('pv_bexio_productsync_options')['pv_productsync_tax_rate_standard_bexio'];
    $reduced_tax_rate = get_option('pv_bexio_productsync_options')['pv_productsync_tax_rate_reduced_bexio'];
    $special_tax_rate = get_option('pv_bexio_productsync_options')['pv_productsync_tax_rate_special_bexio'];

    foreach($bexio_taxes as $bexio_tax) {
      if ($bexio_tax->code === $standard_tax_rate) $bexio_tax_array[$bexio_tax->id] = get_option('pv_bexio_productsync_options')['pv_productsync_tax_rate_standard_woo'];;
      if ($bexio_tax->code === $reduced_tax_rate) $bexio_tax_array[$bexio_tax->id] = get_option('pv_bexio_productsync_options')['pv_productsync_tax_rate_reduced_woo'];
      if ($bexio_tax->code === $special_tax_rate) $bexio_tax_array[$bexio_tax->id] = get_option('pv_bexio_productsync_options')['pv_productsync_tax_rate_special_woo'];
    }

    // start syncing if there are >0 products pulled from bexio
    $prod_count = count($products);
    if ($prod_count> 0) {
      PingvinLogger::log('info', '_____');
      PingvinLogger::log('info', $date_last);
      PingvinLogger::log('info', "Product Sync Action (from Bexio): Pulled $prod_count products from Bexio");

      // if there are Bexio products to sync, get all woo products
      $woo_products = wc_get_products(array(
        'limit' => -1,
      ));

      // get all available sku's from WooCommerce for matching with Bexio products
      $comparison_array = array();
      foreach($woo_products as $woo_product) {
        if ($woo_product->is_type('variable')) {
          $woo_product_variations = $woo_product->get_available_variations();
          foreach ( $variations as $variation ) {
             $sku = $variation->get_sku();
             $comparison_array[$sku] = array($variation->get_id(), 'variable');
          }
        } else {
          $sku = $woo_product->get_sku();
          $comparison_array[$sku] = array($woo_product->get_id(), 'simple');
        }
      }  

      // Loop through Bexio products
      $bexio_sku_array = array();
      foreach($products as $prod) {
          // save Bexio sku into bexio_sku_array for later use
          array_push($bexio_sku_array, $prod->intern_code);

          // product array
          $bexio_data = [
            'id' => $prod->id,
            "user_id" => $prod->user_id,
            "article_type_id" => $prod->article_type_id,
            "contact_id" => $prod->contact_id,
            "deliverer_code" => $prod->deliverer_code,
            "deliverer_name" => $prod->deliverer_name,
            "deliverer_description" => $prod->deliverer_description,
            "intern_code" => $prod->intern_code,
            "intern_name" => $prod->intern_name,
            "intern_description" => $prod->intern_description,
            "purchase_price" => $prod->purchase_price,
            "sale_price" => $prod->sale_price,
            "purchase_total" => $prod->purchase_total,
            "sale_total" => $prod->sale_total,
            "currency_id" => $prod->currency_id,
            "tax_income_id" => $prod->tax_income_id,
            "tax_id" => $prod->tax_id,
            "tax_expense_id" => $prod->tax_expense_id,
            "unit_id" => $prod->unit_id,
            "is_stock" => $prod->is_stock,
            "stock_id" => $prod->stock_id,
            "stock_place_id" => $prod->stock_place_id,
            "stock_nr" => $prod->stock_nr,
            "stock_min_nr" => $prod->stock_min_nr,
            "stock_reserved_nr" => $prod->stock_reserved_nr,
            "stock_available_nr" => $prod->stock_available_nr,
            "stock_picked_nr" => $prod->stock_picked_nr,
            "stock_disposed_nr" => $prod->stock_disposed_nr,
            "stock_ordered_nr" => $prod->stock_ordered_nr,
            "width" => $prod->width,
            "height" => $prod->height,
            "weight" => $prod->weight,
            "volume" => $prod->volume,
            "html_text" => $prod->html_text,
            "remarks" => $prod->remarks,
            "delivery_price" => $prod->delivery_price,
            "article_group_id" => $prod->article_group_id,
          ];
          
          // check the comparison array if there is already a WooCommerce product/variation with the same sku
          // if it exists, then update the product/variation. If not, then create a new wc simple product
          $is_new = false;
          if (array_key_exists($bexio_data['intern_code'], $comparison_array)) {
            $woo_id_to_update = $comparison_array[$bexio_data['intern_code']][0];
            $woo_product_to_update = wc_get_product($woo_id_to_update);
          } else {
            $is_new = true;
            $woo_product_to_update = new \WC_Product_Simple();
            $woo_product_to_update->save();
            $woo_id_to_update = $woo_product_to_update->get_id();
          }

          // update the wc product/variant with the data from Bexio
          if ($woo_product_to_update) {            
            // name, description, sku, price
            $woo_product_to_update->set_name($bexio_data['intern_name']);
            $woo_product_to_update->set_description($bexio_data['intern_description']);
            $woo_product_to_update->set_sku($bexio_data['intern_code']);   
            $woo_product_to_update->set_regular_price($bexio_data['sale_price']);

            // handle weight
            $wc_weight_unit = get_option('woocommerce_weight_unit');
            if ($wc_weight_unit === 'kg') {
              $bexio_data['weight'] = $bexio_data['weight'] / 1000;
            } else if ($wc_weight_unit === 'g') {
              $bexio_data['weight'] = $bexio_data['weight'];
            } else if ($wc_weight_unit === 'lbs') {
              $bexio_data['weight'] = $bexio_data['weight'] * 0.453592;
            } else if ($wc_weight_unit === 'oz') {
              $bexio_data['weight'] = $bexio_data['weight'] * 0.0283495;
            }
            $woo_product_to_update->set_weight($bexio_data['weight']);

            // handle width, height
            $wc_dimesion_unit = get_option('woocommerce_dimension_unit');
            if ($wc_dimesion_unit === 'cm') {
              $bexio_data['width'] = $bexio_data['width'] / 10;
              $bexio_data['height'] = $bexio_data['height'] / 10;
            } else if ($wc_dimesion_unit === 'mm') {
              $bexio_data['width'] = $bexio_data['width'];
              $bexio_data['height'] = $bexio_data['height'];
            } else if ($wc_dimesion_unit === 'in') {
              $bexio_data['width'] = $bexio_data['width'] * 0.393701;
              $bexio_data['height'] = $bexio_data['height'] * 0.393701;
            }
            $woo_product_to_update->set_width($bexio_data['width']);
            $woo_product_to_update->set_height($bexio_data['height']);

            // handle stock, round if necessary
            if ($bexio_data['is_stock'] === true) {
              $new_stock = round(floatval($bexio_data['stock_available_nr']));

              $woo_product_to_update->set_manage_stock(true);
              if ($new_stock > 0) {
                $woo_product_to_update->set_stock_status('instock');
              } else {
                $woo_product_to_update->set_stock_status('outofstock');
              }
              $woo_product_to_update->set_stock_quantity($new_stock);
              $woo_product_to_update->set_low_stock_amount($bexio_data['stock_min_nr']);
            } else {
              $woo_product_to_update->set_manage_stock(false);
              $woo_product_to_update->set_stock_status('instock');
            }

            // handle vat tax rates: use tax code to sync tax rate. if tax code does not exist in wc, then use standard tax rate
            if (get_option('woocommerce_calc_taxes') === 'yes' && count($bexio_tax_array) > 0 && $standard_tax_rate !== null && $reduced_tax_rate !== null) {
              $wc_tax_class = $bexio_tax_array[$bexio_data['tax_income_id']];
              $woo_product_to_update->set_tax_status('taxable');
              $tax_set = $woo_product_to_update->set_tax_class($wc_tax_class);
              if (is_wp_error($tax_set)) {
                $woo_product_to_update->set_tax_class('');
                PingvinLogger::log('error', "Could not set the tax rate of product: ".$tax_set->get_error_message().". Using standard tax rate.");
              }

              if (get_option('woocommerce_prices_include_tax') === 'yes') {
                $woo_product_to_update->set_price($bexio_data['sale_price'] + ($bexio_data['sale_price'] * (get_option('woocommerce_standard_tax_rate') / 100)));
              }
            } else {
              PingvinLogger::log('error', "Could not set the tax rate of product ".$bexio_data['intern_name']);
            }

            // save meta data to wc product/variaation
            $woo_product_to_update->update_meta_data('_bexio_id', $bexio_data['id']);
            $woo_product_to_update->update_meta_data('_deliverer_code', $bexio_data['deliverer_code']);
            $woo_product_to_update->update_meta_data('_user_id', $bexio_data['user_id']);
            $woo_product_to_update->update_meta_data('_article_type_id', $bexio_data['article_type_id']);
            $woo_product_to_update->update_meta_data('_contact_id', $bexio_data['contact_id']);
            $woo_product_to_update->update_meta_data('_deliverer_name', $bexio_data['deliverer_name']);
            $woo_product_to_update->update_meta_data('_purchase_price', $bexio_data['purchase_price']);
            $woo_product_to_update->update_meta_data('_deliverer_description', $bexio_data['deliverer_description']);
            $woo_product_to_update->update_meta_data('_purchase_total', $bexio_data['purchase_total']);
            $woo_product_to_update->update_meta_data('_sale_total', $bexio_data['sale_total']);
            $woo_product_to_update->update_meta_data('_currency_id', $bexio_data['currency_id']);
            $woo_product_to_update->update_meta_data('_tax_income_id', $bexio_data['tax_income_id']);
            $woo_product_to_update->update_meta_data('_tax_id', $bexio_data['tax_id']);
            $woo_product_to_update->update_meta_data('_tax_expense_id', $bexio_data['tax_expense_id']);
            $woo_product_to_update->update_meta_data('_unit_id', $bexio_data['unit_id']);
            $woo_product_to_update->update_meta_data('_stock_id', $bexio_data['stock_id']);
            $woo_product_to_update->update_meta_data('_stock_place_id', $bexio_data['stock_place_id']);
            $woo_product_to_update->update_meta_data('_stock_nr', $bexio_data['stock_nr']);
            $woo_product_to_update->update_meta_data('_stock_min_nr', $bexio_data['stock_min_nr']);
            $woo_product_to_update->update_meta_data('_stock_reserved_nr', $bexio_data['stock_reserved_nr']);
            $woo_product_to_update->update_meta_data('_stock_picked_nr', $bexio_data['stock_picked_nr']);
            $woo_product_to_update->update_meta_data('_stock_disposed_nr', $bexio_data['stock_disposed_nr']);
            $woo_product_to_update->update_meta_data('_stock_ordered_nr', $bexio_data['stock_ordered_nr']);
            $woo_product_to_update->update_meta_data('_volume', $bexio_data['volume']);
            $woo_product_to_update->update_meta_data('_remarks', $bexio_data['remarks']);
            $woo_product_to_update->update_meta_data('_delivery_price', $bexio_data['delivery_price']);
            $woo_product_to_update->update_meta_data('_article_group_id', $bexio_data['article_group_id']);
            
            // save updated product
            $save_woo_product = $woo_product_to_update->save();
            $products_count++;

            if (!is_wp_error($save_woo_product)) {
              if ($is_new) {
                PingvinLogger::log('info', "woo product (id: $woo_id_to_update / name: ".$bexio_data['intern_name'].") has been successfully created.");
              } else {
                PingvinLogger::log('info', "woo product (id: $woo_id_to_update / name: ".$bexio_data['intern_name'].") has been successfully updated.");
              }
            } else {
              PingvinLogger::log('error', $save_woo_product->get_error_message());
            }
          } else {
            PingvinLogger::log('error', "Encountered an error while trying to update product (id: $woo_id_to_update / name: ".$bexio_data['intern_name']);
          }
      }


      // handle woo products that don't exist in Bexio
      $keep_woo_products = get_option('pv_bexio_productsync_options')['pv_productsync_missing_products'];
      if ($keep_woo_products === 'false') {
        foreach ($woo_products as $woo_product) {
          // if product sku is not in comparison array, delete it
          if (!in_array($woo_product->get_sku(), $bexio_sku_array) || $woo_product->get_sku() === '') {
            $del = $woo_product->delete(false);
            if (!is_wp_error($del)) {
              PingvinLogger::log('info', "Deleted product ".$woo_product->get_name());
            } else {
              PingvinLogger::log('error', $del->get_error_message());
            }
          }
        }
      }


      // set wp transients for sync status display
      set_transient('pv_bexio_connector_status', json_encode(array(
        'date' => $date_last,
        'products_count' => $products_count
      )), 0);

      set_transient('pv_bexio_connector_next', json_encode(array(
        'date' => $date_next,
      )), 0);

      PingvinLogger::log('info', "*****");
    } else {
      PingvinLogger::log('error', 'Performed syncing action, but there were no products to sync recieved from Bexio.');
    }
  }

  function pingvin_bexio_connector_sync_action_products_to_bexio() {
    // statistics for sync status:
    $interval = json_decode(get_option('pv_bexio_productsync_action_settings'))->interval;
    $time_locale = 'de_DE';
    $formatter = new \IntlDateFormatter($time_locale, \IntlDateFormatter::LONG, \IntlDateFormatter::SHORT);
    $now = new \DateTime();
    $date_last = $formatter->format($now);
    $date_next = $now->modify('+' . $interval . ' seconds');
    $date_next = $formatter->format($date_next);
    $products_count = 0;

    // get products from Bexio
    $res_products = pv_api_call('GET', '2.0/article/');
    $products = $res_products['result'];

    // get tax rates from Bexio and determine WC tax class per tax rate specified in plugin settings
    $res_taxes = pv_api_call('GET', '3.0/taxes/');
    $bexio_taxes = $res_taxes['result'];

    // income tax rates
    $bexio_tax_array = array();
    $standard_tax_rate = get_option('pv_bexio_productsync_options')['pv_productsync_tax_rate_standard_bexio'];
    $reduced_tax_rate = get_option('pv_bexio_productsync_options')['pv_productsync_tax_rate_reduced_bexio'];
    $special_tax_rate = get_option('pv_bexio_productsync_options')['pv_productsync_tax_rate_special_bexio'];
    
    foreach($bexio_taxes as $bexio_tax) {
      if ($bexio_tax->code === $standard_tax_rate) $bexio_tax_array[get_option('pv_bexio_productsync_options')['pv_productsync_tax_rate_standard_woo']] = $bexio_tax->id;
      if ($bexio_tax->code === $reduced_tax_rate) $bexio_tax_array[get_option('pv_bexio_productsync_options')['pv_productsync_tax_rate_reduced_woo']] = $bexio_tax->id;
      if ($bexio_tax->code === $special_tax_rate) $bexio_tax_array[get_option('pv_bexio_productsync_options')['pv_productsync_tax_rate_special_woo']] = $bexio_tax->id;
    }

    // expense tax rates
    $bexio_tax_array_expense = array();
    $standard_tax_rate_expense = get_option('pv_bexio_productsync_options')['pv_productsync_tax_rate_standard_bexio_expense'];
    $reduced_tax_rate_expense = get_option('pv_bexio_productsync_options')['pv_productsync_tax_rate_reduced_bexio_expense'];
    $special_tax_rate_expense = get_option('pv_bexio_productsync_options')['pv_productsync_tax_rate_special_bexio_expense'];
    
    foreach($bexio_taxes as $bexio_tax) {
      if ($bexio_tax->code === $standard_tax_rate_expense) $bexio_tax_array_expense[get_option('pv_bexio_productsync_options')['pv_productsync_tax_rate_standard_woo']] = $bexio_tax->id;
      if ($bexio_tax->code === $reduced_tax_rate_expense) $bexio_tax_array_expense[get_option('pv_bexio_productsync_options')['pv_productsync_tax_rate_reduced_woo']] = $bexio_tax->id;
      if ($bexio_tax->code === $special_tax_rate_expense) $bexio_tax_array_expense[get_option('pv_bexio_productsync_options')['pv_productsync_tax_rate_special_woo']] = $bexio_tax->id;
    }

    // get all WC products
    $woo_products = wc_get_products(array(
      'limit' => -1,
    ));

    // start syncing if there are >0 products pulled from WC
    $prod_count = count($woo_products);
    if ($prod_count> 0) {
      PingvinLogger::log('info', '_____');
      PingvinLogger::log('info', $date_last);
      PingvinLogger::log('info', "Product Sync Action (to Bexio): Pulled $prod_count products from WooCommerce");

      // get all available sku's from Bexio for matching with WC products
      $comparison_array = array();
      foreach($products as $product) {
        $sku = $product->intern_code;
        $comparison_array[$sku] = $product->id;
      }

      // Loop through WC products
      $wc_sku_array = array();
      foreach($woo_products as $woo_prod) {
        $is_new = false;
        $bexio_id_to_update = null;
        $woo_sku = $woo_prod->get_sku();

        if ($woo_sku !== '') {
          // save WC sku into wc_sku_array for later use
          array_push($wc_sku_array, $woo_sku);

          // check the comparison array if there is already a Bexio product with the same sku
          // if it exists, then update the product. If not, then create a new product
          if (!array_key_exists($woo_sku, $comparison_array)) {
            $is_new = true;
          } else {
            $bexio_id_to_update = $comparison_array[$woo_sku];
          }
        }

        // prepare dimensions of product for payload
        // weight
        $wc_weight_unit = get_option('woocommerce_weight_unit');
        $weight = null;
        if ($wc_weight_unit === 'kg') {
          $weight = floatval($woo_prod->get_weight()) * 1000;
        } else if ($wc_weight_unit === 'g') {
          $weight = floatval($woo_prod->get_weight());
        } else if ($wc_weight_unit === 'lbs') {
          $weight = floatval($woo_prod->get_weight()) / 0.453592;
        } else if ($wc_weight_unit === 'oz') {
          $weight = floatval($woo_prod->get_weight()) / 0.0283495;
        }

        // width, height
        $wc_dimesion_unit = get_option('woocommerce_dimension_unit');
        $height = null;
        $width = null;
        if ($wc_dimesion_unit === 'cm') {
          $width = floatval($woo_prod->get_width()) * 10;
          $height = floatval($woo_prod->get_height()) * 10;
        } else if ($wc_dimesion_unit === 'mm') {
          $width = floatval($woo_prod->get_width());
          $height = floatval($woo_prod->get_height());
        } else if ($wc_dimesion_unit === 'in') {
          $width = floatval($woo_prod->get_width()) / 0.393701;
          $height = floatval($woo_prod->get_height()) / 0.393701;
        }

        // prepare price and tax rate for payload
        $bexio_tax_id = null;
        if (get_option('woocommerce_calc_taxes') === 'yes' && $woo_prod->get_tax_status() === 'taxable' && count($bexio_tax_array) > 0 && $standard_tax_rate !== null && $reduced_tax_rate !== null) {
          $wc_tax_class_of_product = $woo_prod->get_tax_class();
          if ($wc_tax_class_of_product !== null) {
            $bexio_tax_id = $bexio_tax_array[$wc_tax_class_of_product];
          }
        }

        $bexio_tax_id_expense = null;
        if (get_option('woocommerce_calc_taxes') === 'yes' && $woo_prod->get_tax_status() === 'taxable' && count($bexio_tax_array) > 0 && $standard_tax_rate !== null && $reduced_tax_rate !== null) {
          $wc_tax_class_of_product = $woo_prod->get_tax_class();
          if ($wc_tax_class_of_product !== null) {
            $bexio_tax_id_expense = $bexio_tax_array_expense[$wc_tax_class_of_product];
          }
        }
        
        // payload for Bexio API
        $bexio_data = [
          "user_id" => ($user_id = $woo_prod->get_meta('_user_id')) !== '' ? $user_id : 1,
          "contact_id" => ($contact_id = $woo_prod->get_meta('_contact_id')) !== '' ? $contact_id : null,
          "deliverer_code" => ($deliverer_code = $woo_prod->get_meta('_deliverer_code')) !== '' ? $deliverer_code : null,
          "deliverer_name" => ($deliverer_name = $woo_prod->get_meta('_deliverer_name')) !== '' ? $deliverer_name : null,
          "deliverer_description" => ($deliverer_description = $woo_prod->get_meta('_deliverer_description')) !== '' ? $deliverer_description : null,
          "intern_code" => $intern_code = $woo_prod->get_sku(),
          "intern_name" => $woo_prod->get_name(),
          "intern_description" => ($intern_description = $woo_prod->get_meta('_intern_description')) !== '' ? $intern_description : null,
          "purchase_price" => ($purchase_price = $woo_prod->get_meta('_purchase_price')) !== '' ? $purchase_price : 0,
          "sale_price" => $woo_prod->get_regular_price(),
          "purchase_total" => ($purchase_total = $woo_prod->get_meta('_purchase_total')) !== '' ? $purchase_total : 0,
          "sale_total" => ($sale_total = $woo_prod->get_meta('_sale_total')) !== '' ? $sale_total : 0,
          "currency_id" => ($currency_id = $woo_prod->get_meta('_currency_id')) !== '' ? $currency_id : 1,
          "tax_income_id" => $bexio_tax_id,
          "tax_expense_id" => $bexio_tax_id_expense,
          "unit_id" => ($unit_id = $woo_prod->get_meta('_unit_id')) !== '' ? $unit_id : null,
          "is_stock" => $woo_prod->get_manage_stock(),
          "stock_id" => ($stock_id = $woo_prod->get_meta('_stock_id')) !== '' ? $stock_id : null,
          "stock_place_id" => ($stock_place_id = $woo_prod->get_meta('_stock_place_id')) !== '' ? $stock_place_id : null,
          "stock_min_nr" => ($stock_min_nr = $woo_prod->get_low_stock_amount()) !== '' ? $woo_prod->get_low_stock_amount() : 0,
          "width" => ($width_ = $width) !== '' ? $width_ : null,
          "height" => ($height_ = $height) !== '' ? $height_ : null,
          "weight" => ($weight_ = $weight) !== '' ? $weight_ : null,
          "volume" => ($volume = $woo_prod->get_meta('_volume')) !== '' ? $volume : null,
          "remarks" => ($remarks = $woo_prod->get_meta('_remarks')) !== '' ? $remarks : null,
          "delivery_price" => ($delivery_price = $woo_prod->get_meta('_delivery_price')) !== '' ? $delivery_price : null,
          "article_group_id" => ($article_group_id = $woo_prod->get_meta('_article_group_id')) !== '' ? $article_group_id : null,
          "account_id" => ($account_id = $woo_prod->get_meta('_account_id')) !== '' ? $account_id : null,
          "expense_account_id" => ($expense_account_id = $woo_prod->get_meta('_expense_account_id')) !== '' ? $expense_account_id : null,
        ];

        // if is_new is false, then update the product in Bexio
        if ($is_new === false) {
          $res = pv_api_call('POST', '2.0/article/'.$bexio_id_to_update.'/', json_encode($bexio_data));
          if ($res['status'] === 200) {
            $products_count++;
            PingvinLogger::log('info', "Product updated in Bexio: ".$bexio_data['intern_name']);
          } else {
            PingvinLogger::log('error', "Could not update product in Bexio: ".$bexio_data['intern_name']);
            PingvinLogger::log('error', $res['status'].': '.print_r($res['result']->errors[0], true));
          }
        }

        // if is_new is true, then create a new product in Bexio
        if ($is_new === true) {
          $bexio_data['article_type_id'] = ($article_type_id = $woo_prod->get_meta('_article_type_id')) !== '' ? $article_type_id : 1;
          $bexio_data['stock_nr'] = ($stock_nr = $woo_prod->get_stock_quantity()) !== null ? $stock_nr : 0;

          $res = pv_api_call('POST', '2.0/article/', json_encode($bexio_data));
          if ($res['status'] === 200) {
            $products_count++;
            PingvinLogger::log('info', "Product created in Bexio: ".$bexio_data['intern_name']);
          } else {
            PingvinLogger::log('error', "Could not create product in Bexio: ".$bexio_data['intern_name']);
            PingvinLogger::log('error', $res['status'].': '.$res['errors']);
          }
        }       
      }

      // handle woo products that don't exist in Bexio
      $keep_bexio_products = get_option('pv_bexio_productsync_options')['pv_productsync_missing_products'];
      if ($keep_bexio_products === 'false') {
        foreach ($products as $product) {
          // if product sku is not in comparison array, delete it
          if (!in_array($product->intern_code, $wc_sku_array) || $product->intern_code === '') {
            $del_id = $product->id;

            $res = pv_api_call('DELETE', '2.0/article/'.$del_id.'/');
            if ($res['status'] === 200) {
              PingvinLogger::log('info', "Deleted product in Bexio: ".$product->intern_name);
            } else {
              PingvinLogger::log('error', "Could not delete product in Bexio: ".$product->intern_name);
              PingvinLogger::log('error', $res['status'].': '.$res['errors']);
            }
          }
        }
      }


      // set wp transients for sync status display
      set_transient('pv_bexio_connector_status', json_encode(array(
        'date' => $date_last,
        'products_count' => $products_count
      )), 0);

      set_transient('pv_bexio_connector_next', json_encode(array(
        'date' => $date_next,
      )), 0);

      PingvinLogger::log('info', "*****");
    }
  }

}