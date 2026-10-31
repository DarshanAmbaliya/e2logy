<?php

namespace Drupal\workflow_engine\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Controller for assessment submissions.
 */
class SubmissionController extends ControllerBase
{

  /**
   * Get the assessment slug for a submission.
   *
   * Returns an empty string when the original assessment
   * has been deleted.
   */
  protected function getAssessmentSlug(object $submission): string
  {
    $schema = \Drupal::database()->schema();

    $label_column = $schema->fieldExists(
      'workflow_engine_submission',
      'assessment_label'
    ) ? 'assessment_label' : 'workflow_label';

    $label = (string) ($submission->{$label_column} ?? '');

    if ($label === '') {
      return '';
    }

    foreach (\Drupal::configFactory()->listAll('workflow_engine.assessment.') as $name) {
      $data = \Drupal::config($name)->getRawData();

      if (($data['label'] ?? '') === $label) {
        return (string) ($data['slug'] ?? '');
      }
    }

    // Assessment no longer exists.
    return '';
  }

  /**
   * Get the assessment label column.
   */
  protected function labelColumn(): string
  {
    $schema = \Drupal::database()->schema();

    return $schema->fieldExists(
      'workflow_engine_submission',
      'assessment_label'
    ) ? 'assessment_label' : 'workflow_label';
  }

  /**
   * Display all submissions.
   */
  public function listing(): array
  {
    $rows = [];
    $label_column = $this->labelColumn();

    $query = \Drupal::database()
      ->select('workflow_engine_submission', 's')
      ->fields('s')
      ->orderBy('created', 'DESC')
      ->execute();

    foreach ($query as $submission) {

      /*
       * ------------------------------------------------------------
       * View operation.
       * ------------------------------------------------------------
       */
      $operation_links = [
        'view' => [
          'title' => $this->t('View response'),
          'url' => Url::fromRoute(
            'workflow_engine.submission',
            [
              'submission_id' => $submission->id,
            ]
          ),
        ],
      ];

      /*
       * ------------------------------------------------------------
       * Find original assessment.
       * ------------------------------------------------------------
       *
       * If the assessment still exists, add Edit.
       *
       * If the assessment was deleted, $slug will be empty.
       * In that case we MUST NOT create workflow_engine.execute
       * because that route requires a non-empty {slug}.
       */
      $slug = $this->getAssessmentSlug($submission);

      if ($slug !== '') {
        $operation_links['edit'] = [
          'title' => $this->t('Edit'),
          'url' => Url::fromRoute(
            'workflow_engine.execute',
            [
              'slug' => $slug,
            ],
            [
              'query' => [
                'submission_id' => $submission->id,
              ],
            ]
          ),
        ];
      }

      /*
       * ------------------------------------------------------------
       * Delete operation.
       * ------------------------------------------------------------
       *
       * The submission itself can still be deleted even when
       * the original assessment no longer exists.
       */
      $operation_links['delete'] = [
        'title' => $this->t('Delete'),
        'url' => Url::fromRoute(
          'workflow_engine.submission_delete',
          [
            'submission_id' => $submission->id,
          ]
        ),
      ];

      $operations = [
        'data' => [
          '#type' => 'operations',
          '#links' => $operation_links,
        ],
      ];

      /*
       * ------------------------------------------------------------
       * Assessment label.
       * ------------------------------------------------------------
       *
       * If the original assessment was deleted, clearly show it.
       */
      $assessment_label = (string) ($submission->{$label_column} ?? '');

      if ($slug === '') {
        $assessment_label .= ' ' . $this->t('(Assessment deleted)');
      }

      /*
       * ------------------------------------------------------------
       * Submission row.
       * ------------------------------------------------------------
       */
      $rows[] = [
        Link::fromTextAndUrl(
          '#' . $submission->id,
          Url::fromRoute(
            'workflow_engine.submission',
            [
              'submission_id' => $submission->id,
            ]
          )
        )->toString(),

        $assessment_label,

        date('Y-m-d H:i', (int) $submission->created),

        $submission->result ?? '',

        $operations,
      ];
    }

    return [
      '#type' => 'table',
      '#header' => [
        $this->t('ID'),
        $this->t('Assessment'),
        $this->t('Created'),
        $this->t('Result'),
        $this->t('Operations'),
      ],
      '#rows' => $rows,
      '#empty' => $this->t('No submissions.'),
    ];
  }

  /**
   * Display a single submission.
   */
  public function detail($submission_id): array
  {
    $submission = \Drupal::database()
      ->select('workflow_engine_submission', 's')
      ->fields('s')
      ->condition('id', $submission_id)
      ->execute()
      ->fetchObject();

    if (!$submission) {
      throw new NotFoundHttpException();
    }

    $answers = json_decode($submission->answers ?? '{}', TRUE) ?: [];

    $rows = [];

    foreach ($answers as $key => $value) {
      $rows[] = [
        $key,
        is_array($value)
          ? implode(', ', $value)
          : (string) $value,
      ];
    }

    return [
      'summary' => [
        '#markup' => '<p><strong>'
          . $this->t('Result:')
          . '</strong> '
          . htmlspecialchars(
            $submission->result ?? '',
            ENT_QUOTES,
            'UTF-8'
          )
          . '</p>',
      ],

      'answers' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Field'),
          $this->t('Answer'),
        ],
        '#rows' => $rows,
      ],
    ];
  }
}
