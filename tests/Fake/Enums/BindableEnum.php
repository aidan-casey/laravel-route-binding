<?php

namespace AidanCasey\Laravel\RouteBinding\Tests\Fake\Enums;

enum BindableEnum: string
{
    case Option1 = 'test1';
    case Option2 = 'test2';
    case Option3 = 'test3';
}