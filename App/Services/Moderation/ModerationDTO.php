<?php

namespace App\Services\Moderation;

class ModerationDTO
{
    const AVAILABLE_CITIES = array('ЕКБ', 'Ебург', 'Екатеринбург', 'Каменск-Уральский', 'Каменск Уральский', 'Нижний Тагил', 'Первоуральск', 'Серов', 'Новоуральск', 'Верхняя Пышма', 'Асбест',
        'Ревда', 'Берёзовский', 'Полевский', 'краснотурьинск', 'Верхняя Салда', 'Качканар', 'Красноуфимск', 'Алапаевск');

    const STOP_WORDS_ONE = array('сосать', 'сосет', 'в рот', 'бля', 'пизда', 'пиздец', 'мп', 'мат.плата', 'вознагрождение', 'секс', 'трах', 'раскрепащ',
        'любовницу', 'встреч', 'мж', 'интим', 'приглашаю', 'массаж',
        'откровенные', 'минет', 'миньет', 'плачу', 'встречусь', 'язычком', 'куни', 'хуй',
        'член', 'мбр', 'на вечер', 'изделие', 'ищу нижнюю', 'telegram', 'телега', 'телеграм');

    const STOP_WORDS_TWO = array('подпиской', 'подписка', 'продажи', 'выгода', 'выгодно', 'без обязательств', 'эксклюзив', 'условие', 'выигрыш', 'розыгрыш', 'конкурс',
        'бесплатн', 'консультация', 'позвони', 'подпишись',
        'лекарство', 'похудение', 'скидки', 'новинка', '100%', 'коллектор',
        'кредит', 'вклад', 'платёж', 'ставки', 'рефинансирование', 'долги', '$$$', '$');

    const UNSIGNED_WORDS = array('аноним', 'анонимно', 'анон', 'анонимна');

    public static function getAvailableCities()
    {
        return array_map('mb_strtolower', array_map('trim', self::AVAILABLE_CITIES));
    }

    public static function getStopWords()
    {
        $sex_filter = array_map('mb_strtolower', array_map('trim', self::STOP_WORDS_ONE));
        $main_filter = array_map('mb_strtolower', array_map('trim', self::STOP_WORDS_TWO));
        return array_merge($sex_filter, $main_filter);
    }

}


