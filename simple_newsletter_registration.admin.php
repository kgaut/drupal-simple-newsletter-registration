<?php

function _simple_newsletter_registration_admin_form($form, &$form_state) {
  $langues = language_list();
  $form['snr_block_title'] = array(
    '#type' => 'textfield',
    '#title' => t('Newsletter registration block title'),
    '#default_value' => variable_get('snr_block_title'),
    '#required' => TRUE,
  );
  $form['snr_landing_page'] = array(
    '#type' => 'textfield',
    '#title' => t('Landing page after registration'),
    '#default_value' => variable_get('snr_landing_page'),
    '#description' => t('ie : node/28'),
    '#required' => TRUE,
    '#element_validate' => array('_simple_newsletter_registration_validate_nid'),
  );

  return system_settings_form($form);

}

function _simple_newsletter_registration_validate_nid($element, &$form_state) {
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

function _simple_newsletter_registration_listing_form($form, &$form_state) {
  if (isset($form_state['input']['submit_export']) && trim($form_state['input']['submit_export']) != '') {
    $mode = "EXPORT";
  }
  else {
    $mode = "DISPLAY";
  }

  $query = db_select('newsletter_registration', 'nr');
  $query = $query->extend('TableSort');
  $query->fields('nr',array('snr'));
  $query->orderBy('nr.snr', 'DESC');
  $result = $query->execute();
  $options = array();
  $headers = _simple_newsletter_registration_listing_form_header();
  foreach ($result as $i) {
    $options[$i->snr] = _simple_newsletter_registration_listing_form_option($i->snr);
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

function _simple_newsletter_registration_listing_form_header() {
  $header = array(
    'snr' => array('data' => t('ID')),
    'email' => array('data' => t('E-mail address')),
    'status' => array('data' => t('Status')),
    'created' => array('data' => t('Created')),
    'updated' => array('data' => t('Updated')),
    //'actions' => array('data' => t('Actions')),
  );
  return $header;
}

function _simple_newsletter_registration_listing_form_option($snr) {
  $inscrit = new InscritNewsletter(array('snr' => $snr));
  return array(
    'snr' => $inscrit->snr,
    'email' => $inscrit->email,
    'status' => $inscrit->status == 1 ? t('Inscrit') : t('Désinscrit'),
    'created' => $inscrit->created ? format_date($inscrit->created, 'short') : NULL,
    'updated' => $inscrit->updated ? format_date($inscrit->updated, 'short') : NULL,
    //'actions' => l('Edit','admin/simple_newsletter_registration/'.$inscrit->snr.'/edit'). ' ' . l('Delete','admin/simple_newsletter_registration/'.$inscrit->snr.'/delete',array('attributes'=>array('onclick'=>'return confirm("Êtes-vous sûr?")'))),
  );
}

function locations_listing_content_form_submit($form, &$form_state) {
  $form_state['rebuild'] = TRUE;
}

function _simple_newsletter_registration_edit($form, &$form_state) {
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
    $form = drupal_get_form('_simple_newsletter_registration_form',$inscrit);
    return $form;
  }

}
function _simple_newsletter_registration_delete($snr) {
  if(!is_numeric($snr) || $snr <= 0) {
    drupal_access_denied();
    exit;
  }
  $inscrit = new InscritNewsletter(array('snr'=>$snr));

  $inscrit->delete();

  drupal_set_message('L\'enregistrement à été supprimé');
  drupal_goto('admin/simple_newsletter_registration');


}
