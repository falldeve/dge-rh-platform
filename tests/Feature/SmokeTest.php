<?php

it('redirige la racine vers la connexion', function () {
    $this->get('/')->assertRedirect('/login');
});
