<?php

# -*- coding: utf-8 -*-
declare (strict_types=1);
namespace Mollie\WooCommerce\Tracks;

class TracksEventRecorder
{
    private string $pluginVersion;
    public function __construct(string $pluginVersion)
    {
        $this->pluginVersion = $pluginVersion;
    }
    /**
     * Record a WooCommerce Tracks event.
     *
     * No-op when WC Tracks is unavailable or tracking is disabled.
     *
     * @param string $eventName Event name following WC Tracks conventions.
     * @param array  $properties Additional event properties.
     */
    public function recordEvent(string $eventName, array $properties = []): void
    {
        $properties['plugin_version'] = $this->pluginVersion;
        $properties['store_url'] = preg_replace('#^https?://(www\.)?#', '', get_site_url());
        do_action('mollie_tracks_event_recorded', $eventName, $properties);
        if (!class_exists('WC_Tracks')) {
            return;
        }
        \WC_Tracks::record_event($eventName, $properties);
    }
}
