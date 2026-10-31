<?php

namespace Drupal\workflow_engine\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class AssessmentWizardForm extends FormBase
{
  protected array $assessment = [];
  protected ?object $submission = NULL;
  protected array $existingAnswers = [];

  protected function loadSubmission(int $submissionId): ?object
  {
    if (!$submissionId) {
      return NULL;
    }

    $submission = \Drupal::database()
      ->select('workflow_engine_submission', 's')
      ->fields('s')
      ->condition('id', $submissionId)
      ->execute()
      ->fetchObject();

    if (!$submission) {
      throw new NotFoundHttpException();
    }

    return $submission;
  }

  public function getFormId(): string
  {
    return 'workflow_engine_assessment_wizard';
  }
  protected function load(string $slug): array
  {
    foreach (\Drupal::configFactory()->listAll('workflow_engine.assessment.') as $name) {
      $d = \Drupal::config($name)->getRawData();
      if (!empty($d['published']) && ($d['slug'] ?? '') === $slug) return $d;
    }
    throw new NotFoundHttpException();
  }
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $slug = NULL
  ): array {
    $this->assessment = $this->load((string) $slug);

    $request = \Drupal::request();

    $submissionId = (int) $request->query->get('submission_id', 0);

    if ($submissionId) {
      $this->submission = $this->loadSubmission($submissionId);

      $this->existingAnswers = json_decode(
        $this->submission->answers ?? '{}',
        TRUE
      ) ?: [];

      $form['submission_id'] = [
        '#type' => 'hidden',
        '#value' => $submissionId,
      ];
    }

    $def = $this->assessment['definition'];
    $steps = array_values($def['steps'] ?? []);
    if (!$steps) throw new NotFoundHttpException();
    $current = (int)($form_state->get('step') ?? 0);
    $current = max(0, min($current, count($steps) - 1));
    $answers = (array)($form_state->get('answers') ?? []);
    $step = $steps[$current];
    $form['#attributes']['class'][] = 'workflow-assessment-form';
    $form['#attributes']['data-conditions'] = json_encode(array_values($def['conditions'] ?? []));
    $form['#attached']['library'][] = 'workflow_engine/wizard';
    $form['progress'] = ['#markup' => '<p><strong>' . $this->t('Step @current of @total', ['@current' => $current + 1, '@total' => count($steps)]) . '</strong></p><h2>' . htmlspecialchars($step['title'] ?? '') . '</h2>'];
    $form['answers'] = ['#tree' => TRUE];
    $targets = [];
    foreach ($def['conditions'] ?? [] as $c) $targets[$c['target'] ?? ''] = TRUE;
    foreach ($step['fields'] ?? [] as $field) {
      $name = $field['name'];
      $type = $field['type'] ?? 'textfield';
      if ($type === 'radio') {
        $type = 'radios';
      }
      $el = ['#type' => $type, '#title' => $field['label'] ?? $name, '#default_value' => $answers[$name]
        ?? $this->existingAnswers[$name]
        ?? NULL, '#required' => !empty($field['required']) && !isset($targets[$name]), '#wrapper_attributes' => ['data-workflow-field' => $name]];
      if (in_array($type, ['select', 'radios'], TRUE)) $el['#options'] = $field['options'] ?? [];
      if ($type === 'number') $el['#step'] = 'any';
      $form['answers'][$name] = $el;
    }
    $form['actions'] = ['#type' => 'actions'];
    if ($current > 0) $form['actions']['previous'] = ['#type' => 'submit', '#value' => $this->t('Previous'), '#submit' => ['::previousStep'], '#limit_validation_errors' => []];
    if ($current < count($steps) - 1) $form['actions']['next'] = ['#type' => 'submit', '#value' => $this->t('Next'), '#submit' => ['::nextStep'], '#button_type' => 'primary'];
    else $form['actions']['submit'] = ['#type' => 'submit', '#value' => $this->t('Submit assessment'), '#button_type' => 'primary'];
    return $form;
  }
  protected function capture(FormStateInterface $form_state): void
  {
    $a = (array)($form_state->get('answers') ?? []);
    $a = array_replace($a, (array)$form_state->getValue('answers'));
    $form_state->set('answers', $a);
  }
  public function nextStep(array &$form, FormStateInterface $form_state): void
  {
    $this->capture($form_state);
    $form_state->set('step', (int)$form_state->get('step') + 1)->setRebuild();
  }
  public function previousStep(array &$form, FormStateInterface $form_state): void
  {
    $this->capture($form_state);
    $form_state->set('step', max(0, (int)$form_state->get('step') - 1))->setRebuild();
  }
  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    $this->capture($form_state);
    $answers = (array)$form_state->get('answers');
    $def = $this->assessment['definition'];
    $result = $def['default_result'] ?? 'Assessment completed';
    foreach ($def['rules'] ?? [] as $rule) {
      $actual = (string)($answers[$rule['field'] ?? ''] ?? '');
      $expected = (string)($rule['value'] ?? '');
      $match = match ($rule['operator'] ?? 'equals') {
        'not_equals' => $actual !== $expected,
        'contains' => str_contains($actual, $expected),
        default => $actual === $expected
      };
      if ($match) {
        $result = $rule['result'] ?? $result;
        break;
      }
    }
    $database = \Drupal::database();
    $schema = $database->schema();
    $fields = ['created' => time(), 'answers' => json_encode($answers), 'result' => $result];
    if ($schema->fieldExists('workflow_engine_submission', 'assessment_id')) {
      $fields['assessment_id'] = $this->assessment['id'];
    } elseif ($schema->fieldExists('workflow_engine_submission', 'workflow_id')) {
      $fields['workflow_id'] = $this->assessment['id'];
    }
    if ($schema->fieldExists('workflow_engine_submission', 'assessment_label')) {
      $fields['assessment_label'] = $this->assessment['label'];
    } elseif ($schema->fieldExists('workflow_engine_submission', 'workflow_label')) {
      $fields['workflow_label'] = $this->assessment['label'];
    }

    if ($this->submission) {
      $database
        ->update('workflow_engine_submission')
        ->fields($fields)
        ->condition('id', $this->submission->id)
        ->execute();
    } else {
      $database
        ->insert('workflow_engine_submission')
        ->fields($fields)
        ->execute();
    }

    if ($this->submission) {
      $this->messenger()->addStatus(
        $this->t('Submission updated successfully. Result: @result', [
          '@result' => $result,
        ])
      );
    }
    else {
      $this->messenger()->addStatus(
        $this->t('Your result: @result', [
          '@result' => $result,
        ])
      );
    }
    $form_state->setRedirect('workflow_engine.frontend_list');
  }
}
