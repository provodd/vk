<?php
namespace App\Actions;

class Watermark
{
    public $water_logo_lg;
    public $water_logo_md;
    public $path;
    public $path_water;
    public $dir;

    public function __construct()
    {
        $this->dir = dirname(__DIR__);
        $this->path = $this->dir . '/files/posts/';
        $this->path_water = $this->dir . '/files/posts_water/';
        $this->water_logo_lg = $this->dir . '/files/img/post_logo_lg.png';
        $this->water_logo_md = $this->dir . '/files/img/post_logo_md.png';
    }

    public function check_dirs()
    {
        if (!file_exists($this->path)) {
            mkdir($this->path, 0777, true);
        }
        if (!file_exists($this->path_water)) {
            mkdir($this->path_water, 0777, true);
        }
    }

    public function make_water($image_name, $load = false)
    {
        try {
            $main_img_path = $this->path . $image_name;
            $water_img_path = $this->path_water . $image_name;

            $main_img = getimagesize($main_img_path);

            $water_img = $main_img[0] > 500 ? $this->water_logo_md : $this->water_logo_lg;

            if (!$main_img) {
                throw new Exception('Нет изображения');
            }

            $img = $this->createImage($main_img['mime'], $main_img_path);

            if (file_exists($water_img)) {
                $water = imagecreatefrompng($water_img);
            } else {
                throw new Exception('Не найден водяной знак');
            }

            $res_width = $main_img[0];
            $res_height = $main_img[1];

            $water_width = imagesx($water);
            $water_height = imagesy($water);

            //создали как бы пустое изображение
            $res_img = imagecreatetruecolor($res_width, $res_height);

            imagecopyresampled($res_img, $img, 0, 0, 0, 0, $res_width, $res_height, $main_img[0], $main_img[1]);
            imagecopy($res_img, $water, $res_width - $water_width, $res_height - $water_height, 0, 0, $water_width, $water_height);

            if (!$load) {
                header("Content-Type:image/jpeg");
                return imagejpeg($res_img, NULL, 100);
            }
            return imagejpeg($res_img, $water_img_path, 100);

        } catch (Exception $ex) {
            return $ex->getMessage();
        }
    }

    public function createImage($mime, $main_img_path)
    {
        switch ($mime) {
            case 'image/jpg':
            case 'image/jpeg':
                return imagecreatefromjpeg($main_img_path);
            case 'image/png':
            case 'image/x-png':
                return imagecreatefrompng($main_img_path);
            case 'image/gif':
                return imagecreatefromgif($main_img_path);
            default:
                throw new Exception('Недопустимый тип изображения');
        }
    }
}


?>
