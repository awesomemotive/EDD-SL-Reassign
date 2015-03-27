<?php
/*
 * Plugin Name: Easy Digital Downloads - Reassign License
 * Description: Allows the admin to assign a license to a different price id
 * Author: Chris Klosowski
 * Version: 1.0
 */

function edd_sl_add_licenses_link_edit_price_id() {

	global $edd_sl_licenses_page;

	$edd_sl_licenses_page = add_submenu_page( 'edit.php?post_type=download', __( 'License Reassignment', 'edd_sl' ), __( 'License Reassignment', 'edd_sl' ), 'manage_shop_settings', 'edd-sl-reassign-license', 'edd_sl_reassign_license' );

}
add_action( 'admin_menu', 'edd_sl_add_licenses_link_edit_price_id', 99 );

function edd_sl_reassign_license() {
	include( 'EDD_SL_Reassign_List_Table.php' );
	?>
	<style>
	.column-price_id {
		width: 50px;
	}
	.column-purchased {
		width: 115px;
	}
	</style>
	<script>
	jQuery(document).ready(function() {
		jQuery('.license-price-id-select').change( function() {
			var data = {};
			var select = jQuery(this);
			select.attr('disabled', 'disabled');
			select.css('opacity', '.5');
			select.next('.spinner').show();
			data.action   = 'edd_sl_reassign';
			data.price_id  = select.val();
			data.license_id = select.data('id');
			data.download_id = select.data('download');

			jQuery.post(ajaxurl, data, function(response) {
				select.removeAttr('disabled');
				select.css('opacity', '1');
				select.next('.spinner').hide();
				jQuery('#current-id-' + data.license_id).text(data.price_id).css('color', 'green');
			});

		});
	});
	</script>
	<div class="wrap">

		<div id="icon-edit" class="icon32"><br/></div>
		<h2><?php _e( 'Easy Digital Download Licenses', 'edd_sl' ); ?></h2>
		<?php edd_sl_show_errors(); ?>

		<style>
			.column-status, .column-count { width: 100px; }
			.column-limit { width: 150px; }
		</style>
		<form id="licenses-filter" method="get">

			<input type="hidden" name="post_type" value="download" />
			<input type="hidden" name="page" value="edd-licenses" />
			<?php
			$licenses_table = new EDD_SL_Reassign_Table();
			$licenses_table->prepare_items();
			$licenses_table->search_box( 'search', 'edd_sl_search' );
			$licenses_table->views();
			$licenses_table->display();
			?>
		</form>

	</div>
	<?php
}

function edd_sl_reassign() {
	$download_id = isset( $_POST['download_id'] ) ? $_POST['download_id'] : 0;
	$license_id  = isset( $_POST['license_id'] ) ? $_POST['license_id'] : 0;
	$price_id    = isset( $_POST['price_id'] ) ? $_POST['price_id'] : edd_get_default_variable_price( $download_id );

	if ( ! empty( $license_id ) && false !== $price_id ) {
		update_post_meta( $license_id, '_edd_sl_download_price_id', $price_id );
		echo 1;
	} else {
		echo 0;
	}


	die(); // this is required to return a proper result
}
add_action( 'wp_ajax_edd_sl_reassign', 'edd_sl_reassign' );
