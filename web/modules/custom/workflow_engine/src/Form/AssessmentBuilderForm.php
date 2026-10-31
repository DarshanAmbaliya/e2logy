<?php

namespace Drupal\workflow_engine\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/** AJAX-first assessment builder. */
class AssessmentBuilderForm extends FormBase
{

  public function getFormId(): string
  {
    return 'workflow_engine_assessment_builder';
  }

  protected function assessmentId(): ?string
  {
    $id = $this->getRouteMatch()->getParameter('assessment_id');
    return is_string($id) && $id !== '' ? $id : NULL;
  }

  protected function blankDraft(): array
  {
    return [
      'steps' => [0 => ['title' => 'Step 1', 'fields' => []]],
      'conditions' => [],
      'rules' => [],
      'default_result' => 'Assessment completed',
      '_next_step' => 1, '_next_field' => 0, '_next_condition' => 0, '_next_rule' => 0,
    ];
  }

  protected function loadDraft(FormStateInterface $form_state): array
  {
    $draft = $form_state->get('assessment_draft');
    if (is_array($draft)) return $draft;
    $id = $this->assessmentId();
    if ($id) {
      $data = \Drupal::config('workflow_engine.assessment.' . $id)->getRawData();
      if (!empty($data['definition'])) {
        $draft = $data['definition'];
        $draft['_next_step'] = max(array_keys($draft['steps'] ?? [0])) + 1;
        $all = [];
        foreach ($draft['steps'] ?? [] as $s) $all = array_merge($all, array_keys($s['fields'] ?? []));
        $draft['_next_field'] = $all ? max($all) + 1 : 0;
        $draft['_next_condition'] = ($draft['conditions'] ?? []) ? max(array_keys($draft['conditions'])) + 1 : 0;
        $draft['_next_rule'] = ($draft['rules'] ?? []) ? max(array_keys($draft['rules'])) + 1 : 0;
        $form_state->set('assessment_draft', $draft);
        return $draft;
      }
    }
    $draft = $this->blankDraft();
    $form_state->set('assessment_draft', $draft);
    return $draft;
  }

  protected function optionsToText(array $options): string
  {
    $lines = [];

    foreach ($options as $value => $label) {
      $lines[] = $value . '|' . $label;
    }

    return implode("\n", $lines);
  }

  /** Copies current posted builder values into canonical FormState draft. */
  protected function syncDraft(FormStateInterface $form_state): array
  {
    $draft = $this->loadDraft($form_state);
    $values = $form_state->getValue('workspace');

    if (!is_array($values)) return $draft;

    foreach (($values['steps'] ?? []) as $sid => $step) {
      if (!isset($draft['steps'][$sid])) continue;
      $draft['steps'][$sid]['title'] = trim((string) ($step['title'] ?? $draft['steps'][$sid]['title']));
      foreach (($step['fields'] ?? []) as $fid => $field) {
        if (!isset($draft['steps'][$sid]['fields'][$fid])) continue;
        foreach (['label', 'name', 'type', 'options_text'] as $key) if (array_key_exists($key, $field)) $draft['steps'][$sid]['fields'][$fid][$key] = trim((string) $field[$key]);
        $draft['steps'][$sid]['fields'][$fid]['required'] = !empty($field['required']);
      }
    }

    foreach (($values['conditions'] ?? []) as $cid => $condition) if (isset($draft['conditions'][$cid])) $draft['conditions'][$cid] = array_merge($draft['conditions'][$cid], array_intersect_key($condition, array_flip(['source', 'operator', 'value', 'target'])));
    foreach (($values['rules'] ?? []) as $rid => $rule) if (isset($draft['rules'][$rid])) $draft['rules'][$rid] = array_merge($draft['rules'][$rid], array_intersect_key($rule, array_flip(['field', 'operator', 'value', 'result'])));
    if (isset($values['default_result'])) $draft['default_result'] = trim((string) $values['default_result']);
    $form_state->set('assessment_draft', $draft);
    return $draft;
  }

