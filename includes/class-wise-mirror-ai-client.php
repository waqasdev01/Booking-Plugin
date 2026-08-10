<?php
/**
 * Generic AI provider client. Reads the active provider + credentials
 * from AI Configuration and makes a real request to that provider.
 * Not automatically wired into the booking flow yet (no feature calls
 * this today) — but it's a genuine, working client: the API Manager's
 * /ai/generate endpoint and the "Test Connection" button both use it.
 *
 * @package Wise_Mirror_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Wise_Mirror_Ai_Client {

	/**
	 * Send a prompt to the currently configured provider.
	 *
	 * @param string $prompt  User/content prompt.
	 * @param array  $args    Optional overrides: system_prompt, max_tokens, temperature.
	 * @return string|WP_Error Response text, or WP_Error on failure/misconfiguration.
	 */
	public static function generate( $prompt, array $args = array() ) {
		$settings = Wise_Mirror_Settings::ai_settings();
		$provider = $settings['provider'];

		if ( 'none' === $provider || '' === $provider ) {
			return new WP_Error( 'wise_ai_not_configured', __( 'No AI provider is configured.', 'wise-mirror-booking' ) );
		}

		$system_prompt = $args['system_prompt'] ?? $settings['system_prompt'];
		$max_tokens    = (int) ( $args['max_tokens'] ?? $settings['max_tokens'] );
		$temperature   = (float) ( $args['temperature'] ?? $settings['temperature'] );

		switch ( $provider ) {
			case 'openai':
				$result = self::call_openai( $settings, $prompt, $system_prompt, $max_tokens, $temperature );
				break;
			case 'gemini':
				$result = self::call_gemini( $settings, $prompt, $system_prompt, $max_tokens, $temperature );
				break;
			case 'claude':
				$result = self::call_claude( $settings, $prompt, $system_prompt, $max_tokens, $temperature );
				break;
			case 'custom':
				$result = self::call_custom( $settings, $prompt, $system_prompt, $max_tokens, $temperature );
				break;
			default:
				return new WP_Error( 'wise_ai_unknown_provider', __( 'Unknown AI provider.', 'wise-mirror-booking' ) );
		}

		if ( ! is_wp_error( $result ) && ! empty( $settings['logging_enabled'] ) ) {
			Wise_Mirror_Logger::log( 'ai', 'AI generation completed', array( 'provider' => $provider, 'prompt_length' => strlen( $prompt ) ) );
		} elseif ( is_wp_error( $result ) && ! empty( $settings['logging_enabled'] ) ) {
			Wise_Mirror_Logger::log( 'ai', 'AI generation failed: ' . $result->get_error_message(), array( 'provider' => $provider ) );
		}

		return $result;
	}

	private static function call_openai( $settings, $prompt, $system_prompt, $max_tokens, $temperature ) {
		if ( empty( $settings['openai_api_key'] ) ) {
			return new WP_Error( 'wise_ai_missing_key', __( 'OpenAI API key is not set.', 'wise-mirror-booking' ) );
		}

		$messages = array();
		if ( $system_prompt ) {
			$messages[] = array( 'role' => 'system', 'content' => $system_prompt );
		}
		$messages[] = array( 'role' => 'user', 'content' => $prompt );

		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $settings['openai_api_key'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'       => $settings['openai_model'] ?: 'gpt-4o-mini',
						'messages'    => $messages,
						'max_tokens'  => $max_tokens,
						'temperature' => $temperature,
					)
				),
			)
		);

		$data = self::parse_response( $response );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return $data['choices'][0]['message']['content'] ?? '';
	}

	private static function call_claude( $settings, $prompt, $system_prompt, $max_tokens, $temperature ) {
		if ( empty( $settings['claude_api_key'] ) ) {
			return new WP_Error( 'wise_ai_missing_key', __( 'Claude API key is not set.', 'wise-mirror-booking' ) );
		}

		$body = array(
			'model'       => $settings['claude_model'] ?: 'claude-3-5-sonnet-latest',
			'max_tokens'  => $max_tokens,
			'temperature' => $temperature,
			'messages'    => array( array( 'role' => 'user', 'content' => $prompt ) ),
		);
		if ( $system_prompt ) {
			$body['system'] = $system_prompt;
		}

		$response = wp_remote_post(
			'https://api.anthropic.com/v1/messages',
			array(
				'timeout' => 30,
				'headers' => array(
					'x-api-key'         => $settings['claude_api_key'],
					'anthropic-version' => '2023-06-01',
					'Content-Type'      => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		$data = self::parse_response( $response );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return $data['content'][0]['text'] ?? '';
	}

	private static function call_gemini( $settings, $prompt, $system_prompt, $max_tokens, $temperature ) {
		if ( empty( $settings['gemini_api_key'] ) ) {
			return new WP_Error( 'wise_ai_missing_key', __( 'Gemini API key is not set.', 'wise-mirror-booking' ) );
		}

		$model = $settings['gemini_model'] ?: 'gemini-1.5-flash';
		$url   = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . rawurlencode( $settings['gemini_api_key'] );

		$full_prompt = $system_prompt ? ( $system_prompt . "\n\n" . $prompt ) : $prompt;

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode(
					array(
						'contents'         => array( array( 'parts' => array( array( 'text' => $full_prompt ) ) ) ),
						'generationConfig' => array( 'maxOutputTokens' => $max_tokens, 'temperature' => $temperature ),
					)
				),
			)
		);

		$data = self::parse_response( $response );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
	}

	private static function call_custom( $settings, $prompt, $system_prompt, $max_tokens, $temperature ) {
		if ( empty( $settings['custom_endpoint'] ) ) {
			return new WP_Error( 'wise_ai_missing_endpoint', __( 'Custom AI endpoint is not set.', 'wise-mirror-booking' ) );
		}

		$headers = array( 'Content-Type' => 'application/json' );
		if ( ! empty( $settings['custom_api_key'] ) ) {
			$headers['Authorization'] = 'Bearer ' . $settings['custom_api_key'];
		}

		$response = wp_remote_post(
			$settings['custom_endpoint'],
			array(
				'timeout' => 30,
				'headers' => $headers,
				'body'    => wp_json_encode(
					array(
						'prompt'        => $prompt,
						'system_prompt' => $system_prompt,
						'max_tokens'    => $max_tokens,
						'temperature'   => $temperature,
					)
				),
			)
		);

		$data = self::parse_response( $response );
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// Custom endpoints vary — accept a couple of common shapes.
		return $data['text'] ?? ( $data['response'] ?? ( $data['output'] ?? wp_json_encode( $data ) ) );
	}

	private static function parse_response( $response ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'wise_ai_http_error', $response->get_error_message() );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$message = is_array( $data ) ? ( $data['error']['message'] ?? __( 'AI provider request failed.', 'wise-mirror-booking' ) ) : __( 'AI provider request failed.', 'wise-mirror-booking' );
			return new WP_Error( 'wise_ai_provider_error', $message );
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'wise_ai_invalid_response', __( 'Invalid response from AI provider.', 'wise-mirror-booking' ) );
		}

		return $data;
	}
}
