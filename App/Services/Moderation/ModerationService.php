<?php

namespace App\Services\Moderation;

use App\Services\Log\LogService;
use App\Services\Queue\QueueService;
use VK\Client\VKApiClient;

class ModerationService
{
    private $config;
    private $vk;

    public function __construct($config)
    {
        $this->config = $config;
        $this->vk = new VKApiClient();
    }

    public function start()
    {
        //удаляем старые очереди, если есть
        $queueService = new QueueService();
        $queueService->checkingOldQueues();

        $queues = $queueService->getQueues();

        foreach ($queues as $queue) {
            try {
                if (empty($queue['text']) OR mb_strlen($queue['text'])===0){
                    throw new \Exception('Пустой текст');
                }

                //на всякий случай, проверяем что автор поста - человек, а не группа
                if (!is_null($queue['user']) and mb_stripos($queue['user'], '-') === false) {
                    $response = $this->getUserDetails(array($queue['user']));
                    if (!$response) {
                        throw new \Exception('Не удалось получить данные о пользователе');
                    }

                    $this->checkUserWithFilters($response, $queue);
                    $signed = $this->checkSigned($queue);
                    $this->checkPostCount($queue['user']);

                    $response_wall = $this->vk->wall()->post($this->config['moderation_service']['access_token'], array(
                        'owner_id' => $queue['id_owner'],
                        'form_group' => 1,
                        'signed' => $signed ?? 1,
                        'post_id' => $queue['id_post'],
                    ));

                    if (isset($response_wall)) {
                        $queueService->complete($queue, var_export($response_wall, true), 'success');
                    }

                } else {
                    throw new \Exception('Автор поста - группа');
                }

            } catch (\Exception $ex) {
                $queueService->complete($queue, $ex->getMessage());
            }
        }
    }

    /**
     * @throws \Exception
     */
    public function checkPostCount($id_user): bool
    {
        $count = LogService::getLogsCountByUser($id_user);
        if ($count > 7) {
            throw new \Exception('Слишком много постов от этого пользователя');
        }
        return true;
    }

    public function checkSigned($queue): int
    {
        $text = mb_strtolower($queue['text']);
        return (in_array($text, ModerationDTO::UNSIGNED_WORDS)) ? 0 : 1;
    }

    /**
     * @throws \Exception
     */
    public function checkUserWithFilters($response, $queue): bool
    {

        if (isset($response[0]['home_town'])) {
            if (!empty($response[0]['home_town'])) {
                $town = mb_strtolower($response[0]['home_town']);
                $checked_home_town = in_array($town, ModerationDTO::getAvailableCities());
            }
        }
        if (isset($response[0]['city'])) {
            if (!empty($response[0]['city'])) {
                $city = mb_strtolower($response[0]['city']['title']);
                $checked_city = in_array($city, ModerationDTO::getAvailableCities());
            }
        }

        //если город или родной город существуют в списке валидных городов
        if ((isset($checked_city) and ($checked_city === true)) or (isset($checked_home_town) and $checked_home_town === true)) {

        } else {
            throw new \Exception('Нет данных о городе либо неподходящий город');
        }

        for ($i = 0; $i < count(ModerationDTO::getStopWords()); $i++) {
            $res = @substr_count($queue['text'], ModerationDTO::getStopWords()[$i]);
            if ($res == true) {
                throw new \Exception("запрещенное слово " . ModerationDTO::getStopWords()[$i]);
            }
        }

        return true;
    }

    public function getUserDetails(array $user_ids)
    {
        return $this->vk->users()->get($this->config['moderation_service']['access_token'], array(
            'user_ids' => $user_ids,
            'fields' => array('city, home_town, universities'),
        ));
    }
}
