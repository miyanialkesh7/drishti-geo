<?php
/**
 * Class AI_Reach_Admin
 * Handles admin menu, asset enqueuing, AJAX callbacks, and dashboard rendering.
 *
 * @package AI_Reach_GEO_Tracker
 */

declare(strict_types=1);

// Restrict direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles admin menu, asset enqueuing, AJAX callbacks, and dashboard rendering.
 */
class AI_Reach_Admin {

	/**
	 * Scanner collaborator, injectable for testing.
	 *
	 * @var AI_Reach_Scanner|null
	 */
	private ?AI_Reach_Scanner $scanner;

	/**
	 * Constructor
	 *
	 * @param AI_Reach_Scanner|null $scanner Optional scanner instance (for testing); defaults to a new one per use.
	 */
	public function __construct( ?AI_Reach_Scanner $scanner = null ) {
		$this->scanner = $scanner;

		// Admin menus & styles.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

		// Process settings form submission early (before any HTML output), so we can safely redirect afterwards.
		add_action( 'admin_init', array( $this, 'handle_settings_save' ) );

		// AJAX endpoints.
		add_action( 'wp_ajax_ai_reach_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_ai_reach_run_engine_scan', array( $this, 'ajax_run_engine_scan' ) );
		add_action( 'wp_ajax_ai_reach_save_scan_results', array( $this, 'ajax_save_scan_results' ) );
		add_action( 'wp_ajax_ai_reach_autofix_robots', array( $this, 'ajax_autofix_robots' ) );
		add_action( 'wp_ajax_ai_reach_generate_aitxt', array( $this, 'ajax_generate_aitxt' ) );
	}

	/**
	 * Resolve the scanner collaborator, constructing the default implementation on first use.
	 *
	 * Scan options (provider/key/model) can change between requests, so a fresh default instance
	 * is built per call unless one was injected via the constructor.
	 *
	 * @return AI_Reach_Scanner
	 */
	private function get_scanner(): AI_Reach_Scanner {
		return $this->scanner ?? new AI_Reach_Scanner();
	}

