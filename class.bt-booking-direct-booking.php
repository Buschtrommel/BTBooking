<?php
/**
 * @file
 * @brief Implements the BTBooking_Direct_Booking class.
 * @author Matthias Fehring
 * @version 1.0.0
 * @date 2016
 *
 * @copyright
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at http://mozilla.org/MPL/2.0/.
 */

defined( 'ABSPATH' ) or die (' Am Arsch die R&auml;uber! ');

/**
 * @brief Implements the @c btb_direct_booking shortcode.
 *
 * The @c btb_direct_booking shortcode shows a little box to the user where a booking can be
 * started for the specified event. The box shows the name of the event, the price, optional contact links
 * for an individual request and a dropdown menu for the event dates.
 *
 * The shortcode ouput is generated by filter hooks that are only added by the plugin if no other filter is registered
 * for the specified hook. That should make it easy to customize the ouptut.
 *
 * If the event has no times specified, it shows only a link to a contact page to start an individural request.
 *
 * The shortcode can be used without any attributes when placed on an event page or an event description page. It than
 * tries to find the event belonging to this page. So it might be a little faster if you provide an event ID.
 *
 * @par Available shortcode attributes
 * - @a id The ID of an event.
 * - @a style The style to use. Currently supported 'default', 'avada', 'custom'.
 * - @a headline Headline for the booking box.
 * - @a button_class HTML class for the booking button.
 * - @a button_text Text shown on the booking button.
 * - @a select_class HTML class for the data select element.
 * - @a select_label Label for the date selector.
 * - @a amount_input_class HTML class for the amount input element.
 * - @a amount_input_type Type of the amount input field. Default: 'number'.
 * - @a amount_input_surrounding HTML class for a div element surrounding the input element. Only applied when value set.
 * - @a amount_input_label Text label for the input of the amount.
 * - @a ind_req_label Text label for individual request contact link.
 * - @a ind_req_force Force the display of the individual request link on events with times.
 *
 * @par Example
 * @code
 [btb_direct_booking id="9" button_class="button button-small" select_label="Tickets" ind_req_force="1"]
 * @endcode
 *
 *
 * @since 1.0.0
 *
 * @todo Better implementation of events without dates or with only one date.
 */
class BTBooking_Direct_Booking {

	/**
	 * Registers the shortcode btb_direct_booking.
	 */
    public static function register_short_code() {
        add_shortcode( 'btb_direct_booking', array('BTBooking_Direct_Booking','direct_booking_func') );
    }

