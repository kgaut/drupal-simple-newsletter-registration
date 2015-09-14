<?php

function _silhouette_newsletter_admin_form($form, &$form_state) {
  $langues = language_list();
  $form['silhouette_newsletter_redirection_default'] = array(
    '#type' => 'textfield',
    '#title' => t('Page de redirection par default'),
    '#default_value' => variable_get('silhouette_newsletter_redirection_default'),
    '#description' => t('Le nid de la page de remerciement par default pour le formulaire d\'inscription à la newsletter'),
    '#required' => TRUE,
    '#element_validate' => array('_silhouette_newsletter_validate_nid'),
  );

  foreach ($langues as $l) {
    $form['silhouette_newsletter_redirection_' . $l->language] = array(
      '#type' => 'textfield',
      '#title' => t('Page de redirection @lang', array('@lang' => $l->native)),
      '#default_value' => variable_get('silhouette_newsletter_redirection_' . $l->language),
      //'#description' => t('la page facebook qui sera utilisé s\'il n\'y en a pas pour la langue active'),
      '#required' => FALSE,
      '#element_validate' => array('_silhouette_newsletter_validate_nid'),
    );
  }

  $form['silhouette_newsletter_texte_default'] = array(
    '#type' => 'textarea',
    '#title' => t('Texte d\'introduction par défaut'),
    '#default_value' => variable_get('silhouette_newsletter_texte_default'),
    '#description' => t('Le texte affiché en haut du formulaire d\'inscription à la newsletter'),
    '#required' => TRUE,
  );

  foreach ($langues as $l) {
    $form['silhouette_newsletter_texte_' . $l->language] = array(
      '#type' => 'textarea',
      '#title' => t('Texte d\'introduction @lang', array('@lang' => $l->native)),
      '#default_value' => variable_get('silhouette_newsletter_texte_' . $l->language),
      '#required' => FALSE,
    );

  }
  return system_settings_form($form);

}

function _silhouette_newsletter_validate_nid($element, &$form_state) {
  $value = $element['#value'];
  if (isset($value) && $value != '') {
    if (is_numeric($value)) {
      $query = db_select('node', 'n');
      $query->fields('n', array('nid'));
      $query->condition('status', 1);
      $res = $query->execute();

      $r = $res->fetchAssoc();
      if (isset($r['nid']) && $r['nid'] > 0) {
        return TRUE;
      }
    }
    form_error($element, t('Vous devez renseigner un numéro de page'));
  }
}

function _silhouette_newsletter_listing_form($form, &$form_state) {
  if (isset($form_state['input']['submit_export']) && trim($form_state['input']['submit_export']) != '') {
    $mode = "EXPORT";
  }
  else {
    $mode = "DISPLAY";
  }

  $query = db_select('sil_newsletter_registration', 'nr');
  $query = $query->extend('TableSort');
  $query->fields('nr',array('snr'));
  $query->orderBy('nr.snr', 'DESC');
  $result = $query->execute();
  $options = array();
  $headers = _silhouette_newsletter_listing_form_header();
  foreach ($result as $i) {
    $options[$i->snr] = _silhouette_newsletter_listing_form_option($i->snr);
  }

  if($mode == 'EXPORT') {
    drupal_add_http_header('Content-Type', 'text/csv; utf-8');
    drupal_add_http_header('Content-Disposition', 'attachment; filename = inscrits-newsletter.csv');
    $fh = fopen('php://output', 'w');
    $headers = array_keys($headers);
    fputcsv($fh, $headers, ';');
    foreach($options as $id => $o) {
      $row = array();
      foreach($headers as $key) {
        $row[$key] = is_string($o[$key]) ? iconv("UTF-8", "Windows-1252//TRANSLIT", $o[$key]) : $o[$key];
      }
      fputcsv($fh, $row, ';');
    }
    fclose($fh);
    exit;
  }
  else {
    $form['locations'] = array(
      '#type' => 'tableselect',
      '#header' => $headers,
      '#options' => $options,
      '#js_select' => FALSE,
      '#empty' => t('Aucune inscription'),
    );
    $form['actions']['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Afficher le tableau'),
      '#name' => 'submit_display',
    );
    $form['actions']['export'] = array(
      '#type' => 'submit',
      '#value' => t('Export CSV'),
      '#name' => 'submit_export',
    );
    return $form;
  }
}

function _silhouette_newsletter_listing_form_header() {
  $header = array(
    'snr' => array('data' => t('ID')),
    'language' => array('data' => t('Langue')),
    'civility' => array('data' => t('Civilité')),
    'firstname' => array('data' => t('Prénom')),
    'lastname' => array('data' => t('Nom de famille')),
    'email' => array('data' => t('Adresse e-mail')),
    'adherent' => array('data' => t('Adhérent ?')),
    'registration_number' => array('data' => t('Numéro d\'adhérent')),
    'club' => array('data' => t('Club')),
    'status' => array('data' => t('Status')),
    'created' => array('data' => t('Inscrit le')),
    'updated' => array('data' => t('Maj le')),
    'actions' => array('data' => t('Actions')),
  );
  return $header;
}

function _silhouette_newsletter_listing_form_option($snr) {
  $inscrit = new InscritNewsletter(array('snr' => $snr));
  $club = node_load($inscrit->club);
  return array(
    'snr' => $inscrit->snr,
    'language' => $inscrit->language,
    'civility' => $inscrit->civility,
    'firstname' => $inscrit->firstname,
    'lastname' => $inscrit->lastname,
    'email' => $inscrit->email,
    'adherent' => $inscrit->adherent == 1 ? 'oui' : "non",
    'registration_number' => $inscrit->registration_number,
    'club' => isset($club->title) ? $club->title : '',
    'status' => $inscrit->status == 1 ? t('Inscrit') : t('Désinscrit'),
    'created' => $inscrit->created ? format_date($inscrit->created, 'short') : NULL,
    'updated' => $inscrit->updated ? format_date($inscrit->updated, 'short') : NULL,
    'actions' => l('Modifier','admin/silhouette_newsletter/'.$inscrit->snr.'/edit'). ' ' . l('Supprimer','admin/silhouette_newsletter/'.$inscrit->snr.'/delete',array('attributes'=>array('onclick'=>'return confirm("Êtes-vous sûr?")'))),
  );
}

function locations_listing_content_form_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
}

function _silhouette_newsletter_edit($form, &$form_state) {
  $snr = $form_state['build_info']['args'][0];
  if(!is_numeric($snr) || $snr <= 0) {
    drupal_access_denied();
    exit;
  }
  $inscrit = new InscritNewsletter(array('snr'=>$form_state['build_info']['args'][0]));
  if(!$inscrit || $inscrit->created == null) {
    drupal_access_denied();
    exit;
  }
  else {
    $form = drupal_get_form('_silhouette_newsletter_form',$inscrit);
    return $form;
  }

}
function _silhouette_newsletter_delete($snr) {
  if(!is_numeric($snr) || $snr <= 0) {
    drupal_access_denied();
    exit;
  }
  $inscrit = new InscritNewsletter(array('snr'=>$snr));

  $inscrit->delete();

  drupal_set_message('L\'enregistrement à été supprimé');
  drupal_goto('admin/silhouette_newsletter');


}
