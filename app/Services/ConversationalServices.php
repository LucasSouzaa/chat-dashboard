<?php

namespace App\Services;

use App\Models\Phone;
use HeadlessChromium\BrowserFactory;
use Illuminate\Support\Facades\Cache;
use Twilio\Rest\Client;
use GuzzleHttp\Client as HttpClient;

class ConversationalServices
{

    public function showMenu($request, $from, $invalid = false)
    {
        $apiKey = config('twilio.open_ai_token');
        $client = \OpenAI::client($apiKey);

        $dashboards = Phone::with('dashboards')->where('phone', $from)->first();

        $message = "";

        if ($invalid) {
            $message .= "*Opção inválida ou sessão expirada!* \n\n";
        }

        $message .= "Selecione o dashboard que deseja visualizar \n\n";

        foreach($dashboards->dashboards as $key => $dashboard)
        {
            $message .= "*" . $key + 1 . ":* " . $dashboard->name . "\n";
        }

        $twilio = new Client(config('twilio.account_sid'), config('twilio.auth_token'));

        $dashboards->memory = ['messages' => [['message' => 'menu', 'kind' => 'user']]];
        $dashboards->save();

        return $twilio->messages
            ->create("whatsapp:{$dashboards->phone}", // to
                array(
                    "from" => "whatsapp:".config('twilio.phone_number'),
                    "body" => str_replace("**", "*", $message)
                )
            );
    }

    public function sendDashboard($user, $urlToCapture, $name)
    {

        $browser = (new BrowserFactory()) -> createBrowser();

        $page = $browser -> createPage();
        $page -> setViewport(1920, 1080);
        $page -> navigate($urlToCapture)->waitForNavigation();

        sleep(1);

        $namefile = now() . "dashprint.png";

        $screenshot = $page -> screenshot();
        $screenshot -> saveToFile("/var/www/html/public/$namefile");

        $apiKey = config('twilio.open_ai_token');
        $imagePath = "/var/www/html/public/$namefile";
        $base64Image = base64_encode(file_get_contents($imagePath));

        $client = new HttpClient();

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer $apiKey",
        ];

        $payload = [
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Aja como um analista de BI, e me de um resumo sobre o dashboard na imagem, me de uma resposta de um modo que fique formatado para envio no whatsapp, preencha o template abaixo e me retorne o texto com as informacoes do seguinte dashboard enviado por imagem, SOME os 2 lados da imagem para que no resumo tenha a soma dos leads de Facebook e do Google:
<template>
Segue o resumo de leads de {$name}:

Olá!

Este é o relatório de performance de sua operação no dia de hoje, " . now()->day . " de " . now()->month . " de 2024.

Vamos lá:
Total de Leads
XX

Visão Geral do Funil
🔘 Trabalhados: XX (XX%)
🔘 Qualificados: XX (XX%)
🔘 Agendados: XX (XX%)
🔘 Vendidos:* XX (XX%)

Se quiser saber mais detalhes, basta me perguntar. 💬😉
</template>"
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:image/jpeg;base64,$base64Image"
                            ]
                        ]
                    ]
                ]
            ],
            'max_tokens' => 4096
        ];

        $response = $client->post('https://api.openai.com/v1/chat/completions', [
            'headers' => $headers,
            'json' => $payload,
        ]);

        $result = json_decode($response->getBody()->getContents());

        $twilio = new Client(config('twilio.account_sid'), config('twilio.auth_token'));

        $message = $twilio->messages
            ->create("whatsapp:{$user->phone}", // to
                array(
                    "from" => "whatsapp:".config('twilio.phone_number'),
                    "body" => str_replace("**", "*", $result->choices[0]->message->content)
                )
            );

        $gptcontent = $result->choices[0]->message->content;
        return [$gptcontent, $imagePath];
    }

    public function talkToGPT($user, $imagePath, $history)
    {
        $apiKey = config('twilio.open_ai_token');
        $base64Image = base64_encode(file_get_contents($imagePath));

        $history = json_encode($history);

        $client = new HttpClient();

        $headers = [
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer $apiKey",
        ];

        $payload = [
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'text',
                            'text' => "Responda baseado no DASHBOARD enviado por imagem e no contexto da conversa passado no seguinte JSON
                                {$history}
                            "
                        ],
                        [
                            'type' => 'image_url',
                            'image_url' => [
                                'url' => "data:image/jpeg;base64,$base64Image"
                            ]
                        ]
                    ]
                ]
            ],
            'max_tokens' => 4096
        ];

        $response = $client->post('https://api.openai.com/v1/chat/completions', [
            'headers' => $headers,
            'json' => $payload,
        ]);

        $result = json_decode($response->getBody()->getContents());

        $twilio = new Client(config('twilio.account_sid'), config('twilio.auth_token'));

        $message = $twilio->messages
            ->create("whatsapp:{$user->phone}", // to
                array(
                    "from" => "whatsapp:".config('twilio.phone_number'),
                    "body" => str_replace("**", "*", $result->choices[0]->message->content)
                )
            );

        return $result->choices[0]->message->content;
    }
}