    /**
     * Processes the btb_direct_booking shortcode.
     *
     * If the booking process is started, it takes the POST data and creates a new booking post item
     * for further processing.
     *
     * @param array $atts See class description for explanation.
     */
    public static function direct_booking_func($atts) {

		date_default_timezone_set ( get_option('timezone_string', 'UTC') );

		if (!empty($_POST)) {

			if (!wp_verify_nonce($_POST['btb_direct_booking_nonce'], 'btb_direct_booking_data')) {
				return;
			}

			if (!isset($_POST['btb_booking_event_id']) || !isset($_POST['btb_booking_amount']) || !isset($_POST['btb_booking_time'])) {
				return;
			}

			$event_id = intval($_POST['btb_booking_event_id']);
			$time_id = intval($_POST['btb_booking_time']);
			$amount = intval($_POST['btb_booking_amount']);

			$free_slots = btb_get_time_free_slots($time_id);

			if ($free_slots >= $amount) {

				$timeprice = floatval(get_post_meta($time_id, 'btb_price', true));
				$eventprice = floatval(get_post_meta($event_id, 'btb_price', true));
				$price = $timeprice ? $timeprice : $eventprice;

				$new_booking = btb_create_booking($event_id, $time_id, array(
					'btb_slots' => $amount,
					'btb_booking_time' => time(),
					'btb_price' => $price
				));


				if ($new_booking) {

					$nonce = wp_create_nonce('btb_direct_booking_nonce');

					echo printf('<script>window.location.href = "%s?booking=%u&btbnonce=%s";</script>', get_permalink(get_option('btb_checkout_page')), $new_booking, $nonce);

				}

			} else {

				if (abs($free_slots - $amount) < $amount && abs($free_slots - $amount) > 0) {
					return '<p>' . esc_html__('There are not enough free slots for your selection.', 'bt-booking') . '</p>';
				} else {
					return '<p>' . esc_html__('This is now booked out.', 'bt-booking') . '</p>';
				}

			}

		}

        $a = shortcode_atts( array(
            'id' => '',
            'style' => get_option('btb_style', 'default'),
            'headline' => get_option('btb_shortcode_headline', __('Booking', 'bt-booking')),
            'button_class' => get_option('btb_shortcode_buttonclass', ''),
            'button_text' => get_option('btb_shortcode_buttontext', __('Book', 'bt-booking')),
            'select_class' => get_option('btb_shortcode_timeselectorclass', ''),
            'select_label' => get_option('btb_shortcode_timeselectortext', __('Dates', 'bt-booking')),
            'select_layout' => get_option('btb_shortcode_timeselectorlayout', 'dropdown'),
            'amount_input_class' => get_option('btb_shortcode_amount_class', ''),
            'amount_input_type' => 'number',
			'amount_input_surrounding' => get_option('btb_shortcode_amount_surrounding',''),
			'amount_input_label' => get_option('btb_shortcode_amount_label', __('People', 'bt-booking')),
			'ind_req_label' => get_option('btb_shortcode_ind_req_label', __('Individual request', 'bt-booking')),
			'ind_req_force' => get_option('btb_shortcode_force_ind_req', 0)
        ), $atts );

        if (empty($a['id'])) {
			$current_post_type = get_post_type();
			if ($current_post_type == 'btb_event') {
				$a['id'] = get_the_ID();
			} elseif ($current_post_type == 'page') {
				$a['id'] = btb_get_event_id_by_desc_page(get_the_ID());
			}
        }

        if (empty($a['id'])) {
			return "<p>No ID given.</p>";
        }

		$event = btb_get_event(intval($a['id']), OBJECT, 'display');


        if (!$event) {
            return '<p>'. esc_html__('Under the specified ID no event has been found.', 'bt-booking') .'</p>';
        }

        if ($event->post_type !== "btb_event") {
            return '<p>' . esc_html__('The content with the specified ID ist not an event.', 'bt-booking') . '</p>';
        }

        // requesting the event times from the database
		$times = btb_get_times($event->ID, 'display', true);

		// calculate the free slots for each time
		foreach($times as $key => $time) {
 					$time->calc_slots();
		}

		$venue = null;

		// apply the filter to generate the booking box output
		$out = apply_filters('btb_create_direct_booking_box', '', $event, $times, $venue, $a);


		// check if we should generate schema.org output, but only if PHP JSON extension is available.
        if (get_option('btb_struct_data_enabled', 0) == 1 && extension_loaded("json")) {

			if (!$venue) {
				$venue = btb_get_venue($event->venue);
			}

			if ($times && $venue) {
				$out = apply_filters('btb_create_event_schema_org', $out, $event, $times, $venue);
			}
        }

        return $out;
    }



    /**
     * Creates the schema.org event meta data ouput.
     *
     * This is hooked to the btb_create_event_schema_org filter.
     *
     * @param string $input The input string to which the output is appended.
     * @param BTB_Event $event The event for which the meta data should be generated.
     * @param array $times Array of BTB_Time objects for the event.
     * @param BTB_Venue $venue The venue the event will happen.
     * @return string
     */
    public static function event_schema_org_filter($input, BTB_Event $event, array $times, $venue) {

		$out = $input;

		foreach($times as $key => $time) {

			$out .= "\n<script type='application/ld+json'>\n";

			$schema = array(
				'@context' => 'http://schema.org',
				'@type' => $event->event_type
			);

			$schema["name"] = $event->name;

			$site = (string) get_option('btb_struct_data_orga_url', '');
			$schema["organizer"] = array('@type' => 'Organization', 'url' => $site ? $site : (string) get_option('siteurl'));

			$schema["startDate"] = $time->date_only ? date('Y-m-d', $time->start) : date('c', $time->start);
			if ($end_time > $start_time) {
				$schemaEvent["endDate"] = $time->date_only ? date('Y-m-d', $time->end) : date('c', $time->end);
			}

			$eventOffer = array('@type' => 'Offer');
			$eventOffer["price"] = number_format(($time->price ? $time->price : $event->price), 2, '.', '');
			$eventOffer["priceCurrency"] = get_option('btb_currency_code', 'EUR');
			$eventOffer["url"] = get_permalink($event);
			$eventOffer["inventoryLevel"] = $time->free_slots;
// 						$eventOffer["eligibleRegion"] = array("DE","AT","CH");

			$schema["offers"] = $eventOffer;

			$eventLocation = array('@type' => 'Place');
			$eventLocation["name"] = $venue->name;

			if ($venue->use_map_coords) {
				$geo = array('@type' => 'GeoCoordinates');
				$geo["latitude"] = $venue->latitude;
				$geo["longitude"] = $venue->longitude;
				$eventLocation["geo"] = $geo;
			}

			$elAddress = array('@type' => 'PostalAddress');
			if (!empty($venue->streetAndNumber())) $elAddress["streetAddress"] = $venue->streetAndNumber();
			if (!empty($venue->postal_code)) $elAddress["postalCode"] = $venue->postal_code;
			if (!empty($venue->region)) $elAddress["addressRegion"] = $venue->region;
			if (!empty($venue->city)) $elAddress["addressLocality"] = $venue->city;
			if (!empty($venue->country)) $elAddress["addressCountry"] = $venue->country;

			if (count($elAddress) > 1) {
				$eventLocation["address"] = $elAddress;
			}

			$schema["location"] = $eventLocation;

			$out .= json_encode($schema);

			$out .= "\n</script>\n";
		}

		return $out;
    }





