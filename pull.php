<?php

spl_autoload_register(function($class) {
    include __DIR__."/libs/{$class}.php";
});
