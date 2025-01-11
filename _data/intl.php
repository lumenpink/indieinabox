<?php
$originaldaysofweek = [
    "Sunday",
    "Monday",
    "Tuesday",
    "Wednesday",
    "Thursday",
    "Friday",
    "Saturday"
];
$originalmonths = [
    "January",
    "February",
    "March",
    "April",
    "May",
    "June",
    "July",
    "August",
    "September",
    "October",
    "November",
    "December"
];
$intl = [
    'pt-br' => [
        'localizeddate' => [
            'date' => 'd \d\e F \de\ Y',
            'time' => 'H:iP',
            'full' => 'l, d \d\e F \d\e Y \à\s H:i e',
            'shortdate' => 'd/m/Y',
            'shorttime' => 'H:i',
            'shortfull' => 'd/m/Y H:i',
            'daysofweek' => ["Domingo", "Segunda-feira", "Terça-feira", "Quarta-feira", "Quinta-feira", "Sexta-feira", "Sábado"],
            'months' => ["Janeiro", "Fevereiro", "Março", "Abril", "Maio", "Junho", "Julho", "Agosto", "Setembro", "Outubro", "Novembro", "Dezembro"],
        ],
    ],
    'en' => [
        'localizeddate' => [
            'date' => 'F d, Y',
            'time' => 'h:i A',
            'full' => 'l, F d, Y \a\t h:i A',
            'shortdate' => 'm/d/Y',
            'shorttime' => 'h:i A',
            'shortfull' => 'm/d/Y h:i A',
            'daysofweek' => ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"],
            'months' => ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"],
        ],
    ],
    'es' => [
        'localizeddate' => [
            'date' => 'd \d\e F \d\e Y',
            'time' => 'H:iP',
            'full' => 'l, d \d\e F \d\e Y \à\s H:iP',
            'shortdate' => 'd/m/Y',
            'shorttime' => 'H:i',
            'shortfull' => 'd/m/Y H:i',
            'daysofweek' => ["Domingo", "Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"],
            'months' => ["Enero", "Febrero", "Marzo", "Abril", "Mayo", "Junio", "Julio", "Agosto", "Septiembre", "Octubre", "Noviembre", "Diciembre"],
        ],
    ],
];
