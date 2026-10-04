<?php

namespace Fixture\Legacy;

final class HandlerLookup
{
    public function handlers($container)
    {
        return $container->get('__deprecated.gateway_helpers');
    }
}
