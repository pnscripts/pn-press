<?php

// The validation messages the public site can show (contact form and plugins). Admin forms use Filament's own translations.
return [
    'required' => 'Полето „:attribute“ е задължително.',
    'email' => 'Полето „:attribute“ трябва да е валиден имейл адрес.',
    'string' => 'Полето „:attribute“ трябва да е текст.',
    'numeric' => 'Полето „:attribute“ трябва да е число.',
    'integer' => 'Полето „:attribute“ трябва да е цяло число.',
    'url' => 'Полето „:attribute“ трябва да е валиден адрес.',
    'unique' => 'Стойността в „:attribute“ вече е заета.',
    'confirmed' => 'Потвърждението на „:attribute“ не съвпада.',
    'accepted' => 'Полето „:attribute“ трябва да бъде прието.',
    'in' => 'Избраната стойност за „:attribute“ е невалидна.',
    'min' => [
        'numeric' => 'Полето „:attribute“ трябва да е поне :min.',
        'string' => 'Полето „:attribute“ трябва да е поне :min знака.',
        'array' => 'Полето „:attribute“ трябва да има поне :min елемента.',
        'file' => 'Файлът в „:attribute“ трябва да е поне :min KB.',
    ],
    'max' => [
        'numeric' => 'Полето „:attribute“ не може да е повече от :max.',
        'string' => 'Полето „:attribute“ не може да е повече от :max знака.',
        'array' => 'Полето „:attribute“ не може да има повече от :max елемента.',
        'file' => 'Файлът в „:attribute“ не може да е повече от :max KB.',
    ],
    'attributes' => [
        'name' => 'Име',
        'email' => 'Имейл',
        'message' => 'Съобщение',
    ],
];
