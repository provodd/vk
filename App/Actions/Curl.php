<?php

namespace App\Actions;

class Curl
{

    public $url;
    public $data;
    public $timeout;

    public function __construct($url, $data, $timeout)
    {
        $this->url = $url;
        $this->data = $data;
        $this->timeout = $timeout;
    }

    public function exec()
    {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->url);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($this->data));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, $this->timeout);
        $result = curl_exec($curl);

        if (curl_errno($curl)) {
            $error_msg = curl_error($curl);
        }
        curl_close($curl);

        return $error_msg ?? $result;
    }
}