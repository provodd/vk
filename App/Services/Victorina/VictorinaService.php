<?php
namespace App\Services\Victorina;
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

use App\Services\Log\LogService;
use VK\Client\VKApiClient;
use App\Services\Database\DatabaseService;
use App\Services\Victorina\ClueHelper;

class VictorinaService
{
    use ClueHelper;

    public $vk;
    public $victorina;
    public $text;
    public $id_user;
    public $id_log;
    public $peer_id;
    public $group_id;
    public $user;
    public $config;
    public $token;

    public function __construct($data, $config, $id)
    {
        $this->text = $data['object']['message']['text'];
        $this->peer_id = $data['object']['message']['peer_id'];
        $this->group_id = $data['group_id'];
        $this->id_user = $data['object']['message']['from_id'];
        $this->id_log = $id;
        $this->vk = new VKApiClient();
        $this->config = $config;
        $this->token = $config['victorina_service']['access_token'];
        $this->victorina = $this->getVictorina();
    }

    public function start()
    {
        if ($this->createVictorina()) {
            $this->changeStatus('started');
            $msg = $this->generateMessage('started');
            $this->sendMessage($msg);
        }
    }

    public function check()
    {
        switch (true) {
            //топ игроков
            case (mb_strtolower($this->text) === VictorinaDTO::GET_RATING_COMMAND AND $this->isCommand($this->text)):
                //LogService::test(['tolower' => mb_strtolower($this->text), 'command' => VictorinaDTO::GET_RATING_COMMAND, 'text' => mb_strtolower($this->text) === VictorinaDTO::GET_RATING_COMMAND]);
                $msg = $this->generateMessage('rating');
                if ($msg) {
                    $this->sendMessage($msg);
                }
                break;
            //если угадали ответ
            case isset($this->victorina) and (mb_strtolower($this->text) === mb_strtolower(trim($this->victorina->answer))):
                $this->changeStatus('succeed');
                $this->isActive(0);
                $this->changeRating($this->id_user, VictorinaDTO::NUMBER_OF_POINTS);
                $this->setWinner($this->id_user);
                $msg = $this->generateMessage('succeed') ?? '';
                $this->sendMessage($msg);
                break;
            //если просрочили с ответом
            case isset($this->victorina) and !$this->checkDate():
                $this->changeStatus('overdue');
                $this->isActive(0);
                $msg = $this->generateMessage('overdue') ?? '';
                $this->sendMessage($msg);
                break;
            //если нет текущей викторины и поступила команда на старт игры
            case (!isset($this->victorina) and mb_strtolower($this->text) === VictorinaDTO::START_COMMAND) :
                if ($this->createVictorina()) {
                    $this->changeStatus('started');
                    $msg = $this->generateMessage('started');
                    $this->sendMessage($msg);
                }
                break;
            //даем подсказку
            case (isset($this->victorina) and ($this->checkClueDate() or mb_strtolower($this->text) === VictorinaDTO::CLUE_COMMAND)):
                $msg = $this->generateMessage('clue');
                if ($msg) {
                    $this->sendMessage($msg);
                }
                break;

        }
        return true;
    }

    public function isCommand($text){
        if (mb_substr($text,0,1)==='!'){
            return true;
        }
        return false;
    }

    public function getUserPoints($id_user)
    {
        $points = DatabaseService::findOne(VictorinaDTO::VICTORINA_RATING_TABLE, 'id_user=? AND peer_id=? AND group_id=?', array($id_user, $this->peer_id, $this->group_id));
        return $points;
    }

