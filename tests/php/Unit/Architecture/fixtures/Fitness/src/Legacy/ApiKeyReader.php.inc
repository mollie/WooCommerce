<?php

namespace Fixture\Legacy;

final class ApiKeyReader
{
    public function live($settings)
    {
        return $settings->getApiKey('live');
    }

    public function test($settings)
    {
        return $settings->getApiKey('test');
    }
}
