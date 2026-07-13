<?php
function csrf_field(): string
{
    return \Matrimony\Http\Csrf::hiddenField();
}

function csrf_meta(): string
{
    $token = \Matrimony\Http\Csrf::token();
    return '<meta name="csrf-token" content="' . $token . '">';
}

function csrf_token(): string
{
    return \Matrimony\Http\Csrf::token();
}
