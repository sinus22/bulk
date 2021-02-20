<?php

namespace App\Http\Handlers;

use App\Http\Exceptions\ApiException;
use App\Http\Exceptions\InvalidJsonInput;
use App\Http\Exceptions\InvalidTokenException;
use App\Http\Exceptions\TelegramMethodCallException;
use App\Http\Exceptions\ValidationException;
use App\Http\Telegram;
use App\Memory\Redis;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Valitron\Validator;

class SendMessageHandler
{
    private Redis $redis;

    public function __construct(ContainerInterface $container)
    {
        $this->redis = new Redis($container->get('redis_url'));
    }

    /**
     * @param ServerRequestInterface $request
     * @param ResponseInterface $response
     * @return ResponseInterface
     * @throws ValidationException|InvalidJsonInput
     * @throws TelegramMethodCallException|InvalidTokenException
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        $body = $this->parseBody($request);
        $this->validate($body);

        $api = new Telegram();
        $me = $api->getMe($body['token']);

        if (!$me['ok']) {
            throw new InvalidTokenException($me);
        }

        $bot_id = $me['result']['id'];

        if ($this->redis->taskExists($bot_id)) {
            throw new ApiException('Task already exists for this bot', 429);
        }

        $data = $this->only($body, ['text', 'parse_mode', 'disable_web_page_preview',
            'disable_notification', 'entities', 'reply_markup']);

        $message = $api->sendMessage('798987043:AAEFbSVifXq8POi5Sg4FlayAkrh7buJwcSs',
            -1001176886276,
            $data);

        if (!$message['ok']) {
            throw new TelegramMethodCallException('sendMessage', $message);
        }

        $chats_id = array_unique($body['chats_id']);

        $this->redis->setData($bot_id, $body['token'], 'sendMessage', $data, $chats_id);

        $response->getBody()->write('dd');
        return $response;
    }

    /**
     * @param array $body
     * @throws ValidationException
     */
    private function validate(array $body)
    {
        $validator = new Validator($body);
        $validator->setPrependLabels(false);
        $validator->rule('required', ['token', 'chats_id'])
            ->rule('string', 'token')
            ->rule('my_array', 'chats_id')
            ->rule('integer', 'chats_id.*');

        $validator->rule('required', 'text')
            ->rule('string', ['text', 'parse_mode'])
            ->rule('lengthMax', 'text', 4096)
            ->rule('parse_mode_values', 'parse_mode')
            ->rule('exclude_if_entities', 'parse_mode')
            ->rule('boolean', ['disable_web_page_preview', 'disable_notification'])
            ->rule('my_array', 'entities');

        if (!$validator->validate()) {
            throw new ValidationException($validator->errors());
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @return array
     * @throws InvalidJsonInput
     */
    private function parseBody(ServerRequestInterface $request) :array
    {
        try {
            $body = json_decode($request->getBody(), flags: JSON_THROW_ON_ERROR | JSON_OBJECT_AS_ARRAY);
        } catch (\JsonException $exception){
            throw new InvalidJsonInput();
        }
        return $body;
    }

    private function only(array $array, array $keys)
    {
        $result = [];

        foreach ($keys as $key)
        {
            if (isset($array[$key])) {
                $result[$key] = $array[$key];
            }
        }

        return $result;
    }
}
