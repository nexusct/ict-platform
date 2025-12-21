<?php

declare(strict_types=1);

namespace ICT_Platform\Api\Controllers;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_Error;

/**
 * Weather Integration REST API Controller
 *
 * Provides weather data for project scheduling and outdoor work alerts.
 *
 * @package ICT_Platform
 * @since   2.1.0
 */
class WeatherController extends AbstractController {

	/**
	 * REST base for this controller.
	 *
	 * @var string
	 */
	protected string $rest_base = 'weather';

	/**
	 * Weather API base URL (using Open-Meteo - free, no API key required).
	 *
	 * @var string
	 */
	private string $api_base = 'https://api.open-meteo.com/v1';

	/**
	 * Register routes for weather.
	 *
	 * @return void
	 */
	public function registerRoutes(): void {
		// GET /weather/current - Get current weather
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/current',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getCurrentWeather' ),
				'permission_callback' => '__return_true',
			)
		);

		// GET /weather/forecast - Get weather forecast
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/forecast',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getForecast' ),
				'permission_callback' => '__return_true',
			)
		);

		// GET /weather/project/{id} - Get weather for project location
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/project/(?P<id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getProjectWeather' ),
				'permission_callback' => array( $this, 'canViewProjects' ),
			)
		);

		// GET /weather/alerts - Get weather alerts
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/alerts',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getAlerts' ),
				'permission_callback' => array( $this, 'canViewProjects' ),
			)
		);

		// GET /weather/work-safe - Check if weather is safe for outdoor work
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/work-safe',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'checkWorkSafety' ),
				'permission_callback' => '__return_true',
			)
		);

		// GET /weather/schedule-impact - Get weather impact on schedule
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/schedule-impact',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'getScheduleImpact' ),
				'permission_callback' => array( $this, 'canViewProjects' ),
			)
		);
	}

	/**
	 * Get current weather.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getCurrentWeather( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$latitude  = (float) $request->get_param( 'latitude' );
		$longitude = (float) $request->get_param( 'longitude' );

		if ( ! $latitude || ! $longitude ) {
			// Default to company location from settings
			$latitude  = (float) get_option( 'ict_company_latitude', 40.7128 );
			$longitude = (float) get_option( 'ict_company_longitude', -74.0060 );
		}

		$cache_key = 'ict_weather_current_' . md5( $latitude . '_' . $longitude );
		$cached    = get_transient( $cache_key );

		if ( $cached !== false ) {
			return $this->success( $cached );
		}

		$response = $this->fetchWeather( $latitude, $longitude, 'current' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Cache for 15 minutes
		set_transient( $cache_key, $response, 15 * MINUTE_IN_SECONDS );

		return $this->success( $response );
	}

	/**
	 * Get weather forecast.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getForecast( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$latitude  = (float) $request->get_param( 'latitude' );
		$longitude = (float) $request->get_param( 'longitude' );
		$days      = min( (int) ( $request->get_param( 'days' ) ?: 7 ), 16 );

		if ( ! $latitude || ! $longitude ) {
			$latitude  = (float) get_option( 'ict_company_latitude', 40.7128 );
			$longitude = (float) get_option( 'ict_company_longitude', -74.0060 );
		}

		$cache_key = 'ict_weather_forecast_' . md5( $latitude . '_' . $longitude . '_' . $days );
		$cached    = get_transient( $cache_key );

		if ( $cached !== false ) {
			return $this->success( $cached );
		}

		$response = $this->fetchWeather( $latitude, $longitude, 'forecast', $days );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Cache for 1 hour
		set_transient( $cache_key, $response, HOUR_IN_SECONDS );

		return $this->success( $response );
	}

	/**
	 * Get weather for project location.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getProjectWeather( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		$id      = (int) $request->get_param( 'id' );
		$project = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . ICT_PROJECTS_TABLE . ' WHERE id = %d', $id )
		);

		if ( ! $project ) {
			return $this->error( 'not_found', 'Project not found', 404 );
		}

		// Try to get coordinates from project
		// This assumes site_address can be geocoded or coordinates are stored
		$latitude  = null;
		$longitude = null;

		// Check if project has stored coordinates
		$project_meta = get_post_meta( $id, '_ict_project_coordinates', true );
		if ( $project_meta ) {
			$coords = explode( ',', $project_meta );
			if ( count( $coords ) === 2 ) {
				$latitude  = (float) $coords[0];
				$longitude = (float) $coords[1];
			}
		}

		// Fallback to company location
		if ( ! $latitude || ! $longitude ) {
			$latitude  = (float) get_option( 'ict_company_latitude', 40.7128 );
			$longitude = (float) get_option( 'ict_company_longitude', -74.0060 );
		}

		$current  = $this->fetchWeather( $latitude, $longitude, 'current' );
		$forecast = $this->fetchWeather( $latitude, $longitude, 'forecast', 7 );

		return $this->success(
			array(
				'project_id'   => $id,
				'project_name' => $project->project_name,
				'location'     => array(
					'latitude'  => $latitude,
					'longitude' => $longitude,
					'address'   => $project->site_address,
				),
				'current'      => is_wp_error( $current ) ? null : $current,
				'forecast'     => is_wp_error( $forecast ) ? null : $forecast,
			)
		);
	}

	/**
	 * Get weather alerts.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getAlerts( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		global $wpdb;

		// Get all active projects
		$projects = $wpdb->get_results(
			'SELECT id, project_name, site_address FROM ' . ICT_PROJECTS_TABLE . "
             WHERE status IN ('pending', 'in-progress')
             ORDER BY project_name"
		);

		$alerts    = array();
		$latitude  = (float) get_option( 'ict_company_latitude', 40.7128 );
		$longitude = (float) get_option( 'ict_company_longitude', -74.0060 );

		$forecast = $this->fetchWeather( $latitude, $longitude, 'forecast', 3 );

		if ( ! is_wp_error( $forecast ) && isset( $forecast['daily'] ) ) {
			foreach ( $forecast['daily'] as $day ) {
				$date       = $day['date'];
				$conditions = $this->analyzeConditions( $day );

				if ( ! empty( $conditions['alerts'] ) ) {
					$alerts[] = array(
						'date'       => $date,
						'alerts'     => $conditions['alerts'],
						'severity'   => $conditions['severity'],
						'work_safe'  => $conditions['work_safe'],
						'conditions' => $day,
					);
				}
			}
		}

		return $this->success(
			array(
				'alerts'       => $alerts,
				'total_alerts' => count( $alerts ),
				'generated_at' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Check if weather is safe for outdoor work.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function checkWorkSafety( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$latitude  = (float) $request->get_param( 'latitude' );
		$longitude = (float) $request->get_param( 'longitude' );

		if ( ! $latitude || ! $longitude ) {
			$latitude  = (float) get_option( 'ict_company_latitude', 40.7128 );
			$longitude = (float) get_option( 'ict_company_longitude', -74.0060 );
		}

		$current = $this->fetchWeather( $latitude, $longitude, 'current' );

		if ( is_wp_error( $current ) ) {
			return $current;
		}

		$safety = $this->assessWorkSafety( $current );

		return $this->success(
			array(
				'location'           => array(
					'latitude'  => $latitude,
					'longitude' => $longitude,
				),
				'current_conditions' => $current,
				'safety_assessment'  => $safety,
				'checked_at'         => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Get weather impact on schedule.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function getScheduleImpact( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$latitude  = (float) get_option( 'ict_company_latitude', 40.7128 );
		$longitude = (float) get_option( 'ict_company_longitude', -74.0060 );
		$days      = 7;

		$forecast = $this->fetchWeather( $latitude, $longitude, 'forecast', $days );

		if ( is_wp_error( $forecast ) ) {
			return $forecast;
		}

		$impact        = array();
		$work_days     = 0;
		$affected_days = 0;

		if ( isset( $forecast['daily'] ) ) {
			foreach ( $forecast['daily'] as $day ) {
				$date        = $day['date'];
				$day_of_week = date( 'N', strtotime( $date ) );

				// Skip weekends
				if ( $day_of_week > 5 ) {
					continue;
				}

				++$work_days;
				$conditions = $this->analyzeConditions( $day );

				$impact[] = array(
					'date'            => $date,
					'day_name'        => date( 'l', strtotime( $date ) ),
					'work_safe'       => $conditions['work_safe'],
					'impact_level'    => $conditions['severity'],
					'conditions'      => $day,
					'recommendations' => $conditions['recommendations'],
				);

				if ( ! $conditions['work_safe'] || $conditions['severity'] === 'high' ) {
					++$affected_days;
				}
			}
		}

		return $this->success(
			array(
				'forecast_days'  => $days,
				'work_days'      => $work_days,
				'affected_days'  => $affected_days,
				'impact_percent' => $work_days > 0 ? round( ( $affected_days / $work_days ) * 100, 1 ) : 0,
				'daily_impact'   => $impact,
				'generated_at'   => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Fetch weather data from API.
	 *
	 * @param float  $latitude Latitude.
	 * @param float  $longitude Longitude.
	 * @param string $type Type: current or forecast.
	 * @param int    $days Number of days for forecast.
	 * @return array|WP_Error
	 */
	private function fetchWeather( float $latitude, float $longitude, string $type = 'current', int $days = 7 ): array|WP_Error {
		$params = array(
			'latitude'  => $latitude,
			'longitude' => $longitude,
			'timezone'  => wp_timezone_string(),
		);

		if ( $type === 'current' ) {
			$params['current'] = 'temperature_2m,relative_humidity_2m,apparent_temperature,precipitation,rain,showers,snowfall,weather_code,cloud_cover,wind_speed_10m,wind_gusts_10m';
			$url               = $this->api_base . '/forecast?' . http_build_query( $params );
		} else {
			$params['daily']         = 'weather_code,temperature_2m_max,temperature_2m_min,apparent_temperature_max,apparent_temperature_min,precipitation_sum,rain_sum,snowfall_sum,precipitation_probability_max,wind_speed_10m_max,wind_gusts_10m_max';
			$params['forecast_days'] = $days;
			$url                     = $this->api_base . '/forecast?' . http_build_query( $params );
		}

		$response = wp_remote_get( $url, array( 'timeout' => 10 ) );

		if ( is_wp_error( $response ) ) {
			return $this->error( 'api_error', 'Failed to fetch weather data', 500 );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! $data ) {
			return $this->error( 'parse_error', 'Failed to parse weather data', 500 );
		}

		// Format response
		if ( $type === 'current' && isset( $data['current'] ) ) {
			return $this->formatCurrentWeather( $data['current'] );
		} elseif ( $type === 'forecast' && isset( $data['daily'] ) ) {
			return $this->formatForecast( $data['daily'] );
		}

		return $this->error( 'no_data', 'No weather data available', 404 );
	}

	/**
	 * Format current weather data.
	 *
	 * @param array $data Raw weather data.
	 * @return array Formatted data.
	 */
	private function formatCurrentWeather( array $data ): array {
		return array(
			'temperature'         => $data['temperature_2m'] ?? null,
			'feels_like'          => $data['apparent_temperature'] ?? null,
			'humidity'            => $data['relative_humidity_2m'] ?? null,
			'precipitation'       => $data['precipitation'] ?? 0,
			'rain'                => $data['rain'] ?? 0,
			'snow'                => $data['snowfall'] ?? 0,
			'cloud_cover'         => $data['cloud_cover'] ?? null,
			'wind_speed'          => $data['wind_speed_10m'] ?? null,
			'wind_gusts'          => $data['wind_gusts_10m'] ?? null,
			'weather_code'        => $data['weather_code'] ?? null,
			'weather_description' => $this->getWeatherDescription( $data['weather_code'] ?? 0 ),
			'units'               => array(
				'temperature'   => '°F',
				'wind'          => 'mph',
				'precipitation' => 'mm',
			),
		);
	}

	/**
	 * Format forecast data.
	 *
	 * @param array $data Raw forecast data.
	 * @return array Formatted data.
	 */
	private function formatForecast( array $data ): array {
		$forecast = array( 'daily' => array() );

		$dates = $data['time'] ?? array();
		for ( $i = 0; $i < count( $dates ); $i++ ) {
			$forecast['daily'][] = array(
				'date'                => $dates[ $i ],
				'temp_high'           => $data['temperature_2m_max'][ $i ] ?? null,
				'temp_low'            => $data['temperature_2m_min'][ $i ] ?? null,
				'feels_like_high'     => $data['apparent_temperature_max'][ $i ] ?? null,
				'feels_like_low'      => $data['apparent_temperature_min'][ $i ] ?? null,
				'precipitation'       => $data['precipitation_sum'][ $i ] ?? 0,
				'rain'                => $data['rain_sum'][ $i ] ?? 0,
				'snow'                => $data['snowfall_sum'][ $i ] ?? 0,
				'precip_probability'  => $data['precipitation_probability_max'][ $i ] ?? 0,
				'wind_speed'          => $data['wind_speed_10m_max'][ $i ] ?? null,
				'wind_gusts'          => $data['wind_gusts_10m_max'][ $i ] ?? null,
				'weather_code'        => $data['weather_code'][ $i ] ?? null,
				'weather_description' => $this->getWeatherDescription( $data['weather_code'][ $i ] ?? 0 ),
			);
		}

		return $forecast;
	}

	/**
	 * Analyze weather conditions for alerts.
	 *
	 * @param array $day Daily weather data.
	 * @return array Analysis results.
	 */
	private function analyzeConditions( array $day ): array {
		$alerts          = array();
		$recommendations = array();
		$severity        = 'low';
		$work_safe       = true;

		// Check precipitation
		if ( ( $day['precipitation'] ?? 0 ) > 10 || ( $day['precip_probability'] ?? 0 ) > 70 ) {
			$alerts[]          = 'Heavy precipitation expected';
			$recommendations[] = 'Consider rescheduling outdoor electrical work';
			$severity          = 'high';
			$work_safe         = false;
		} elseif ( ( $day['precipitation'] ?? 0 ) > 5 || ( $day['precip_probability'] ?? 0 ) > 50 ) {
			$alerts[]          = 'Moderate precipitation possible';
			$recommendations[] = 'Have rain gear and tarps ready';
			$severity          = max( $severity, 'medium' );
		}

		// Check wind
		if ( ( $day['wind_gusts'] ?? 0 ) > 50 ) {
			$alerts[]          = 'Dangerous wind gusts expected';
			$recommendations[] = 'Avoid ladder work and elevated platforms';
			$severity          = 'high';
			$work_safe         = false;
		} elseif ( ( $day['wind_gusts'] ?? 0 ) > 30 ) {
			$alerts[]          = 'Strong winds expected';
			$recommendations[] = 'Secure loose materials';
			$severity          = max( $severity, 'medium' );
		}

		// Check temperature
		if ( ( $day['temp_high'] ?? 0 ) > 95 || ( $day['feels_like_high'] ?? 0 ) > 100 ) {
			$alerts[]          = 'Extreme heat warning';
			$recommendations[] = 'Schedule frequent breaks, provide hydration';
			$severity          = max( $severity, 'high' );
		} elseif ( ( $day['temp_low'] ?? 32 ) < 32 ) {
			$alerts[]          = 'Freezing temperatures';
			$recommendations[] = 'Be aware of ice hazards';
			$severity          = max( $severity, 'medium' );
		}

		// Check snow
		if ( ( $day['snow'] ?? 0 ) > 5 ) {
			$alerts[]          = 'Significant snowfall expected';
			$recommendations[] = 'Consider postponing outdoor work';
			$severity          = 'high';
			$work_safe         = false;
		}

		return array(
			'alerts'          => $alerts,
			'recommendations' => $recommendations,
			'severity'        => $severity,
			'work_safe'       => $work_safe,
		);
	}

	/**
	 * Assess work safety based on current conditions.
	 *
	 * @param array $current Current weather data.
	 * @return array Safety assessment.
	 */
	private function assessWorkSafety( array $current ): array {
		$safe            = true;
		$warnings        = array();
		$recommendations = array();

		// Rain check
		if ( ( $current['rain'] ?? 0 ) > 0 || ( $current['precipitation'] ?? 0 ) > 0 ) {
			$safe       = false;
			$warnings[] = 'Active precipitation - electrical work not recommended';
		}

		// Wind check
		if ( ( $current['wind_gusts'] ?? 0 ) > 40 ) {
			$safe       = false;
			$warnings[] = 'High wind gusts - elevated work dangerous';
		} elseif ( ( $current['wind_gusts'] ?? 0 ) > 25 ) {
			$warnings[] = 'Moderate winds - use caution on ladders';
		}

		// Temperature check
		if ( ( $current['temperature'] ?? 70 ) > 95 ) {
			$warnings[]        = 'Extreme heat - heat illness risk';
			$recommendations[] = 'Take breaks every 20-30 minutes';
			$recommendations[] = 'Stay hydrated';
		} elseif ( ( $current['temperature'] ?? 70 ) < 32 ) {
			$warnings[]        = 'Freezing conditions - frostbite risk';
			$recommendations[] = 'Wear appropriate cold weather gear';
		}

		return array(
			'safe_to_work'    => $safe,
			'warnings'        => $warnings,
			'recommendations' => $recommendations,
			'risk_level'      => $safe ? 'low' : ( count( $warnings ) > 2 ? 'high' : 'medium' ),
		);
	}

	/**
	 * Get weather description from WMO code.
	 *
	 * @param int $code WMO weather code.
	 * @return string Description.
	 */
	private function getWeatherDescription( int $code ): string {
		$descriptions = array(
			0  => 'Clear sky',
			1  => 'Mainly clear',
			2  => 'Partly cloudy',
			3  => 'Overcast',
			45 => 'Foggy',
			48 => 'Depositing rime fog',
			51 => 'Light drizzle',
			53 => 'Moderate drizzle',
			55 => 'Dense drizzle',
			61 => 'Slight rain',
			63 => 'Moderate rain',
			65 => 'Heavy rain',
			71 => 'Slight snow',
			73 => 'Moderate snow',
			75 => 'Heavy snow',
			80 => 'Slight rain showers',
			81 => 'Moderate rain showers',
			82 => 'Violent rain showers',
			85 => 'Slight snow showers',
			86 => 'Heavy snow showers',
			95 => 'Thunderstorm',
			96 => 'Thunderstorm with hail',
			99 => 'Thunderstorm with heavy hail',
		);

		return $descriptions[ $code ] ?? 'Unknown';
	}
}
