<?php
/*
 * Plugin Spipagram
 * (c) 2016 Julien Tessier
 * Distribue sous licence GPL
 *
 */

if (!defined("_ECRIRE_INC_VERSION")) return;

function spipagram_import(){
	include_spip('inc/config');

	if ($_config_rubrique = lire_config('spipagram/config/rubrique')) {
		$_rubrique = explode('|', $_config_rubrique[0]);
		$_rubrique = intval($_rubrique[1]);
	} else {
		$_rubrique = FALSE;
	}
	$_auteur = intval(lire_config('spipagram/config/auteur'));
	$_hashtag = ltrim(lire_config('spipagram/config/hashtag'), '#');
	$_statut = lire_config('spipagram/config/statut');
	$_mots = lire_config('spipagram/config/mots');
	$_token = lire_config('spipagram/config/token');

	if (!$_rubrique || !$_auteur || !$_hashtag || !$_statut || !$_token) {
		spip_log('Plugin non configuré', 'spipagram'._LOG_DEBUG);
		return FALSE;
	}


	include_spip('action/editer_article');
	include_spip('action/editer_liens');

	$_rss = 'https://websta.me/rss/tag/'.rawurlencode($_hashtag);
	
	include_spip('ecrire/iterateur/data');
	include_spip('inc/distant');

	$_distant = recuperer_page($_rss, true);

	if ($_result = json_decode(file_get_contents('https://api.instagram.com/v1/tags/kabardock/media/recent?access_token=245157508.ba4c844.638763c59c9e469fa6800ba03b786e39'))) {

		// on règle l'ID auteur pour qu’il puisse créer des article		
		if (isset($GLOBALS['visiteur_session']) && isset($GLOBALS['visiteur_session']['id_auteur'])) $old_id_auteur = $GLOBALS['visiteur_session']['id_auteur'];
		$GLOBALS['visiteur_session']['id_auteur'] = lire_meta('spipagram/config/auteur');

		$_items = $_result->data;

		foreach($_items as $_item) {

			$article = array();
			$article['titre'] = $_item->user->username;
			$article['date'] = date('Y-m-d H:i:s', $_item->created_time);
			$article['texte'] = $_item->caption->text;
			$article['id_rubrique'] = $_rubrique;
			$article['url_site'] = $_item->link;
			$article['statut'] = $_statut;

			$article_logo = $_item->images->standard_resolution->url;

			if ($row = sql_fetsel('id_article', 'spip_articles', 'id_rubrique = '.$_rubrique.' AND url_site = '.sql_quote($article['url_site']))) {
				$id_article = $row['id_article'];
				spip_log('Article trouvé pour '.$article['url_site'].' => '.$id_article, 'spipagram'._LOG_INFO);
			} else {
				$id_article = article_inserer($article['id_rubrique']);
				article_instituer($id_article, array('statut' => $article['statut']), true);
				if ($id_article) {
					spip_log('Article créé pour '.$article['url_site'].' => '.$id_article, 'spipagram'._LOG_INFO);
					spip_log('Màj des données pour l’article '.$id_article, 'spipagram'._LOG_INFO);
					sql_updateq('spip_articles', $article, "id_article = $id_article");
				} else {
					spip_log('Impossible de créer l’article pour '.$article['url_site'], 'spipagram'._LOG_CRITIQUE);
				}
				if ($_mots) {
					spip_log('Association des mots-clés pour l’article '.$id_article, 'spipagram'._LOG_INFO);
					foreach($_mots as $id_mot) objet_associer(array('mot' => $id_mot), array('article' => $id_article));
				}
			}
			if ($article_logo && !is_file('./IMG/arton'.$id_article.'.jpg')) {
				spip_log('Màj du logo pour l’article '.$id_article.' depuis '.$article_logo, 'spipagram'._LOG_INFO);
				copie_locale($article_logo, 'auto', './IMG/arton'.$id_article.'.jpg');
				if (!is_file('./IMG/arton'.$id_article.'.jpg')) {
					spip_log('Impossible de copier le logo', 'spipagram'._LOG_AVERTISSEMENT);
				}
			}
		}

		if (isset($old_id_auteur)) $GLOBALS['visiteur_session']['id_auteur'] = $old_id_auteur;
		else unset($GLOBALS['visiteur_session']['id_auteur']);

	} else {

		spip_log("Fichier $_rss non parsable", 'spipagram'._LOG_CRITIQUE);

		return FALSE;

	}


}