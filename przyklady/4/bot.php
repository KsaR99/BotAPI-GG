<?php

spl_autoload_register(function($class) {
    require __DIR__."/sciezka_do_bibliotek/{$class}.php";
});

$m = new MessageBuilder;

$m
  ->addText('Zapraszam na http://boty.gg.pl/')
  ->setRecipients([123, 456]); // lista odbiorców

$p = new PushConnection(123456, 'wojtek@gg.pl', 'hasło'); // autoryzacja
$p->push($m); // wysłanie wiadomości do odbiorców
