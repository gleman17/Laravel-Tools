<?php

namespace Gleman17\LaravelTools\Services;
use EchoLabs\Prism\Enums\Provider;
use EchoLabs\Prism\Exceptions\PrismException;
use EchoLabs\Prism\Prism;
use EchoLabs\Prism\Responses\TextResponse;
use Config;

class AIQueryService
{
    /**
     * @param int $check_type
     * @param $scam_text
     * @return TextResponse
     * @throws PrismException
     */
    public function generate_response(array $metadata, array $graph, string $query): array
    {
        $retryAttempts = 3; // Number of retry attempts
        $retryDelay = 5; // Delay between retries in seconds
        $systemPrompt = <<<PROMPT
You are a database, sql, and laravel expert. You must use the provided
database schema, graph, and metadata to generate an accurate response.
PROMPT;

        for ($attempt = 1; $attempt <= $retryAttempts; $attempt++) {
            try {
                // Generate the response
                $prism = Prism::text()
                    ->using(Provider::OpenAI, config('gleman17_laravel_tools.ai_model'))
                    ->withSystemPrompt()
                    ->withPrompt($scam_text)
                    ->withClientOptions(['timeout' => 30])
                    ->generate();

                [$detected, $summary, $scam_response_text] = $this->extract_prism_text($prism->text);

                return [true, $detected, $summary, $scam_response_text];
            } catch (Exception $e) {
                // Log the error
                Log::error("Attempt $attempt failed: " . $e->getMessage());

                // If this was the last attempt, return an error code or message
                if ($attempt === $retryAttempts) {
                    return [false, null, null, null];
                }

                // Delay before the next retry
                sleep($retryDelay);
            }
        }

        // Can't really get here, but the IDE is complaining about it
        return [false, null, null, null];
    }
}
