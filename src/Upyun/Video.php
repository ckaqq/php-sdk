<?php
namespace Upyun;

class Video {
    /**
     * @var BucketConfig
     */
    protected $config;

    public function __construct(BucketConfig $bucketConfig) {
        $this->setConfig($bucketConfig);
    }

    public function setConfig(BucketConfig $bucketConfig) {
        $this->config = $bucketConfig;
    }

    public function pretreat($source, $notifyUrl, $tasks) {
        $postParams['tasks'] = $this->config->base64Json($tasks);
        $postParams['source'] = $source;
        $postParams['notify_url'] = $notifyUrl;
        $postParams['bucket_name'] = $this->config->bucketName;
        $sign = $this->config->getSignature($postParams, BucketConfig::SIGN_VIDEO);
        $response = Request::post(
            sprintf('http://%s/%s/', BucketConfig::ED_VIDEO, 'pretreatment'),
            $this->config->getSignHeader($sign),
            $postParams
        );

        if($response->status_code !== 200) {
            $body = json_decode($response->body, true);
            throw new \Exception(sprintf('%s, with x-request-id=%s', $body['msg'], $body['id']), $body['code']);
        }


        $taskIds = json_decode($response->body, true);
        return $taskIds;
    }


    public function status($taskIds) {
        $limit = 20;
        if(count($taskIds) <= $limit) {
            $taskIds = implode(',', $taskIds);
        } else {
            throw new \Exception('can not query more than ' . $limit . ' tasks at one time!');
        }

        $query['task_ids'] = $taskIds;
        $query['bucket_name'] = $this->config->bucketName;
        $sign = $this->config->getSignature($query, BucketConfig::SIGN_VIDEO);

        $response = Request::get(
            sprintf('http://%s/%s/', BucketConfig::ED_VIDEO, 'status'),
            $this->config->getSignHeader($sign),
            $query
        );

        if($response->status_code !== 200) {
            $body = json_decode($response->body, true);
            throw new \Exception(sprintf('%s, with x-request-id=%s', $body['msg'], $body['id']), $body['code']);
        }

        $status = json_decode($response->body, true);
        return $status;
    }

    public function callbackSignVerify() {
        $callbackKeys = array(
            'bucket_name',
            'status_code',
            'path',
            'description',
            'task_id',
            'info',
            'signature',
        );
        $callbackParams = array();
        foreach($callbackKeys as $key) {
            if(isset($_POST[$key])) {
               $callbackParams[$key] = Util::trim($_POST[$key]);
            }
        }

        if(isset($callbackParams['signature'])) {
            $sign = $callbackParams['signature'];
            unset($callbackParams['signature']);
            return $sign === $this->config->getSignature($callbackParams, BucketConfig::SIGN_VIDEO);
        }

        if(isset($data['non_signature'])) {
            $sign = $callbackParams['non_signature'];
            unset($callbackParams['non_signature']);
            return $sign === $this->config->getSignature($callbackParams, BucketConfig::SIGN_VIDEO_NO_OPERATOR);
        }
        return false;
    }
}