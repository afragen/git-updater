<?php
	/**
	 * @package     Freemius
	 * @copyright   Copyright (c) 2016, Freemius, Inc.
	 * @license     https://www.gnu.org/licenses/gpl-3.0.html GNU General Public License Version 3
	 * @since       1.2.0
	 */

	if ( ! defined( 'ABSPATH' ) ) {
		exit;
	}

	/**
	 * @var array $VARS
	 * @var Freemius $fs
	 */
	$fs = freemius( $VARS['id'] );

	$slug = $fs->get_slug();

	$edit_text   = fs_text_x_inline( 'Edit', 'verb', 'edit', $slug );
	$update_text = fs_text_x_inline( 'Update', 'verb', 'update', $slug );

	$billing     = $fs->_fetch_billing();
	$has_billing = ( $billing instanceof FS_Billing );
	if ( ! $has_billing ) {
		$billing = new FS_Billing();
	}
?>
<!-- Billing -->
<div class="postbox">
	<div id="fs_billing">
		<h3><span class="dashicons dashicons-portfolio"></span> <?php fs_esc_html_echo_inline( 'Billing', 'billing', $slug ) ?></h3>
		<table id="fs_billing_address"<?php if ( $has_billing ) {
			echo ' class="fs-read-mode"';
		} ?>>
			<tr>
				<td><label><span><?php fs_esc_html_echo_inline( 'Business name', 'business-name', $slug ) ?>:</span> <input id="business_name" value="<?php echo $billing->business_name ?>" placeholder="<?php fs_esc_attr_echo_inline( 'Business name', 'business-name', $slug ) ?>"></label></td>
				<td><label><span><?php fs_esc_html_echo_inline( 'Tax / VAT ID', 'tax-vat-id', $slug ) ?>:</span> <input id="tax_id" value="<?php echo $billing->tax_id ?>" placeholder="<?php fs_esc_attr_echo_inline( 'Tax / VAT ID', 'tax-vat-id', $slug ) ?>"></label></td>
			</tr>
			<tr>
				<td><label><span><?php printf( fs_esc_html_inline( 'Address Line %d', 'address-line-n', $slug ), 1 ) ?>:</span> <input id="address_street" value="<?php echo $billing->address_street ?>" placeholder="<?php printf( fs_esc_attr_inline( 'Address Line %d', 'address-line-n', $slug ), 1 ) ?>"></label></td>
				<td><label><span><?php printf( fs_esc_html_inline( 'Address Line %d', 'address-line-n', $slug ), 2 ) ?>:</span> <input id="address_apt" value="<?php echo $billing->address_apt ?>" placeholder="<?php printf( fs_esc_attr_inline( 'Address Line %d', 'address-line-n', $slug ), 2 ) ?>"></label></td>
			</tr>
			<tr>
				<td><label><span><?php fs_esc_html_echo_inline( 'City', 'city', $slug ) ?> / <?php fs_esc_html_echo_inline( 'Town', 'town', $slug ) ?>:</span> <input id="address_city" value="<?php echo $billing->address_city ?>" placeholder="<?php fs_esc_attr_echo_inline( 'City', 'city', $slug ) ?> / <?php fs_esc_attr_echo_inline( 'Town', 'town', $slug ) ?>"></label></td>
				<td><label><span><?php fs_esc_html_echo_inline( 'ZIP / Postal Code', 'zip-postal-code', $slug ) ?>:</span> <input id="address_zip" value="<?php echo $billing->address_zip ?>" placeholder="<?php fs_esc_attr_echo_inline( 'ZIP / Postal Code', 'zip-postal-code', $slug ) ?>"></label></td>
			</tr>
			<tr>
				<?php $countries = array(
					'AF' => 'Afghanistan',
					'AX' => 'Aland Islands',
					'AL' => 'Albania',
					'DZ' => 'Algeria',
					'AS' => 'American Samoa',
					'AD' => 'Andorra',
					'AO' => 'Angola',
					'AI' => 'Anguilla',
					'AQ' => 'Antarctica',
					'AG' => 'Antigua and Barbuda',
					'AR' => 'Argentina',
					'AM' => 'Armenia',
					'AW' => 'Aruba',
					'AU' => 'Australia',
					'AT' => 'Austria',
					'AZ' => 'Azerbaijan',
					'BS' => 'Bahamas',
					'BH' => 'Bahrain',
					'BD' => 'Bangladesh',
					'BB' => 'Barbados',
					'BY' => 'Belarus',
					'BE' => 'Belgium',
					'BZ' => 'Belize',
					'BJ' => 'Benin',
					'BM' => 'Bermuda',
					'BT' => 'Bhutan',
					'BO' => 'Bolivia',
					'BQ' => 'Bonaire, Saint Eustatius and Saba',
					'BA' => 'Bosnia and Herzegovina',
					'BW' => 'Botswana',
					'BV' => 'Bouvet Island',
					'BR' => 'Brazil',
					'IO' => 'British Indian Ocean Territory',
					'VG' => 'British Virgin Islands',
					'BN' => 'Brunei',
					'BG' => 'Bulgaria',
					'BF' => 'Burkina Faso',
					'BI' => 'Burundi',
					'KH' => 'Cambodia',
					'CM' => 'Cameroon',
					'CA' => 'Canada',
					'CV' => 'Cape Verde',
					'KY' => 'Cayman Islands',
					'CF' => 'Central African Republic',
					'TD' => 'Chad',
					'CL' => 'Chile',
					'CN' => 'China',
					'CX' => 'Christmas Island',
					'CC' => 'Cocos Islands',
					'CO' => 'Colombia',
					'KM' => 'Comoros',
					'CK' => 'Cook Islands',
					'CR' => 'Costa Rica',
					'HR' => 'Croatia',
					'CU' => 'Cuba',
					'CW' => 'Curacao',
					'CY' => 'Cyprus',
					'CZ' => 'Czech Republic',
					'CD' => 'Democratic Republic of the Congo',
					'DK' => 'Denmark',
					'DJ' => 'Djibouti',
					'DM' => 'Dominica',
					'DO' => 'Dominican Republic',
					'TL' => 'East Timor',
					'EC' => 'Ecuador',
					'EG' => 'Egypt',
					'SV' => 'El Salvador',
					'GQ' => 'Equatorial Guinea',
					'ER' => 'Eritrea',
					'EE' => 'Estonia',
					'ET' => 'Ethiopia',
					'FK' => 'Falkland Islands',
					'FO' => 'Faroe Islands',
					'FJ' => 'Fiji',
					'FI' => 'Finland',
					'FR' => 'France',
					'GF' => 'French Guiana',
					'PF' => 'French Polynesia',
					'TF' => 'French Southern Territories',
					'GA' => 'Gabon',
					'GM' => 'Gambia',
					'GE' => 'Georgia',
					'DE' => 'Germany',
					'GH' => 'Ghana',
					'GI' => 'Gibraltar',
					'GR' => 'Greece',
					'GL' => 'Greenland',
					'GD' => 'Grenada',
					'GP' => 'Guadeloupe',
					'GU' => 'Guam',
					'GT' => 'Guatemala',
					'GG' => 'Guernsey',
					'GN' => 'Guinea',
					'GW' => 'Guinea-Bissau',
					'GY' => 'Guyana',
					'HT' => 'Haiti',
					'HM' => 'Heard Island and McDonald Islands',
					'HN' => 'Honduras',
					'HK' => 'Hong Kong',
					'HU' => 'Hungary',
					'IS' => 'Iceland',
					'IN' => 'India',
					'ID' => 'Indonesia',
					'IR' => 'Iran',
					'IQ' => 'Iraq',
					'IE' => 'Ireland',
					'IM' => 'Isle of Man',
					'IL' => 'Israel',
					'IT' => 'Italy',
					'CI' => 'Ivory Coast',
					'JM' => 'Jamaica',
					'JP' => 'Japan',
					'JE' => 'Jersey',
					'JO' => 'Jordan',
					'KZ' => 'Kazakhstan',
					'KE' => 'Kenya',
					'KI' => 'Kiribati',
					'XK' => 'Kosovo',
					'KW' => 'Kuwait',
					'KG' => 'Kyrgyzstan',
					'LA' => 'Laos',
					'LV' => 'Latvia',
					'LB' => 'Lebanon',
					'LS' => 'Lesotho',
					'LR' => 'Liberia',
					'LY' => 'Libya',
					'LI' => 'Liechtenstein',
					'LT' => 'Lithuania',
					'LU' => 'Luxembourg',
					'MO' => 'Macao',
					'MK' => 'Macedonia',
					'MG' => 'Madagascar',
					'MW' => 'Malawi',
					'MY' => 'Malaysia',
					'MV' => 'Maldives',
					'ML' => 'Mali',
					'MT' => 'Malta',
					'MH' => 'Marshall Islands',
					'MQ' => 'Martinique',
					'MR' => 'Mauritania',
					'MU' => 'Mauritius',
					'YT' => 'Mayotte',
					'MX' => 'Mexico',
					'FM' => 'Micronesia',
					'MD' => 'Moldova',
					'MC' => 'Monaco',
					'MN' => 'Mongolia',
					'ME' => 'Montenegro',
					'MS' => 'Montserrat',
					'MA' => 'Morocco',
					'MZ' => 'Mozambique',
					'MM' => 'Myanmar',
					'NA' => 'Namibia',
					'NR' => 'Nauru',
					'NP' => 'Nepal',
					'NL' => 'Netherlands',
					'NC' => 'New Caledonia',
					'NZ' => 'New Zealand',
					'NI' => 'Nicaragua',
					'NE' => 'Niger',
					'NG' => 'Nigeria',
					'NU' => 'Niue',
					'NF' => 'Norfolk Island',
					'KP' => 'North Korea',
					'MP' => 'Northern Mariana Islands',
					'NO' => 'Norway',
					'OM' => 'Oman',
					'PK' => 'Pakistan',
					'PW' => 'Palau',
					'PS' => 'Palestinian Territory',
					'PA' => 'Panama',
					'PG' => 'Papua New Guinea',
					'PY' => 'Paraguay',
					'PE' => 'Peru',
					'PH' => 'Philippines',
					'PN' => 'Pitcairn',
					'PL' => 'Poland',
					'PT' => 'Portugal',
					'PR' => 'Puerto Rico',
					'QA' => 'Qatar',
					'CG' => 'Republic of the Congo',
					'RE' => 'Reunion',
					'RO' => 'Romania',
					'RU' => 'Russia',
					'RW' => 'Rwanda',
					'BL' => 'Saint Barthelemy',
					'SH' => 'Saint Helena',
					'KN' => 'Saint Kitts and Nevis',
					'LC' => 'Saint Lucia',
					'MF' => 'Saint Martin',
					'PM' => 'Saint Pierre and Miquelon',
					'VC' => 'Saint Vincent and the Grenadines',
					'WS' => 'Samoa',
					'SM' => 'San Marino',
					'ST' => 'Sao Tome and Principe',
					'SA' => 'Saudi Arabia',
					'SN' => 'Senegal',
					'RS' => 'Serbia',
					'SC' => 'Seychelles',
					'SL' => 'Sierra Leone',
					'SG' => 'Singapore',
					'SX' => 'Sint Maarten',
					'SK' => 'Slovakia',
					'SI' => 'Slovenia',
					'SB' => 'Solomon Islands',
					'SO' => 'Somalia',
					'ZA' => 'South Africa',
					'GS' => 'South Georgia and the South Sandwich Islands',
					'KR' => 'South Korea',
					'SS' => 'South Sudan',
					'ES' => 'Spain',
					'LK' => 'Sri Lanka',
					'SD' => 'Sudan',
					'SR' => 'Suriname',
					'SJ' => 'Svalbard and Jan Mayen',
					'SZ' => 'Swaziland',
					'SE' => 'Sweden',
					'CH' => 'Switzerland',
					'SY' => 'Syria',
					'TW' => 'Taiwan',
					'TJ' => 'Tajikistan',
					'TZ' => 'Tanzania',
					'TH' => 'Thailand',
					'TG' => 'Togo',
					'TK' => 'Tokelau',
					'TO' => 'Tonga',
					'TT' => 'Trinidad and Tobago',
					'TN' => 'Tunisia',
					'TR' => 'Turkey',
					'TM' => 'Turkmenistan',
					'TC' => 'Turks and Caicos Islands',
					'TV' => 'Tuvalu',
					'VI' => 'U.S. Virgin Islands',
					'UG' => 'Uganda',
					'UA' => 'Ukraine',
					'AE' => 'United Arab Emirates',
					'GB' => 'United Kingdom',
					'US' => 'United States',
					'UM' => 'United States Minor Outlying Islands',
					'UY' => 'Uruguay',
					'UZ' => 'Uzbekistan',
					'VU' => 'Vanuatu',
					'VA' => 'Vatican',
					'VE' => 'Venezuela',
					'VN' => 'Vietnam',
					'WF' => 'Wallis and Futuna',
					'EH' => 'Western Sahara',
					'YE' => 'Yemen',
					'ZM' => 'Zambia',
					'ZW' => 'Zimbabwe',
				) ?>
				<td><label><span><?php fs_esc_html_echo_inline( 'Country', 'country', $slug ) ?>:</span> <select id="address_country_code">
							<?php if ( empty( $billing->address_country_code ) ) : ?>
								<option value="" selected><?php fs_esc_html_echo_inline( 'Select Country', 'select-country', $slug ) ?></option>
							<?php endif ?>
							<?php foreach ( $countries as $code => $country ) : ?>
								<option
									value="<?php echo $code ?>" <?php selected( $billing->address_country_code, $code ) ?>><?php echo $country ?></option>
							<?php endforeach ?>
						</select></label></td>
				<td><label><span><?php fs_esc_html_echo_inline( 'State', 'state', $slug ) ?> / <?php fs_esc_html_echo_inline( 'Province', 'province', $slug ) ?>:</span>
						<input id="address_state" value="<?php echo $billing->address_state ?>" placeholder="<?php fs_esc_html_echo_inline( 'State', 'state', $slug ) ?> / <?php fs_esc_html_echo_inline( 'Province', 'province', $slug ) ?>"></label></td>
			</tr>
			<tr>
				<td colspan="2">
					<button
						class="button"><?php echo esc_html( $has_billing ?
							$edit_text :
							$update_text
						) ?></button>
				</td>
			</tr>
		</table>
	</div>
