=== Gravity Flow PDF Extension ===
Contributors: stevehenty
Tags: gravity forms, approvals, workflow
Requires at least: 4.0
Tested up to: 4.6.1
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Create PDF files from entries in Gravity Flow.

== Description ==

Gravity Flow PDF Generator is an extension for Gravity Flow.

Gravity Flow is an Add-On for [Gravity Forms](https://gravityflow.io/gravityforms)

Facebook: [Gravity Flow](https://www.facebook.com/gravityflow.io)

= Requirements =

1. [Purchase and install Gravity Forms](https://gravityflow.io/gravityforms)
1. [Purchase and install Gravity Flow](https://gravityflow.io)
1. Wordpress 4.2+
1. Gravity Forms 1.9.4+
1. Gravity Flow 1.1.0.4+


= Support =
If you find any that needs fixing, or if you have any ideas for improvements, please get in touch:
https://gravityflow.io/contact/


== Installation ==

1.  Download the zipped file.
1.  Extract and upload the contents of the folder to /wp-contents/plugins/ folder
1.  Go to the Plugin management page of WordPress admin section and enable the 'Gravity Flow PDF Extension' plugin.

== Frequently Asked Questions ==

= Which license of Gravity Flow do I need? =
The Gravity Flow PDF Generator Extension will work with any license of [Gravity Flow](https://gravityflow.io).


== ChangeLog ==

= 1.0.6.7 =
- Fixed an issue with the PDF when the content includes complex scripts such as Thai.

= 1.0.6.6 =
- Fixed PHP error when mPDF has already been included by another plugin.

= 1.0.6.5 =
- Updated mPDF to v6.1.3.

= 1.0.6.4 =
- Added Spanish translation.
- Added Chinese translation.

= 1.0.6.3 =
- Fixed conditional shortcodes in the template failing conditional logic evaluation due to the merge tags not being replaced early enough.

= 1.0.6.2 =
- Fixed an issue which prevented the Signature field image being displayed in the PDF when using the {all_fields} merge tag.

= 1.0.6.1 =
- Added the gravityflowpdf_mpdf filter to allow the mPDF object to be modified just before the PDF is generated.
    Example:
    add_filter( 'gravityflowpdf_mpdf', 'gravityflow_filter_gravityflowpdf_mpdf', 10, 3 );
    function gravityflow_filter_gravityflowpdf_mpdf( $mpdf, $body, $file_path ) {
    	// modify $mpdf properties
    	return $mpdf;
    }
= 1.0.6 =
- Added the gravityflowpdf_content filter to allow the content to be modified just before generating the PDF.
    Example:
    add_filter( 'gravityflowpdf_content', 'gravityflow_filter_gravityflowpdf_content', 10, 2 );

    /**
     * Prevent the {all_fields} merge tag from shrinking all the text in the table for long tables.
     *
     * @param string $body
     * @param string $file_path
     *
     * @return string
     */
    function gravityflow_filter_gravityflowpdf_content( $body, $file_path ) {
    	$body = str_replace( '<table width="99%" border="0" cellpadding="1" cellspacing="0" bgcolor="#EAEAEA"><tr><td>', '', $body );
    	$body = str_replace( "</td>\r\n                   </tr>\r\n               </table>", '', $body );

    	return $body;
    }

- Updated mPDF to 6.1

= 1.0.5.1 =
- Fixed an issue with the shortcode parsing.

= 1.0.5 =
- Fixed a warning when an entry doesn't exist.

= 1.0.4 =
- Fixed an issue with the entry meta for some installations.

= 1.0.3 =
- Added the Disable auto-formatting setting pdf template and email message.
- Added support for processing shortcodes in the pdf template. Return false to gravityflowpdf_process_template_shortcodes to disable processing.
    Example:
    add_filter( 'gravityflowpdf_process_template_shortcodes', '__return_false' );

= 1.0.2 =
- Fixed an issue with the plugin zip file size
- Fixed an issue on the PDF feeds page where the Add New button is not available.

= 1.0.1 =
- Added the PDF templates form settings.
- Added gravity_flow_pdf()->get_file_path and gravity_flow_pdf()->generate_pdf() to allow PDFs to be generated more easily from custom code.
    Examples:
    // Get the file path of the PDF (filtered by gravityflowpdf_file_path)
	$filepath = gravity_flow_pdf()->get_file_path( $entry['id'] );
	// Generate the PDF
	gravity_flow_pdf()->generate_pdf( $body, $filepath );
- Added the gravityflowpdf_file_path filter to allow the file path to be modified.
    Example:
    add_filter( 'gravityflowpdf_file_path', 'sh_gravityflowpdf_file_path', 10, 2 );
    function sh_gravityflowpdf_file_path( $path, $entry_id ) {
        return $path;
    }
- Added support for the {workflow_timeline} merge tag.
- Fixed an issue with permissions.

= 1.0 =
All new!