  /** Merge raw AJAX input before the form is rebuilt, so dependent selects are current. */
  protected function syncRawInput(FormStateInterface $form_state, array $draft): array
  {
    $input = $form_state->getUserInput();
    $values = $input['workspace'] ?? NULL;
    if (!is_array($values)) return $draft;
    foreach (($values['steps'] ?? []) as $sid => $step) {
      if (!isset($draft['steps'][$sid])) continue;
      if (isset($step['title'])) $draft['steps'][$sid]['title'] = trim((string) $step['title']);
      foreach (($step['fields'] ?? []) as $fid => $field) {
        if (!isset($draft['steps'][$sid]['fields'][$fid])) continue;
        foreach (['label', 'name', 'type', 'options_text'] as $key) if (array_key_exists($key, $field)) $draft['steps'][$sid]['fields'][$fid][$key] = trim((string) $field[$key]);
        $draft['steps'][$sid]['fields'][$fid]['required'] = !empty($field['required']);
        // Friendly Drupal-like behavior: derive machine name from label while blank.
        if (($draft['steps'][$sid]['fields'][$fid]['name'] ?? '') === '' && ($draft['steps'][$sid]['fields'][$fid]['label'] ?? '') !== '') {
          $machine = strtolower(Html::getId($draft['steps'][$sid]['fields'][$fid]['label']));
          $machine = str_replace('-', '_', $machine);
          $machine = preg_replace('/[^a-z0-9_]/', '', $machine);
          if ($machine !== '' && preg_match('/^[0-9]/', $machine)) $machine = 'field_' . $machine;
          $draft['steps'][$sid]['fields'][$fid]['name'] = $machine;
        }
      }
    }
    foreach (($values['conditions'] ?? []) as $cid => $row) if (isset($draft['conditions'][$cid])) $draft['conditions'][$cid] = array_merge($draft['conditions'][$cid], array_intersect_key($row, array_flip(['source', 'operator', 'value', 'target'])));
    foreach (($values['rules'] ?? []) as $rid => $row) if (isset($draft['rules'][$rid])) $draft['rules'][$rid] = array_merge($draft['rules'][$rid], array_intersect_key($row, array_flip(['field', 'operator', 'value', 'result'])));
    if (isset($values['default_result'])) $draft['default_result'] = trim((string) $values['default_result']);
    $form_state->set('assessment_draft', $draft);
    return $draft;
  }

  protected function fieldOptions(array $draft): array
  {
    $out = ['' => $this->t('- Select field -')];
    foreach ($draft['steps'] as $step) foreach ($step['fields'] as $field) {
      $name = trim((string) ($field['name'] ?? ''));
      if ($name !== '') $out[$name] = (($field['label'] ?? '') ?: $name) . ' (' . $name . ')';
    }
    return $out;
  }

  public function fieldMachineNameExists(string $name): bool
  {
    return FALSE;
  }

  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $draft = $this->loadDraft($form_state);
    $draft = $this->syncRawInput($form_state, $draft);
    $id = $this->assessmentId();
    $saved = $id ? \Drupal::config('workflow_engine.assessment.' . $id)->getRawData() : [];
    $form['#attached']['library'][] = 'workflow_engine/admin';
    $form['meta'] = ['#type' => 'details', '#title' => $this->t('Assessment details'), '#open' => TRUE, '#tree' => TRUE];
    $form['meta']['label'] = ['#type' => 'textfield', '#title' => $this->t('Name'), '#default_value' => $saved['label'] ?? '', '#required' => TRUE];
    $form['meta']['id'] = ['#type' => 'machine_name', '#title' => $this->t('Machine name'), '#default_value' => $saved['id'] ?? $id ?? '', '#machine_name' => ['exists' => [$this, 'assessmentExists'], 'source' => ['meta', 'label']], '#disabled' => (bool) $id, '#required' => TRUE];
    $form['meta']['slug'] = ['#type' => 'textfield', '#title' => $this->t('URL slug'), '#default_value' => $saved['slug'] ?? '', '#required' => TRUE, '#description' => $this->t('Example: home-loan gives /assessments/home-loan')];
    $form['meta']['published'] = ['#type' => 'checkbox', '#title' => $this->t('Published'), '#default_value' => $saved['published'] ?? FALSE];

