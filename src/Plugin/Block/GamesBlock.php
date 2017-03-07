<?php

/**
 * @file
 * Contains \Drupal\nbb\Plugin\Block\GamesBlock.
 */

namespace Drupal\nbb\Plugin\Block;


use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Games' block.
 *
 * @Block(
 *   id = "games_block",
 *   admin_label = @Translation("Games Block"),
 *   category = @Translation("Blocks")
 * )
 */
class GamesBlock extends BlockBase {
	protected function getClubId () {
		return (int) \Drupal::config('nbb.settings')->get('club_id');
	}
	protected function getCompetitionId () {
		$config = $this->getConfiguration();
		return ( ! empty($config['competition_id']) ? (int) $config['competition_id'] : 0);
	}
	protected function prepareMatches (array & $matches, $clubId) {
		$settings    = \Drupal::config('nbb.settings');
		$format      = $settings->get('date_format');
		$replace     = $settings->get('date_replace');
		$replaceWith = $settings->get('date_replace_with');

		foreach ($matches as & $match) {
			$match['is_home'] = ($match['thuis_club_id'] == $clubId);
			$match['is_away'] = ($match['uit_club_id'] == $clubId);

			$date = strftime("%a %e %b, %H:%M", strtotime($match['datum']));
			if ($replace) {
				$date = str_replace($replace, $replaceWith, $date);
			}
			$match['date'] = $date;
		};
	}

	public function build() {
		$clubId = $this->getClubId();
		$competitionId = $this->getCompetitionId();

		$url = 'http://db.basketball.nl/db/json/wedstrijd.pl?clb_ID=' . $clubId;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$data = json_decode(curl_exec($ch), true);
		curl_close($ch);

		if ( ! empty($data['wedstrijden']) && is_array($data['wedstrijden'])) {
			$matches = & $data['wedstrijden'];

			$this->prepareMatches($matches, $clubId);

			// Table
			$output = '<table class="gameschedule">';
			$output .= '<thead><tr>';
			$output .= '<th>Datum</th>';
			$output .= '<th>Thuis ploeg</th>';
			$output .= '<th>Uit ploeg</th>';
			$output .= '<th>Uitslag</th>';
			$output .= '<th>locatie</th>';
			$output .= '</tr></thead>';
			$output .= '<tbody>';

			foreach ($matches as & $match) {
				if ( ! $match['is_home'] && ! $match['is_away']) continue;

				$output .= '<tr>';
				$output .= '<td>' . $match['date'] . '</td>';
				$output .= '<td>' . ($match['is_home'] ? '<strong>' . $match['thuis_ploeg'] . '</strong>' : $match['thuis_ploeg']) . '</td>';
				$output .= '<td>' . ($match['is_away'] ? '<strong>' . $match['uit_ploeg'] . '</strong>' : $match['uit_ploeg']) . '</td>';
				$output .= '<td>' . $match['score_thuis'] . ' - ' . $match['score_uit'] . '</td>';
				$output .= '<td>' . $match['loc_naam'] . ', ' . $match['loc_plaats'] . '</td>';
				$output .= '</tr>';
			}

			$output .= '</tbody>';
			$output .= '</table>';
		} else {
			// TODO: Log error
			$output = 'Error loading team standings from NBB API.';
		}

		return [
			'#title' => 'Wedstrijden',
			'#markup' => $output,
		];
	}
}
