<?php

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}

class EDD_SL_Reassign_Table extends WP_List_Table {

	function __construct() {

		global $status, $page;

		//Set parent defaults
		parent::__construct( array(
			'singular' => 'license',
			'plural'   => 'licenses',
			'ajax'     => false
		) );

		$this->per_page = 30;
	}


	/**
	 * Output column data
	 *
	 * @access      private
	 * @since       1.0
	 * @return      void
	 */


	function column_default( $item, $column_name ) {

		$status = edd_software_licensing()->get_license_status( $item['ID'] );

		switch( $column_name ) {

			case 'status':
				echo '<span class="edd-sl-' . esc_attr( $status ) . '">' . esc_html( $status ) . '</span>';
				break;
			case 'key':
				echo esc_html( get_post_meta( $item['ID'], '_edd_sl_key', true ) );
				break;

			case 'purchased':

				$payment_id = get_post_meta( $item['ID'], '_edd_sl_payment_id', true );
				if ( $payment_id ) {
					$payment_url = admin_url( 'edit.php?post_type=download&page=edd-payment-history&view=view-order-details&id=' . $payment_id );
					echo '<a href="' . esc_attr( $payment_url ) . '">' . __( 'View payment', 'edd_sl' ) . '</a>';
				}

				break;

			case 'price_id':
				$download_id = get_post_meta( $item['ID'], '_edd_sl_download_id', true );
				$prices      = edd_get_variable_prices( $download_id );
				if ( ! array_key_exists( $item['price_id'], $prices ) ) {
					$class = 'red';
				} else {
					$class = 'green';
				}
				echo '<span id="current-id-' . $item['ID'] . '" style="color:' . $class . '">' . $item['price_id'] . '</span>';
				break;

			case 'change_id':
				$download_id = get_post_meta( $item['ID'], '_edd_sl_download_id', true );
				$prices      = edd_get_variable_prices( $download_id );
				?>
				<form id="reassign-license">
					<select class="license-price-id-select" data-download="<?php echo $download_id; ?>" data-id="<?php echo $item['ID']; ?>" name="new-id">
					<?php
					$selected = '';
					if ( ! array_key_exists( $item['price_id'], $prices ) ) {
						$selected = 'selected="selected"';
					}
					?>
					<option value="-1" disabled="disabled" <?php echo $selected; ?>>Price ID Not Found</option>
					<?php
						foreach ( $prices as $key => $price ) {
							?><option <?php selected( $key, $item['price_id'], true ); ?> value="<?php echo $key; ?>"><?php echo $key . ' - ' . $price['name']; ?></option><?php
						}
					?>
					</select>
					<span class="spinner"></span>
				</form>
				<?php
				break;

		}

	}


	/**
	 * Output the title column
	 *
	 * @access      private
	 * @since       1.0
	 * @return      void
	 */

	function column_title( $item ) {

		//Build row actions
		$actions = array();
		$base    = wp_nonce_url( admin_url( 'edit.php?post_type=download&page=edd-sl-reassign-license' ), 'edd_sl_key_nonce' );
		$license = get_post( $item['ID'] );
		$status  = edd_software_licensing()->get_license_status( $item['ID'] );

		$title = get_the_title( $item['ID'] );

		$actions['view_log'] = sprintf( '<a href="#TB_inline?width=640&inlineId=license_log_%s" class="thickbox" data-license-id="'. esc_attr( $item['ID'] ) .'" title="' . __( 'License Log', 'edd_sl' ) . '">' . __( 'View Log', 'edd_sl' ) . '</a>', $item['ID'] );

		// Return the title contents
		return $title . $this->row_actions( $actions );
	}

	/**
	 * Output the checkbox column
	 *
	 * @access      private
	 * @since       1.0
	 * @return      void
	 */

