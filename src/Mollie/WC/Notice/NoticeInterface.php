<?php


/**
 * Interface NoticeInterface
 *
 * @package Mollie\WC\Notice
 */
interface Mollie_WC_Notice_NoticeInterface
{

    /**
     * @param string $level class to apply: ex. 'notice-error'
     * @param string $message translated message
     *
     * @return mixed
     */
    public function addNotice($level, $message);
}
