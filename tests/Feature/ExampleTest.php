<?php

test('public home is available', function () {
    $this->get('/')->assertOk()->assertSee('Besofton Insights');
});
