<?php

class PvLoonityProductData {

  public function __construct() {
    add_filter( 'woocommerce_product_data_tabs', array($this, 'pingvin_loonity_product_info_tab'), 10, 1 );
    add_action( 'woocommerce_product_data_panels', array($this, 'pingvin_loonity_product_info_tab_display') );  
    add_filter( 'woocommerce_product_tabs', array($this, 'pingvin_additional_product_info_tab') );
  }

  /**
   * Frontend loonity product data tab
   */
  public function pingvin_additional_product_info_tab( $tabs ) {
    $tabs['attrib_desc_tab'] = array(
        'title'     => __( 'Loonity Data', 'pv_loonity_connector' ),
        'priority'  => 100,
        'callback'  => array($this, 'loonity_data_tab')
    );
    return $tabs;
  }


  public function loonity_data_tab() {
    global $woocommerce, $post;
    $product = wc_get_product($post->ID);

    include plugin_dir_path( __FILE__ ).'../assets/pingvin-loonity-vars.php';
    $locale = get_option('loonity_connector_options')['loonity_locale'];    


      ?>
        <table class="woocommerce-product-attributes shop_attributes" aria-label="Product Details">
          <tbody>   
            
            <tr class="woocommerce-product-attributes-item woocommerce-product-attributes-item--weight">
              <th class="woocommerce-product-attributes-item__label" scope="row"><?php echo __('Produzent', 'pv_loonity_connector' ) ?></th>
              <td class="woocommerce-product-attributes-item__value"><?php echo $product->get_meta('_produzent'); ?></td>
            </tr>
            
            <!--
            <tr class="woocommerce-product-attributes-item woocommerce-product-attributes-item--weight">
              <th class="woocommerce-product-attributes-item__label" scope="row"><?php echo __('Lieferant', 'pv_loonity_connector' ) ?></th>
              <td class="woocommerce-product-attributes-item__value"><?php echo $product->get_meta('_lieferant'); ?></td>
            </tr>
            -->
            
            <tr class="woocommerce-product-attributes-item woocommerce-product-attributes-item--weight">
              <th class="woocommerce-product-attributes-item__label" scope="row"><?php echo __('Herkunft', 'pv_loonity_connector' ) ?></th>
              <td class="woocommerce-product-attributes-item__value"><?php echo $product->get_meta('_herkunft'); ?></td>
            </tr>

            <tr class="woocommerce-product-attributes-item woocommerce-product-attributes-item--weight">
              <th class="woocommerce-product-attributes-item__label" scope="row"><?php echo __('Zertifizierungen', 'pv_loonity_connector' ) ?></th>
              <td class="woocommerce-product-attributes-item__value">
                <ul>
                  <?php
                    $certifications = json_decode($product->get_meta('_certifications'));
                    foreach($certifications as $certification) {
                      echo "<li>".$certification."</li>";
                    }
                  ?>
                </ul>
              </td>
            </tr>
            
            <tr class="woocommerce-product-attributes-item woocommerce-product-attributes-item--weight">
              <th class="woocommerce-product-attributes-item__label" scope="row"><?php echo __('Ernhärungsformen', 'pv_loonity_connector' ) ?></th>
              <td class="woocommerce-product-attributes-item__value">
                <ul>
                  <?php
                    $diets = json_decode($product->get_meta('_diet'));
                    foreach($diets as $diet_key => $diet_value) {
                      if ($diet_value === true) {
                        echo "<li>".$loonity_diets[$locale][$diet_key]."</li>";
                      }
                    }
                  ?>
                </ul>
              </td>
            </tr>
            
            <tr class="woocommerce-product-attributes-item woocommerce-product-attributes-item--weight">
              <th class="woocommerce-product-attributes-item__label" scope="row"><?php echo __('Gewicht', 'pv_loonity_connector' ) ?></th>
              <td class="woocommerce-product-attributes-item__value"><?php echo $product->get_weight(); ?></td>
            </tr>
            
            <tr class="woocommerce-product-attributes-item woocommerce-product-attributes-item--weight">
              <th class="woocommerce-product-attributes-item__label" scope="row"><?php echo __('Einheit', 'pv_loonity_connector' ) ?></th>
              <td class="woocommerce-product-attributes-item__value"><?php echo $product->get_meta('_einheit'); ?></td>
            </tr>
            
            <?php
              $transparency = json_decode($product->get_meta('_transparency'));
              $eco = $transparency->transparencyEco;
              $price = $transparency->transparencyPrice;
              //$pricePart = $transparency->transparencyPricePart;
              $social = $transparency->transparencySocial;

              if ($eco) {
                ?>
                  <tr class="woocommerce-product-attributes-item woocommerce-product-attributes-item--weight">
                    <th class="woocommerce-product-attributes-item__label" scope="row"><?php echo __('Ökologische Transparenz', 'pv_loonity_connector' ) ?></th>
                    <td class="woocommerce-product-attributes-item__value">
                      <?php echo $eco; ?>
                    </td>
                  </tr>
                <?php
              }

              if ($price) {
                ?>
                  <tr class="woocommerce-product-attributes-item woocommerce-product-attributes-item--weight">
                    <th class="woocommerce-product-attributes-item__label" scope="row"><?php echo __('Preistransparenz', 'pv_loonity_connector') ?></th>
                    <td class="woocommerce-product-attributes-item__value">
                     <?php echo $price; ?>
                    </td>
                  </tr>
                <?php
              }

              if ($social) {
                ?>
                  <tr class="woocommerce-product-attributes-item woocommerce-product-attributes-item--weight">
                    <th class="woocommerce-product-attributes-item__label" scope="row"><?php echo __('Soziale Transparenz', 'pv_loonity_connector' ) ?></th>
                    <td class="woocommerce-product-attributes-item__value">
                      <?php echo $social; ?>
                    </td>
                  </tr>
                <?php
              }
            ?>
          </tbody>
        </table>
      <?php
  }




