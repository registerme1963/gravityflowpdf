<?php

if ( class_exists( 'Gravity_Flow_Step' ) ) {

	class Gravity_Flow_Step_PDF extends Gravity_Flow_Step {
		public $_step_type = 'pdf';

		public function get_label() {
			return esc_html__( 'PDF', 'gravityflowpdf' );
		}

		public function get_icon_url() {
			return plugins_url( 'images/pdf.svg' ,'gravityflowpdf/pdf.php' );
		}

		/**
		 * Determines if this step is supported on this server.
		 *
		 * @since 1.x
		 *
		 * @return bool
		 */
		public function is_supported() {
			$is_supported       = true;
			$meets_requirements = gravity_flow_pdf()->meets_minimum_requirements();
			if ( ! $meets_requirements['meets_requirements'] ) {
				$is_supported = false;
			}

			return $is_supported;
		}

		/**
		 * Ensures active steps are not processed when not supported.
		 *
		 * @since 1.x
		 *
		 * @return bool
		 */
		public function is_active() {
			$is_active = parent::is_active();

			if ( $is_active && ! $this->is_supported() ) {
				$is_active = false;
			}

			return $is_active;
		}

		public function get_settings() {

			$settings = gravity_flow_pdf()->feed_settings_fields();

			return $settings[1];
		}

		function process() {

			try {
				$this->generate_pdf();
			} catch ( Exception $e ) {
				gravity_flow()->log_error( __METHOD__ . '(): Unable to generate PDF. ' . $e->getMessage() );
				$note = sprintf( esc_html__( 'Error: Unable to generate PDF. %s', 'gravityflowpdf' ), $e->getMessage() );
				$this->add_note( $note );

				return false;
			}

			$note = esc_html__( 'PDF Generated', 'gravityflowpdf' );
			$this->add_note( $note, 0, $this->get_type() );

			$this->send_email();

			return true;
		}

		function generate_pdf() {

			$entry = $this->get_entry();
			$form  = $this->get_form();

			$body = $this->template;
			$body = $this->replace_variables( $body, null );

			add_filter( 'gform_merge_tag_filter', array( $this, 'maybe_filter_merge_tag' ), 11, 5 );
			$body = GFCommon::replace_variables( $body, $form, $entry, false, false, ! $this->template_disable_autoformat );
			remove_filter( 'gform_merge_tag_filter', array( $this, 'maybe_filter_merge_tag' ), 11 );

			/**
			 * Support processing shortcodes placed in the pdf template.
			 *
			 * @param bool $process_template_shortcodes Should shortcodes be processed. Default is true.
			 * @param array $form The current form.
			 * @param array $entry The current entry.
			 * @param Gravity_Flow_Step_PDF $step The pdf step currently being processed.
			 */
			$process_template_shortcodes = apply_filters( 'gravityflowpdf_process_template_shortcodes', true, $form, $entry, $this );
			if ( $process_template_shortcodes ) {
				$body = do_shortcode( $body );
			}

			$file_path = gravity_flow_pdf()->get_file_path( $this->get_entry_id(), $form['id'] );

			gravity_flow_pdf()->generate_pdf( $body, $file_path, $entry, $this );
		}

		public function send_email() {

			if ( ! $this->workflow_notification_enabled ) {
				return;
			}

			$assignees = array();

			$notification_type = $this->workflow_notification_type;

			switch ( $notification_type ) {
				case 'select':
					if ( is_array( $this->workflow_notification_users ) ) {
						foreach ( $this->workflow_notification_users as $assignee_key ) {
							$assignees[] = new Gravity_Flow_Assignee( $assignee_key, $this );
						}
					}
					break;
				case 'routing':
					$routings = $this->workflow_notification_routing;
					if ( is_array( $routings ) ) {
						foreach ( $routings as $routing ) {
							if ( $user_is_assignee = $this->evaluate_routing_rule( $routing ) ) {
								$assignees[] = new Gravity_Flow_Assignee( rgar( $routing, 'assignee' ), $this );
							}
						}
					}

					break;
			}

			if ( empty( $assignees ) ) {
				return;
			}

			$notification['workflow_notification_type'] = 'workflow';
			$notification['fromName']                   = $this->workflow_notification_from_name;
			$notification['from']                       = $this->workflow_notification_from_email;
			$notification['replyTo']                    = $this->workflow_notification_reply_to;
			$notification['cc']                         = $this->workflow_notification_cc;
			$notification['bcc']                        = $this->workflow_notification_bcc;
			$notification['subject']                    = $this->workflow_notification_subject;
			$notification['message']                    = $this->workflow_notification_message;
			$notification['disableAutoformat']          = $this->workflow_notification_disable_autoformat;

			$this->send_notifications( $assignees, $notification );

			$note = esc_html__( 'Sent Notification: ', 'gravityflowpdf' ) . $this->get_name();
			$this->add_note( $note, 0, $this->get_type() );

			$file_path = gravity_flow_pdf()->get_file_path( $this->get_entry_id(), $this->get_form_id() );

			$delete_pdf = true;
			$form       = $this->get_form();
			$entry      = $this->get_entry();

			/**
			 * Allows the PDF to be retained on the server after sending by email.
			 *
			 * Care should be taken to ensure that the workflow doesn't allow subsequent assignees to access the PDF with sensitive data.
			 *
			 * @since 1.3
			 *
			 * @param bool $delete_pdf Whether to delete the PDF after sending by email.
			 *
			 * @param array $form The form array
			 * @param array $entry The entry array
			 * @param Gravity_Flow_Step_PDF This PDF step
			 */
			$delete_pdf = apply_filters( 'gravityflowpdf_delete_post_send', $delete_pdf, $form, $entry, $this );

			if ( $delete_pdf ) {
				@unlink( $file_path );
			}
		}

		public function send_notification( $notification ) {

			$entry = $this->get_entry();

			$form = $this->get_form();

			$file_path = gravity_flow_pdf()->get_file_path( $this->get_entry_id(), $form['id'] );

			if ( ! isset( $notification['attachments'] ) ) {
				$notification['attachments'] = array();
			}

			$notification['attachments'][] = $file_path;

			$notification = apply_filters( 'gravityflow_notification', $notification, $form, $entry, $this );

			$this->log_debug( __METHOD__ . '() - sending notification: ' . print_r( $notification, true ) );

			add_filter( 'gform_notification_enable_cc', '__return_true' );
			GFCommon::send_notification( $notification, $form, $entry );
			remove_filter( 'gform_notification_enable_cc', '__return_true' );

		}

		/**
		 * Filter the all_fields merge tag output for the Signature field.
		 *
		 * The gf_signature page url does not work with mPDF; replace it with the path to the image instead.
		 *
		 * @param string $value The value of the field currently being processed.
		 * @param string $merge_tag The merge tag (i.e. all_field) or the field/input ID when processing a merge tag for an individual field.
		 * @param string $options The merge tag modifiers. e.g. "value,nohidden" would be the modifiers for {all_fields:value,nohidden}.
		 * @param GF_Field $field The field currently being processed.
		 * @param mixed $raw_field_value The fields raw value before it was processed by $field->get_value_entry_detail().
		 *
		 * @return string
		 */
		public function maybe_filter_merge_tag( $value, $merge_tag, $options, $field, $raw_field_value ) {
			if ( $merge_tag == 'all_fields' && $field->type == 'signature' && ! empty( $raw_field_value ) ) {
				$show_in_all_fields = apply_filters( 'gform_signature_show_in_all_fields', true, $field, $options, $value );
				if ( $show_in_all_fields && function_exists( 'gf_signature' ) ) {
					$find    = gf_signature()->get_signature_url( $raw_field_value );
					$replace = GFFormsModel::get_upload_root() . 'signatures/' . $raw_field_value;

					return str_replace( $find, $replace, $value );
				}
			}

			return $value;
		}
	}

}



