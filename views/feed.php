<?php
$taxonomies = get_option( 'woogoo_google_product_type' );

$products = $this->get_products();
foreach ( $products as $key => $product) {
    $product_log[$product->ID] = $product->post_title;
}

$feed_fields = array();
$feed_id = isset( $_GET['feed_id'] ) ? intval( $_GET['feed_id'] ) : 0;

if ( $feed_id ) {
	$post = get_post( $feed_id );
}

$feed_fields['id'] = array(
	'type'     => 'hidden',
	'value'    => $feed_id
);
$feed_fields['post_title'] = array(
	'type'     => 'text',
	'class'    => 'wogo-field',
	'value'    => isset( $post->post_title ) ? $post->post_title : '',
	'label'    => __( 'Name', 'wogo' ),
	'desc'     => __( 'The file genera', 'wogo' )
);

$feed_fields['all_products'] = array(
    'label' => __( 'Products', 'wogo' ),
    'type' => 'checkbox',
    'desc' => __( 'Enable all products feed', 'wogo' ),
    'fields' => array(
        array(
            'label' => __( 'All', 'wogo' ),
            'value' => 'on',
            'checked' => get_post_meta( $feed_id, '_all_products', true ),
            'class' => 'wogo-all-product-checkbox-feed wogo-field',
        ),
    ),
);
$selected_products = get_post_meta( $feed_id, '_products', true );

$feed_fields['products[]'] = array(
	'type'     => 'multiple',
	'label'    => __( 'Products', 'wogo' ),
	'option'   => $product_log,
	'class'    => 'wogo-chosen',
	'selected' => empty( $selected_products ) ? array() : $selected_products,
	'desc'     => 'Chose products',
	'wrap_start' => true,
	'wrap_attr' => array(
		'class' => 'wogo-product-chosen-field-wrap'
	),
	'wrap_close' => true,
);

$feed_fields['google_product_category'] = array(
	'type'     => 'select',
	'label'    => __( 'Category', 'wogo' ),
	'option'   => $taxonomies,
	'class'    => 'wogo-chosen',
	'selected' => get_post_meta( $feed_id, '_google_product_category', true ),
	'desc'     => __( 'Google\'s category of the item', 'wogo' )
);

$feed_fields['product_type'] = array(
	'type'     => 'select',
	'label'    => __( 'Product Type', 'wogo' ),
	'option'   => $taxonomies,
	'class'    => 'wogo-chosen',
	'selected' => get_post_meta( $feed_id, '_product_type', true ),
	'desc'     => __( 'Your category of the item', 'wogo' )
);

$feed_fields['availability'] = array(
	'type'     => 'select',
	'class'    => 'wogo-field',
	'selected' => get_post_meta( $feed_id, '_availability', true ),
	'label'    => __( 'Availability', 'wogo' ),
	'option'   => array( 'in stock' => 'in stock', 'out of stock' => 'out of stock', 'preorder' => 'preorder' ),
	'desc'     => __( 'Availability status of the item', 'wogo' )
);

$feed_fields['availability_date'] = array(
	'type'     => 'text',
	'class'    => 'wogo-field',
	'class'    => 'wogo-date-picker',
	'value'    => get_post_meta( $feed_id, '_availability_date', true ),
 	'label'    => __( 'Availability date', 'wogo' ),
	'desc'     => __( 'The day a pre-ordered product becomes available for delivery', 'wogo' ),
);

$feed_fields['condition'] = array(
	'type'     => 'select',
	'class'    => 'wogo-field',
	'selected' => get_post_meta( $feed_id, '_condition', true ),
	'label'    => __( 'Condition', 'wogo' ),
	'option'   => array( 'new' => 'New', 'used' => 'Used', 'refurbished' => 'refurbished' ),
	'desc'     => __( 'Availability status of the item', 'wogo' )
);