    public function getUserRating($id_user)
    {
        $rating = DatabaseService::findOne(VictorinaDTO::VICTORINA_RATING_TABLE, 'id_user=? AND peer_id=? AND group_id=?', array($id_user, $this->peer_id, $this->group_id));
        $rating = $rating->rating;
        $r = DatabaseService::getAll('select count(*) as count from ' . VictorinaDTO::VICTORINA_RATING_TABLE . ' 
        where peer_id = ' . $this->peer_id . ' 
        and group_id = ' . $this->group_id . ' 
        and ((rating > ' . $rating . ') 
        or (rating = ' . $rating . ' and id_user < ' . $id_user . '))');

        $count = (int)$r[0]['count'];
        return $count + 1;
    }

    public function getRating()
    {
        return DatabaseService::getAll('select * from ' . VictorinaDTO::VICTORINA_RATING_TABLE . ' 
        where peer_id = ' . $this->peer_id . ' and group_id = ' . $this->group_id . ' order by rating DESC LIMIT 7');
    }

    public function setWinner($id_user)
    {
        $victorina = DatabaseService::load(VictorinaDTO::VICTORINA_TABLE, $this->victorina->id);
        $victorina->winner = $id_user;
        if (DatabaseService::store($victorina)) {
            return true;
        }
        return false;
    }

    public function checkDate()
    {
        if (isset($this->victorina)) {
            $created_at = (int)strtotime($this->victorina->date);
            $now = (int)strtotime("now");
            if (($now - $created_at) < VictorinaDTO::TIME_OF_ACTION) {
                return true;
            }
        }
        return false;
    }

    //проверяем дату последней подсказки
    public function checkClueDate(): bool
    {
        $date = $this->victorina->clue_latest_date ?? $this->victorina->date;
        if (isset($date)) {
            $created_at = (int)strtotime($date);
            $now = (int)strtotime("now");
            if (($now - $created_at) > VictorinaDTO::CLUE_INTERVAL) {
                return true;
            }
        }
        return false;
    }

    public function changeClue($msg)
    {
        $change = DatabaseService::load(VictorinaDTO::VICTORINA_TABLE, $this->victorina->id);
        $change->clue_number = $this->victorina->clue_number + 1;
        $change->clue_latest = $msg;
        $change->clue_latest_date = date('Y-m-d H:i:s');
        return DatabaseService::store($change);
    }

    //изменить рейтинг пользователя
    public function changeRating($id_user, $points)
    {
        $response = $this->vk->users()->get($this->token, array(
            'user_ids' => array($id_user),
            'fields' => array('city, home_town, universities', 'bdate')
        ));

        $this->user = $response;

        $rating = DatabaseService::findOne(VictorinaDTO::VICTORINA_RATING_TABLE, 'id_user=? AND peer_id=? AND group_id =?', array($id_user, $this->peer_id, $this->group_id));

        if (isset($rating)) {
            $add = DatabaseService::load(VictorinaDTO::VICTORINA_RATING_TABLE, $rating->id);
            $rate = $rating->rating + $points;
        } else {
            $add = DatabaseService::xdispense(VictorinaDTO::VICTORINA_RATING_TABLE);
            $rate = $points;
        }
        $add->id_user = $id_user;
        $add->peer_id = $this->peer_id;
        $add->group_id = $this->group_id;
        $add->first_name = $response[0]['first_name'] ?? '';
        $add->last_name = $response[0]['last_name'] ?? '';
        $add->birthdate = $response[0]['bdate'] ?? '';
        //$add->payload = var_export($response[0], true);
        $add->created_at = date('Y-m-d H:i:s');
        $add->rating = $rate;
        return DatabaseService::store($add);
    }

    public function getVictorina()
    {
        return DatabaseService::findOne(VictorinaDTO::VICTORINA_TABLE, 'active=? AND peer_id=? AND group_id=?', array('1', $this->peer_id, $this->group_id));
    }

    //создаем виктарину, если нет текущей
    public function createVictorina()
    {
        if (!isset($this->victorina)) {
            $question = $this->getQuestion();
            $add = DatabaseService::xdispense(VictorinaDTO::VICTORINA_TABLE);
            $add->status = 'started';
            $add->winner = NULL;
            $add->id_question = $question->id;
            $add->question = $question->question;
            $add->answer = $question->answer;
            $add->clue_number = 0;
            $add->clue_latest = NULL;
            $add->clue_latest_date = NULL;
            $add->active = 1;
            $add->peer_id = $this->peer_id;
            $add->group_id = $this->group_id;
            $add->created_by = $this->id_user;
            $add->id_log = $this->id_log;
            $add->date = date('Y-m-d H:i:s');
            $add->timestamp = date('Y-m-d H:i:s');
            $id = DatabaseService::store($add);
            $this->victorina = DatabaseService::findOne(VictorinaDTO::VICTORINA_TABLE, 'id=?', array($id));
            return (isset($this->victorina));
        }
        return false;
    }

    //смена статуса викторины
    public function changeStatus($status): bool
    {
        $change = DatabaseService::load(VictorinaDTO::VICTORINA_TABLE, $this->victorina->id);
        $change->status = $status;
        return DatabaseService::store($change);
    }

    public function isActive($act): bool
    {
        $change = DatabaseService::load(VictorinaDTO::VICTORINA_TABLE, $this->victorina->id);
        $change->active = $act;
        if (DatabaseService::store($change)) {
            return true;
        }
        return false;
    }

    //получаем неиспользованный вопрос
    public function getQuestion()
    {
        $q = DatabaseService::findOne(VictorinaDTO::QUESTIONS_TABLE, 'active=? AND used_number=? ORDER BY RAND() LIMIT 1', array('1', '0'));
        $load = DatabaseService::load(VictorinaDTO::QUESTIONS_TABLE, $q->id);
        $load->used_number = $q->used_number + 1;
        DatabaseService::store($load);

        return $q;
    }

    public function generateMessage($type)
    {
        $answer = isset($this->victorina->answer) ? trim($this->victorina->answer) : null;
        $strlen = isset($this->victorina->answer) ? mb_strlen(trim($answer)) : null;
        $question = $this->victorina->question ?? null;

        switch ($type) {
            case 'started':
                $prefix = VictorinaDTO::START_MESSAGE_PREFIXES[rand(0, 4)];
                $msg = "{$prefix} {$question} \n В ответе {$strlen} букв.";
                break;
            case 'succeed':
                $user = '[id' . $this->user[0]['id'] . '|' . $this->user[0]['first_name'] . ']';
                $question_cost = VictorinaDTO::NUMBER_OF_POINTS;
                $rating = $this->getUserRating($this->id_user);
                $points = $this->getUserPoints($this->id_user);
                $prefix = VictorinaDTO::SUCCESS_MESSAGE_PREFIXES[rand(0, 2)];
                $msg = $user . ', ' . $prefix . ' ' . trim($answer) . '. Начислено ' . $question_cost . ' балла(ов) за правильный ответ. Ты на ' . $rating . ' месте, счёт: ' . $points->rating . ' баллов.';
                break;
            case 'overdue':
                $msg = 'Викторина осталась без ответа. Правильный ответ: ' . $answer . '';
                break;
            case 'clue':
                $clue = $this->getClue($this->victorina);
                if ($clue) {
                    $this->changeClue($clue['clue']);
                    $msg = 'Подсказка: ' . $clue['hidden_answer'];
                }
                break;
            case 'rating':
                $r = $this->getRating();
                $msg = '';
                if (isset($r) and !empty($r)) {
                    $i = 0;
                    foreach ($r as $item) {
                        $i++;
                        $msg .= $i . '. [id' . $item['id_user'] . '|' . $item['first_name'] . '] - ' . $item['rating'] . " баллов.\n";
                    }
                }
                break;
            default:
                $msg = '';
                break;
        }

        return $msg ?? false;
    }

    public function sendMessage($msg)
    {
        $peer_id = $this->victorina->peer_id ?? $this->peer_id;
        return $this->vk->messages()->send($this->token, array(
            'peer_id' => $peer_id,
            'random_id' => rand(0, 9) . 7 . rand(1, 999),
            "chat_id" => $peer_id * 1 - 2000000000,
            "message" => $msg,
            'group_id' => $this->victorina->group_id ?? $this->group_id,
            //'fields' => array('name'),
        ));
    }
}


?>
