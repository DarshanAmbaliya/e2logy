<?php

namespace Drupal\workflow_engine\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Administrative assessment listing.
 */
final class AssessmentListForm extends FormBase
{

  public function getFormId(): string
  {
    return 'workflow_engine_assessment_list_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state): array
  {
    $rows = [];
    $names = \Drupal::configFactory()->listAll('workflow_engine.assessment.');

    foreach ($names as $name) {
      $assessment = \Drupal::config($name)->getRawData();
    
      if (!$assessment || empty($assessment['id'])) {
        continue;
      }
    
      $id = (string) $assessment['id'];
    
      // Drupal standard operations dropdown.
      $operations = [
        '#type' => 'operations',
        '#links' => [
          'edit' => [
            'title' => $this->t('Edit'),
            'url' => Url::fromRoute('workflow_engine.edit', [
              'assessment_id' => $id,
            ]),
          ],
          'delete' => [
            'title' => $this->t('Delete'),
            'url' => Url::fromRoute('workflow_engine.delete', [
              'assessment_id' => $id,
            ]),
          ],
        ],
      ];
    
      // Show View only for published assessments.
      if (!empty($assessment['published']) && !empty($assessment['slug'])) {
        $operations['#links']['view'] = [
          'title' => $this->t('View'),
          'url' => Url::fromRoute('workflow_engine.execute', [
            'slug' => $assessment['slug'],
          ]),
        ];
      }
    
      $rows[] = [
        $assessment['label'] ?? $id,
        $id,
        $assessment['slug'] ?? '',
        !empty($assessment['published'])
          ? $this->t('Published')
          : $this->t('Unpublished'),
        [
          'data' => $operations,
        ],
      ];
    }

    $form['actions'] = [
      '#type' => 'actions',
      'add' => [
        '#type' => 'link',
        '#title' => $this->t('Add assessment'),
        '#url' => Url::fromRoute('workflow_engine.add'),
        '#attributes' => ['class' => ['button', 'button--primary']],
      ],
      'submissions' => [
        '#type' => 'link',
        '#title' => $this->t('Submissions'),
        '#url' => Url::fromRoute('workflow_engine.submissions'),
        '#attributes' => ['class' => ['button']],
      ],
    ];

    $form['assessments'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Name'),
        $this->t('Machine name'),
        $this->t('Slug'),
        $this->t('Status'),
        $this->t('Operations'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No assessments yet.'),
    ];

    return $form;
  }

  public function submitForm(array &$form, FormStateInterface $form_state): void
  {
    // Listing form has no submit operation.
  }
}
