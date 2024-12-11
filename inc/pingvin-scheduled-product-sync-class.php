<?php 

class PvLoonitySync {

  public function __construct() {
    add_action( 'update_option_pv_loonity_sync_options', array($this, 'pv_loonity_sync_options_updated'), 10, 2 );  
    add_action( 'pingvin_loonity_connector_sync', array($this, 'pingvin_loonity_connector_sync_action_products')); 
    add_action( 'pingvin_loonity_connector_sync', array($this, 'pingvin_loonity_connector_sync_action_producers'));
  }

  public function  sideload_image_to_product($image_url, $product_id) {
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');

    // Download the image and add it to the WordPress media library
    $media_id = media_sideload_image($image_url, $product_id, null, 'id');

    if (is_wp_error($media_id)) {
        // Handle errors
        error_log('Error sideloading image: ' . $media_id->get_error_message());
        return false;
    }

    // Set the image as the product's featured image
    set_post_thumbnail($product_id, $media_id);

    return true;
  }

  public function remove_featured_image($post_id) {
    // Get the ID of the featured image
    $featured_image_id = get_post_thumbnail_id($post_id);

    if ($featured_image_id) {
      // Delete the featured image from the Media Library
      wp_delete_attachment($featured_image_id, true);

      // Remove the association of the featured image with the post
      delete_post_meta($post_id, '_thumbnail_id');

      return;
    } else {
      error_log("No featured image found for this post.");
      return;
    }
  }

  public function check_featured_image($post_id) {
    $featured_image = false;
    $featured_image_id = get_post_thumbnail_id($post_id);
    if ($featured_image_id && get_post($featured_image_id) && wp_attachment_is_image($featured_image_id)) {
      $featured_image = true;
    }
    return $featured_image;  
  }

