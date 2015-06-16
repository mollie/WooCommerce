<?php
class WC_Mollie_Exception_IncompatiblePlatform extends WC_Mollie_Exception
{
    const API_CLIENT_NOT_INSTALLED    = 1000;
    const PHP_NOT_COMPATIBLE          = 1500;
    const COULD_NOT_CONNECT_TO_MOLLIE = 2000;
}
