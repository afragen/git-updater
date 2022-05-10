<?php

/**
 * Test methods in GU_Trait.
 */
class Test_GUTrait extends \WP_UnitTestCase {

	use Fragen\Git_Updater\Traits\GU_Trait;

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
	public function test_sanitize( $input = [], $expected = [] ) {
		$this->assertSame( $expected, $this->sanitize( $input ) );
	}

	public function data_sanitize() {
		return [
			[ [], [] ],
			[ [ 0 => 'test' ], [ 0 => 'test' ] ],
			[ [ '0' => 'test' ], [ 0 => 'test' ] ],
			[ [ 'test' => 'test' ], [ 'test' => 'test' ] ],
			[ [ 'test' => '<test' ], [ 'test' => '&lt;test' ] ],
			[ [ '<test' => '<test' ], [ '' => '&lt;test' ] ],
			[ [ 'test_one' => 'test' ], [ 'test_one' => 'test' ] ],
			[ [ 'test-one' => 'test' ], [ 'test-one' => 'test' ] ],

		];
	}
}
