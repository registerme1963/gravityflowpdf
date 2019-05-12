<?php

/**
 * Gravity Flow Form Submission Merge Tag
 *
 * @package     GravityFlow
 * @copyright   Copyright (c) 2015-2018, Steven Henty S.L.
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 */

if ( ! class_exists( 'GFForms' ) ) {
	die();
}

/**
 * Class Gravity_Flow_Merge_Tag_PDF
 *
 * @since 1.3
 */
class Gravity_Flow_Merge_Tag_PDF extends Gravity_Flow_Merge_Tag {

	/**
	 * The name of the merge tag.
	 *
	 * @since 1.3
	 *
	 * @var string
	 */
	public $name = 'workflow_pdf_download_url';

	/**
	 * The regular expression to use for the matching.
	 *
	 * @since 1.3
	 *
	 * @var string
	 */
	protected $regex = '/{workflow_pdf_download_(url|link)(:(.*?))?}/';

	/**
	 * Replace the {workflow_pdf_download_url} and {workflow_pdf_download_link} merge tags.
	 *
	 * @since 1.3
	 *
	 * @param string $text The text being processed.
	 *
	 * @return string
	 */
	public function replace( $text ) {

		$matches = $this->get_matches( $text );

		if ( ! empty( $matches ) ) {

			if ( empty( $this->entry ) ) {
				foreach ( $matches as $match ) {
					$full_tag = $match[0];
					$text = str_replace( $full_tag, '', $text );
				}
				return $text;
			}

			foreach ( $matches as $match ) {
				$full_tag       = $match[0];
				$type           = $match[1];
				$options_string = isset( $match[3] ) ? $match[3] : '';

				$a = $this->get_attributes( $options_string, array(
					'text'    => __( 'Download PDF', 'gravityflowpdf' ),
					'signed'  => false,
					'expires' => '20 minutes',
				) );

				$url = add_query_arg( 'gravityflow-pdf-entry-id', $this->entry['id'], trailingslashit( home_url() ) );

				if ( $a['signed'] ) {
					try {
						$date = new DateTime( 'now', new DateTimeZone( 'UTC' ) );
					} catch( Exception $e ) {
						return $text;
					}
					$timeout = $date->modify( $a['expires'] );

					if ( ! $timeout ) {
						return $text;
					}

					$expiration = $timeout->getTimestamp();

					$signature = gravity_flow_pdf()->generate_signature( $url, $expiration );

					$url = add_query_arg( array( 'expires' => $expiration, 'signature' => $signature ), $url );
				}

				$url = $this->format_value( $url );

				if ( $type == 'link' ) {
					$url = sprintf( '<a href="%s">%s</a>', $url, $a['text'] );
				}

				$text = str_replace( $full_tag, $url, $text );
			}
		}

		return $text;
	}
}

Gravity_Flow_Merge_Tags::register( new Gravity_Flow_Merge_Tag_PDF );