	function column_cb( $item ) {

		return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			esc_attr( $this->_args['singular'] ),
			esc_attr( $item['ID'] )
		);

	}


	/**
	 * Setup columns
	 *
	 * @access      private
	 * @since       1.0
	 * @return      array
	 */

	function get_columns() {

		$columns = array(
			'title'     => __( 'Name', 'edd_sl' ),
			'status'    => __( 'Status', 'edd_sl' ),
			'key'       => __( 'Key', 'edd_sl' ),
			'purchased' => __( 'Purchased', 'edd_sl' ),
			'price_id'  => __( 'Price ID', 'edd_sl' ),
			'change_id' => __( 'Change ID', 'edd_sl' )
		);

		return $columns;
	}

	/**
	 * Retrieve the table's sortable columns
	 *
	 * @access public
	 * @since 2.1.2
	 * @return array Array of all the sortable columns
	 */
	public function get_sortable_columns() {
		return array(
			'expires'   => array( 'expires', false ),
			'purchased' => array( 'purchased', false )
		);
	}

	/**
	 * Setup available views
	 *
	 * @access      private
	 * @since       1.0
	 * @return      array
	 */

	function get_views() {

		$base = admin_url( 'edit.php?post_type=download&page=edd-sl-reassign-license' );
		$current = isset( $_GET['view'] ) ? $_GET['view'] : '';

		$link_html = '<a href="%s"%s>%s</a>(%s)';

		$views = array(
			'all'      => sprintf( $link_html,
				esc_url( remove_query_arg( 'view', $base ) ),
				$current === 'all' || $current == '' ? ' class="current"' : '',
				esc_html__( 'All', 'edd_sl' ),
				$this->get_total_licenses()
			)
		);

		return $views;

	}


	/**
	 * Retrieve the current page number
	 *
	 * @access      private
	 * @since       1.3.4
	 * @return      int
	 */

	function get_paged() {
		return isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
	}


	/**
	 * Retrieve the total number of licenses
	 *
	 * @access      private
	 * @since       1.3.4
	 * @return      int
	 */

	function get_total_licenses() {
		$license_args = array(
			'post_type'      => 'edd_license',
			'fields'         => 'ids',
			'nopaging'       => true,
			'meta_query'     => array( 'relation' => 'AND' ),
			'post_parent'    => 0
		);

		$license_args['meta_query'][] = array(
			'key'     => '_edd_sl_download_price_id',
			'copmare' => 'EXISTS'
		);

		$query = new WP_Query( $license_args );

		if( $query->have_posts() ) {
			return $query->post_count;
		}

		return 0;
	}


	/**
	 * Retrieve the count of licenses by status
	 *
	 * @access      private
	 * @since       1.3.4
	 * @return      int
	 */

	function count_licenses( $status = 'active' ) {
		$args = array(
			'post_type'   => 'edd_license',
			'fields'      => 'ids',
			'nopaging'    => true,
			'post_parent' => 0
		);

		if( 'disabled' == $status ) {
			$args['post_status'] = 'draft';
		} else {
			$args['meta_key']  = '_edd_sl_status';
			$args['meta_value'] = $status;
		}

		$query = new WP_Query( $args );

		if( $query->have_posts() ) {
			return $query->post_count;
		}

		return 0;
	}


	/**
	 * Setup available bulk actions
	 *
	 * @access      private
	 * @since       1.0
	 * @return      array
	 */

	function get_bulk_actions() {

		$actions = array();

		return $actions;

	}


	/**
	 * Process bulk actions
	 *
	 * @access      private
	 * @since       1.0
	 * @return      void
	 */
	function process_bulk_action() {

	}


	/**
	 * Query database for license data and prepare it for the table
	 *
	 * @access      private
	 * @since       1.0
	 * @return      array
	 */
	function licenses_data() {

		$licenses_data = array();

		$license_args = array(
			'post_type'      => 'edd_license',
			'posts_per_page' => 30,
			'paged'          => $this->get_paged(),
			'meta_query'     => array( 'relation' => 'AND' ),
			'post_parent'    => 0
		);

		$license_args['meta_query'][] = array(
			'key'     => '_edd_sl_download_price_id',
			'copmare' => 'EXISTS'
		);

		$key_search = false;

		// check to see if we are searching
		if( isset( $_GET['s'] ) ) {

			$search = trim( $_GET['s'] );

			if( ! is_email( $search ) ) {

				$has_period = strstr( $search, '.' );

				if( false === $has_period && ! preg_match( '/\s/', $search ) ) {
					// Search in the license key.
					$license_args['meta_query'][] = array(
						'key'   => '_edd_sl_key',
						'value' => $search
					);

					$key_search = true;
					unset( $license_args['post_parent'] );


				}

			}

		}

		$licenses = get_posts( $license_args );

		// If searching by Key
		if ( $key_search ) {

			$found_license = $licenses[0];

			// And we found a child license
			if ( ! empty( $found_license->post_parent ) ) {

				// Swap out the meta query for the parent license to show the entire bundle
				$parent_license_key = get_post_meta( $found_license->post_parent, '_edd_sl_key', true );

				foreach ( $license_args['meta_query'] as $key => $args ) {

					if ( ! empty( $args['key'] ) && '_edd_sl_key' === $args['key'] ) {
						$license_args['meta_query'][$key]['value'] = $parent_license_key;
					}

				}

				$licenses = get_posts( $license_args );

			}

		}

		if ( $licenses ) {
			foreach ( $licenses as $license ) {

				$status      = get_post_meta( $license->ID, '_edd_sl_status', true );
				$key         = get_post_meta( $license->ID, '_edd_sl_key', true );
				$user        = get_post_meta( $license->ID, '_edd_sl_user_id', true );
				$expires     = date_i18n( get_option( 'date_format' ), get_post_meta( $license->ID, '_edd_sl_expiration', true ) );
				$purchased   = get_the_time( get_option( 'date_format' ), $license->ID );
				$download_id = get_post_meta( $license->ID, '_edd_sl_download_id', true );
				$price_id    = get_post_meta( $license->ID, '_edd_sl_download_price_id', true );

				$licenses_data[] = array(
					'ID'               => $license->ID,
					'title'            => get_the_title( $license->ID ),
					'status'           => $status,
					'key'              => $key,
					'user'             => $user,
					'expires'          => $expires,
					'purchased'        => $purchased,
					'download_id'      => $download_id,
					'price_id'         => $price_id
				);
			}
		}

		return $licenses_data;

	}

	/** ************************************************************************
	 * @uses $this->_column_headers
	 * @uses $this->items
	 * @uses $this->get_columns()
	 * @uses $this->get_sortable_columns()
	 * @uses $this->get_pagenum()
	 * @uses $this->set_pagination_args()
	 **************************************************************************/
	function prepare_items() {

		/**
		 * First, lets decide how many records per page to show
		 */
		$per_page = $this->per_page;

		add_thickbox();

		$columns = $this->get_columns();
		$hidden  = array(); // no hidden columns

		$sortable = $this->get_sortable_columns();

		$this->_column_headers = array( $columns, $hidden, $sortable );

		$this->process_bulk_action();

		$current_page = $this->get_pagenum();
		$total_items  = $this->get_total_licenses();

		$this->items  = $this->licenses_data();

		$pagination_args = array(
			'total_items' => $total_items,
			'per_page'    => 30,
			'total_pages' => ceil( $total_items / 30 )
		);

		$this->set_pagination_args( $pagination_args );

	}

}