    $form['workspace'] = ['#type' => 'container', '#tree' => TRUE, '#attributes' => ['id' => 'assessment-builder-workspace']];
    $form['workspace']['intro'] = ['#markup' => '<div class="workflow-builder__toolbar"><strong>' . $this->t('Form builder') . '</strong><span>' . $this->t('Add steps and fields. Field dropdowns below update immediately through AJAX.') . '</span></div>'];
    foreach ($draft['steps'] as $sid => $step) {
      $form['workspace']['steps'][$sid] = ['#type' => 'container', '#attributes' => ['class' => ['workflow-step']]];
      $form['workspace']['steps'][$sid]['head'] = ['#type' => 'container', '#attributes' => ['class' => ['workflow-step__head']]];
      $form['workspace']['steps'][$sid]['head']['title_markup'] = ['#markup' => '<h3>' . $this->t('Step @n', ['@n' => array_search($sid, array_keys($draft['steps'])) + 1]) . '</h3>'];
      if (count($draft['steps']) > 1) $form['workspace']['steps'][$sid]['head']['remove'] = $this->ajaxButton($this->t('Remove step'), 'removeStep', ['step' => $sid], ['button', 'button--danger']);
      $form['workspace']['steps'][$sid]['title'] = ['#type' => 'textfield', '#title' => $this->t('Step title'), '#default_value' => $step['title'], '#required' => TRUE];
      foreach ($step['fields'] as $fid => $field) {
        $form['workspace']['steps'][$sid]['fields'][$fid] = ['#type' => 'container', '#attributes' => ['class' => ['workflow-field']]];
        $form['workspace']['steps'][$sid]['fields'][$fid]['remove'] = $this->ajaxButton($this->t('Remove field'), 'removeField', ['step' => $sid, 'field' => $fid], ['button', 'button--small', 'button--danger']);
        $form['workspace']['steps'][$sid]['fields'][$fid]['label'] = [
          '#type' => 'textfield',
          '#title' => $this->t('Field label'),
          '#default_value' => $field['label'] ?? '',
          '#required' => TRUE,
          '#ajax' => [
            'callback' => '::refreshWorkspace',
            'wrapper' => 'assessment-builder-workspace',
            'event' => 'change',
          ],
        ];

        $form['workspace']['steps'][$sid]['fields'][$fid]['name'] = [
          '#type' => 'machine_name',
          '#title' => $this->t('Machine name'),
          '#default_value' => $field['name'] ?? '',
          '#machine_name' => [
            'exists' => [$this, 'fieldMachineNameExists'],
            'source' => [
              'workspace',
              'steps',
              $sid,
              'fields',
              $fid,
              'label',
            ],
          ],
          '#required' => TRUE,
          '#ajax' => [
            'callback' => '::fieldChanged',
            'wrapper' => 'assessment-builder-workspace',
            'event' => 'change',
          ],
        ];
        $form['workspace']['steps'][$sid]['fields'][$fid]['type'] = ['#type' => 'select', '#title' => $this->t('Type'), '#options' => ['textfield' => 'Text', 'email' => 'Email', 'number' => 'Number', 'textarea' => 'Textarea', 'select' => 'Select', 'radios' => 'Radios', 'checkbox' => 'Checkbox'], '#default_value' => $field['type'] ?? 'textfield'];
        $form['workspace']['steps'][$sid]['fields'][$fid]['required'] = ['#type' => 'checkbox', '#title' => $this->t('Required'), '#default_value' => $field['required'] ?? FALSE];
        $form['workspace']['steps'][$sid]['fields'][$fid]['options_text'] = ['#type' => 'textarea', '#title' => $this->t('Options'), '#default_value' => $field['options_text']
          ?? $this->optionsToText($field['options'] ?? []), '#rows' => 2, '#description' => $this->t('For Select/Radios only. One per line: value|Label')];
      }
      $form['workspace']['steps'][$sid]['add_field'] = $this->ajaxButton($this->t('+ Add field'), 'addField', ['step' => $sid], ['button', 'button--small']);
    }
    $form['workspace']['add_step'] = $this->ajaxButton($this->t('+ Add step'), 'addStep', [], ['button', 'button--primary']);

    $field_options = $this->fieldOptions($draft);
    $form['workspace']['conditions_title'] = ['#markup' => '<hr><h2>' . $this->t('Conditional visibility') . '</h2><p>' . $this->t('Example: when Property type equals Residential, show Property purpose.') . '</p>'];
    foreach ($draft['conditions'] as $cid => $condition) {
      $form['workspace']['conditions'][$cid] = ['#type' => 'container', '#attributes' => ['class' => ['workflow-rule']]];
      $form['workspace']['conditions'][$cid]['source'] = ['#type' => 'select', '#title' => $this->t('When field'), '#options' => $field_options, '#default_value' => $condition['source'] ?? ''];
      $form['workspace']['conditions'][$cid]['operator'] = ['#type' => 'select', '#title' => $this->t('Operator'), '#options' => ['equals' => 'Equals', 'not_equals' => 'Does not equal'], '#default_value' => $condition['operator'] ?? 'equals'];
      $form['workspace']['conditions'][$cid]['value'] = ['#type' => 'textfield', '#title' => $this->t('Value'), '#default_value' => $condition['value'] ?? ''];
      $form['workspace']['conditions'][$cid]['target'] = ['#type' => 'select', '#title' => $this->t('Show field'), '#options' => $field_options, '#default_value' => $condition['target'] ?? ''];
      $form['workspace']['conditions'][$cid]['remove'] = $this->ajaxButton($this->t('Remove'), 'removeCondition', ['condition' => $cid], ['button', 'button--small', 'button--danger']);
    }
    $form['workspace']['add_condition'] = $this->ajaxButton($this->t('+ Add condition'), 'addCondition', [], ['button', 'button--small']);

