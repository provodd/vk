<?php

namespace App\Services\Victorina;

use App\Services\Posts\PostServiceDTO;

trait ClueHelper
{

    public function mb_substr_replace($original, $replacement, $position, $length)
    {
        $startString = mb_substr($original, 0, $position, 'UTF-8');
        $endString = mb_substr($original, $position + $length, mb_strlen($original), 'UTF-8');
        $out = $startString . $replacement . $endString;
        return $out;
    }

    //берем рандомно любую букву из неразгаданных и возвращаем ее вместе с позицией
    public function getClue($victorina)
    {
        //preg_match_all('/[a-я]/iu',$answer,$matches,PREG_PATTERN_ORDER);

        //из-за кириллицы, необходимо разбить строку на массив букв
        $split_answer = preg_split('//u', trim($victorina->answer), 30, PREG_SPLIT_NO_EMPTY);

        //clue - массив с номерами позиций букв которые уже были показаны в подсказке
        $clue = !is_null($victorina->clue_latest) ? explode(',', $victorina->clue_latest) : [];
        $len = count($split_answer);
        $len_clue = count($clue) === 0 ? 1 : count($clue);
        $stop = (($len / $len_clue < 1.21) or (int)$victorina->clue_number > 7);

        if ($stop) {
            return false;
        }

        //получаем рандомную позицию буквы для подсказки, исключая те буквы, которые уже были показаны
        if (count($clue) !== 0 and !empty($clue)) {
            do {
                $rand = rand(0, ($len * 1 - 1));
            } while (in_array($rand, $clue));
        } else {
            $rand = rand(0, ($len * 1 - 1));
        }

        array_push($clue, $rand);

        foreach ($split_answer as $key => $item) {
            if (!in_array($key, $clue)) {
                $split_answer[$key] = '*';
            }
        }

        return ['clue' => implode(',', $clue), 'hidden_answer' => implode('', $split_answer)];
    }
}