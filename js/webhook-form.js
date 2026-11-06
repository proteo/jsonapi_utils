/**
 * @file
 * "Select all" checkboxes for the enabled entities table.
 */
(function (Drupal, once) {
  Drupal.behaviors.jsonapiUtilsWebhookForm = {
    attach(context) {
      once('jsonapi-utils-select-all', '.jsonapi-utils-select-all', context).forEach((toggle) => {
        const group = toggle.dataset.group;
        const boxes = () => context.querySelectorAll(`input[data-group="${group}"].jsonapi-utils-bundle`);
        const sync = () => {
          const all = Array.from(boxes());
          toggle.checked = all.length > 0 && all.every((box) => box.checked);
        };
        toggle.addEventListener('change', () => {
          boxes().forEach((box) => { box.checked = toggle.checked; });
        });
        boxes().forEach((box) => box.addEventListener('change', sync));
        sync();
      });
    },
  };
}(Drupal, once));
