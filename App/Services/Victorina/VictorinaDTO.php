<?php

namespace App\Services\Victorina;

class VictorinaDTO
{

    const VICTORINA_TABLE = 'victorina';
    const QUESTIONS_TABLE = 'victorina_questions';
    const VICTORINA_RATING_TABLE = 'victorina_rating';

    const START_COMMAND = '!вик';
    const CLUE_COMMAND = '!подсказка';
    const GET_RATING_COMMAND = '!топ';

    //количество очков за выигрыш
    const NUMBER_OF_POINTS = 10;

    //время действия викторины в секундах
    const TIME_OF_ACTION = 1400;

    //интервал подсказок в секундах
    const CLUE_INTERVAL = 120;

    //Максимальное количество созданных викторин одним пользователем за день
    const MAX_COUNT = 15;

    const START_MESSAGE_PREFIXES = [
        'Простейший вопрос. ',
        'Попробуйте угадать! ',
        'Задаю вопрос. ',
        'Самый легкий вопрос. ',
        'Вопрос на засыпку. ',
    ];

    const SUCCESS_MESSAGE_PREFIXES = [
        'безусловно это ',
        'это действительно ',
        'конечно это ',
    ];
}