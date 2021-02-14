<?php

namespace Automattic\LegacyRedirector\Tests;

use Brain\Monkey;
use Yoast\WPTestUtils\BrainMonkey\TestCase;

class PreservableParamsTest extends TestCase {

	public function data_get_preservable_querystring_params_from_url() {
		return array(
			// $url, $preservable_param_keys, $expected array.
			'No querystring'                         => array(
				'https://example.com',
				array( 'foo', 'bar', 'baz' ),
				array(),
			),
			'Empty list of param keys'               => array(
				'https://example.com?foo=123&bar=456',
				array(),
				array(),
			),
			'Single key'                             => array(
				'https://example.com?foo=123&bar=qwerty&baz=456',
				array( 'foo' ),
				array(
					'foo' => '123',
				),
			),
			'Multiple keys'                          => array(
				'https://example.com?foo=123&bar=qwerty&baz=456',
				array( 'foo', 'bar' ),
				array(
					'foo' => '123',
					'bar' => 'qwerty',
				),
			),
			'Multiple instance of preservable keys'  => array(
				'https://example.com?foo=123&bar=qwerty&baz=456',
				array( 'foo', 'bar', 'foo' ),
				array(
					'foo' => '123',
					'bar' => 'qwerty',
				),
			),
			'Multiple instance of URL keys'          => array(
				'https://example.com?foo=123&bar=qwerty&foo=456',
				array( 'foo', 'bar', 'foo' ),
				array(
					'foo' => '123',
					'bar' => 'qwerty',
					'foo' => '456',
				),
			),
			'URL key is an array'                    => array(
				'https://example.com?foo[]=123&bar=qwerty&foo[]=456',
				array( 'foo', 'bar', 'foo' ),
				array(
					'foo' => array(
						'123',
						'456',
					),
					'bar' => 'qwerty',
				),
			),
			'String returned from filter'            => array(
				'https://example.com?foo=123&bar=456',
				'foo',
				new \UnexpectedValueException(),
			),
			'Int returned from filter'               => array(
				'https://example.com?foo=123&bar=456',
				0,
				new \UnexpectedValueException(),
			),
			'Associative array returned from filter' => array(
				'https://example.com?foo=123&bar=456',
				array( 'foo' => 0, 'baz' => 1 ),
				new \UnexpectedValueException(),
			),
		);
	}

	/**
	* @covers WPCOM_Legacy_Redirector::get_preservable_querystring_params_from_url
	* @dataProvider data_get_preservable_querystring_params_from_url
	*/
	public function test_get_preservable_querystring_params_from_url( $url, $preservable_param_keys, $expected ) {

		Monkey\Filters\expectApplied( 'wpcom_legacy_redirector_preserve_query_params' )
			->once()
			->andReturn( $preservable_param_keys );


		Monkey\Functions\stubs(
			array(
				'wp_parse_url' => static function ( $url, $component ) {
					return \parse_url( $url, $component );
				},
			)
		);

		if ( ! is_array( $expected ) ) {
			$this->expectException( \get_class( $expected ) );
		}

		$actual = \WPCOM_Legacy_Redirector::get_preservable_querystring_params_from_url( $url );

		$this->assertSame( $expected, $actual, 'Preserved keys and values do not match.' );
	}
}