  /**
   * Backend loonity product data tab
   */
  public function pingvin_loonity_product_info_tab( $default_tabs ) {
    global $post;
    $product = wc_get_product($post->ID);

    if ($product->get_meta('_loonity_id')) {
      $tabs = array(
          'pv_loonity_data' => array(
              'label'       => esc_html__( 'Loonity Data', 'pv_loonity_connector' ),
              'target'      => 'pv_loonity_data',
              'priority'    => 60,
              'class'       => array(),
          ),
      );
      $default_tabs = array_merge( $default_tabs, $tabs );
      return $default_tabs;
    }
    
    return $default_tabs;
  }



  public function pingvin_loonity_product_info_tab_display() {
    global $woocommerce, $post;
    $product = wc_get_product($post->ID);

    ?>
    <div id="pv_loonity_data" class="panel woocommerce_options_panel">
      <p class="form-field">
        <label>Variant ID</label>
        <span><?php echo $product->get_meta('_loonity_id'); ?></span>
      </p>
      
      <p class="form-field">
        <label>Certifications</label>
        <span><?php echo $product->get_meta('_certifications'); ?></span>
      </p>
      
      <p class="form-field">
        <label>Diet</label>
        <span><?php echo $product->get_meta('_diet'); ?></span>
      </p>
      
      <p class="form-field">
        <label>Producer</label>
        <span><?php echo $product->get_meta('_produzent'); ?></span>
      </p>
      
      <p class="form-field">
        <label>Supplier</label>
        <span><?php echo $product->get_meta('_lieferant'); ?></span>
      </p>
      
      <p class="form-field">
        <label>Origin</label>
        <span><?php echo $product->get_meta('_herkunft'); ?></span>
      </p>
      
      <p class="form-field">
        <label>Lot</label>
        <span><?php echo $product->get_meta('_gebinde'); ?></span>
      </p>
      
      <p class="form-field">
        <label>Unit</label>
        <span><?php echo $product->get_meta('_einheit'); ?></span>
      </p>
      
      <p class="form-field">
        <label>Intermediate Trade</label>
        <span><?php echo $product->get_meta('_intermediary_trade'); ?></span>
      </p>
      
      <p class="form-field">
        <label>Original Producer</label>
        <span><?php echo $product->get_meta('_original_producer'); ?></span>
      </p>
      
      <p class="form-field">
        <label>Original Producer Description</label>
        <span><?php echo $product->get_meta('_original_producer_description'); ?></span>
      </p>
      
      <p class="form-field">
        <label>Transparency</label>
        <span><?php echo htmlspecialchars($product->get_meta('_transparency')); ?></span>
      </p>
      
      <p class="form-field">
        <label>Transparency Price Parts</label>
        <span><?php echo htmlspecialchars($product->get_meta('_transparencyPricePart')); ?></span>
      </p>
    </div>
    <?php
  }
}
?>