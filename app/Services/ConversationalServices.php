<?php

namespace App\Services;

use HeadlessChromium\BrowserFactory;
use Illuminate\Support\Facades\Cache;
use Twilio\Rest\Client;
use GuzzleHttp\Client as HttpClient;

class ConversationalServices
{
    public function sendDashboard($request, $urlToCapture)
    {

        $browser = (new BrowserFactory()) -> createBrowser();

        $page = $browser -> createPage();
        $page -> setViewport(1920, 1080);
        $page -> navigate($urlToCapture)->waitForNavigation();

        sleep(3);

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
                            'text' => "Aja como um analista de BI, e me de um resumo sobre o dashboard na imagem, me de uma resposta de um modo que fique formatado para envio no whatsapp lembrando que negrito no whatsapp usa apenas um asterisco e nao 2, pode usar emojis nos topicos, e também que fique como um assistente enviando as informações, iniciando como 'Segue o resumo de leads' e depois complete com as informacoes do seguinte template, SOME os leads de FACEBOOK E GOOGLE para colocar no template
                                Olá!

                                Este é o relatório de performance de sua operação no dia de hoje, XX de XX de 2024.

                                Vamos lá:
                                Total de Leads
                                XXX

                                Visão Geral do Funil
                                🔘 Trabalhados: XX (XX%)
                                🔘 Qualificados: XX (XX%)
                                🔘 Agendados: XX (XX%)
                                🔘 Vendidos:* XX (XX%)

                                Se quiser saber mais detalhes, basta me perguntar. 💬😉"
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
            ->create("whatsapp:+5516981130663", // to
                array(
                    "from" => "whatsapp:".config('twilio.phone_number'),
                    "body" => str_replace("**", "*", $result->choices[0]->message->content)
                )
            );
    }
}
