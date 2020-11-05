<?php


namespace RequiredVersionDisabler;


use InvalidArgumentException;
use RequiredVersionDisabler\Constraints\ConstraintsCollection;


class ConstraintsCollectionFactory
{

    const WORDPRESS_CONSTRAINT = 'WordPressConstraint';
    const WOOCOMMERCE_CONSTRAINT = 'WooCommerceConstraint';
    const PHP_CONSTRAINT = 'PhpConstraint';
    protected $availableConstraints;

    /**
     * ConstraintsCollectionFactory constructor.
     */
    public function __construct()
    {
        $this->availableConstraints = [
            self::WORDPRESS_CONSTRAINT,
            self::WOOCOMMERCE_CONSTRAINT,
            self::PHP_CONSTRAINT
        ];
    }

    /**
     * Creates a Constraints Collection
     * @param $constraintsArray
     * @param $pluginName
     *
     * @return ConstraintsCollection
     */
    public function create($constraintsArray, $pluginName)
    {
        $constraints = [];
        foreach ($constraintsArray as $constraintClass => $constraintVersion) {
            if (!in_array($constraintClass, $this->availableConstraints)) {
                throw new InvalidArgumentException(
                    'Contraint provided is not withing the available ones'
                );
            }
            $className = '\\RequiredVersionDisabler\\Constraints\\' . $constraintClass;
            $constraints[] = new $className($constraintVersion, $pluginName);
        }


        return new ConstraintsCollection(...$constraints);
    }
}