  /**
   * Update scheduled task when 'pv_loonity_sync_options' is changed
   */
  public function pv_loonity_sync_options_updated( $old_value, $new_value ) {
    if ($old_value != $new_value)  {
      PingvinLogger::log('info', '`pv_loonity_sync_options` changed from '.$old_value.' to '.$new_value);
      $updated_options = json_decode($new_value);

      // check if sync is enabled or disabled
      $sync_enabled = $updated_options->enabled;

      if ($sync_enabled) {
        // handle action scheduler task
        // 1. check if there is a scheduled action. If yes, unschedule it
        $existing_scheduled_action = as_has_scheduled_action('pingvin_loonity_connector_sync');

        if ($existing_scheduled_action) {
          // unschedule the existing action
          $unscheduled_action = as_unschedule_all_actions('pingvin_loonity_connector_sync');
          
          // logging results
          if (!is_wp_error($unscheduled_action)) {
            PingvinLogger::log('info', 'scheduled action `pingvin_loonity_connector_sync` has been successfully UNscheduled.');
          } else {
            PingvinLogger::log('error', $unscheduled_action->get_error_message());
          }
        }
        
        // 2. schedule new action with updated options
        $new_scheduled_action = as_schedule_recurring_action( strtotime('now'), (int)$updated_options->interval, 'pingvin_loonity_connector_sync', array(), '', true, 1 );

        // update transient for next sync
        $time_locale = 'de_DE';
        $formatter = new IntlDateFormatter($time_locale, IntlDateFormatter::LONG, IntlDateFormatter::SHORT);  
        $now = new DateTime();
        $date_next = $now->modify('+' . (int)$updated_options->interval . ' seconds');
        $date_next = $formatter->format($date_next);
        $market_id = get_option('loonity_connector_options')['loonity_market_id'];
        $locale = get_option('loonity_connector_options')['loonity_locale'];

        set_transient('pv_loonity_connector_next', json_encode(array(
          'date' => $date_next,
          'market_id' => $market_id,
          'locale' => $locale
        )), 0);

        // logging results
        if (!is_wp_error($new_scheduled_action)) {
          PingvinLogger::log('info', 'new scheduled action `pingvin_loonity_connector_sync` with interval '.(int)$updated_options->interval).' has been successfully scheduled.';
        } else {
          PingvinLogger::log('error', $new_scheduled_action->get_error_message());
        }

      } else {
        // check if there is a scheduled action. If yes, unschedule it
        $existing_scheduled_action = as_has_scheduled_action('pingvin_loonity_connector_sync');

        if ($existing_scheduled_action) {
          // unschedule the existing action
          $unscheduled_action = as_unschedule_all_actions('pingvin_loonity_connector_sync');

          // remove transient for next sync
          delete_transient( 'pv_loonity_connector_next' );
          
          // logging results
          PingvinLogger::log('info', 'sync has been disabled.');
          if (!is_wp_error($unscheduled_action)) {
            PingvinLogger::log('info', 'scheduled action `pingvin_loonity_connector_sync` has been successfully UNscheduled.');
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
  
  function pingvin_loonity_connector_sync_action_products() {
    // loonity vars
    include plugin_dir_path( __FILE__ ).'../assets/pingvin-loonity-vars.php';
    $loonity_countries = json_decode(file_get_contents(plugin_dir_path( __FILE__ ).'../assets/countries.json'),true);

    // statistics for sync status:
    $interval = json_decode(get_option('pv_loonity_sync_options'))->interval;
    $time_locale = 'de_DE';
    $formatter = new IntlDateFormatter($time_locale, IntlDateFormatter::LONG, IntlDateFormatter::SHORT);
    $now = new DateTime();
    $date_last = $formatter->format($now);
    $date_next = $now->modify('+' . $interval . ' seconds');
    $date_next = $formatter->format($date_next);
    $market_id = get_option('loonity_connector_options')['loonity_market_id'];
    $locale = get_option('loonity_connector_options')['loonity_locale'];
    $products_count = 0;

    // get products from Loonity
    $res = pv_api_call('GET', 'market/'.$market_id.'/proudcts');
    $products = $res['result'];
    $prod_count = count($products);

    if ($prod_count> 0) {
      PingvinLogger::log('info', '_____');
      PingvinLogger::log('info', $date_last);
      PingvinLogger::log('info', "Product Sync Action: Pulled $prod_count products from Loonity");

      // if there are Loonity products to sync, get all woo products
      $woo_products = wc_get_products([]);

      // loop through all the products and build an array with loonity id referencing woo id 
      $comparison_array = array();
      foreach($woo_products as $woo_product) {
        $loonity_id_in_woo = $woo_product->get_meta('_loonity_id');

        if (!empty($loonity_id_in_woo)) {
          $comparison_array[$loonity_id_in_woo] = $woo_product->get_id();
        }
      }  

      $loonity_variant_ids = array();
      // Loop through Loonity products
      foreach($products as $prod) {
        // get variants of the product
        $variants = $prod->variants;
        // loop through variants, then for each variant, build the product array to sync to woocommerce as simple products
        foreach($variants as $variant) {
          // build array of all loonity variant ids for later use
          array_push($loonity_variant_ids, $variant->id);

          // product array
          $loonity_data = [
            'id' => $prod->id,
            'name' => $prod->translations->$locale->name,
            'description' => $prod->translations->$locale->description,
            'slug' => $prod->translations->$locale->slug,
            'image' => isset($prod->image->image->img) ? $prod->image->image->img : null,
            'certifications' => json_encode($prod->certifications),
            'diet' => json_encode($prod->extra->diet),
            'producer_name' => $prod->producer_name,
            'category' => $prod->category->translations->$locale->name,
            'category__parent' => $prod->category__parent->translations->$locale->name,
            'macrocategory' => $prod->category__macrocategory->translations->$locale->name,
            'transparency' => isset($prod->extra->transparency->$locale) ? json_encode($prod->extra->transparency->$locale) : null,
            'transparencyPricePart' => isset($prod->extra->transparency->pricePart) ? json_encode($prod->extra->transparency->pricePart) : null,
            'variant_id' => $variant->id,
            'variant_name' => $variant->translations->$locale->name,
            'variant_image' => isset($variant->image->image) ? $variant->image->image->img : null,
            'variant_sku' => $variant->producer_code,
            'supplier_name' => $prod->producer_name,
            'supplier_description' => $prod->producer_name,
            'variant_price' => $variant->price,
            'variant_unit' => isset($loonity_units[$locale][$variant->price_unit]) ? $loonity_units[$locale][$variant->price_unit] : null,
            'variant_lot' => 1,
            'variant_tax' => isset($loonity_tax_rates[$locale][$variant->tax_rate]) ? $loonity_tax_rates[$locale][$variant->tax_rate] : null,
            'variant_weight' => $variant->price_len,
            'variant_origin' => isset($variant->production_type_nation) ? $loonity_countries[$locale][$variant->production_type_nation] : $loonity_countries[$locale]['CH'],
            'intermediary_trade' => $prod->extra->supplier->type ? 'true' : 'false',
            'original_producer' => isset($prod->extra->supplier) ? $prod->extra->supplier->name : null,
            'original_producer_description' => isset($prod->extra->supplier) ? $prod->extra->supplier->description : null,
            'variant_production_type' => '', //tbd
            'variant_availability_seasonal' => '', //tbd
          ];


          // handle variant images
          if (!$loonity_data['variant_image'] && $loonity_data['image']) {
            $loonity_data['variant_image'] = $loonity_data['image'];
          }

          
          // check the comparison array if there is already a woocommerce product with the same loonity variant id
          // if it exists, then update the product, if not, then create a new woo product
          $is_new = false;
          if (array_key_exists($variant->id, $comparison_array)) {
            $woo_id_to_update = $comparison_array[$variant->id];
            $woo_product_to_update = wc_get_product($woo_id_to_update);
          } else {
            $is_new = true;
            $woo_product_to_update = new WC_Product_Simple();
            $woo_product_to_update->save();
            $woo_id_to_update = $woo_product_to_update->get_id();
          }

          if ($woo_product_to_update) {
            $woo_product_to_update->set_name($loonity_data['variant_name']);
            $woo_product_to_update->set_description($loonity_data['description']);
            $woo_product_to_update->set_sku($loonity_data['variant_sku']);
            $woo_product_to_update->update_meta_data('_certifications', $loonity_data['certifications']);
            $woo_product_to_update->update_meta_data('_diet', $loonity_data['diet']);
            $woo_product_to_update->update_meta_data('_produzent', $loonity_data['producer_name']);
            $woo_product_to_update->update_meta_data('_lieferant', $loonity_data['supplier_name']);
            $woo_product_to_update->update_meta_data('_herkunft', $loonity_data['variant_origin']);
            $woo_product_to_update->update_meta_data('_gebinde', $loonity_data['variant_lot']);
            $woo_product_to_update->update_meta_data('_einheit', $loonity_data['variant_unit']); //tbc
            $woo_product_to_update->update_meta_data('_transparency', $loonity_data['transparency']);    
            $woo_product_to_update->update_meta_data('_transparencyPricePart', $loonity_data['transparencyPricePart']);            
            $woo_product_to_update->update_meta_data('_loonity_id', $loonity_data['variant_id']);     
            $woo_product_to_update->update_meta_data('_loonity_slug', $loonity_data['slug']);    
            $woo_product_to_update->update_meta_data('_intermediary_trade', $loonity_data['intermediary_trade']);    
            $woo_product_to_update->update_meta_data('_original_producer', $loonity_data['original_producer']);    
            $woo_product_to_update->update_meta_data('_original_producer_description', $loonity_data['original_producer_description']);   
            $woo_product_to_update->set_regular_price($loonity_data['variant_price']);
            $woo_product_to_update->set_weight($loonity_data['variant_weight']);
            
            // if category exists in woo: remove all categories from product and add the ones from woo
            // if category does not exist in woo: create it first
            $category_ids = [];
            $category = get_term_by('name', $loonity_data['category__parent'], 'product_cat');
            if (!$category) {
              $category = wp_insert_term($loonity_data['category__parent'], 'product_cat');
              PingvinLogger::log('info', "woo category ".$loonity_data['category__parent']." has been created successfully");
            }
            array_push($category_ids, $category->term_id);
            $woo_product_to_update->set_category_ids($category_ids);

            // handle image 
            if ($loonity_data['variant_image']) {
              // if the product is new, then import the image and skip all other tests
              if ($is_new) {
                $this->sideload_image_to_product($loonity_data['variant_image'], $woo_id_to_update);
              } else {
                // check if product has featured image
                $featured_image = $this->check_featured_image($woo_id_to_update);
              
                // if product has no featured image, import via sideloading
                if (!$featured_image) {
                  $this->sideload_image_to_product($loonity_data['variant_image'], $woo_id_to_update);
                } 
                // if product has a featured image, compare md5 hashes of loonity image and existing image to see if it has changed
                else {
                  // get wp img data
                  $contents_existing_image = wp_remote_get(get_the_post_thumbnail_url($woo_id_to_update, 'full'));
                  if (is_wp_error($contents_existing_image)) {
                      PingvinLogger::log('error', 'HTTP Request Failed: ' . $contents_existing_image->get_error_message());
                      PingvinLogger::log('error', print_r($contents_existing_image,true));
                  } else {
                      $contents_existing_image = wp_remote_retrieve_body($contents_existing_image);
                  }

                  // get loonity img data
                  $contents_featured_image = wp_remote_get($loonity_data['variant_image']);
                  if (is_wp_error($contents_featured_image)) {
                      PingvinLogger::log('error', 'HTTP Request Failed: ' . $contents_featured_image->get_error_message());
                      PingvinLogger::log('error', print_r($contents_featured_image,true));
                  } else {
                      $contents_featured_image = wp_remote_retrieve_body($contents_featured_image);
                  }

                  
                  // Check if both images were successfully fetched
                  if (is_wp_error($contents_existing_image) || is_wp_error($contents_featured_image)) {
                    PingvinLogger::log('error', "Error fetching one or both images.");
                  } else {
                    // Calculate MD5 hashes
                    $hash1 = md5($contents_existing_image);
                    $hash2 = md5($contents_featured_image);

                    // Compare the hashes
                    if ($hash1 !== $hash2) {
                      // remove old image form media library
                      $this->remove_featured_image($woo_id_to_update);
                      
                      // import new image into media library
                      $this->sideload_image_to_product($loonity_data['variant_image'], $woo_id_to_update);
                    }
                  }
                }
              }
            // if image is missing form loonity, but product is not new
            } else {
              if (!$is_new) {
                // check if product has featured image
                $featured_image = $this->check_featured_image($woo_id_to_update);

                if ($featured_image) {
                  // remove old image form media library
                  $this->remove_featured_image($woo_id_to_update);
                } 
              }
            }            

            // handle vat tax rates
            $loonity_standard_tax_rate = get_option('loonity_connector_options')['loonity_tax_standard'];
            $loonity_reduced_tax_rate = get_option('loonity_connector_options')['loonity_tax_reduced'];
            $tax_class_to_set = $loonity_tax_rates[$locale][$variant->tax_rate];
            
            if ($loonity_standard_tax_rate !== null && $loonity_reduced_tax_rate !== null && $tax_class_to_set !== null) {
              $tax_set = $woo_product_to_update->set_tax_class($tax_class_to_set); // set the tax rate
              if (is_wp_error($tax_set)) {
                PingvinLogger::log('error', "Could not set the tax rate of product: ".$tax_set->get_error_message());
              }
            } else {
              PingvinLogger::log('error', "Could not set the tax rate of product ".$loonity_data['variant_name']);
            }
            

            // save updated product
            $save_woo_product = $woo_product_to_update->save();
            $products_count++;

            if (!is_wp_error($save_woo_product)) {
              if ($is_new) {
                PingvinLogger::log('info', "woo product (id: $woo_id_to_update / name: ".$loonity_data['variant_name'].") has been successfully created.");
              } else {
                PingvinLogger::log('info', "woo product (id: $woo_id_to_update / name: ".$loonity_data['variant_name'].") has been successfully updated.");
              }
            } else {
              PingvinLogger::log('error', $save_woo_product->get_error_message());
            }
          } else {
            PingvinLogger::log('error', "Encountered an error while trying to update product (id: $woo_id_to_update / name: ".$loonity_data['variant_name']);
          }
        }
      }


      // handle woo products that don't exist in loonity
      $keep_woo_products = get_option('loonity_connector_options')['loonity_missing_products'];
      if ($keep_woo_products === 'false') {
        foreach ($woo_products as $woo_product) {
          // if product has no loonity id meta, delete it
          // if product has a loonity id meta, but the loonity id is not pulled from loonity, delete it
          if (empty($woo_product->get_meta('_loonity_id')) || !in_array($woo_product->get_meta('_loonity_id'), $loonity_variant_ids)) {
            // exceptions for special POT Plugin products
            if ($woo_product->get_sku() !== 'fcplugin_instant_topup_product' && $woo_product->get_sku() !== 'fcplugin_pos_product') {
              $del = $woo_product->delete(false);
              if (!is_wp_error($del)) {
                PingvinLogger::log('info', "Deleted product ".$woo_product->get_name());
              } else {
                PingvinLogger::log('error', $del->get_error_message());
              }
            }
          }
        }
      }


      // set wp transients for sync status display
      set_transient('pv_loonity_connector_status', json_encode(array(
        'date' => $date_last,
        'market_id' => $market_id,
        'locale' => $locale,
        'products_count' => $products_count
      )), 0);

      set_transient('pv_loonity_connector_next', json_encode(array(
        'date' => $date_next,
        'market_id' => $market_id,
        'locale' => $locale
      )), 0);

      PingvinLogger::log('info', "*****");
    } else {
      PingvinLogger::log('error', 'Performed syncing action, but there were no products to sync recieved from Loonity.');
    }
  }




  /**
   * Scheduled syncing action task to update producers / create new producers
   */
  
   function pingvin_loonity_connector_sync_action_producers() {
    // loonity vars
    $loonity_countries = json_decode(file_get_contents(plugin_dir_path( __FILE__ ).'../assets/countries.json'),true);

    $market_id = get_option('loonity_connector_options')['loonity_market_id'];
    $locale = get_option('loonity_connector_options')['loonity_locale'];

    // get products from Loonity

    //$res = pv_api_call('GET', 'market/'.$market_id.'/producers');
    //$producers = $res['result'];

    // dummy data
    $producers = json_decode('[
      {
          "id": 3,
          "is_active": true,
          "extra": {
              "transparency": {
                  "soil": "",
                  "team": "",
                  "type": "",
                  "cycles": "",
                  "animals": "",
                  "climate": "",
                  "biodiversity": "",
                  "diversification": "",
                  "regionalwertUrl": ""
              }
          },
          "name": "huulu GmbH",
          "tel": "+417864482924",
          "url": "www.food-depot.ch",
          "info": null,
          "info_extended": null,
          "info_contact": "huulu gmbh\nIndustriestrasse 30\n8604 Volketswil\ninfo@huulu.ch\n044 500 02 97",
          "desc_zone": "Zürich, Bern, Basel, Aarau, Olten",
          "desc_short": "Unsere Produkte zeichnen sich aus durch Fairtrade, ökologische Produktion und minimale Verpackung. Wir setzen uns ein für faire Bedingungen in der gesamten Produktionskette: vom Hof bis zum Teller.",
          "address": "huulu GmbH , Industriestrasse 30, Volketswil, 8604 CH",
          "logo_square": "https://minio-wordpress-review.apps.dev.addvalyou.ch/loonity-media/IMAGES/ROLE/00000003/Gallery_IMG_8Rrz8ZC.jpeg?X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=minio%2F20241127%2Fus-east-1%2Fs3%2Faws4_request&X-Amz-Date=20241127T072523Z&X-Amz-Expires=604800&X-Amz-SignedHeaders=host&X-Amz-Signature=ca80e8b1c3b30ea08eca8fe87de6df1e44e1cc8ad77a813d773c0745b16b0eb6",
          "country_id": "CH",
          "tax_number": "CHE-264.714.008 MWST"
      },
      {
          "id": 4,
          "is_active": true,
          "extra": {
              "transparency": {
                  "soil": "soil info",
                  "team": "team info",
                  "type": "type of business",
                  "cycles": "cycles info",
                  "animals": "animals info",
                  "climate": "climate info",
                  "biodiversity": "biodiversity info",
                  "diversification": "diversification info",
                  "regionalwertUrl": "regionalwert info"
              }
          },
          "name": "maxapatabrazala gmbh",
          "tel": "+41786448292",
          "url": "www.food-depot.ch",
          "info": null,
          "info_extended": null,
          "info_contact": "maxapatabrazala gmbh\nIndustriestrasse 30\n8604 Volketswil\ninfo@huulu.ch\n044 500 02 97",
          "desc_zone": "Zürich, Bern, Basel, Aarau, Olten",
          "desc_short": "Unsere Produkte zeichnen sich aus durch Fairtrade, ökologische Produktion und minimale Verpackung. Wir setzen uns ein für faire Bedingungen in der gesamten Produktionskette: vom Hof bis zum Teller.",
          "address": "maxapatabrazala gmbh , Industriestrasse 30, Volketswil, 8604 CH",
          "logo_square": "",
          "country_id": "CH",
          "tax_number": ""
      },
      {
          "id": 11,
          "name": "StadtLand Winti",
          "country_id": "CH",
          "tel": "+41793908594",
          "url": "https://www.stadtlandwinti.ch",
          "tax_exempt": false,
          "is_active": true,
          "tax_number": null,
          "address": "StadtLand Winti , Zünikon 67, Bertschikon, 8543 CH",
          "info_contact": "StadtLand Winti\nbio-dynamischer Landbau\nZünikon 67\n8543 Bertschikon\nTel. 079 390 85 94\ninfo@stadtlandwinti.ch",
          "desc_zone": "In und um Winterthur oder Bezug ab Hof.",
          "desc_short": "Seit Anfang 2019 bewirtschaften wir unseren Hof mit zwei Standorten, Zünikon und Neftenbach. An beiden Standorten wachsen Hochstamm-Obstbäume auf extensiven Wiesen und auf den Ackerflächen werden bis zu neun verschiedene Ackerkulturen kultiviert.",
          "logo_square": "https://minio-wordpress-review.apps.dev.addvalyou.ch/loonity-media/IMAGES/ROLE/00000011/Gallery_IMG_6ybLi7h.jpeg?X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=minio%2F20241127%2Fus-east-1%2Fs3%2Faws4_request&X-Amz-Date=20241127T093259Z&X-Amz-Expires=604800&X-Amz-SignedHeaders=host&X-Amz-Signature=86a736b1a98805e8b2425fa0bdb9b68425969c8bc254c7599c34ba3e940da4c7",
          "extra": {
              "transparency": {
                  "soil": "Auf den ackerfähigen Parzellen werden verschiedene Getreidearten, Hirse, Lein und Linsen angebaut. Wir arbeiten strikt ohne externe Nährstoffzufuhr und wollen in erster Linie Kulturen anbauen, die direkt für menschliche Nahrung bestimmt sind. Die Ackerfrüchte werden zu einem grossen Teil in diverse Produkte wie Brot, Mehl, Körner, Leinsamen verarbeitet, direkt verkauft oder an Läden und Restaurants in der Region geliefert.",
                  "team": "Unsere Geschenkkörbe werden mit unseren Produkten vom Hof liebevoll gefüllt. Einzelne ausgewählte Produkte von Partner* innen werden ebenfalls gerne eingesetzt. Wir dekorieren die Körbe je nach Saison passend mit Baumnüssen, Tannenzweigen und Kräutern dazu kommt ein Trockenblumensträusschen. Die Blumen stammen hauptsächlich von uns.",
                  "type": "In Winti West bewirtschaften wir in zweiter Generation das seit 1989 nach biologisch-dynamischen Richtlinien bewirtschaftete Ackerland, Ökoflächen und Hochstamm-Obstgärten.",
                  "cycles": "In Neftenbach wird das in den letzten Jahren erprobte bodenschonende Mulchsaatverfahren auf tieferem Nährstoffniveau weiterverfolgt. Die bisher angebauten Kulturen wie Dinkel, Lein, Emmer, Hirse, Roggen, Ackerbohnen/ Linsen werden weiterhin kultiviert.",
                  "animals": "Auf den schweren Böden in Zünikon wird mit „klassischem“ pflügen gearbeitet und so der eigene Hofdünger (von den Kühen) in den Boden eingearbeitet. Darum sind die Erträge tendenziell höher als in Neftenbach.",
                  "climate": "",
                  "biodiversity": "Mehr als ein Drittel der Betriebsfläche gehört zum „oekologischen Ausgleich“. Diese Wiesen werden extensiv bewirtschaftet, d.h. keine Düngung und sie werden erst später gemäht. Auf diesen Flächen können sich auch spätblühende Arten vermehren und zahlreiche Insekten und Kleintiere finden Nahrung.",
                  "diversification": "In Zünikon befindet sich seit Januar 2019 unser Betriebszentrum mit Stall und Verarbeitungsräumen. Dort befindet sich nebst Ackerflächen und wunderschönen Blumenwiesen und unser Abholdepot",
                  "regionalwertUrl": ""
              }
          }
      }
    ]');

    $prod_count = count($producers);

    if ($prod_count> 0) {
      // if there are Loonity producers to sync, get all wp producers (custom post type: producers)
      $wp_producers = get_posts([
        'post_type' => 'producers',
        'post_status' => 'publish',
        'numberposts' => -1
      ]);

      // loop through all the products and build an array with loonity id referencing wp id 
      $comparison_array = array();
      foreach($wp_producers as $wp_producer) {
        $loonity_id_in_wp = get_post_meta( $wp_producer->ID, 'loonity_id', true );

        if (!empty($loonity_id_in_wp)) {
          $comparison_array[$loonity_id_in_wp] = $wp_producer->ID;
        }
      }     

      $loonity_producer_ids = array();
      // Loop through Loonity producers
      foreach($producers as $producer) {
        // build array of all loonity producer ids for later use
        array_push($loonity_producer_ids, $producer->id);

        // producer array
        $loonity_data = [
          'id' => $producer->id,
          'name' => $producer->name,
          'tel' => $producer->tel,
          'url' => $producer->url,
          'info_contact' => $producer->info_contact,
          'desc_zone' => $producer->desc_zone,
          'desc_short' => $producer->desc_short,
          'address' => $producer->address,
          'transparency' => $producer->extra->transparency,
          'tax_number' => $producer->tax_number,
          //'logo_rect' => $producer->logo_rect,
          'logo_square' => $producer->logo_square,
          'country_id' => $producer->country_id
        ];    
          
        // check the comparison array if there is already a wp producer with the same loonity variant id
        // if it exists, then update the producer, if not, then create a new producer
        $is_new = false;
        if (array_key_exists($loonity_data['id'], $comparison_array)) {
          $wp_id_to_update = $comparison_array[$loonity_data['id']];
          $wp_producer_to_update = get_post($wp_id_to_update);

          $wp_producer_args = array(
            'ID'           => $wp_id_to_update,
            'post_title'   => $loonity_data['name'],
            'post_content' => $loonity_data['desc_short'],
            'post_status'  => 'publish',
            'post_author'  => 1,
            'post_type'    => 'producers',
          );
          
          $update_post = wp_update_post($wp_producer_args, true);

          update_post_meta($wp_id_to_update, 'origin', $loonity_countries['de'][$loonity_data['country_id']]);
          update_post_meta($wp_id_to_update, 'website', $loonity_data['url']);
          update_post_meta($wp_id_to_update, 'loonity_id', $loonity_data['id']);
          update_post_meta($wp_id_to_update, 'tel', $loonity_data['tel']);
          update_post_meta($wp_id_to_update, 'info_contact', $loonity_data['info_contact']);
          update_post_meta($wp_id_to_update, 'desc_zone', $loonity_data['desc_zone']);
          update_post_meta($wp_id_to_update, 'address', $loonity_data['address']);
          update_post_meta($wp_id_to_update, 'transparency', json_encode($loonity_data['transparency']));
          update_post_meta($wp_id_to_update, 'tax_number', $loonity_data['tax_number']);

          PingvinLogger::log('info', 'Producer '.$loonity_data['name'].' successfully updated.');

        } else {
          $is_new = true;
          $wp_producer_args = array(
            'post_title'   => $loonity_data['name'],
            'post_content' => $loonity_data['desc_short'],
            'post_status'  => 'publish',
            'post_author'  => 1,
            'post_type'    => 'producers',
          );
          
          $wp_id_to_update = wp_insert_post($wp_producer_args);

          update_post_meta($wp_id_to_update, 'origin', $loonity_countries['de'][$loonity_data['country_id']]);
          update_post_meta($wp_id_to_update, 'website', $loonity_data['url']);
          update_post_meta($wp_id_to_update, 'loonity_id', $loonity_data['id']);
          update_post_meta($wp_id_to_update, 'tel', $loonity_data['tel']);
          update_post_meta($wp_id_to_update, 'info_contact', $loonity_data['info_contact']);
          update_post_meta($wp_id_to_update, 'desc_zone', $loonity_data['desc_zone']);
          update_post_meta($wp_id_to_update, 'address', $loonity_data['address']);
          update_post_meta($wp_id_to_update, 'transparency', $loonity_data['transparency']);
          update_post_meta($wp_id_to_update, 'tax_number', $loonity_data['tax_number']);

          $wp_producer_to_update = get_post($wp_id_to_update);

          PingvinLogger::log('info', 'Producer '.$loonity_data['name'].' successfully created.');
        }

        // handle image 
        if ($loonity_data['logo_square']) {
          // if the producer is new, then import the image and skip all other tests
          if ($is_new) {
            $this->sideload_image_to_product($loonity_data['logo_square'], $wp_id_to_update);
          } else {
            // check if producer has featured image
            $featured_image = $this->check_featured_image($wp_id_to_update);
          
            // if producer has no featured image, import via sideloading
            if (!$featured_image) {
              $this->sideload_image_to_product($loonity_data['logo_square'], $wp_id_to_update);
            } 
            // if producer has a featured image, compare md5 hashes of loonity image and existing image to see if it has changed
            else {
              // get wp img data
              $contents_existing_image = wp_remote_get(get_the_post_thumbnail_url($wp_id_to_update, 'full'));
              if (is_wp_error($contents_existing_image)) {
                  PingvinLogger::log('error', 'HTTP Request Failed: ' . $contents_existing_image->get_error_message());
                  PingvinLogger::log('error', print_r($contents_existing_image,true));
              } else {
                  $contents_existing_image = wp_remote_retrieve_body($contents_existing_image);
              }

              // get loonity img data
              $contents_featured_image = wp_remote_get($loonity_data['logo_square']);
              if (is_wp_error($contents_featured_image)) {
                  PingvinLogger::log('error', 'HTTP Request Failed: ' . $contents_featured_image->get_error_message());
                  PingvinLogger::log('error', print_r($contents_featured_image,true));
              } else {
                  $contents_featured_image = wp_remote_retrieve_body($contents_featured_image);
              }

              
              // Check if both images were successfully fetched
              if (is_wp_error($contents_existing_image) || is_wp_error($contents_featured_image)) {
                PingvinLogger::log('error', "Error fetching one or both images.");
              } else {
                // Calculate MD5 hashes
                $hash1 = md5($contents_existing_image);
                $hash2 = md5($contents_featured_image);

                // Compare the hashes
                if ($hash1 !== $hash2) {
                  // remove old image form media library
                  $this->remove_featured_image($wp_id_to_update);
                  
                  // import new image into media library
                  $this->sideload_image_to_product($loonity_data['logo_square'], $wp_id_to_update);
                }
              }
            }
          }
        // if image is missing form loonity, but product is not new
        } else {
          if (!$is_new) {
            // check if product has featured image
            $featured_image = $this->check_featured_image($wp_id_to_update);

            if ($featured_image) {
              // remove old image form media library
              $this->remove_featured_image($wp_id_to_update);
            } 
          }
        } 
      }

      // handle wp producers that don't exist in loonity
      $keep_wp_producers = get_option('loonity_connector_options')['loonity_missing_producers'];
      if ($keep_wp_producers === 'false') {
        foreach ($wp_producers as $wp_producer) {
          // if producer has no loonity id meta, delete it
          // if producer has a loonity id meta, but the loonity id is not pulled from loonity, delete it
          if (empty(get_post_meta( $wp_producer->ID, 'loonity_id', true )) || !in_array(get_post_meta( $wp_producer->ID, 'loonity_id', true ), $loonity_producer_ids)) {
            $del = wp_delete_post($wp_producer->ID);
            if (!is_wp_error($del)) {
              PingvinLogger::log('info', "Deleted producer ".$wp_producer->post_title);
            } else {
              PingvinLogger::log('error', $del->get_error_message());
            }
          }
        }
      }

      PingvinLogger::log('info', "*****");
    } else {
      PingvinLogger::log('error', 'Performed syncing action, but there were no producers to sync recieved from Loonity.');
    }
  }
}