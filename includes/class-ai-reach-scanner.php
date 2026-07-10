<?php
/**
 * Class AI_Reach_Scanner
 * Handles multi-provider API interactions (OpenRouter, OpenAI, Gemini, Perplexity, Anthropic),
 * scanning logic, and 9-pillar GEO calculation.
 */

// Restrict direct access
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AI_Reach_Scanner {

	/**
	 * Active API provider key (openrouter|openai|gemini|perplexity|anthropic)
	 * @var string
	 */
	private $provider;

	/**
	 * Active API key for the selected provider
	 * @var string
	 */
	private $api_key;

	/**
	 * Selected model for the active provider
	 * @var string
	 */
	private $model;

	/**
	 * Brand Name to search for
	 * @var string
	 */
	private $brand_name;

	/**
	 * Keywords to search for
	 * @var string
	 */
	private $keywords;

	/**
	 * Constructor — loads active provider, key, and model from DB.
	 */
	public function __construct() {
		$this->provider   = get_option( 'ai_reach_api_provider', 'openrouter' );
		$key_option       = 'ai_reach_' . sanitize_key( $this->provider ) . '_key';
		$model_option     = 'ai_reach_' . sanitize_key( $this->provider ) . '_model';
		$this->api_key    = get_option( $key_option, '' );
		$this->model      = get_option( $model_option, '' );
		$this->brand_name = get_option( 'ai_reach_brand_name', '' );
		$this->keywords   = get_option( 'ai_reach_keywords', '' );
	}

	/**
	 * Available models per provider.
	 *
	 * @param string $provider Provider key.
	 * @return array
	 */
	public static function get_provider_models( $provider ) {
		$map = array(
			'openrouter' => array(
				'default'                                       => 'Default (engine-specific models)',
				'google/gemini-2.5-flash'                       => 'Gemini 2.5 Flash',
				'google/gemini-2.5-pro'                         => 'Gemini 2.5 Pro',
				'openai/gpt-4o'                                 => 'GPT-4o',
				'openai/gpt-4o-mini'                            => 'GPT-4o Mini',
				'anthropic/claude-3.5-sonnet'                   => 'Claude 3.5 Sonnet',
				'perplexity/llama-3.1-sonar-large-128k-online'  => 'Perplexity Sonar Large',
			),
			'openai' => array(
				'gpt-4o-mini'    => 'GPT-4o Mini',
				'gpt-4o'         => 'GPT-4o',
				'gpt-3.5-turbo'  => 'GPT-3.5 Turbo',
			),
			'gemini' => array(
				'gemini-2.5-flash' => 'Gemini 2.5 Flash',
				'gemini-2.5-pro'   => 'Gemini 2.5 Pro',
				'gemini-1.5-flash' => 'Gemini 1.5 Flash',
				'gemini-1.5-pro'   => 'Gemini 1.5 Pro',
			),
			'perplexity' => array(
				'sonar'            => 'Sonar',
				'sonar-reasoning'  => 'Sonar Reasoning',
			),
			'anthropic' => array(
				'claude-3-5-sonnet-20241022' => 'Claude 3.5 Sonnet',
				'claude-3-5-haiku-20241022'  => 'Claude 3.5 Haiku',
				'claude-3-opus-20240229'     => 'Claude 3 Opus',
			),
		);
		return isset( $map[ $provider ] ) ? $map[ $provider ] : array();
	}

	/**
	 * Get configured model ID by engine key (used for OpenRouter default mapping).
	 */
	public function get_openrouter_engine_model( $engine_id ) {
		$models = array(
			'openai'     => 'openai/gpt-4o',
			'gemini'     => 'google/gemini-2.5-pro',
			'perplexity' => 'perplexity/llama-3.1-sonar-large-128k-online',
			'claude'     => 'anthropic/claude-3.5-sonnet',
			'siri'       => 'google/gemini-2.5-flash',
		);
		return isset( $models[ $engine_id ] ) ? $models[ $engine_id ] : 'google/gemini-2.5-flash';
	}

	/**
	 * Construct prompt for the scan
	 */
	public function get_scan_prompt() {
		$brand = ! empty( $this->brand_name ) ? $this->brand_name : get_bloginfo( 'name' );
		$kw    = ! empty( $this->keywords ) ? $this->keywords : 'website';

		return sprintf(
			"Search the web for '%s'. In the top results or recommendations, is the brand '%s' mentioned? Answer with a strict JSON format (no markdown blocks, no leading/trailing text, just raw JSON): {\"mentioned\": true/false, \"transcript\": \"full AI response text summarizing what was found and how the brand is positioned\"}",
			$kw,
			$brand
		);
	}

	/**
	 * Test the connection for the given provider and api key.
	 *
	 * @param string $api_key  The API key to test.
	 * @param string $provider The provider slug.
	 * @param string $model    The model ID to use for testing.
	 * @return true|WP_Error
	 */
	public function test_connection( $api_key, $provider = 'openrouter', $model = '' ) {
		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_key', __( 'API Key is empty.', 'ai-reach-geotracker' ) );
		}

		switch ( $provider ) {
			case 'openai':
				return $this->test_openai( $api_key, $model ?: 'gpt-4o-mini' );
			case 'gemini':
				return $this->test_gemini( $api_key, $model ?: 'gemini-2.5-flash' );
			case 'perplexity':
				return $this->test_perplexity( $api_key, $model ?: 'sonar' );
			case 'anthropic':
				return $this->test_anthropic( $api_key, $model ?: 'claude-3-5-sonnet-20241022' );
			default: // openrouter
				return $this->test_openrouter( $api_key );
		}
	}

	/** ---- Provider-specific connection testers ---- */

	private function test_openrouter( $api_key ) {
		$url  = 'https://openrouter.ai/api/v1/chat/completions';
		$body = wp_json_encode( array(
			'model'    => 'google/gemini-2.5-flash',
			'messages' => array( array( 'role' => 'user', 'content' => 'Hello' ) ),
		) );
		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . trim( $api_key ),
			'HTTP-Referer'  => esc_url( home_url() ),
			'X-Title'       => 'AI Reach GEO Tracker',
		);
		return $this->do_http_test( $url, $body, $headers );
	}

	private function test_openai( $api_key, $model ) {
		$url  = 'https://api.openai.com/v1/chat/completions';
		$body = wp_json_encode( array(
			'model'    => $model,
			'messages' => array( array( 'role' => 'user', 'content' => 'Hello' ) ),
		) );
		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . trim( $api_key ),
		);
		return $this->do_http_test( $url, $body, $headers );
	}

	private function test_gemini( $api_key, $model ) {
		$url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( trim( $api_key ) );
		$body = wp_json_encode( array(
			'contents' => array(
				array( 'parts' => array( array( 'text' => 'Hello' ) ) ),
			),
		) );
		$headers = array( 'Content-Type' => 'application/json' );
		return $this->do_http_test( $url, $body, $headers );
	}

	private function test_perplexity( $api_key, $model ) {
		$url  = 'https://api.perplexity.ai/chat/completions';
		$body = wp_json_encode( array(
			'model'    => $model,
			'messages' => array( array( 'role' => 'user', 'content' => 'Hello' ) ),
		) );
		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . trim( $api_key ),
		);
		return $this->do_http_test( $url, $body, $headers );
	}

	private function test_anthropic( $api_key, $model ) {
		$url  = 'https://api.anthropic.com/v1/messages';
		$body = wp_json_encode( array(
			'model'      => $model,
			'max_tokens' => 10,
			'messages'   => array( array( 'role' => 'user', 'content' => 'Hello' ) ),
		) );
		$headers = array(
			'Content-Type'     => 'application/json',
			'x-api-key'        => trim( $api_key ),
			'anthropic-version' => '2023-06-01',
		);
		return $this->do_http_test( $url, $body, $headers );
	}

	/**
	 * Generic HTTP POST tester — returns true on 200, WP_Error otherwise.
	 */
	private function do_http_test( $url, $body, $headers ) {
		$response = wp_remote_post( $url, array(
			'headers' => $headers,
			'body'    => $body,
			'timeout' => 15,
		) );

		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$res_body = wp_remote_retrieve_body( $response );
			$err_data = json_decode( $res_body, true );
			$err_msg  = isset( $err_data['error']['message'] )
				? $err_data['error']['message']
				: /* translators: %d HTTP response code */ sprintf( __( 'API returned HTTP code %d.', 'ai-reach-geotracker' ), $code );
			return new WP_Error( 'api_error', $err_msg );
		}
		return true;
	}

	/**
	 * Run scan for a single engine using the active provider.
	 *
	 * @param string $engine_id One of: openai, gemini, perplexity, claude, siri.
	 * @return array|WP_Error
	 */
	public function run_scan_for_engine( $engine_id ) {
		if ( empty( $this->api_key ) ) {
			/* translators: %s provider label */
			return new WP_Error( 'missing_key', __( 'API key is not configured. Please add your key in the Configuration tab.', 'ai-reach-geotracker' ) );
		}

		$prompt = $this->get_scan_prompt();

		switch ( $this->provider ) {
			case 'openai':
				return $this->scan_via_openai( $prompt );
			case 'gemini':
				return $this->scan_via_gemini( $prompt );
			case 'perplexity':
				return $this->scan_via_perplexity( $prompt );
			case 'anthropic':
				return $this->scan_via_anthropic( $prompt );
			default: // openrouter
				return $this->scan_via_openrouter( $engine_id, $prompt );
		}
	}

	/** ---- Provider-specific scan executors ---- */

	private function scan_via_openrouter( $engine_id, $prompt ) {
		$model_id = ( ! empty( $this->model ) && 'default' !== $this->model )
			? $this->model
			: $this->get_openrouter_engine_model( $engine_id );

		$url  = 'https://openrouter.ai/api/v1/chat/completions';
		$body = wp_json_encode( array(
			'model'    => $model_id,
			'messages' => array( array( 'role' => 'user', 'content' => $prompt ) ),
		) );
		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . trim( $this->api_key ),
			'HTTP-Referer'  => esc_url( home_url() ),
			'X-Title'       => 'AI Reach GEO Tracker',
		);
		$raw = $this->do_http_scan( $url, $body, $headers );
		if ( is_wp_error( $raw ) ) return $raw;
		return $this->parse_openai_style_response( $raw );
	}

	private function scan_via_openai( $prompt ) {
		$url  = 'https://api.openai.com/v1/chat/completions';
		$body = wp_json_encode( array(
			'model'           => $this->model ?: 'gpt-4o-mini',
			'messages'        => array( array( 'role' => 'user', 'content' => $prompt ) ),
			'response_format' => array( 'type' => 'json_object' ),
		) );
		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . trim( $this->api_key ),
		);
		$raw = $this->do_http_scan( $url, $body, $headers );
		if ( is_wp_error( $raw ) ) return $raw;
		return $this->parse_openai_style_response( $raw );
	}

	private function scan_via_gemini( $prompt ) {
		$model = $this->model ?: 'gemini-2.5-flash';
		$url   = 'https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( trim( $this->api_key ) );
		$body  = wp_json_encode( array(
			'contents'         => array(
				array( 'parts' => array( array( 'text' => $prompt ) ) ),
			),
			'tools'            => array( array( 'googleSearch' => (object) array() ) ),
			'generationConfig' => array( 'responseMimeType' => 'application/json' ),
		) );
		$headers = array( 'Content-Type' => 'application/json' );
		$raw = $this->do_http_scan( $url, $body, $headers );
		if ( is_wp_error( $raw ) ) return $raw;
		return $this->parse_gemini_response( $raw );
	}

	private function scan_via_perplexity( $prompt ) {
		$url  = 'https://api.perplexity.ai/chat/completions';
		$body = wp_json_encode( array(
			'model'    => $this->model ?: 'sonar',
			'messages' => array( array( 'role' => 'user', 'content' => $prompt ) ),
		) );
		$headers = array(
			'Content-Type'  => 'application/json',
			'Authorization' => 'Bearer ' . trim( $this->api_key ),
		);
		$raw = $this->do_http_scan( $url, $body, $headers );
		if ( is_wp_error( $raw ) ) return $raw;
		return $this->parse_openai_style_response( $raw );
	}

	private function scan_via_anthropic( $prompt ) {
		$url  = 'https://api.anthropic.com/v1/messages';
		$body = wp_json_encode( array(
			'model'      => $this->model ?: 'claude-3-5-sonnet-20241022',
			'max_tokens' => 1024,
			'messages'   => array( array( 'role' => 'user', 'content' => $prompt ) ),
		) );
		$headers = array(
			'Content-Type'      => 'application/json',
			'x-api-key'         => trim( $this->api_key ),
			'anthropic-version' => '2023-06-01',
		);
		$raw = $this->do_http_scan( $url, $body, $headers );
		if ( is_wp_error( $raw ) ) return $raw;
		return $this->parse_anthropic_response( $raw );
	}

	/**
	 * Generic HTTP POST for scans (45s timeout).
	 */
	private function do_http_scan( $url, $body, $headers ) {
		$response = wp_remote_post( $url, array(
			'headers' => $headers,
			'body'    => $body,
			'timeout' => 45,
		) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}
		$code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			$res_body = wp_remote_retrieve_body( $response );
			$err_data = json_decode( $res_body, true );
			$err_msg  = isset( $err_data['error']['message'] )
				? $err_data['error']['message']
				: /* translators: %d HTTP response code */ sprintf( __( 'API response code %d.', 'ai-reach-geotracker' ), $code );
			return new WP_Error( 'api_error', $err_msg );
		}
		return wp_remote_retrieve_body( $response );
	}

	/** ---- Response parsers ---- */

	/**
	 * Parses OpenAI-compatible responses (OpenRouter, OpenAI, Perplexity).
	 */
	private function parse_openai_style_response( $body ) {
		$data = json_decode( $body, true );
		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid response format from API.', 'ai-reach-geotracker' ) );
		}
		$content = $data['choices'][0]['message']['content'];
		return $this->extract_mentioned_and_transcript( $content );
	}

	/**
	 * Parses Gemini generateContent responses.
	 */
	private function parse_gemini_response( $body ) {
		$data = json_decode( $body, true );
		if ( ! isset( $data['candidates'][0]['content']['parts'][0]['text'] ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid response format from Gemini API.', 'ai-reach-geotracker' ) );
		}
		$content = $data['candidates'][0]['content']['parts'][0]['text'];
		return $this->extract_mentioned_and_transcript( $content );
	}

	/**
	 * Parses Anthropic Messages API responses.
	 */
	private function parse_anthropic_response( $body ) {
		$data = json_decode( $body, true );
		if ( ! isset( $data['content'][0]['text'] ) ) {
			return new WP_Error( 'invalid_response', __( 'Invalid response format from Anthropic API.', 'ai-reach-geotracker' ) );
		}
		$content = $data['content'][0]['text'];
		return $this->extract_mentioned_and_transcript( $content );
	}

	/**
	 * Common logic: extract {mentioned, transcript} from any text content.
	 */
	private function extract_mentioned_and_transcript( $content ) {
		$json_extracted = array();
		if ( preg_match( '/\{.*\}/s', $content, $matches ) ) {
			$json_extracted = json_decode( $matches[0], true );
		}

		if ( is_array( $json_extracted ) && isset( $json_extracted['mentioned'] ) ) {
			return array(
				'mentioned'  => (bool) $json_extracted['mentioned'],
				'transcript' => isset( $json_extracted['transcript'] ) ? sanitize_textarea_field( $json_extracted['transcript'] ) : sanitize_textarea_field( $content ),
			);
		}

		// Fallback: check for brand name in raw content
		$brand     = ! empty( $this->brand_name ) ? $this->brand_name : get_bloginfo( 'name' );
		$mentioned = ( stripos( $content, $brand ) !== false );
		return array(
			'mentioned'  => $mentioned,
			'transcript' => sanitize_textarea_field( $content ),
		);
	}

	/**
	 * Run local scans & parse transcripts to build the 9-pillar optimization checklist
	 */
	public function calculate_9_point_checklist( $scan_results ) {
		$checklist = array();
		
		// Fetch local homepage HTML safely for parsing
		$homepage_url = home_url();
		$html_response = wp_remote_get( $homepage_url, array( 'timeout' => 10 ) );
		$homepage_html = '';
		if ( ! is_wp_error( $html_response ) ) {
			$homepage_html = wp_remote_retrieve_body( $html_response );
		}

		$brand = ! empty( $this->brand_name ) ? $this->brand_name : get_bloginfo( 'name' );
		$keywords_raw = ! empty( $this->keywords ) ? $this->keywords : '';
		$keywords_arr = array_filter( array_map( 'trim', explode( ',', $keywords_raw ) ) );

		// Assemble all transcripts for off-page checks
		$all_transcripts = '';
		$mention_count = 0;
		if ( is_array( $scan_results ) ) {
			foreach ( $scan_results as $engine_res ) {
				if ( isset( $engine_res['transcript'] ) ) {
					$all_transcripts .= ' ' . $engine_res['transcript'];
				}
				if ( isset( $engine_res['mentioned'] ) && $engine_res['mentioned'] ) {
					$mention_count++;
				}
			}
		}

		/* ========================================================
		 * CATEGORY 1: TECHNICAL FOUNDATION
		 * ======================================================== */

		// Pillar 1: Brand Entity Alignment (Schema)
		$has_schema = false;
		if ( ! empty( $homepage_html ) ) {
			// Search for schema.org type definitions
			if ( stripos( $homepage_html, 'schema.org' ) !== false && 
				( stripos( $homepage_html, '"@type": "Organization"' ) !== false || 
				  stripos( $homepage_html, '"@type": "Brand"' ) !== false || 
				  stripos( $homepage_html, '"@type": "WebSite"' ) !== false ||
				  stripos( $homepage_html, '"@type": "LocalBusiness"' ) !== false ) ) {
				$has_schema = true;
			}
		}
		
		if ( $has_schema ) {
			$checklist['schema_alignment'] = array(
				'title'          => __( '1. Brand Entity Alignment (Schema)', 'ai-reach-geotracker' ),
				'status'         => 'optimized',
				'score'          => 10,
				'description'    => __( 'Schema.org Brand, Organization, or WebSite JSON-LD markup is present on your homepage.', 'ai-reach-geotracker' ),
				'recommendation' => __( 'No action required. Your semantic structure is ready for AI LLM relationship crawlers.', 'ai-reach-geotracker' ),
				'snippet'        => ''
			);
		} else {
			$checklist['schema_alignment'] = array(
				'title'          => __( '1. Brand Entity Alignment (Schema)', 'ai-reach-geotracker' ),
				'status'         => 'action',
				'score'          => 0,
				'description'    => __( 'No Organization or Brand schema was detected in your homepage HTML header.', 'ai-reach-geotracker' ),
				'recommendation' => __( 'Paste this JSON-LD schema markup inside the head section of your site to establish entity authority:', 'ai-reach-geotracker' ),
				'snippet'        => sprintf(
					"<script type=\"application/ld+json\">\n{\n  \"@context\": \"https://schema.org\",\n  \"@type\": \"Organization\",\n  \"name\": \"%s\",\n  \"url\": \"%s\",\n  \"logo\": \"[Logo_URL]\"\n}\n</script>",
					esc_js( $brand ),
					esc_url( home_url() )
				)
			);
		}

		// Pillar 2: Information Freshness
		$latest_posts = get_posts( array(
			'numberposts'      => 1,
			'post_status'      => 'publish',
			'suppress_filters' => false,
		) );
		$fresh = false;
		$post_date = '';

		if ( ! empty( $latest_posts ) ) {
			$latest_post = $latest_posts[0];
			$post_date   = $latest_post->post_date;
			$days_old    = ( time() - strtotime( $post_date ) ) / ( 60 * 60 * 24 );
			if ( $days_old < 30 ) {
				$fresh = true;
			}
		}

		if ( $fresh ) {
			$checklist['freshness'] = array(
				'title'          => __( '2. Information Freshness (Content Age)', 'ai-reach-geotracker' ),
				'status'         => 'optimized',
				'score'          => 10,
				/* translators: %s: publish date of the latest post */
				'description'    => sprintf( __( 'Your latest content was published on %s (less than 30 days ago).', 'ai-reach-geotracker' ), $post_date ),
				'recommendation' => __( 'No action required. Keep updating your site regularly to feed real-time search models.', 'ai-reach-geotracker' ),
				'snippet'        => ''
			);
		} else {
			$checklist['freshness'] = array(
				'title'          => __( '2. Information Freshness (Content Age)', 'ai-reach-geotracker' ),
				'status'         => 'action',
				'score'          => 0,
				'description'    => ! empty( $post_date )
					/* translators: %s: publish date of the latest post */
					? sprintf( __( 'The latest post was published on %s (older than 30 days ago).', 'ai-reach-geotracker' ), $post_date )
					: __( 'No published posts were found on your site.', 'ai-reach-geotracker' ),
				'recommendation' => __( 'Publish a new post or update an existing one weekly. AI crawlers favor active content domains.', 'ai-reach-geotracker' ),
				'snippet'        => ''
			);
		}

		/* ========================================================
		 * CATEGORY 2: CONTENT OPTIMIZATION
		 * ======================================================== */

		// Pillar 3: Direct Answerability (FAQ/Table density)
		$has_answerability = false;
		if ( ! empty( $homepage_html ) ) {
			if ( stripos( $homepage_html, '<table' ) !== false || 
				 stripos( $homepage_html, '<details' ) !== false || 
				 stripos( $homepage_html, 'class="faq"' ) !== false || 
				 stripos( $homepage_html, 'id="faq"' ) !== false || 
				 stripos( $homepage_html, '<dl' ) !== false ) {
				$has_answerability = true;
			}
		}

		if ( $has_answerability ) {
			$checklist['answerability'] = array(
				'title'          => __( '3. Direct Answerability (FAQ/Tables)', 'ai-reach-geotracker' ),
				'status'         => 'optimized',
				'score'          => 10,
				'description'    => __( 'Structured tables, lists, or details tags are present on your homepage.', 'ai-reach-geotracker' ),
				'recommendation' => __( 'No action required. Your structure supports featured answer extractions.', 'ai-reach-geotracker' ),
				'snippet'        => ''
			);
		} else {
			$checklist['answerability'] = array(
				'title'          => __( '3. Direct Answerability (FAQ/Tables)', 'ai-reach-geotracker' ),
				'status'         => 'action',
				'score'          => 0,
				'description'    => __( 'No tabular listings or FAQ panels detected on the homepage.', 'ai-reach-geotracker' ),
				'recommendation' => __( 'Insert an FAQ block or a direct comparison table. AI engines look for structured blocks to parse answers directly:', 'ai-reach-geotracker' ),
				'snippet'        => "<h3>Frequently Asked Questions</h3>\n<details>\n  <summary>What is our brand service?</summary>\n  <p>We provide industry-leading services directly to your project.</p>\n</details>"
			);
		}

		// Pillar 4: Conversational Tone
		$has_conversational = false;
		if ( ! empty( $homepage_html ) ) {
			$body_text = wp_strip_all_tags( $homepage_html );
			// Check for conversational pronoun count
			$pronouns = array( 'we', 'our', 'you', 'your', 'us', 'i', 'my' );
			$matches_count = 0;
			foreach ( $pronouns as $pronoun ) {
				$matches_count += preg_match_all( '/\b' . preg_quote( $pronoun, '/' ) . '\b/i', $body_text );
			}
			if ( $matches_count > 10 ) {
				$has_conversational = true;
			}
		}

		if ( $has_conversational ) {
			$checklist['conversational'] = array(
				'title'          => __( '4. Conversational Tone', 'ai-reach-geotracker' ),
				'status'         => 'optimized',
				'score'          => 10,
				'description'    => __( 'Homepage copy exhibits natural, first/second-person pronouns suited for chat inputs.', 'ai-reach-geotracker' ),
				'recommendation' => __( 'No action required. The copy is organic and fits standard prompt response styles.', 'ai-reach-geotracker' ),
				'snippet'        => ''
			);
		} else {
			$checklist['conversational'] = array(
				'title'          => __( '4. Conversational Tone', 'ai-reach-geotracker' ),
				'status'         => 'action',
				'score'          => 0,
				'description'    => __( 'Content has a low density of natural, relational terms (under 10 references).', 'ai-reach-geotracker' ),
				'recommendation' => __( 'Revise text from passive academic tone to conversational tone. Optimize for NLP (Natural Language Processing):', 'ai-reach-geotracker' ),
				'snippet'        => "Change: \"Services are rendered by the brand to customers.\"\nTo: \"We deliver our personalized services directly to you.\""
			);
		}

		// Pillar 5: Contextual Relevance
		$has_contextual = false;
		$missing_kw = array();
		if ( ! empty( $homepage_html ) && ! empty( $keywords_arr ) ) {
			// Extract page titles & headings
			preg_match( '/<title>(.*?)<\/title>/is', $homepage_html, $title_match );
			$header_text = '';
			preg_match_all( '/<h[12][^>]*>(.*?)<\/h[12]>/is', $homepage_html, $h_matches );
			if ( isset( $h_matches[1] ) ) {
				$header_text = implode( ' ', $h_matches[1] );
			}
			
			$combined_meta = ( isset( $title_match[1] ) ? $title_match[1] : '' ) . ' ' . $header_text;

			foreach ( $keywords_arr as $keyword ) {
				if ( stripos( $combined_meta, $keyword ) === false ) {
					$missing_kw[] = $keyword;
				}
			}
			if ( empty( $missing_kw ) ) {
				$has_contextual = true;
			}
		} else {
			$has_contextual = true; // Fallback if no keywords provided
		}

		if ( $has_contextual ) {
			$checklist['relevance'] = array(
				'title'          => __( '5. Contextual Relevance (Keywords in Headings)', 'ai-reach-geotracker' ),
				'status'         => 'optimized',
				'score'          => 10,
				'description'    => __( 'All defined target keywords are indexed within title tags or H1/H2 header tags.', 'ai-reach-geotracker' ),
				'recommendation' => __( 'No action required. Your heading hierarchy clearly links keywords with site context.', 'ai-reach-geotracker' ),
				'snippet'        => ''
			);
		} else {
			$checklist['relevance'] = array(
				'title'          => __( '5. Contextual Relevance (Keywords in Headings)', 'ai-reach-geotracker' ),
				'status'         => 'action',
				'score'          => 0,
				/* translators: %s: comma-separated list of missing target keywords */
				'description'    => sprintf( __( 'Missing target keywords in headings: %s.', 'ai-reach-geotracker' ), implode( ', ', $missing_kw ) ),
				'recommendation' => __( 'Ensure major keywords appear organically inside your H1 and H2 tags:', 'ai-reach-geotracker' ),
				'snippet'        => sprintf( "<h1>%s: The Premium [Your Keyword] Solution</h1>", esc_html( $brand ) )
			);
		}

		/* ========================================================
		 * CATEGORY 3: OFF-PAGE TRUST
		 * ======================================================== */

		// Pillar 6: Direct Brand Citation
		if ( $mention_count > 0 ) {
			$checklist['brand_citation'] = array(
				'title'          => __( '6. Direct Brand Citation', 'ai-reach-geotracker' ),
				'status'         => 'optimized',
				'score'          => 10,
				/* translators: %d: number of AI engines that mentioned the brand */
				'description'    => sprintf( __( 'Your brand was mentioned in %d of the 5 active AI engine search results.', 'ai-reach-geotracker' ), $mention_count ),
				'recommendation' => __( 'Good off-page presence. Continue building reviews and mentions.', 'ai-reach-geotracker' ),
				'snippet'        => ''
			);
		} else {
			$checklist['brand_citation'] = array(
				'title'          => __( '6. Direct Brand Citation', 'ai-reach-geotracker' ),
				'status'         => 'action',
				'score'          => 0,
				'description'    => __( 'Your brand was not cited in any of the simulated AI search answers.', 'ai-reach-geotracker' ),
				'recommendation' => __( 'Develop organic PR, secure references on directory listings, and publish reviews to establish digital footprint.', 'ai-reach-geotracker' ),
				'snippet'        => ''
			);
		}

		// Pillar 7: Authority Sources
		$has_authority = false;
		if ( ! empty( $all_transcripts ) ) {
			$auths = array( 'reddit.com', 'quora.com', 'wikipedia.org', 'medium.com', 'github.com', 'forbes', 'techcrunch' );
			foreach ( $auths as $auth ) {
				if ( stripos( $all_transcripts, $auth ) !== false ) {
					$has_authority = true;
					break;
				}
			}
		}

		if ( $has_authority ) {
			$checklist['authority_sources'] = array(
				'title'          => __( '7. Authority Sources Mentions', 'ai-reach-geotracker' ),
				'status'         => 'optimized',
				'score'          => 10,
				'description'    => __( 'AI engine transcripts refer to discussions/sources on authority domains.', 'ai-reach-geotracker' ),
				'recommendation' => __( 'No action required. Your authority context is successfully mapped by AI crawlers.', 'ai-reach-geotracker' ),
				'snippet'        => ''
			);
		} else {
			$checklist['authority_sources'] = array(
				'title'          => __( '7. Authority Sources Mentions', 'ai-reach-geotracker' ),
				'status'         => 'action',
				'score'          => 0,
				'description'    => __( 'No authority platform references (Reddit, Quora, Wiki) were found alongside mentions.', 'ai-reach-geotracker' ),
				'recommendation' => __( 'Participate actively in relevant subreddits, write detailed Quora answers, and secure links on high-authority wikis/media.', 'ai-reach-geotracker' ),
				'snippet'        => ''
			);
		}

		// Pillar 8: Sentiment Vector
		$sentiment_status = 'optimized';
		$sentiment_score = 10;
		$sentiment_desc = __( 'AI response sentiment is positive or neutral.', 'ai-reach-geotracker' );
		$sentiment_recom = __( 'Keep up the customer service and clean reputational records.', 'ai-reach-geotracker' );

		if ( ! empty( $all_transcripts ) ) {
			$negatives = array( 'poor', 'bad', 'avoid', 'worst', 'unreliable', 'scam', 'complaint', 'expensive', 'disappoint' );
			$positives = array( 'recommend', 'great', 'excellent', 'best', 'good', 'reliable', 'trustworthy', 'popular' );
			
			$neg_matches = 0;
			$pos_matches = 0;
			foreach ( $negatives as $neg ) {
				$neg_matches += substr_count( strtolower( $all_transcripts ), $neg );
			}
			foreach ( $positives as $pos ) {
				$pos_matches += substr_count( strtolower( $all_transcripts ), $pos );
			}

			if ( $neg_matches > $pos_matches && $neg_matches > 0 ) {
				$sentiment_status = 'action';
				$sentiment_score  = 0;
				/* translators: 1: number of negative keyword matches, 2: number of positive keyword matches */
				$sentiment_desc   = sprintf( __( 'Negative sentiment cues detected: negative keyword matches (%1$d) exceed positive matches (%2$d).', 'ai-reach-geotracker' ), $neg_matches, $pos_matches );
				$sentiment_recom  = __( 'Evaluate transcripts for complaints, address user reviews online, and build a positive citation campaign.', 'ai-reach-geotracker' );
			}
		}

		$checklist['sentiment'] = array(
			'title'          => __( '8. Sentiment Vector', 'ai-reach-geotracker' ),
			'status'         => $sentiment_status,
			'score'          => $sentiment_score,
			'description'    => $sentiment_desc,
			'recommendation' => $sentiment_recom,
			'snippet'        => ''
		);

		// Pillar 9: EEAT Score (Author Bio)
		$has_author_bio = false;
		$users = get_users( array(
			'role__in' => array( 'administrator', 'editor' ),
			'number'   => 10
		) );
		foreach ( $users as $user ) {
			$bio = get_user_meta( $user->ID, 'description', true );
			if ( ! empty( $bio ) ) {
				$has_author_bio = true;
				break;
			}
		}

		if ( $has_author_bio ) {
			$checklist['eeat'] = array(
				'title'          => __( '9. EEAT Score (Author Bios)', 'ai-reach-geotracker' ),
				'status'         => 'optimized',
				'score'          => 10,
				'description'    => __( 'Found biographical profile details for administrators or editor authors.', 'ai-reach-geotracker' ),
				'recommendation' => __( 'No action required. Your publishing structure demonstrates author credibility.', 'ai-reach-geotracker' ),
				'snippet'        => ''
			);
		} else {
			$checklist['eeat'] = array(
				'title'          => __( '9. EEAT Score (Author Bios)', 'ai-reach-geotracker' ),
				'status'         => 'action',
				'score'          => 0,
				'description'    => __( 'No author biographical descriptions were found in active WordPress profiles.', 'ai-reach-geotracker' ),
				'recommendation' => __( 'Fill in the "Biographical Info" text area inside your profile under WordPress Users settings to feed entity authority.', 'ai-reach-geotracker' ),
				'snippet'        => ''
			);
		}

		return $checklist;
	}
}