	/**
	 * Creates the booking box output for the avada style.
	 *
	 * This is hooked to the btb_create_direct_booking_box filter if the Avada style has
	 * been chosen.
	 *
	 * @param string $input The input string, can be empty.
	 * @param BTB_Event $event The event for which the box should be created.
	 * @param array $times Array of BTB_Time objects for the event.
	 * @param BTB_Venue $venue The venue the event happens.
	 * @param array $atts The shortcode parameters.
	 * @return string
	 */
    public static function avada_style_filter($input, BTB_Event $event, array $times, $venue, array $atts) {

		$out  = $input;

		$out .= '<div class="btb_direct_booking_box">';

        $out .= '<div class="btb_direct_booking_header">';

        $out .= '<h4>' . $atts['headline'] . '</h4>';

        $out .= '</div>';

        $out .= '<div class="btb_direct_booking_content">';

        $out .= '<p class="btb_direct_booking_name">' . $event->name . '</p>';

        $out .= '<p class="btb_direct_booking_price">' . get_option('btb_currency', '€') . ' <span data-default-price="'. number_format_i18n($event->price, 2) .'" id="btb_direct_booking_price_value_' . $event->ID . '">' . number_format_i18n($event->price, 2) . '</span></p>';

		if ($event->price_hint) {
			$out .= '<p class="btb_direct_booking_price_hint">' . $event->price_hint . '</p>';
		}

		if (intval($atts['ind_req_force'])) {
			$out .= '<p class="btb_direct_booking_no_times"><a href="' . sprintf('%s?your-subject=%s', get_permalink(get_option('btb_contact_page')), $event->name) . '">' . $atts['ind_req_label'] . '</a></p>';
		 }

        if (empty($times)) {
			if (!intval($atts['ind_req_force'])) {
				$out .= '<p class="btb_direct_booking_no_times"><a href="' . sprintf('%s?your-subject=%s', get_permalink(get_option('btb_contact_page')), $event->post_title) . '">' . __('Individual request', 'bt-booking') . '</a></p>';
			}
        } else {

			$dateselectorlayout = get_option('btb_shortcode_timeselectorlayout', 'dropdown');

			wp_localize_script( 'btb-direct-booking-script', 'BTBooking',
                            array(
								'available' => __('available', 'bt-booking'),
								'fully_booked' => __('fully booked', 'bt-booking'),
								'radiolist' => $dateselectorlayout == 'radiolist' ? true : false
                            )
            );

            wp_enqueue_script('btb-direct-booking-script');

            $out .= '<form id="btb_direct_booking_form_' . $event->ID . '" method="post" onSubmit="return btb_direct_booking_checkForm(this)">';

            $out .= '<input type="hidden" value="' .$event->ID . '" name="btb_booking_event_id" >';

            $out .= wp_nonce_field('btb_direct_booking_data', 'btb_direct_booking_nonce', true, false);

            if ($dateselectorlayout == 'dropdown' || $dateselectorlayout == 'bigdropdown') {

				$out .= '<label class="btb_direct_booking_select_label" for="btb_direct_booking_selector_'. $event->ID .'">' . $atts['select_label'] . '</label> ';

				$out .= '<select id="btb_direct_booking_selector_' . $event->ID . '" name="btb_booking_time" onchange="btb_change_selection(this)" data-event-id="' . $event->ID . '" class="btb_direct_booking_selector '. $atts['select_class'] . '"';

				if ($dateselectorlayout == 'bigdropdown') {
					$out .= ' size="' . (count($times) + 1) . '"';
				}

				$out .= '>';

				$out .= '<option value="">' . __('Select a date', 'bt-booking') . '</option>';

				foreach($times as $key => $time) {

					$out .= '<option value="' . $time->ID . '"';

					$out .= ' data-slots="' . $time->free_slots . '"';
					$out .= ' data-price="' . number_format_i18n(($time->price ? $time->price : $event->price), 2) . '"';

					$out .= '>' . $time->post_title . '</option>';
				}

				$out .= '</select>';

            } elseif ($dateselectorlayout == 'radiolist') {

				$out .= '<fieldset id="btb_direct_booking_selector_' . $event->ID . '" class="btb_direct_booking_selector '. $atts['select_class'] . '">';

				$out .= '<legend class="btb_direct_booking_select_label">' . $atts['select_label'] . '</legend> ';

				$out .= '<div><input type="radio" id="time_0" name="btb_booking_time" value="" onclick="btb_change_radio(this)" data-event-id="' . $event->ID . '" checked><label for="time_0">' . esc_html__('Nothing selected', 'bt-booking') . '</label></div>';

				foreach($times as $key => $time) {

					$out .= '<div><input type="radio" id="time_' . $time->ID . '" name="btb_booking_time" value="' . $time->ID . '" onclick="btb_change_radio(this)"';
					$out .= ' data-event-id="' . $event->ID . '"';
					$out .= ' data-slots="' . $time->free_slots . '"';
					$out .= ' data-price="' . number_format_i18n(($time->price ? $time->price : $event->price), 2) . '">';

					$out .= '<label for="time_' . $time->ID . '">' . $time->post_title . '</label></div>';

				}

				$out .= '</fieldset>';

            } elseif ($dateselectorlayout == 'styledlist') {

            	wp_enqueue_script('jquery-ui-selectable');

            	$out .= '<ul id="btb_direct_booking_selector_' . $event->ID . '" class="btb_direct_booking_selectable btb_direct_booking_selector '. $atts['select_class'] . '">';

            	foreach($times as $key => $time) {

            		$out .= '<li>';
            		$out .= $time->post_title . '</li>';

            	}

            	$out .= '</ul>';

            }


            $out .= '<div id="btb_direct_booking_checkout_' .$event->ID . '" class="btb_direct_booking_checkout" style="display:none">';

            $out .= '<div class="btb_direct_booking_avail_clear">';

            $out .= '<span id="btb_direct_booking_free_slots_' . $event->ID . '" class="btb_direct_booking_free_slots"></span>';
            $out .= '<a onclick="btb_clear_selection(this)" style="cursor:pointer" class="btb_direct_booking_clear_selection" data-event-id="' . $event->ID . '">' . __('Clear selection', 'bt-booking') . '</a>';

            $out .= '</div>';

            $out .= '<div class="btb_direct_booking_amount_submit">';

            if (!empty($atts['amount_input_surrounding'])) {
                $out .= '<div class="'. $atts['amount_input_surrounding'] .'">';
            }

            $out .= '<input type="number" class="btb_direct_booking_amout_input '. $atts['amount_input_class'] .'"  min="1" step="1" value="1" size="4" name="btb_booking_amount" id="btb_direct_booking_amount_' . $event->ID . '" data-event-id="' . $event->ID . '">';

            if (!empty($atts['amount_input_surrounding'])) {
                $out .= '</div>';
            }

            if (!empty($atts['amount_input_label'])) {
                $out .= '<label class="btb_direct_amount_unit" for="btb_direct_booking_amount_' . $event->ID . '">';
                $out .= ' ' . $atts['amount_input_label'] . '</label>';
            }

            $out .= '<button id="btb_direct_submit_button_' . $event->ID . '" type="submit" class="btb_direct_booking_submit '. $atts['button_class'] .'">' . $atts['button_text'] . '</button>';

            $out .= '</div>';

            $out .= '</div>';

            $out .= '</form>';

        }

        $out .= '</div>';

        $out .= "</div>";

        return  $out;
    }

}
?>