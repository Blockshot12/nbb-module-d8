<?php

/**
 * @file
 * Contains \Drupal\nbb\Plugin\Block\TeamStandingsBlock.
 */

namespace Drupal\nbb\Plugin\Block;


use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Team Standings' block.
 *
 * @Block(
 *   id = "team_standings_block",
 *   admin_label = @Translation("Team Standings Block"),
 *   category = @Translation("Blocks")
 * )
 */
class TeamStandingsBlock extends BlockBase {
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

		$url = 'http://db.basketball.nl/db/json/wedstrijd.pl?cmp_ID=' . $competitionId;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$data = json_decode(curl_exec($ch), true);
		curl_close($ch);

		if ( ! empty($data['wedstrijden']) && is_array($data['wedstrijden'])) {
			$matches = & $data['wedstrijden'];

			$this->prepareMatches($matches, $clubId);

			// Table
			$output = '<table class="gameschedule--team">';
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

	public function blockForm($form, FormStateInterface $form_state) {
		$form = parent::blockForm($form, $form_state);
		$clubId = $this->getClubId();
		$competitionId = $this->getCompetitionId();

		$url = 'http://db.basketball.nl/db/json/team.pl?clb_ID=' . $clubId;
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
		$data = json_decode(curl_exec($ch), true);
		curl_close($ch);

		$options = [];

		if ( ! empty($data['teams'])) {
			foreach ($data['teams'] as & $team) {
				$options[$team['comp_id']] = $team['naam'];
			}
		}

		$form['competition_id'] = [
			'#type' => 'select',
			'#title' => 'Team',
			'#options' => & $options,
			'#default_value' => $competitionId,
		];

		return $form;
	}

	public function blockSubmit($form, FormStateInterface $form_state) {
		$competitionId = (int) $form_state->getValue('competition_id');

		$this->setConfigurationValue('competition_id', $competitionId);
	}

	public function blockValidate($form, FormStateInterface $form_state) {
		$competitionId = $form_state->getValue('competition_id');

		if ( ! is_numeric($competitionId)) {
			$form_state->setErrorByName('nbb_team_standings_settings', t('Needs to be an integer'));
		}
	}
}
