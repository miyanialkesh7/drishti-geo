<?php
/**
 * Constants normally defined at runtime in the plugin's main file,
 * declared here so PHPStan can resolve them during static analysis.
 *
 * @package Drishti_GEO
 */

namespace {
	if ( ! defined( 'DRISHTI_GEO_VERSION' ) ) {
		define( 'DRISHTI_GEO_VERSION', '1.0.0' );
	}
	if ( ! defined( 'DRISHTI_GEO_PATH' ) ) {
		define( 'DRISHTI_GEO_PATH', __DIR__ . '/' );
	}
	if ( ! defined( 'DRISHTI_GEO_URL' ) ) {
		define( 'DRISHTI_GEO_URL', '' );
	}
	if ( ! defined( 'DRISHTI_GEO_BASENAME' ) ) {
		define( 'DRISHTI_GEO_BASENAME', 'drishti-geo/drishti-geo.php' );
	}
}

/*
 * The WordPress AI Client library is an optional third-party dependency, provided by a
 * separate "AI Provider" plugin and only ever called behind a class_exists() guard at
 * runtime (see Drishti_GEO_Scanner::has_existing_connection()). It is not a Composer
 * dependency of this plugin, so these minimal stubs exist solely so PHPStan can resolve
 * the fluent call chain in Drishti_GEO_Scanner::ai_client_generate_text(); they are never
 * loaded outside of static analysis.
 */
namespace WordPress\AiClient {
	if ( ! class_exists( __NAMESPACE__ . '\AiClient' ) ) {
		class AiClient {
			public static function isConfigured( string $provider_id ): bool {
				return false;
			}

			public static function prompt( string $prompt ): AiClientPromptBuilder {
				return new AiClientPromptBuilder();
			}
		}

		class AiClientPromptBuilder {
			public function usingProvider( string $provider_id ): self {
				return $this;
			}

			public function usingModelPreference( string ...$model_ids ): self {
				return $this;
			}

			public function usingRequestOptions( $options ): self {
				return $this;
			}

			public function generateText(): string {
				return '';
			}
		}
	}
}

namespace WordPress\AiClient\Providers\Http\DTO {
	if ( ! class_exists( __NAMESPACE__ . '\RequestOptions' ) ) {
		class RequestOptions {
			public static function fromArray( array $options ): self {
				return new self();
			}
		}
	}
}
