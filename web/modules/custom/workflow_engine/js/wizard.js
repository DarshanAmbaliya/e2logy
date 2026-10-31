(function (Drupal, once) {
  Drupal.behaviors.workflowEngineConditions = {
    attach(context) {
      once('workflow-engine-conditions', '.workflow-assessment-form', context).forEach((form) => {
        const conditions = JSON.parse(form.dataset.conditions || '[]');
        const update = () => {
          conditions.forEach((rule) => {
            const source = form.querySelector(`[name="answers[${rule.source}]"]`) || form.querySelector(`[name="answers[${rule.source}][]"]`);
            const targets = form.querySelectorAll(`[data-workflow-field="${rule.target}"]`);
            if (!source || !targets.length) return;
            let value;
            const radios = form.querySelectorAll(`[name="answers[${rule.source}]"]`);
            if (radios.length > 1) { const checked = form.querySelector(`[name="answers[${rule.source}]"]:checked`); value = checked ? checked.value : ''; }
            else if (source.type === 'checkbox') value = source.checked ? '1' : '0';
            else value = source.value;
            const show = String(value) === String(rule.value);
            targets.forEach((target) => { target.hidden = !show; target.querySelectorAll('input,select,textarea').forEach((el) => el.disabled = !show); });
          });
        };
        form.addEventListener('change', update); update();
      });
    }
  };
})(Drupal, once);
