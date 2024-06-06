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

            $result = Cache::rememberForever('conversationalabc5', function () {

                $browser = (new BrowserFactory()) -> createBrowser();

                $urlToCapture = "https://app.powerbi.com/view?r=eyJrIjoiMDM2ZTJjNzItNWYwYy00ZjFlLWI4ZmQtMGQ4Y2FkNmNmMjU1IiwidCI6IjMxMTI4Zjg1LTlmMmUtNDhmNi05NDg0LTBjOWMzY2UzZTUzNiJ9";

                $page = $browser -> createPage();
                $page -> setViewport(1920, 1080);
                $page -> navigate($urlToCapture)->waitForNavigation();

                sleep(3);

                $screenshot = $page -> screenshot();
                $screenshot -> saveToFile("/var/www/html/public/captureWIthChrome.png");


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
                                    'text' => "Aja como um analista de BI, e me de um resumo sobre o dashboard na imagem, me de uma resposta de um modo que fique formatado para envio no whatsapp lembrando que negrito no whatsapp usa apenas um asterisco e nao 2, pode usar emojis nos topicos, e também que fique como um assistente enviando as informações, iniciando como 'Segue o resumo de leads' e depois complete com as informacoes, Observe que o grafico Quantidade de oportunidades esta dia e quantidade de oportunidade do dia, no grafico tem somente o dia 2 de janeiro e depois os dias do mes de maio"
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

                return $result;
            });


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
