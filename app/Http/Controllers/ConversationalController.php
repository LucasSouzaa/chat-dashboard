<?php

namespace App\Http\Controllers;

use App\Models\Phone;
use App\Services\ConversationalServices;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;
use HeadlessChromium\BrowserFactory;
use GuzzleHttp\Client as HttpClient;

class ConversationalController extends Controller
{


    public function __construct(protected ConversationalServices $conversationalServices)
    {
    }

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

                                Este é o relatório de performance de sua operação no dia de hoje, XX de XX de 2024.

                                Vamos lá:
                                Novos Leads Hoje
                                112

                                Importante você saber:
                                O dia de hoje foi XX melhor que a média das últimas XX dos últimos XX meses. ↗

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

        $from = str_replace("whatsapp:", "", $request->From);

        $user = Phone::where('phone', $from)->first();

        if (strtolower($request->Body) == "menu")
        {
            return $this->conversationalServices->showMenu($request, $from);
        }

        if (isset($user->memory['messages']) && count($user->memory['messages'])) {
            if (is_numeric($request->Body) && count($user->memory) == 1 && strtolower($user->memory['messages'][0]['message']) == 'menu')
            {
                $user->load('dashboards');
                $dashboard = $user->dashboards[$request->Body-1];

                [$gptcontent, $imagePath] = $this->conversationalServices->sendDashboard($user, $dashboard->url, $dashboard->name);

                $user->memory = [
                    'urlimagedash' => $imagePath,
                    'messages' => [['message' => 'menu', 'kind' => 'user'], ['message' => $request->Body, 'kind' => 'user'], ['message' => $gptcontent, 'kind' => 'gpt']]
                ];
                return $user->save();
            }

            $memory = $user->memory['messages'];
            array_push($memory, ['message' => $request->Body, 'kind' => 'user']);

            $gptmessage = $this->conversationalServices->talkToGPT($user, $user->memory['urlimagedash'], $memory);

            array_push($memory, ['message' => $gptmessage, 'kind' => 'gpt']);

            $user->memory = $memory;

            return $user->save();
        }

        return $this->conversationalServices->showMenu($request, $from, true);





        return response("ok", 200);
    }

    public function status(Request $request)
    {
        Log::driver('stderr')
            ->error(json_encode($request->all()));

        return response("ok", 200);
    }
}
