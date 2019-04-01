<?php
namespace Http\Tests;

use Http\HttpClient;
use PHPUnit\Framework\TestCase;
use Monolog\Logger;

class HttpTest extends TestCase
{
    function testBaidu()
    {
        $client = new HttpClient();
        $client->get('https://www.baidu.com');
        $this->assertSame(200, $client->getHttpCode());
    }

    function testJson()
    {
        $client = new HttpClient();
        $client->get('https://api.weixin.qq.com/cgi-bin/token?grant_type=client_credential&appid=APPID&secret=APPSECRET');
        $log = new Logger('testJson');
        $log->info('Response Header:', $client->getResponseHeaders());
        $log->info('Response Data:', $client->getData());
        $this->assertContains('application/json', $client->getContentType());
        $this->assertSame(true, is_array($client->getData()));
    }

    function testLogin()
    {
        $client = new HttpClient();
        $client->post('https://dayingjia.morewifi.com/login', [
            'name' => '***@***.***',
            'pass' => '*****'
        ]);
        $log = new Logger('testLogin');
        $log->info('Cookie:', $client->getCookies());

        $client->get('https://dayingjia.morewifi.com/brand/index/');
        $this->assertContains('修改密码', $client->getData());
    }

    function testUpload()
    {
        $client = new HttpClient();
        $client->setRequestHeaders([
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_3) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.86 Safari/537.36'
        ]);
        $client->get('https://sm.ms/');
        $client->request('https://sm.ms/api/upload?inajax=1&ssl=1', 'POST', [
            'file_id' => 0
        ], [], [
            'smfile' => './img/1.png'
        ]);
        $log = new Logger('testLogin');
        $log->info('Data:', [$client->getData()]);
        $this->assertSame('success', $client->getData()['code']);
    }
}
