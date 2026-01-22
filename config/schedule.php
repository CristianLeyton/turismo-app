<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Scheduler Timezone
    |--------------------------------------------------------------------------
    |
    | All scheduler times are based on this timezone. You can change it to
    | whatever timezone you wish.
    |
    */

    'timezone' => 'America/Argentina/Salta',

    /*
    |--------------------------------------------------------------------------
    | Schedule Listeners
    |--------------------------------------------------------------------------
    |
    | Here you may define a list of commands that will be automatically run
    | by the scheduler. You may add any number of commands to the array.
    |
    */

    'list' => [
        // Limpiar reservas expiradas cada 5 minutos
        '*/5 * * * *' => [
            'command' => 'app:cleanup-expired-reservations',
            'description' => 'Clean up expired seat reservations',
        ],
    ],

];
