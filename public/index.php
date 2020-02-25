<?php
require __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

use \LINE\LINEBot;
use \LINE\LINEBot\HTTPClient\CurlHTTPClient;
use \LINE\LINEBot\MessageBuilder\MultiMessageBuilder;
use \LINE\LINEBot\MessageBuilder\TextMessageBuilder;
use \LINE\LINEBot\MessageBuilder\StickerMessageBuilder;
use \LINE\LINEBot\MessageBuilder\AudioMessageBuilder;
use \LINE\LINEBot\MessageBuilder\ImageMessageBuilder;
use \LINE\LINEBot\MessageBuilder\VideoMessageBuilder;
use \LINE\LINEBot\SignatureValidator as SignatureValidator;

$pass_signature = true;

// set LINE channel_access_token and channel_secret
$channel_access_token = "7q41nFkXI+YpZOEhIpyHOpEFpMsNTxLAQ/35aZPNC44TFqEaQuFGNej5CoqtRwP4+XAUEJhyCDbgdLaZQzVv6zC968HNarPG11WZJG3+1CJuYw/COkFMDU55f2uIw56qHufLM8+Vn3XDg81OUV/L+QdB04t89/1O/w1cDnyilFU=";
$channel_secret = "cedbf9efc5fcf0afd1c5aba1ddd9f40a";

// inisiasi objek bot
$httpClient = new CurlHTTPClient($channel_access_token);
$bot = new LINEBot($httpClient, ['channelSecret' => $channel_secret]);

$app = AppFactory::create();
$app->setBasePath("/public");

$app->get('/', function (Request $request, Response $response, $args) {
    $response->getBody()->write("Hello World!");
    return $response;
});

// buat route untuk webhook
$app->post('/webhook', function (Request $request, Response $response) use ($channel_secret, $bot, $httpClient, $pass_signature) {
    // get request body and line signature header
    $body = $request->getBody();
    $signature = $request->getHeaderLine('HTTP_X_LINE_SIGNATURE');

    // log body and signature
    file_put_contents('php://stderr', 'Body: ' . $body);

    if ($pass_signature === false) {
        // is LINE_SIGNATURE exists in request header?
        if (empty($signature)) {
            return $response->withStatus(400, 'Signature not set');
        }

        // is this request comes from LINE?
        if (!SignatureValidator::validateSignature($body, $channel_secret, $signature)) {
            return $response->withStatus(400, 'Invalid signature');
        }
    }
    $data = json_decode($body, true);
    if (is_array($data['events'])) {
        foreach ($data['events'] as $event) {
            if ($event['type'] == 'message') {
                if (
                    $event['source']['type'] == 'group' or
                    $event['source']['type'] == 'room'
                ) {
                    //message from group / room
                    if ($event['source']['userId']) {

                        $userId = $event['source']['userId'];
                        $getprofile = $bot->getProfile($userId);
                        $profile = $getprofile->getJSONDecodedBody();
                        $greetings = new TextMessageBuilder("Halo, " . $profile['displayName']);

                        $result = $bot->replyMessage($event['replyToken'], $greetings);
                        $response->getBody()->write((string) $result->getJSONDecodedBody());
                        return $response
                            ->withHeader('Content-Type', 'application/json')
                            ->withStatus($result->getHTTPStatus());
                    } else {
                        // message from single user


                        $result = $bot->replyText($event['replyToken'], $event['message']['text']);
                        $response->getBody()->write((string) $result->getJSONDecodedBody());
                        return $response
                            ->withHeader('Content-Type', 'application/json')
                            ->withStatus($result->getHTTPStatus());
                    }
                } else {
                    //message from single user

                    if ($event['message']['type'] == 'text') {
                        if (strtolower($event['message']['text']) == 'user id') {

                            $result = $bot->replyText($event['replyToken'], $event['source']['userId']);
                        } elseif ((strtolower($event['message']['text']) == 'bosen') or (strtolower($event['message']['text']) == 'bosen dong') or (strtolower($event['message']['text']) == 'rekomendasi')) {

                            $flexTemplate = file_get_contents("../flex_message.json"); // template flex message
                            $result = $httpClient->post(LINEBot::DEFAULT_ENDPOINT_BASE . '/v2/bot/message/reply', [
                                'replyToken' => $event['replyToken'],
                                'messages'   => [
                                    [
                                        'type'     => 'flex',
                                        'altText'  => 'Test Flex Message',
                                        'contents' => json_decode($flexTemplate)
                                    ]
                                ],
                            ]);
                        } elseif ((strtolower($event['message']['text']) == 'kamu siapa') or (strtolower($event['message']['text']) == 'kenalin diri dong') or (strtolower($event['message']['text']) == 'kamu siapa?')) {

                            $userId = $event['source']['userId'];
                            $getprofile = $bot->getProfile($userId);
                            $profile = $getprofile->getJSONDecodedBody();
                            $greetings = new TextMessageBuilder("Halo, " . $profile['displayName'] . ". Perkenalkan saya bot yang nemenin kamu kalo lagi kesepian. Kamu bisa ketik 'REKOMENDASI' atau 'BOSEN' untuk mendapatkan rekomendasi dari kami");

                            $result = $bot->replyMessage($event['replyToken'], $greetings);

                            $response->getBody()->write((string) $result->getJSONDecodedBody());
                            return $response
                                ->withHeader('Content-Type', 'application/json')
                                ->withStatus($result->getHTTPStatus());
                        } else {
                            // send same message as reply to user
                            $result = $bot->replyText($event['replyToken'], $event['message']['text']);
                        }

                        $response->getBody()->write($result->getJSONDecodedBody());
                        return $response
                            ->withHeader('Content-Type', 'application/json')
                            ->withStatus($result->getHTTPStatus());
                    }

                    if ($event['message']['type'] == 'text') {
                        // send same message as reply to user
                        $result = $bot->replyText($event['replyToken'], $event['message']['text']);

                        $replyToken = $event['replyToken'];

                        $bot->replyText($replyToken, 'ini pesan balasan');

                        // or we can use replyMessage() instead to send reply message
                        // $textMessageBuilder = new TextMessageBuilder($event['message']['text']);
                        // $result = $bot->replyMessage($event['replyToken'], $textMessageBuilder);


                        $response->getBody()->write($result->getJSONDecodedBody());
                        return $response
                            ->withHeader('Content-Type', 'application/json')
                            ->withStatus($result->getHTTPStatus());
                    } else if (
                        $event['message']['type'] == 'image' or
                        $event['message']['type'] == 'video' or
                        $event['message']['type'] == 'audio' or
                        $event['message']['type'] == 'file'
                    ) {
                        $contentURL = "https://implementlinephp.herokuapp.com/public/content/" . $event['message']['id'];
                        $contentType = ucfirst($event['message']['type']);
                        $result = $bot->replyText(
                            $event['replyToken'],
                            $contentType . " yang Anda kirim bisa diakses dari link:\n " . $contentURL
                        );

                        $response->getBody()->write((string) $result->getJSONDecodedBody());
                        return $response
                            ->withHeader('Content-Type', 'application/json')
                            ->withStatus($result->getHTTPStatus());
                    } elseif ($event['message']['type'] == 'text') {
                        // send same message as reply to user
                        if (strtolower($event['message']['text']) == 'user id') {

                            $result = $bot->replyText($event['replyToken'], $event['source']['userId']);
                        } else {
                            // send same message as reply to user
                            $result = $bot->replyText($event['replyToken'], $event['message']['text']);
                        }

                        $response->getBody()->write($result->getJSONDecodedBody());
                        return $response
                            ->withHeader('Content-Type', 'application/json')
                            ->withStatus($result->getHTTPStatus());
                    }
                }
            }
        }
    }

    return $response->withStatus(400, 'No event sent!');
});



