<?php
/**
 * Class ValidatedUrlStylesheetDataGarbageCollectionTest.
 *
 * @package AmpProject\AmpWP
 */

use AmpProject\AmpWP\BackgroundTask\ValidatedUrlStylesheetDataGarbageCollection;

/** @covers ValidatedUrlStylesheetDataGarbageCollection */
class ValidatedUrlStylesheetDataGarbageCollectionTest extends WP_UnitTestCase {

	/**
	 * Test whether an event is actually scheduled when the garbage collection is registered.
	 *
	 * @covers ValidatedUrlStylesheetDataGarbageCollection::activate()
	 * @covers ValidatedUrlStylesheetDataGarbageCollection::deactivate()
	 */
	public function test_event_gets_scheduled_and_unscheduled() {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->assertFalse( wp_next_scheduled( ValidatedUrlStylesheetDataGarbageCollection::EVENT_NAME ) );

		$monitor = new ValidatedUrlStylesheetDataGarbageCollection();
		$monitor->schedule_event();

		$timestamp = wp_next_scheduled( ValidatedUrlStylesheetDataGarbageCollection::EVENT_NAME );

		$this->assertNotFalse( $timestamp );
		$this->assertInternalType( 'int', $timestamp );
		$this->assertGreaterThan( 0, $timestamp );

		$monitor->deactivate( false );

		$this->assertFalse( wp_next_scheduled( ValidatedUrlStylesheetDataGarbageCollection::EVENT_NAME ) );
	}

	/**
	 * Test whether stylesheet data is deleted when the garbage collection is processing.
	 *
	 * @covers ValidatedUrlStylesheetDataGarbageCollection::process()
	 */
	public function test_event_can_be_processed() {
		$monitor = new ValidatedUrlStylesheetDataGarbageCollection();

		// Insert four weeks of validated URLs.
		$post_ids = [];
		for ( $days_ago = 1; $days_ago <= 28; $days_ago++ ) {
			$post_date = gmdate( 'Y-m-d H:i:s', strtotime( "$days_ago days ago" ) + 2 );
			$post_id   = AMP_Validated_URL_Post_Type::store_validation_errors(
				[],
				home_url( "/days-ago-$days_ago/" ),
				[ 'stylesheets' => [ '/*...*/' ] ]
			);
			wp_update_post(
				[
					'ID'            => $post_id,
					'post_date_gmt' => $post_date,
					'post_date'     => $post_date,
				]
			);
			$post_ids[ $days_ago ] = $post_id;
		}

		$monitor->process();

		foreach ( $post_ids as $days_ago => $post_id ) {
			if ( $days_ago > 7 ) {
				$this->assertEmpty( get_post_meta( $post_id, AMP_Validated_URL_Post_Type::STYLESHEETS_POST_META_KEY ), "Expected $days_ago days ago to be empty." );
			} else {
				$this->assertNotEmpty( get_post_meta( $post_id, AMP_Validated_URL_Post_Type::STYLESHEETS_POST_META_KEY ), "Expected $days_ago days ago to not be empty." );
			}
		}
	}
}
