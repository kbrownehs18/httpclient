<?php
namespace Http;

class HttpClient
{
    // cookie
    private $cookies = [];

    // response header
    private $headers = [];

    // request header
    private $requestHeaders = [];

    // return data
    private $data;

    // http status code
    private $httpCode;

    // response content-type
    private $contentType = '';

    /**
     * Get请求
     * @param $url 请求地址
     * @param $headers 请求头 dict or list
     */
    public function get($url, $headers = [])
    {
        return $this->request($url, 'GET', [], $headers);
    }

    /**
     * Post请求
     * @param $url 请求地址
     * @param $params post请求参数 string or dict
     * @param $headers 请求头 dict or list 
     * @param $isJson 是否为 Content-Type: application/json
     */
    public function post($url, $params = [], $headers = [])
    {
        return $this->request($url, 'POST', $params, $headers);
    }

    /**
     * 请求url
     * @param $url 请求地址
     * @param $method 请求方法 暂时只支持 GET POST PUT DELETE
     * @param $params post请求参数 string or dict
     * @param $headers 请求头 dict or list 
     * @param $files 上传文件
     * @param $options curl 额外参数 
     * $options ['timeout', 'ca']
     */
    public function request($url, $method = 'GET', $params = [], $headers = [], $files = [], $options = [])
    {
        $ch = curl_init();
        if (isset($options['timeout'])) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $options['timeout']);
            unset($options['timeout']);
        }
        if (isset($options['ca'])) {
            $ca = $options['ca'];
            unset($options['ca']);
        }
        $options && curl_setopt_array($ch, $options);

        $isUpload = false;
        $method = strtoupper($method);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        $isArrParams = is_array($params);
        if (in_array($method, ['GET', 'DELETE'])) {
            // GET DELETE
            $queryString = '';
            if ($params) {
                $urlStrs = parse_url($url);
                $queryString = ($urlStrs['query'] ? '&' : '?') . ($isArrParams ? http_build_query($params) : $params);
            }

            curl_setopt($ch, CURLOPT_URL, $url . $queryString);
        } else {
            // POST PUT
            $isUpload = empty($files) ? false : true;
            @curl_setopt($ch, CURLOPT_SAFE_UPLOAD, false);
            curl_setopt($ch, CURLOPT_URL, $url);

            if ($isUpload) {
                foreach ($files as $name => $file) {
                    $params[$name] = $this->makeCurlFile($file);
                }
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $isArrParams ? http_build_query($params) : $params);
            }
        }

        if (strpos($url, 'https://') === 0) {
            // https请求
            if (isset($ca)) {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                curl_setopt($ch, CURLOPT_CAINFO, $ca['cert']);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            } else {
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
            }
        }

        if ($this->cookies) {
            curl_setopt($ch, CURLOPT_COOKIE, implode('; ', $this->cookies));
        }

        curl_setopt($ch, CURLOPT_HEADER, true);

        $headers['Expect'] = '';
        empty($headers['content-type']) && ($headers['content-type'] = $isUpload ? "multipart/form-data; charset=UTF-8" : "application/x-www-form-urlencoded; charset=UTF-8");
        $reqHeaders = array_merge($this->getHeaders($this->requestHeaders), $this->getHeaders($headers));

        curl_setopt($ch, CURLOPT_HTTPHEADER, $reqHeaders);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // 以文件流返回，而不是直接输出
        curl_setopt($ch, CURLOPT_BINARYTRANSFER, true); // 返回原生的（Raw）输出
        $data = curl_exec($ch);
        if ($data === false) {
            error_log("CURLERROR:" . curl_error($ch) . "@URL:" . $url);
            return '';
        }
        $this->httpCode = intval(curl_getinfo($ch, CURLINFO_HTTP_CODE));

        curl_close($ch);
        // 解析headers和body
        list($headerStr, $body) = explode("\r\n\r\n", $data);
        $respHeaders = explode("\r\n", $headerStr);

        foreach ($respHeaders as $row) {
            $pos = strpos($row, ': ');
            if ($pos > 0) {
                $headerName = trim(substr($row, 0, $pos));
                $headerValue = trim(substr($row, $pos + 2));
                $this->headers[$headerName][] = $headerValue;
                if ($headerName == 'Set-Cookie') {
                    // cookie
                    preg_match('/(.*);/iU', $headerValue, $strs);
                    if (count($strs) > 0) {
                        $this->cookies[] = $strs[1];
                    }
                } elseif ($headerName == 'Content-Type') {
                    $this->contentType = $headerValue;
                }
            }
        }

        $this->data = strpos($this->contentType, 'application/json') === 0 ? json_decode($body, true) : $body;
    }

    /**
     * 设置header
     * 后续请求都会使用设置的header
     */
    private function getHeaders($headers = [])
    {
        $reqHeaders = [];
        foreach ($headers as $k => $v) {
            if (is_int($k)) {
                $reqHeaders[] = $v;
            } else {
                $reqHeaders[] = sprintf('%s: %s', $k, $v);
            }
        }

        return $reqHeaders;
    }

    /**
     * 返回 response content-type
     */
    public function getContentType() {
        return $this->contentType;
    }

    /**
     * 返回内容
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * 获取http code
     */
    public function getHttpCode()
    {
        return $this->httpCode;
    }

    /**
     * 设置请求头
     */
    public function setRequestHeaders($name, $value = '')
    {
        if (is_array($name)) {
            foreach ($name as $key => $value) {
                if (is_int($key)) {
                    $this->requestHeaders[] = $value;
                } else {
                    $this->requestHeaders[$key] = $value;
                }
            }
        } else {
            $this->requestHeaders[$name] = $value;
        }
    }

    /**
     * 获取到CURlFile对象
     */
    private function makeCurlFile($file)
    {
        $mime = mime_content_type($file);
        $info = pathinfo($file);
        $name = $info['basename'];
        $output = new \CURLFile(realpath($file), $mime, $name);
        return $output;
    }

    /**
     * 获取 response headers
     */
    public function getResponseHeaders() {
        return $this->headers;
    }

    /**
     * 获取cookie信息
     */
    public function getCookies() {
        return $this->cookies;
    }
}
