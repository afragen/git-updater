<?php

/**
 * Test methods in GHU_Trait.
 */
class Test_GHUTrait extends \WP_UnitTestCase {

	use Fragen\GitHub_Updater\Traits\GHU_Trait;

	/**
	 * Test sanitize.
	 *
	 * @dataProvider data_sanitize
	 *
	 * @param array $input
	 * @param array $expected
	 *
	 * @return void
	 */
	public function test_sanitize( $input = [], $expected ) {
		$this->assertSame( $expected, $this->sanitize( $input ) );
	}

	public function data_sanitize() {
		return [
			[ [], [] ],
			[ [ 0 => 'test' ], [ 0 => 'test' ] ],
			[ [ '0' => 'test' ], [ 0 => 'test' ] ],
			[ [ 'test' => 'test' ], [ 'test' => 'test' ] ],
			[ [ '<test' => '<test' ], [ 'test' => '&lt;test' ] ],
		];
	}
}
