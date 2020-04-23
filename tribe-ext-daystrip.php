<?php
/**
 * Plugin Name:       The Events Calendar Pro Extension: Daystrip
 * Plugin URI:        https://theeventscalendar.com/extensions/daystrip/
 * GitHub Plugin URI: https://github.com/mt-support/tribe-ext-daystrip
 * Description:       Adds a day strip at the top of the Day View
 * Version:           1.0.0
 * Extension Class:   Tribe\Extensions\Daystrip\Main
 * Author:            Modern Tribe, Inc.
 * Author URI:        http://m.tri.be/1971
 * License:           GPL version 3 or any later version
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       tribe-ext-daystrip
 *
 *     This plugin is free software: you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation, either version 3 of the License, or
 *     any later version.
 *
 *     This plugin is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *     GNU General Public License for more details.
 */

namespace Tribe\Extensions\Daystrip;

use Tribe__Autoloader;
use Tribe__Dependency;
use Tribe__Extension;

// Do not load unless Tribe Common is fully loaded and our class does not yet exist.
if (
	class_exists( 'Tribe__Extension' )
	&& ! class_exists( Main::class )
) {
	/**
	 * Extension main class, class begins loading on init() function.
	 */
	class Main extends Tribe__Extension {

		/**
		 * @var Tribe__Autoloader
		 */
		private $class_loader;

		/**
		 * @var Settings
		 */
		private $settings;

		/**
		 * Setup the Extension's properties.
		 *
		 * This always executes even if the required plugins are not present.
		 */
		public function construct() {
			$this->add_required_plugin( 'Tribe__Events__Pro__Main', '5.0' );
		}

		/**
		 * Get this plugin's options prefix.
		 *
		 * Settings_Helper will append a trailing underscore before each option.
		 *
		 * @see \Tribe\Extensions\Daystrip\Settings::set_options_prefix()
		 *
		 * @return string
		 */
		private function get_options_prefix() {
			return (string) str_replace( '-', '_', 'tribe-ext-daystrip' );
		}

		/**
		 * Get Settings instance.
		 *
		 * @return Settings
		 */
		private function get_settings() {
			if ( empty( $this->settings ) ) {
				$this->settings = new Settings( $this->get_options_prefix() );
			}

			return $this->settings;
		}

		/**
		 * Extension initialization and hooks.
		 */
		public function init() {
			// Load plugin textdomain
			// Don't forget to generate the 'languages/tribe-ext-daystrip.pot' file
			load_plugin_textdomain( 'tribe-ext-daystrip', false, basename( dirname( __FILE__ ) ) . '/languages/' );

			if ( ! $this->php_version_check() ) {
				return;
			}

			if ( ! $this->is_using_compatible_view_version() ) {
				return;
			}

			$this->class_loader();

			$this->get_settings();

			wp_enqueue_style( 'tribe-ext-daystrip',  plugin_dir_url( __FILE__ ) . 'src/resources/style.css' );
			add_action( 'tribe_template_after_include:events/day/top-bar', [ $this, 'daystrip' ], 10, 3 );
		}

		/**
		 * Check if we have a sufficient version of PHP. Admin notice if we don't and user should see it.
		 *
		 * @link https://theeventscalendar.com/knowledgebase/php-version-requirement-changes/ All extensions require PHP 5.6+.
		 *
		 * @return bool
		 */
		private function php_version_check() {
			$php_required_version = '5.6';

			if ( version_compare( PHP_VERSION, $php_required_version, '<' ) ) {
				if (
					is_admin()
					&& current_user_can( 'activate_plugins' )
				) {
					$message = '<p>';
					$message .= sprintf( __( '%s requires PHP version %s or newer to work. Please contact your website host and inquire about updating PHP.', 'tribe-ext-daystrip' ), $this->get_name(), $php_required_version );
					$message .= sprintf( ' <a href="%1$s">%1$s</a>', 'https://wordpress.org/about/requirements/' );
					$message .= '</p>';
					tribe_notice( 'tribe-ext-daystrip-php-version', $message, [ 'type' => 'error' ] );
				}

				return false;
			}

			return true;
		}

		/**
		 * Check if we have the required TEC view. Admin notice if we don't and user should see it.
		 *
		 * @return bool
		 */
		private function is_using_compatible_view_version() {
			$view_required_version = 2;

			$meets_req = true;

			// Is V2 enabled?
			if (
				function_exists( 'tribe_events_views_v2_is_enabled' )
				&& ! empty( tribe_events_views_v2_is_enabled() )
			) {
				$is_v2 = true;
			} else {
				$is_v2 = false;
			}

			// V1 compatibility check.
			if (
				1 === $view_required_version
				&& $is_v2
			) {
				$meets_req = false;
			}

			// V2 compatibility check.
			if (
				2 === $view_required_version
				&& ! $is_v2
			) {
				$meets_req = false;
			}

			// Notice, if should be shown.
			if (
				! $meets_req
				&& is_admin()
				&& current_user_can( 'activate_plugins' )
			) {
				if ( 1 === $view_required_version ) {
					$view_name = _x( 'Legacy Views', 'name of view', 'tribe-ext-daystrip' );
				} else {
					$view_name = _x( 'New (V2) Views', 'name of view', 'tribe-ext-daystrip' );
				}

				$view_name = sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'edit.php?page=tribe-common&tab=display&post_type=tribe_events' ) ),
					$view_name
				);

				// Translators: 1: Extension plugin name, 2: Name of required view, linked to Display tab.
				$message = sprintf(
					__(
						'%1$s requires the "%2$s" so this extension\'s code will not run until this requirement is met. You may want to deactivate this extension or visit its homepage to see if there are any updates available.',
						'tribe-ext-daystrip'
					),
					$this->get_name(),
					$view_name
				);

				tribe_notice(
					'tribe-ext-daystrip-view-mismatch',
					'<p>' . $message . '</p>',
					[ 'type' => 'error' ]
				);
			}

			return $meets_req;
		}

		/**
		 * Use Tribe Autoloader for all class files within this namespace in the 'src' directory.
		 *
		 * @return Tribe__Autoloader
		 */
		public function class_loader() {
			if ( empty( $this->class_loader ) ) {
				$this->class_loader = new Tribe__Autoloader;
				$this->class_loader->set_dir_separator( '\\' );
				$this->class_loader->register_prefix(
					__NAMESPACE__ . '\\',
					__DIR__ . DIRECTORY_SEPARATOR . 'src'
				);
			}

			$this->class_loader->register_autoloader();

			return $this->class_loader;
		}

		/**
		 * Getting this extension's `daystrip_number_of_days` option value.
		 *
		 * @return mixed
		 */
		public function get_daystrip_number_of_days() {
			$settings = $this->get_settings();

			return $settings->get_option( 'daystrip_number_of_days', '9' );
		}

		/**
		 * Get all of this extension's options.
		 *
		 * @return array
		 */
		public function get_all_options() {
			$settings = $this->get_settings();

			return $settings->get_all_options();
		}

		public function daystrip( $file, $name, $template ) {

			$options = $this
			$days_to_show = (int)$this->get_daystrip_number_of_days();
			$day_name_length = (int)$this->get

			// If out of range, then set to default.
			if ( $days_to_show < 3 || $days_to_show > 31 ) {
				$days_to_show = 9;
			}

			// Getting today's date
			$default_date        = $template->get( 'today' );

			// Getting selected date
			$selected_date_value = $template->get( [ 'bar', 'date' ], $default_date );

			if ( empty( $selected_date_value ) ) {
				$selected_date_value = $default_date;
			}

			// Choosing the starting date for the array and formatting it
			$starting_date = date('Y-m-d', strtotime($selected_date_value . ' -' . intdiv( $days_to_show, 2 ) . ' days'));

			// Creating and filling the array
			$days = [];
			for( $i = 0; $i < $days_to_show; $i++ ) {
				$days[] = date('Y-m-d', strtotime($starting_date . ' +' . $i . ' days'));
			}

			// Setting up the width for the boxes
			$dayWidth = 100 / count( $days );

			$html = "";
			$html .= '<div class="tribe-daystrip-container">';

			// Going through the array and setting up the strip
			foreach( $days as $day ) {
				// Making a date object
				$date = date_create( $day );
				$class = "";

				// Setting class for past, today, and future events
				if ( strtotime( $day ) < strtotime( $default_date ) ) {
					$class = 'tribe-daystrip-past';
				}
				elseif ( strtotime( $day ) == strtotime( $default_date ) ) {
					$class = 'tribe-daystrip-today';
				}
				elseif ( strtotime( $day ) > strtotime( $default_date ) ) {
					$class = 'tribe-daystrip-future';
				}
				// Setting class for selected day
				if ( strtotime( $day ) == strtotime( $selected_date_value ) ) {
					$class .= ' current';
				}

				// Putting together markup
				// Opening
				$html .= '<div class="tribe-daystrip-day '. $class . '" style="width:' . $dayWidth . '%;">';
				// URL
				$html .= '<a href="';
				$html .= tribe_events_get_url();
				$html .= $day;
				$html .= '">';
				// Name of day
				$html .= '<span class="tribe-daystrip-shortday">';
				$html .= strtoupper( substr( date_format( $date, 'l' ), 0, 3 ) );
				$html .= '</span>';
				// Date of day
				$html .= '<span class="tribe-daystrip-date">';
				$html .= date_format( $date, 'd' );
				$html .= '</span>';
				// Closing
				$html .= '</a>';
				$html .= '</div>';
			}

			echo $html;

		}

	} // end class
} // end if class_exists check