	/**
	 * Add Admin Menu Item
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			__( 'AI Reach Dashboard', 'ai-reach-geotracker' ),
			__( 'AI Reach', 'ai-reach-geotracker' ),
			'manage_options',
			'ai-reach',
			array( $this, 'render_dashboard' ),
			'dashicons-chart-area',
			30
		);
	}

	/**
	 * Enqueue styles and scripts on our settings page.
	 *
	 * @param string $hook Current admin page hook suffix.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( 'toplevel_page_ai-reach' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'ai-reach-style',
			AI_REACH_URL . 'admin/css/admin-style.css',
			array(),
			AI_REACH_VERSION
		);

		wp_enqueue_script(
			'ai-reach-script',
			AI_REACH_URL . 'admin/js/admin-script.js',
			array( 'jquery' ),
			AI_REACH_VERSION,
			true
		);

		// Localize values for script access.
		$provider_labels = array(
			'openrouter' => __( 'OpenRouter', 'ai-reach-geotracker' ),
			'openai'     => __( 'OpenAI', 'ai-reach-geotracker' ),
			'gemini'     => __( 'Gemini', 'ai-reach-geotracker' ),
			'perplexity' => __( 'Perplexity', 'ai-reach-geotracker' ),
			'anthropic'  => __( 'Anthropic', 'ai-reach-geotracker' ),
		);
		$active_provider = get_option( 'ai_reach_api_provider', 'openrouter' );
		wp_localize_script(
			'ai-reach-script',
			'aiReachData',
			array(
				'ajax_url'              => admin_url( 'admin-ajax.php' ),
				'nonce'                 => wp_create_nonce( 'ai_reach_nonce' ),
				'engines'               => array( 'openai', 'gemini', 'perplexity', 'claude', 'siri' ),
				'active_provider_label' => isset( $provider_labels[ $active_provider ] ) ? $provider_labels[ $active_provider ] : __( 'AI API', 'ai-reach-geotracker' ),
				'i18n'                  => array(
					'enterApiKeyFirst'      => __( 'Please enter an API Key first.', 'ai-reach-geotracker' ),
					'testing'               => __( 'Testing...', 'ai-reach-geotracker' ),
					'testConnection'        => __( 'Test Connection', 'ai-reach-geotracker' ),
					'networkError'          => __( 'Network communication error occurred.', 'ai-reach-geotracker' ),
					'robotsFixFailed'       => __( 'Robots.txt fix request failed.', 'ai-reach-geotracker' ),
					'autofixBlocker'        => __( 'Auto-Fix Blocker', 'ai-reach-geotracker' ),
					'fixing'                => __( 'Fixing...', 'ai-reach-geotracker' ),
					/* translators: %s: error message returned by the failed request. */
					'errorPrefix'           => __( 'Error: %s', 'ai-reach-geotracker' ),
					'generateFailed'        => __( 'Failed to send generate request.', 'ai-reach-geotracker' ),
					'generateAitxt'         => __( 'Generate ai.txt File', 'ai-reach-geotracker' ),
					'generating'            => __( 'Generating...', 'ai-reach-geotracker' ),
					'scanning'              => __( 'Scanning...', 'ai-reach-geotracker' ),
					/* translators: %s: active AI provider label (e.g. OpenAI, Gemini). */
					'queryingModelVia'      => __( 'Querying model via %s...', 'ai-reach-geotracker' ),
					'mentioned'             => __( '✅ Mentioned', 'ai-reach-geotracker' ),
					'missing'               => __( '❌ Missing', 'ai-reach-geotracker' ),
					'failed'                => __( '❌ Failed', 'ai-reach-geotracker' ),
					'rebuildingScore'       => __( 'Rebuilding Score...', 'ai-reach-geotracker' ),
					'doneReloading'         => __( 'Done! Reloading...', 'ai-reach-geotracker' ),
					'runAiScan'             => __( 'Run AI Scan', 'ai-reach-geotracker' ),
					/* translators: %s: error message returned by the failed request. */
					'errorSavingResults'    => __( 'Error saving results: %s', 'ai-reach-geotracker' ),
					'failedToConnectScores' => __( 'Failed to connect to the database to update scores.', 'ai-reach-geotracker' ),
					'copied'                => __( 'Copied!', 'ai-reach-geotracker' ),
					'copyFailed'            => __( 'Failed to copy. Please highlight and copy manually.', 'ai-reach-geotracker' ),
					/* translators: %s: raw error message returned by a failed engine scan. */
					'scanFailedPrefix'      => __( 'Scan failed: %s', 'ai-reach-geotracker' ),
					'scanTimedOut'          => __( 'Scan failed: Network request timed out.', 'ai-reach-geotracker' ),
					/* translators: %s: AI engine display name (e.g. OpenAI SearchGPT). */
					'transcriptModalTitle'  => __( '%s Response', 'ai-reach-geotracker' ),
				),
			)
		);
	}


	/**
	 * Check local robots.txt and search visibility configurations
	 */
	public function check_robots_txt(): array {
		$blocked = false;
		$reason  = '';

		// 1. Check WordPress setting for Search Engine Visibility
		if ( '0' === get_option( 'blog_public', '1' ) ) {
			$blocked = true;
			$reason  = __( 'WordPress "Search Engine Visibility" option is set to discourage indexing, which inserts Disallow: / to your virtual robots.txt.', 'ai-reach-geotracker' );
			return array(
				'blocked' => true,
				'reason'  => $reason,
			);
		}

		// 2. Check physical robots.txt file in root
		$robots_file = ABSPATH . 'robots.txt';
		if ( file_exists( $robots_file ) ) {
			$wp_filesystem = $this->get_filesystem();
			$content       = $wp_filesystem ? $wp_filesystem->get_contents( $robots_file ) : false;
			if ( $content ) {
				// Pattern check for Disallow: / under global or AI bot headings.
				if ( preg_match( '/User-agent:\s*\*\s*Disallow:\s*\/\s*($|\n)/i', $content ) ) {
					$blocked = true;
					$reason  = __( 'Physical robots.txt file exists and contains a global "Disallow: /" directive.', 'ai-reach-geotracker' );
				} else {
					// Check specific AI bots.
					$ai_agents = array( 'GPTBot', 'Google-Extended', 'Anthropic-ai', 'PerplexityBot' );
					foreach ( $ai_agents as $agent ) {
						if ( preg_match( '/User-agent:\s*' . preg_quote( $agent, '/' ) . '.*Disallow:\s*\/\s*($|\n)/is', $content ) ) {
							$blocked = true;
							/* translators: %s: AI crawler user-agent name, e.g. "GPTBot" */
							$reason = sprintf( __( 'Physical robots.txt explicitly blocks "%s" from crawling.', 'ai-reach-geotracker' ), $agent );
							break;
						}
					}
				}
			}
		}

		return array(
			'blocked' => $blocked,
			'reason'  => $reason,
		);
	}

	/**
	 * Auto-Fix Robots.txt issues
	 */
	private function autofix_robots_txt() {
		// 1. Check Search Engine Visibility setting
		if ( '0' === get_option( 'blog_public', '1' ) ) {
			update_option( 'blog_public', '1' );
		}

		// 2. Fix physical robots.txt if exists
		$robots_file = ABSPATH . 'robots.txt';
		if ( file_exists( $robots_file ) ) {
			$wp_filesystem = $this->get_filesystem();
			if ( ! $wp_filesystem ) {
				return new WP_Error( 'fs_unavailable', __( 'Could not access the filesystem to edit robots.txt. Please edit the file manually.', 'ai-reach-geotracker' ) );
			}
			$content = $wp_filesystem->get_contents( $robots_file );
			if ( $content ) {
				// Remove global blocking.
				$content = preg_replace( '/User-agent:\s*\*\s*Disallow:\s*\/\s*/i', "User-agent: *\nDisallow:", $content );

				// Remove specific AI bot blocks.
				$ai_agents = array( 'GPTBot', 'Google-Extended', 'Anthropic-ai', 'PerplexityBot' );
				foreach ( $ai_agents as $agent ) {
					$content = preg_replace( '/User-agent:\s*' . preg_quote( $agent, '/' ) . '\s*Disallow:\s*\/\s*/i', 'User-agent: ' . $agent . "\nAllow: /", $content );
				}

				if ( ! $wp_filesystem->put_contents( $robots_file, $content, FS_CHMOD_FILE ) ) {
					return new WP_Error( 'write_failed', __( 'Could not write to physical robots.txt file. Please check file permissions or edit manually.', 'ai-reach-geotracker' ) );
				}
			}
		}

		return true;
	}

	/**
	 * Generate ai.txt rules file in WP Root
	 */
	private function write_ai_txt() {
		$aitxt_file = ABSPATH . 'ai.txt';

		$content  = "# ai.txt - Generative Engine Optimization Rules\n";
		$content .= '# Generated by AI Reach (GEO Tracker) on ' . gmdate( 'Y-m-d H:i:s' ) . "\n\n";

		$ai_agents = array(
			'GPTBot',
			'Google-Extended',
			'Anthropic-ai',
			'PerplexityBot',
			'Claudebot',
			'Applebot-Extended',
			'cohere-ai',
			'facebookexternalhit',
		);

		foreach ( $ai_agents as $agent ) {
			$content .= 'User-agent: ' . $agent . "\n";
			$content .= "Allow: /\n\n";
		}

		$content .= '# End of AI Rules';

		$wp_filesystem = $this->get_filesystem();
		if ( ! $wp_filesystem ) {
			return new WP_Error( 'fs_unavailable', __( 'Could not access the filesystem to write ai.txt. Please check root write permissions.', 'ai-reach-geotracker' ) );
		}
		if ( ! $wp_filesystem->put_contents( $aitxt_file, $content, FS_CHMOD_FILE ) ) {
			return new WP_Error( 'write_failed', __( 'Could not write ai.txt in the WordPress root directory. Please check root write permissions.', 'ai-reach-geotracker' ) );
		}

		return true;
	}

	/**
	 * Safely initialize and return the WP_Filesystem instance.
	 *
	 * @return WP_Filesystem_Base|false
	 */
	private function get_filesystem() {
		global $wp_filesystem;

		if ( ! empty( $wp_filesystem ) ) {
			return $wp_filesystem;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( ! WP_Filesystem() || empty( $wp_filesystem ) ) {
			return false;
		}

		return $wp_filesystem;
	}

	// ======================================================================
	// AJAX Handlers
	// ======================================================================

	/**
	 * AJAX: verify an API key/model combination for the given provider.
	 */
	public function ajax_test_connection(): void {
		check_ajax_referer( 'ai_reach_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ai-reach-geotracker' ) ) );
			return;
		}

		$api_key         = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
		$posted_provider = isset( $_POST['provider'] ) ? sanitize_key( wp_unslash( $_POST['provider'] ) ) : 'openrouter';
		$model           = isset( $_POST['model'] ) ? sanitize_text_field( wp_unslash( $_POST['model'] ) ) : '';

		$allowed_providers = array( 'openrouter', 'openai', 'gemini', 'perplexity', 'anthropic' );
		$provider          = in_array( $posted_provider, $allowed_providers, true ) ? $posted_provider : 'openrouter';

		$scanner = $this->get_scanner();
		$test    = $scanner->test_connection( $api_key, $provider, $model );

		if ( is_wp_error( $test ) ) {
			wp_send_json_error( array( 'message' => $test->get_error_message() ) );
			return;
		}

		wp_send_json_success( array( 'message' => __( 'Connection verified successfully!', 'ai-reach-geotracker' ) ) );
	}

	/**
	 * AJAX: run a single-engine scan and return its result.
	 */
	public function ajax_run_engine_scan(): void {
		check_ajax_referer( 'ai_reach_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ai-reach-geotracker' ) ) );
			return;
		}

		$engine_id       = isset( $_POST['engine_id'] ) ? sanitize_key( wp_unslash( $_POST['engine_id'] ) ) : '';
		$allowed_engines = array( 'openai', 'gemini', 'perplexity', 'claude', 'siri' );

		if ( ! in_array( $engine_id, $allowed_engines, true ) ) {
			wp_send_json_error( array( 'message' => __( 'Unknown or missing engine ID.', 'ai-reach-geotracker' ) ) );
			return;
		}

		$scanner = $this->get_scanner();
		$result  = $scanner->run_scan_for_engine( $engine_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			return;
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: persist client-collected scan results and recompute the checklist/score.
	 */
	public function ajax_save_scan_results(): void {
		check_ajax_referer( 'ai_reach_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ai-reach-geotracker' ) ) );
			return;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each element is individually sanitized in the loop below.
		$results = isset( $_POST['results'] ) ? wp_unslash( $_POST['results'] ) : array();

		// Sanitize results array structure.
		$sanitized_results = array();
		if ( is_array( $results ) ) {
			foreach ( $results as $engine => $data ) {
				if ( ! is_array( $data ) ) {
					continue;
				}
				$sanitized_results[ sanitize_key( $engine ) ] = array(
					'mentioned'  => isset( $data['mentioned'] ) ? filter_var( $data['mentioned'], FILTER_VALIDATE_BOOLEAN ) : false,
					'transcript' => isset( $data['transcript'] ) ? sanitize_textarea_field( $data['transcript'] ) : '',
				);
			}
		}

		update_option( 'ai_reach_scan_results', $sanitized_results );
		update_option( 'ai_reach_last_scan_time', current_time( 'mysql' ) );

		$scanner   = $this->get_scanner();
		$checklist = $scanner->calculate_9_point_checklist( $sanitized_results );
		update_option( 'ai_reach_checklist_results', $checklist );

		// Calculate overall score (0 to 100) based on checklist.
		$total_score = 0;
		foreach ( $checklist as $pillar ) {
			$total_score += intval( $pillar['score'] ); // Each of the 9 pillars returns up to 10 points.
		}
		$overall_score = round( ( $total_score / 90 ) * 100 );

		wp_send_json_success(
			array(
				'score'     => $overall_score,
				'checklist' => $checklist,
			)
		);
	}

	/**
	 * AJAX: apply the robots.txt/search-visibility auto-fix.
	 */
	public function ajax_autofix_robots(): void {
		check_ajax_referer( 'ai_reach_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ai-reach-geotracker' ) ) );
			return;
		}

		$fix = $this->autofix_robots_txt();
		if ( is_wp_error( $fix ) ) {
			wp_send_json_error( array( 'message' => $fix->get_error_message() ) );
			return;
		}

		wp_send_json_success( array( 'message' => __( 'Robots.txt restrictions removed successfully!', 'ai-reach-geotracker' ) ) );
	}

	/**
	 * AJAX: generate the ai.txt file in the site root.
	 */
	public function ajax_generate_aitxt(): void {
		check_ajax_referer( 'ai_reach_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ai-reach-geotracker' ) ) );
			return;
		}

		$generate = $this->write_ai_txt();
		if ( is_wp_error( $generate ) ) {
			wp_send_json_error( array( 'message' => $generate->get_error_message() ) );
			return;
		}

		wp_send_json_success( array( 'message' => __( 'ai.txt file generated successfully at the root directory!', 'ai-reach-geotracker' ) ) );
	}

	// ======================================================================
	// RENDER FUNCTIONS
	// ======================================================================

	/**
	 * Handle the settings form submission on 'admin_init' (i.e. before any HTML is sent),
	 * so we can safely redirect afterwards (PRG pattern) and avoid form-resubmission on refresh.
	 */
	public function handle_settings_save(): void {
		if ( ! isset( $_POST['ai_reach_save_settings'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'ai_reach_settings_nonce' );

		$brand_name_input = isset( $_POST['brand_name'] ) ? sanitize_text_field( wp_unslash( $_POST['brand_name'] ) ) : '';
		$keywords_input   = isset( $_POST['keywords'] ) ? sanitize_text_field( wp_unslash( $_POST['keywords'] ) ) : '';
		update_option( 'ai_reach_brand_name', $brand_name_input );
		update_option( 'ai_reach_keywords', $keywords_input );

		// API Provider.
		$allowed_providers = array( 'openrouter', 'openai', 'gemini', 'perplexity', 'anthropic' );
		$posted_provider   = isset( $_POST['api_provider'] ) ? sanitize_key( wp_unslash( $_POST['api_provider'] ) ) : '';
		$provider          = in_array( $posted_provider, $allowed_providers, true )
			? $posted_provider
			: 'openrouter';
		update_option( 'ai_reach_api_provider', $provider );

		// Per-provider API keys.
		$provider_keys = array( 'openrouter', 'openai', 'gemini', 'perplexity', 'anthropic' );
		foreach ( $provider_keys as $p ) {
			$field = $p . '_key';
			if ( isset( $_POST[ $field ] ) ) {
				update_option( 'ai_reach_' . $p . '_key', sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}

		// Per-provider models.
		foreach ( $provider_keys as $p ) {
			$field = $p . '_model';
			if ( isset( $_POST[ $field ] ) ) {
				update_option( 'ai_reach_' . $p . '_model', sanitize_text_field( wp_unslash( $_POST[ $field ] ) ) );
			}
		}

		$daily = isset( $_POST['daily_scan'] ) ? '1' : '0';
		update_option( 'ai_reach_daily_scan', $daily );

		// PRG pattern: redirect back to the settings tab instead of re-rendering inline,
		// so a page refresh never triggers a "confirm form resubmission" resave.
		wp_safe_redirect(
			add_query_arg(
				array( 'ai_reach_updated' => '1' ),
				admin_url( 'admin.php?page=ai-reach' )
			) . '#tab-settings'
		);
		exit;
	}

	/**
	 * Render Admin Dashboard Page HTML.
	 */
	public function render_dashboard(): void {
		// Show a success notice after the PRG redirect from handle_settings_save().
		if ( isset( $_GET['ai_reach_updated'] ) && '1' === $_GET['ai_reach_updated'] ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully!', 'ai-reach-geotracker' ) . '</p></div>';
		}

		// Retrieve data values.
		$brand_name = get_option( 'ai_reach_brand_name', '' );
		$keywords   = get_option( 'ai_reach_keywords', '' );
		$daily_scan = get_option( 'ai_reach_daily_scan', '0' );

		// Multi-provider settings.
		$api_provider     = get_option( 'ai_reach_api_provider', 'openrouter' );
		$openrouter_key   = get_option( 'ai_reach_openrouter_key', '' );
		$openai_key       = get_option( 'ai_reach_openai_key', '' );
		$gemini_key       = get_option( 'ai_reach_gemini_key', '' );
		$perplexity_key   = get_option( 'ai_reach_perplexity_key', '' );
		$anthropic_key    = get_option( 'ai_reach_anthropic_key', '' );
		$openrouter_model = get_option( 'ai_reach_openrouter_model', 'default' );
		$openai_model     = get_option( 'ai_reach_openai_model', 'gpt-4o-mini' );
		$gemini_model     = get_option( 'ai_reach_gemini_model', 'gemini-2.5-flash' );
		$perplexity_model = get_option( 'ai_reach_perplexity_model', 'sonar' );
		$anthropic_model  = get_option( 'ai_reach_anthropic_model', 'claude-3-5-sonnet-20241022' );

		// Derive the active key for scan-readiness check.
		$active_key_option = 'ai_reach_' . sanitize_key( $api_provider ) . '_key';
		$api_key           = get_option( $active_key_option, '' );

		$scan_results = get_option( 'ai_reach_scan_results', array() );
		$checklist    = get_option( 'ai_reach_checklist_results', array() );
		$last_scan    = get_option( 'ai_reach_last_scan_time', '' );

		// Calculate Mentions count.
		$mentions_count = 0;
		if ( is_array( $scan_results ) ) {
			foreach ( $scan_results as $res ) {
				if ( isset( $res['mentioned'] ) && $res['mentioned'] ) {
					++$mentions_count;
				}
			}
		}

		// Calculate score (0 - 100).
		$overall_score = 0;
		if ( ! empty( $checklist ) ) {
			$total_score = 0;
			foreach ( $checklist as $pillar ) {
				$total_score += intval( $pillar['score'] );
			}
			$overall_score = round( ( $total_score / 90 ) * 100 );
		}

		// Check robots.txt block status.
		$robots_status = $this->check_robots_txt();
		?>

		<div id="ai-reach-dashboard" class="wrap">
			<header class="dashboard-header">
				<div class="header-main">
					<h1 class="wp-heading-inline"><span class="dashicons dashicons-rss"></span> <?php esc_html_e( 'AI Reach', 'ai-reach-geotracker' ); ?> <span class="badge"><?php esc_html_e( 'GEO Tracker', 'ai-reach-geotracker' ); ?></span></h1>
					<p class="tagline"><?php esc_html_e( 'Generative Engine Optimization & AI Visibility command center.', 'ai-reach-geotracker' ); ?></p>
				</div>
				<?php if ( ! empty( $last_scan ) ) : ?>
					<div class="last-scan-time">
						<span class="dashicons dashicons-clock"></span>
						<?php
						/* translators: %s: date and time of the last scan */
						printf( esc_html__( 'Last Scan: %s', 'ai-reach-geotracker' ), esc_html( $last_scan ) );
						?>
					</div>
				<?php endif; ?>
			</header>

			<!-- Robots.txt Warning Banner -->
			<div id="robots-warning-banner" class="alert-banner <?php echo $robots_status['blocked'] ? 'show-alert' : 'hide-alert'; ?>">
				<div class="alert-icon">
					<span class="dashicons dashicons-warning"></span>
				</div>
				<div class="alert-content">
					<h3><?php esc_html_e( 'AI Blockers Detected in robots.txt!', 'ai-reach-geotracker' ); ?></h3>
					<p id="robots-warning-text"><?php echo esc_html( $robots_status['reason'] ); ?></p>
					<button id="autofix-robots-btn" class="button button-primary action-btn-red"><?php esc_html_e( 'Auto-Fix Blocker', 'ai-reach-geotracker' ); ?></button>
				</div>
			</div>

			<!-- Tab Navigation -->
			<nav class="nav-tab-wrapper custom-tabs">
				<a href="#tab-command-center" class="nav-tab nav-tab-active" data-tab="tab-command-center"><span class="dashicons dashicons-dashboard"></span> <?php esc_html_e( 'Command Center', 'ai-reach-geotracker' ); ?></a>
				<a href="#tab-checklist" class="nav-tab" data-tab="tab-checklist"><span class="dashicons dashicons-editor-ul"></span> <?php esc_html_e( '9-Point Deep Dive', 'ai-reach-geotracker' ); ?></a>
				<a href="#tab-settings" class="nav-tab" data-tab="tab-settings"><span class="dashicons dashicons-admin-generic"></span> <?php esc_html_e( 'Configuration', 'ai-reach-geotracker' ); ?></a>
			</nav>

			<main class="dashboard-content">

				<!-- SCREEN 1: COMMAND CENTER -->
				<section id="tab-command-center" class="tab-pane active-pane">
					<div class="quick-actions-bar">
						<?php if ( empty( $api_key ) || empty( $brand_name ) || empty( $keywords ) ) : ?>
							<div class="config-needed-msg">
								<span class="dashicons dashicons-info"></span>
								<?php
								echo wp_kses(
									__( 'Please fill in settings in the <a href="#" class="go-to-settings-tab">Configuration Tab</a> before running a scan.', 'ai-reach-geotracker' ),
									array(
										'a' => array(
											'href'  => array(),
											'class' => array(),
										),
									)
								);
								?>
							</div>
							<button id="run-ai-scan-btn" class="btn-run-scan disabled-btn" disabled><span class="dashicons dashicons-arrow-right-alt2"></span> <?php esc_html_e( 'Run AI Scan', 'ai-reach-geotracker' ); ?></button>
						<?php else : ?>
							<button id="run-ai-scan-btn" class="btn-run-scan"><span class="dashicons dashicons-performance"></span> <?php esc_html_e( 'Run AI Scan', 'ai-reach-geotracker' ); ?></button>
						<?php endif; ?>
					</div>

					<div class="stats-row">
						<!-- Score card -->
						<div class="stat-card card-radial">
							<div class="card-inner">
								<h3><?php esc_html_e( 'Overall AI Score', 'ai-reach-geotracker' ); ?></h3>
								<div class="circle-container">
									<svg class="progress-ring" width="120" height="120">
										<circle class="progress-ring__circle-bg" stroke="#1e293b" stroke-width="8" fill="transparent" r="52" cx="60" cy="60" />
										<circle class="progress-ring__circle" id="overall-score-circle" stroke="#6366f1" stroke-dasharray="326.7" stroke-dashoffset="<?php echo esc_attr( 326.7 - ( 326.7 * ( $overall_score / 100 ) ) ); ?>" stroke-width="8" stroke-linecap="round" fill="transparent" r="52" cx="60" cy="60" />
									</svg>
									<div class="radial-score" id="overall-score-text"><?php echo esc_html( $overall_score ); ?>%</div>
								</div>
								<p class="stat-meta"><?php esc_html_e( 'Calculated across the 9 optimization pillars.', 'ai-reach-geotracker' ); ?></p>
							</div>
						</div>

						<!-- Mentions card -->
						<div class="stat-card card-mentions">
							<div class="card-inner">
								<h3><?php esc_html_e( 'Total Mentions', 'ai-reach-geotracker' ); ?></h3>
								<div class="mentions-display">
									<span class="mention-ratio" id="total-mentions-ratio"><?php echo esc_html( $mentions_count ); ?></span><span class="mention-total">/5</span>
								</div>
								<p class="stat-meta"><?php esc_html_e( 'Active AI Engines referencing your brand.', 'ai-reach-geotracker' ); ?></p>
							</div>
						</div>
					</div>

					<!-- Engine Matrix -->
					<h2 class="section-title"><?php esc_html_e( 'AI Search Engine Visibility Matrix', 'ai-reach-geotracker' ); ?></h2>
					<div class="engines-grid">
						<?php
						$engines_config = array(
							'openai'     => array(
								'name' => __( 'OpenAI SearchGPT', 'ai-reach-geotracker' ),
								'sub'  => 'gpt-4o',
							),
							'gemini'     => array(
								'name' => __( 'Google Gemini', 'ai-reach-geotracker' ),
								'sub'  => 'gemini-2.5-pro',
							),
							'perplexity' => array(
								'name' => __( 'Perplexity AI', 'ai-reach-geotracker' ),
								'sub'  => 'sonar-online',
							),
							'claude'     => array(
								'name' => __( 'Anthropic Claude', 'ai-reach-geotracker' ),
								'sub'  => 'claude-3.5-sonnet',
							),
							'siri'       => array(
								'name' => __( 'Apple Intelligence', 'ai-reach-geotracker' ),
								'sub'  => 'siri-llm-layer',
							),
						);

						foreach ( $engines_config as $id => $cfg ) :
							$has_data   = isset( $scan_results[ $id ] );
							$mentioned  = $has_data && $scan_results[ $id ]['mentioned'];
							$transcript = $has_data ? $scan_results[ $id ]['transcript'] : '';

							$card_status_class = 'not-scanned';
							if ( $has_data ) {
								$card_status_class = $mentioned ? 'status-mentioned' : 'status-missing';
							}
							?>
							<div class="engine-card <?php echo esc_attr( $card_status_class ); ?>" data-engine="<?php echo esc_attr( $id ); ?>" data-has-data="<?php echo $has_data ? 'yes' : 'no'; ?>">
								<div class="engine-card-header">
									<div class="engine-info">
										<h4><?php echo esc_html( $cfg['name'] ); ?></h4>
										<span class="model-id"><?php echo esc_html( $cfg['sub'] ); ?></span>
									</div>
									<div class="engine-status-tag">
										<?php if ( $has_data ) : ?>
											<?php if ( $mentioned ) : ?>
												<span class="tag tag-green"><span class="pulse-dot green-dot"></span> <?php esc_html_e( '✅ Mentioned', 'ai-reach-geotracker' ); ?></span>
											<?php else : ?>
												<span class="tag tag-red"><span class="pulse-dot red-dot"></span> <?php esc_html_e( '❌ Missing', 'ai-reach-geotracker' ); ?></span>
											<?php endif; ?>
										<?php else : ?>
											<span class="tag tag-gray"><?php esc_html_e( '⚪ Not Scanned', 'ai-reach-geotracker' ); ?></span>
										<?php endif; ?>
									</div>
								</div>
								<div class="engine-card-body">
									<p class="transcript-preview">
										<?php
										if ( $has_data ) {
											echo esc_html( wp_trim_words( $transcript, 18, '...' ) );
										} else {
											esc_html_e( 'No scan data available. Trigger a scan to analyze visibility.', 'ai-reach-geotracker' );
										}
										?>
									</p>
								</div>
								<div class="engine-card-footer">
									<button class="view-transcript-btn" <?php echo ! $has_data ? 'disabled' : ''; ?>><?php esc_html_e( 'View Full Transcript', 'ai-reach-geotracker' ); ?> <span class="dashicons dashicons-external"></span></button>
								</div>
								<!-- Hidden full transcript container -->
								<div class="hidden-transcript" style="display:none;"><?php echo esc_textarea( $transcript ); ?></div>
							</div>
						<?php endforeach; ?>
					</div>
				</section>

				<!-- SCREEN 2: 9-POINT DEEP DIVE -->
				<section id="tab-checklist" class="tab-pane">
					<h2 class="section-title"><?php esc_html_e( 'GEO Optimization pillars (9-Point Check)', 'ai-reach-geotracker' ); ?></h2>
					<p class="section-desc"><?php esc_html_e( 'Review recommendations categorized under technical, content, and authority vectors.', 'ai-reach-geotracker' ); ?></p>

					<?php if ( empty( $checklist ) ) : ?>
						<div class="empty-state-notice">
							<span class="dashicons dashicons-clipboard"></span>
							<p><?php esc_html_e( 'No checklist analysis exists yet. Please run an AI Scan from the Command Center to populate this tab.', 'ai-reach-geotracker' ); ?></p>
						</div>
					<?php else : ?>
						<div class="checklist-categories">

							<!-- CATEGORY A: TECHNICAL FOUNDATION -->
							<div class="category-block">
								<h3 class="cat-title"><span class="dashicons dashicons-admin-tools"></span> <?php esc_html_e( 'Technical Foundation', 'ai-reach-geotracker' ); ?></h3>
								<div class="accordion-list">
									<?php $this->render_checklist_item( $checklist, 'schema_alignment' ); ?>
									<?php $this->render_checklist_item( $checklist, 'freshness' ); ?>
								</div>
							</div>

							<!-- CATEGORY B: CONTENT OPTIMIZATION -->
							<div class="category-block">
								<h3 class="cat-title"><span class="dashicons dashicons-editor-alignleft"></span> <?php esc_html_e( 'Content Optimization', 'ai-reach-geotracker' ); ?></h3>
								<div class="accordion-list">
									<?php $this->render_checklist_item( $checklist, 'answerability' ); ?>
									<?php $this->render_checklist_item( $checklist, 'conversational' ); ?>
									<?php $this->render_checklist_item( $checklist, 'relevance' ); ?>
								</div>
							</div>

							<!-- CATEGORY C: TRUST & CITATIONS -->
							<div class="category-block">
								<h3 class="cat-title"><span class="dashicons dashicons-awards"></span> <?php esc_html_e( 'Off-Page Trust & Authority', 'ai-reach-geotracker' ); ?></h3>
								<div class="accordion-list">
									<?php $this->render_checklist_item( $checklist, 'brand_citation' ); ?>
									<?php $this->render_checklist_item( $checklist, 'authority_sources' ); ?>
									<?php $this->render_checklist_item( $checklist, 'sentiment' ); ?>
									<?php $this->render_checklist_item( $checklist, 'eeat' ); ?>
								</div>
							</div>

						</div>
					<?php endif; ?>
				</section>

				<!-- SCREEN 3: CONFIGURATION PAGE -->
				<section id="tab-settings" class="tab-pane">
					<div class="settings-grid-layout">

						<!-- Form Configuration -->
						<div class="settings-form-container">
							<h2 class="section-title"><?php esc_html_e( 'General Settings', 'ai-reach-geotracker' ); ?></h2>
							<form method="post" action="">
								<?php wp_nonce_field( 'ai_reach_settings_nonce' ); ?>

								<table class="form-table custom-form-table">
									<tr>
										<th scope="row"><label for="brand_name"><?php esc_html_e( 'Brand/Entity Name', 'ai-reach-geotracker' ); ?></label></th>
										<td>
											<input name="brand_name" type="text" id="brand_name" value="<?php echo esc_attr( $brand_name ); ?>" class="regular-text" placeholder="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" required />
											<p class="description"><?php esc_html_e( 'The specific brand name or entity phrase AI engines should search for (e.g. Acme Corp).', 'ai-reach-geotracker' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="keywords"><?php esc_html_e( 'Target Keywords', 'ai-reach-geotracker' ); ?></label></th>
										<td>
											<input name="keywords" type="text" id="keywords" value="<?php echo esc_attr( $keywords ); ?>" class="regular-text" placeholder="best CRM software, enterprise solutions" required />
											<p class="description"><?php esc_html_e( 'A comma-separated list of keywords that will trigger AI engine searches.', 'ai-reach-geotracker' ); ?></p>
										</td>
									</tr>
									<tr>
										<th scope="row"><label for="api_provider"><?php esc_html_e( 'API Provider', 'ai-reach-geotracker' ); ?></label></th>
										<td>
											<select name="api_provider" id="api_provider" class="regular-text">
												<option value="openrouter" <?php selected( $api_provider, 'openrouter' ); ?>><?php esc_html_e( 'OpenRouter', 'ai-reach-geotracker' ); ?></option>
												<option value="openai" <?php selected( $api_provider, 'openai' ); ?>><?php esc_html_e( 'OpenAI', 'ai-reach-geotracker' ); ?></option>
												<option value="gemini" <?php selected( $api_provider, 'gemini' ); ?>><?php esc_html_e( 'Gemini', 'ai-reach-geotracker' ); ?></option>
												<option value="perplexity" <?php selected( $api_provider, 'perplexity' ); ?>><?php esc_html_e( 'Perplexity', 'ai-reach-geotracker' ); ?></option>
												<option value="anthropic" <?php selected( $api_provider, 'anthropic' ); ?>><?php esc_html_e( 'Anthropic', 'ai-reach-geotracker' ); ?></option>
											</select>
											<p class="description"><?php esc_html_e( 'Select which AI provider will power the scans. Only the active provider key is required.', 'ai-reach-geotracker' ); ?></p>
										</td>
									</tr>

									<?php
									// Build provider config for key + model rows.
									$providers_ui = array(
										'openrouter' => array(
											'label'       => __( 'API Key', 'ai-reach-geotracker' ),
											'placeholder' => 'sk-or-v1-...',
											'key_val'     => $openrouter_key,
											'model_val'   => $openrouter_model,
											'desc'        => __( 'Enter the API key for your selected provider to enable AI scans. You can obtain this key from the provider\'s developer platform.', 'ai-reach-geotracker' ),
										),
										'openai'     => array(
											'label'       => __( 'API Key', 'ai-reach-geotracker' ),
											'placeholder' => 'sk-...',
											'key_val'     => $openai_key,
											'model_val'   => $openai_model,
											'desc'        => __( 'Enter the API key for your selected provider to enable AI scans. You can obtain this key from the provider\'s developer platform.', 'ai-reach-geotracker' ),
										),
										'gemini'     => array(
											'label'       => __( 'API Key', 'ai-reach-geotracker' ),
											'placeholder' => 'AIza...',
											'key_val'     => $gemini_key,
											'model_val'   => $gemini_model,
											'desc'        => __( 'Enter the API key for your selected provider to enable AI scans. You can obtain this key from the provider\'s developer platform.', 'ai-reach-geotracker' ),
										),
										'perplexity' => array(
											'label'       => __( 'API Key', 'ai-reach-geotracker' ),
											'placeholder' => 'pplx-...',
											'key_val'     => $perplexity_key,
											'model_val'   => $perplexity_model,
											'desc'        => __( 'Enter the API key for your selected provider to enable AI scans. You can obtain this key from the provider\'s developer platform.', 'ai-reach-geotracker' ),
										),
										'anthropic'  => array(
											'label'       => __( 'API Key', 'ai-reach-geotracker' ),
											'placeholder' => 'sk-ant-...',
											'key_val'     => $anthropic_key,
											'model_val'   => $anthropic_model,
											'desc'        => __( 'Enter the API key for your selected provider to enable AI scans. You can obtain this key from the provider\'s developer platform.', 'ai-reach-geotracker' ),
										),
									);
									foreach ( $providers_ui as $p_key => $p_cfg ) :
										$is_active   = ( $api_provider === $p_key );
										$row_style   = $is_active ? '' : 'display:none;';
										$models      = AI_Reach_Scanner::get_provider_models( $p_key );
										$field_key   = esc_attr( $p_key ) . '_key';
										$field_model = esc_attr( $p_key ) . '_model';
										?>
										<tr class="provider-key-row provider-row-<?php echo esc_attr( $p_key ); ?>" style="<?php echo esc_attr( $row_style ); ?>">
											<th scope="row"><label for="<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html( $p_cfg['label'] ); ?></label></th>
											<td>
												<div class="api-input-row">
													<input name="<?php echo esc_attr( $field_key ); ?>" type="password" id="<?php echo esc_attr( $field_key ); ?>" value="<?php echo esc_attr( $p_cfg['key_val'] ); ?>" class="regular-text provider-api-key" data-provider="<?php echo esc_attr( $p_key ); ?>" placeholder="<?php echo esc_attr( $p_cfg['placeholder'] ); ?>" />
													<button type="button" class="button button-secondary test-conn-btn" data-provider="<?php echo esc_attr( $p_key ); ?>"><?php esc_html_e( 'Test Connection', 'ai-reach-geotracker' ); ?></button>
												</div>
												<span class="conn-feedback test-conn-feedback-<?php echo esc_attr( $p_key ); ?>"></span>
												<p class="description">
													<?php
													echo wp_kses(
														$p_cfg['desc'],
														array(
															'a' => array(
																'href' => array(),
																'target' => array(),
																'rel'  => array(),
															),
														)
													);
													?>
												</p>
											</td>
										</tr>
										<tr class="provider-model-row provider-row-<?php echo esc_attr( $p_key ); ?>" style="<?php echo esc_attr( $row_style ); ?>">
											<th scope="row"><label for="<?php echo esc_attr( $field_model ); ?>"><?php esc_html_e( 'Model', 'ai-reach-geotracker' ); ?></label></th>
											<td>
												<select name="<?php echo esc_attr( $field_model ); ?>" id="<?php echo esc_attr( $field_model ); ?>" class="regular-text">
													<?php foreach ( $models as $m_val => $m_label ) : ?>
														<option value="<?php echo esc_attr( $m_val ); ?>" <?php selected( $p_cfg['model_val'], $m_val ); ?>><?php echo esc_html( $m_label ); ?></option>
													<?php endforeach; ?>
												</select>
												<p class="description"><?php esc_html_e( 'Select the AI model to use for all engine scans under this provider.', 'ai-reach-geotracker' ); ?></p>
											</td>
										</tr>
									<?php endforeach; ?>
									<tr style="display:none;">
										<td colspan="2"><span id="test-conn-feedback"></span></td>
									</tr>
									<tr>
										<th scope="row"><?php esc_html_e( 'Automated Scan Schedule', 'ai-reach-geotracker' ); ?></th>
										<td>
											<fieldset>
												<label for="daily_scan">
													<input name="daily_scan" type="checkbox" id="daily_scan" value="1" <?php checked( $daily_scan, '1' ); ?> />
													<?php esc_html_e( 'Enable Daily Auto-Scan via WP-Cron', 'ai-reach-geotracker' ); ?>
												</label>
												<p class="description"><?php esc_html_e( 'Automatically run scans every 24 hours in the background using native WordPress scheduling.', 'ai-reach-geotracker' ); ?></p>
											</fieldset>
										</td>
									</tr>
								</table>

								<div class="submit-button-row">
									<input type="submit" name="ai_reach_save_settings" id="submit" class="button button-primary action-btn-purple" value="<?php esc_html_e( 'Save Settings', 'ai-reach-geotracker' ); ?>" />
								</div>
							</form>
						</div>

						<!-- Safety Generator Panel -->
						<div class="safety-panel-container">
							<div class="safety-card">
								<div class="safety-card-header">
									<h3><span class="dashicons dashicons-shield"></span> <?php esc_html_e( 'AI Crawler Safelist', 'ai-reach-geotracker' ); ?></h3>
								</div>
								<div class="safety-card-body">
									<p><?php esc_html_e( 'To optimize your site for 2026 generative models, create an ai.txt file. This file sits alongside robots.txt in your site root and explicitly permits and instructs AI engines on how to crawl and read content indices.', 'ai-reach-geotracker' ); ?></p>

									<div class="ai-txt-preview">
										<pre><code># ai.txt sample
User-agent: GPTBot
Allow: /
User-agent: Google-Extended
Allow: /
User-agent: PerplexityBot
Allow: /</code></pre>
									</div>

									<button id="generate-aitxt-btn" class="button button-primary action-btn-purple-outline"><?php esc_html_e( 'Generate ai.txt File', 'ai-reach-geotracker' ); ?></button>
									<span id="aitxt-feedback" class="aitxt-feedback"></span>
								</div>
							</div>
						</div>

					</div>
				</section>
			</main>

			<!-- Transcript Viewer Modal -->
			<div id="transcript-modal" class="modal-overlay">
				<div class="modal-window">
					<div class="modal-header">
						<h3 id="modal-title"><?php esc_html_e( 'Engine Scan Transcript', 'ai-reach-geotracker' ); ?></h3>
						<button class="modal-close-btn">&times;</button>
					</div>
					<div class="modal-body">
						<div class="transcript-meta">
							<span id="modal-engine-badge" class="badge"></span>
						</div>
						<textarea id="modal-transcript-content" readonly></textarea>
					</div>
					<div class="modal-footer">
						<button class="button button-secondary modal-close-btn-bottom"><?php esc_html_e( 'Close', 'ai-reach-geotracker' ); ?></button>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Individual Checklist Accordion Item HTML.
	 *
	 * @param array  $checklist Full 9-pillar checklist, keyed by pillar slug.
	 * @param string $key       Pillar slug to render from the checklist.
	 */
	private function render_checklist_item( array $checklist, string $key ): void {
		if ( ! isset( $checklist[ $key ] ) ) {
			return;
		}

		$item         = $checklist[ $key ];
		$is_opt       = ( 'optimized' === $item['status'] );
		$status_icon  = $is_opt
			? '<span class="dashicons dashicons-yes-alt status-icon-green"></span>'
			: '<span class="dashicons dashicons-dismiss status-icon-red"></span>';
		$status_class = $is_opt ? 'pillar-optimized' : 'pillar-action';
		?>
		<details class="checklist-details <?php echo esc_attr( $status_class ); ?>">
			<summary class="checklist-summary">
				<div class="summary-left">
					<?php echo wp_kses( $status_icon, array( 'span' => array( 'class' => array() ) ) ); ?>
					<span class="pillar-title"><?php echo esc_html( $item['title'] ); ?></span>
				</div>
				<div class="summary-right">
					<?php if ( $is_opt ) : ?>
						<span class="badge badge-green"><?php esc_html_e( 'Optimized', 'ai-reach-geotracker' ); ?> (+10)</span>
					<?php else : ?>
						<span class="badge badge-red"><?php esc_html_e( 'Action Required', 'ai-reach-geotracker' ); ?></span>
					<?php endif; ?>
				</div>
			</summary>
			<div class="checklist-details-body">
				<p class="checklist-desc"><strong><?php esc_html_e( 'Current Status:', 'ai-reach-geotracker' ); ?></strong> <?php echo esc_html( $item['description'] ); ?></p>
				<p class="checklist-recom"><strong><?php esc_html_e( 'Action Step:', 'ai-reach-geotracker' ); ?></strong> <?php echo esc_html( $item['recommendation'] ); ?></p>

				<?php if ( ! empty( $item['snippet'] ) ) : ?>
					<div class="code-snippet-box">
						<div class="snippet-header">
							<span><?php esc_html_e( 'Recommended Implementation:', 'ai-reach-geotracker' ); ?></span>
							<button class="copy-snippet-btn" data-clipboard-target="#snippet-<?php echo esc_attr( $key ); ?>"><?php esc_html_e( 'Copy Code', 'ai-reach-geotracker' ); ?></button>
						</div>
						<pre><code id="snippet-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $item['snippet'] ); ?></code></pre>
					</div>
				<?php endif; ?>
			</div>
		</details>
		<?php
	}
}
