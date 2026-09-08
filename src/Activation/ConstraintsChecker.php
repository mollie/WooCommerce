<?php

declare (strict_types=1);
namespace Mollie\WooCommerce\Activation;

use Mollie\Inpsyde\EnvironmentChecker\Constraints\ExtensionConstraint;
use Mollie\Inpsyde\EnvironmentChecker\Constraints\PhpConstraint;
use Mollie\Inpsyde\EnvironmentChecker\Constraints\PluginConstraint;
use Mollie\Inpsyde\EnvironmentChecker\Constraints\WordPressConstraint;
use Mollie\Inpsyde\EnvironmentChecker\ConstraintsCollectionFactory;
use Mollie\Inpsyde\EnvironmentChecker\EnvironmentChecker;
use Mollie\Inpsyde\EnvironmentChecker\Exception\ConstraintFailedException;
use Mollie\WooCommerce\Notice\AdminNotice;
use Mollie\WooCommerce\Notice\NoticeInterface;
class ConstraintsChecker
{
    /**
     * @var EnvironmentChecker
     */
    protected $checker;
    /**
     * @var NoticeInterface
     */
    protected $notice;
    /**
     * ConstraintsChecker constructor.
     *
     *
     */
    public function __construct()
    {
        $wpConstraint = new WordPressConstraint('5.0');
        $wcConstraint = new PluginConstraint('3.9', 'woocommerce', 'WooCommerce');
        $phpConstraint = new PhpConstraint('7.4');
        $jsonConstraint = new ExtensionConstraint('json');
        $collectionFactory = new ConstraintsCollectionFactory();
        $constraintsCollection = $collectionFactory->create($wpConstraint, $wcConstraint, $phpConstraint, $jsonConstraint);
        $this->checker = new EnvironmentChecker($constraintsCollection->constraints());
        $this->notice = new AdminNotice();
    }
    /**
     * Notices of failed constraint
     * Deactivates the plugin if needed by not met constraint
     * prevents updates
     *
     * @return bool
     */
    public function handleActivation()
    {
        try {
            $this->checker->check();
            return \true;
        } catch (ConstraintFailedException $constraintFailedException) {
            $mainException = $constraintFailedException->getValidationErrors();
            $errors = [];
            foreach ($mainException as $error) {
                $errors[] = $error->getMessage();
            }
            add_action('after_setup_theme', function () use ($errors) {
                $this->showNotice($errors);
            });
            $disabler = new \Mollie\WooCommerce\Activation\PluginDisabler('mollie-payments-for-woocommerce', 'mollie_wc_plugin_init');
            $disabler->disableAll();
            return \false;
        }
    }
    public function maybeShowWarning($constraint, $warning)
    {
        $collectionFactory = new ConstraintsCollectionFactory();
        $constraintsCollection = $collectionFactory->create($constraint);
        $result = new EnvironmentChecker($constraintsCollection->constraints());
        try {
            $result->check();
            return \true;
        } catch (ConstraintFailedException $exception) {
            $mainException = $exception->getValidationErrors();
            foreach ($mainException as $error) {
                $errors[] = $error->getMessage();
            }
            $this->notice->addNotice('notice-warning is-dismissible', $warning);
            return \false;
        }
    }
    protected function showNotice(array $errors)
    {
        $message = sprintf(
            /* translators: Placeholder 1: opening tags Placeholder 2: closing tags */
            __('%1$sMollie Payments for WooCommerce is inactive:%2$s', 'mollie-payments-for-woocommerce'),
            '<p><strong>',
            '</strong></p>'
        );
        foreach ($errors as $error) {
            $message .= sprintf('<p>%s</p>', $error);
        }
        $message .= sprintf(
            /* translators: Placeholder 1: opening tags Placeholder 2: closing tags */
            __('%1$sCorrect the above errors to use Mollie Payments for WooCommerce%2$s', 'mollie-payments-for-woocommerce'),
            '<p>',
            '</p>'
        );
        $errorLevel = 'notice-error';
        $this->notice->addNotice($errorLevel, $message);
    }
}
