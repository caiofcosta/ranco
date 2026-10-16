<?php

return [

    /*
    | E-mails dos administradores do sistema, que acessam o Painel (/painel).
    | Separados por vírgula em RANCO_ADMINISTRADORES.
    */
    'administradores' => array_values(array_filter(array_map(
        static fn (string $email): string => mb_strtolower(trim($email)),
        explode(',', (string) env('RANCO_ADMINISTRADORES', '')),
    ))),

];
