<?php

class SimpleJsonRequest
{
    public static function get(string $url, array $parameters = null)
    {
        return json_decode(self::makeRequest('GET', $url, $parameters));
    }

    private static function makeRequest(string $method, string $url, array $parameters = null, array $data = null)
    {
        $uniqueUrlHash = null;
        $opts          = [
            'http' => [
                'method'  => $method,
                'header'  => 'Content-type: application/json',
                'content' => $data ? json_encode($data) : null
            ]
        ];

        $url .= ($parameters ? '?' . http_build_query($parameters) : '');

        //Check and return if the result is available in cache
        if ($method === 'GET') { // Do not cache request other than GET as those are always contain dynamic response and high chance that the response is not the same as each time
            $uniqueUrlHash  = self::uniqueHashForGetUrlAndParameters($url);
            $cachedResponse = MyRedis::get($uniqueUrlHash);

            if (!empty($cachedResponse)) {
                return $cachedResponse;
            }
        }

        $freshResponse = file_get_contents($url, false, stream_context_create($opts));

        //Caching the result
        if ($method === 'GET') {
            MyRedis::setValueWithTtl($uniqueUrlHash, $freshResponse, 10); // Cache will live on the server for 10 seconds
        }

        return $freshResponse;
    }

    private static function uniqueHashForGetUrlAndParameters(string $url): string
    {
        return hash('md5', $url);
    }

    public static function post(string $url, array $parameters = null, array $data)
    {
        return json_decode(self::makeRequest('POST', $url, $parameters, $data));
    }

    public static function put(string $url, array $parameters = null, array $data)
    {
        return json_decode(self::makeRequest('PUT', $url, $parameters, $data));
    }

    public static function patch(string $url, array $parameters = null, array $data)
    {
        return json_decode(self::makeRequest('PATCH', $url, $parameters, $data));
    }

    public static function delete(string $url, array $parameters = null, array $data = null)
    {
        return json_decode(self::makeRequest('DELETE', $url, $parameters, $data));
    }
}


class MyRedis
{
    public static function get($key)
    {
        $redisObj = self::openRedisConnection();

        if (!$redisObj->exists($key)) {
            return null;
        }

        $result = $redisObj->get($key);
        $redisObj->close();
        return $result;
    }

    private static function openRedisConnection(string $hostName = '127.0.0.1', string $port = '6379')
    {
        $redisObj = new Redis();
        $redisObj->connect($hostName, $port);
        return $redisObj;
    }

    public static function setValueWithTtl($key, $value, $ttl)
    {

        try {
            $redisObj = self::openRedisConnection();
            $redisObj->setex($key, $ttl, $value);
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    }
}

//Invoking the API Call
print_r(SimpleJsonRequest::get("https://jsonplaceholder.typicode.com/todos/1"));