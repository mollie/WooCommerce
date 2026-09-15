<?php

namespace Mollie\libphonenumber;

/**
 * @internal
 */
class DefaultMetadataLoader implements MetadataLoaderInterface
{
    public function loadMetadata($metadataFileName)
    {
        return include $metadataFileName;
    }
}
