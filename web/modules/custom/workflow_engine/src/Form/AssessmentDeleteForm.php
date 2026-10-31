<?php

namespace Drupal\workflow_engine\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

class AssessmentDeleteForm extends ConfirmFormBase
{
  protected string $id = '';
  public function getFormId(): string
  {
    return 'workflow_engine_delete_assessment';
  }
  public function buildForm(array $form, FormStateInterface $form_state, $assessment_id = NULL): array
  {
    $this->id = (string)$assessment_id;
    return parent::buildForm($form, $form_state);
  }
  public function getQuestion()
  {
    return $this->t('Delete assessment %id?', ['%id' => $this->id]);
  }
  public function getCancelUrl()
  {
    return Url::fromRoute('workflow_engine.admin');
  }
  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    \Drupal::configFactory()->getEditable('workflow_engine.assessment.' . $this->id)->delete();
    $this->messenger()->addStatus($this->t('Assessment deleted.'));
    $form_state->setRedirectUrl($this->getCancelUrl());
  }
}
