<?php

use Inpsyde\EnvironmentChecker\Constraints\ExtensionConstraint;
use Inpsyde\EnvironmentChecker\Constraints\PhpConstraint;
use Inpsyde\EnvironmentChecker\Constraints\PluginConstraint;
use Inpsyde\EnvironmentChecker\Constraints\WordPressConstraint;
use Inpsyde\EnvironmentChecker\ConstraintsCollectionFactory;
use Inpsyde\EnvironmentChecker\EnvironmentChecker;
use Inpsyde\EnvironmentChecker\Exception\ConstraintFailedException;


class Mollie_WC_ActivationHandle_ConstraintsChecker
{

    /**
     * @var EnvironmentChecker
     */
    protected $checker;

    /**
     * @var Mollie_WC_Notice_NoticeInterface
     */
    protected $notice;


    /**
     * ConstraintsChecker constructor.
     *
     *
     */
    public function __construct()
    {
        $wpConstraint = new WordPressConstraint('3.8');
        $wcConstraint = new PluginConstraint('3.0', 'woocommerce', 'WooCommerce');
        $phpConstraint = new PhpConstraint('5.6');
        $jsonConstraint = new ExtensionConstraint('json');
        $collectionFactory = new ConstraintsCollectionFactory();
        $constraintsCollection = $collectionFactory->create(
            $wpConstraint,
            $wcConstraint,
            $phpConstraint,
            $jsonConstraint
        );
        $this->checker = new EnvironmentChecker(
            $constraintsCollection->constraints()
        );
        $this->notice = new Mollie_WC_Notice_AdminNotice();
    }

    /**
     * Notices of failed constraint
     * Deactivates the plugin if needed by not met constraint
     * prevents updates
     *
     * @return bool
     * @throws Exception
     */
    public function handleActivation()
    {
        try {
            $this->checker->check();
            return true;
        } catch (ConstraintFailedException $exception) {
            $mainException = $exception->getValidationErrors();
            $errors = [];
            foreach ($mainException as $error) {
                $errors[] = $error->getMessage();
            }
            $this->showNotice($errors);
            $disabler = new Mollie_WC_ActivationHandle_PluginDisabler(
                'mollie-payments-for-woocommerce',
                'mollie_wc_plugin_init'
            );
            $disabler->disableAll();
            return false;
        }
    }

    public function maybeShowWarning($constraint, $warning){
        $collectionFactory = new ConstraintsCollectionFactory();
        $constraintsCollection = $collectionFactory->create(
            $constraint
        );
        $result = new EnvironmentChecker(
            $constraintsCollection->constraints()
        );
        try {
            $result->check();
            return true;
        } catch (ConstraintFailedException $exception) {
            $mainException = $exception->getValidationErrors();
            $errors = [];
            foreach ($mainException as $error) {
                $errors[] = $error->getMessage();
            }

            $this->notice->addNotice('notice-warning is-dismissible', $warning);
            return false;
        }

    }

    protected function showNotice(array $errors)
    {
        $message = '%1$sMollie Payments for WooCommerce is inactive:%2$s';
        foreach ($errors as $error) {
            $message .= "<p>{$error}</p>";
        }
        $message .= "<p>Correct the above errors to use Mollie Payments for Woocommerce</p>";
        $message = sprintf(__($message, 'mollie-payments-for-woocommerce'), '<p><strong>', '</strong></p>');
        $errorLevel = 'notice-error';
        $this->notice->addNotice($errorLevel, $message);
    }
}
