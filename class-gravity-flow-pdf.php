<?php // Make sure Gravity Forms is active and already loaded.
if ( class_exists( 'GFForms' ) ) {
	class Gravity_Flow_PDF extends Gravity_Flow_Feed_Extension {

		private static $_instance = null;

		public $_version = GRAVITY_FLOW_PDF_VERSION;

		public $edd_item_name = GRAVITY_FLOW_PDF_EDD_ITEM_NAME;

		// The Framework will display an appropriate message on the plugins page if necessary
		protected $_min_gravityforms_version = '1.9.10';

		protected $_slug = 'gravityflowpdf';

		protected $_path = 'gravityflowpdf/pdf.php';

		protected $_full_path = __FILE__;

		// Title of the plugin to be used on the settings page, form settings and plugins page.
		protected $_title = 'Gravity Flow PDF Generator';

		protected $_capabilities = array(
			'gravityflowpdf_uninstall',
			'gravityflowpdf_settings',
			'gravityflowpdf_form_settings',
		);

		protected $_capabilities_app_settings = 'gravityflowpdf_settings';
		protected $_capabilities_uninstall = 'gravityflowpdf_uninstall';
		protected $_capabilities_form_settings = 'gravityflowpdf_form_settings';

		// Short version of the plugin title to be used on menus and other places where a less verbose string is useful.
		protected $_short_title = 'PDF';

		public static function get_instance() {
			if ( self::$_instance == null ) {
				self::$_instance = new Gravity_Flow_PDF();
			}

			return self::$_instance;
		}

		private function __clone() {
		} /* do nothing */

		/**
		 * The minimum requirements to use this extension.
		 *
		 * @since 1.2
		 *
		 * @return array
		 */
		public function minimum_requirements() {

			return array(
				'php' => array(
					'version'   => '5.6',
					'extensions' => array(
						'curl',
						'gd',
						'mbstring',
					),
					'functions' => array(
						'mb_substr',
						'mb_regex_encoding',
					),
				),
			);

		}

		public function __construct() {
			parent::__construct();
			add_action( 'wp', array( $this, 'maybe_get_pdf' ) );
		}

		public function init() {
			parent::init();
			add_action( 'gravityflow_workflow_complete', array( $this, 'action_gravityflow_workflow_complete' ), 10, 3 );
			add_action( 'gravityflow_step_complete', array( $this, 'action_gravityflow_step_complete' ), 10, 5 );
		}

		/**
		 * Add the extension capabilities to the Gravity Flow group in Members.
		 *
		 * @since 1.1-dev
		 *
		 * @param array $caps The capabilities and their human readable labels.
		 *
		 * @return array
		 */
		public function get_members_capabilities( $caps ) {
			$prefix = $this->get_short_title() . ': ';

			$caps['gravityflowpdf_settings']      = $prefix . __( 'Manage Settings', 'gravityflowpdf' );
			$caps['gravityflowpdf_uninstall']     = $prefix . __( 'Uninstall', 'gravityflowpdf' );
			$caps['gravityflowpdf_form_settings'] = $prefix . __( 'Manage Form Settings', 'gravityflowpdf' );

			return $caps;
		}

		/**
		 * Add the form settings tab.
		 *
		 * @since 1.3.4
		 *
		 * @param array $tabs    The form settings.
		 * @param int   $form_id The form ID.
		 *
		 * @return array
		 */
		public function add_form_settings_menu( $tabs, $form_id ) {

			$tabs[] = array(
				'name'         => $this->_slug,
				'label'        => gravity_flow()->translate_navigation_label( 'workflow' ) . ' PDF',
				'query'        => array( 'fid' => null ),
				'capabilities' => $this->_capabilities_form_settings,
			);

			return $tabs;
		}

		public function feed_settings_fields() {
			$account_choices = gravity_flow()->get_users_as_choices();
			$feed_settings   = array(
				'title'  => 'Settings',
				'fields' => array(
					array(
						'name'     => 'name',
						'label'    => __( 'Name', 'gravityflowpdf' ),
						'type'     => 'text',
						'class'    => 'medium',
						'required' => true,
						'tooltip'  => '<h6>' . __( 'Name', 'gravityflowpdf' ) . '</h6>' . __( 'Enter a name to uniquely identify this pdf.', 'gravityflowpdf' ),
					),
					array(
						'name'  => 'description',
						'label' => esc_html__( 'Description', 'gravityflowpdf' ),
						'class' => 'fieldwidth-3 fieldheight-2',
						'type'  => 'textarea',
					),
					array(
						'name'    => 'event',
						'label'   => __( 'Event', 'gravityflowpdf' ),
						'type'    => 'select',
						'choices' => array(
							array(
								'label' => esc_html__( 'Workflow Complete', 'gravityflowpdf' ),
								'value' => 'workflow_complete'
							),
							array(
								'label' => esc_html__( 'Workflow Step Complete: Approval', 'gravityflowpdf' ),
								'value' => 'workflow_step_complete_approval'
							),
							array(
								'label' => esc_html__( 'Workflow Step Complete: User Input', 'gravityflowpdf' ),
								'value' => 'workflow_step_complete_user_input'
							),
						),
					),
					array(
						'name'           => 'condition',
						'tooltip'        => esc_html__( "Build the conditional logic that should be applied to this feed before it's allowed to be processed. If an entry does not meet the conditions of this step it will fall on to the next step in the list.", 'gravityflowpdf' ),
						'label'          => 'Condition',
						'type'           => 'feed_condition_pdf',
						'checkbox_label' => esc_html__( 'Enable Condition', 'gravityflowpdf' ),
						'instructions'   => esc_html__( 'Use this PDF if', 'gravityflowpdf' ),
					),
				),
			);

			$description = '';
			if ( method_exists( 'Gravity_Flow_Common', 'get_total_accounts' ) ) {
				$total_accounts = Gravity_Flow_Common::get_total_accounts();
				$args           = Gravity_Flow_Common::get_users_args();
				$number         = ( isset( $args['number'] ) && $args['number'] > 0 ) ? $args['number'] : 2000;
				/* translators: 1: Warning icon 2: Number of users displayed 3: Open link tag 4: Close link tag */
				$description = ( $total_accounts > $number ) ? '<span class="gf_settings_description">' . sprintf( esc_html__( '%1$s The list of users contains only the first %2$s users in your site. %3$sLearn how to remove this limit%4$s. ', 'gravityflow' ), '<i class="dashicons dashicons-warning" style="color:red;"></i> ', $number, '<a href="https://docs.gravityflow.io/article/54-gravityflowgetusersargs" target="_blank">', '</a>' ) . '</span>' : '';
			}

			$settings = array(
				'title'  => 'PDF',
				'fields' => array(
					array(
						'name'     => 'custom_file_name',
						'label'    => __( 'Custom File Name', 'gravityflowpdf' ),
						'type'     => 'checkbox_and_text',
						'tooltip'  => '<h6>' . __( 'Custom File Name', 'gravityflowpdf' ) . '</h6>' . __( 'Enter a name to uniquely identify this PDF. Default file name format is "form-##-entry-##.pdf".', 'gravityflowpdf' ),
						'checkbox' => array(
							'name'          => 'enable_file_name',
							'label'         => __( 'Enabled', 'gravityflowpdf' ),
						),
						'text'     => array(
							'name'          => 'file_name',
							'class'         => 'medium merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
							'placeholder'   => 'form-{form_id}-entry-{entry_id}.pdf',
							'before'        => '<br />',
						),						
					),					
					array(
						'name'          => 'template',
						'label'         => esc_html__( 'Template', 'gravityflowpdf' ),
						'type'          => 'textarea',
						'use_editor'    => true,
						'default_value' => '{all_fields}',
					),
					array(
						'name'    => 'template_autoformat',
						'label'   => '',
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'label'         => __( 'Disable auto-formatting', 'gravityflowpdf' ),
								'name'          => 'template_disable_autoformat',
								'default_value' => false,
								'tooltip'       => __( 'Disable auto-formatting to prevent paragraph breaks being automatically inserted when using HTML to create the PDF template.', 'gravityflowpdf' ),
							),
						),
					),
					array(
						'name'    => 'workflow_notification_enabled',
						'label'   => __( 'Send by email', 'gravityflowpdf' ),
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'label'         => __( 'Enabled', 'gravityflowpdf' ),
								'name'          => 'workflow_notification_enabled',
								'default_value' => false,
								'tooltip'       => __( 'Enable this setting to send the PDF by email as an attachment. The PDF will be deleted automatically from the server.', 'gravityflowpdf' ),

							),
						),
					),
					array(
						'name'          => 'workflow_notification_type',
						'label'         => __( 'Send To', 'gravityflowpdf' ),
						'type'          => 'radio',
						'default_value' => 'select',
						'horizontal'    => true,
						'choices'       => array(
							array( 'label' => __( 'Select Users', 'gravityflowpdf' ), 'value' => 'select' ),
							array( 'label' => __( 'Configure Routing', 'gravityflowpdf' ), 'value' => 'routing' ),
						),
					),
					array(
						'id'          => 'workflow_notification_users',
						'name'        => 'workflow_notification_users[]',
						'label'       => __( 'Select User', 'gravityflowpdf' ),
						'size'        => '8',
						'multiple'    => 'multiple',
						'type'        => 'select',
						'choices'     => $account_choices,
						'description' => $description,
					),
					array(
						'name'        => 'workflow_notification_routing',
						'label'       => __( 'Routing', 'gravityflowpdf' ),
						'type'        => 'user_routing',
						'description' => $description,
					),
					array(
						'name'  => 'workflow_notification_from_name',
						'class' => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
						'label' => __( 'From Name', 'gravityflowpdf' ),
						'type'  => 'text',
					),
					array(
						'name'          => 'workflow_notification_from_email',
						'class'         => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
						'label'         => __( 'From Email', 'gravityflowpdf' ),
						'type'          => 'text',
						'default_value' => '{admin_email}',
					),
					array(
						'name'  => 'workflow_notification_reply_to',
						'class' => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
						'label' => __( 'Reply To', 'gravityflowpdf' ),
						'type'  => 'text',
					),
					array(
						'name'  => 'workflow_notification_cc',
						'class' => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
						'label' => __( 'CC', 'gravityflowpdf' ),
						'type'  => 'text',
						'tooltip'  => '<h6>' . __( 'Name', 'gravityflow' ) . '</h6>' . __( 'Be aware of any privacy policies your website is subject to that would apply to using the CC field. For example, GDPR indicates names and emails are private that should not be exposed.', 'gravityflow' ),
					),
					array(
						'name'  => 'workflow_notification_bcc',
						'class' => 'fieldwidth-2 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
						'label' => __( 'BCC', 'gravityflowpdf' ),
						'type'  => 'text',
					),
					array(
						'name'  => 'workflow_notification_subject',
						'class' => 'fieldwidth-1 merge-tag-support mt-hide_all_fields mt-position-right ui-autocomplete-input',
						'label' => __( 'Subject', 'gravityflowpdf' ),
						'type'  => 'text',
					),
					array(
						'name'          => 'workflow_notification_message',
						'label'         => __( 'Message', 'gravityflowpdf' ),
						'type'          => 'textarea',
						'use_editor'    => true,
						'default_value' => __( 'The PDF for entry {entry_id} is attached.', 'gravityflowpdf' ),
					),
					array(
						'name'    => 'workflow_notification_autoformat',
						'label'   => '',
						'type'    => 'checkbox',
						'choices' => array(
							array(
								'label'         => __( 'Disable auto-formatting', 'gravityflowpdf' ),
								'name'          => 'workflow_notification_disable_autoformat',
								'default_value' => false,
								'tooltip'       => __( 'Disable auto-formatting to prevent paragraph breaks being automatically inserted when using HTML to create the email message.', 'gravityflowpdf' ),

							),
						),
					),
				),
			);

			return array( $feed_settings, $settings );
		}

		public function scripts() {
			$form_id        = absint( rgget( 'id' ) );
			$form           = GFAPI::get_form( $form_id );
			$routing_fields = ! empty( $form ) ? GFCommon::get_field_filter_settings( $form ) : array();
			$input_fields   = array();
			if ( is_array( rgar( $form, 'fields' ) ) ) {
				foreach ( $form['fields'] as $field ) {
					/* @var GF_Field $field */
					$input_fields[] = array( 'key'  => absint( $field->id ),
					                         'text' => esc_html__( $field->get_field_label( false, null ) )
					);
				}
			}

			$users   = is_admin() ? gravity_flow()->get_users_as_choices() : array();
			$min     = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';
			$scripts = array(
				array(
					'handle'  => 'gravityflow_multi_select',
					'src'     => gravity_flow()->get_base_url() . "/js/multi-select{$min}.js",
					'deps'    => array( 'jquery' ),
					'version' => gravity_flow()->_version,
					'enqueue' => array(
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflowpdf' ),
					),
				),
				array(
					'handle'  => 'gf_routing_setting',
					'src'     => gravity_flow()->get_base_url() . "/js/routing-setting{$min}.js",
					'deps'    => array( 'jquery' ),
					'version' => gravity_flow()->_version,
					'enqueue' => array(
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflowpdf' ),
					),
					'strings' => array(
						'accounts'     => $users,
						'fields'       => $routing_fields,
						'input_fields' => $input_fields,
					),
				),
				array(
					'handle'  => 'gravityflow_form_settings_js',
					'src'     => gravity_flow()->get_base_url() . "/js/form-settings{$min}.js",
					'deps'    => array(
						'jquery',
						'jquery-ui-core',
						'jquery-ui-tabs',
						'jquery-ui-datepicker',
						'gform_datepicker_init',
						'gf_routing_setting',
					),
					'version' => gravity_flow()->_version,
					'enqueue' => array(
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflowpdf&fid=_notempty_' ),
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflowpdf&fid=0' ),
					),
					'strings' => array(
						'feedId'                    => absint( rgget( 'fid' ) ),
						'formId'                    => absint( rgget( 'id' ) ),
						'mergeTagLabels'            => gravity_flow()->get_form_settings_js_merge_tag_labels(),
						'assigneeSearchPlaceholder' => esc_attr__( 'Type to search', 'gravityflowpdf' ),
					),

				),
				array(
					'handle'  => 'gravityflow_quicksearch',
					'enqueue' => array(
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflowpdf&fid=_notempty_' ),
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflowpdf&fid=0' ),
					),
				),

			);

			return array_merge( parent::scripts(), $scripts );
		}

		public function styles() {

			$min = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG || isset( $_GET['gform_debug'] ) ? '' : '.min';

			$styles = array(
				array(
					'handle'  => 'gravityflow_multi_select_css',
					'src'     => gravity_flow()->get_base_url() . "/css/multi-select{$min}.css",
					'version' => gravity_flow()->_version,
					'enqueue' => array(
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflowpdf' ),
					),
				),
				array(
					'handle'  => 'gravityflow_form_settings',
					'src'     => gravity_flow()->get_base_url() . "/css/form-settings{$min}.css",
					'version' => gravity_flow()->_version,
					'enqueue' => array(
						array( 'query' => 'page=gf_edit_forms&view=settings&subview=gravityflowpdf' ),
					),
				),
			);

			return array_merge( parent::styles(), $styles );
		}

		function settings_user_routing( $field ) {
			return gravity_flow()->settings_user_routing( $field );
		}

		public function maybe_get_pdf() {
			if ( ! isset( $_REQUEST['gravityflow-pdf-entry-id'] ) ) {
				return;
			}

			$entry_id = $_REQUEST['gravityflow-pdf-entry-id'];

			$entry_id = absint( $entry_id );

			$entry = GFAPI::get_entry( $entry_id );

			if ( empty( $entry ) || is_wp_error( $entry ) ) {
				$message = esc_html__( 'Entry not found.', 'gravityflowpdf' );
				wp_die( $message, $message, 404 );
			}

			if ( isset( $_REQUEST['signature'] ) ) {
				$signature = sanitize_text_field( $_REQUEST['signature'] );
				$expires = absint( $_REQUEST['expires'] );

				if ( time() > $expires ) {
					$message = esc_html__( 'Expired.', 'gravityflowpdf' );
					wp_die( $message, $message, 401 );
				}

				$query_args = remove_query_arg( array( 'signature', 'expires' ) );
				$generated_signature = $this->generate_signature( untrailingslashit( home_url() ) . $query_args, $expires );
				if ( ! hash_equals( $generated_signature, $signature ) ) {
					$message = esc_html__( 'Invalid signature.', 'gravityflowpdf' );
					wp_die( $message, $message, 401 );
				}
			} else {
				$assignee_key = gravity_flow()->get_current_user_assignee_key();

				if ( ! $assignee_key ) {
					$message = esc_html__( 'Unauthorized.', 'gravityflowpdf' );
					wp_die( $message, $message, 401 );
				}

				if ( ! $this->is_download_authorized( $entry ) ) {
					$message = esc_html__( "You don't have access to this PDF.", 'gravityflowpdf' );
					wp_die( $message, $message, 403 );
				}
			}

			$form_id = $entry['form_id'];

			$name      = $this->get_file_name( $entry_id, $form_id );
			$file_path = $this->get_file_path( $entry_id, $form_id );

			$file = '';

			if ( @file_exists( $file_path ) ) {
				$file = @file_get_contents( $file_path );
			}

			//Download file
			header( 'X-Robots-Tag: noindex, nofollow', true );
			header( 'Content-Description: File Transfer' );
			header( 'Content-Transfer-Encoding: binary' );
			header( 'Cache-Control: public, must-revalidate, max-age=0' );
			header( 'Pragma: public' );
			header( 'Expires: Sat, 26 Jul 1997 05:00:00 GMT' );
			header( 'Last-Modified: ' . gmdate( 'D, d M Y H:i:s' ) . ' GMT' );
			header( 'Content-Type: application/force-download' );
			header( 'Content-Type: application/octet-stream', false );
			header( 'Content-Type: application/download', false );
			header( 'Content-Type: application/pdf', false );
			if ( ! isset( $_SERVER['HTTP_ACCEPT_ENCODING'] ) || empty( $_SERVER['HTTP_ACCEPT_ENCODING'] ) ) {
				// don't use length if server using compression
				header( 'Content-Length: ' . strlen( $file ) );
			}
			header( 'Content-disposition: attachment; filename="' . $name . '"' );

			echo $file;
			exit;
		}

		/**
		 * @param $entry
		 *
		 * @return bool|mixed|void
		 */
		public function is_download_authorized( $entry ){

			$authorized = false;

			if (  GFAPI::current_user_can_any( array( 'gravityforms_view_entries', 'gravityflow_status_view_all' ) ) ) {

				$authorized = true;

			} else {

				// User doesn't have access to all entries

				$assignee_key = gravity_flow()->get_current_user_assignee_key();

				if ( $assignee_key ) {

					$form_id = $entry['form_id'];

					// See if the user has access because they're assigned to the current step.
					$api  = new Gravity_Flow_API( $form_id );
					$step = $api->get_current_step( $entry );
					if ( $step && $step->is_assignee( $assignee_key ) ) {
						$authorized = true;
					}
				}
			}

			/**
			 * Allows the download authorization to be overridden. Only triggered for authenticated users and assignees.
			 *
			 * @since 1.1.1
			 *
			 * @param bool $authorized
			 * @param array $entry
			 */
			$authorized = apply_filters( 'gravityflowpdf_download_authorized', $authorized, $entry );

			return $authorized;
		}

		/**
		 * @param string                 $body      The PDF content.
		 * @param string                 $file_path The PDF path.
		 * @param bool|array             $entry     The current entry.
		 * @param bool|Gravity_Flow_Step $step      The current step.
		 *
		 * @return string
		 * @throws \Mpdf\MpdfException
		 */
		public function generate_pdf( $body, $file_path, $entry = false, $step = false ) {
			if ( ! class_exists( '\Mpdf\Mpdf' ) ) {
				require_once( 'vendor/autoload.php' );
			}

			$mpdf_config = array(
				'fontDir'          => array( $this->get_base_path() . '/includes/fonts/' ),
				'tempDir'          => $this->get_tmp_path(),
				'autoScriptToLang' => true,
				'autoLangToFont'   => true,
			);

			/**
			 * Allow the mPDF initialization properties to be overridden.
			 *
			 * @since 1.1.3
			 * @since 1.3.2 Added the $entry and $step arguments
			 *
			 * @param array                  $mpdf_config The mPDF initialization properties. See https://mpdf.github.io/reference/mpdf-variables/overview.html
			 * @param bool|array             $entry       The current entry.
			 * @param bool|Gravity_Flow_Step $step        The current step.
			 *
			 * @return array
			 */
			$mpdf_config = apply_filters( 'gravityflowpdf_mpdf_config', $mpdf_config, $entry, $step );

			$mpdf = new \Mpdf\Mpdf( $mpdf_config );

			if ( ! $mpdf ) {
				return $file_path;
			}

			$mpdf->SetCreator( 'Gravity Flow v' . GRAVITY_FLOW_VERSION . '. https://gravityflow.io' );

			/**
			 * Allow the content for PDF creation to be overridden.
			 *
			 * @since unknown
			 * @since 1.3.2   Added the $entry and $step parameters
			 *
			 * @param string                 $body      The markup for PDF as defined through step settings.
			 * @param string                 $file_path The path that PDF will be saved to.
			 * @param bool|array             $entry     The current entry.
			 * @param bool|Gravity_Flow_Step $step      The current step.
			 *
			 * @return string
			 */
			$body = apply_filters( 'gravityflowpdf_content', $body, $file_path, $entry, $step );

			/**
			 * Allow the content for PDF creation to be overridden.
			 *
			 * @since unknown
			 * @since 1.3.2   Added the $entry and $step parameters
			 *
			 * @param Mpdf\Mpdf              $mpdf      The mpdf instance - See https://mpdf.github.io/reference/mpdf-variables/overview.html
			 * @param string                 $body      The markup for PDF as defined through step settings.
			 * @param string                 $file_path The path that PDF will be saved to.
			 * @param bool|array             $entry     The current entry.
			 * @param bool|Gravity_Flow_Step $step      The current step.
			 *
			 * @return Mpdf\Mpdf
			 */
			$mpdf = apply_filters( 'gravityflowpdf_mpdf', $mpdf, $body, $file_path, $entry, $step );

			$mpdf->WriteHTML( $body );

			$mpdf->Output( $file_path );

			return $file_path;
		}

		/**
		 * Returns the path to the tmp directory to be used by mPDF.
		 *
		 * @since 1.1.3
		 *
		 * @return string
		 */
		public function get_tmp_path() {
			$path = $this->get_destination_folder() . 'tmp' . DIRECTORY_SEPARATOR;

			if ( ! is_dir( $path ) ) {
				wp_mkdir_p( $path );
			}

			return $path;
		}

		public function get_destination_folder() {
			$upload_dir = wp_upload_dir();
			$path       = $upload_dir['basedir'] . DIRECTORY_SEPARATOR . 'gravityflowpdf' . DIRECTORY_SEPARATOR;
			if ( ! is_dir( $path ) ) {
				wp_mkdir_p( $path );
			}
			$this->maybe_create_htaccess_file( $path );
			if ( is_multisite() ) {
				$blog_id = get_current_blog_id();
				$path    .= $blog_id . DIRECTORY_SEPARATOR;
				if ( ! is_dir( $path ) ) {
					wp_mkdir_p( $path );
				}
			}

			return $path;
		}

		public function get_file_path( $entry_id, $form_id ) {

			$folder = $this->get_destination_folder();

			$path = $folder . $this->get_file_name( $entry_id, $form_id );
			$path = apply_filters( 'gravityflowpdf_file_path', $path, $entry_id, $form_id );

			return $path;
		}

		/**
		 * Get the file name for the PDF download.
		 *
		 * @since 1.0.6.8
		 *
		 * @param int $entry_id The ID of the current entry.
		 * @param int $form_id  The ID of the current form.
		 *
		 * @return string
		 */
		public function get_file_name( $entry_id, $form_id ) {
			$file_name = 'form-' . $form_id . '-entry-' . $entry_id . '.pdf';

			/**
			 * Allows changing the default name of the PDF file generated.
			 *
			 * @since 1.1
			 *
			 * @param string    $file_name  The name of PDF as defined through step settings.
			 * @param int       $entry_id   The entry id of the current entry.
			 * @param int       $form_id    The form id of the current form.
			 *
			 * @return string
			 */
			return apply_filters( 'gravityflowpdf_file_name', $file_name, $entry_id, $form_id );
		}

		public static function maybe_create_htaccess_file( $path ) {
			$htaccess_file = $path . '.htaccess';
			if ( file_exists( $htaccess_file ) ) {
				return;
			}
			$txt   = '# Disable access to files via Apache webservers.
deny from all';
			$rules = explode( "\n", $txt );

			if ( ! function_exists( 'insert_with_markers' ) ) {
				require_once( ABSPATH . 'wp-admin/includes/misc.php' );
			}
			insert_with_markers( $htaccess_file, 'Gravity Flow', $rules );

			return true;
		}

		public function settings_feed_condition_pdf( $field, $echo = true ) {

			$form       = $this->get_current_form();
			$form_id    = absint( $form['id'] );
			$entry_meta = $this->get_all_entry_meta( $form_id );
			$html       = '<script>';
			$html       .= 'var entry_meta=' . GFCommon::json_encode( $entry_meta );
			$html       .= '</script>';
			$html       .= parent::settings_feed_condition( $field, false );

			if ( $echo ) {
				echo $html;
			}

			return $html;
		}

		public function get_all_entry_meta( $form_ids ) {
			global $_entry_meta;

			if ( $form_ids == 0 ) {
				$form_ids = GFFormsModel::get_form_ids();
			}

			if ( ! is_array( $form_ids ) ) {
				$form_ids = array( $form_ids );
			}
			$meta = array();
			if ( ! isset( $_entry_meta ) ) {
				$_entry_meta = array();
			}
			foreach ( $form_ids as $form_id ) {
				$_entry_meta[ $form_id ] = apply_filters( 'gform_entry_meta', array(), $form_id );
				$meta                    = array_merge( $meta, $_entry_meta[ $form_id ] );
			}

			return $meta;
		}

		public function action_gravityflow_workflow_complete( $entry_id, $form, $step_status ) {
			$this->process_pdf_feeds( $entry_id, $form, 'workflow_complete' );
		}

		/**
		 * Callback for the gravityflow_step_complete action.
		 *
		 * Triggers the PDF templates for the Approval and User Input steps.
		 *
		 *
		 * @param int $step_id
		 * @param int $entry_id
		 * @param int $form_id
		 * @param string $status
		 * @param Gravity_Flow_Step $step
		 */
		public function action_gravityflow_step_complete( $step_id, $entry_id, $form_id, $status, $step ) {
			$form = GFAPI::get_form( $form_id );
			$this->process_pdf_feeds( $entry_id, $form, 'workflow_step_complete_' . $step->get_type() );
		}

		public function process_pdf_feeds( $entry_id, $form, $event = 'workflow_complete' ) {
			$meets_requirements = $this->meets_minimum_requirements();
			if ( ! $meets_requirements['meets_requirements'] ) {
				return;
			}

			$entry = GFAPI::get_entry( $entry_id );
			$feeds = $this->get_active_feeds( $form['id'] );
			foreach ( $feeds as $feed ) {
				if ( $feed['meta']['event'] === $event ) {
					$feed['meta']['step_type'] = 'pdf';
					$feed['meta']['step_name'] = $feed['meta']['name'];
					if ( $this->is_feed_condition_met( $feed, $form, $entry ) ) {
						$pdf_step = new Gravity_Flow_Step_PDF( $feed, $entry );
						$pdf_step->process();
					}
				}
			}
		}

		/**
		 * Adds columns to the list of feeds.
		 *
		 * setting name => label
		 *
		 * @return array
		 */
		public function feed_list_columns() {
			return array(
				'name'        => __( 'Name', 'gravityflowpdf' ),
				'description' => __( 'Description', 'gravityflowpdf' ),
			);
		}

		public function feed_list_title() {
			if ( ! $this->can_create_feed() ) {
				return esc_html__( 'PDF Templates', 'gravityflowpdf' );
			}

			$url = add_query_arg( array( 'fid' => '0' ) );
			$url = esc_url( $url );

			return sprintf( esc_html__( 'PDF Templates', 'gravityflowpdf' ), $this->get_short_title() ) . " <a class='add-new-h2' href='{$url}'>" . esc_html__( 'Add New', 'gravityflowpdf' ) . '</a>';
		}

		public function feed_settings_title() {
			return esc_html__( 'PDF Template Settings', 'gravityflowpdf' );
		}

		public function feed_list_no_item_message() {
			return esc_html__( "You don't have any PDF templates configured.", 'gravityflowpdf' );
		}

		/**
		 *
		 * Generates a signature for the given download URL and expiration.
		 *
		 * @since 1.3
		 *
		 * @param $url
		 * @param $expiration
		 *
		 * @return false|string
		 */
		public function generate_signature( $url, $expiration ) {
			$secret_key = get_option( 'gravityflowpdf_signed_secret_token', '' );

			if ( empty( $secret_key ) ) {
				$secret_key = wp_generate_password( 64 );
				update_option( 'gravityflowpdf_signed_secret_token', $secret_key );
			}

			$token_data = array(
				'expires' => $expiration,
				'url'     => (string) $url,
			);

			$token = rawurlencode( base64_encode( json_encode( $token_data ) ) );

			return hash_hmac( 'sha256', $token, $secret_key );
		}
	}
}
