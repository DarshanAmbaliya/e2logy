<?php

namespace Drupal\workflow_engine\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

class AssessmentController extends ControllerBase
{
  protected function all(): array
  {
    $out = [];
    foreach (\Drupal::configFactory()->listAll('workflow_engine.assessment.') as $name) {
      $d = \Drupal::config($name)->getRawData();
      if ($d) $out[] = $d;
    }
    usort($out, fn ($a, $b) => strnatcasecmp($a['label'] ?? '', $b['label'] ?? ''));
    return $out;
  }
  public function listing(): array
  {
    $rows = [];
    foreach ($this->all() as $a) {
      $id = $a['id'];
      $ops = [Link::fromTextAndUrl($this->t('Edit'), Url::fromRoute('workflow_engine.edit', ['assessment_id' => $id]))->toString(), Link::fromTextAndUrl($this->t('Delete'), Url::fromRoute('workflow_engine.delete', ['assessment_id' => $id]))->toString()];
      if (!empty($a['published'])) $ops[] = Link::fromTextAndUrl($this->t('View'), Url::fromRoute('workflow_engine.execute', ['slug' => $a['slug']]))->toString();
      $rows[] = [['data' => $a['label']], ['data' => $id], ['data' => $a['slug']], ['data' => !empty($a['published']) ? $this->t('Published') : $this->t('Draft')], ['data' => ['#markup' => implode(' | ', $ops)]]];
    }
    return ['actions' => ['#type' => 'container', 'add' => ['#type' => 'link', '#title' => $this->t('+ Add assessment'), '#url' => Url::fromRoute('workflow_engine.add'), '#attributes' => ['class' => ['button', 'button--primary']]], 'submissions' => ['#type' => 'link', '#title' => $this->t('Submissions'), '#url' => Url::fromRoute('workflow_engine.submissions'), '#attributes' => ['class' => ['button']]]], 'table' => ['#type' => 'table', '#header' => [$this->t('Name'), $this->t('Machine name'), $this->t('Slug'), $this->t('Status'), $this->t('Operations')], '#rows' => $rows, '#empty' => $this->t('No assessments yet.')]];
  }
  public function frontendListing(): array
  {
    $items = [];
    foreach ($this->all() as $a) if (!empty($a['published'])) $items[] = Link::fromTextAndUrl($a['label'], Url::fromRoute('workflow_engine.execute', ['slug' => $a['slug']]))->toRenderable();
    return ['#theme' => 'item_list', '#items' => $items, '#empty' => $this->t('No assessments available.')];
  }
}
