<?php

return [
    'account_sid' => env("TWILIO_ACCOUNT_SID"),
    'auth_token' => env("TWILIO_AUTH_TOKEN"),
    'phone_number' => env("TWILIO_WHATSAPP_NUMBER"),
    'open_ai_token' => env('OPENAI_TOKEN')
];