    $form['workspace']['rules_title'] = ['#markup' => '<hr><h2>' . $this->t('Result rules') . '</h2><p>' . $this->t('Rules are checked in order. The first matching rule becomes the result.') . '</p>'];
    foreach ($draft['rules'] as $rid => $rule) {
      $form['workspace']['rules'][$rid] = ['#type' => 'container', '#attributes' => ['class' => ['workflow-rule']]];
      $form['workspace']['rules'][$rid]['field'] = ['#type' => 'select', '#title' => $this->t('Field'), '#options' => $field_options, '#default_value' => $rule['field'] ?? ''];
      $form['workspace']['rules'][$rid]['operator'] = ['#type' => 'select', '#title' => $this->t('Operator'), '#options' => ['equals' => 'Equals', 'not_equals' => 'Does not equal', 'contains' => 'Contains'], '#default_value' => $rule['operator'] ?? 'equals'];
      $form['workspace']['rules'][$rid]['value'] = ['#type' => 'textfield', '#title' => $this->t('Value'), '#default_value' => $rule['value'] ?? ''];
      $form['workspace']['rules'][$rid]['result'] = ['#type' => 'textfield', '#title' => $this->t('Result'), '#default_value' => $rule['result'] ?? '', '#required' => TRUE];
      $form['workspace']['rules'][$rid]['remove'] = $this->ajaxButton($this->t('Remove'), 'removeRule', ['rule' => $rid], ['button', 'button--small', 'button--danger']);
    }
    $form['workspace']['add_rule'] = $this->ajaxButton($this->t('+ Add result rule'), 'addRule', [], ['button', 'button--small']);
    $form['workspace']['default_result'] = ['#type' => 'textfield', '#title' => $this->t('Default result'), '#default_value' => $draft['default_result'] ?? 'Assessment completed', '#required' => TRUE];
    $form['actions'] = ['#type' => 'actions'];
    $form['actions']['save'] = ['#type' => 'submit', '#value' => $this->t('Save assessment'), '#button_type' => 'primary'];
    $form['actions']['cancel'] = ['#type' => 'link', '#title' => $this->t('Cancel'), '#url' => Url::fromRoute('workflow_engine.admin'), '#attributes' => ['class' => ['button']]];
    return $form;
  }

  protected function ajaxButton($label, string $submit, array $context, array $classes): array
  {
    return ['#type' => 'submit', '#value' => $label, '#submit' => ['::' . $submit], '#ajax' => ['callback' => '::refreshWorkspace', 'wrapper' => 'assessment-builder-workspace'], '#limit_validation_errors' => [], '#attributes' => ['class' => $classes], '#workflow_context' => $context];
  }

  public function assessmentExists(string $id): bool
  {
    if ($id === $this->assessmentId()) return FALSE;
    return !\Drupal::config('workflow_engine.assessment.' . $id)->isNew();
  }
  public function refreshWorkspace(array &$form, FormStateInterface $form_state)
  {
    return $form['workspace'];
  }
  public function fieldChanged(array &$form, FormStateInterface $form_state)
  {
    $this->syncDraft($form_state);
    $form_state->setRebuild(TRUE);
    return $form['workspace'];
  }
  protected function triggerContext(FormStateInterface $form_state): array
  {
    return (array) ($form_state->getTriggeringElement()['#workflow_context'] ?? []);
  }
  public function addStep(array &$form, FormStateInterface $form_state): void
  {
    $d = $this->syncDraft($form_state);
    $id = $d['_next_step']++;
    $d['steps'][$id] = ['title' => 'Step ' . (count($d['steps']) + 1), 'fields' => []];
    $form_state->set('assessment_draft', $d)->setRebuild();
  }
  public function removeStep(array &$form, FormStateInterface $form_state): void
  {
    $d = $this->syncDraft($form_state);
    $c = $this->triggerContext($form_state);
    unset($d['steps'][$c['step']]);
    $form_state->set('assessment_draft', $d)->setRebuild();
  }
  public function addField(array &$form, FormStateInterface $form_state): void
  {
    $d = $this->syncDraft($form_state);
    $c = $this->triggerContext($form_state);
    $fid = $d['_next_field']++;
    $d['steps'][$c['step']]['fields'][$fid] = ['label' => '', 'name' => '', 'type' => 'textfield', 'required' => FALSE, 'options_text' => ''];
    $form_state->set('assessment_draft', $d)->setRebuild();
  }
  public function removeField(array &$form, FormStateInterface $form_state): void
  {
    $d = $this->syncDraft($form_state);
    $c = $this->triggerContext($form_state);
    $old = $d['steps'][$c['step']]['fields'][$c['field']]['name'] ?? '';
    unset($d['steps'][$c['step']]['fields'][$c['field']]);
    foreach ($d['conditions'] as $k => $r) if (($r['source'] ?? '') === $old || ($r['target'] ?? '') === $old) unset($d['conditions'][$k]);
    foreach ($d['rules'] as $k => $r) if (($r['field'] ?? '') === $old) unset($d['rules'][$k]);
    $form_state->set('assessment_draft', $d)->setRebuild();
  }
  public function addCondition(array &$form, FormStateInterface $form_state): void
  {
    $d = $this->syncDraft($form_state);
    $id = $d['_next_condition']++;
    $d['conditions'][$id] = ['source' => '', 'operator' => 'equals', 'value' => '', 'target' => ''];
    $form_state->set('assessment_draft', $d)->setRebuild();
  }
  public function removeCondition(array &$form, FormStateInterface $form_state): void
  {
    $d = $this->syncDraft($form_state);
    $c = $this->triggerContext($form_state);
    unset($d['conditions'][$c['condition']]);
    $form_state->set('assessment_draft', $d)->setRebuild();
  }
  public function addRule(array &$form, FormStateInterface $form_state): void
  {
    $d = $this->syncDraft($form_state);
    $id = $d['_next_rule']++;
    $d['rules'][$id] = ['field' => '', 'operator' => 'equals', 'value' => '', 'result' => ''];
    $form_state->set('assessment_draft', $d)->setRebuild();
  }
  public function removeRule(array &$form, FormStateInterface $form_state): void
  {
    $d = $this->syncDraft($form_state);
    $c = $this->triggerContext($form_state);
    unset($d['rules'][$c['rule']]);
    $form_state->set('assessment_draft', $d)->setRebuild();
  }

  public function validateForm(array &$form, FormStateInterface $form_state): void
  {
    $draft = $this->syncDraft($form_state);
    $seen = [];
    foreach ($draft['steps'] as $sid => $step) foreach ($step['fields'] as $fid => $field) {
      $name = trim($field['name'] ?? '');
      if (!$name) continue;
      if (!preg_match('/^[a-z][a-z0-9_]*$/', $name)) $form_state->setErrorByName("workspace][steps][$sid][fields][$fid][name", $this->t('Machine name must start with a lowercase letter and contain only lowercase letters, numbers and underscores.'));
      if (isset($seen[$name])) $form_state->setErrorByName("workspace][steps][$sid][fields][$fid][name", $this->t('Machine name %name is duplicated.', ['%name' => $name]));
      $seen[$name] = TRUE;
    }
    $slug = trim((string)$form_state->getValue(['meta', 'slug']));
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) $form_state->setErrorByName('meta][slug', $this->t('URL slug may contain lowercase letters, numbers and hyphens only.'));
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    $draft = $this->syncDraft($form_state);
    unset($draft['_next_step'], $draft['_next_field'], $draft['_next_condition'], $draft['_next_rule']);
    foreach ($draft['steps'] as &$step) foreach ($step['fields'] as &$field) {
      $field['options'] = $this->parseOptions($field['options_text'] ?? '');
      unset($field['options_text']);
    }
    $meta = $form_state->getValue('meta');
    $id = $this->assessmentId() ?: $meta['id'];
    \Drupal::configFactory()->getEditable('workflow_engine.assessment.' . $id)->setData(['id' => $id, 'label' => $meta['label'], 'slug' => $meta['slug'], 'published' => (bool)$meta['published'], 'definition' => $draft])->save();
    $this->messenger()->addStatus($this->t('Assessment %label saved.', ['%label' => $meta['label']]));
    $form_state->setRedirect('workflow_engine.admin');
  }
  protected function parseOptions(string $text): array
  {
    $out = [];
    foreach (preg_split('/\R/', trim($text)) as $line) {
      if (trim($line) === '') continue;
      [$v, $l] = array_pad(explode('|', $line, 2), 2, '');
      $v = trim($v);
      if ($v !== '') $out[$v] = trim($l) !== '' ? trim($l) : $v;
    }
    return $out;
  }
}
