<?php

if ( class_exists( 'Gravity_Flow_Step' ) ) {

	class Gravity_Flow_Step_PDF extends Gravity_Flow_Step {
		public $_step_type = 'pdf';

		public function get_label() {
			return esc_html__( 'PDF', 'gravityflow' );
		}

		public function get_icon_url() {
			return plugins_url( 'images/pdf.svg' ,'gravityflowpdf/pdf.php' );
		}

		public function get_settings() {

			$settings = gravity_flow_pdf()->feed_settings_fields();

			return $settings[1];
		}

		function process() {

			$this->generate_pdf();

			$note = esc_html__( 'PDF Generated', 'gravityflow' );
			$this->add_note( $note, 0, $this->get_type() );

			$this->send_email();

			return true;
		}

		function generate_pdf() {

			$entry = $this->get_entry();

			$form = $this->get_form();

			$body = $this->template;

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

			$body = $this->replace_variables( $body, null );
			$body = GFCommon::replace_variables( $body, $form, $entry, false, false, ! $this->template_disable_autoformat );

			$file_path = gravity_flow_pdf()->get_file_path( $this->get_entry_id() );

			gravity_flow_pdf()->generate_pdf( $body, $file_path );
		}

		public function send_email() {

			if ( ! $this->workflow_notification_enabled ) {
				return;
			}

			$assignees = array();

			$notification_type = $this->workflow_notification_type;

			switch ( $notification_type ) {
				case 'select' :
					if ( is_array( $this->workflow_notification_users ) ) {
						foreach ( $this->workflow_notification_users as $assignee_key ) {
							$assignees[] = new Gravity_Flow_Assignee( $assignee_key, $this );
						}
					}
					break;
				case 'routing' :
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
			$notification['bcc']                        = $this->workflow_notification_bcc;
			$notification['subject']                    = $this->workflow_notification_subject;
			$notification['message']                    = $this->workflow_notification_message;
			$notification['disableAutoformat']          = $this->workflow_notification_disable_autoformat;

			$this->send_notifications( $assignees, $notification );

			$note = esc_html__( 'Sent Notification: ', 'gravityflow' ) . $this->get_name();
			$this->add_note( $note, 0, $this->get_type() );

			$file_path = gravity_flow_pdf()->get_file_path( $this->get_entry_id() );
			@unlink( $file_path );
		}

		public function send_notification( $notification ) {

			$entry = $this->get_entry();

			$form = $this->get_form();

			$file_path = gravity_flow_pdf()->get_file_path( $this->get_entry_id() );

			if ( ! isset( $notification['attachments'] ) ) {
				$notification['attachments'] = array();
			}

			$notification['attachments'][] = $file_path;

			$notification = apply_filters( 'gravityflow_notification', $notification, $form, $entry, $this );

			$this->log_debug( __METHOD__ . '() - sending notification: ' . print_r( $notification, true ) );

			GFCommon::send_notification( $notification, $form, $entry );

		}
	}

}



