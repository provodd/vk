<?php

namespace App\Services\Posts;

use VK\Client\VKApiClient;
use App\Services\Database\DatabaseService;

class PostPublishService
{
    private $config;
    private $vk;
    private $rand;

    public function __construct($config)
    {
        $this->config = $config;
        $this->vk = new VKApiClient();
        $this->rand = rand(50, 20000);
    }

    public function getPosts($limit = 20)
    {
        return DatabaseService::getAll('SELECT * FROM ' . PostServiceDTO::POSTS_TABLE_NAME . ' WHERE status=0 ORDER BY id DESC LIMIT ' . $limit);
    }

    public function getLastPublishedTime()
    {
        $last = DatabaseService::findOne(PostServiceDTO::POSTS_TABLE_NAME, 'status>0 ORDER BY published_in DESC');
        if (isset($last)) {
            $last_published_time = $last->published_in;
        } else {
            return time();
        }

        return ($last_published_time > time()) ? $last_published_time : time();
    }

    /**
     * @throws \Exception
     */
    public function validate($text)
    {
        for ($i = 0; $i < count(PostServiceDTO::STOP_WORDS); $i++) {
            $res = @substr_count(mb_strtolower($text), PostServiceDTO::STOP_WORDS[$i]);
            if ($res == true) {
                throw new \Exception("запрещенное слово " . PostServiceDTO::STOP_WORDS[$i]);
            }
        }
    }

    public function getPostAttachments($id)
    {
        return DatabaseService::getAll('SELECT * FROM ' . PostServiceDTO::POST_ATTACHMENTS_TABLE_NAME . ' WHERE identifier_post = ' . $id . ' AND type="photo"');
    }

    public function getParamsByAttachments($attachments)
    {
        if (!empty($attachments)) {
            $i = 0;
            foreach ($attachments as $attachment) {
                $i++;
                //$media[] = 'photo' . $attachment['owner_id'] . '_' . $attachment['identifier'];
                if ($i < 6) {
                    $curl_params['file' . $i] = new \CURLFile(dirname(__DIR__, 3) . '/App/files/posts_water/' . $attachment['new_name']);
                }
            }
        }
        return $curl_params ?? false;
    }

    public function uploadToServer($upload_link, $curl_params)
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $upload_link);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $curl_params);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($curl);
        curl_close($curl);
        return json_decode($result);
    }

    public function saveWallPhoto($curl_result)
    {
        $wall_photo = $this->vk->photos()->saveWallPhoto($this->config['post_service']['access_token'], array(
            'group_id' => abs($this->config['post_service']['group_id']),
            'server' => $curl_result->server,
            'photo' => $curl_result->photo,
            'hash' => $curl_result->hash,
        ));

        $array = array();
        foreach ($wall_photo as $photo) {
            $array[] = 'photo' . $photo['owner_id'] . '_' . $photo['id'];
        }
        return (isset($array) and !empty($array)) ? implode(',', $array) : '';
    }

    /**
     * @throws \VK\Exceptions\VKApiException
     */
    public function post($media_for_upload, $time, $post)
    {
        if (isset($media_for_upload)) {
            $response_wall = $this->vk->wall()->post($this->config['post_service']['access_token'], array(
                'owner_id' => $this->config['post_service']['group_id'],
                'form_group' => 1,
                'signed' => 0,
                'message' => $post['text'],
                'attachments' => $media_for_upload,
                'publish_date' => $time,
            ));

            $add_post = DatabaseService::load(PostServiceDTO::POSTS_TABLE_NAME, $post['id']);
            $add_post->published_in = $time;
            $add_post->new_id = $response_wall['post_id'];
            DatabaseService::store($add_post);

            $this->changePostStatus($post['id'], 1, var_export($response_wall, true));
        } else {
            $this->changePostStatus($post['id'], 4, 'media for upload is missing');
        }
    }

    public function changePostStatus($id, $status, $text)
    {
        $edit_status = DatabaseService::load(PostServiceDTO::POSTS_TABLE_NAME, $id);
        $edit_status->status = $status;
        $edit_status->updated_at = date('Y-m-d H:i:s');
        $edit_status->status_info = var_export($text, true);
        DatabaseService::store($edit_status);
    }

    public function publish($posts)
    {
        $i = 0;
        $time = $this->getLastPublishedTime();
        foreach ($posts as $post) {
            $i++;
            try {
                $this->validate($post['text']);

                $attachments = $this->getPostAttachments($post['identifier']);
                if (!isset($attachments)) {
                    throw new Exception('Нет вложений');
                }

                $params = $this->getParamsByAttachments($attachments);

                if (!isset($params)) {
                    throw new Exception('Не удалось сформировать список вложений');
                }

                $sec = 3600 * ($i * 2);
                $rand_sec = 14400 + rand(60, 6000);
                $time = $time + $rand_sec;

                $upload_link = $this->vk->photos()->getWallUploadServer($this->config['post_service']['access_token'], array(
                    'group_id' => abs($this->config['post_service']['group_id']),
                ));

                $curl_result = $this->uploadToServer($upload_link['upload_url'], $params);

                if (isset($curl_result->photo)) {
                    if (!empty($curl_result->photo) AND trim($curl_result->photo)!=='[]'){
                        $media_for_upload = $this->saveWallPhoto($curl_result);
                        $this->post($media_for_upload, $time, $post);
                    }
                }
            } catch (\Exception $ex) {
                $this->changePostStatus($post['id'], 4, $ex->getMessage());
                //echo $ex->getMessage().'<br>';
            }
            sleep(2);
        }
    }
}