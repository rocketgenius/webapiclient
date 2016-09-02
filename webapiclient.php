<?php
/*
Plugin Name: Gravity Forms Web API Client
Plugin URI: http://www.gravityforms.com
Description: Demonstrates usages of the Gravity Forms Web API
Version: 1.0
Author: rocketgenius
Author URI: http://www.rocketgenius.com
*/


if ( class_exists( "GFForms" ) ) {
	GFForms::include_addon_framework();

	class GFWebAPIClient extends GFAddOn {

		protected $_version = '0.1';
		protected $_min_gravityforms_version = '1.7.7';
		protected $_slug = 'gravityformswebapiclient';
		protected $_path = 'gravityformswebapiclient/webapiclient.php';
		protected $_full_path = __FILE__;
		protected $_url = 'http://www.gravityforms.com';
		protected $_title = 'Gravity Forms Web API Client';
		protected $_short_title = 'Web API Client';

		public function plugin_page() {

			if ( $this->is_form_list_page() ) {
				$this->form_list_page();
			} else if ( $this->is_form_edit_page() ) {
				$this->form_edit_page( $_GET['form_id'] );
			} else if ( $this->is_entry_list_page() ) {
				$this->entry_list_page( $_GET['form_id'] );
			} else if ( $this->is_entry_edit_page() ) {
				$this->entry_edit_page( $_GET['entry_id'], $_GET['form_id'] );
			} else if ( $this->is_results_page() ) {
				$this->results_page( $_GET['form_id'] );
			}

		}

		public function plugin_page_title() {
			if ( $this->is_form_list_page() ) {
				return "<span>Web API - Forms</span> &nbsp;<a class='button add-new-h2' href='" . add_query_arg( array( 'form_id' => '0' ) ) . "'>Add New</a>";
			} else if ( $this->is_form_edit_page() ) {
				return "<span>Web API - Form</span><span class='gf_admin_page_subtitle'><span class='gf_admin_page_formid'>Form ID: {$_GET['form_id']}</span></span>";
			} else if ( $this->is_entry_list_page() ) {
				return "<span>Web API - Entries</span> &nbsp;<a class='button add-new-h2' href='" . add_query_arg( array( 'entry_id' => '0' ) ) . "'>Add New</a> <span class='gf_admin_page_subtitle'><span class='gf_admin_page_formid'>Form ID: {$_GET["form_id"]}</span></span>";
			} else if ( $this->is_entry_edit_page() ) {
				return "<span>Web API - Entry</span><span class='gf_admin_page_subtitle'><span class='gf_admin_page_formid'>Entry ID: {$_GET['entry_id']}</span></span>";
			} else if ( $this->is_results_page() ) {
				return "<span>Web API - Results</span><span class='gf_admin_page_subtitle'><span class='gf_admin_page_formid'>Form ID: {$_GET['form_id']}</span></span>";
			}

		}

		public function plugin_settings_fields() {
			return array(
				array(
					'title'       => 'Web API Information',
					'description' => 'Enter your Web API information below',
					'fields'      => array(
						array(
							'name'  => 'apiURL',
							'label' => 'API URL',
							'type'  => 'text',
							'class' => 'medium',
						),
						array(
							'name'  => 'apiPublicKey',
							'label' => 'Public Key',
							'type'  => 'text',
							'class' => 'medium',
						),
						array(
							'name'  => 'apiPrivateKey',
							'label' => 'Private Key',
							'type'  => 'text',
							'class' => 'medium',
						),
					),
				),
			);

		}

		public function form_list_page() {
			require_once( $this->get_base_path() . '/includes/class-gf-forms-table.php' );

			$api = $this->get_api();
			if ( $api == false ) {
				?>
				<div class="updated" style="padding:15px;">Your Web API settings haven't been configured yet. <a
						href="<?php echo admin_url( 'admin.php?page=gf_settings&subview=Web API Client' ) ?>">Lets go
						set them up!</a></div>
				<?php
				return;
			}

			$this->maybe_delete_form();

			$forms = $api->get_forms();

			if ( GFWebAPIError::is_error( $forms ) ) {
				$this->display_error( 'There was an error getting the list of forms. Error: ' . $forms->get_message() );

				return;
			}

			$form_list = new GFFormsTable( $forms );
			$form_list->prepare_items();
			?>
			<script type="text/javascript">
				function DeleteForm(formId) {
					jQuery("#gf_command_name").val('delete');
					jQuery('#gf_command_args').val(formId);
					jQuery('#gf_form_list').submit();
				}
			</script>
			<form method="post" id="gf_form_list">
				<input type="hidden" name="gf_command_name" id="gf_command_name">
				<input type="hidden" name="gf_command_args" id="gf_command_args">
				<?php
				$form_list->display();
				?>
			</form>
			<?php
		}

		public function maybe_delete_form() {
			$command = rgpost( 'gf_command_name' );
			$form_id = absint( rgpost( 'gf_command_args' ) );
			if ( $command == 'delete' ) {
				$api    = $this->get_api();
				$result = $api->delete_form( $form_id );
				if ( GFWebAPIError::is_error( $result ) ) {
					$this->display_error( 'There was an error deleting the specified form. Error: ' . $result->get_message() );
				} else {
					$this->display_message( 'Form deleted successfully' );
				}
			}
		}

		public function entry_list_page( $form_id ) {
			$this->maybe_delete_entry();

			require_once( $this->get_base_path() . '/includes/class-gf-entries-table.php' );

			//search
			$search = '';

			//sorting
			$orderby = rgempty( 'orderby', $_GET ) ? 'id' : rgget( 'orderby' );
			$dir     = rgempty( 'order', $_GET ) ? 'DESC' : rgget( 'order' );
			$sorting = array( 'key' => $orderby, 'direction' => $dir );

			//paging
			$current_page = rgempty( 'paged', $_GET ) ? 1 : rgget( 'paged' );
			$page_size    = 10;
			$paging       = array( 'current_page' => $current_page, 'page_size' => $page_size );

			$api          = $this->get_api();
			$entry_result = $api->get_entries( $form_id, $search, $sorting, $paging );

			if ( GFWebAPIError::is_error( $entry_result ) ) {
				$this->display_error( 'There was an error getting the entry list. Error: ' . $entry_result->get_message() );

				return;
			}

			$entries     = $entry_result['entries'];
			$total_count = $entry_result["total_count"];

			$entry_list = new GFEntriesTable( $entries );
			$entry_list->prepare_items();
			$entry_list->set_pagination_args( array( "total_items" => $total_count, "per_page" => $page_size ) );
			?>
			<script type="text/javascript">
				function DeleteEntry(entryId) {
					jQuery("#gf_command_name").val('delete');
					jQuery('#gf_command_args').val(entryId);
					jQuery('#gf_form_list').submit();
				}
			</script>
			<form method="post" id="gf_form_list">
				<input type="hidden" name="gf_command_name" id="gf_command_name">
				<input type="hidden" name="gf_command_args" id="gf_command_args">
				<?php
				$entry_list->display();
				?>
			</form>
			<?php
		}

		public function maybe_delete_entry() {
			$command  = rgpost( "gf_command_name" );
			$entry_id = absint( rgpost( "gf_command_args" ) );
			if ( $command == "delete" ) {
				$api    = $this->get_api();
				$result = $api->delete_entry( $entry_id );
				if ( GFWebAPIError::is_error( $result ) ) {
					$this->display_error( "There was an error deleting the specified entry. Error: " . $result->get_message() );
				} else {
					$this->display_message( "Entry deleted successfully" );
				}
			}
		}

		public function form_edit_page( $form_id ) {

			if ( empty( $form_id ) && ! rgempty( "gforms_form_id" ) ) {
				$form_id = rgpost( "gforms_form_id" );
			}

			$api = $this->get_api();

			if ( isset( $_POST["gform_submit"] ) ) {
				$form              = json_decode( rgpost( "gform_form_meta" ), true );
				$form["title"]     = rgpost( "gform_form_title" );
				$form["is_active"] = rgpost( "gform_form_active" );

				if ( empty( $form_id ) ) {
					$form_id = $api->create_form( $form );
					$this->display_status( "Form created successfully", "There was an error creating your form: ", $form_id );

					if ( GFWebAPIError::is_error( $form_id ) ) {
						return;
					}
				} else {
					$result = $api->update_form( $form_id, $form );
					$this->display_status( "Form updated successfully", "There was an error updating your form: ", $result );

					if ( GFWebAPIError::is_error( $result ) ) {
						return;
					}
				}
			}

			$form = empty( $form_id ) ? array( "id"        => "",
			                                   "title"     => "",
			                                   "is_active" => 1
			) : $api->get_form( $form_id );
			if ( GFWebAPIError::is_error( $form ) ) {
				$this->display_error( "Could not load form. Error: " . $form->get_message() );

				return;
			}

			?>
			<div class="gform_panel gform_panel_form_settings" id="form_settings">
				<br/>
				<form method="post" id="gform_form_settings">
					<input name="gforms_form_id" type="hidden" value="<?php echo $form_id ?>"/>
					<table class="gforms_form_settings" style="width:100%;">
						<tbody>
						<tr>
							<td colspan="2"><h4 class="gf_settings_subgroup_title">Form Information</h4></td>
						</tr>

						<tr>
							<th>ID</th>
							<td><?php echo $form["id"] ?></td>
						</tr>
						<tr>
							<th>Title</th>
							<td><input type="text" name="gform_form_title"
							           value="<?php echo esc_attr( $form["title"] ) ?>"/></td>
						</tr>
						<tr>
							<th>Active</th>
							<td>
								<input type="radio" value='1'
								       name="gform_form_active" <?php checked( empty( $form["is_active"] ), false ) ?>>
								Yes&nbsp;&nbsp;&nbsp;&nbsp;
								<input type="radio" value='0'
								       name="gform_form_active" <?php checked( empty( $form["is_active"] ), true ) ?>>
								No
							</td>
						</tr>

						<tr>
							<td colspan="2"><h4 class="gf_settings_subgroup_title">Form Meta</h4></td>
						</tr>
						<tr>
							<td colspan="2">
								<textarea name="gform_form_meta"
								          style="width:750px; height:300px;"><?php echo json_encode( $form ) ?></textarea>

							</td>
						</tr>
						<tr>
							<td colspan="2">
								<input type="submit" name="gform_submit" value="Update" class="button-primary"/>
							</td>
						</tr>
						</tbody>
					</table>
				</form>
			</div>
			<?php
		}

		public function entry_edit_page( $entry_id, $form_id ) {
			if ( empty( $entry_id ) && ! rgempty( "gforms_entry_id" ) ) {
				$entry_id = rgpost( "gforms_entry_id" );
			}

			$api = $this->get_api();

			if ( isset( $_POST["gform_submit"] ) ) {
				$entry = json_decode( rgpost( "gform_entry_meta" ), true );

				if ( empty( $entry_id ) ) {
					$entry_id = $api->create_entry( $entry );
					$this->display_status( "Entry created successfully", "There was an error creating your entry. ", $entry_id );

					if ( GFWebAPIError::is_error( $entry_id ) ) {
						return;
					}
				} else {
					$result = $api->update_entry( $entry_id, $entry );

					$this->display_status( "Entry updated successfully", "There was an error updating your entry. ", $result );

					if ( GFWebAPIError::is_error( $result ) ) {
						return;
					}
				}


			}
			$entry = empty( $entry_id ) ? array( "id"           => "",
			                                     "date_created" => "",
			                                     "form_id"      => $form_id
			) : $api->get_entry( $entry_id );
			if ( GFWebAPIError::is_error( $entry ) ) {
				$this->display_error( "Could not load entry. Error: " . $entry->get_message() );

				return;
			}

			?>
			<div id="entry_detail">
				<form method="post">
					<input name="gforms_entry_id" type="hidden" value="<?php echo $entry_id ?>"/>
					<table class="gforms_form_settings" style="width:100%;">
						<tbody>
						<tr>
							<td colspan="2"><h4 class="gf_settings_subgroup_title">Entry Information</h4></td>
						</tr>
						<tr>
							<th>ID</th>
							<td><?php echo $entry["id"] ?></td>
						</tr>
						<tr>
							<th>Date</th>
							<td><?php echo $entry["date_created"] ?></td>
						</tr>
						<tr>
							<td colspan="2"><h4 class="gf_settings_subgroup_title">Entry Meta</h4></td>
						</tr>
						<tr>
							<td colspan="2">
								<textarea name="gform_entry_meta"
								          style="width:750px; height:300px;"><?php echo json_encode( $entry ) ?></textarea>

							</td>
						</tr>
						<tr>
							<td colspan="2">
								<input type="submit" name="gform_submit" value="Update" class="button-primary"/>
							</td>
						</tr>
						</tbody>
					</table>
				</form>
			</div>
			<?php
		}

		public function results_page( $form_id ) {

			$api = $this->get_api();

			$results = $api->get_results( $form_id );

			if ( GFWebAPIError::is_error( $results ) ) {
				$this->display_error( 'Could not load results. Error: ' . $results->get_message() );

				return;
			}

			if ( $results['entry_count'] == 0 ) {
				echo 'No entries';
				return;
			}

			?>
			<div id="entry_detail">
				<form method="post">
					<table class="gforms_form_settings" style="width:100%;">
						<tbody>
						<tr>
							<td colspan="2"><h4 class="gf_settings_subgroup_title">Result Information</h4></td>
						</tr>
						<tr>
							<th>Status</th>
							<td><?php echo $results['status'] ?></td>
						</tr>
						<tr>
							<th>Timestamp</th>
							<td><?php echo gmdate( 'Y-m-d H:i:s T', $results['timestamp'] ) ?></td>
						</tr>
						<tr>
							<th>Entries</th>
							<td><?php echo $results['entry_count'] ?></td>
						</tr>
						<tr>
							<td colspan="2"><h4 class="gf_settings_subgroup_title">Field Data</h4></td>
						</tr>
						<tr>
							<td colspan="2">
								<pre><?php print_r( $results['field_data'] ); ?></pre>
							</td>
						</tr>
						</tbody>
					</table>
				</form>
			</div>
			<?php
		}

		public function styles() {

			$my_styles =
				array(
					array(
						"handle"  => "gform_admin",
						"src"     => GFCommon::get_base_url() . "/css/admin.css",
						"version" => GFCommon::$version,
						"enqueue" => array(
							array( "query" => "page=" . $this->_slug )
						)
					)
				);

			return array_merge( parent::styles(), $my_styles );
		}

		protected function display_status( $success_message, $error_message, $response ) {

			if ( GFWebAPIError::is_error( $response ) ) {
				$this->display_error( $error_message . $response->get_message() );
			} else {
				$this->display_message( $success_message );
			}

		}

		protected function display_error( $error_message ) {
			?>
			<div class="error" style="padding:15px;">
				<?php echo $error_message; ?>
			</div>
			<?php
		}

		protected function display_message( $message ) {
			?>
			<div class="updated" style="padding:15px;">
				<?php echo $message; ?>
			</div>
			<?php
		}

		protected function is_form_list_page() {
			return ! isset( $_GET["form_id"] );
		}

		protected function is_form_edit_page() {
			return isset( $_GET["form_id"] ) && ! isset( $_GET["view"] );
		}

		protected function is_entry_list_page() {
			return rgar( $_GET, "view" ) == "entries" && ! isset( $_GET["entry_id"] );
		}

		protected function is_results_page() {
			return rgar( $_GET, "view" ) == "results";
		}

		protected function is_entry_edit_page() {
			return rgar( $_GET, "view" ) == "entries" && isset( $_GET["entry_id"] );
		}

		protected function get_api() {

			require_once( $this->get_base_path() . "/includes/class-gf-web-api-wrapper.php" );
			$settings = $this->get_plugin_settings();

			$api_url     = $settings["apiURL"];
			$public_key  = $settings["apiPublicKey"];
			$private_key = $settings["apiPrivateKey"];

			if ( empty( $api_url ) || empty( $public_key ) || empty( $private_key ) ) {
				return false;
			}

			return new GFWebAPIWrapper( $api_url, $public_key, $private_key );
		}

	}

	new GFWebAPIClient();
}
