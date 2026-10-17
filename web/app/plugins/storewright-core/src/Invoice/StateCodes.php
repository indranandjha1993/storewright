<?php

declare(strict_types=1);

namespace Storewright\Core\Invoice;

final class StateCodes {

	private const CODES = [
		'JK' => '01',
		'HP' => '02',
		'PB' => '03',
		'CH' => '04',
		'UK' => '05',
		'HR' => '06',
		'DL' => '07',
		'RJ' => '08',
		'UP' => '09',
		'BR' => '10',
		'SK' => '11',
		'AR' => '12',
		'NL' => '13',
		'MN' => '14',
		'MZ' => '15',
		'TR' => '16',
		'ML' => '17',
		'AS' => '18',
		'WB' => '19',
		'JH' => '20',
		'OD' => '21',
		'CT' => '22',
		'MP' => '23',
		'GJ' => '24',
		'DD' => '26',
		'DN' => '26',
		'DH' => '26',
		'MH' => '27',
		'KA' => '29',
		'GA' => '30',
		'LD' => '31',
		'KL' => '32',
		'TN' => '33',
		'PY' => '34',
		'AN' => '35',
		'TS' => '36',
		'AP' => '37',
		'LA' => '38',
	];

	public static function gst( string $state ): string {
		return self::CODES[ strtoupper( $state ) ] ?? '';
	}
}
