<?php # -*- coding: utf-8 -*-

namespace Mollie\WooCommerceTests\Functional\Tracks;

use Mollie\WooCommerce\Tracks\TracksEventRecorder;
use Mollie\WooCommerceTests\TestCase;
use Mockery;

use function Brain\Monkey\Functions\expect;
use function Brain\Monkey\Functions\when;

class TracksEventRecorderTest extends TestCase
{
    /**
     * WHEN WC_Tracks class is not available
     * THEN recordEvent skips silently
     * @test
     */
    public function recordEventSkipsWhenWcTracksUnavailable()
    {
        when('get_site_url')->justReturn('https://example.com');

        // WC_Tracks is not loaded in the test environment, so class_exists returns false natively
        $recorder = new TracksEventRecorder('8.1.6');
        $recorder->recordEvent('mollie_plugin_activated');

        $this->addToAssertionCount(1);
    }

    /**
     * WHEN recordEvent is called
     * THEN properties are enriched with plugin_version and store_url
     * @test
     */
    public function recordEventEnrichesProperties()
    {
        when('get_site_url')->justReturn('https://www.example.com');

        $captured = null;
        expect('do_action')
            ->once()
            ->with('mollie_tracks_event_recorded', 'mollie_plugin_activated', Mockery::on(function ($props) use (&$captured) {
                $captured = $props;
                return true;
            }));

        $recorder = new TracksEventRecorder('8.1.6');
        $recorder->recordEvent('mollie_plugin_activated', ['is_test_mode' => true]);

        $this->assertNotNull($captured);
        $this->assertEquals('8.1.6', $captured['plugin_version']);
        $this->assertEquals('example.com', $captured['store_url']);
        $this->assertTrue($captured['is_test_mode']);
    }

    /**
     * WHEN WC_Tracks is available
     * THEN recordEvent forwards event to WC_Tracks::record_event
     * @test
     */
    public function recordEventForwardsToWcTracks()
    {
        when('get_site_url')->justReturn('https://example.com');

        $wcTracks = Mockery::mock('alias:\WC_Tracks');
        $wcTracks->shouldReceive('record_event')
            ->once()
            ->withArgs(function ($name, $props) {
                return $name === 'mollie_plugin_activated'
                    && $props['plugin_version'] === '8.1.6';
            })
            ->andReturn(true);

        $recorder = new TracksEventRecorder('8.1.6');
        $recorder->recordEvent('mollie_plugin_activated');

        $this->addToAssertionCount(1);
    }
}