$feed_fields['brand'] = array(
	'type'     => 'text',
	'class'    => 'wogo-field',
	'value'    => get_post_meta( $feed_id, '_brand', true ),
	'label'    => __( 'Brand', 'wogo' ),
	'desc'     => __( 'Brand of the item', 'wogo' )
);

$feed_fields['mpn'] = array(
	'type'     => 'checkbox',
	'label'    => __( 'MPN', 'wogo' ),
	'desc'     => __( 'Manufacturer Part Number (MPN) of the item', 'wogo' ),
	'fields' => array(
        array(
            'label' => __( 'Enable', 'wogo' ),
            'value' => 'on',
            'checked' => get_post_meta( $feed_id, '_mpn', true ),
            'class'    => 'wogo-field',
        ),
    ),
);

$feed_fields['gender'] = array(
	'type'     => 'select',
	'class'    => 'wogo-field',
	'label'    => __( 'Gender', 'wogo' ),
	'selected' => get_post_meta( $feed_id, '_gender', true ),
	'option'   => array( '-1' => '--Select--', 'male' => 'male', 'female' => 'female', 'unisex' => 'unisex' ),
	'desc'     => __( 'Gender of the item', 'wogo' )
);

$feed_fields['age_group'] = array(
	'type'     => 'select',
	'class'    => 'wogo-field',
	'selected' => get_post_meta( $feed_id, '_age_group', true ),
	'label'    => __( 'Age Group', 'wogo' ),
	'option'   => array( '-1' => '--Select--', 'newborn' => 'newborn', 'infant' => 'infant', 'toddler' => 'toddler', 'kids' => 'kids', 'adult' => 'adult' ),
	'desc'     => __( 'Availability status of the item', 'wogo' )
);

$feed_fields['color'] = array(
	'type'     => 'checkbox',
	'label'    => __( 'Color', 'wogo' ),
	'desc'     => __( 'Color of the item', 'wogo' ),
	'fields' => array(
        array(
            'label' => __( 'Enable', 'wogo' ),
            'value' => 'on',
            'checked' => get_post_meta( $feed_id, '_color', true ),
            'class'    => 'wogo-field',
        ),
    ),
);

$feed_fields['size'] = array(
	'type'     => 'checkbox',
	'label'    => __( 'Size', 'wogo' ),
	'desc'     => __( 'Size of the item', 'wogo' ),
	'fields' => array(
        array(
            'label' => __( 'Enable', 'wogo' ),
            'value' => 'on',
            'checked' => get_post_meta( $feed_id, '_size', true ),
            'class'    => 'wogo-field',
        ),
    ),
);

$feed_fields['size_type'] = array(
	'type'     => 'select',
	'label'    => __( 'Size Type', 'wogo' ),
	'class'    => 'wogo-field',
	'selected' => get_post_meta( $feed_id, '_size_type', true ),
	'option'   => array( '-1' => '--Select--', 'regular' => 'regular', 'petite' => 'petite', 'plus' => 'plus', 'big and tall' => 'big and tall', 'maternity' => 'maternity' ),
	'desc'     => __( 'Size type of the item', 'wogo' )
);

$feed_fields['size_system'] = array(
	'type'     => 'select',
	'class'    => 'wogo-field',
	'label'    => __( 'Size System', 'wogo' ),
	'selected' => get_post_meta( $feed_id, '_size_system', true ),
	'option'   => array(
		'-1'  => '--Select--',
		'US'  => 'US',
		'UK'  => 'UK',
		'EU'  => 'EU',
		'DE'  => 'DE',
		'FR'  => 'FR',
		'JP'  => 'JP',
		'CN'  => 'CN',
		'IT'  => 'IT',
		'BR'  => 'BR',
		'MEX' => 'MEX',
		'AU'  => 'AU'
	),
	'desc'     => __( 'Size system of the item', 'wogo' )
);

$feed_fields['custom_label_0'] = array(
	'type'     => 'text',
	'label'    => __( 'Custom Label 0', 'wogo' ),
	'class'    => 'wogo-field',
	'value'    => get_post_meta( $feed_id, '_custom_label_0', true ),
	//'desc'     => __( 'Brand of the item', 'wogo' )
);

