<?php

if ( ! class_exists( 'GFWebAPIError' ) ) {
	class GFWebAPIError {
		public $code;
		public $message;
		public $data;

		function __construct( $code, $message, $data = null ) {
			$this->code    = $code;
			$this->message = $message;
			$this->data    = $data;
		}

		function get_message() {
			return sprintf( '%s (%s)', $this->message, $this->code );
		}

		public static function is_error( $thing ) {
			if ( is_object( $thing ) && is_a( $thing, 'GFWebAPIError' ) ) {
				return true;
			}

			return false;
		}
	}
}

if ( ! class_exists( 'GFWebAPIWrapper' ) ) {
	class GFWebAPIWrapper {

		private $_api_url;
		private $_public_key;
		private $_private_key;
		public $_expires_seconds = null;

		function __construct( $api_url, $public_key, $private_key ) {
			$this->_api_url     = $api_url;
			$this->_public_key  = $public_key;
			$this->_private_key = $private_key;

		}

		//------------- FORMS ---------------------------//
		/**
		 * Gets a list of active forms
		 * Returns a list of all active forms along with their entry count. If there is an error, a GFWebAPIError object will be returned
		 * Output:
		 * <code>
		 * array  (
		 *       "30" => array(
		 *                       "id" => 30
		 *                       "title" => "Contact Us"
		 *                      "entries" => 0
		 *                  )
		 *
		 *      "2" => array(
		 *                  "id" => 2
		 *                  "title" => "Send your Feedback"
		 *                  "entries" => 23
		 *              )
		 *
		 * )
		 * </code>
		 *
		 * @return array|GFWebAPIError
		 */
		public function get_forms() {

			$response = $this->send_request( 'GET', 'forms' );

			return $this->prepare_response( $response );
		}

		/**
		 * Gets a form
		 * Returns a full form object corresponding to the specified form id. If there is an error during the operation, a GFWebAPIError object will be returned
		 *
		 * @param $form_id - The form id to be returned
		 *
		 * @return array|GFWebAPIError
		 */
		public function get_form( $form_id ) {
			$form_id  = absint( $form_id );
			$response = $this->send_request( 'GET', "forms/{$form_id}" );

			$form = $this->prepare_response( $response );

			return $form;
		}

		/**
		 * Updates a form
		 * Updates a form and returns true if the form was updated successfully or GFWebAPIError if there was an error while updating the form
		 *
		 * @param $form_id - The form to be updated
		 * @param array $form - The new form meta (formatted as an array)
		 *
		 * @return bool|GFWebAPIError
		 */
		public function update_form( $form_id, $form ) {
			$form_id  = absint( $form_id );
			$response = $this->send_request( 'PUT', "forms/{$form_id}", '', json_encode( $form ) );

			$result = $this->prepare_response( $response );

			return GFWebAPIError::is_error( $result ) ? $result : true;
		}

		/**
		 * Creates new forms
		 * Creates all specified forms and returns an array of the new form ids. If there was an error while creating the forms, an instance of GFWebAPIError will be returned.
		 *
		 * @param array $forms - An array of form objects to be created. If a form object has an "id" property, it will be ignored when creating the form.
		 *
		 * @return array|GFWebAPIError - Returns an array with the new form ids or an instance of GFWebAPIError
		 */
		public function create_forms( $forms ) {

			$response = $this->send_request( 'POST', 'forms', '', json_encode( $forms ) );

			$response = $this->prepare_response( $response );

			return $response;
		}

		/**
		 * Creates a single form
		 * Creates a form and returns the new form id. If there was an error while creating the form, an instance of GFWebAPIError will be returned.
		 *
		 * @param array $form - The form objects to be created. If the form object has an "id" property, it will be ignored when creating the form.
		 *
		 * @return array|GFWebAPIError - Returns the new form id or an instance of GFWebAPIError
		 */
		public function create_form( $form ) {

			$response = $this->create_forms( array( $form ) );

			if ( ! GFWebAPIError::is_error( $response ) ) {
				$response = $response[0];
			}

			return $response;
		}

		/**
		 * Delete one or more forms
		 * Deletes a set of forms based on the $form_ids parameter
		 *
		 * @param array $form_ids - The form ids to be deleted
		 *
		 * @return bool|GFWebAPIError - Returns true if all forms were deleted successfully. If one or more forms couldn't be deleted, an instance of GFWebAPIError is returned.
		 */
		public function delete_forms( $form_ids ) {
			$form_ids_string = implode( ';', $form_ids );

			$response = $this->send_request( 'DELETE', "forms/{$form_ids_string}" );

			$result = $this->prepare_response( $response );

			return GFWebAPIError::is_error( $result ) ? $result : true;

		}

		/**
		 * Delete a single form
		 * Deletes a form specified by the $form_id parameter
		 *
		 * @param array $form_id - The form id to be deleted
		 *
		 * @return bool|GFWebAPIError - Returns true if the form was deleted successfully, or an instance of GFWebAPIError is there was an error.
		 */
		public function delete_form( $form_id ) {

			return $this->delete_forms( array( $form_id ) );

		}

		//------------- ENTRIES --------------------------//
		/**
		 * Get entries
		 * Returns a list of entries based on the specified parameters.
		 *
		 * @param int $form_id - The form id whose entries will be returned
		 * @param array $search - An array specifying the search criteria.
		 *  Filter by status
		 *     $search_criteria["status"] = "active";
		 *
		 *  Filter by date range
		 *     $search_criteria["start_date"] = $start_date;
		 *     $search_criteria["end_date"] =  $end_date;
		 *
		 *  Filter by any column in the main table
		 *     $search_criteria["field_filters"][] = array("key" => "currency", value => "USD");
		 *     $search_criteria["field_filters"][] = array("key" => "is_read", value => true);
		 *
		 *  Filter by Field Values
		 *     $search_criteria["field_filters"][] = array('key' => "1", 'value' => "gquiz159982170");
		 *
		 *  Filter by a checkbox value (not recommended)
		 *     $search_criteria["field_filters"][] = array('key' => "2.2", 'value' => "gquiz246fec995");
		 *     note: this will work for checkboxes but it won't work if the checkboxes have been re-ordered - best to use the following example below
		 *
		 *  Filter by a checkbox value (recommended)
		 *     $search_criteria["field_filters"][] = array('key' => "2", 'value' => "gquiz246fec995");
		 *
		 *  Filter by a global search of values of any form field
		 *     $search_criteria["field_filters"][] = array('value' => $search_value);
		 *  OR
		 *     $search_criteria["field_filters"][] = array('key' => 0, 'value' => $search_value);
		 *
		 *  Filter entries by Entry meta (added using the gform_entry_meta hook)
		 *     $search_criteria["field_filters"][] = array('key' => "gquiz_score", 'value' => "1");
		 *     $search_criteria["field_filters"][] = array('key' => "gquiz_is_pass", 'value' => "1");
		 *
		 *  Filter by ALL / ANY of the field filters
		 *     $search_criteria["field_filters"]["mode"] = "all"; // default
		 *     $search_criteria["field_filters"]["mode"] = "any";
		 *
		 * @param array $sorting - Specifies how the entries should be sorted. Entries can be sorted by a column in the lead table, by a field or by an entry meta. Following is the format.
		 *     $sorting = array('key' => $sort_field, 'direction' => $sort_direction );
		 *
		 * @param array $paging - Specifies the page size and page number, which controls which subset of the entry list will be returned. Following is the format:
		 *     $paging = array('offset' => 0, 'page_size' => 20 );
		 *
		 * @return array|GFWebAPIError - Returns an array of entry objects if the operation is completed successfully or an instance of GFWebAPIError if an error has occurred
		 */
		public function get_entries( $form_id, $search = null, $sorting = null, $paging = null ) {
			$form_id = absint( $form_id );

			$query['sorting'] = $sorting;
			$query['paging']  = $paging;
			$query['search']  = $search;

			$response = $this->send_request( 'GET', "forms/{$form_id}/entries", $query );

			return $this->prepare_response( $response );
		}

		/**
		 * Gets a single entry
		 *
		 * @param $entry_id - The ID of the entry to be returned
		 *
		 * @return array|GFWebAPIError - Returns an entry object if the operation is completed successfully or an instance of GFWebAPIError if an error has occurred.
		 */
		public function get_entry( $entry_id ) {
			$entry_id = absint( $entry_id );
			$response = $this->send_request( 'GET', "entries/{$entry_id}" );

			return $this->prepare_response( $response );
		}

		/**
		 * Updates an entry
		 * Updates an entry and returns true if the entry was updated successfully or an instance of GFWebAPIError if there was an error.
		 *
		 * @param $entry_id - The entry to be updated
		 * @param array $entry - The new entry (formatted as an array)
		 *
		 * @return bool|GFWebAPIError - Returns true if the entry was updated successfully or an instance of GFWebAPIError if there was an error.
		 */
		public function update_entry( $entry_id, $entry ) {
			$entry_id = absint( $entry_id );
			$response = $this->send_request( 'PUT', "entries/{$entry_id}", '', json_encode( $entry ) );

			$result = $this->prepare_response( $response );

			return GFWebAPIError::is_error( $result ) ? $result : true;
		}

		/**
		 * Creates new entries
		 * Creates all specified entries and returns an array of the new entry ids. If there was an error while creating the entries, an instance of GFWebAPIError will be returned.
		 *
		 * @param array $entries - An array of entry objects to be created. If an entry object has an "id" property, it will be ignored when creating it.
		 *
		 * @return array|GFWebAPIError - Returns an array with the new entry ids if the operation is completed successfully or an instance of GFWebAPIError if there was an error.
		 */
		public function create_entries( $entries ) {

			$response = $this->send_request( 'POST', 'entries', '', json_encode( $entries ) );

			$response = $this->prepare_response( $response );

			return $response;
		}

		/**
		 * Creates a single entry
		 * Creates an entry and returns the new entry id. If there was an error while creating the entry, an instance of GFWebAPIError will be returned.
		 *
		 * @param array $entry - The entry object to be created. If an entry object has an "id" property, it will be ignored when creating it.
		 *
		 * @return array|GFWebAPIError - Returns an array with the new entry ids if the operation is completed successfully or an instance of GFWebAPIError if there was an error.
		 */
		public function create_entry( $entry ) {

			$response = $this->create_entries( array( $entry ) );

			if ( ! GFWebAPIError::is_error( $response ) ) {
				$response = $response[0];
			}

			return $response;
		}

		/**
		 * Delete one or more entries
		 * Deletes a set of entries based on the $entry_ids parameter
		 *
		 * @param array $entry_ids - An array of entry ids to be deleted
		 *
		 * @return bool|GFWebAPIError - Returns true if all entries were deleted successfully. If one or more entries couldn't be deleted, an instance of GFWebAPIError is returned.
		 */
		public function delete_entries( $entry_ids ) {
			$entry_ids_string = implode( ';', $entry_ids );

			$response = $this->send_request( 'DELETE', "entries/{$entry_ids_string}" );

			$result = $this->prepare_response( $response );

			return GFWebAPIError::is_error( $result ) ? $result : true;
		}

		/**
		 * Delete a single entry
		 * Deletes a entry specified by the $entry_id parameter
		 *
		 * @param array $entry_id - The entry id to be deleted
		 *
		 * @return bool|GFWebAPIError - Returns true if the entry was deleted successfully, or an instance of GFWebAPIError is there was an error.
		 */
		public function delete_entry( $entry_id ) {

			return $this->delete_entries( array( $entry_id ) );

		}

		//------------- RESULTS --------------------------//
		/**
		 * Gets results
		 * Returns the results associated with the specified form id. Results are created by certain Add-Ons such as Quiz, Polls and Surveys.
		 *
		 * @param $form_id - The form id to return the results from.
		 *
		 * @return array|GFWebAPIError - Returns an array with the results if the operation was completed successfully or an instance of GFWebAPIError if there was an error.
		 */
		public function get_results( $form_id ) {
			$form_id  = absint( $form_id );
			$response = $this->send_request( 'GET', "forms/{$form_id}/results" );

			return $this->prepare_response( $response );
		}


		//------------------- HELPER METHODS ---------------------------------------

		private function prepare_response( $raw_response ) {

			if ( is_wp_error( $raw_response ) ) {
				return new GFWebAPIError( $raw_response->get_error_code(), $raw_response->get_error_message(), $raw_response->get_error_data() );
			}

			if ( $raw_response['response']['code'] != 200 ) {
				return new GFWebAPIError( 'Http: ' . $raw_response['response']['code'], $raw_response['response']['message'] );
			}

			$response = json_decode( $raw_response['body'], true );
			if ( ! isset( $response ) ) {
				return new GFWebAPIError( 'InvalidResponse', 'An invalid JSON string was returned by the server.', $raw_response['body'] );
			}

			$status   = wp_remote_retrieve_response_code( $raw_response );

			if ( ! in_array( $status, array( 200, 201, 202 ) ) ) {
				if ( is_array( $response ) ) {
					$data = isset( $response['data'] ) ? $response['data'] : '';

					return new GFWebAPIError( 'Status: ' . $status, $response['code'] . ' - ' . $response['message'], $data );
				} else {
					return new GFWebAPIError( 'Status: ' . $status, $response );
				}
			}

			//TODO: support returning JSON string as well
			return $response;
		}

		private function send_request( $method, $route, $query = null, $body = null ) {

			$url = $this->get_url( $method, $route, $query );

			return wp_remote_request( $url, array( 'method' => $method, 'body' => $body, 'timeout' => 25, 'headers' => array( 'Content-type' => 'application/json' ) ) );
		}

		private function get_url( $method, $route, $query = null ) {
			//expiration based from the current time
			$expires = ! empty( $this->_expires_seconds ) && is_numeric( $this->_expires_seconds ) ? time() + $this->_expires_seconds : time() + 600;

			//getting signature
			$signature = $this->get_signature( $method, $route, $expires );

			if ( empty( $query ) ) {
				$query = array();
			}

			$query['api_key']   = $this->_public_key;
			$query['signature'] = $signature;
			$query['expires']   = $expires;

			$query_str = http_build_query( $query );
			$query_str = urldecode( $query_str );

			//returning request url
			return trailingslashit( $this->_api_url ) . $route . '?' . $query_str;

		}

		private function get_signature( $method, $route, $expires ) {

			$string_to_sign = sprintf( '%s:%s:%s:%s', $this->_public_key, $method, $route, $expires );

			$hash = hash_hmac( 'sha1', $string_to_sign, $this->_private_key, true );
			$sig  = rawurlencode( base64_encode( $hash ) );

			return $sig;
		}
	}
}
