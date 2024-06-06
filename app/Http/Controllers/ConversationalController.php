<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;
use HeadlessChromium\BrowserFactory;
use GuzzleHttp\Client as HttpClient;

class ConversationalController extends Controller
{
    public function sendDashboardReport()
    {

        try {
            $apiKey = config('twilio.open_ai_token');
            $imagePath = "/var/www/html/public/captureWIthChrome.png";
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
                                'text' => "Aja como um analista de BI, e me de um resumo sobre o dashboard na imagem, me de uma resposta de um modo que fique formatado para envio no whatsapp lembrando que negrito no whatsapp usa apenas um asterisco e nao 2, pode usar emojis nos topicos, e também que fique como um assistente enviando as informações, iniciando como 'Segue o resumo de leads' e depois complete com as informacoes do seguinte template
                                Olá!

                                Este é o relatório de performance de sua operação no dia de hoje, 24 de Maio de 2024.

                                Vamos lá:
                                Novos Leads Hoje
                                112

                                Importante você saber:
                                O dia de hoje foi 8,7% melhor que a média das últimas terça-feiras dos últimos 2 meses. ↗

                                Visão Geral do Funil
                                🔘 Trabalhados:
                                🔘 Qualificados:
                                🔘 Agendados:
                                🔘 Vendidos:*

                                Se quiser saber mais detalhes, basta me perguntar. 💬😉


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
                ->create("whatsapp:+5516981130663", // to
                    array(
                        "from" => "whatsapp:".config('twilio.phone_number'),
                        "body" => str_replace("**", "*", $result->choices[0]->message->content)
                    )
                );

            return response()->json(json_decode($result->choices[0]->message->content, true));

        }
        catch (\Exception $ex) {
            dd($ex);
        }
//        finally {
//            $browser -> close();
//        }


    }

    public function new_message(Request $request)
    {

        $result = Cache::get('conversationalabc5');

        $apiKey = config('twilio.open_ai_token');
        $client = \OpenAI::client($apiKey);

        $result = $client->chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Baseado no ultimo relatorio que me respondeu: "' . $result->choices[0]->message->content . '" agora me responda (nao precisa falar que é baseado no resumo fornecido): ' . $request->Body
                ],
            ],
        ]);

        $twilio = new Client(config('twilio.account_sid'), config('twilio.auth_token'));

        $message = $twilio->messages
            ->create("whatsapp:+5516981130663", // to
                array(
                    "from" => "whatsapp:".config('twilio.phone_number'),
                    "body" => str_replace("**", "*", $result->choices[0]->message->content)
                )
            );

        return response("ok", 200);
    }

    public function status(Request $request)
    {
        Log::driver('stderr')
            ->error(json_encode($request->all()));

        return response("ok", 200);
    }
}
