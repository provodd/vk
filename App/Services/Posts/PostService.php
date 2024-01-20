<?php

namespace App\Services\Posts;

use VK\Client\VKApiClient;
use App\Actions\Watermark;
use App\Services\Database\DatabaseService;

class PostService
{
    private $config;
    private $vk;
    private $rand;

    public function __construct($config)
    {
        $this->config = $config;
        $this->vk = new VKApiClient();
        $this->rand = rand(50, 3000);
    }

    public function getPosts($limit = 10)
    {
        $data = array();
        foreach($this->config['post_service']['source_groups_id'] as $item){
            $response = $this->vk->wall()->get($this->config['post_service']['access_token'], array(
                'owner_id' => array($item),
                'filter' => array('owner'),
                'count' => array($limit),
                "offset" => $this->rand,
                //'group_id' => array($public_id),
                //'fields' => array('name'),
            ));

            $data = array_merge($data, $response['items']);
        }

        if (isset($data)) {
            $this->store($data);
        }
    }

    public function checkByStopWords($text): bool
    {
        return !in_array(mb_strtolower($text), PostServiceDTO::STOP_WORDS);
    }

    public function store($data)
    {
        foreach ($data as $item) {

            $isExist = DatabaseService::findOne(PostServiceDTO::POSTS_TABLE_NAME, 'identifier=?', array($item['id']));

            if (is_null($isExist) AND $this->checkByStopWords($item['text'])) {
                $post = DatabaseService::xdispense(PostServiceDTO::POSTS_TABLE_NAME);
                $post->identifier = $item['id'];
                $post->date = $item['date'];
                $post->marked_as_ads = $item['marked_as_ads'];
                $post->offset = $this->rand;
                $post->post_type = $item['post_type'];
                $post->text = $item['text'];
                $post->likes = $item['likes']['count'];
                $post->reposts = $item['reposts']['count'];
                $post->status = 0;
                $post->published_in = NULL;
                $post->hash = $item['hash'];
                $post->payload = json_encode($data);
                $id = DatabaseService::store($post);

                if (isset($id)) {
                    if (isset($item['attachments'])) {

                        foreach ($item['attachments'] as $val) {

                            if ($val['type'] == 'photo') {

                                $attachment = DatabaseService::xdispense(PostServiceDTO::POST_ATTACHMENTS_TABLE_NAME);
                                $attachment->id_post = $id;
                                $attachment->identifier_post = $item['id'];
                                $attachment->album_id = $val['photo']['album_id'];
                                $attachment->owner_id = $val['photo']['owner_id'];
                                $attachment->type = $val['type'];
                                $attachment->date = $val['photo']['date'];
                                $attachment->identifier = $val['photo']['id'];
                                $attachment->access_key = $val['photo']['access_key'];

                                //TODO подумать, что делать с этим безобразием
                                $w = array_search('w', array_column($val['photo']['sizes'], 'type'));
                                $z = array_search('z', array_column($val['photo']['sizes'], 'type'));
                                $y = array_search('y', array_column($val['photo']['sizes'], 'type'));
                                $x = array_search('x', array_column($val['photo']['sizes'], 'type'));

                                switch (true) {
                                    case !empty($w):
                                        $image = $val['photo']['sizes'][$w]['url'];
                                        $sizes_index = $w;
                                        break;
                                    case !empty($z):
                                        $image = $val['photo']['sizes'][$z]['url'];
                                        $sizes_index = $z;
                                        break;
                                    case !empty($y):
                                        $image = $val['photo']['sizes'][$y]['url'];
                                        $sizes_index = $y;
                                        break;
                                    case !empty($x):
                                        $image = $val['photo']['sizes'][$x]['url'];
                                        $sizes_index = $x;
                                        break;
                                    default:
                                        break;
                                }
                                if (isset($sizes_index)) {

                                    $image_name = time() . '_' . rand(1, 10) . '_' . crc32($val['photo']['id']) . '.jpg';
                                    $url = $val['photo']['sizes'][$sizes_index]['url'];
                                    $path = dirname(__FILE__, 3) . '/files/posts/' . $image_name;

                                    $water = new Watermark();
                                    $water->check_dirs();
                                    file_put_contents($path, file_get_contents($url));
                                    $watermark = $water->make_water($image_name, true);

                                    $attachment->width = $val['photo']['sizes'][$sizes_index]['width'];
                                    $attachment->height = $val['photo']['sizes'][$sizes_index]['height'];
                                    $attachment->url = $val['photo']['sizes'][$sizes_index]['url'];
                                    $attachment->photo_type = $val['photo']['sizes'][$sizes_index]['type'];
                                    $attachment->info = json_encode($watermark);
                                    $attachment->water = json_encode($water);
                                    $attachment->new_name = $image_name;
                                }
                                DatabaseService::store($attachment);
                            }
                        }
                    }
                }
                sleep(2);
            }
        }
    }
}