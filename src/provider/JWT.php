<?php


namespace thans\jwt\provider;

use thans\jwt\facade\JWTAuth;
use thans\jwt\parser\AuthHeader;
use thans\jwt\parser\Cookie;
use thans\jwt\parser\Param;
use think\App;
use think\Container;
use think\facade\Config;
use think\Request;
use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;

class JWT
{
    private $request;

    private $config;

    public function __construct(Request $request)
    {
        $this->request = $request;
        $config        = require __DIR__.'/../../config/config.php';
        if (strpos(App::VERSION, '6.0') !== false) {
            $this->config = array_merge($config, !empty(Config::get('jwt')) ?Config::get('jwt'): []);
        } else {
            $this->config = array_merge($config, !empty(Config::get('jwt.')) ?Config::get('jwt.'): []);
        }
    }

    protected function registerBlacklist()
    {
        Container::getInstance()->make('thans\jwt\Blacklist', [
            new $this->config['blacklist_storage'],
        ]);
    }


    protected function registerProvider()
    {
        //builder asymmetric keys
        $keys = $this->config['secret']
            ? $this->config['secret']
            : [
                'public'   => $this->config['public_key'],
                'private'  => $this->config['private_key'],
                'password' => $this->config['password'],
            ];
        Container::getInstance()->make('thans\jwt\provider\JWT\Lcobucci', [
            new Builder(),
            new Parser(),
            $this->config['algo'],
            $keys,
        ]);
    }

    protected function registerFactory()
    {
        Container::getInstance()->make('thans\jwt\claim\Factory', [
            new Request(),
            $this->config['ttl'],
            $this->config['refresh_ttl'],
        ]);
    }

    protected function registerPayload()
    {
        Container::getInstance()->make('thans\jwt\Payload', [
            Container::getInstance()->make('thans\jwt\claim\Factory'),
        ]);
    }

    protected function registerManager()
    {
        Container::getInstance()->make('thans\jwt\Manager', [
            Container::getInstance()->make('thans\jwt\Blacklist'),
            Container::getInstance()->make('thans\jwt\Payload'),
            Container::getInstance()->make('thans\jwt\provider\JWT\Lcobucci'),
        ]);
    }

    protected function registerJWTAuth()
    {
        JWTAuth::parser()->setRequest($this->request)->setChain([
            new AuthHeader(),
            new Cookie(),
            new Param(),
        ]);
    }

    public function init()
    {
        $this->registerBlacklist();
        $this->registerProvider();
        $this->registerFactory();
        $this->registerPayload();
        $this->registerManager();
        $this->registerJWTAuth();
    }
}
