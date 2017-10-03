<?php

spl_autoload_register(function($class) {
    include __DIR__."/../../../libs/{$class}.php";
});

$p = new PushConnection(123456, 'wojtek@gg.pl', 'hasło'); // autoryzacja
$p->setStatus('Mój nowy opis', PushConnection::STATUS_AWAY);
