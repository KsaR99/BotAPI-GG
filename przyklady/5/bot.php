<?php

spl_autoload_register(function($class) {
    require __DIR__."/sciezka_do_bibliotek/{$class}.php";
});

$p = new PushConnection(123456, 'wojtek@gg.pl', 'hasło'); // autoryzacja
$p->setStatus('Mój nowy opis', PushConnection::STATUS_AWAY);
