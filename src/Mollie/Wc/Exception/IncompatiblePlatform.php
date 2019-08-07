<?php
class Mollie_WC_Exception_IncompatiblePlatform extends Mollie_WC_Exception
{
    const API_CLIENT_NOT_INSTALLED    = 1000;
    const API_CLIENT_NOT_COMPATIBLE   = 2000;
}


// Factories (Mod A)
[
    'administrators' => function (ContainerInterface $c) {
        return new Inpsyde\EventDispatcher();
//        return [
//            'anton'
//        ];

    },
    // Other
]

// Factort (Mod B)

[
'administrators' => function (ContainerInterface $c, $prev) {

    return new My\EventDispatcher($prev);

//    return $prev;
}
]


// Exetnsions (Mod C)
[
    'administrators' => function (ContainerInterface $c, $prev) {
        $prev[] = 'emili';

        return $prev;
    }
]

$result = $c->get('administrators');
// My\EventDispatcher


//[
//    'anton',
//    'guido',
//    'emili'
//]