</div>
<!--/ Billing -->
<script type="text/javascript">
	(function($){
		var $billingAddress = $('#fs_billing_address'),
		    $billingInputs = $billingAddress.find('input, select');

		var setPrevValues = function () {
			$billingInputs.each(function () {
				$(this).attr('data-val', $(this).val());
			});
		};

		setPrevValues();

		var hasBillingChanged = function () {
			for (var i = 0, len = $billingInputs.length; i < len; i++){
				var $this = $($billingInputs[i]);
				if ($this.attr('data-val') !== $this.val()) {
					return true;
				}
			}

			return false;
		};

		var isEditAllFieldsMode = false;

		$billingAddress.find('.button').click(function(){
			$billingAddress.toggleClass('fs-read-mode');

			var isEditMode = !$billingAddress.hasClass('fs-read-mode');

			$(this)
				.html(isEditMode ? '<?php echo esc_js( $update_text ) ?>' : '<?php echo esc_js( $edit_text ) ?>')
				.toggleClass('button-primary');

			if (isEditMode) {
				$('#business_name').focus().select();
				isEditAllFieldsMode = true;
			} else {
				isEditAllFieldsMode = false;

				if (!hasBillingChanged())
					return;

				var billing = {};

				$billingInputs.each(function(){
					if ($(this).attr('data-val') !== $(this).val()) {
						billing[$(this).attr('id')] = $(this).val();
					}
				});

				$.ajax({
					url    : <?php echo Freemius::ajax_url() ?>,
					method : 'POST',
					data   : {
						action   : '<?php echo $fs->get_ajax_action( 'update_billing' ) ?>',
						security : '<?php echo $fs->get_ajax_security( 'update_billing' ) ?>',
						module_id: '<?php echo $fs->get_id() ?>',
						billing  : billing
					},
					success: function (resultObj) {
						if (resultObj.success) {
							setPrevValues();
						} else {
							alert(resultObj.error);
						}
					}
				});
			}
		});

		$billingInputs
		// Get into edit mode upon selection.
			.focus(function () {
				var isEditMode = !$billingAddress.hasClass('fs-read-mode');

				if (isEditMode) {
					return;
				}

				$billingAddress.toggleClass('fs-read-mode');
				$billingAddress.find('.button')
					.html('<?php echo esc_js( $update_text ) ?>')
					.toggleClass('button-primary');
			})
			// If blured after editing only one field without changes, exit edit mode.
			.blur(function () {
				if (!isEditAllFieldsMode && !hasBillingChanged()) {
					$billingAddress.toggleClass('fs-read-mode');
					$billingAddress.find('.button')
						.html('<?php echo esc_js( $edit_text ) ?>')
						.toggleClass('button-primary');
				}
			});
	})(jQuery);
</script>