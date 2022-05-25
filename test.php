<?php

class Test
{
    public function execute(string $name)
    {
        //
    }
}

call_user_func_array([new Test, 'execute'], [
    'name' => 'Bob',
    'date' => 'jan 20th',
]);