$feed_fields['custom_label_1'] = array(
	'type'     => 'text',
	'label'    => __( 'Custom Label 1', 'wogo' ),
	'class'    => 'wogo-field',
	'value'    => get_post_meta( $feed_id, '_custom_label_1', true ),
	//'desc'     => __( 'Brand of the item', 'wogo' )
);

$feed_fields['custom_label_2'] = array(
	'type'     => 'text',
	'label'    => __( 'Custom Label 2', 'wogo' ),
	'class'    => 'wogo-field',
	'value'    => get_post_meta( $feed_id, '_custom_label_2', true ),
	//'desc'     => __( 'Brand of the item', 'wogo' )
);

$feed_fields['custom_label_3'] = array(
	'type'     => 'text',
	'label'    => __( 'Custom Label 3', 'wogo' ),
	'class'    => 'wogo-field',
	'value'    => get_post_meta( $feed_id, '_custom_label_3', true ),
	//'desc'     => __( 'Brand of the item', 'wogo' )
);

$feed_fields['custom_label_4'] = array(
	'type'     => 'text',
	'label'    => __( 'Custom Label 4', 'wogo' ),
	'class'    => 'wogo-field',
	'value'    => get_post_meta( $feed_id, '_custom_label_4', true ),
	//'desc'     => __( 'Brand of the item', 'wogo' )
);

$feed_fields['promotion_id'] = array(
	'type'     => 'text',
	'label'    => __( 'Promotion ID', 'wogo' ),
	'class'    => 'wogo-field',
	'value' => get_post_meta( $feed_id, '_promotion_id', true ),
	//'desc'     => __( 'Brand of the item', 'wogo' )
);

?>

<div class="postbox">

		<h3 class="postbox-title"><?php _e( 'Generate Products Feed', 'wogo' ); ?></h3>
		<div class="wogo-feed-form-content">
			<form method="post" class="wogo-feed-form" action="">
			<?php
				wp_nonce_field( 'wogo_feed_nonce', 'feed_nonce' );
				foreach( $feed_fields as $name => $field_obj ) {

				    if( ! isset( $field_obj['type'] ) || empty( $field_obj['type'] ) ) {
				        continue;
				    }

				    switch ( $field_obj['type'] ) {
				        case 'text':
				            echo WOGO_Admin_Settings::getInstance()->text_field( $name, $field_obj );
				            break;
				        case 'select':
				            echo WOGO_Admin_Settings::getInstance()->select_field( $name, $field_obj );
				            break;
				        case 'textarea':
				            echo WOGO_Admin_Settings::getInstance()->textarea_field( $name, $field_obj );
				            break;
				        case 'radio':
				            echo WOGO_Admin_Settings::getInstance()->radio_field( $name, $field_obj );
				            break;
				        case 'checkbox':
				            echo WOGO_Admin_Settings::getInstance()->checkbox_field( $name, $field_obj );
				            break;
				        case 'hidden':
				            echo WOGO_Admin_Settings::getInstance()->hidden_field( $name, $field_obj );
				            break;
				        case 'multiple':
				            echo WOGO_Admin_Settings::getInstance()->multiple_select_field( $name, $field_obj );
				            break;
				        case 'html':
				            echo WOGO_Admin_Settings::getInstance()->html_field( $name, $field_obj );
				            break;
				    }
				}
				?>
				<div class="wogo-clear"></div>
				<input type="submit" disabled="disabled" class="button button-primary" value="<?php _e( 'This feature is available for pro version', 'wogo'); ?>" name="wogo_submit_feed">
				<a class="button button-primary" href="http://mishubd.com/product/woogoo/" target="_blank"><?php _e( 'Update to pro version', 'wogo' ); ?></a>
			</form>
		</div>
		<div class="wogo-clear"></div>
</div>