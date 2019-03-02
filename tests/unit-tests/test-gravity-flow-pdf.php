<?php

/**
 * Testing Gravity Flow PDF.
 *
 * @group testsuite
 */
class Tests_Gravity_Flow_PDF extends GF_UnitTestCase {

	/**
	 * @var Gravity_Flow_PDF
	 */
	protected $add_on;

	public function setUp() {
		parent::setUp();
		$this->add_on = gravity_flow_pdf();
	}

	public function test_get_destination_folder() {
		$path = $this->add_on->get_destination_folder();
		$this->assertNotEmpty( $path );
		$this->assertDirectoryExists( $path );
	}

	public function test_get_tmp_path() {
		$path = $this->add_on->get_tmp_path();
		$this->assertNotEmpty( $path );
		$this->assertDirectoryExists( $path );
	}

	public function test_get_file_name() {
		$entry_id = 1;
		$form_id  = 2;
		$this->assertSame( 'form-2-entry-1.pdf', $this->add_on->get_file_name( $entry_id, $form_id ) );
	}

	public function test_gravityflowpdf_file_name() {
		$this->ma->set_return_value( 'success' );
		add_filter( 'gravityflowpdf_file_name', array( $this->ma, 'return_value' ), 10, 3 );
		$result = $this->add_on->get_file_name( 1, 2 );
		remove_filter( 'gravityflowpdf_file_name', array( $this->ma, 'return_value' ) );

		$expected_events = array(
			array(
				'filter' => 'return_value',
				'tag'    => 'gravityflowpdf_file_name',
				'args'   => array( 'form-2-entry-1.pdf', 1, 2 )
			),
		);
		$this->assertSame( $expected_events, $this->ma->get_events() );
		$this->assertSame( 'success', $result );
	}

	public function test_generate_pdf() {
		$file_path = $this->add_on->get_destination_folder() . 'testing.pdf';
		$result    = $this->add_on->generate_pdf( 'testing', $file_path );
		$this->assertSame( $file_path, $result );
		$this->assertFileExists( $file_path );
	}

}