$app->get('/pushmessage', function ($req, $response) use ($bot) {
    // send push message to user
    $userId = 'Ud87671ad3f635d81d1a142dc371d0d5c';
    $textMessageBuilder = new TextMessageBuilder('Halo, ini pesan push');
    $result = $bot->pushMessage($userId, $textMessageBuilder);

    $stickerMessageBuilder = new StickerMessageBuilder(1, 106);
    $bot->pushMessage($userId, $stickerMessageBuilder);

    $response->getBody()->write((string) $result->getJSONDecodedBody());
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($result->getHTTPStatus());
});

$app->get('/multicast', function ($req, $response) use ($bot) {
    // list of users
    $userList = [
        'Ud87671ad3f635d81d1a142dc371d0d5c',
        'U1afb282d2e367cc2396fbf1d8716a5c7',
        'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
        'xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'
    ];

    // send multicast message to user
    $textMessageBuilder = new TextMessageBuilder('Halo, ini pesan multicast');
    $result = $bot->multicast($userList, $textMessageBuilder);


    $response->getBody()->write((string) $result->getJSONDecodedBody());
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($result->getHTTPStatus());
});

$app->get('/profile', function ($req, $response) use ($bot) {
    // get user profile
    $userId = 'Ud87671ad3f635d81d1a142dc371d0d5c';
    $result = $bot->getProfile($userId);

    $response->getBody()->write((string) $result->getJSONDecodedBody());
    return $response
        ->withHeader('Content-Type', 'application/json')
        ->withStatus($result->getHTTPStatus());
});

$app->get('/content/{messageId}', function ($req, $response) use ($bot) {
    // get message content
    $route = $req->getAttribute('route');
    $messageId = $route->getArgument('messageId');
    $result = $bot->getMessageContent($messageId);

    // set response
    $response->getBody()->write($result->getRawBody());

    return $response
        ->withHeader('Content-Type', $result->getHeader('Content-Type'))
        ->withStatus($result->getHTTPStatus());
});

$app->run();
