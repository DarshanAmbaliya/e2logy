<?php

namespace Drupal\workflow_engine\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class SubmissionDeleteForm extends ConfirmFormBase
{
  protected int $submissionId;

  public function getFormId(): string
  {
    return 'workflow_engine_submission_delete_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $submission_id = NULL): array
  {
    $this->submissionId = (int) $submission_id;

    $exists = \Drupal::database()
      ->select('workflow_engine_submission', 's')
      ->condition('id', $this->submissionId)
      ->countQuery()
      ->execute()
      ->fetchField();

    if (!$exists) {
      throw new NotFoundHttpException();
    }

    return parent::buildForm($form, $form_state);
  }

  public function getQuestion(): string
  {
    return $this->t(
      'Are you sure you want to delete submission #@id?',
      ['@id' => $this->submissionId]
    );
  }

  public function getCancelUrl(): Url
  {
    return Url::fromRoute('workflow_engine.submissions');
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    \Drupal::database()
      ->delete('workflow_engine_submission')
      ->condition('id', $this->submissionId)
      ->execute();

    $this->messenger()->addStatus(
      $this->t(
        'Submission #@id has been deleted.',
        ['@id' => $this->submissionId]
      )
    );

    $form_state->setRedirect('workflow_engine.submissions');
  }
}