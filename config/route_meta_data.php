<?php

return [

    '_default' => [ 
        'activity' => [ 
        'log' => false, 
        'type' => null, 
        'remove' => false, 
        'publish_in_newsfeed' => false, 
        'dft_visibility' => null
      ],
      'notification' => [
        'log' => false, 
        'type_main' => null, 
        'type_list' => null
      ]
    ],

  // GROUP 

    'group-create' => [ 
      'activity' => [ 
        'log' => true, 
        'type' => 'group.create', 
        'remove' => false, 
        'publish_in_newsfeed' => false, 
        'dft_visibility' => 'private'
      ],
      'notification' => [
        'log' => false, 
        'type_main' => null, 
        'type_list' => null
      ]
    ],

    'group-titleChange' => [ 
      'activity' => [ 
        'log' => true, 
        'type' => 'group.titleChange', 
        'remove' => false, 
        'publish_in_newsfeed' => false, 
        'dft_visibility' => 'group-members'
      ],
      'notification' => [
        'log' => true, 
        'type_main' => null, 
        'type_list' => 'group.titleChange'
      ]
    ],

    'group-close' => [ 
      'activity' => [ 
        'log' => true, 
        'type' => 'group.close', 
        'remove' => false, 
        'publish_in_newsfeed' => false, 
        'dft_visibility' => 'group-members'
      ],
      'notification' => [
        'log' => true, 
        'type_main' => null, 
        'type_list' => 'group.close'
      ]
    ],

    'group-open' => [ 
      'activity' => [ 
        'log' => true, 
        'type' => 'group.open', 
        'remove' => false, 
        'publish_in_newsfeed' => false, 
        'dft_visibility' => 'group-members'
      ],
      'notification' => [
        'log' => true, 
        'type_main' => null, 
        'type_list' => 'group.open'
      ]
    ]

];
